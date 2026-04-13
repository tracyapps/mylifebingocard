<?php defined( 'ABSPATH' ) || exit; ?>

<div class="creator-page container">

	<!-- ── Left: Item Entry ────────────────────────────────────────────── -->
	<section class="creator-form" aria-label="Card builder">

		<div class="creator-header">
			<h1>Create Your Life Bingo Card</h1>
			<p>Fill in up to 24 life experiences, goals, or memories — then hit Generate to shuffle them onto your board.</p>
		</div>

		<!-- Card title + name -->
		<div class="form-row">
			<div class="form-group">
				<label for="card-title">Card Title</label>
				<input type="text" id="card-title" placeholder="My Life Bingo Card" maxlength="80" />
			</div>
			<div class="form-group">
				<label for="card-author">Your Name</label>
				<input type="text" id="card-author" placeholder="Your name (optional)" maxlength="60" />
			</div>
		</div>

		<!-- Item list -->
		<div class="items-section">
			<div class="items-header">
				<label>Your Bingo Squares <span class="item-count-badge"><span id="item-count">0</span>/24</span></label>
				<span class="items-hint">Click a suggestion on the right to add it instantly.</span>
			</div>
			<div id="item-list" class="item-list">
				<!-- Inputs rendered by JS -->
			</div>
			<button class="btn btn-ghost btn-add-item" id="btn-add-item" type="button">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
				Add a square
			</button>
		</div>

		<!-- Theme picker -->
		<div class="theme-section">
			<label>Color Theme</label>
			<div id="theme-picker" class="theme-picker">
				<!-- Rendered by JS -->
			</div>
		</div>

		<!-- Actions -->
		<div class="creator-actions">
			<button class="btn btn-primary btn-lg" id="btn-generate" type="button" disabled>
				<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17,1 21,5 17,9"/><path d="M3,11V9a4,4,0,0,1,4-4h14"/><polyline points="7,23 3,19 7,15"/><path d="M21,13v2a4,4,0,0,1-4,4H3"/></svg>
				Generate Card
			</button>
		</div>

	</section>

	<!-- ── Center: Board Preview ───────────────────────────────────────── -->
	<section class="board-preview-section" id="board-preview-section" aria-label="Board preview">
		<div class="board-preview-placeholder" id="board-placeholder">
			<div class="placeholder-inner">
				<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" opacity="0.3"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
				<p>Your board will appear here after you click Generate.</p>
			</div>
		</div>
		<div id="bingo-board-preview" class="bingo-board-wrap hidden"></div>

		<!-- Post-generate actions -->
		<div class="post-generate-actions hidden" id="post-generate-actions">
			<button class="btn btn-ghost" id="btn-regenerate" type="button">
				<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17,1 21,5 17,9"/><path d="M3,11V9a4,4,0,0,1,4-4h14"/><polyline points="7,23 3,19 7,15"/><path d="M21,13v2a4,4,0,0,1-4,4H3"/></svg>
				Shuffle Again
			</button>
			<div class="save-download-actions">
				<button class="btn btn-outline" id="btn-download-pdf" type="button">
					<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
					Download PDF
				</button>
				<button class="btn btn-outline" id="btn-download-png" type="button">
					<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
					Save Image
				</button>
				<button class="btn btn-primary" id="btn-save-card" type="button">
					<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/></svg>
					Save &amp; Share
				</button>
			</div>
		</div>
	</section>

	<!-- ── Right: Suggestions Sidebar ─────────────────────────────────── -->
	<aside class="suggestions-sidebar" aria-label="Suggestions">
		<div class="suggestions-header">
			<h3>Need ideas?</h3>
			<p>Click any suggestion to add it to your next empty square.</p>
		</div>
		<div id="suggestions-list" class="suggestions-list">
			<div class="suggestions-loading">Loading suggestions…</div>
		</div>
		<button class="btn btn-ghost btn-refresh-suggestions" id="btn-refresh-suggestions" type="button">
			<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23,4 23,10 17,10"/><polyline points="1,20 1,14 7,14"/><path d="M3.51,9a9,9,0,0,1,14.85-3.36L23,10M1,14l4.64,4.36A9,9,0,0,0,20.49,15"/></svg>
			More ideas
		</button>
	</aside>

</div>

<!-- Save modal (shown when not logged in) -->
<div class="modal-overlay hidden" id="save-modal-overlay">
	<div class="modal" id="save-modal">
		<button class="modal-close" id="close-save-modal" aria-label="Close">&times;</button>
		<div class="modal-content">
			<h2>Save Your Card</h2>
			<p>Create a free account to save your card, get a shareable link, and check things off digitally.</p>
			<div class="modal-actions">
				<a href="<?php echo esc_url( bingo_page_url( 'account' ) ); ?>" class="btn btn-primary btn-lg" id="modal-signup-link">Create Free Account</a>
				<a href="<?php echo esc_url( bingo_page_url( 'account' ) ); ?>" class="btn btn-ghost" id="modal-login-link">Log In</a>
			</div>
			<p class="modal-note">Your card data will be preserved — just sign in to save it.</p>
		</div>
	</div>
</div>

<!-- Saved confirmation toast -->
<div class="share-toast hidden" id="save-toast"></div>
