/**
 * ThemeToggle settings page behaviour. Loaded only on Appearance → ThemeToggle.
 */
( function () {
	'use strict';

	const i18n = window.themetoggleAdmin || {};

	// "All / None" helpers per role row.
	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '[data-tt-select]' );

		if ( ! button ) {
			return;
		}

		const row = button.closest( 'tr' );
		const state = 'all' === button.getAttribute( 'data-tt-select' );

		event.preventDefault();

		if ( row ) {
			row.querySelectorAll(
				'.tt-theme-grid input[type="checkbox"]'
			).forEach( function ( input ) {
				input.checked = state;
			} );
		}
	} );

	// Extra confirmation for the destructive reset.
	document.addEventListener( 'submit', function ( event ) {
		if (
			event.target.classList &&
			event.target.classList.contains( 'tt-reset-all' ) &&
			i18n.confirmResetAll
		) {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( i18n.confirmResetAll ) ) {
				event.preventDefault();
			}
		}
	} );

	// Colour pickers (only enqueued on the Floating Button tab).
	if ( window.jQuery && window.jQuery.fn && window.jQuery.fn.wpColorPicker ) {
		window.jQuery( function ( $ ) {
			$( '.tt-color' ).wpColorPicker();
		} );
	}
} )();
