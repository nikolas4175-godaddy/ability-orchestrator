( function () {
	'use strict';

	const cfg = window.Baton || {};
	const strings = cfg.strings || {};

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
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	function syncDefinition() {
		if ( typeof window.BatonEditorSync === 'function' ) {
			window.BatonEditorSync();
		}
	}

	var RunPanel = {
		init() {
			const btn = document.getElementById( 'aw-run-workflow' );
			if ( ! btn ) {
				return;
			}

			btn.addEventListener( 'click', this.run.bind( this ) );

			if ( window.location.hash === '#aw-run-panel' ) {
				const panel = document.getElementById( 'aw-run-panel' );
				if ( panel ) {
					panel.scrollIntoView( { behavior: 'smooth' } );
				}
			}
		},

		run() {
			const btn = document.getElementById( 'aw-run-workflow' );
			const results = document.getElementById( 'aw-run-results' );
			const initialInput = document.getElementById(
				'aw-run-initial-input'
			);

			if ( ! btn || ! results ) {
				return;
			}

			syncDefinition();

			const hidden = document.getElementById( 'workflow_definition' );
			const definition = parseJson( hidden ? hidden.value : '', {} );
			if (
				! definition ||
				! definition.steps ||
				! definition.steps.length
			) {
				window.alert(
					strings.noSteps || 'Add at least one step before running.'
				);
				return;
			}

			const initial = parseJson(
				initialInput ? initialInput.value : '',
				{}
			);
			if (
				initialInput &&
				initialInput.value.trim() &&
				initial === null
			) {
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

			const body = new FormData();
			body.append( 'action', 'baton_run' );
			body.append( 'nonce', cfg.nonce );
			body.append(
				'workflow_id',
				btn.getAttribute( 'data-workflow-id' )
			);
			body.append(
				'initial_input',
				initialInput ? initialInput.value : '{}'
			);

			fetch( cfg.ajaxUrl, {
				method: 'POST',
				body,
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

		renderResults( data ) {
			const results = document.getElementById( 'aw-run-results' );
			if ( ! results ) {
				return;
			}

			const report = data.data || data;
			let html = '';

			if ( data.success ) {
				html +=
					'<div class="notice notice-success"><p>' +
					escapeHtml(
						strings.runSuccess || 'Workflow completed successfully.'
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
					const statusClass = step.success
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

		const copyBtn = document.getElementById( 'baton-copy-ability-slug' );
		const slugEl = document.getElementById( 'baton-ability-slug' );
		if ( copyBtn && slugEl ) {
			copyBtn.addEventListener( 'click', function () {
				const text = slugEl.textContent || '';
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( text );
				}
			} );
		}
	} );
} )();
