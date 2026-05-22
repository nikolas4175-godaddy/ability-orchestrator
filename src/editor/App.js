import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Modal,
	Notice,
	Panel,
	PanelBody,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	defaultStep,
	formatStepInput,
	getAbility,
	parseStepInput,
	definitionFromSteps,
	mappingRowLabel,
	sanitizeMappingsForSave,
	groupAbilitiesByCategory,
} from './utils';

function defaultMappingRow( stepIndex ) {
	return {
		source: stepIndex === 0 ? 'initial' : 'previous',
		path: '',
		target: '',
	};
}

function FlowLine() {
	return <div className="baton-flow-line" aria-hidden="true" />;
}

function AbilitySelect( { value, abilities, onChange } ) {
	const groups = useMemo(
		() => groupAbilitiesByCategory( abilities, __( 'Other', 'baton' ) ),
		[ abilities ]
	);

	return (
		<div className="baton-ability-field">
			<span className="baton-ability-field__label">
				{ __( 'Ability', 'baton' ) }
			</span>
			<select
				className="baton-ability-select"
				value={ value }
				aria-label={ __( 'Ability', 'baton' ) }
				onChange={ ( event ) => onChange( event.target.value ) }
			>
				<option value="">
					{ __( 'Select an ability…', 'baton' ) }
				</option>
				{ groups.map( ( group ) => (
					<optgroup key={ group.label } label={ group.label }>
						{ group.abilities.map( ( ability ) => (
							<option key={ ability.slug } value={ ability.slug }>
								{ ability.label } ({ ability.slug })
							</option>
						) ) }
					</optgroup>
				) ) }
			</select>
		</div>
	);
}

function IoChip( { label, sublabel, summary, isSingleValue, onClick } ) {
	return (
		<button
			type="button"
			className={ `baton-io-chip${
				isSingleValue ? ' baton-io-chip--single' : ''
			}` }
			onClick={ onClick }
		>
			<span className="baton-io-chip__label">{ label }</span>
			{ sublabel && (
				<span className="baton-io-chip__sublabel">{ sublabel }</span>
			) }
			<span className="baton-io-chip__value">{ summary }</span>
		</button>
	);
}

function DataFilterSlot( {
	downstreamIndex,
	steps,
	abilities,
	onConfigure,
	onRemove,
} ) {
	const downstream = steps[ downstreamIndex ];
	const mappings = downstream?.input_mappings || [];
	const hasMappings = mappings.length > 0;

	if ( ! hasMappings ) {
		return (
			<div className="baton-filter-slot baton-filter-slot--empty">
				<FlowLine />
				<div className="baton-filter-slot__compact">
					<Button
						variant="secondary"
						className="baton-filter-slot__btn"
						onClick={ onConfigure }
						size="compact"
					>
						+
					</Button>
					<button
						type="button"
						className="baton-filter-slot__label-btn"
						onClick={ onConfigure }
					>
						{ __( 'Add data filter', 'baton' ) }
					</button>
				</div>
				<FlowLine />
			</div>
		);
	}

	return (
		<div className="baton-filter-slot baton-filter-slot--configured">
			<FlowLine />
			<DataFilterNode
				downstreamIndex={ downstreamIndex }
				steps={ steps }
				abilities={ abilities }
				onConfigure={ onConfigure }
				onRemove={ onRemove }
			/>
			<FlowLine />
		</div>
	);
}

