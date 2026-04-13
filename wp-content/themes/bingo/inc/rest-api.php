<?php
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	$ns = 'bingo/v1';

	// GET  /bingo/v1/suggestions
	register_rest_route( $ns, '/suggestions', [
		'methods'             => 'GET',
		'callback'            => 'bingo_rest_get_suggestions',
		'permission_callback' => '__return_true',
		'args' => [
			'count'   => [ 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 48 ],
			'exclude' => [ 'type' => 'string',  'default' => '' ],
		],
	] );

	// POST /bingo/v1/cards
	register_rest_route( $ns, '/cards', [
		'methods'             => 'POST',
		'callback'            => 'bingo_rest_create_card',
		'permission_callback' => '__return_true',
		'args'                => bingo_card_args(),
	] );

	// GET  /bingo/v1/cards/(?P<id>\d+)
	register_rest_route( $ns, '/cards/(?P<id>[\d]+)', [
		'methods'             => 'GET',
		'callback'            => 'bingo_rest_get_card',
		'permission_callback' => '__return_true',
		'args' => [
			'id' => [ 'type' => 'integer', 'required' => true ],
		],
	] );

	// PUT  /bingo/v1/cards/(?P<id>\d+)
	register_rest_route( $ns, '/cards/(?P<id>[\d]+)', [
		'methods'             => 'PUT',
		'callback'            => 'bingo_rest_update_card',
		'permission_callback' => '__return_true',
		'args'                => array_merge( [ 'id' => [ 'type' => 'integer', 'required' => true ] ], bingo_card_args() ),
	] );

	// POST /bingo/v1/cards/(?P<id>\d+)/check
	register_rest_route( $ns, '/cards/(?P<id>[\d]+)/check', [
		'methods'             => 'POST',
		'callback'            => 'bingo_rest_toggle_check',
		'permission_callback' => '__return_true',
		'args' => [
			'id'          => [ 'type' => 'integer', 'required' => true ],
			'position'    => [ 'type' => 'integer', 'required' => true, 'minimum' => 0, 'maximum' => 24 ],
			'guest_token' => [ 'type' => 'string',  'default' => '' ],
		],
	] );

	// POST /bingo/v1/cards/(?P<id>\d+)/claim
	register_rest_route( $ns, '/cards/(?P<id>[\d]+)/claim', [
		'methods'             => 'POST',
		'callback'            => 'bingo_rest_claim_card',
		'permission_callback' => 'is_user_logged_in',
		'args' => [
			'id'          => [ 'type' => 'integer', 'required' => true ],
			'guest_token' => [ 'type' => 'string',  'required' => true ],
		],
	] );

	// GET /bingo/v1/my-cards
	register_rest_route( $ns, '/my-cards', [
		'methods'             => 'GET',
		'callback'            => 'bingo_rest_my_cards',
		'permission_callback' => 'is_user_logged_in',
	] );

	// DELETE /bingo/v1/cards/(?P<id>\d+)
	register_rest_route( $ns, '/cards/(?P<id>[\d]+)', [
		'methods'             => 'DELETE',
		'callback'            => 'bingo_rest_delete_card',
		'permission_callback' => '__return_true',
		'args' => [
			'id'          => [ 'type' => 'integer', 'required' => true ],
			'guest_token' => [ 'type' => 'string',  'default' => '' ],
		],
	] );
} );

// ── Shared arg definitions ────────────────────────────────────────────────────

function bingo_card_args(): array {
	return [
		'title'       => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		'author_name' => [ 'type' => 'string',  'required' => false, 'sanitize_callback' => 'sanitize_text_field' ],
		'items'       => [ 'type' => 'array',   'required' => false, 'items' => [ 'type' => 'string' ] ],
		'board'       => [ 'type' => 'array',   'required' => false, 'items' => [ 'type' => 'string' ] ],
		'theme'       => [ 'type' => 'string',  'required' => false, 'default' => 'classic', 'enum' => array_keys( bingo_card_themes() ) ],
		'guest_token' => [ 'type' => 'string',  'required' => false, 'default' => '' ],
	];
}

// ── Authorization helper ──────────────────────────────────────────────────────

function bingo_can_edit_card( WP_Post $post, string $guest_token = '' ): bool {
	// Logged-in owner
	if ( is_user_logged_in() && (int) $post->post_author === get_current_user_id() ) {
		return true;
	}
	// Admin
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	// Guest token match
	$stored = get_post_meta( $post->ID, 'bingo_guest_token', true );
	return $stored && $guest_token && hash_equals( $stored, $guest_token );
}

// ── Format card for response ──────────────────────────────────────────────────

