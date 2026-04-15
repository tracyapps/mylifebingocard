<?php
/**
 * REST endpoint for feedback submissions.
 * POST /bingo/v1/feedback
 */
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'bingo/v1', '/feedback', [
		'methods'             => 'POST',
		'callback'            => 'bingo_rest_submit_feedback',
		'permission_callback' => '__return_true',
		'args'                => [
			'type'         => [ 'type' => 'string',  'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
			'message'      => [ 'type' => 'string',  'required' => true,  'sanitize_callback' => 'sanitize_textarea_field' ],
			'email'        => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_email',         'default' => '' ],
			'wants_reply'  => [ 'type' => 'boolean', 'required' => false, 'default' => false ],
			'page_url'     => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'esc_url_raw',            'default' => '' ],
			'browser_info' => [ 'type' => 'object',  'required' => false, 'default' => null ],
			'screenshot'   => [ 'type' => 'string',  'required' => false, 'default' => '' ],
		],
	] );
} );

function bingo_rest_submit_feedback( WP_REST_Request $req ): WP_REST_Response|WP_Error {
	$settings = bingo_get_feedback_settings();

	// Validate type
	$allowed_types = array_keys( $settings['types'] );
	$type          = $req->get_param( 'type' );
	if ( ! in_array( $type, $allowed_types, true ) ) {
		return new WP_Error( 'invalid_type', 'Invalid feedback type.', [ 'status' => 400 ] );
	}

	// Validate message
	$message = trim( $req->get_param( 'message' ) );
	if ( strlen( $message ) < 3 ) {
		return new WP_Error( 'message_too_short', 'Please enter a longer message.', [ 'status' => 400 ] );
	}
	if ( strlen( $message ) > 5000 ) {
		return new WP_Error( 'message_too_long', 'Message must be 5,000 characters or fewer.', [ 'status' => 400 ] );
	}

	// Email validation
	$email       = $req->get_param( 'email' );
	$wants_reply = (bool) $req->get_param( 'wants_reply' );
	$is_logged_in = is_user_logged_in();

	if ( ! $is_logged_in && empty( $email ) ) {
		return new WP_Error( 'email_required', 'Email address is required.', [ 'status' => 400 ] );
	}
	if ( ! empty( $email ) && ! is_email( $email ) ) {
		return new WP_Error( 'invalid_email', 'Invalid email address.', [ 'status' => 400 ] );
	}

	// ── Create post ───────────────────────────────────────────────────────────

	$browser_raw = $req->get_param( 'browser_info' );

	$post_id = wp_insert_post( [
		'post_type'    => 'bingo_feedback',
		'post_title'   => 'Feedback',  // placeholder; updated to #ID below
		'post_content' => $message,
		'post_status'  => 'publish',
	], true );

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'save_failed', 'Could not save feedback. Please try again.', [ 'status' => 500 ] );
	}

	// Update title to #ID now that we have the ID
	wp_update_post( [ 'ID' => $post_id, 'post_title' => '#' . $post_id ] );

	// ── Save meta fields ──────────────────────────────────────────────────────

	update_post_meta( $post_id, '_fb_type',        $type );
	update_post_meta( $post_id, '_fb_email',       $email );
	update_post_meta( $post_id, '_fb_page_url',    $req->get_param( 'page_url' ) );
	update_post_meta( $post_id, '_fb_wants_reply', $wants_reply ? 1 : 0 );
	update_post_meta( $post_id, '_fb_user_id',     is_user_logged_in() ? get_current_user_id() : 0 );
	update_post_meta( $post_id, '_fb_status',      'new' );

	if ( $browser_raw && is_array( $browser_raw ) ) {
		$safe_browser = bingo_feedback_sanitize_browser_info( $browser_raw );
		update_post_meta( $post_id, '_fb_browser_info', wp_json_encode( $safe_browser ) );
	}

	// ── Save screenshot ───────────────────────────────────────────────────────

	$screenshot_data = $req->get_param( 'screenshot' );
	if ( ! empty( $screenshot_data ) && $settings['enable_screenshot'] ) {
		$att_id = bingo_feedback_save_screenshot( $screenshot_data, $post_id );
		if ( $att_id ) {
			update_post_meta( $post_id, '_fb_screenshot_id', $att_id );
		}
	}

	// ── Send notification email ───────────────────────────────────────────────

	bingo_feedback_notify( $post_id, $settings );

	return new WP_REST_Response( [ 'success' => true, 'id' => $post_id ], 201 );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function bingo_feedback_sanitize_browser_info( array $raw ): array {
	$allowed = [ 'userAgent', 'platform', 'language', 'screenWidth', 'screenHeight', 'viewportWidth', 'viewportHeight', 'colorDepth' ];
	$out     = [];
	foreach ( $allowed as $key ) {
		if ( isset( $raw[ $key ] ) ) {
			$out[ $key ] = is_string( $raw[ $key ] )
				? sanitize_text_field( $raw[ $key ] )
				: (int) $raw[ $key ];
		}
	}
	return $out;
}

