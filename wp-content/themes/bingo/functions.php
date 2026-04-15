<?php
defined( 'ABSPATH' ) || exit;

define( 'BINGO_VERSION', '1.1.0' );
define( 'BINGO_DIR', get_template_directory() );
define( 'BINGO_URI', get_template_directory_uri() );

require_once BINGO_DIR . '/inc/post-types.php';
require_once BINGO_DIR . '/inc/suggestions.php';
require_once BINGO_DIR . '/inc/rest-api.php';
require_once BINGO_DIR . '/inc/feedback.php';
require_once BINGO_DIR . '/inc/feedback-api.php';
require_once BINGO_DIR . '/inc/enqueue.php';

// ── Theme setup ──────────────────────────────────────────────────────────────

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'gallery', 'caption' ] );

	register_nav_menus( [
		'primary' => __( 'Primary Menu', 'bingo' ),
	] );
} );

// ── Page slugs (filterable) ───────────────────────────────────────────────────

function bingo_page_slugs(): array {
	return apply_filters( 'bingo_page_slugs', [
		'create'       => 'create',
		'my-cards'     => 'my-cards',
		'account'      => 'account',
		'edit-account' => 'edit-account',
	] );
}

function bingo_page_url( string $key ): string {
	$slugs = bingo_page_slugs();
	return home_url( '/' . ( $slugs[ $key ] ?? $key ) . '/' );
}

// ── Current user helper ───────────────────────────────────────────────────────

function bingo_current_user_data(): array {
	if ( ! is_user_logged_in() ) {
		return [ 'logged_in' => false ];
	}
	$user = wp_get_current_user();
	return [
		'logged_in' => true,
		'id'        => $user->ID,
		'name'      => $user->display_name,
		'email'     => $user->user_email,
	];
}

// ── Utility: generate a UUID v4 ───────────────────────────────────────────────

function bingo_generate_uuid(): string {
	return sprintf(
		'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
		mt_rand( 0, 0xffff ),
		mt_rand( 0, 0x0fff ) | 0x4000,
		mt_rand( 0, 0x3fff ) | 0x8000,
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
	);
}

// ── Redirect /account/ to login page if not logged in ────────────────────────

add_action( 'template_redirect', function () {
	$slugs = bingo_page_slugs();
	if ( is_page( $slugs['my-cards'] ) && ! is_user_logged_in() ) {
		wp_redirect( bingo_page_url( 'account' ) );
		exit;
	}
} );

// ── OG meta tags for bingo card posts ─────────────────────────────────────────

add_action( 'wp_head', function () {
	if ( ! is_singular( 'bingo_card' ) ) {
		return;
	}
	$post      = get_post();
	$title     = get_the_title() . ' — My Life Bingo Card';
	$desc      = 'Check out my life bingo card!';
	$url       = get_permalink();
	$thumb_url = get_post_meta( $post->ID, 'bingo_og_image', true );
	if ( ! $thumb_url ) {
		$thumb_url = BINGO_URI . '/assets/img/og-default.png';
	}
	?>
	<meta property="og:type"        content="website" />
	<meta property="og:url"         content="<?php echo esc_url( $url ); ?>" />
	<meta property="og:title"       content="<?php echo esc_attr( $title ); ?>" />
	<meta property="og:description" content="<?php echo esc_attr( $desc ); ?>" />
	<meta property="og:image"       content="<?php echo esc_url( $thumb_url ); ?>" />
	<meta name="twitter:card"       content="summary_large_image" />
	<meta name="twitter:title"      content="<?php echo esc_attr( $title ); ?>" />
	<meta name="twitter:image"      content="<?php echo esc_url( $thumb_url ); ?>" />
	<?php
} );