function bingo_format_card( WP_Post $post ): array {
	return [
		'id'          => $post->ID,
		'title'       => get_the_title( $post ),
		'authorName'  => get_post_meta( $post->ID, 'bingo_author_name', true ),
		'theme'       => get_post_meta( $post->ID, 'bingo_theme', true ) ?: 'classic',
		'items'       => get_post_meta( $post->ID, 'bingo_items', true ) ?: [],
		'board'       => get_post_meta( $post->ID, 'bingo_board', true ) ?: [],
		'checked'     => get_post_meta( $post->ID, 'bingo_checked', true ) ?: [],
		'isGuest'     => (bool) get_post_meta( $post->ID, 'bingo_is_guest', true ),
		'permalink'   => get_permalink( $post ),
		'shareToken'  => get_post_meta( $post->ID, 'bingo_share_token', true ),
		'createdAt'   => get_the_date( 'c', $post ),
	];
}

// ── GET /suggestions ──────────────────────────────────────────────────────────

function bingo_rest_get_suggestions( WP_REST_Request $request ): WP_REST_Response {
	$count   = (int) $request->get_param( 'count' );
	$exclude = $request->get_param( 'exclude' );
	$exclude_names = $exclude ? array_map( 'trim', explode( '|', $exclude ) ) : [];

	$suggestions = bingo_get_suggestions( $count, $exclude_names );
	return new WP_REST_Response( $suggestions, 200 );
}

// ── POST /cards ───────────────────────────────────────────────────────────────

function bingo_rest_create_card( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$title       = $request->get_param( 'title' ) ?: 'My Life Bingo Card';
	$author_name = $request->get_param( 'author_name' ) ?: '';
	$items       = bingo_sanitize_items( (array) $request->get_param( 'items' ) );
	$board       = bingo_sanitize_items( (array) $request->get_param( 'board' ) );
	$theme       = $request->get_param( 'theme' ) ?: 'classic';

	if ( count( $board ) !== 24 ) {
		return new WP_Error( 'invalid_board', 'Board must contain exactly 24 items.', [ 'status' => 400 ] );
	}

	$is_guest   = ! is_user_logged_in();
	$author_id  = is_user_logged_in() ? get_current_user_id() : 0;
	$post_status = 'publish';

	$post_id = wp_insert_post( [
		'post_type'   => 'bingo_card',
		'post_title'  => $title,
		'post_status' => $post_status,
		'post_author' => $author_id,
	], true );

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$share_token = bingo_generate_uuid();
	$guest_token = $is_guest ? bingo_generate_uuid() : '';

	update_post_meta( $post_id, 'bingo_author_name', $author_name );
	update_post_meta( $post_id, 'bingo_theme', $theme );
	update_post_meta( $post_id, 'bingo_items', $items );
	update_post_meta( $post_id, 'bingo_board', $board );
	update_post_meta( $post_id, 'bingo_checked', [] );
	update_post_meta( $post_id, 'bingo_share_token', $share_token );
	update_post_meta( $post_id, 'bingo_is_guest', $is_guest );

	if ( $guest_token ) {
		update_post_meta( $post_id, 'bingo_guest_token', $guest_token );
	}

	$response_data = bingo_format_card( get_post( $post_id ) );
	if ( $guest_token ) {
		$response_data['guestToken'] = $guest_token;
	}

	return new WP_REST_Response( $response_data, 201 );
}

// ── GET /cards/{id} ───────────────────────────────────────────────────────────

function bingo_rest_get_card( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$post = get_post( (int) $request->get_param( 'id' ) );
	if ( ! $post || $post->post_type !== 'bingo_card' ) {
		return new WP_Error( 'not_found', 'Card not found.', [ 'status' => 404 ] );
	}
	return new WP_REST_Response( bingo_format_card( $post ), 200 );
}

// ── PUT /cards/{id} ───────────────────────────────────────────────────────────

