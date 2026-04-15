<?php
/**
 * Feedback widget — CPT, admin UI, settings, and front-end modal injection.
 */
defined( 'ABSPATH' ) || exit;

// ── 1. Settings helper ────────────────────────────────────────────────────────

function bingo_get_feedback_settings(): array {
	$defaults = [
		'notify_email'              => get_option( 'admin_email' ),
		'enable_email_notifications' => true,
		'widget_title'              => 'Send Feedback',
		'widget_btn_label'          => 'Feedback',
		'success_message'           => "Thanks! We'll look into it soon.",
		'enable_screenshot'         => true,
		'enable_browser_info'       => true,
		'types'                     => [
			'bug'         => 'Bug Report',
			'feature'     => 'Feature Request',
			'improvement' => 'Improvement',
			'technical'   => 'Technical Error',
			'other'       => 'Other',
		],
	];
	$saved = get_option( 'bingo_feedback_settings', [] );
	// Deep-merge types so renamed labels survive
	if ( ! empty( $saved['types'] ) ) {
		$saved['types'] = array_merge( $defaults['types'], $saved['types'] );
	}
	return array_merge( $defaults, $saved );
}

// ── 2. CPT registration ───────────────────────────────────────────────────────

add_action( 'init', function () {
	register_post_type( 'bingo_feedback', [
		'label'               => 'Feedback',
		'labels'              => [
			'name'               => 'Feedback',
			'singular_name'      => 'Feedback Entry',
			'edit_item'          => 'View',
			'view_item'          => 'View',
			'search_items'       => 'Search',
			'not_found'          => 'No feedback found.',
			'not_found_in_trash' => 'No feedback in trash.',
		],
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false, // added manually below
		'show_in_rest'        => false,
		'capability_type'     => 'post',
		'supports'            => [ 'title' ], // title = auto-generated #ID
		'menu_icon'           => 'dashicons-format-chat',
		'rewrite'             => false,
	] );
} );

// ── 3. Admin menu ─────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
	add_menu_page(
		'Feedback',
		'Feedback',
		'edit_posts',
		'edit.php?post_type=bingo_feedback',
		'',
		'dashicons-format-chat',
		30
	);
	add_submenu_page(
		'edit.php?post_type=bingo_feedback',
		'All Submissions',
		'All Submissions',
		'edit_posts',
		'edit.php?post_type=bingo_feedback'
	);
	add_submenu_page(
		'edit.php?post_type=bingo_feedback',
		'Feedback Settings',
		'Settings',
		'manage_options',
		'bingo-feedback-settings',
		'bingo_feedback_settings_page'
	);
} );

// Highlight the menu entry when on the single edit screen
add_filter( 'parent_file', function ( $parent ) {
	global $post_type;
	if ( 'bingo_feedback' === $post_type ) {
		return 'edit.php?post_type=bingo_feedback';
	}
	return $parent;
} );

// ── 4. Admin list table columns ───────────────────────────────────────────────

add_filter( 'manage_bingo_feedback_posts_columns', function ( $cols ) {
	return [
		'cb'         => $cols['cb'],
		'title'      => '#',          // WP attaches edit/quick-edit row actions here
		'fb_type'    => 'Type',
		'fb_message' => 'Message',
		'fb_email'   => 'Email',
		'fb_status'  => 'Status',
		'fb_page'    => 'Page',
		'date'       => 'Date',
	];
} );

