<?php get_header(); ?>
<div class="container">
	<div class="home-hero">
		<h1>My Life Bingo Card</h1>
		<p>Create a personalized bingo card out of your life adventures, goals, and memories. Share it, print it, and check things off as you go.</p>
		<a href="<?php echo esc_url( bingo_page_url( 'create' ) ); ?>" class="btn btn-primary btn-lg">Create Your Card</a>
	</div>
</div>
<?php get_footer(); ?>