function DataFilterNode( {
	downstreamIndex,
	steps,
	abilities,
	onConfigure,
	onRemove,
} ) {
	const downstream = steps[ downstreamIndex ];
	const downstreamAbility = getAbility( abilities, downstream?.ability );
	const upstream = downstreamIndex > 0 ? steps[ downstreamIndex - 1 ] : null;
	const upstreamAbility = upstream
		? getAbility( abilities, upstream.ability )
		: null;

	const mappings = downstream?.input_mappings || [];

	const fromLabel =
		downstreamIndex > 0 && upstreamAbility
			? `${ __( 'From', 'baton' ) } ${ __(
					'Step',
					'baton'
			  ) } ${ downstreamIndex }: ${ upstreamAbility.label }`
			: __( 'From workflow input', 'baton' );

	const toLabel = downstreamAbility
		? `${ __( 'Into', 'baton' ) } ${ __( 'Step', 'baton' ) } ${
				downstreamIndex + 1
		  }: ${ downstreamAbility.label }`
		: `${ __( 'Into', 'baton' ) } ${ __( 'Step', 'baton' ) } ${
				downstreamIndex + 1
		  }`;

	return (
		<Card className="baton-filter-node baton-filter-node--configured">
			<CardHeader>
				<div className="baton-filter-node__header">
					<div>
						<span className="baton-filter-node__type">
							{ __( 'Data filter', 'baton' ) }
						</span>
						<strong className="baton-filter-node__title">
							{ __( 'Data flow', 'baton' ) }
						</strong>
					</div>
					<div className="baton-filter-node__actions">
						<Button
							variant="link"
							isDestructive
							onClick={ onRemove }
							size="small"
						>
							{ __( 'Clear', 'baton' ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ onConfigure }
							size="small"
						>
							{ __( 'Edit mapping', 'baton' ) }
						</Button>
					</div>
				</div>
			</CardHeader>
			<CardBody>
				<div className="baton-filter-node__route">
					<span className="baton-filter-node__endpoint">
						{ fromLabel }
					</span>
					<span
						className="baton-filter-node__arrow"
						aria-hidden="true"
					>
						→
					</span>
					<span className="baton-filter-node__endpoint">
						{ toLabel }
					</span>
				</div>
				<ul className="baton-filter-node__mappings">
					{ mappings.map( ( mapping, i ) => (
						<li key={ i }>
							<code>
								{ mappingRowLabel(
									mapping,
									downstreamAbility
								) }
							</code>
						</li>
					) ) }
				</ul>
			</CardBody>
		</Card>
	);
}

function DataFilterPanel( {
	stepIndex,
	steps,
	abilities,
	onClose,
	onChangeMappings,
} ) {
	const downstream = steps[ stepIndex ];
	const downstreamAbility = getAbility( abilities, downstream?.ability );
	const upstream = stepIndex > 0 ? steps[ stepIndex - 1 ] : null;
	const upstreamAbility = upstream
		? getAbility( abilities, upstream.ability )
		: null;

	const [ draftMappings, setDraftMappings ] = useState( () => {
		const saved = downstream?.input_mappings;
		return saved?.length
			? saved.map( ( row ) => ( { ...row } ) )
			: [ defaultMappingRow( stepIndex ) ];
	} );

	const mappings = draftMappings;

	const sourceOptions = useMemo( () => {
		if ( stepIndex === 0 || ! upstreamAbility?.source_selectable ) {
			return [];
		}
		return ( upstreamAbility.output_paths || [] ).map( ( p ) => ( {
			value: p.value,
			label: p.label,
		} ) );
	}, [ stepIndex, upstreamAbility ] );

	const targetOptions = useMemo( () => {
		if ( ! downstreamAbility?.target_selectable ) {
			return [];
		}
		return ( downstreamAbility.input_targets || [] ).map( ( t ) => ( {
			value: t.value,
			label: t.label,
		} ) );
	}, [ downstreamAbility ] );

	const updateMapping = ( rowIndex, field, value ) => {
		const source = stepIndex === 0 ? 'initial' : 'previous';
		setDraftMappings( ( prev ) =>
			prev.map( ( row, i ) =>
				i === rowIndex
					? { ...row, [ field ]: value, source }
					: { ...row, source: row.source || source }
			)
		);
	};

	const addMapping = () => {
		setDraftMappings( ( prev ) => [
			...prev,
			defaultMappingRow( stepIndex ),
		] );
	};

	const removeMapping = ( rowIndex ) => {
		setDraftMappings( ( prev ) => {
			const next = prev.filter( ( _, i ) => i !== rowIndex );
			return next.length ? next : [ defaultMappingRow( stepIndex ) ];
		} );
	};

	const handleDone = () => {
		onChangeMappings(
			stepIndex,
			sanitizeMappingsForSave( draftMappings, downstreamAbility )
		);
		onClose();
	};

	return (
		<Modal
			title={ `${ __( 'Configure data filter', 'baton' ) } — ${ __(
				'Step',
				'baton'
			) } ${ stepIndex + 1 }` }
			onRequestClose={ onClose }
			className="baton-data-filter-modal"
		>
			<Notice status="info" isDismissible={ false }>
				{ __(
					'Data filters define how values move between steps at run time. Ability schemas (on step cards) describe what each ability accepts and returns in general.',
					'baton'
				) }
			</Notice>
			{ stepIndex > 0 && upstreamAbility && (
				<p className="description">
					<strong>{ __( 'Source', 'baton' ) }:</strong>{ ' ' }
					{ upstreamAbility.output_summary?.summary }
					<span className="baton-schema-hint">
						{ ' ' }
						({ __( 'ability output schema', 'baton' ) })
					</span>
				</p>
			) }
			{ downstreamAbility && (
				<p className="description">
					<strong>{ __( 'Target', 'baton' ) }:</strong>{ ' ' }
					{ downstreamAbility.input_summary?.summary }
					<span className="baton-schema-hint">
						{ ' ' }
						({ __( 'ability input schema', 'baton' ) })
					</span>
				</p>
			) }
			{ stepIndex > 0 && ! upstreamAbility && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Select an ability on the previous step first.',
						'baton'
					) }
				</Notice>
			) }
			{ mappings.map( ( mapping, rowIndex ) => (
				<div key={ rowIndex } className="baton-mapping-row">
					<div className="baton-mapping-row__fields">
						{ stepIndex > 0 &&
						! upstreamAbility?.source_selectable ? (
							<div className="baton-mapping-readonly">
								<span className="baton-mapping-readonly__label">
									{ __( 'Source path', 'baton' ) }
								</span>
								<div className="baton-mapping-readonly__value">
									{ upstreamAbility?.source_display?.label ||
										__( 'Previous step output', 'baton' ) }
								</div>
							</div>
						) : (
							<SelectControl
								label={ __( 'Source path', 'baton' ) }
								help={ __(
									'Value taken from the previous step or workflow input.',
									'baton'
								) }
								value={ mapping.path || '' }
								options={ [
									{
										value: '',
										label: __( 'Select path…', 'baton' ),
									},
									...sourceOptions,
								] }
								onChange={ ( path ) =>
									updateMapping( rowIndex, 'path', path )
								}
							/>
						) }
						<span className="baton-mapping-arrow">→</span>
						{ ! downstreamAbility?.target_selectable ? (
							<div className="baton-mapping-readonly">
								<span className="baton-mapping-readonly__label">
									{ __( 'Target', 'baton' ) }
								</span>
								<div className="baton-mapping-readonly__value">
									{ downstreamAbility?.target_display
										?.label ||
										__( 'Entire input', 'baton' ) }
								</div>
							</div>
						) : (
							<SelectControl
								label={ __( 'Target field', 'baton' ) }
								help={ __(
									'Field on the next step’s input object.',
									'baton'
								) }
								value={ mapping.target || '' }
								options={ [
									{
										value: '',
										label: __( 'Select field…', 'baton' ),
									},
									...targetOptions,
								] }
								onChange={ ( target ) =>
									updateMapping( rowIndex, 'target', target )
								}
							/>
						) }
					</div>
					{ mappings.length > 1 && (
						<Button
							variant="link"
							isDestructive
							onClick={ () => removeMapping( rowIndex ) }
						>
							{ __( 'Remove row', 'baton' ) }
						</Button>
					) }
				</div>
			) ) }
			{ downstreamAbility?.target_selectable && (
				<Button variant="secondary" onClick={ addMapping }>
					{ __( 'Add mapping row', 'baton' ) }
				</Button>
			) }
			<div className="baton-modal-actions">
				<Button variant="primary" onClick={ handleDone }>
					{ __( 'Done', 'baton' ) }
				</Button>
			</div>
		</Modal>
	);
}

