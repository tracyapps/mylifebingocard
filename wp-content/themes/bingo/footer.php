</main><!-- .site-main -->

<footer class="site-footer">
	<div class="footer-inner">
		<p>&copy; <?php echo date( 'Y' ); ?> My Life Bingo Card. Share your story, one square at a time.</p>
		<nav class="footer-nav">
			<a href="<?php echo esc_url( bingo_page_url( 'create' ) ); ?>">Create a Card</a>
		</nav>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
