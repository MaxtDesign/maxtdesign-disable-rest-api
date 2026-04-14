/**
 * MaxtDesign Disable REST API — Admin Settings JS
 *
 * Loaded ONLY on the plugin's settings page. Never globally.
 * Vanilla JS only — no frameworks, no jQuery dependency.
 *
 * @package MaxtDesign\DisableRestApi
 * @since   1.0.0
 */

( function () {
	'use strict';

	/**
	 * Initializes all interactive settings behaviors.
	 */
	function init() {
		initCollapsibleSections();
		initNamespaceToggles();
		initNamespaceCheckboxes();
		initSelectAllButtons();
		initRoleRestrictionToggles();
		initResetConfirmation();
	}

	/**
	 * Handles collapsible section toggles (Per-Role Controls).
	 */
	function initCollapsibleSections() {
		document.querySelectorAll( '.mdra-toggle-section' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var targetId = this.getAttribute( 'data-target' );
				var target = document.getElementById( targetId );

				if ( ! target ) {
					return;
				}

				var expanded = this.getAttribute( 'aria-expanded' ) === 'true';
				this.setAttribute( 'aria-expanded', String( ! expanded ) );
				target.hidden = expanded;
			} );
		} );
	}

	/**
	 * Handles namespace route expand/collapse toggles.
	 */
	function initNamespaceToggles() {
		document.querySelectorAll( '.mdra-toggle-namespace' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var targetId = this.getAttribute( 'data-target' );
				var target = document.getElementById( targetId );

				if ( ! target ) {
					return;
				}

				var expanded = this.getAttribute( 'aria-expanded' ) === 'true';
				this.setAttribute( 'aria-expanded', String( ! expanded ) );
				target.hidden = expanded;
			} );
		} );
	}

	/**
	 * When a namespace checkbox is checked, disable individual route checkboxes
	 * (since the entire namespace is whitelisted).
	 */
	function initNamespaceCheckboxes() {
		document.querySelectorAll( '.mdra-namespace-checkbox' ).forEach( function ( checkbox ) {
			checkbox.addEventListener( 'change', function () {
				var namespaceId = this.getAttribute( 'data-namespace' );
				var routeCheckboxes = document.querySelectorAll(
					'input[data-parent-namespace="' + namespaceId + '"]'
				);

				routeCheckboxes.forEach( function ( routeCb ) {
					routeCb.disabled = checkbox.checked;
					if ( checkbox.checked ) {
						routeCb.checked = true;
					}
				} );
			} );
		} );
	}

	/**
	 * Handles Select All / Deselect All buttons per namespace.
	 */
	function initSelectAllButtons() {
		document.querySelectorAll( '.mdra-select-all' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var namespaceId = this.getAttribute( 'data-namespace' );
				setAllRoutes( namespaceId, true );
			} );
		} );

		document.querySelectorAll( '.mdra-deselect-all' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var namespaceId = this.getAttribute( 'data-namespace' );
				setAllRoutes( namespaceId, false );
			} );
		} );
	}

	/**
	 * Sets all route checkboxes in a namespace to checked or unchecked.
	 *
	 * @param {string}  namespaceId The namespace container ID.
	 * @param {boolean} checked     Whether to check or uncheck.
	 */
	function setAllRoutes( namespaceId, checked ) {
		var nsCheckbox = document.querySelector(
			'.mdra-namespace-checkbox[data-namespace="' + namespaceId + '"]'
		);

		if ( nsCheckbox ) {
			nsCheckbox.checked = checked;
			nsCheckbox.dispatchEvent( new Event( 'change' ) );
		}

		var routeCheckboxes = document.querySelectorAll(
			'input[data-parent-namespace="' + namespaceId + '"]'
		);

		routeCheckboxes.forEach( function ( cb ) {
			cb.checked = checked;
			cb.disabled = checked && nsCheckbox && nsCheckbox.checked;
		} );
	}

	/**
	 * Shows/hides per-role endpoint whitelist when restriction toggle changes.
	 */
	function initRoleRestrictionToggles() {
		document.querySelectorAll( '.mdra-role-restrict-toggle' ).forEach( function ( checkbox ) {
			checkbox.addEventListener( 'change', function () {
				var role = this.getAttribute( 'data-role' );
				var endpointsDiv = document.getElementById( 'mdra-role-endpoints-' + role );

				if ( endpointsDiv ) {
					endpointsDiv.hidden = ! this.checked;
				}
			} );
		} );
	}

	/**
	 * Adds a confirmation dialog to the Reset to Defaults button.
	 */
	function initResetConfirmation() {
		var resetForm = document.getElementById( 'mdra-reset-form' );

		if ( ! resetForm ) {
			return;
		}

		resetForm.addEventListener( 'submit', function ( event ) {
			/* eslint-disable no-alert */
			if ( ! window.confirm( 'Are you sure you want to reset all settings to defaults? This cannot be undone.' ) ) {
				event.preventDefault();
			}
			/* eslint-enable no-alert */
		} );
	}

	// Initialize when DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