function bingo_rest_update_card( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$post = get_post( (int) $request->get_param( 'id' ) );
	if ( ! $post || $post->post_type !== 'bingo_card' ) {
		return new WP_Error( 'not_found', 'Card not found.', [ 'status' => 404 ] );
	}

	$guest_token = (string) $request->get_param( 'guest_token' );
	if ( ! bingo_can_edit_card( $post, $guest_token ) ) {
		return new WP_Error( 'forbidden', 'You do not have permission to edit this card.', [ 'status' => 403 ] );
	}

	if ( $request->get_param( 'title' ) !== null ) {
		wp_update_post( [ 'ID' => $post->ID, 'post_title' => $request->get_param( 'title' ) ] );
	}
	if ( $request->get_param( 'author_name' ) !== null ) {
		update_post_meta( $post->ID, 'bingo_author_name', $request->get_param( 'author_name' ) );
	}
	if ( $request->get_param( 'theme' ) !== null ) {
		update_post_meta( $post->ID, 'bingo_theme', $request->get_param( 'theme' ) );
	}
	if ( $request->get_param( 'items' ) !== null ) {
		update_post_meta( $post->ID, 'bingo_items', bingo_sanitize_items( (array) $request->get_param( 'items' ) ) );
	}
	if ( $request->get_param( 'board' ) !== null ) {
		$board = bingo_sanitize_items( (array) $request->get_param( 'board' ) );
		if ( count( $board ) !== 24 ) {
			return new WP_Error( 'invalid_board', 'Board must contain exactly 24 items.', [ 'status' => 400 ] );
		}
		update_post_meta( $post->ID, 'bingo_board', $board );
		// Reset checked squares when board changes
		update_post_meta( $post->ID, 'bingo_checked', [] );
	}

	return new WP_REST_Response( bingo_format_card( get_post( $post->ID ) ), 200 );
}

// ── POST /cards/{id}/check ────────────────────────────────────────────────────

function bingo_rest_toggle_check( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$post = get_post( (int) $request->get_param( 'id' ) );
	if ( ! $post || $post->post_type !== 'bingo_card' ) {
		return new WP_Error( 'not_found', 'Card not found.', [ 'status' => 404 ] );
	}

	$guest_token = (string) $request->get_param( 'guest_token' );
	if ( ! bingo_can_edit_card( $post, $guest_token ) ) {
		return new WP_Error( 'forbidden', 'You do not have permission to update this card.', [ 'status' => 403 ] );
	}

	$position = (int) $request->get_param( 'position' );
	if ( $position === 12 ) {
		return new WP_Error( 'invalid_position', 'Cannot check the FREE space.', [ 'status' => 400 ] );
	}

	$checked = get_post_meta( $post->ID, 'bingo_checked', true );
	if ( ! is_array( $checked ) ) {
		$checked = [];
	}

	if ( in_array( $position, $checked, true ) ) {
		$checked = array_values( array_filter( $checked, fn( $p ) => $p !== $position ) );
	} else {
		$checked[] = $position;
	}

	update_post_meta( $post->ID, 'bingo_checked', $checked );

	return new WP_REST_Response( [ 'checked' => $checked ], 200 );
}

// ── POST /cards/{id}/claim ────────────────────────────────────────────────────

function bingo_rest_claim_card( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$post = get_post( (int) $request->get_param( 'id' ) );
	if ( ! $post || $post->post_type !== 'bingo_card' ) {
		return new WP_Error( 'not_found', 'Card not found.', [ 'status' => 404 ] );
	}

	$guest_token = (string) $request->get_param( 'guest_token' );
	$stored      = get_post_meta( $post->ID, 'bingo_guest_token', true );

	if ( ! $stored || ! $guest_token || ! hash_equals( $stored, $guest_token ) ) {
		return new WP_Error( 'invalid_token', 'Invalid guest token.', [ 'status' => 403 ] );
	}

	// Assign to current user
	wp_update_post( [ 'ID' => $post->ID, 'post_author' => get_current_user_id() ] );
	update_post_meta( $post->ID, 'bingo_is_guest', false );
	delete_post_meta( $post->ID, 'bingo_guest_token' );

	return new WP_REST_Response( bingo_format_card( get_post( $post->ID ) ), 200 );
}

// ── GET /my-cards ─────────────────────────────────────────────────────────────

function bingo_rest_my_cards( WP_REST_Request $request ): WP_REST_Response {
	$posts = get_posts( [
		'post_type'      => 'bingo_card',
		'post_status'    => 'publish',
		'author'         => get_current_user_id(),
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
	] );

	return new WP_REST_Response( array_map( 'bingo_format_card', $posts ), 200 );
}

// ── DELETE /cards/{id} ────────────────────────────────────────────────────────

function bingo_rest_delete_card( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$post = get_post( (int) $request->get_param( 'id' ) );
	if ( ! $post || $post->post_type !== 'bingo_card' ) {
		return new WP_Error( 'not_found', 'Card not found.', [ 'status' => 404 ] );
	}

	$guest_token = (string) $request->get_param( 'guest_token' );
	if ( ! bingo_can_edit_card( $post, $guest_token ) ) {
		return new WP_Error( 'forbidden', 'You do not have permission to delete this card.', [ 'status' => 403 ] );
	}

	wp_delete_post( $post->ID, true );
	return new WP_REST_Response( [ 'deleted' => true ], 200 );
}

// ── Sanitize items array ──────────────────────────────────────────────────────

function bingo_sanitize_items( array $items ): array {
	return array_map( 'sanitize_text_field', array_values( $items ) );
}