add_action( 'manage_bingo_feedback_posts_custom_column', function ( $col, $post_id ) {
	$type    = get_post_meta( $post_id, '_fb_type', true );
	$status  = get_post_meta( $post_id, '_fb_status', true ) ?: 'new';
	$email   = get_post_meta( $post_id, '_fb_email', true );
	$page    = get_post_meta( $post_id, '_fb_page_url', true );
	$message = get_post_field( 'post_content', $post_id );

	$type_colors = [
		'bug'         => '#c53030',
		'feature'     => '#276749',
		'improvement' => '#b7791f',
		'technical'   => '#c05621',
		'other'       => '#553c9a',
	];
	$status_colors = [
		'new'      => '#2b4c8c',
		'reviewed' => '#b7791f',
		'resolved' => '#276749',
		'closed'   => '#718096',
	];
	$type_labels   = bingo_get_feedback_settings()['types'];
	$status_labels = [ 'new' => 'New', 'reviewed' => 'Reviewed', 'resolved' => 'Resolved', 'closed' => 'Closed' ];

	switch ( $col ) {
		case 'fb_type':
			$label = $type_labels[ $type ] ?? ucfirst( (string) $type );
			$color = $type_colors[ $type ] ?? '#4a5568';
			printf(
				'<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:0.8em;font-weight:600;background:%s;color:#fff;">%s</span>',
				esc_attr( $color ),
				esc_html( $label )
			);
			// Hidden data used by Quick Edit JS to pre-populate fields
			printf(
				'<span class="fb-qe-data" data-post-id="%d" data-status="%s" data-type="%s" data-type-label="%s" data-email="%s" style="display:none"></span>',
				(int) $post_id,
				esc_attr( $status ),
				esc_attr( $type ),
				esc_attr( $label ),
				esc_attr( $email )
			);
			break;
		case 'fb_message':
			echo esc_html( wp_trim_words( $message, 14, '…' ) );
			break;
		case 'fb_email':
			if ( $email ) {
				printf( '<a href="mailto:%s">%s</a>', esc_attr( $email ), esc_html( $email ) );
			} else {
				echo '<span style="color:#a0aec0;">—</span>';
			}
			break;
		case 'fb_status':
			$label = $status_labels[ $status ] ?? ucfirst( $status );
			$color = $status_colors[ $status ] ?? '#4a5568';
			printf(
				'<span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:0.8em;font-weight:600;background:%s;color:#fff;">%s</span>',
				esc_attr( $color ),
				esc_html( $label )
			);
			break;
		case 'fb_page':
			if ( $page ) {
				$short = wp_parse_url( $page, PHP_URL_PATH ) ?: '/';
				printf(
					'<a href="%s" target="_blank" title="%s">%s</a>',
					esc_url( $page ),
					esc_attr( $page ),
					esc_html( $short )
				);
			} else {
				echo '<span style="color:#a0aec0;">—</span>';
			}
			break;
	}
}, 10, 2 );

// Make status + type sortable
add_filter( 'manage_edit-bingo_feedback_sortable_columns', function ( $cols ) {
	$cols['fb_status'] = 'fb_status';
	$cols['fb_type']   = 'fb_type';
	return $cols;
} );

add_action( 'pre_get_posts', function ( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'bingo_feedback' !== $query->get( 'post_type' ) ) {
		return;
	}
	$orderby = $query->get( 'orderby' );
	if ( 'fb_status' === $orderby ) {
		$query->set( 'meta_key', '_fb_status' );
		$query->set( 'orderby', 'meta_value' );
	} elseif ( 'fb_type' === $orderby ) {
		$query->set( 'meta_key', '_fb_type' );
		$query->set( 'orderby', 'meta_value' );
	}
} );

// Remove 'Edit' from Bulk Actions (irrelevant; quick edit handles individual status)
add_filter( 'bulk_actions-edit-bingo_feedback', function ( $actions ) {
	unset( $actions['edit'] );
	return $actions;
} );

// ── 5. Admin list table — styles + Quick Edit customisation ───────────────────

