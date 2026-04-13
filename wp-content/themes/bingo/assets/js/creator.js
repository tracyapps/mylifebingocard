/* global BingoData, html2canvas, jspdf */
'use strict';

const BingoCreator = {

	MAX_ITEMS: 24,
	items: [],        // live array of user-typed strings
	board: [],        // 24 items in shuffled board order
	theme: 'classic',
	generated: false,
	saving: false,

	// ── Init ──────────────────────────────────────────────────────────────────

	init() {
		this.theme = Object.keys( BingoData.themes )[0] || 'classic';
		this.renderItemInputs( 5 ); // start with 5 blank inputs
		this.renderThemePicker();
		this.bindEvents();
		this.loadSuggestions();

		// Restore pending card data from sessionStorage (after login redirect)
		const pending = sessionStorage.getItem( 'bingo_pending_card' );
		if ( pending ) {
			try {
				const data = JSON.parse( pending );
				this.restoreFromPending( data );
			} catch ( e ) { /* ignore */ }
		}
	},

	// ── Event binding ─────────────────────────────────────────────────────────

	bindEvents() {
		document.getElementById( 'btn-add-item' ).addEventListener( 'click', () => this.addItemInput() );
		document.getElementById( 'btn-generate' ).addEventListener( 'click', () => this.generate() );
		document.getElementById( 'btn-regenerate' ).addEventListener( 'click', () => this.generate() );
		document.getElementById( 'btn-download-pdf' ).addEventListener( 'click', () => this.downloadPDF() );
		document.getElementById( 'btn-download-png' ).addEventListener( 'click', () => this.downloadPNG() );
		document.getElementById( 'btn-save-card' ).addEventListener( 'click', () => this.save() );
		document.getElementById( 'btn-refresh-suggestions' ).addEventListener( 'click', () => this.loadSuggestions() );
		document.getElementById( 'close-save-modal' ).addEventListener( 'click', () => this.closeSaveModal() );
		document.getElementById( 'save-modal-overlay' ).addEventListener( 'click', ( e ) => {
			if ( e.target === e.currentTarget ) this.closeSaveModal();
		} );

		// Store card in session before navigating to account page
		[ 'modal-signup-link', 'modal-login-link' ].forEach( id => {
			const el = document.getElementById( id );
			if ( el ) {
				el.addEventListener( 'click', () => this.storePendingCard() );
			}
		} );
	},

	// ── Item inputs ───────────────────────────────────────────────────────────

	renderItemInputs( count = this.MAX_ITEMS ) {
		const list = document.getElementById( 'item-list' );
		list.innerHTML = '';
		for ( let i = 0; i < count; i++ ) {
			this.appendInputRow( this.items[ i ] || '' );
		}
		this.updateItemCount();
	},

	appendInputRow( value = '' ) {
		const list = document.getElementById( 'item-list' );
		const current = list.querySelectorAll( '.item-row' ).length;
		if ( current >= this.MAX_ITEMS ) return;

		const row = document.createElement( 'div' );
		row.className = 'item-row';
		row.innerHTML = `
			<span class="item-number">${ current + 1 }</span>
			<input
				type="text"
				class="item-input"
				placeholder="Enter a life experience…"
				maxlength="60"
				value="${ this.escAttr( value ) }"
			/>
			<button class="item-remove" aria-label="Remove" type="button">
				<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>
		`;

		const input = row.querySelector( 'input' );
		const removeBtn = row.querySelector( '.item-remove' );

		input.addEventListener( 'input', () => this.syncItems() );
		removeBtn.addEventListener( 'click', () => {
			row.remove();
			this.renumberInputs();
			this.syncItems();
		} );

		list.appendChild( row );
		this.syncItems();
	},

	addItemInput() {
		this.appendInputRow();
		// Focus the new input
		const inputs = document.querySelectorAll( '.item-input' );
		if ( inputs.length ) inputs[ inputs.length - 1 ].focus();
	},

	syncItems() {
		const inputs = document.querySelectorAll( '.item-input' );
		this.items = Array.from( inputs ).map( i => i.value.trim() ).filter( v => v !== '' );
		this.updateItemCount();
		this.updateGenerateButton();
	},

	renumberInputs() {
		document.querySelectorAll( '.item-row' ).forEach( ( row, i ) => {
			const num = row.querySelector( '.item-number' );
			if ( num ) num.textContent = i + 1;
		} );
	},

	updateItemCount() {
		const el = document.getElementById( 'item-count' );
		if ( el ) el.textContent = this.items.length;
		const addBtn = document.getElementById( 'btn-add-item' );
		if ( addBtn ) {
			const count = document.querySelectorAll( '.item-row' ).length;
			addBtn.disabled = count >= this.MAX_ITEMS;
			addBtn.style.display = count >= this.MAX_ITEMS ? 'none' : '';
		}
	},

	updateGenerateButton() {
		const btn = document.getElementById( 'btn-generate' );
		if ( btn ) btn.disabled = this.items.length < 1;
	},

	// ── Board generation ──────────────────────────────────────────────────────

	generate() {
		const items = [ ...this.items ];
		if ( items.length === 0 ) return;

		// Pad to 24 by cycling existing items if fewer than 24
		while ( items.length < this.MAX_ITEMS ) {
			items.push( this.items[ items.length % this.items.length ] );
		}

		// Fisher-Yates shuffle
		for ( let i = items.length - 1; i > 0; i-- ) {
			const j = Math.floor( Math.random() * ( i + 1 ) );
			[ items[ i ], items[ j ] ] = [ items[ j ], items[ i ] ];
		}

		this.board = items.slice( 0, 24 );
		this.generated = true;
		this.renderBoard();
		this.showPostGenerateActions();
	},

	// ── Board rendering ───────────────────────────────────────────────────────

	renderBoard( container = null, board = null, checked = [], interactive = false ) {
		const el = container || document.getElementById( 'bingo-board-preview' );
		if ( ! el ) return;

		const boardData = board || this.board;
		const cardTitle  = document.getElementById( 'card-title' )?.value.trim() || 'My Life Bingo Card';
		const cardAuthor = document.getElementById( 'card-author' )?.value.trim() || '';

		el.className = `bingo-board-wrap theme-${ this.theme }`;
		el.innerHTML = this.buildBoardHTML( boardData, cardTitle, cardAuthor, checked, interactive );

		if ( interactive ) {
			el.querySelectorAll( '.bingo-cell:not(.free-space)' ).forEach( cell => {
				cell.addEventListener( 'click', () => this.handleCellClick( cell ) );
			} );
		}

		// Show the board, hide placeholder
		el.classList.remove( 'hidden' );
		const placeholder = document.getElementById( 'board-placeholder' );
		if ( placeholder ) placeholder.classList.add( 'hidden' );
	},

	buildBoardHTML( board, title, authorName, checked = [], interactive = false ) {
		let cells = '';
		for ( let i = 0; i < 25; i++ ) {
			if ( i === 12 ) {
				cells += `<div class="bingo-cell free-space"><span class="cell-text">FREE</span></div>`;
				continue;
			}
			const itemIdx = i < 12 ? i : i - 1;
			const text    = board[ itemIdx ] || '';
			const isChecked = checked.includes( i );
			cells += `
				<div class="bingo-cell${ isChecked ? ' checked' : '' }" data-pos="${ i }" ${ interactive ? 'role="button" tabindex="0"' : '' }>
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

	handleCellClick( cell ) {
		// Preview mode only — toggle visual state, no server call
		cell.classList.toggle( 'checked' );
		const existing = cell.querySelector( '.check-mark' );
		if ( existing ) {
			existing.remove();
		} else {
			const mark = document.createElement( 'span' );
			mark.className = 'check-mark';
			mark.textContent = '✓';
			cell.prepend( mark );
		}
	},

	showPostGenerateActions() {
		document.getElementById( 'post-generate-actions' )?.classList.remove( 'hidden' );
	},

	// ── Theme picker ──────────────────────────────────────────────────────────

	renderThemePicker() {
		const picker = document.getElementById( 'theme-picker' );
		if ( ! picker ) return;
		picker.innerHTML = '';
		Object.entries( BingoData.themes ).forEach( ( [ slug, theme ] ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = `theme-swatch${ slug === this.theme ? ' active' : '' }`;
			btn.dataset.theme = slug;
			btn.title = theme.label;
			btn.style.cssText = `background:${ theme.boardBg };border-color:${ theme.cellBorder };`;
			btn.innerHTML = `
				<span class="swatch-dot" style="background:${ theme.cellBg };"></span>
				<span class="swatch-dot" style="background:${ theme.freeBg };"></span>
				<span class="swatch-label">${ theme.label }</span>`;
			btn.addEventListener( 'click', () => this.selectTheme( slug ) );
			picker.appendChild( btn );
		} );
	},

	selectTheme( slug ) {
		this.theme = slug;
		document.querySelectorAll( '.theme-swatch' ).forEach( el => {
			el.classList.toggle( 'active', el.dataset.theme === slug );
		} );
		if ( this.generated ) {
			const preview = document.getElementById( 'bingo-board-preview' );
			if ( preview ) preview.className = `bingo-board-wrap theme-${ slug }`;
		}
	},

	// ── Suggestions ───────────────────────────────────────────────────────────

	async loadSuggestions() {
		const list = document.getElementById( 'suggestions-list' );
		if ( ! list ) return;
		list.innerHTML = '<div class="suggestions-loading">Loading…</div>';

		// Build exclude string from current items
		const exclude = this.items.join( '|' );
		const url = `${ BingoData.restUrl }suggestions?count=16${ exclude ? '&exclude=' + encodeURIComponent( exclude ) : '' }`;

		try {
			const res = await fetch( url );
			const suggestions = await res.json();

			if ( ! suggestions.length ) {
				list.innerHTML = '<p class="suggestions-empty">No more suggestions — great job filling your card!</p>';
				return;
			}

			list.innerHTML = '';
			const grouped = {};
			suggestions.forEach( s => {
				const cat = s.category || 'General';
				if ( ! grouped[ cat ] ) grouped[ cat ] = [];
				grouped[ cat ].push( s );
			} );

			Object.entries( grouped ).forEach( ( [ category, items ] ) => {
				const group = document.createElement( 'div' );
				group.className = 'suggestion-group';
				group.innerHTML = `<div class="suggestion-category">${ this.escHtml( category ) }</div>`;
				items.forEach( s => {
					const chip = document.createElement( 'button' );
					chip.type = 'button';
					chip.className = 'suggestion-chip';
					chip.textContent = s.name;
					chip.addEventListener( 'click', () => this.addSuggestion( s.name ) );
					group.appendChild( chip );
				} );
				list.appendChild( group );
			} );
		} catch ( e ) {
			list.innerHTML = '<p class="suggestions-error">Could not load suggestions.</p>';
		}
	},

	addSuggestion( text ) {
		// Find first empty input, or add new row
		const inputs = document.querySelectorAll( '.item-input' );
		let added = false;
		for ( const input of inputs ) {
			if ( input.value.trim() === '' ) {
				input.value = text;
				input.dispatchEvent( new Event( 'input' ) );
				input.focus();
				added = true;
				break;
			}
		}
		if ( ! added ) {
			if ( document.querySelectorAll( '.item-row' ).length < this.MAX_ITEMS ) {
				this.appendInputRow( text );
			}
		}
		// Remove the chip that was clicked
		document.querySelectorAll( '.suggestion-chip' ).forEach( chip => {
			if ( chip.textContent === text ) chip.remove();
		} );
	},

	// ── Save ──────────────────────────────────────────────────────────────────

	async save() {
		if ( this.saving || ! this.generated ) return;

		if ( ! BingoData.user.logged_in ) {
			this.showSaveModal();
			return;
		}

		this.saving = true;
		const btn = document.getElementById( 'btn-save-card' );
		btn.disabled = true;
		btn.textContent = 'Saving…';

		const title       = document.getElementById( 'card-title' )?.value.trim() || 'My Life Bingo Card';
		const author_name = document.getElementById( 'card-author' )?.value.trim() || '';

		try {
			const res = await fetch( `${ BingoData.restUrl }cards`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': BingoData.nonce,
				},
				body: JSON.stringify( {
					title,
					author_name,
					items: this.items,
					board: this.board,
					theme: this.theme,
				} ),
			} );

			const data = await res.json();
			if ( ! res.ok ) throw new Error( data.message || 'Save failed.' );

			// Store guest token if returned
			if ( data.guestToken ) {
				const tokens = JSON.parse( localStorage.getItem( 'bingo_guest_tokens' ) || '{}' );
				tokens[ data.id ] = data.guestToken;
				localStorage.setItem( 'bingo_guest_tokens', JSON.stringify( tokens ) );
			}

			sessionStorage.removeItem( 'bingo_pending_card' );
			this.showToast( 'Card saved! Redirecting…' );
			setTimeout( () => { window.location.href = data.permalink; }, 1200 );
		} catch ( err ) {
			this.showToast( err.message, true );
			btn.disabled = false;
			btn.innerHTML = `<svg …></svg> Save &amp; Share`;
		} finally {
			this.saving = false;
		}
	},

	// ── Guest token + pending state ───────────────────────────────────────────

	storePendingCard() {
		const title       = document.getElementById( 'card-title' )?.value.trim() || '';
		const author_name = document.getElementById( 'card-author' )?.value.trim() || '';
		sessionStorage.setItem( 'bingo_pending_card', JSON.stringify( {
			title, author_name,
			items: this.items,
			board: this.board,
			theme: this.theme,
			generated: this.generated,
		} ) );
	},

	restoreFromPending( data ) {
		this.items    = data.items || [];
		this.board    = data.board || [];
		this.theme    = data.theme || 'classic';
		this.generated = data.generated || false;

		// Re-populate inputs
		const list = document.getElementById( 'item-list' );
		list.innerHTML = '';
		this.items.forEach( v => this.appendInputRow( v ) );
		if ( list.querySelectorAll( '.item-row' ).length < 5 ) {
			for ( let i = list.querySelectorAll( '.item-row' ).length; i < 5; i++ ) this.appendInputRow();
		}

		if ( data.title ) {
			const titleEl = document.getElementById( 'card-title' );
			if ( titleEl ) titleEl.value = data.title;
		}
		if ( data.author_name ) {
			const authorEl = document.getElementById( 'card-author' );
			if ( authorEl ) authorEl.value = data.author_name;
		}
		if ( this.generated && this.board.length === 24 ) {
			this.renderBoard();
			this.showPostGenerateActions();
		}
		this.selectTheme( this.theme );
		this.renderThemePicker();
	},

	// ── Export ────────────────────────────────────────────────────────────────

	async downloadPDF() {
		const el = document.getElementById( 'bingo-card-printable' );
		if ( ! el ) return;
		const btn = document.getElementById( 'btn-download-pdf' );
		btn.disabled = true;
		btn.textContent = 'Generating…';

		try {
			const canvas = await html2canvas( el, { scale: 2, useCORS: true, backgroundColor: null } );
			const { jsPDF } = jspdf;
			const pdf = new jsPDF( { orientation: 'portrait', unit: 'px', format: [ canvas.width / 2, canvas.height / 2 ] } );
			pdf.addImage( canvas.toDataURL( 'image/png' ), 'PNG', 0, 0, canvas.width / 2, canvas.height / 2 );
			const title = document.getElementById( 'card-title' )?.value.trim() || 'bingo-card';
			pdf.save( `${ title.replace( /\s+/g, '-' ).toLowerCase() }.pdf` );
		} catch ( e ) {
			this.showToast( 'PDF export failed. Please try again.', true );
		} finally {
			btn.disabled = false;
			btn.textContent = 'Download PDF';
		}
	},

	async downloadPNG() {
		const el = document.getElementById( 'bingo-card-printable' );
		if ( ! el ) return;
		const btn = document.getElementById( 'btn-download-png' );
		btn.disabled = true;
		btn.textContent = 'Generating…';

		try {
			const canvas = await html2canvas( el, { scale: 2, useCORS: true, backgroundColor: null } );
			const link = document.createElement( 'a' );
			const title = document.getElementById( 'card-title' )?.value.trim() || 'bingo-card';
			link.download = `${ title.replace( /\s+/g, '-' ).toLowerCase() }.png`;
			link.href = canvas.toDataURL( 'image/png' );
			link.click();
		} catch ( e ) {
			this.showToast( 'Image export failed. Please try again.', true );
		} finally {
			btn.disabled = false;
			btn.textContent = 'Save Image';
		}
	},

	// ── Modal + Toast ─────────────────────────────────────────────────────────

	showSaveModal() {
		document.getElementById( 'save-modal-overlay' )?.classList.remove( 'hidden' );
		document.body.style.overflow = 'hidden';
	},

	closeSaveModal() {
		document.getElementById( 'save-modal-overlay' )?.classList.add( 'hidden' );
		document.body.style.overflow = '';
	},

	showToast( message, isError = false ) {
		const toast = document.getElementById( 'save-toast' );
		if ( ! toast ) return;
		toast.textContent = message;
		toast.className = `share-toast${ isError ? ' error' : '' }`;
		setTimeout( () => toast.classList.add( 'hidden' ), 3000 );
	},

	// ── Helpers ───────────────────────────────────────────────────────────────

	escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	},

	escAttr( str ) {
		return String( str ).replace( /"/g, '&quot;' ).replace( /'/g, '&#39;' );
	},
};

document.addEventListener( 'DOMContentLoaded', () => BingoCreator.init() );