function IoDetailPanel( { panel, abilities, steps, onClose, onInputChange } ) {
	const step = panel ? steps[ panel.stepIndex ] : null;
	const ability = step ? getAbility( abilities, step.ability ) : null;
	const isInput = panel?.kind === 'input';
	const formattedInput = formatStepInput( step?.input, ability );

	const [ inputDraft, setInputDraft ] = useState( formattedInput );
	const [ jsonError, setJsonError ] = useState( null );

	// Reset draft only when opening a different step/ability panel, not on each keystroke.
	useEffect( () => {
		if ( ! panel || ! isInput ) {
			return;
		}
		setInputDraft(
			formatStepInput( steps[ panel.stepIndex ]?.input, ability )
		);
		setJsonError( null );
	}, [ panel?.stepIndex, panel?.kind, ability?.slug, isInput ] ); // eslint-disable-line react-hooks/exhaustive-deps -- avoid resetting draft while typing.

	if ( ! panel ) {
		return null;
	}

	if ( ! ability ) {
		return null;
	}

	const schema =
		panel.kind === 'input' ? ability.input_schema : ability.output_schema;

	const handleInputDraftChange = ( text ) => {
		setInputDraft( text );

		if ( ability.input_is_scalar ) {
			onInputChange( panel.stepIndex, parseStepInput( text, ability ) );
			setJsonError( null );
			return;
		}

		if ( ! text || ! String( text ).trim() ) {
			onInputChange( panel.stepIndex, {} );
			setJsonError( null );
			return;
		}

		try {
			const parsed = JSON.parse( text );
			onInputChange( panel.stepIndex, parsed ?? {} );
			setJsonError( null );
		} catch ( e ) {
			setJsonError(
				__(
					'Invalid JSON — keep editing or fix before saving the workflow.',
					'baton'
				)
			);
		}
	};

	const handleClose = () => {
		setJsonError( null );
		onClose();
	};

	return (
		<Modal
			title={
				isInput
					? `${ __( 'Ability input schema', 'baton' ) } — ${ __(
							'Step',
							'baton'
					  ) } ${ panel.stepIndex + 1 }`
					: `${ __( 'Ability output schema', 'baton' ) } — ${ __(
							'Step',
							'baton'
					  ) } ${ panel.stepIndex + 1 }`
			}
			onRequestClose={ handleClose }
		>
			<Notice status="info" isDismissible={ false }>
				{ __(
					'This is the ability’s declared contract (JSON Schema). It does not show your workflow mappings — configure those in the data filter nodes between steps.',
					'baton'
				) }
			</Notice>
			<p className="description">
				<strong>{ ability.label }</strong>
				<br />
				<code>{ ability.slug }</code>
			</p>
			{ ability.description && (
				<p className="description">{ ability.description }</p>
			) }
			<Panel>
				<PanelBody title={ __( 'JSON Schema', 'baton' ) } initialOpen>
					<pre className="baton-schema-pre">
						{ JSON.stringify( schema || {}, null, 2 ) }
					</pre>
				</PanelBody>
			</Panel>
			{ isInput && (
				<div className="baton-static-input">
					{ jsonError && (
						<Notice status="warning" isDismissible={ false }>
							{ jsonError }
						</Notice>
					) }
					{ ability.input_is_scalar ? (
						<TextControl
							label={ __(
								'Static input (optional override)',
								'baton'
							) }
							help={ __(
								'Fixed value merged at run time; overrides data filter mappings for this step.',
								'baton'
							) }
							value={ inputDraft }
							onChange={ handleInputDraftChange }
						/>
					) : (
						<TextareaControl
							label={ __( 'Static input (JSON)', 'baton' ) }
							help={ __(
								'Fixed fields merged at run time; overrides matching keys from data filters.',
								'baton'
							) }
							value={ inputDraft }
							onChange={ handleInputDraftChange }
							rows={ 6 }
						/>
					) }
				</div>
			) }
			<div className="baton-modal-actions">
				<Button variant="primary" onClick={ handleClose }>
					{ __( 'Close', 'baton' ) }
				</Button>
			</div>
		</Modal>
	);
}