add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-bingo_feedback' !== $screen->id ) {
		return;
	}
	?>
	<style>
		/* Column widths */
		.post-type-bingo_feedback .column-title    { width: 150px; }
		.post-type-bingo_feedback .column-fb_type  { width: 130px; }
		.post-type-bingo_feedback .column-fb_status { width: 100px; }
		.post-type-bingo_feedback .column-fb_email  { width: 155px; }
		.post-type-bingo_feedback .column-fb_page   { width: 110px; }
		.post-type-bingo_feedback .column-date      { width: 90px; }

		/* Highlight 'new' submissions */
		.post-type-bingo_feedback tr[data-fb-status="new"] td { background: #fffbeb !important; }

		/* Quick Edit — hide WP default fields, show only ours */
		.post-type-bingo_feedback .inline-edit-col-left  { display: none !important; }
		/* Hide the first .inline-edit-col-right (WP status/date/password) via JS
		   because CSS :nth-of-type isn't reliable across WP versions */
		.post-type-bingo_feedback .fb-qe-col { padding: 12px 16px; }
		.post-type-bingo_feedback .fb-qe-col label { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
		.post-type-bingo_feedback .fb-qe-col select { padding: 4px 6px; }
		.post-type-bingo_feedback .fb-qe-info { font-size: 0.875em; color: #4a5568; background: #f7f8fa; padding: 8px 10px; border-radius: 6px; border: 1px solid #e2e8f0; }
		.post-type-bingo_feedback .fb-qe-info p { margin: 3px 0; }
	</style>
	<script>
	jQuery( function ( $ ) {

		// Mark 'new' rows for amber highlight
		$( '#the-list tr' ).each( function () {
			var $badge = $( this ).find( '.fb-qe-data' );
			if ( $badge.length && $badge.data( 'status' ) === 'new' ) {
				$( this ).attr( 'data-fb-status', 'new' );
			}
		} );

		// Override inlineEditPost.edit to customise the quick edit panel
		var $wpInlineEdit = inlineEditPost.edit;
		inlineEditPost.edit = function ( id ) {
			$wpInlineEdit.apply( this, arguments );

			var postId = 0;
			if ( typeof id === 'object' ) {
				postId = parseInt( this.getId( id ), 10 );
			} else {
				postId = parseInt( id, 10 );
			}
			if ( ! postId ) { return; }

			var $qeData = $( '#post-' + postId + ' .fb-qe-data' );
			if ( ! $qeData.length ) { return; }

			var $row = $( '#edit-' + postId );

			// Hide WP's default right column (status/date/password)
			$row.find( '.inline-edit-col-right' ).first().hide();

			// Pre-populate our status select
			$row.find( 'select[name="fb_qe_status"]' ).val( $qeData.data( 'status' ) );

			// Populate read-only info
			$row.find( '.fb-qe-type-val' ).text( $qeData.data( 'type-label' ) || $qeData.data( 'type' ) );
			$row.find( '.fb-qe-email-val' ).text( $qeData.data( 'email' ) || '—' );
		};
	} );
	</script>
	<?php
} );

// ── 6. Quick Edit custom box ──────────────────────────────────────────────────

add_action( 'quick_edit_custom_box', function ( $column_name, $post_type ) {
	if ( 'bingo_feedback' !== $post_type || 'fb_type' !== $column_name ) {
		return;
	}
	wp_nonce_field( 'bingo_feedback_quick_edit', '_fb_qe_nonce' );
	?>
	<fieldset class="inline-edit-col-right fb-qe-col">
		<div class="inline-edit-col">
			<label>
				<span class="title" style="min-width:60px">Status</span>
				<select name="fb_qe_status">
					<option value="new">New</option>
					<option value="reviewed">Reviewed</option>
					<option value="resolved">Resolved</option>
					<option value="closed">Closed</option>
				</select>
			</label>
			<div class="fb-qe-info">
				<p><strong>Type:</strong> <span class="fb-qe-type-val">—</span></p>
				<p><strong>Email:</strong> <span class="fb-qe-email-val">—</span></p>
			</div>
		</div>
	</fieldset>
	<?php
}, 10, 2 );

// ── 7. Single submission meta boxes ───────────────────────────────────────────

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'fb_details',
		'Submission Details',
		'bingo_feedback_details_meta_box',
		'bingo_feedback',
		'normal',
		'high'
	);
	add_meta_box(
		'fb_status_box',
		'Status',
		'bingo_feedback_status_meta_box',
		'bingo_feedback',
		'side',
		'high'
	);
} );

