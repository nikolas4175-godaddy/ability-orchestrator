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

	function formatStepInput( value ) {
		if ( null === value || '' === value ) {
			return '';
		}
		if ( 'number' === typeof value || 'boolean' === typeof value ) {
			return String( value );
		}
		return stringifyJson( value );
	}

	function parseStepInput( text, ability ) {
		if ( ! text || ! text.trim() ) {
			if ( ability && ability.input_is_scalar ) {
				return '';
			}
			return {};
		}

		var parsed = parseJson( text, null );
		if ( null === parsed ) {
			return ability && ability.input_is_scalar ? text.trim() : {};
		}

		if ( ability && ability.input_is_scalar ) {
			if ( 'number' === typeof parsed || 'boolean' === typeof parsed ) {
				return parsed;
			}
			if ( 'string' === typeof parsed && '' !== parsed ) {
				return parsed;
			}
		}

		return parsed || {};
	}

	function stepUsesScalarInput( ability ) {
		return ability && ability.input_is_scalar;
	}

	function getAbilityBySlug( abilities, slug ) {
		for ( var i = 0; i < abilities.length; i++ ) {
			if ( abilities[ i ].slug === slug ) {
				return abilities[ i ];
			}
		}
		return null;
	}

	function buildDatalistOptions( fields ) {
		if ( ! fields || ! fields.length ) {
			return '';
		}
		return fields
			.map( function ( field ) {
				return '<option value="' + escapeAttr( field ) + '"></option>';
			} )
			.join( '' );
	}

	function buildAbilityOptions( abilities, selected ) {
		var html = '<option value="">' + escapeHtml( strings.selectAbility || 'Select an ability…' ) + '</option>';
		var grouped = {};

		abilities.forEach( function ( ability ) {
			var cat = ability.category || 'Other';
			if ( ! grouped[ cat ] ) {
				grouped[ cat ] = [];
			}
			grouped[ cat ].push( ability );
		} );

		Object.keys( grouped )
			.sort()
			.forEach( function ( category ) {
				html += '<optgroup label="' + escapeHtml( category ) + '">';
				grouped[ category ].forEach( function ( ability ) {
					var sel = ability.slug === selected ? ' selected' : '';
					html +=
						'<option value="' +
						escapeAttr( ability.slug ) +
						'"' +
						sel +
						'>' +
						escapeHtml( ability.label ) +
						' (' +
						escapeHtml( ability.slug ) +
						')</option>';
				} );
				html += '</optgroup>';
			} );

		return html;
	}

	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}

	function escapeAttr( text ) {
		return String( text )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;' );
	}

	var StepsEditor = {
		container: null,
		abilities: [],
		steps: [],

		init: function () {
			this.container = document.getElementById( 'aw-steps-container' );
			if ( ! this.container ) {
				return;
			}

			var abilitiesEl = document.getElementById( 'aw-abilities-data' );
			var definitionEl = document.getElementById( 'aw-definition-data' );

			this.abilities =
				parseJson( abilitiesEl ? abilitiesEl.textContent : '', [] ) || [];
			var definition =
				parseJson( definitionEl ? definitionEl.textContent : '', {} ) || {};
			this.steps = definition.steps || [];

			if ( ! this.steps.length ) {
				this.steps.push( this.defaultStep() );
			}

			this.render();
			this.bindGlobal();
		},

		defaultStep: function () {
			return {
				ability: '',
				input: {},
				use_previous_output: false,
				input_mappings: [],
			};
		},

		getPreviousAbility: function ( stepIndex ) {
			if ( stepIndex <= 0 ) {
				return null;
			}
			var prev = this.steps[ stepIndex - 1 ];
			if ( ! prev || ! prev.ability ) {
				return null;
			}
			return getAbilityBySlug( this.abilities, prev.ability );
		},

		getCurrentAbilityFromEl: function ( stepEl ) {
			var select = stepEl.querySelector( '.aw-step-ability' );
			if ( ! select || ! select.value ) {
				return null;
			}
			return getAbilityBySlug( this.abilities, select.value );
		},

		bindGlobal: function () {
			var self = this;
			var addBtn = document.getElementById( 'aw-add-step' );
			var form = document.getElementById( 'baton-form' );

			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () {
					self.steps.push( self.defaultStep() );
					self.render();
				} );
			}

			if ( form ) {
				form.addEventListener( 'submit', function () {
					self.syncHiddenField();
				} );
			}
		},

		syncHiddenField: function () {
			var field = document.getElementById( 'workflow_definition' );
			if ( ! field ) {
				return;
			}

			this.readStepsFromDom();

			var existing = parseJson( field.value, {} ) || {};
			existing.steps = this.steps;
			field.value = stringifyJson( existing );
		},

		readMappingsFromEl: function ( stepEl ) {
			var mappings = [];
			var isScalar = stepEl.getAttribute( 'data-scalar-input' ) === '1';

			stepEl.querySelectorAll( '.aw-mapping-row' ).forEach( function ( row ) {
				var source = row.querySelector( '.aw-mapping-source' );
				var path = row.querySelector( '.aw-mapping-path' );
				var target = row.querySelector( '.aw-mapping-target' );

				var pathVal = path ? path.value.trim() : '';
				var targetVal = target ? target.value.trim() : '';

				if ( ! pathVal ) {
					return;
				}

				if ( ! isScalar && ! targetVal ) {
					return;
				}

				mappings.push( {
					source: source ? source.value : 'previous',
					path: pathVal,
					target: isScalar ? '' : targetVal,
				} );
			} );

			return mappings;
		},

		readStepsFromDom: function () {
			var self = this;
			var steps = [];

			this.container.querySelectorAll( '.aw-step' ).forEach( function ( stepEl ) {
				var abilitySelect = stepEl.querySelector( '.aw-step-ability' );
				var input = stepEl.querySelector( '.aw-step-input' );
				var usePrev = stepEl.querySelector( '.aw-step-use-previous' );
				var abilityMeta = abilitySelect
					? getAbilityBySlug( self.abilities, abilitySelect.value )
					: null;

				steps.push( {
					ability: abilitySelect ? abilitySelect.value : '',
					input: parseStepInput( input ? input.value : '', abilityMeta ),
					use_previous_output: usePrev ? usePrev.checked : false,
					input_mappings: self.readMappingsFromEl( stepEl ),
				} );
			} );

			this.steps = steps.length ? steps : [ this.defaultStep() ];
		},

		render: function () {
			var self = this;
			this.container.innerHTML = '';

			this.steps.forEach( function ( step, index ) {
				self.container.appendChild( self.renderStep( step, index ) );
			} );
		},

		buildMappingsSection: function ( step, stepIndex ) {
			var mappings = step.input_mappings || [];
			var prevAbility = this.getPreviousAbility( stepIndex );
			var currentAbility = getAbilityBySlug( this.abilities, step.ability );
			var isScalar = stepUsesScalarInput( currentAbility );

			var pathListId = 'aw-path-list-' + stepIndex;
			var targetListId = 'aw-target-list-' + stepIndex;

			var pathFields = prevAbility ? prevAbility.output_fields || [] : [];
			var targetFields = currentAbility ? currentAbility.input_fields || [] : [];

			var rowsHtml = '';
			if ( mappings.length ) {
				mappings.forEach( function ( mapping, rowIndex ) {
					rowsHtml += isScalar
						? StepsEditor.renderScalarMappingRow( mapping, stepIndex, pathListId )
						: StepsEditor.renderMappingRow(
								mapping,
								stepIndex,
								rowIndex,
								pathListId,
								targetListId
						  );
				} );
			}

			var showMappings = stepIndex > 0;
			if (
				! showMappings &&
				currentAbility &&
				( isScalar || ( currentAbility.input_fields && currentAbility.input_fields.length ) )
			) {
				showMappings = true;
			}

			if ( ! showMappings ) {
				return '';
			}

			var exampleHint = '';
			if ( isScalar ) {
				exampleHint =
					'<p class="description aw-mapping-example">' +
					escapeHtml(
						'Example: path "0.plan_id" passes the first membership plan ID (142) as the whole input.'
					) +
					'</p>';
			} else if (
				stepIndex > 0 &&
				prevAbility &&
				prevAbility.output_fields &&
				prevAbility.output_fields.indexOf( 'id' ) !== -1 &&
				currentAbility &&
				currentAbility.input_fields &&
				currentAbility.input_fields.indexOf( 'user_id' ) !== -1
			) {
				exampleHint =
					'<p class="description aw-mapping-example">' +
					escapeHtml(
						strings.mappingExample ||
							'Example: map path "id" to target "user_id" when the previous step returns a user object.'
					) +
					'</p>';
			}

			if ( isScalar ) {
				return (
					'<div class="aw-mappings-section aw-mappings-section--scalar">' +
					'<p><strong>' +
					escapeHtml( strings.scalarMappings || 'Scalar input mapping' ) +
					'</strong></p>' +
					'<p class="description">' +
					escapeHtml(
						strings.scalarMappingsHelp ||
							'Map a source path to pass that value as the entire input.'
					) +
					'</p>' +
					exampleHint +
					'<datalist id="' +
					escapeAttr( pathListId ) +
					'">' +
					buildDatalistOptions( pathFields ) +
					'</datalist>' +
					'<table class="aw-mappings-table widefat"><thead><tr>' +
					'<th>' +
					escapeHtml( strings.sourceColumn || 'Source' ) +
					'</th><th>' +
					escapeHtml( strings.sourcePath || 'Source path' ) +
					'</th><th></th></tr></thead>' +
					'<tbody class="aw-mappings-rows">' +
					rowsHtml +
					'</tbody></table>' +
					'<p><button type="button" class="button button-small aw-add-mapping" data-step-index="' +
					stepIndex +
					'">' +
					escapeHtml( strings.addMapping || 'Add mapping' ) +
					'</button></p>' +
					'</div>'
				);
			}

			return (
				'<div class="aw-mappings-section">' +
				'<p><strong>' +
				escapeHtml( strings.fieldMappings || 'Field mappings' ) +
				'</strong></p>' +
				'<p class="description">' +
				escapeHtml(
					strings.fieldMappingsHelp ||
						'Map values from the previous step or workflow input into input fields.'
				) +
				'</p>' +
				exampleHint +
				'<datalist id="' +
				escapeAttr( pathListId ) +
				'">' +
				buildDatalistOptions( pathFields ) +
				'</datalist>' +
				'<datalist id="' +
				escapeAttr( targetListId ) +
				'">' +
				buildDatalistOptions( targetFields ) +
				'</datalist>' +
				'<table class="aw-mappings-table widefat"><thead><tr>' +
				'<th>' +
				escapeHtml( strings.sourceColumn || 'Source' ) +
				'</th><th>' +
				escapeHtml( strings.sourcePath || 'Source path' ) +
				'</th><th>' +
				escapeHtml( strings.targetField || 'Target field' ) +
				'</th><th></th></tr></thead>' +
				'<tbody class="aw-mappings-rows">' +
				rowsHtml +
				'</tbody></table>' +
				'<p><button type="button" class="button button-small aw-add-mapping" data-step-index="' +
				stepIndex +
				'">' +
				escapeHtml( strings.addMapping || 'Add mapping' ) +
				'</button></p>' +
				'</div>'
			);
		},

		renderScalarMappingRow: function ( mapping, stepIndex, pathListId ) {
			var source = mapping.source || ( stepIndex === 0 ? 'initial' : 'previous' );
			var path = mapping.path || '';

			var sourceOptions = '';
			if ( stepIndex === 0 ) {
				sourceOptions =
					'<option value="initial" selected>' +
					escapeHtml( strings.sourceInitial || 'Workflow input' ) +
					'</option>';
			} else {
				sourceOptions =
					'<option value="previous"' +
					( source === 'previous' ? ' selected' : '' ) +
					'>' +
					escapeHtml( strings.sourcePrevious || 'Previous step' ) +
					'</option>' +
					'<option value="initial"' +
					( source === 'initial' ? ' selected' : '' ) +
					'>' +
					escapeHtml( strings.sourceInitial || 'Workflow input' ) +
					'</option>';
			}

			return (
				'<tr class="aw-mapping-row">' +
				'<td><select class="aw-mapping-source">' +
				sourceOptions +
				'</select></td>' +
				'<td><input type="text" class="aw-mapping-path regular-text" list="' +
				escapeAttr( pathListId ) +
				'" value="' +
				escapeAttr( path ) +
				'" placeholder="' +
				escapeAttr( strings.scalarPathPlaceholder || 'e.g. 0.plan_id' ) +
				'" /></td>' +
				'<td><button type="button" class="button button-small aw-remove-mapping">' +
				escapeHtml( strings.removeMapping || 'Remove' ) +
				'</button></td></tr>'
			);
		},

		renderMappingRow: function ( mapping, stepIndex, rowIndex, pathListId, targetListId ) {
			var source = mapping.source || ( stepIndex === 0 ? 'initial' : 'previous' );
			var path = mapping.path || '';
			var target = mapping.target || '';

			var sourceOptions = '';
			if ( stepIndex === 0 ) {
				sourceOptions =
					'<option value="initial" selected>' +
					escapeHtml( strings.sourceInitial || 'Workflow input' ) +
					'</option>';
			} else {
				sourceOptions =
					'<option value="previous"' +
					( source === 'previous' ? ' selected' : '' ) +
					'>' +
					escapeHtml( strings.sourcePrevious || 'Previous step' ) +
					'</option>' +
					'<option value="initial"' +
					( source === 'initial' ? ' selected' : '' ) +
					'>' +
					escapeHtml( strings.sourceInitial || 'Workflow input' ) +
					'</option>';
			}

			return (
				'<tr class="aw-mapping-row">' +
				'<td><select class="aw-mapping-source">' +
				sourceOptions +
				'</select></td>' +
				'<td><input type="text" class="aw-mapping-path regular-text" list="' +
				escapeAttr( pathListId ) +
				'" value="' +
				escapeAttr( path ) +
				'" placeholder="' +
				escapeAttr( strings.pathPlaceholder || 'e.g. id' ) +
				'" /></td>' +
				'<td><input type="text" class="aw-mapping-target regular-text" list="' +
				escapeAttr( targetListId ) +
				'" value="' +
				escapeAttr( target ) +
				'" placeholder="' +
				escapeAttr( strings.targetPlaceholder || 'e.g. user_id' ) +
				'" /></td>' +
				'<td><button type="button" class="button button-small aw-remove-mapping">' +
				escapeHtml( strings.removeMapping || 'Remove' ) +
				'</button></td></tr>'
			);
		},

		renderStep: function ( step, index ) {
			var self = this;
			var el = document.createElement( 'div' );
			el.className = 'aw-step postbox';
			el.dataset.index = String( index );

			var ability = getAbilityBySlug( this.abilities, step.ability );
			var isScalar = stepUsesScalarInput( ability );
			var inputJson = formatStepInput( step.input );

			if (
				ability &&
				( '' === inputJson ||
					( ! isScalar &&
						'object' === typeof step.input &&
						! Array.isArray( step.input ) &&
						0 === Object.keys( step.input || {} ).length ) )
			) {
				inputJson = formatStepInput( ability.example_input );
			}

			var mappingsHtml = this.buildMappingsSection( step, index );
			var staticHelp = isScalar
				? strings.scalarStaticHelp ||
				  'Optional override (e.g. 142). Leave empty to use the mapped path only.'
				: 'Overrides mapped values for the same keys.';

			el.setAttribute( 'data-scalar-input', isScalar ? '1' : '0' );

			el.innerHTML =
				'<div class="aw-step-header postbox-header">' +
				'<h3>' +
				escapeHtml( ( strings.step || 'Step' ) + ' ' + ( index + 1 ) ) +
				'</h3>' +
				'<div class="aw-step-actions">' +
				'<button type="button" class="button button-small aw-move-up" title="' +
				escapeAttr( strings.moveUp || 'Move up' ) +
				'">↑</button>' +
				'<button type="button" class="button button-small aw-move-down" title="' +
				escapeAttr( strings.moveDown || 'Move down' ) +
				'">↓</button>' +
				'<button type="button" class="button button-small aw-remove-step">' +
				escapeHtml( strings.removeStep || 'Remove' ) +
				'</button>' +
				'</div></div>' +
				'<div class="aw-step-body inside">' +
				'<p><label><strong>' +
				escapeHtml( strings.ability || 'Ability' ) +
				'</strong></label><br>' +
				'<select class="aw-step-ability regular-text">' +
				buildAbilityOptions( this.abilities, step.ability ) +
				'</select></p>' +
				'<p><label><input type="checkbox" class="aw-step-use-previous"' +
				( step.use_previous_output ? ' checked' : '' ) +
				( index === 0 ? ' disabled' : '' ) +
				'> ' +
				escapeHtml( strings.usePreviousOutput || 'Use previous step output as input' ) +
				'</label>' +
				( index > 0 && ! isScalar
					? '<span class="description"> — ' +
					  escapeHtml(
							'Ignored when field mappings are set; use mappings for individual fields.'
					  ) +
					  '</span>'
					: '' ) +
				( index > 0 && isScalar
					? '<span class="description"> — ' +
					  escapeHtml( 'Ignored when a scalar path mapping is set.' ) +
					  '</span>'
					: '' ) +
				'</p>' +
				mappingsHtml +
				'<p><label><strong>' +
				escapeHtml(
					isScalar
						? strings.staticInput + ' (optional override)'
						: strings.staticInput || 'Static input (JSON)'
				) +
				'</strong></label><br>' +
				'<span class="description">' +
				escapeHtml( staticHelp ) +
				'</span><br>' +
				'<textarea class="aw-step-input large-text code" rows="8">' +
				escapeHtml( inputJson ) +
				'</textarea></p>' +
				'<div class="aw-step-schemas"></div>' +
				'</div>';

			this.bindStepEvents( el, index );
			this.updateSchemas( el, step.ability );

			return el;
		},

		bindStepEvents: function ( el, index ) {
			var self = this;

			el.querySelector( '.aw-remove-step' ).addEventListener( 'click', function () {
				if ( self.steps.length <= 1 ) {
					self.steps[ 0 ] = self.defaultStep();
				} else {
					self.steps.splice( index, 1 );
				}
				self.readStepsFromDom();
				self.render();
			} );

			el.querySelector( '.aw-move-up' ).addEventListener( 'click', function () {
				if ( index === 0 ) {
					return;
				}
				self.readStepsFromDom();
				var tmp = self.steps[ index - 1 ];
				self.steps[ index - 1 ] = self.steps[ index ];
				self.steps[ index ] = tmp;
				self.render();
			} );

			el.querySelector( '.aw-move-down' ).addEventListener( 'click', function () {
				if ( index >= self.steps.length - 1 ) {
					return;
				}
				self.readStepsFromDom();
				var tmp = self.steps[ index + 1 ];
				self.steps[ index + 1 ] = self.steps[ index ];
				self.steps[ index ] = tmp;
				self.render();
			} );

			el.querySelector( '.aw-step-ability' ).addEventListener( 'change', function ( e ) {
				self.readStepsFromDom();
				var slug = e.target.value;
				var ability = getAbilityBySlug( self.abilities, slug );
				if ( ability ) {
					el.querySelector( '.aw-step-input' ).value = formatStepInput(
						ability.example_input
					);
				}
				self.steps[ index ].ability = slug;
				self.maybeSuggestMapping( index );
				self.render();
			} );

			var addMapping = el.querySelector( '.aw-add-mapping' );
			if ( addMapping ) {
				addMapping.addEventListener( 'click', function () {
					self.readStepsFromDom();
					if ( ! self.steps[ index ].input_mappings ) {
						self.steps[ index ].input_mappings = [];
					}
					self.steps[ index ].input_mappings.push( {
						source: index === 0 ? 'initial' : 'previous',
						path: '',
						target: '',
					} );
					self.render();
				} );
			}

			el.querySelectorAll( '.aw-remove-mapping' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					self.readStepsFromDom();
					var row = btn.closest( '.aw-mapping-row' );
					var tbody = row ? row.parentNode : null;
					if ( tbody ) {
						var rows = tbody.querySelectorAll( '.aw-mapping-row' );
						var rowIndex = Array.prototype.indexOf.call( rows, row );
						if ( self.steps[ index ].input_mappings ) {
							self.steps[ index ].input_mappings.splice( rowIndex, 1 );
						}
					}
					self.render();
				} );
			} );
		},

		maybeSuggestMapping: function ( stepIndex ) {
			if ( stepIndex <= 0 ) {
				return;
			}

			var step = this.steps[ stepIndex ];
			if ( ! step || ! step.ability ) {
				return;
			}

			if ( step.input_mappings && step.input_mappings.length ) {
				return;
			}

			var prevAbility = this.getPreviousAbility( stepIndex );
			var currentAbility = getAbilityBySlug( this.abilities, step.ability );

			if (
				! prevAbility ||
				! currentAbility ||
				! prevAbility.output_fields ||
				prevAbility.output_fields.indexOf( 'id' ) === -1 ||
				! currentAbility.input_fields ||
				currentAbility.input_fields.indexOf( 'user_id' ) === -1
			) {
				return;
			}

			step.input_mappings = [
				{
					source: 'previous',
					path: 'id',
					target: 'user_id',
				},
			];
		},

		updateSchemas: function ( el, slug ) {
			var schemasEl = el.querySelector( '.aw-step-schemas' );
			var ability = getAbilityBySlug( this.abilities, slug );

			if ( ! schemasEl ) {
				return;
			}

			if ( ! ability ) {
				schemasEl.innerHTML = '';
				return;
			}

			var html = '';

			if ( ability.input_schema && Object.keys( ability.input_schema ).length ) {
				html +=
					'<details class="aw-schema"><summary><strong>' +
					escapeHtml( strings.inputSchema || 'Input schema' ) +
					'</strong></summary><pre class="aw-schema-pre">' +
					escapeHtml( stringifyJson( ability.input_schema ) ) +
					'</pre></details>';
			}

			if ( ability.output_schema && Object.keys( ability.output_schema ).length ) {
				html +=
					'<details class="aw-schema"><summary><strong>' +
					escapeHtml( strings.outputSchema || 'Output schema' ) +
					'</strong></summary><pre class="aw-schema-pre">' +
					escapeHtml( stringifyJson( ability.output_schema ) ) +
					'</pre></details>';
			}

			schemasEl.innerHTML = html;
		},
	};

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

			StepsEditor.syncHiddenField();

			var definition = parseJson(
				document.getElementById( 'workflow_definition' ).value,
				{}
			);
			if ( ! definition || ! definition.steps || ! definition.steps.length ) {
				alert( strings.noSteps || 'Add at least one step before running.' );
				return;
			}

			var initial = parseJson( initialInput ? initialInput.value : '', {} );
			if ( initialInput && initialInput.value.trim() && initial === null ) {
				alert( strings.invalidJson || 'Invalid JSON.' );
				return;
			}

			btn.disabled = true;
			btn.textContent = strings.running || 'Running…';
			results.hidden = false;
			results.innerHTML = '<p class="aw-run-status">' + escapeHtml( strings.running || 'Running…' ) + '</p>';

			var body = new FormData();
			body.append( 'action', 'baton_run' );
			body.append( 'nonce', cfg.nonce );
			body.append( 'workflow_id', btn.getAttribute( 'data-workflow-id' ) );
			body.append( 'initial_input', initialInput ? initialInput.value : '{}' );

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
					btn.textContent = btn.getAttribute( 'data-label' ) || 'Run Workflow';
					RunPanel.renderResults( data );
				} )
				.catch( function ( err ) {
					btn.disabled = false;
					btn.textContent = btn.getAttribute( 'data-label' ) || 'Run Workflow';
					results.innerHTML =
						'<div class="notice notice-error"><p>' + escapeHtml( String( err ) ) + '</p></div>';
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
					escapeHtml( strings.runSuccess || 'Workflow completed successfully.' ) +
					'</p></div>';
			} else {
				html +=
					'<div class="notice notice-error"><p>' +
					escapeHtml( report.error || strings.runFailed || 'Workflow failed.' ) +
					'</p></div>';
			}

			if ( report.steps && report.steps.length ) {
				html += '<div class="aw-run-steps">';
				report.steps.forEach( function ( step, i ) {
					var statusClass = step.success ? 'aw-step-ok' : 'aw-step-fail';
					html += '<details class="aw-run-step ' + statusClass + '" open>';
					html +=
						'<summary><strong>Step ' +
						( i + 1 ) +
						':</strong> ' +
						escapeHtml( step.ability || '' ) +
						( step.success ? ' ✓' : ' ✗' ) +
						'</summary>';
					if ( step.warnings && step.warnings.length ) {
						html += '<p class="aw-warning">' + escapeHtml( step.warnings.join( ' ' ) ) + '</p>';
					}
					html += '<h4>Input</h4><pre class="aw-result-pre">' + escapeHtml( stringifyJson( step.input ) ) + '</pre>';
					if ( step.success ) {
						html +=
							'<h4>Output</h4><pre class="aw-result-pre">' +
							escapeHtml( stringifyJson( step.output ) ) +
							'</pre>';
					} else if ( step.error ) {
						html += '<h4>Error</h4><p class="aw-error">' + escapeHtml( step.error ) + '</p>';
					}
					html += '</details>';
				} );
				html += '</div>';
			}

			results.innerHTML = html;
		},
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		StepsEditor.init();
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