function StepCard( {
	step,
	index,
	abilities,
	totalSteps,
	onAbilityChange,
	onIoClick,
	onMoveUp,
	onMoveDown,
	onRemove,
} ) {
	const ability = getAbility( abilities, step.ability );
	const inputSummary =
		ability?.input_summary?.summary || __( 'No input', 'baton' );
	const outputSummary =
		ability?.output_summary?.summary || __( 'Unknown', 'baton' );
	const isSingleInput = ability?.input_summary?.kind === 'single_value';

	return (
		<Card className="baton-step-card">
			<CardHeader>
				<div className="baton-step-card__header">
					<div>
						<span className="baton-step-card__type">
							{ __( 'Ability step', 'baton' ) }
						</span>
						<strong>
							{ __( 'Step', 'baton' ) } { index + 1 }
							{ ability ? `: ${ ability.label }` : '' }
						</strong>
						{ ability && (
							<div className="baton-step-card__slug">
								<code>{ ability.slug }</code>
							</div>
						) }
					</div>
					<div className="baton-step-card__actions">
						<Button
							icon="arrow-up-alt2"
							label={ __( 'Move up', 'baton' ) }
							onClick={ onMoveUp }
							disabled={ index === 0 }
							size="small"
						/>
						<Button
							icon="arrow-down-alt2"
							label={ __( 'Move down', 'baton' ) }
							onClick={ onMoveDown }
							disabled={ index >= totalSteps - 1 }
							size="small"
						/>
						<Button
							variant="link"
							isDestructive
							onClick={ onRemove }
							size="small"
						>
							{ __( 'Remove', 'baton' ) }
						</Button>
					</div>
				</div>
			</CardHeader>
			<CardBody>
				<AbilitySelect
					value={ step.ability }
					abilities={ abilities }
					onChange={ onAbilityChange }
				/>
				<div className="baton-schema-section">
					<p className="baton-schema-section__heading">
						{ __( 'Ability contract (schema)', 'baton' ) }
					</p>
					<p className="baton-schema-section__help description">
						{ __(
							'What this ability is designed to accept and return. Use data filter nodes between steps to map actual values at run time.',
							'baton'
						) }
					</p>
					<div className="baton-io-row">
						<IoChip
							label={ __( 'Input schema', 'baton' ) }
							sublabel={ __( 'declared input', 'baton' ) }
							summary={ inputSummary }
							isSingleValue={ isSingleInput }
							onClick={ () => onIoClick( index, 'input' ) }
						/>
						<IoChip
							label={ __( 'Output schema', 'baton' ) }
							sublabel={ __( 'declared output', 'baton' ) }
							summary={ outputSummary }
							isSingleValue={
								ability?.output_summary?.kind === 'single_value'
							}
							onClick={ () => onIoClick( index, 'output' ) }
						/>
					</div>
				</div>
			</CardBody>
		</Card>
	);
}