function bingo_feedback_details_meta_box( WP_Post $post ): void {
	$type        = get_post_meta( $post->ID, '_fb_type', true );
	$email       = get_post_meta( $post->ID, '_fb_email', true );
	$page_url    = get_post_meta( $post->ID, '_fb_page_url', true );
	$wants_reply = get_post_meta( $post->ID, '_fb_wants_reply', true );
	$user_id     = (int) get_post_meta( $post->ID, '_fb_user_id', true );
	$browser_raw = get_post_meta( $post->ID, '_fb_browser_info', true );
	$browser     = $browser_raw ? json_decode( $browser_raw, true ) : null;
	$scr_id      = (int) get_post_meta( $post->ID, '_fb_screenshot_id', true );
	$message     = $post->post_content;
	$type_labels = bingo_get_feedback_settings()['types'];
	$type_label  = $type_labels[ $type ] ?? ucfirst( (string) $type );

	// Reply link
	$mailto = '';
	if ( $email ) {
		$subject = rawurlencode( 'Re: Your feedback — ' . $type_label );
		$mailto  = sprintf( 'mailto:%s?subject=%s', rawurlencode( $email ), $subject );
	}
	?>
	<table class="form-table" style="margin:0">
		<tr>
			<th style="width:120px">Type</th>
			<td><?php echo esc_html( $type_label ); ?></td>
		</tr>
		<tr>
			<th>Message</th>
			<td><p style="white-space:pre-wrap;margin:0;background:#f7f8fa;padding:10px;border-radius:6px;border:1px solid #e2e8f0"><?php echo esc_html( $message ); ?></p></td>
		</tr>
		<tr>
			<th>Email</th>
			<td>
				<?php if ( $email ) : ?>
					<?php echo esc_html( $email ); ?>
					<?php if ( $mailto ) : ?>
						&nbsp;<a href="<?php echo esc_url( $mailto ); ?>" class="button button-small">Reply</a>
					<?php endif; ?>
				<?php else : ?>
					<span style="color:#a0aec0">Not provided</span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th>Wants Reply</th>
			<td><?php echo $wants_reply ? '<strong>Yes</strong>' : 'No'; ?></td>
		</tr>
		<tr>
			<th>Submitted From</th>
			<td>
				<?php if ( $page_url ) : ?>
					<a href="<?php echo esc_url( $page_url ); ?>" target="_blank"><?php echo esc_html( $page_url ); ?></a>
				<?php else : ?>
					<span style="color:#a0aec0">—</span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th>WP User</th>
			<td>
				<?php if ( $user_id ) : ?>
					<?php $u = get_userdata( $user_id ); ?>
					<?php echo $u ? esc_html( $u->display_name . ' (' . $u->user_email . ')' ) : "User #$user_id (deleted)"; ?>
				<?php else : ?>
					Guest
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( $browser ) : ?>
		<tr>
			<th>Browser Info</th>
			<td style="font-size:0.875em">
				<code style="display:block;margin-bottom:4px;word-break:break-all"><?php echo esc_html( $browser['userAgent'] ?? '' ); ?></code>
				Platform: <?php echo esc_html( $browser['platform'] ?? '' ); ?> &bull;
				Screen: <?php echo esc_html( ( $browser['screenWidth'] ?? '' ) . '×' . ( $browser['screenHeight'] ?? '' ) ); ?> &bull;
				Viewport: <?php echo esc_html( ( $browser['viewportWidth'] ?? '' ) . '×' . ( $browser['viewportHeight'] ?? '' ) ); ?>
			</td>
		</tr>
		<?php endif; ?>
		<?php if ( $scr_id ) : ?>
		<tr>
			<th>Screenshot</th>
			<td>
				<?php $img_url = wp_get_attachment_url( $scr_id ); ?>
				<?php if ( $img_url ) : ?>
					<a href="<?php echo esc_url( $img_url ); ?>" target="_blank">
						<img src="<?php echo esc_url( $img_url ); ?>" style="max-width:100%;max-height:300px;border:1px solid #e2e8f0;border-radius:4px">
					</a>
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>
	</table>
	<?php
}

function bingo_feedback_status_meta_box( WP_Post $post ): void {
	$status  = get_post_meta( $post->ID, '_fb_status', true ) ?: 'new';
	$options = [
		'new'      => 'New',
		'reviewed' => 'Reviewed',
		'resolved' => 'Resolved',
		'closed'   => 'Closed',
	];
	wp_nonce_field( 'bingo_feedback_save_status', '_fb_status_nonce' );
	?>
	<label for="fb-status-select" style="display:block;margin-bottom:6px;font-weight:600">Change status:</label>
	<select id="fb-status-select" name="fb_status" style="width:100%">
		<?php foreach ( $options as $val => $label ) : ?>
			<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $status, $val ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<p class="description" style="margin-top:8px">Save the post to apply.</p>
	<?php
}

