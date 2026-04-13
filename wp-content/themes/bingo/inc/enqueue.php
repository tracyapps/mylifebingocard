<?php
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {

	// ── Main stylesheet ───────────────────────────────────────────────────────
	wp_enqueue_style(
		'bingo-main',
		BINGO_URI . '/assets/css/bingo.css',
		[],
		BINGO_VERSION
	);

	// ── Third-party: html2canvas + jsPDF (CDN) ────────────────────────────────
	wp_register_script(
		'html2canvas',
		'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
		[],
		'1.4.1',
		true
	);
	wp_register_script(
		'jspdf',
		'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
		[],
		'2.5.1',
		true
	);

	// ── Shared bingo data ─────────────────────────────────────────────────────
	$shared_data = [
		'restUrl'   => esc_url_raw( rest_url( 'bingo/v1/' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'createUrl' => bingo_page_url( 'create' ),
		'accountUrl' => bingo_page_url( 'account' ),
		'myCardsUrl' => bingo_page_url( 'my-cards' ),
		'user'      => bingo_current_user_data(),
		'themes'    => bingo_card_themes(),
	];

	// ── Card creator page ─────────────────────────────────────────────────────
	if ( is_page( bingo_page_slugs()['create'] ) ) {
		wp_enqueue_script( 'html2canvas' );
		wp_enqueue_script( 'jspdf' );
		wp_enqueue_script(
			'bingo-creator',
			BINGO_URI . '/assets/js/creator.js',
			[ 'html2canvas', 'jspdf' ],
			BINGO_VERSION,
			true
		);
		wp_localize_script( 'bingo-creator', 'BingoData', $shared_data );
	}

	// ── Single card view ──────────────────────────────────────────────────────
	if ( is_singular( 'bingo_card' ) ) {
		$post      = get_post();
		$owner_id  = (int) $post->post_author;
		$is_owner  = is_user_logged_in() && get_current_user_id() === $owner_id;

		wp_enqueue_script( 'html2canvas' );
		wp_enqueue_script( 'jspdf' );
		wp_enqueue_script(
			'bingo-viewer',
			BINGO_URI . '/assets/js/viewer.js',
			[ 'html2canvas', 'jspdf' ],
			BINGO_VERSION,
			true
		);

		$board   = get_post_meta( $post->ID, 'bingo_board', true ) ?: [];
		$checked = get_post_meta( $post->ID, 'bingo_checked', true ) ?: [];

		wp_localize_script( 'bingo-viewer', 'BingoData', array_merge( $shared_data, [
			'card' => [
				'id'          => $post->ID,
				'title'       => get_the_title( $post ),
				'authorName'  => get_post_meta( $post->ID, 'bingo_author_name', true ),
				'theme'       => get_post_meta( $post->ID, 'bingo_theme', true ) ?: 'classic',
				'board'       => $board,   // 24-item ordered array
				'checked'     => $checked, // array of checked positions (0-24)
				'isOwner'     => $is_owner,
				'permalink'   => get_permalink( $post ),
				// Note: guest token is NOT passed here — viewer.js reads it from localStorage
			],
		] ) );
	}

	// ── My Cards page ─────────────────────────────────────────────────────────
	if ( is_page( bingo_page_slugs()['my-cards'] ) ) {
		wp_enqueue_script(
			'bingo-my-cards',
			BINGO_URI . '/assets/js/my-cards.js',
			[],
			BINGO_VERSION,
			true
		);
		wp_localize_script( 'bingo-my-cards', 'BingoData', $shared_data );
	}
} );

// ── Color themes definition (used by PHP + passed to JS) ──────────────────────

function bingo_card_themes(): array {
	return [
		'classic' => [
			'label'     => 'Classic',
			'boardBg'   => '#1a365d',
			'cellBg'    => '#ffffff',
			'cellText'  => '#1a365d',
			'cellBorder'=> '#2b4c8c',
			'checkedBg' => '#2b4c8c',
			'checkedText'=> '#ffffff',
			'freeBg'    => '#f6ad55',
			'freeText'  => '#1a365d',
			'headerBg'  => '#1a365d',
			'headerText'=> '#ffffff',
		],
		'sunset' => [
			'label'     => 'Sunset',
			'boardBg'   => '#c05621',
			'cellBg'    => '#fff5f5',
			'cellText'  => '#7b341e',
			'cellBorder'=> '#dd6b20',
			'checkedBg' => '#dd6b20',
			'checkedText'=> '#ffffff',
			'freeBg'    => '#fbb6ce',
			'freeText'  => '#7b341e',
			'headerBg'  => '#c05621',
			'headerText'=> '#ffffff',
		],
		'forest' => [
			'label'     => 'Forest',
			'boardBg'   => '#276749',
			'cellBg'    => '#f0fff4',
			'cellText'  => '#276749',
			'cellBorder'=> '#38a169',
			'checkedBg' => '#38a169',
			'checkedText'=> '#ffffff',
			'freeBg'    => '#c6f6d5',
			'freeText'  => '#276749',
			'headerBg'  => '#276749',
			'headerText'=> '#ffffff',
		],
		'ocean' => [
			'label'     => 'Ocean',
			'boardBg'   => '#2c7a7b',
			'cellBg'    => '#e6fffa',
			'cellText'  => '#234e52',
			'cellBorder'=> '#4fd1c5',
			'checkedBg' => '#319795',
			'checkedText'=> '#ffffff',
			'freeBg'    => '#81e6d9',
			'freeText'  => '#234e52',
			'headerBg'  => '#2c7a7b',
			'headerText'=> '#ffffff',
		],
		'slate' => [
			'label'     => 'Slate',
			'boardBg'   => '#2d3748',
			'cellBg'    => '#4a5568',
			'cellText'  => '#e2e8f0',
			'cellBorder'=> '#718096',
			'checkedBg' => '#805ad5',
			'checkedText'=> '#ffffff',
			'freeBg'    => '#9f7aea',
			'freeText'  => '#1a202c',
			'headerBg'  => '#1a202c',
			'headerText'=> '#e2e8f0',
		],
	];
}
