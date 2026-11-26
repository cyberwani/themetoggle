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

	// Show a role's theme row only while the role is ticked as allowed. Rows are
	// hidden, not removed, so their checkboxes are still submitted and a hidden
	// role keeps its theme assignments.
	function syncRoleRows() {
		const table = document.querySelector( '[data-tt-role-table]' );
		const empty = document.querySelector( '[data-tt-no-roles]' );
		let anyAllowed = false;

		document
			.querySelectorAll( 'input[data-tt-role-toggle]' )
			.forEach( function ( toggle ) {
				const allowed = toggle.checked;

				anyAllowed = anyAllowed || allowed;

				document
					.querySelectorAll( 'tr[data-tt-role]' )
					.forEach( function ( row ) {
						if (
							row.getAttribute( 'data-tt-role' ) === toggle.value
						) {
							row.hidden = ! allowed;
						}
					} );
			} );

		if ( table ) {
			table.hidden = ! anyAllowed;
		}

		if ( empty ) {
			empty.hidden = anyAllowed;
		}
	}

	document.addEventListener( 'change', function ( event ) {
		if (
			event.target.matches &&
			event.target.matches( 'input[data-tt-role-toggle]' )
		) {
			syncRoleRows();
		}
	} );

	// Browsers may restore checkbox states on back/refresh: sync once on load.
	syncRoleRows();

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