// ── 8. Save post meta (edit screen + quick edit) ──────────────────────────────

add_action( 'save_post_bingo_feedback', function ( int $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$allowed = [ 'new', 'reviewed', 'resolved', 'closed' ];

	// ── Regular edit screen (meta box nonce) ──────────────────────────────────
	if ( isset( $_POST['_fb_status_nonce'] ) && wp_verify_nonce( $_POST['_fb_status_nonce'], 'bingo_feedback_save_status' ) ) {
		$status = sanitize_text_field( $_POST['fb_status'] ?? 'new' );
		if ( in_array( $status, $allowed, true ) ) {
			update_post_meta( $post_id, '_fb_status', $status );
		}
		return;
	}

	// ── Quick edit (inline-save AJAX; WP already verified the inline nonce) ──
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['fb_qe_status'] ) ) {
		// Verify our own quick-edit nonce as an extra layer
		if ( ! isset( $_POST['_fb_qe_nonce'] ) || ! wp_verify_nonce( $_POST['_fb_qe_nonce'], 'bingo_feedback_quick_edit' ) ) {
			return;
		}
		$status = sanitize_text_field( $_POST['fb_qe_status'] );
		if ( in_array( $status, $allowed, true ) ) {
			update_post_meta( $post_id, '_fb_status', $status );
		}
	}
} );

// ── 9. Single edit screen — hide irrelevant WP UI chrome ─────────────────────

add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'bingo_feedback' !== $screen->id ) {
		return;
	}
	// Hide title input, slug box, and most publish meta (date/visibility/password)
	echo '<style>
		#titlediv, #edit-slug-box { display: none; }
		.misc-pub-section:not(.misc-pub-post-status) { display: none; }
	</style>';
} );

// ── 10. Settings page ─────────────────────────────────────────────────────────

