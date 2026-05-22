/**
 * @param {Array} abilities
 * @param {string} slug
 */
export function getAbility( abilities, slug ) {
	return abilities.find( ( a ) => a.slug === slug ) || null;
}

/**
 * Group abilities by category label for <optgroup> rendering.
 *
 * @param {Array} abilities Catalog from PHP.
 * @param {string} otherLabel Label for uncategorized abilities.
 * @return {Array<{ label: string, abilities: Array }>}
 */
export function groupAbilitiesByCategory( abilities, otherLabel = 'Other' ) {
	const grouped = {};

	abilities.forEach( ( ability ) => {
		const cat = ability.category || otherLabel;
		if ( ! grouped[ cat ] ) {
			grouped[ cat ] = [];
		}
		grouped[ cat ].push( ability );
	} );

	return Object.keys( grouped )
		.sort( ( a, b ) => a.localeCompare( b ) )
		.map( ( label ) => ( {
			label,
			abilities: grouped[ label ].sort( ( a, b ) =>
				a.label.localeCompare( b.label )
			),
		} ) );
}

export function defaultStep() {
	return {
		ability: '',
		input: {},
		use_previous_output: false,
		input_mappings: [],
	};
}

export function stringifyJson( value ) {
	return JSON.stringify( value, null, 2 );
}

export function formatStepInput( value, ability ) {
	if ( value === null || value === '' ) {
		return '';
	}
	if ( typeof value === 'number' || typeof value === 'boolean' ) {
		return String( value );
	}
	if ( ability?.input_is_scalar && typeof value === 'string' ) {
		return value;
	}
	return stringifyJson( value );
}

export function parseStepInput( text, ability ) {
	if ( ! text || ! String( text ).trim() ) {
		return ability?.input_is_scalar ? '' : {};
	}
	try {
		const parsed = JSON.parse( text );
		if ( ability?.input_is_scalar ) {
			if ( typeof parsed === 'number' || typeof parsed === 'boolean' ) {
				return parsed;
			}
			if ( typeof parsed === 'string' && parsed !== '' ) {
				return parsed;
			}
		}
		return parsed || {};
	} catch ( e ) {
		if ( ability?.input_is_scalar ) {
			return String( text ).trim();
		}
		// Caller should keep a local draft while typing; do not use this return for live textarea binding.
		return {};
	}
}

/**
 * @param {object} mapping
 * @param {object|null} downstreamAbility
 */
export function mappingRowLabel( mapping, downstreamAbility ) {
	const path = mapping.path || '';
	const target = mapping.target || '';
	if ( downstreamAbility && ! downstreamAbility.target_selectable ) {
		return path ? `${ path } → entire input` : '';
	}
	if ( path && target ) {
		return `${ path } → ${ target }`;
	}
	return path || '';
}

/**
 * @param {Array} mappings
 * @param {object|null} downstreamAbility
 */
export function connectorSummary( mappings, downstreamAbility ) {
	if ( ! mappings?.length ) {
		return '';
	}
	return mappings
		.map( ( m ) => mappingRowLabel( m, downstreamAbility ) )
		.filter( Boolean )
		.join( ', ' );
}

/**
 * @param {object} definition
 */
/**
 * Keep only complete mapping rows for persistence.
 *
 * @param {Array} mappings Draft rows.
 * @param {object|null} downstreamAbility Ability meta.
 * @return {Array}
 */
export function sanitizeMappingsForSave( mappings, downstreamAbility ) {
	if ( ! Array.isArray( mappings ) ) {
		return [];
	}

	const needsTarget = !! downstreamAbility?.target_selectable;

	return mappings.filter( ( row ) => {
		if ( ! row || ! row.path ) {
			return false;
		}
		if ( needsTarget ) {
			return !! row.target;
		}
		return true;
	} );
}

export function definitionFromSteps( steps, initialInput = {} ) {
	return {
		initial_input: initialInput,
		steps: steps.map( ( step ) => ( {
			ability: step.ability,
			input: step.input,
			use_previous_output: !! step.use_previous_output,
			input_mappings: step.input_mappings || [],
		} ) ),
	};
}
