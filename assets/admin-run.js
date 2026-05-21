( function () {
	'use strict';

	var cfg = window.Baton || {};
	var strings = cfg.strings || {};

	function parseJson( text, fallback ) {
		if ( ! text || ! text.trim() ) {
			return fallback;
		}
		try {
			return JSON.parse( text );
		} catch ( e ) {
			return null;
		}
	}

	function stringifyJson( value ) {
		return JSON.stringify( value, null, 2 );
	}

	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	function syncDefinition() {
		if ( typeof window.BatonEditorSync === 'function' ) {
			window.BatonEditorSync();
		}
	}

	var RunPanel = {
		init: function () {
			var btn = document.getElementById( 'aw-run-workflow' );
			if ( ! btn ) {
				return;
			}

			btn.addEventListener( 'click', this.run.bind( this ) );

			if ( window.location.hash === '#aw-run-panel' ) {
				var panel = document.getElementById( 'aw-run-panel' );
				if ( panel ) {
					panel.scrollIntoView( { behavior: 'smooth' } );
				}
			}
		},

		run: function () {
			var btn = document.getElementById( 'aw-run-workflow' );
			var results = document.getElementById( 'aw-run-results' );
			var initialInput = document.getElementById( 'aw-run-initial-input' );

			if ( ! btn || ! results ) {
				return;
			}

			syncDefinition();

			var hidden = document.getElementById( 'workflow_definition' );
			var definition = parseJson( hidden ? hidden.value : '', {} );
			if ( ! definition || ! definition.steps || ! definition.steps.length ) {
				window.alert(
					strings.noSteps || 'Add at least one step before running.'
				);
				return;
			}

			var initial = parseJson(
				initialInput ? initialInput.value : '',
				{}
			);
			if ( initialInput && initialInput.value.trim() && initial === null ) {
				window.alert( strings.invalidJson || 'Invalid JSON.' );
				return;
			}

			btn.disabled = true;
			btn.textContent = strings.running || 'Running…';
			results.hidden = false;
			results.innerHTML =
				'<p class="aw-run-status">' +
				escapeHtml( strings.running || 'Running…' ) +
				'</p>';

			var body = new FormData();
			body.append( 'action', 'baton_run' );
			body.append( 'nonce', cfg.nonce );
			body.append( 'workflow_id', btn.getAttribute( 'data-workflow-id' ) );
			body.append(
				'initial_input',
				initialInput ? initialInput.value : '{}'
			);

			fetch( cfg.ajaxUrl, {
				method: 'POST',
				body: body,
				credentials: 'same-origin',
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					btn.disabled = false;
					btn.textContent =
						btn.getAttribute( 'data-label' ) || 'Run Workflow';
					RunPanel.renderResults( data );
				} )
				.catch( function ( err ) {
					btn.disabled = false;
					btn.textContent =
						btn.getAttribute( 'data-label' ) || 'Run Workflow';
					results.innerHTML =
						'<div class="notice notice-error"><p>' +
						escapeHtml( String( err ) ) +
						'</p></div>';
				} );
		},

		renderResults: function ( data ) {
			var results = document.getElementById( 'aw-run-results' );
			if ( ! results ) {
				return;
			}

			var report = data.data || data;
			var html = '';

			if ( data.success ) {
				html +=
					'<div class="notice notice-success"><p>' +
					escapeHtml(
						strings.runSuccess ||
							'Workflow completed successfully.'
					) +
					'</p></div>';
			} else {
				html +=
					'<div class="notice notice-error"><p>' +
					escapeHtml(
						report.error || strings.runFailed || 'Workflow failed.'
					) +
					'</p></div>';
			}

			if ( report.steps && report.steps.length ) {
				html += '<div class="aw-run-steps">';
				report.steps.forEach( function ( step, i ) {
					var statusClass = step.success
						? 'aw-step-ok'
						: 'aw-step-fail';
					html +=
						'<details class="aw-run-step ' +
						statusClass +
						'" open>';
					html +=
						'<summary><strong>Step ' +
						( i + 1 ) +
						':</strong> ' +
						escapeHtml( step.ability || '' ) +
						( step.success ? ' ✓' : ' ✗' ) +
						'</summary>';
					if ( step.warnings && step.warnings.length ) {
						html +=
							'<p class="aw-warning">' +
							escapeHtml( step.warnings.join( ' ' ) ) +
							'</p>';
					}
					html +=
						'<h4>Input</h4><pre class="aw-result-pre">' +
						escapeHtml( stringifyJson( step.input ) ) +
						'</pre>';
					if ( step.success ) {
						html +=
							'<h4>Output</h4><pre class="aw-result-pre">' +
							escapeHtml( stringifyJson( step.output ) ) +
							'</pre>';
					} else if ( step.error ) {
						html +=
							'<h4>Error</h4><p class="aw-error">' +
							escapeHtml( step.error ) +
							'</p>';
					}
					html += '</details>';
				} );
				html += '</div>';
			}

			results.innerHTML = html;
		},
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		RunPanel.init();

		var copyBtn = document.getElementById( 'baton-copy-ability-slug' );
		var slugEl = document.getElementById( 'baton-ability-slug' );
		if ( copyBtn && slugEl ) {
			copyBtn.addEventListener( 'click', function () {
				var text = slugEl.textContent || '';
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text );
				}
			} );
		}
	} );
} )();