function bingo_feedback_save_screenshot( string $data_url, int $post_id ): int|false {
	// Expect: data:image/jpeg;base64,...
	if ( ! preg_match( '/^data:image\/(jpeg|png|webp);base64,(.+)$/s', $data_url, $m ) ) {
		return false;
	}

	$ext  = $m[1] === 'jpeg' ? 'jpg' : $m[1];
	$data = base64_decode( $m[2], true );

	if ( ! $data ) {
		return false;
	}
	// 4MB hard limit on screenshot
	if ( strlen( $data ) > 4 * 1024 * 1024 ) {
		return false;
	}

	$filename = 'feedback-' . $post_id . '-' . time() . '.' . $ext;
	$upload   = wp_upload_bits( $filename, null, $data );

	if ( ! empty( $upload['error'] ) ) {
		return false;
	}

	$mime     = 'image/' . ( $ext === 'jpg' ? 'jpeg' : $ext );
	$att_id   = wp_insert_attachment(
		[
			'post_mime_type' => $mime,
			'post_title'     => $filename,
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
		],
		$upload['file'],
		$post_id
	);

	if ( is_wp_error( $att_id ) ) {
		return false;
	}

	// Generate image sizes (thumbnail etc.)
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$meta = wp_generate_attachment_metadata( $att_id, $upload['file'] );
	wp_update_attachment_metadata( $att_id, $meta );

	return $att_id;
}

function bingo_feedback_notify( int $post_id, array $settings ): void {
	if ( empty( $settings['enable_email_notifications'] ) ) {
		return;
	}
	$notify = $settings['notify_email'] ?: get_option( 'admin_email' );
	if ( ! $notify ) {
		return;
	}

	$type_label  = $settings['types'][ get_post_meta( $post_id, '_fb_type', true ) ] ?? 'Feedback';
	$message     = get_post_field( 'post_content', $post_id );
	$email       = get_post_meta( $post_id, '_fb_email', true );
	$wants_reply = get_post_meta( $post_id, '_fb_wants_reply', true );
	$page_url    = get_post_meta( $post_id, '_fb_page_url', true );
	$browser_raw = get_post_meta( $post_id, '_fb_browser_info', true );
	$admin_url   = admin_url( "post.php?post={$post_id}&action=edit" );

	$subject = "[Bingo Feedback] New {$type_label}";

	$body  = "New feedback received on My Life Bingo Card.\n\n";
	$body .= "Type: {$type_label}\n";
	$body .= str_repeat( '-', 40 ) . "\n";
	$body .= $message . "\n";
	$body .= str_repeat( '-', 40 ) . "\n\n";
	$body .= 'From email : ' . ( $email ?: 'Not provided' ) . "\n";
	$body .= 'Wants reply: ' . ( $wants_reply ? 'Yes' : 'No' ) . "\n";
	$body .= 'Page       : ' . ( $page_url ?: 'Unknown' ) . "\n\n";

	if ( $browser_raw ) {
		$b     = json_decode( $browser_raw, true );
		$body .= "Browser info:\n";
		$body .= '  UA       : ' . ( $b['userAgent'] ?? 'n/a' ) . "\n";
		$body .= '  Platform : ' . ( $b['platform'] ?? 'n/a' ) . "\n";
		$body .= '  Screen   : ' . ( $b['screenWidth'] ?? '?' ) . '×' . ( $b['screenHeight'] ?? '?' ) . "\n";
		$body .= '  Viewport : ' . ( $b['viewportWidth'] ?? '?' ) . '×' . ( $b['viewportHeight'] ?? '?' ) . "\n\n";
	}

	$body .= "View in admin:\n{$admin_url}\n";

	$headers     = [ 'Content-Type: text/plain; charset=UTF-8' ];
	$attachments = [];

	$scr_id = (int) get_post_meta( $post_id, '_fb_screenshot_id', true );
	if ( $scr_id ) {
		$file = get_attached_file( $scr_id );
		if ( $file && file_exists( $file ) ) {
			$attachments[] = $file;
		}
	}

	wp_mail( $notify, $subject, $body, $headers, $attachments );
}