function bingo_feedback_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Save
	if ( isset( $_POST['bingo_fb_settings_nonce'] ) && wp_verify_nonce( $_POST['bingo_fb_settings_nonce'], 'bingo_fb_settings_save' ) ) {
		$settings = bingo_get_feedback_settings();

		$settings['enable_email_notifications'] = ! empty( $_POST['enable_email_notifications'] );
		$settings['notify_email']               = sanitize_email( $_POST['notify_email'] ?? '' ) ?: get_option( 'admin_email' );
		$settings['widget_title']               = sanitize_text_field( $_POST['widget_title'] ?? 'Send Feedback' );
		$settings['widget_btn_label']           = sanitize_text_field( $_POST['widget_btn_label'] ?? 'Feedback' );
		$settings['success_message']            = sanitize_text_field( $_POST['success_message'] ?? '' );
		$settings['enable_screenshot']          = ! empty( $_POST['enable_screenshot'] );
		$settings['enable_browser_info']        = ! empty( $_POST['enable_browser_info'] );

		$default_keys = array_keys( $settings['types'] );
		foreach ( $default_keys as $key ) {
			$label = sanitize_text_field( $_POST[ 'type_' . $key ] ?? '' );
			if ( $label ) {
				$settings['types'][ $key ] = $label;
			}
		}

		update_option( 'bingo_feedback_settings', $settings );
		echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
	}

	$s     = bingo_get_feedback_settings();
	$types = $s['types'];
	$email_on = ! empty( $s['enable_email_notifications'] );
	?>
	<div class="wrap">
		<h1>Feedback Widget Settings</h1>
		<form method="post" action="">
			<?php wp_nonce_field( 'bingo_fb_settings_save', 'bingo_fb_settings_nonce' ); ?>

			<h2 class="title">Notifications</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th>Email notifications</th>
					<td>
						<label>
							<input
								name="enable_email_notifications"
								id="fb-notify-toggle"
								type="checkbox"
								value="1"
								<?php checked( $email_on ); ?>
							>
							Send an email notification for each new submission
						</label>
					</td>
				</tr>
				<tr id="fb-notify-email-row" <?php echo $email_on ? '' : 'style="display:none"'; ?>>
					<th><label for="notify_email">Send notifications to</label></th>
					<td>
						<input name="notify_email" id="notify_email" type="email" value="<?php echo esc_attr( $s['notify_email'] ); ?>" class="regular-text">
						<p class="description">Requires working SMTP — see setup notes below.</p>
					</td>
				</tr>
			</table>
			<script>
			document.getElementById('fb-notify-toggle').addEventListener('change', function () {
				document.getElementById('fb-notify-email-row').style.display = this.checked ? '' : 'none';
			});
			</script>

			<h2 class="title">Widget Text</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="widget_btn_label">Button label</label></th>
					<td><input name="widget_btn_label" id="widget_btn_label" type="text" value="<?php echo esc_attr( $s['widget_btn_label'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="widget_title">Modal title</label></th>
					<td><input name="widget_title" id="widget_title" type="text" value="<?php echo esc_attr( $s['widget_title'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="success_message">Success message</label></th>
					<td><input name="success_message" id="success_message" type="text" value="<?php echo esc_attr( $s['success_message'] ); ?>" class="large-text"></td>
				</tr>
			</table>

			<h2 class="title">Features</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th>Screenshots</th>
					<td>
						<label>
							<input name="enable_screenshot" type="checkbox" value="1" <?php checked( $s['enable_screenshot'] ); ?>>
							Allow users to opt-in to screenshot capture
						</label>
					</td>
				</tr>
				<tr>
					<th>Browser info</th>
					<td>
						<label>
							<input name="enable_browser_info" type="checkbox" value="1" <?php checked( $s['enable_browser_info'] ); ?>>
							Allow users to opt-in to sending browser/device info
						</label>
					</td>
				</tr>
			</table>

			<h2 class="title">Feedback Type Labels</h2>
			<p class="description" style="margin-bottom:12px">Rename each category label. Keys are fixed; only the displayed text changes.</p>
			<table class="form-table" role="presentation">
				<?php foreach ( $types as $key => $label ) : ?>
				<tr>
					<th><label for="type_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $key ); ?></label></th>
					<td>
						<input name="type_<?php echo esc_attr( $key ); ?>" id="type_<?php echo esc_attr( $key ); ?>" type="text" value="<?php echo esc_attr( $label ); ?>" class="regular-text">
					</td>
				</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button( 'Save Settings' ); ?>
		</form>

		<hr>
		<h2>Email Setup (SpinupWP / Digital Ocean)</h2>
		<p>SpinupWP does not configure outbound SMTP — PHP's built-in <code>mail()</code> will likely fail or land in spam. To enable reliable email notifications:</p>
		<ol>
			<li>Install the free <strong><a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">WP Mail SMTP</a></strong> plugin.</li>
			<li>Choose a transactional email provider (free tiers shown):
				<ul style="margin-top:6px;list-style:disc;padding-left:20px">
					<li><strong>Brevo</strong> (formerly Sendinblue) — 300 emails/day free. Best for low volume.</li>
					<li><strong>Mailgun</strong> — 5,000 emails/month free (first 3 months), then $0.80/1,000.</li>
					<li><strong>Postmark</strong> — 100 emails/month free, pay-as-you-go after.</li>
					<li><strong>Gmail SMTP</strong> — 500/day; requires a Google App Password.</li>
				</ul>
			</li>
			<li>In WP Mail SMTP → Settings, select your provider and enter the API key or credentials.</li>
			<li>Use the built-in "Send a Test Email" tool to verify delivery before going live.</li>
		</ol>
		<p>SpinupWP's own docs recommend Postmark or Mailgun — check your SpinupWP dashboard under <em>Site → Email</em> for shortcuts they may offer.</p>
	</div>
	<?php
}

// ── 11. Inject the feedback widget into the front-end footer ──────────────────

