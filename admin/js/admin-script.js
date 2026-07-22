/**
 * Admin behaviour for Beplus Smart SEO Google & AI.
 *
 * Handles: meta box tab switching, Google snippet preview, character counters,
 * the client-side focus keyword analyzer, the shared WP media uploader, the
 * FAQ repeater, schema-type conditional fields, settings save feedback, and
 * Gutenberg (Block Editor) data-store subscriptions for real-time updates.
 *
 * Works with both Classic Editor and Block Editor (Gutenberg).
 *
 * Loaded on:
 *   - post.php / post-new.php  → ssoAdmin.context = 'meta-box'
 *   - options-general.php?page=sso-settings → ssoAdmin.context = 'settings'
 *
 * @package Beplus_Smart_SEO_Optimizer
 */
( function ( $ ) {
	'use strict';

	// Guard: bail if the localised object is missing (script loaded on a page
	// it was not intended for).
	if ( typeof ssoAdmin === 'undefined' ) {
		return;
	}

	// Module-level references to update functions so the Gutenberg subscriber
	// can call them when editor state changes (without closing over stale vars).
	var doSnippetUpdate = null;
	var doAnalyze       = null;

	// =========================================================================
	// Bootstrap
	// =========================================================================

	$( function () {
		initTabs();
		initMediaFields();
		initFaqRepeater();
		initSchemaTypeToggle();
		initSettingsFeedback();

		if ( 'meta-box' === ssoAdmin.context ) {
			initSnippetPreview();
			initCharCounters();
			initAnalyzer();
			initGutenbergSubscribe();
		}
	} );

	// =========================================================================
	// Tab switching — meta box (General / Social / Schema)
	// =========================================================================

	/**
	 * Click a .nav-tab inside .sso-tabs-nav to show the matching .sso-tab-panel.
	 * The settings page uses regular URL-based tab links and is not affected here.
	 */
	function initTabs() {
		$( '.sso-tabs-nav .nav-tab' ).on( 'click', function ( e ) {
			e.preventDefault();

			var $tab   = $( this );
			var target = $tab.data( 'tab' );

			$tab.siblings().removeClass( 'nav-tab-active' );
			$tab.addClass( 'nav-tab-active' );

			$( '.sso-tab-panel' ).hide();
			$( '.sso-tab-panel[data-tab-panel="' + target + '"]' ).show();
		} );
	}

	// =========================================================================
	// Editor helpers — Classic Editor & Block Editor (Gutenberg)
	// =========================================================================

	/**
	 * Return the current post title, preferring the Block Editor data store.
	 *
	 * @return {string}
	 */
	function getPostTitle() {
		if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
			try {
				return wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '';
			} catch ( e ) { /* fall through to Classic Editor */ }
		}
		var $f = $( '#title' );
		return $f.length ? $f.val() : '';
	}

	/**
	 * Return the current post content (raw HTML/markup), preferring the Block
	 * Editor data store.
	 *
	 * @return {string}
	 */
	function getPostContent() {
		if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
			try {
				return wp.data.select( 'core/editor' ).getEditedPostContent() || '';
			} catch ( e ) { /* fall through to Classic Editor */ }
		}
		var $f = $( '#content' );
		return $f.length ? $f.val() : '';
	}

	/**
	 * Return the current post slug, preferring the Block Editor data store.
	 * Falls back to the hidden #post_name field, then to ssoAdmin.postSlug
	 * (server-side value at page load).
	 *
	 * @return {string}
	 */
	function getPostSlug() {
		if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
			try {
				var sel = wp.data.select( 'core/editor' );
				if ( sel.getEditedPostSlug ) {
					var gutenbergSlug = sel.getEditedPostSlug();
					if ( gutenbergSlug ) {
						return gutenbergSlug;
					}
				}
			} catch ( e ) { /* fall through */ }
		}
		var $postName = $( '#post_name' );
		if ( $postName.length && $postName.val() ) {
			return $postName.val();
		}
		return ssoAdmin.postSlug || '';
	}

	// =========================================================================
	// Google search snippet preview (SERP mock)
	// =========================================================================

	/**
	 * Update the .sso-snippet-preview block whenever the meta title, meta
	 * description, or slug changes — from meta box inputs or Block Editor.
	 *
	 * Exposed as doSnippetUpdate so initGutenbergSubscribe() can call it.
	 */
	function initSnippetPreview() {
		var $metaTitle = $( '#sso_meta_title' );
		var $metaDesc  = $( '#sso_meta_description' );
		var $pTitle    = $( '.sso-snippet-title' );
		var $pDesc     = $( '.sso-snippet-desc' );
		var $pSlug     = $( '.sso-snippet-slug' );

		function update() {
			// Title: meta title field → post title → site name.
			$pTitle.text(
				$metaTitle.val() || getPostTitle() || ssoAdmin.siteName || ''
			);
			// Description: meta description field only (no auto-excerpt in preview).
			$pDesc.text( $metaDesc.val() || '' );
			// Slug: live-updating from Block Editor or classic #post_name.
			if ( $pSlug.length ) {
				$pSlug.text( getPostSlug() );
			}
		}

		// Expose for Gutenberg subscriber.
		doSnippetUpdate = update;

		$metaTitle.on( 'input', update );
		$metaDesc.on( 'input', update );
		update(); // Initial render.
	}

	// =========================================================================
	// Character counters (meta title 0-60, meta description 0-160)
	// =========================================================================

	/**
	 * Attach a real-time character counter to every .sso-char-count element.
	 * Turns red (via .is-warning) once the text exceeds the recommended limit.
	 */
	function initCharCounters() {
		$( '.sso-char-count' ).each( function () {
			var $counter = $( this );
			var targetId = $counter.data( 'target' );
			var max      = parseInt( $counter.data( 'max' ), 10 ) || 0;
			var $field   = $( '#' + targetId );

			function update() {
				var len = ( $field.val() || '' ).length;
				$counter.text( len + ' / ' + max );
				$counter.toggleClass( 'is-warning', len > max );
			}

			$field.on( 'input', update );
			update(); // Initial render.
		} );
	}

	// =========================================================================
	// Focus keyword analyzer — client-side, no AJAX round-trip
	// =========================================================================

	/**
	 * Run all SEO checks for the focus keyword and render the score badge +
	 * checklist. All data is available in the DOM or Block Editor store.
	 *
	 * Checks performed:
	 *   1. Keyword in SEO title
	 *   2. Keyword in meta description
	 *   3. Keyword in URL slug
	 *   4. Keyword in any heading (h1-h6)
	 *   5. Keyword in first paragraph
	 *   6. Keyword density within healthy range (0.5 – 2.5 %)
	 *   7. SEO title length (30 – 60 chars)
	 *   8. Meta description length (120 – 160 chars)
	 *
	 * Exposed as doAnalyze so initGutenbergSubscribe() can call it.
	 */
	function initAnalyzer() {
		var config = ssoAdmin.analyzer;
		if ( ! config ) {
			return;
		}

		var $keyword = $( '#sso_focus_keyword' );
		var $title   = $( '#sso_meta_title' );
		var $desc    = $( '#sso_meta_description' );
		var $list    = $( '#sso-analyzer-list' );
		var $badge   = $( '#sso-score-badge' );

		/**
		 * Escape a string for use inside a RegExp literal.
		 *
		 * @param {string} str
		 * @return {string}
		 */
		function escapeRegExp( str ) {
			return str.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		}

		/**
		 * Case-insensitive check whether haystack contains keyword.
		 *
		 * @param {string} haystack
		 * @param {string} keyword
		 * @return {boolean}
		 */
		function containsKeyword( haystack, keyword ) {
			if ( ! haystack ) {
				return false;
			}
			return new RegExp( escapeRegExp( keyword ), 'i' ).test( haystack );
		}

		/**
		 * Strip HTML tags from a string using the browser's parser.
		 * Never executes scripts because innerHTML is set on a detached node.
		 *
		 * @param {string} html
		 * @return {string}
		 */
		function stripHtml( html ) {
			var div       = document.createElement( 'div' );
			div.innerHTML = html || '';
			return div.textContent || div.innerText || '';
		}

		/**
		 * Run all checks and refresh the UI.
		 */
		function analyze() {
			var keyword = ( $keyword.val() || '' ).trim();

			// No keyword yet — show a placeholder message, clear the badge.
			if ( ! keyword ) {
				$list.empty().append(
					$( '<li>' ).text( config.labels.keywordMissing )
				);
				$badge
					.text( '' )
					.removeClass( 'sso-score-red sso-score-yellow sso-score-green' );
				return;
			}

			// Gather content from the active editor.
			var title       = $title.val()  || getPostTitle()   || '';
			var desc        = $desc.val()   || '';
			var slug        = getPostSlug() || '';
			var rawContent  = getPostContent() || '';
			var textContent = stripHtml( rawContent );

			// Extract headings and first paragraph text from raw HTML.
			var headingMatches = rawContent.match( /<h[1-6][^>]*>([\s\S]*?)<\/h[1-6]>/gi ) || [];
			var headingText    = headingMatches.join( ' ' );

			var paraMatch     = rawContent.match( /<p[^>]*>([\s\S]*?)<\/p>/i );
			var firstParaText = paraMatch ? stripHtml( paraMatch[ 0 ] ) : '';

			// Keyword density.
			var words       = textContent.trim().length ? textContent.trim().split( /\s+/ ) : [];
			var keywordRe   = new RegExp( escapeRegExp( keyword ), 'gi' );
			var occurrences = ( textContent.match( keywordRe ) || [] ).length;
			var density     = words.length > 0 ? ( occurrences / words.length ) * 100 : 0;

			// Define all checks as { pass, good, bad } objects.
			var checks = [
				{
					pass: containsKeyword( title, keyword ),
					good: config.labels.keywordInTitle,
					bad:  config.labels.keywordNotInTitle,
				},
				{
					pass: containsKeyword( desc, keyword ),
					good: config.labels.keywordInDesc,
					bad:  config.labels.keywordNotInDesc,
				},
				{
					pass: containsKeyword( slug, keyword ),
					good: config.labels.keywordInSlug,
					bad:  config.labels.keywordNotInSlug,
				},
				{
					pass: containsKeyword( headingText, keyword ),
					good: config.labels.keywordInHeading,
					bad:  config.labels.keywordNotInHeading,
				},
				{
					pass: containsKeyword( firstParaText, keyword ),
					good: config.labels.keywordInFirstPara,
					bad:  config.labels.keywordNotInFirstPara,
				},
				{
					pass: density >= config.densityMin && density <= config.densityMax,
					good: config.labels.densityGood + ' (' + density.toFixed( 2 ) + '%)',
					bad:  config.labels.densityBad  + ' (' + density.toFixed( 2 ) + '%)',
				},
				{
					pass: title.length >= config.titleMin && title.length <= config.titleMax,
					good: config.labels.titleLengthGood,
					bad:  config.labels.titleLengthBad,
				},
				{
					pass: desc.length >= config.descMin && desc.length <= config.descMax,
					good: config.labels.descLengthGood,
					bad:  config.labels.descLengthBad,
				},
			];

			// Build the result list using DOM methods (not innerHTML string
			// concatenation) so translated label strings can never inject markup.
			var passCount = 0;
			$list.empty();

			checks.forEach( function ( check ) {
				if ( check.pass ) {
					passCount++;
				}

				var $li = $( '<li>' ).addClass(
					check.pass ? 'sso-check-good' : 'sso-check-warning'
				);
				$li.append( $( '<span>' ).addClass( 'sso-icon' ) );
				// createTextNode ensures label text is never interpreted as HTML.
				$li.append( document.createTextNode( check.pass ? check.good : check.bad ) );
				$list.append( $li );
			} );

			// Compute and render the score badge.
			var score      = Math.round( ( passCount / checks.length ) * 100 );
			var scoreClass, scoreLabel;

			if ( score >= 80 ) {
				scoreClass = 'sso-score-green';
				scoreLabel = ssoAdmin.i18n.scoreGood;
			} else if ( score >= 50 ) {
				scoreClass = 'sso-score-yellow';
				scoreLabel = ssoAdmin.i18n.scoreOk;
			} else {
				scoreClass = 'sso-score-red';
				scoreLabel = ssoAdmin.i18n.scorePoor;
			}

			$badge
				.text( scoreLabel + ' (' + score + '/100)' )
				.removeClass( 'sso-score-red sso-score-yellow sso-score-green' )
				.addClass( scoreClass );
		}

		// Expose for Gutenberg subscriber.
		doAnalyze = analyze;

		$keyword.on( 'input', analyze );
		$title.on( 'input', analyze );
		$desc.on( 'input', analyze );
		analyze(); // Initial render.
	}

	// =========================================================================
	// WP media uploader (wp.media)
	// =========================================================================

	/**
	 * Wire up every .sso-media-field on the page with the WordPress media
	 * library frame. Works for OG image, default OG image, and schema logo.
	 *
	 * Uses event delegation so fields added after page load (e.g. future
	 * dynamic panels) are handled automatically.
	 */
	function initMediaFields() {
		if ( ! window.wp || ! wp.media ) {
			return;
		}

		// "Choose Image" button — open the media library.
		$( document ).on( 'click', '.sso-media-select', function ( e ) {
			e.preventDefault();

			var $field     = $( this ).closest( '.sso-media-field' );
			var $input     = $field.find( '.sso-media-input' );
			var $preview   = $field.find( '.sso-media-preview' );
			var $removeBtn = $field.find( '.sso-media-remove' );

			var frame = wp.media( {
				title:    ssoAdmin.i18n.chooseImage,
				button:   { text: ssoAdmin.i18n.useImage },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				// Prefer medium thumbnail when available; fall back to full URL.
				var imageUrl = ( attachment.sizes && attachment.sizes.medium )
					? attachment.sizes.medium.url
					: attachment.url;

				$input.val( attachment.id );
				// Build the preview <img> via jQuery to avoid raw HTML injection.
				$preview.empty().append(
					$( '<img>' ).attr( { src: imageUrl, alt: '' } )
				);
				$removeBtn.show();
			} );

			frame.open();
		} );

		// "Remove" button — clear the stored ID and preview.
		$( document ).on( 'click', '.sso-media-remove', function ( e ) {
			e.preventDefault();
			var $field = $( this ).closest( '.sso-media-field' );
			$field.find( '.sso-media-input' ).val( '' );
			$field.find( '.sso-media-preview' ).empty();
			$( this ).hide();
		} );
	}

	// =========================================================================
	// FAQ question/answer repeater (FAQPage schema)
	// =========================================================================

	/**
	 * Add/remove question-answer rows in the Schema → FAQPage repeater table.
	 *
	 * New rows are built with jQuery DOM methods instead of HTML string
	 * concatenation so localised placeholder text cannot inject markup even
	 * if a translation contains angle brackets or quotes.
	 */
	function initFaqRepeater() {
		$( '#sso-faq-add' ).on( 'click', function ( e ) {
			e.preventDefault();

			var qInput = $( '<input>' ).attr( {
				type:        'text',
				name:        'sso_schema_faq_question[]',
				'class':     'widefat',
				placeholder: ( ssoAdmin.i18n && ssoAdmin.i18n.question ) || 'Question',
			} );
			var aInput = $( '<input>' ).attr( {
				type:        'text',
				name:        'sso_schema_faq_answer[]',
				'class':     'widefat',
				placeholder: ( ssoAdmin.i18n && ssoAdmin.i18n.answer ) || 'Answer',
			} );
			// Use × (U+00D7 MULTIPLICATION SIGN) as the remove icon.
			var removeBtn = $( '<button>' )
				.attr( { type: 'button', 'class': 'button sso-faq-remove' } )
				.text( '×' );

			var $row = $( '<tr>' )
				.append( $( '<td>' ).append( qInput ) )
				.append( $( '<td>' ).append( aInput ) )
				.append( $( '<td>' ).append( removeBtn ) );

			$( '#sso-faq-repeater tbody' ).append( $row );
		} );

		// Remove button — delegate so it works on dynamically added rows.
		$( document ).on( 'click', '.sso-faq-remove', function ( e ) {
			e.preventDefault();
			$( this ).closest( 'tr' ).remove();
		} );
	}

	// =========================================================================
	// Schema-type conditional field groups
	// =========================================================================

	/**
	 * Show the .sso-schema-fields panel that matches the chosen schema type.
	 *
	 * When the type is Auto (empty string) we show the 'article' panel because
	 * it contains headline and author overrides that are useful regardless of
	 * the auto-resolved type, and the PHP renders it visible by default.
	 */
	function initSchemaTypeToggle() {
		var $select = $( '#sso_schema_type' );
		// Not present on the settings page — bail early.
		if ( ! $select.length ) {
			return;
		}

		function update() {
			var value  = $select.val();
			// Fall back to 'article' for Auto/empty so headline & author are visible.
			var target = value || 'article';

			$( '.sso-schema-fields' ).hide();
			$( '.sso-schema-fields[data-schema-fields="' + target + '"]' ).show();
		}

		$select.on( 'change', update );
		update(); // Initial render.
	}

	// =========================================================================
	// Settings page — save feedback
	// =========================================================================

	/**
	 * On the Settings page, disable the submit button while the form is posting
	 * to prevent accidental double-submits. WordPress shows its own success
	 * notice after the Settings API redirect, so no custom banner is needed.
	 */
	function initSettingsFeedback() {
		if ( 'settings' !== ssoAdmin.context ) {
			return;
		}

		$( '.sso-settings-form' ).on( 'submit', function () {
			var $btn  = $( this ).find( '[type="submit"]' );
			var label = $btn.val();
			// Append ellipsis (U+2026) to signal activity.
			$btn.prop( 'disabled', true ).val( label + '…' );
		} );
	}

	// =========================================================================
	// Gutenberg (Block Editor) real-time subscription
	// =========================================================================

	/**
	 * Subscribe to the Block Editor data store so that changes to the post
	 * title, content, or slug in the editor automatically re-trigger the
	 * snippet preview and focus keyword analysis — without the user having to
	 * type in the meta box fields first.
	 *
	 * Calls are debounced via requestAnimationFrame so the subscriber never
	 * fires faster than the browser's repaint cycle, keeping UI snappy even
	 * on large documents.
	 */
	function initGutenbergSubscribe() {
		if ( ! window.wp || ! wp.data || ! wp.data.subscribe ) {
			return;
		}

		var editorSelect = wp.data.select( 'core/editor' );
		if ( ! editorSelect ) {
			return;
		}

		// Snapshot the editor state at the moment this runs.
		var prev = {
			title:   editorSelect.getEditedPostAttribute( 'title' ) || '',
			content: editorSelect.getEditedPostContent()            || '',
			slug:    editorSelect.getEditedPostSlug
				? ( editorSelect.getEditedPostSlug() || '' )
				: '',
		};

		// Flag to avoid scheduling more than one rAF at a time.
		var rafPending = false;

		wp.data.subscribe( function () {
			if ( rafPending ) {
				return;
			}
			rafPending = true;

			window.requestAnimationFrame( function () {
				rafPending = false;

				var store = wp.data.select( 'core/editor' );
				if ( ! store ) {
					return;
				}

				var cur = {
					title:   store.getEditedPostAttribute( 'title' ) || '',
					content: store.getEditedPostContent()            || '',
					slug:    store.getEditedPostSlug
						? ( store.getEditedPostSlug() || '' )
						: '',
				};

				var changed = (
					cur.title   !== prev.title   ||
					cur.content !== prev.content ||
					cur.slug    !== prev.slug
				);

				if ( changed ) {
					prev = cur;
					if ( typeof doSnippetUpdate === 'function' ) {
						doSnippetUpdate();
					}
					if ( typeof doAnalyze === 'function' ) {
						doAnalyze();
					}
				}
			} );
		} );
	}

} )( jQuery );
