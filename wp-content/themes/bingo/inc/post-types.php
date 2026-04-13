<?php
defined( 'ABSPATH' ) || exit;

// ── CPT: bingo_card ───────────────────────────────────────────────────────────

add_action( 'init', function () {
	register_post_type( 'bingo_card', [
		'labels' => [
			'name'               => 'Bingo Cards',
			'singular_name'      => 'Bingo Card',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Bingo Card',
			'edit_item'          => 'Edit Bingo Card',
			'new_item'           => 'New Bingo Card',
			'view_item'          => 'View Bingo Card',
			'search_items'       => 'Search Bingo Cards',
			'not_found'          => 'No bingo cards found',
			'not_found_in_trash' => 'No bingo cards found in Trash',
		],
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'query_var'           => true,
		'rewrite'             => [ 'slug' => 'card' ],
		'capability_type'     => 'post',
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-grid-view',
		'supports'            => [ 'title', 'author' ],
		'show_in_rest'        => true,
	] );
} );

// ── Taxonomy: bingo_suggestion ────────────────────────────────────────────────

add_action( 'init', function () {
	register_taxonomy( 'bingo_suggestion', null, [
		'labels' => [
			'name'              => 'Bingo Suggestions',
			'singular_name'     => 'Suggestion',
			'search_items'      => 'Search Suggestions',
			'all_items'         => 'All Suggestions',
			'edit_item'         => 'Edit Suggestion',
			'update_item'       => 'Update Suggestion',
			'add_new_item'      => 'Add New Suggestion',
			'new_item_name'     => 'New Suggestion Name',
			'menu_name'         => 'Suggestions',
		],
		'hierarchical'      => true, // categories-style so admin can group by category
		'show_ui'           => true,
		'show_in_menu'      => true,
		'show_admin_column' => true,
		'query_var'         => false,
		'rewrite'           => false,
		'show_in_rest'      => false,
	] );
} );

// ── Admin meta box for bingo_card ─────────────────────────────────────────────

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'bingo_card_meta',
		'Card Details',
		'bingo_card_meta_box_cb',
		'bingo_card',
		'normal',
		'high'
	);
} );

function bingo_card_meta_box_cb( WP_Post $post ): void {
	$author_name  = get_post_meta( $post->ID, 'bingo_author_name', true );
	$theme        = get_post_meta( $post->ID, 'bingo_theme', true ) ?: 'classic';
	$items        = get_post_meta( $post->ID, 'bingo_items', true ) ?: [];
	$board        = get_post_meta( $post->ID, 'bingo_board', true ) ?: [];
	$checked      = get_post_meta( $post->ID, 'bingo_checked', true ) ?: [];
	$share_token  = get_post_meta( $post->ID, 'bingo_share_token', true );
	$guest        = get_post_meta( $post->ID, 'bingo_is_guest', true );

	wp_nonce_field( 'bingo_save_meta', 'bingo_meta_nonce' );
	?>
	<table class="form-table">
		<tr>
			<th>Author Name (display)</th>
			<td><input type="text" name="bingo_author_name" value="<?php echo esc_attr( $author_name ); ?>" class="regular-text" /></td>
		</tr>
		<tr>
			<th>Color Theme</th>
			<td><?php echo esc_html( $theme ); ?></td>
		</tr>
		<tr>
			<th>Guest Card?</th>
			<td><?php echo $guest ? 'Yes' : 'No'; ?></td>
		</tr>
		<tr>
			<th>Share Token</th>
			<td><code><?php echo esc_html( $share_token ); ?></code></td>
		</tr>
		<tr>
			<th>Items (<?php echo count( $items ); ?>)</th>
			<td><pre style="max-height:120px;overflow:auto;font-size:11px;"><?php echo esc_html( implode( "\n", $items ) ); ?></pre></td>
		</tr>
		<tr>
			<th>Checked Positions</th>
			<td><code><?php echo esc_html( implode( ', ', $checked ) ); ?></code></td>
		</tr>
	</table>
	<?php
}

add_action( 'save_post_bingo_card', function ( int $post_id ): void {
	if (
		! isset( $_POST['bingo_meta_nonce'] ) ||
		! wp_verify_nonce( $_POST['bingo_meta_nonce'], 'bingo_save_meta' ) ||
		defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE
	) {
		return;
	}
	if ( isset( $_POST['bingo_author_name'] ) ) {
		update_post_meta( $post_id, 'bingo_author_name', sanitize_text_field( $_POST['bingo_author_name'] ) );
	}
} );

// ── Flush rewrite rules on activation ─────────────────────────────────────────

add_action( 'after_switch_theme', function () {
	flush_rewrite_rules();
} );