add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	$s         = bingo_get_feedback_settings();
	$types     = $s['types'];
	$logged_in = is_user_logged_in();
	?>
	<button
		id="feedback-trigger"
		class="feedback-trigger"
		aria-label="<?php echo esc_attr( $s['widget_btn_label'] ); ?>"
		aria-expanded="false"
		title="<?php echo esc_attr( $s['widget_btn_label'] ); ?>"
	>
		<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
		<?php echo esc_html( $s['widget_btn_label'] ); ?>
	</button>

	<div
		id="feedback-widget"
		class="feedback-widget"
		role="dialog"
		aria-modal="true"
		aria-label="<?php echo esc_attr( $s['widget_title'] ); ?>"
		aria-hidden="true"
	>
		<!-- Success overlay — covers the entire modal on submission -->
		<div id="fw-success-overlay" class="fw-success-overlay" aria-hidden="true">
			<div class="fw-success-content">
				<div class="fw-success-check">
					<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
				</div>
				<p id="fw-success-msg" class="fw-success-msg"></p>
				<p class="fw-success-sub">This window will close shortly.</p>
			</div>
		</div>

		<div class="fw-header">
			<span class="fw-title"><?php echo esc_html( $s['widget_title'] ); ?></span>
			<div class="fw-header-actions">
				<button id="fw-minimize" type="button" title="Minimize" aria-label="Minimize">&#8212;</button>
				<button id="fw-close" type="button" title="Clear and close" aria-label="Clear draft and close">&#10005;</button>
			</div>
		</div>

		<div class="fw-body">

			<!-- Type selection -->
			<div class="fw-section-label">What kind of feedback?</div>
			<div class="fw-types" role="group" aria-label="Feedback type">
				<?php foreach ( $types as $key => $label ) : ?>
				<button type="button" class="fw-type-btn" data-type="<?php echo esc_attr( $key ); ?>">
					<?php echo esc_html( $label ); ?>
				</button>
				<?php endforeach; ?>
			</div>

			<!-- Message -->
			<div class="fw-field">
				<label for="fw-message">Message <span class="fw-required">*</span></label>
				<textarea id="fw-message" placeholder="Describe the bug, idea, or feedback…" rows="4"></textarea>
			</div>

			<?php if ( $s['enable_screenshot'] ) : ?>
			<!-- Screenshot opt-in -->
			<label class="fw-toggle">
				<input type="checkbox" id="fw-screenshot">
				<span>
					Include a screenshot with this report
					<span class="fw-toggle-note">Captures the current page — the feedback panel is excluded.</span>
				</span>
			</label>
			<div id="fw-screenshot-preview" class="fw-screenshot-preview" style="display:none">
				<img id="fw-screenshot-img" alt="Screenshot preview">
				<button id="fw-retake" type="button">Retake</button>
			</div>
			<?php endif; ?>

			<?php if ( $s['enable_browser_info'] ) : ?>
			<!-- Browser info consent -->
			<label class="fw-toggle">
				<input type="checkbox" id="fw-browser-info" checked>
				<span>
					Send browser &amp; device info
					<span class="fw-toggle-note">Browser name/version, OS, screen size. No personal data, cookies, or IP address.</span>
				</span>
			</label>
			<?php endif; ?>

			<!-- Email -->
			<div class="fw-field" id="fw-email-field">
				<label for="fw-email">
					Your email
					<?php if ( ! $logged_in ) : ?>
					<span class="fw-required">*</span>
					<?php endif; ?>
				</label>
				<input
					type="email"
					id="fw-email"
					placeholder="you@example.com"
					autocomplete="email"
					<?php echo ! $logged_in ? 'required' : ''; ?>
				>
				<span class="fw-hint" id="fw-email-hint">
					<?php if ( $logged_in ) : ?>
					Auto-filled from your account. Required only if you'd like a reply.
					<?php else : ?>
					Required so we can follow up if needed.
					<?php endif; ?>
				</span>
			</div>

			<!-- Wants reply -->
			<label class="fw-toggle">
				<input type="checkbox" id="fw-wants-reply">
				<span>I'd like a response to this feedback</span>
			</label>

			<!-- Actions -->
			<div class="fw-footer">
				<button id="fw-clear" type="button" class="fw-clear-btn" title="Clear this draft and close">Clear &amp; Close</button>
				<button id="fw-submit" type="button" class="fw-submit-btn">Send Feedback</button>
			</div>

			<!-- Error status only — success uses the overlay above -->
			<p id="fw-status" class="fw-status" role="alert"></p>

		</div><!-- .fw-body -->
	</div><!-- #feedback-widget -->
	<?php
} );
