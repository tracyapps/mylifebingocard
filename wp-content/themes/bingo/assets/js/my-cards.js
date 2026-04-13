/* global BingoData */
'use strict';

const BingoMyCards = {

	init() {
		this.loadCards();
	},

	async loadCards() {
		const grid = document.getElementById( 'my-cards-grid' );
		if ( ! grid ) return;

		try {
			const res = await fetch( `${ BingoData.restUrl }my-cards`, {
				headers: { 'X-WP-Nonce': BingoData.nonce },
			} );
			const cards = await res.json();

			if ( ! cards.length ) {
				grid.innerHTML = `
					<div class="my-cards-empty">
						<p>You haven't saved any cards yet.</p>
						<a href="${ BingoData.createUrl }" class="btn btn-primary">Create Your First Card</a>
					</div>`;
				return;
			}

			grid.innerHTML = '';
			cards.forEach( card => grid.appendChild( this.renderCardThumb( card ) ) );
		} catch ( e ) {
			grid.innerHTML = '<p class="error-message">Could not load your cards. Please refresh.</p>';
		}
	},

	renderCardThumb( card ) {
		const a = document.createElement( 'a' );
		a.href = card.permalink;
		a.className = `card-thumb theme-${ card.theme }`;

		const board = card.board || [];
		const mini  = this.buildMiniBoard( board, card.checked || [] );
		const title = card.title || 'My Life Bingo Card';
		const name  = card.authorName || '';

		a.innerHTML = `
			<div class="card-thumb-board">${ mini }</div>
			<div class="card-thumb-meta">
				<strong class="thumb-title">${ this.escHtml( title ) }</strong>
				${ name ? `<span class="thumb-author">by ${ this.escHtml( name ) }</span>` : '' }
				<span class="thumb-checked">${ card.checked?.length || 0 } checked</span>
			</div>`;
		return a;
	},

	buildMiniBoard( board, checked ) {
		let html = '<div class="mini-grid">';
		for ( let i = 0; i < 25; i++ ) {
			const isFree    = i === 12;
			const isChecked = checked.includes( i );
			html += `<div class="mini-cell${ isFree ? ' free' : '' }${ isChecked ? ' checked' : '' }"></div>`;
		}
		html += '</div>';
		return html;
	},

	escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	},
};

document.addEventListener( 'DOMContentLoaded', () => BingoMyCards.init() );
