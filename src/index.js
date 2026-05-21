import { createRoot } from '@wordpress/element';
import WorkflowEditor from './editor/App';

function readJsonElement( id, fallback ) {
	const el = document.getElementById( id );
	if ( ! el || ! el.textContent ) {
		return fallback;
	}
	try {
		return JSON.parse( el.textContent );
	} catch ( e ) {
		return fallback;
	}
}

document.addEventListener( 'DOMContentLoaded', () => {
	const rootEl = document.getElementById( 'baton-editor-root' );
	if ( ! rootEl ) {
		return;
	}

	const abilities = readJsonElement( 'baton-abilities-data', [] );
	const definition = readJsonElement( 'baton-definition-data', {
		initial_input: {},
		steps: [],
	} );

	const root = createRoot( rootEl );
	root.render(
		<WorkflowEditor
			abilities={ abilities }
			initialDefinition={ definition }
		/>
	);
} );
