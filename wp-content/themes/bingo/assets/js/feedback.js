/**
 * Bingo Feedback Widget
 * Floating button → modal → POST /bingo/v1/feedback
 * Draft persists in localStorage across page navigations (24h TTL).
 */
( function () {
	'use strict';

	const STORAGE_KEY    = 'bingo_feedback_draft';
	const DRAFT_TTL_MS   = 86_400_000; // 24 hours
	const HTML2CANVAS_URL = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';

	// ── Element refs (resolved on DOMContentLoaded) ───────────────────────────
	let trigger, widget, minimizeBtn, closeBtn;
	let typeButtons;
	let messageEl, screenshotToggle, screenshotPreview, screenshotImg, retakeBtn;
	let browserInfoToggle, emailField, emailInput, emailHint;
	let wantsReplyToggle, clearBtn, submitBtn, statusEl;
	let successOverlay, successMsgEl;

	let currentType        = null;
	let screenshotDataUrl  = null;
	let isCapturing        = false;
	let isSubmitting       = false;

	// ── Init ──────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		trigger           = document.getElementById( 'feedback-trigger' );
		widget            = document.getElementById( 'feedback-widget' );
		minimizeBtn       = document.getElementById( 'fw-minimize' );
		closeBtn          = document.getElementById( 'fw-close' );
		typeButtons       = document.querySelectorAll( '.fw-type-btn' );
		messageEl         = document.getElementById( 'fw-message' );
		screenshotToggle  = document.getElementById( 'fw-screenshot' );
		screenshotPreview = document.getElementById( 'fw-screenshot-preview' );
		screenshotImg     = document.getElementById( 'fw-screenshot-img' );
		retakeBtn         = document.getElementById( 'fw-retake' );
		browserInfoToggle = document.getElementById( 'fw-browser-info' );
		emailField        = document.getElementById( 'fw-email-field' );
		emailInput        = document.getElementById( 'fw-email' );
		emailHint         = document.getElementById( 'fw-email-hint' );
		wantsReplyToggle  = document.getElementById( 'fw-wants-reply' );
		clearBtn          = document.getElementById( 'fw-clear' );
		submitBtn         = document.getElementById( 'fw-submit' );
		statusEl          = document.getElementById( 'fw-status' );
		successOverlay    = document.getElementById( 'fw-success-overlay' );
		successMsgEl      = document.getElementById( 'fw-success-msg' );

		if ( ! trigger || ! widget ) {
			return;
		}

		// Auto-fill email for logged-in users
		if ( window.BingoFeedback && BingoFeedback.user && BingoFeedback.user.logged_in ) {
			emailInput.value = BingoFeedback.user.email || '';
		}

		// Email required state for logged-in vs guest
		updateEmailRequired();

		// Restore any saved draft
		const draft = loadDraft();
		if ( draft ) {
			restoreDraft( draft );
		}
		updateTriggerBadge();

		// ── Event listeners ───────────────────────────────────────────────────

		trigger.addEventListener( 'click', function () {
			if ( widget.getAttribute( 'aria-hidden' ) === 'true' ) {
				openWidget();
			} else {
				minimizeWidget();
			}
		} );

		minimizeBtn.addEventListener( 'click', function () {
			saveDraft();
			minimizeWidget();
		} );

		closeBtn.addEventListener( 'click', function () {
			handleClearAndClose();
		} );

		typeButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				selectType( btn.dataset.type );
				saveDraft();
			} );
		} );

		messageEl.addEventListener( 'input', saveDraft );

		if ( screenshotToggle ) {
			screenshotToggle.addEventListener( 'change', function () {
				if ( screenshotToggle.checked ) {
					handleScreenshotCapture();
				} else {
					screenshotDataUrl = null;
					if ( screenshotPreview ) {
						screenshotPreview.style.display = 'none';
					}
					saveDraft();
				}
			} );
		}

		if ( retakeBtn ) {
			retakeBtn.addEventListener( 'click', handleScreenshotCapture );
		}

		emailInput.addEventListener( 'input', saveDraft );
		wantsReplyToggle.addEventListener( 'change', function () {
			updateEmailRequired();
			saveDraft();
		} );

		if ( browserInfoToggle ) {
			browserInfoToggle.addEventListener( 'change', saveDraft );
		}

		clearBtn.addEventListener( 'click', handleClearAndClose );
		submitBtn.addEventListener( 'click', handleSubmit );

		// Close on Escape key (minimize, not clear)
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && widget.getAttribute( 'aria-hidden' ) !== 'true' ) {
				saveDraft();
				minimizeWidget();
			}
		} );
	} );

	// ── Draft management ──────────────────────────────────────────────────────

	function saveDraft() {
		const draft = {
			type:        currentType,
			message:     messageEl ? messageEl.value : '',
			email:       emailInput ? emailInput.value : '',
			wantsReply:  wantsReplyToggle ? wantsReplyToggle.checked : false,
			browserInfo: browserInfoToggle ? browserInfoToggle.checked : true,
			screenshot:  null, // set separately below
			savedAt:     Date.now(),
		};

		// Screenshot can be large — store separately, skip on quota error
		if ( screenshotDataUrl ) {
			draft.screenshot = screenshotDataUrl;
		}

		try {
			localStorage.setItem( STORAGE_KEY, JSON.stringify( draft ) );
		} catch ( e ) {
			// Quota exceeded (screenshot too large?) — save without it
			draft.screenshot = null;
			try {
				localStorage.setItem( STORAGE_KEY, JSON.stringify( draft ) );
			} catch ( e2 ) { /* nothing we can do */ }
		}

		updateTriggerBadge();
	}

	function loadDraft() {
		const raw = localStorage.getItem( STORAGE_KEY );
		if ( ! raw ) {
			return null;
		}
		try {
			const draft = JSON.parse( raw );
			if ( Date.now() - draft.savedAt > DRAFT_TTL_MS ) {
				localStorage.removeItem( STORAGE_KEY );
				return null;
			}
			return draft;
		} catch ( e ) {
			return null;
		}
	}

	function restoreDraft( draft ) {
		if ( draft.type ) {
			selectType( draft.type, false );
		}
		if ( draft.message && messageEl ) {
			messageEl.value = draft.message;
		}
		if ( draft.email && emailInput ) {
			emailInput.value = draft.email;
		}
		if ( draft.wantsReply && wantsReplyToggle ) {
			wantsReplyToggle.checked = true;
			updateEmailRequired();
		}
		if ( typeof draft.browserInfo !== 'undefined' && browserInfoToggle ) {
			browserInfoToggle.checked = !! draft.browserInfo;
		}
		if ( draft.screenshot ) {
			screenshotDataUrl = draft.screenshot;
			if ( screenshotToggle ) {
				screenshotToggle.checked = true;
			}
			if ( screenshotPreview && screenshotImg ) {
				screenshotImg.src = draft.screenshot;
				screenshotPreview.style.display = 'block';
			}
		}
	}

	function clearDraft() {
		localStorage.removeItem( STORAGE_KEY );
		screenshotDataUrl = null;
		currentType       = null;

		if ( messageEl ) {
			messageEl.value = '';
		}
		if ( emailInput ) {
			emailInput.value = ( window.BingoFeedback && BingoFeedback.user && BingoFeedback.user.logged_in )
				? ( BingoFeedback.user.email || '' )
				: '';
		}
		if ( wantsReplyToggle ) {
			wantsReplyToggle.checked = false;
		}
		if ( browserInfoToggle ) {
			browserInfoToggle.checked = true;
		}
		if ( screenshotToggle ) {
			screenshotToggle.checked = false;
		}
		if ( screenshotPreview ) {
			screenshotPreview.style.display = 'none';
		}
		typeButtons.forEach( function ( b ) {
			b.classList.remove( 'selected' );
		} );
		clearStatus();
		updateTriggerBadge();
	}

	function updateTriggerBadge() {
		if ( ! trigger ) {
			return;
		}
		const hasDraft = !! localStorage.getItem( STORAGE_KEY );
		trigger.classList.toggle( 'has-draft', hasDraft );
	}

	// ── Widget open/close/minimize ────────────────────────────────────────────

	function openWidget() {
		widget.removeAttribute( 'aria-hidden' );
		trigger.setAttribute( 'aria-expanded', 'true' );
		if ( messageEl ) {
			messageEl.focus();
		}
	}

	function minimizeWidget() {
		widget.setAttribute( 'aria-hidden', 'true' );
		trigger.setAttribute( 'aria-expanded', 'false' );
	}

	function handleClearAndClose() {
		const hasDraft = !! localStorage.getItem( STORAGE_KEY );
		const hasContent = currentType || ( messageEl && messageEl.value.trim() );

		if ( hasContent || hasDraft ) {
			if ( ! window.confirm( 'Clear this draft and close?' ) ) {
				return;
			}
		}
		clearDraft();
		minimizeWidget();
	}

	// ── Type selection ────────────────────────────────────────────────────────

	function selectType( type, save ) {
		currentType = type;
		typeButtons.forEach( function ( b ) {
			b.classList.toggle( 'selected', b.dataset.type === type );
		} );
		if ( save !== false ) {
			saveDraft();
		}
	}

	// ── Email required logic ──────────────────────────────────────────────────

	function updateEmailRequired() {
		if ( ! emailInput ) {
			return;
		}
		const isLoggedIn = window.BingoFeedback && BingoFeedback.user && BingoFeedback.user.logged_in;

		if ( isLoggedIn ) {
			// Only required if they want a reply
			const wantsReply = wantsReplyToggle && wantsReplyToggle.checked;
			emailInput.required = wantsReply;
		} else {
			// Always required for guests
			emailInput.required = true;
		}
	}

	// ── Screenshot capture ────────────────────────────────────────────────────

	async function handleScreenshotCapture() {
		if ( isCapturing ) {
			return;
		}

		// Show spinner state
		if ( screenshotToggle ) {
			screenshotToggle.disabled = true;
		}
		if ( retakeBtn ) {
			retakeBtn.textContent = 'Capturing…';
			retakeBtn.disabled    = true;
		}

		try {
			screenshotDataUrl = await captureScreenshot();

			if ( screenshotImg && screenshotPreview ) {
				screenshotImg.src                 = screenshotDataUrl;
				screenshotPreview.style.display   = 'block';
			}
			if ( screenshotToggle ) {
				screenshotToggle.checked = true;
			}
			saveDraft();
		} catch ( err ) {
			console.warn( '[Bingo Feedback] Screenshot failed:', err );
			screenshotDataUrl = null;
			if ( screenshotToggle ) {
				screenshotToggle.checked = false;
			}
			showStatus( 'Screenshot capture failed. Please try again.', 'error' );
		} finally {
			if ( screenshotToggle ) {
				screenshotToggle.disabled = false;
			}
			if ( retakeBtn ) {
				retakeBtn.textContent = 'Retake';
				retakeBtn.disabled    = false;
			}
		}
	}

	async function captureScreenshot() {
		isCapturing = true;

		// Load html2canvas lazily
		if ( ! window.html2canvas ) {
			await loadScript( HTML2CANVAS_URL );
		}

		// Briefly hide the widget so it doesn't appear in the shot
		widget.style.visibility  = 'hidden';
		trigger.style.visibility = 'hidden';

		// Let the browser repaint
		await new Promise( function ( r ) { setTimeout( r, 80 ); } );

		let dataUrl;
		try {
			const canvas = await html2canvas( document.body, {
				scale:      0.5,
				useCORS:    true,
				logging:    false,
				allowTaint: true,
			} );
			dataUrl = canvas.toDataURL( 'image/jpeg', 0.6 );
		} finally {
			widget.style.visibility  = '';
			trigger.style.visibility = '';
			isCapturing              = false;
		}

		return dataUrl;
	}

	function loadScript( src ) {
		return new Promise( function ( resolve, reject ) {
			const s    = document.createElement( 'script' );
			s.src      = src;
			s.onload   = resolve;
			s.onerror  = reject;
			document.head.appendChild( s );
		} );
	}

	// ── Submission ────────────────────────────────────────────────────────────

	async function handleSubmit() {
		if ( isSubmitting ) {
			return;
		}
		clearStatus();

		// Client-side validation
		if ( ! currentType ) {
			showStatus( 'Please select a feedback type.', 'error' );
			return;
		}
		const message = messageEl ? messageEl.value.trim() : '';
		if ( message.length < 3 ) {
			showStatus( 'Please enter a message (at least 3 characters).', 'error' );
			if ( messageEl ) { messageEl.focus(); }
			return;
		}

		const email      = emailInput ? emailInput.value.trim() : '';
		const isLoggedIn = window.BingoFeedback && BingoFeedback.user && BingoFeedback.user.logged_in;
		const wantsReply = wantsReplyToggle ? wantsReplyToggle.checked : false;

		if ( ! isLoggedIn && ! email ) {
			showStatus( 'Please enter your email address.', 'error' );
			if ( emailInput ) { emailInput.focus(); }
			return;
		}
		if ( email && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( email ) ) {
			showStatus( 'Please enter a valid email address.', 'error' );
			if ( emailInput ) { emailInput.focus(); }
			return;
		}
		if ( wantsReply && ! email ) {
			showStatus( 'Please enter your email to receive a reply.', 'error' );
			if ( emailInput ) { emailInput.focus(); }
			return;
		}

		// Build payload
		const payload = {
			type:        currentType,
			message:     message,
			email:       email,
			wants_reply: wantsReply,
			page_url:    window.location.href,
			browser_info: null,
			screenshot:  null,
		};

		if ( browserInfoToggle && browserInfoToggle.checked ) {
			payload.browser_info = getBrowserInfo();
		}
		if ( screenshotDataUrl ) {
			payload.screenshot = screenshotDataUrl;
		}

		// Submit
		isSubmitting       = true;
		submitBtn.disabled = true;
		submitBtn.textContent = 'Sending…';

		try {
			const res = await fetch( BingoFeedback.restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   BingoFeedback.nonce,
				},
				body: JSON.stringify( payload ),
			} );

			const data = await res.json();

			if ( ! res.ok ) {
				throw new Error( data.message || 'Submission failed. Please try again.' );
			}

			const successMsg = ( window.BingoFeedback && BingoFeedback.settings && BingoFeedback.settings.success_message )
				? BingoFeedback.settings.success_message
				: "Thanks! We'll look into it soon.";
			clearDraft();
			showSuccessOverlay( successMsg );

			// Auto-close after 3.5 s
			setTimeout( function () {
				hideSuccessOverlay();
				minimizeWidget();
			}, 3500 );

		} catch ( err ) {
			showStatus( err.message || 'Something went wrong. Please try again.', 'error' );
		} finally {
			isSubmitting          = false;
			submitBtn.disabled    = false;
			submitBtn.textContent = 'Send Feedback';
		}
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	function getBrowserInfo() {
		return {
			userAgent:      navigator.userAgent,
			platform:       navigator.platform,
			language:       navigator.language,
			screenWidth:    screen.width,
			screenHeight:   screen.height,
			viewportWidth:  window.innerWidth,
			viewportHeight: window.innerHeight,
			colorDepth:     screen.colorDepth,
		};
	}

	function showSuccessOverlay( msg ) {
		if ( ! successOverlay ) { return; }
		if ( successMsgEl ) { successMsgEl.textContent = msg; }
		successOverlay.removeAttribute( 'aria-hidden' );
	}

	function hideSuccessOverlay() {
		if ( ! successOverlay ) { return; }
		successOverlay.setAttribute( 'aria-hidden', 'true' );
	}

	function showStatus( msg, type ) {
		if ( ! statusEl ) { return; }
		statusEl.textContent = msg;
		statusEl.className   = 'fw-status ' + type;
	}

	function clearStatus() {
		if ( ! statusEl ) { return; }
		statusEl.textContent = '';
		statusEl.className   = 'fw-status';
	}

}() );
