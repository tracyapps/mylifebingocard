<?php
get_header();

$post        = get_post();
$theme       = get_post_meta( $post->ID, 'bingo_theme', true ) ?: 'classic';
$author_name = get_post_meta( $post->ID, 'bingo_author_name', true );
$board       = get_post_meta( $post->ID, 'bingo_board', true ) ?: [];
$checked     = get_post_meta( $post->ID, 'bingo_checked', true ) ?: [];
$is_owner    = is_user_logged_in() && get_current_user_id() === (int) $post->post_author;
?>

<div class="card-page container">

	<div class="card-page-header">
		<div class="card-page-title-area">
			<h1 class="card-title-display"><?php the_title(); ?></h1>
			<?php if ( $author_name ) : ?>
				<p class="card-author-display">by <?php echo esc_html( $author_name ); ?></p>
			<?php endif; ?>
		</div>
		<div class="card-page-actions">
			<button class="btn btn-outline" id="btn-share-card">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
				Share
			</button>
			<button class="btn btn-outline" id="btn-download-pdf-view">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
				Download PDF
			</button>
			<button class="btn btn-outline" id="btn-download-png-view">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
				Save Image
			</button>
		</div>
	</div>

	<?php if ( $is_owner ) : ?>
		<p class="owner-hint">This is your card — tap squares to check them off!</p>
	<?php endif; ?>

	<!-- The bingo board (rendered by viewer.js) -->
	<div id="bingo-board-view" class="bingo-board-wrap theme-<?php echo esc_attr( $theme ); ?>"></div>

	<div id="share-toast" class="share-toast hidden">Link copied to clipboard!</div>

	<!-- CTA for visitors -->
	<?php if ( ! $is_owner ) : ?>
		<div class="card-cta-box">
			<h2>Make Your Own Bingo Card</h2>
			<p>Create a personalized life bingo card in minutes — free to download and share.</p>
			<a href="<?php echo esc_url( bingo_page_url( 'create' ) ); ?>" class="btn btn-primary btn-lg">Create Your Card</a>
		</div>
	<?php endif; ?>

</div>

<?php get_footer(); ?>