export default function WorkflowEditor( { abilities, initialDefinition } ) {
	const [ steps, setSteps ] = useState( () => {
		const s = initialDefinition?.steps;
		return s?.length
			? s.map( ( step ) => ( { ...step } ) )
			: [ defaultStep() ];
	} );
	const [ activeFilter, setActiveFilter ] = useState( null );
	const [ ioPanel, setIoPanel ] = useState( null );

	const syncHiddenField = useCallback( () => {
		const hidden = document.getElementById( 'workflow_definition' );
		if ( ! hidden ) {
			return;
		}
		const initial =
			initialDefinition?.initial_input &&
			typeof initialDefinition.initial_input === 'object'
				? initialDefinition.initial_input
				: {};
		hidden.value = JSON.stringify( definitionFromSteps( steps, initial ) );
	}, [ steps, initialDefinition ] );

	useEffect( () => {
		syncHiddenField();
		window.BatonEditorSync = syncHiddenField;
		return () => {
			delete window.BatonEditorSync;
		};
	}, [ syncHiddenField ] );

	const updateStep = ( index, patch ) => {
		setSteps( ( prev ) =>
			prev.map( ( step, i ) =>
				i === index ? { ...step, ...patch } : step
			)
		);
	};

	const changeAbility = ( index, slug ) => {
		const ability = getAbility( abilities, slug );
		let input = {};
		if ( ability ) {
			input =
				ability.example_input ?? ( ability.input_is_scalar ? '' : {} );
		}
		updateStep( index, {
			ability: slug,
			input,
			input_mappings: [],
		} );
	};

	const moveStep = ( index, direction ) => {
		const target = index + direction;
		if ( target < 0 || target >= steps.length ) {
			return;
		}
		setSteps( ( prev ) => {
			const next = [ ...prev ];
			const tmp = next[ index ];
			next[ index ] = next[ target ];
			next[ target ] = tmp;
			return next;
		} );
		setActiveFilter( null );
	};

	const removeStep = ( index ) => {
		setSteps( ( prev ) => {
			if ( prev.length <= 1 ) {
				return [ defaultStep() ];
			}
			return prev.filter( ( _, i ) => i !== index );
		} );
		setActiveFilter( null );
	};

	const changeMappings = ( stepIndex, mappings ) => {
		updateStep( stepIndex, { input_mappings: mappings } );
	};

	if ( ! abilities.length ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ __( 'No abilities registered.', 'baton' ) }
			</Notice>
		);
	}

	return (
		<div className="baton-editor">
			<Notice
				status="info"
				isDismissible={ false }
				className="baton-editor__intro"
			>
				<p>
					<strong>{ __( 'Ability steps', 'baton' ) }</strong>
					{ ' — ' }
					{ __(
						'run abilities in order; input/output chips show each ability’s schema.',
						'baton'
					) }
				</p>
				<p>
					<strong>{ __( 'Data filter nodes', 'baton' ) }</strong>
					{ ' — ' }
					{ __(
						'sit between steps and define how values are passed from one step into the next at run time.',
						'baton'
					) }
				</p>
			</Notice>

			<div className="baton-editor__toolbar">
				<Button
					variant="primary"
					onClick={ () =>
						setSteps( ( prev ) => [ ...prev, defaultStep() ] )
					}
				>
					{ __( 'Add step', 'baton' ) }
				</Button>
			</div>

			<div className="baton-editor__chain">
				{ steps.map( ( step, index ) => (
					<div
						key={ `step-${ index }` }
						className="baton-editor__segment"
					>
						<StepCard
							step={ step }
							index={ index }
							abilities={ abilities }
							totalSteps={ steps.length }
							onAbilityChange={ ( slug ) =>
								changeAbility( index, slug )
							}
							onIoClick={ ( stepIndex, kind ) =>
								setIoPanel( { stepIndex, kind } )
							}
							onMoveUp={ () => moveStep( index, -1 ) }
							onMoveDown={ () => moveStep( index, 1 ) }
							onRemove={ () => removeStep( index ) }
						/>
						{ index < steps.length - 1 && (
							<DataFilterSlot
								downstreamIndex={ index + 1 }
								steps={ steps }
								abilities={ abilities }
								onConfigure={ () =>
									setActiveFilter( index + 1 )
								}
								onRemove={ () =>
									changeMappings( index + 1, [] )
								}
							/>
						) }
					</div>
				) ) }
			</div>

			{ activeFilter !== null && (
				<DataFilterPanel
					key={ `filter-panel-${ activeFilter }` }
					stepIndex={ activeFilter }
					steps={ steps }
					abilities={ abilities }
					onClose={ () => setActiveFilter( null ) }
					onChangeMappings={ changeMappings }
				/>
			) }

			<IoDetailPanel
				panel={ ioPanel }
				abilities={ abilities }
				steps={ steps }
				onClose={ () => setIoPanel( null ) }
				onInputChange={ ( stepIndex, input ) =>
					updateStep( stepIndex, { input } )
				}
			/>
		</div>
	);
}
