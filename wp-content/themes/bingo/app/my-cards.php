<?php defined( 'ABSPATH' ) || exit; ?>

<div class="my-cards-page container">
	<div class="my-cards-header">
		<h1>My Bingo Cards</h1>
		<a href="<?php echo esc_url( bingo_page_url( 'create' ) ); ?>" class="btn btn-primary">
			<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
			New Card
		</a>
	</div>

	<div id="my-cards-grid" class="my-cards-grid">
		<div class="cards-loading">Loading your cards…</div>
	</div>
</div>

<script type="text/template" id="tmpl-card-thumbnail">
	<a href="{{permalink}}" class="card-thumb theme-{{theme}}">
		<div class="card-thumb-board">{{board_mini}}</div>
		<div class="card-thumb-meta">
			<strong>{{title}}</strong>
			<span>{{authorName}}</span>
		</div>
	</a>
</script>
