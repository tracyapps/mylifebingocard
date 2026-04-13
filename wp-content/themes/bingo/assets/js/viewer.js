/* global BingoData, html2canvas, jspdf */
'use strict';

const BingoViewer = {

	card: null,
	checked: [],

	// ── Init ──────────────────────────────────────────────────────────────────

	init() {
		this.card    = BingoData.card;
		this.checked = [ ...( this.card.checked || [] ) ];

		this.renderBoard();
		this.bindEvents();
	},

	// ── Render ────────────────────────────────────────────────────────────────

	renderBoard() {
		const el = document.getElementById( 'bingo-board-view' );
		if ( ! el ) return;

		const { title, authorName, theme, board, isOwner } = this.card;

		el.className = `bingo-board-wrap theme-${ theme }`;
		el.innerHTML = this.buildBoardHTML( board, title, authorName, this.checked, isOwner );

		if ( isOwner ) {
			el.querySelectorAll( '.bingo-cell:not(.free-space)' ).forEach( cell => {
				cell.addEventListener( 'click', () => this.toggleCell( cell ) );
				cell.addEventListener( 'keydown', e => {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						e.preventDefault();
						this.toggleCell( cell );
					}
				} );
			} );
		}
	},

	buildBoardHTML( board, title, authorName, checked, interactive ) {
		let cells = '';
		for ( let i = 0; i < 25; i++ ) {
			if ( i === 12 ) {
				cells += `<div class="bingo-cell free-space"><span class="cell-text">FREE</span></div>`;
				continue;
			}
			const itemIdx   = i < 12 ? i : i - 1;
			const text      = board[ itemIdx ] || '';
			const isChecked = checked.includes( i );
			cells += `
				<div
					class="bingo-cell${ isChecked ? ' checked' : '' }"
					data-pos="${ i }"
					${ interactive ? 'role="button" tabindex="0"' : '' }
					${ interactive ? 'title="Click to check off"' : '' }
				>
					${ isChecked ? '<span class="check-mark">✓</span>' : '' }
					<span class="cell-text">${ this.escHtml( text ) }</span>
				</div>`;
		}

		return `
			<div class="bingo-card" id="bingo-card-printable">
				<div class="bingo-card-header">
					<div class="bingo-card-title">${ this.escHtml( title ) }</div>
					${ authorName ? `<div class="bingo-card-author">by ${ this.escHtml( authorName ) }</div>` : '' }
					<div class="bingo-letters">
						<span>B</span><span>I</span><span>N</span><span>G</span><span>O</span>
					</div>
				</div>
				<div class="bingo-grid">${ cells }</div>
			</div>`;
	},

	// ── Check off ─────────────────────────────────────────────────────────────

	async toggleCell( cell ) {
		const pos = parseInt( cell.dataset.pos, 10 );
		if ( pos === 12 ) return;

		// Optimistic UI
		const wasChecked = this.checked.includes( pos );
		cell.classList.toggle( 'checked', ! wasChecked );
		const existing = cell.querySelector( '.check-mark' );
		if ( existing ) {
			existing.remove();
		} else {
			const mark = document.createElement( 'span' );
			mark.className = 'check-mark';
			mark.textContent = '✓';
			cell.prepend( mark );
		}

		if ( wasChecked ) {
			this.checked = this.checked.filter( p => p !== pos );
		} else {
			this.checked.push( pos );
		}

		// Persist to server
		try {
			const guestToken = this.getGuestToken();
			const body = { position: pos };
			if ( guestToken ) body.guest_token = guestToken;

			const res = await fetch( `${ BingoData.restUrl }cards/${ this.card.id }/check`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': BingoData.nonce,
				},
				body: JSON.stringify( body ),
			} );
			const data = await res.json();
			if ( res.ok ) {
				this.checked = data.checked;
			} else {
				// Revert on error
				this.revertCell( cell, wasChecked, pos );
			}
		} catch ( e ) {
			this.revertCell( cell, wasChecked, pos );
		}
	},

	revertCell( cell, wasChecked, pos ) {
		cell.classList.toggle( 'checked', wasChecked );
		const mark = cell.querySelector( '.check-mark' );
		if ( wasChecked && ! mark ) {
			const m = document.createElement( 'span' );
			m.className = 'check-mark';
			m.textContent = '✓';
			cell.prepend( m );
		} else if ( ! wasChecked && mark ) {
			mark.remove();
		}
		if ( wasChecked ) {
			this.checked.push( pos );
		} else {
			this.checked = this.checked.filter( p => p !== pos );
		}
	},

	getGuestToken() {
		try {
			const tokens = JSON.parse( localStorage.getItem( 'bingo_guest_tokens' ) || '{}' );
			return tokens[ this.card.id ] || null;
		} catch { return null; }
	},

	// ── Share ─────────────────────────────────────────────────────────────────

	bindEvents() {
		document.getElementById( 'btn-share-card' )?.addEventListener( 'click', () => this.share() );
		document.getElementById( 'btn-download-pdf-view' )?.addEventListener( 'click', () => this.downloadPDF() );
		document.getElementById( 'btn-download-png-view' )?.addEventListener( 'click', () => this.downloadPNG() );
	},

	share() {
		const url = this.card.permalink;
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( url ).then( () => this.showToast( 'Link copied to clipboard!' ) );
		} else {
			const input = document.createElement( 'input' );
			input.value = url;
			document.body.appendChild( input );
			input.select();
			document.execCommand( 'copy' );
			document.body.removeChild( input );
			this.showToast( 'Link copied to clipboard!' );
		}
	},

	// ── Export ────────────────────────────────────────────────────────────────

	async downloadPDF() {
		const el = document.getElementById( 'bingo-card-printable' );
		if ( ! el ) return;
		try {
			const canvas = await html2canvas( el, { scale: 2, useCORS: true, backgroundColor: null } );
			const { jsPDF } = jspdf;
			const pdf = new jsPDF( { orientation: 'portrait', unit: 'px', format: [ canvas.width / 2, canvas.height / 2 ] } );
			pdf.addImage( canvas.toDataURL( 'image/png' ), 'PNG', 0, 0, canvas.width / 2, canvas.height / 2 );
			pdf.save( `${ this.card.title.replace( /\s+/g, '-' ).toLowerCase() }.pdf` );
		} catch ( e ) {
			this.showToast( 'PDF export failed.', true );
		}
	},

	async downloadPNG() {
		const el = document.getElementById( 'bingo-card-printable' );
		if ( ! el ) return;
		try {
			const canvas = await html2canvas( el, { scale: 2, useCORS: true, backgroundColor: null } );
			const link = document.createElement( 'a' );
			link.download = `${ this.card.title.replace( /\s+/g, '-' ).toLowerCase() }.png`;
			link.href = canvas.toDataURL( 'image/png' );
			link.click();
		} catch ( e ) {
			this.showToast( 'Image export failed.', true );
		}
	},

	// ── Toast ─────────────────────────────────────────────────────────────────

	showToast( message, isError = false ) {
		const toast = document.getElementById( 'share-toast' );
		if ( ! toast ) return;
		toast.textContent = message;
		toast.className = `share-toast${ isError ? ' error' : '' }`;
		setTimeout( () => toast.classList.add( 'hidden' ), 3000 );
	},

	escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	},
};

document.addEventListener( 'DOMContentLoaded', () => BingoViewer.init() );
