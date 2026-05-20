<?php
/**
 * Admin screens and AJAX handlers.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for ability workflows.
 */
final class Baton_Admin {

	public const PAGE_SLUG = 'baton';

	public const NONCE_ACTION = 'baton_admin';

	public const AJAX_RUN = 'baton_run';

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_init', array( self::class, 'maybe_handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_RUN, array( self::class, 'ajax_run_workflow' ) );
	}

	/**
	 * Register Tools submenu.
	 */
	public static function register_menu(): void {
		add_submenu_page(
			'tools.php',
			__( 'Baton', 'baton' ),
			__( 'Baton', 'baton' ),
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render_router' )
		);
	}

	/**
	 * Route list vs edit screen.
	 */
	public static function render_router(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'baton' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( in_array( $action, array( 'edit', 'new' ), true ) ) {
			self::render_edit_page();
			return;
		}

		self::render_list_page();
	}

	/**
	 * List workflows page.
	 */
	private static function render_list_page(): void {
		$table = new Baton_Workflow_List_Table();
		$table->prepare_items();

		$new_url = self::get_edit_url( 0 );

		echo '<div class="wrap baton-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Baton', 'baton' ) . '</h1>';
		echo '<a href="' . esc_url( $new_url ) . '" class="page-title-action">' . esc_html__( 'Add New', 'baton' ) . '</a>';
		echo '<hr class="wp-header-end">';

		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Workflow saved.', 'baton' ) . '</p></div>';
		}

		if ( isset( $_GET['deleted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Workflow deleted.', 'baton' ) . '</p></div>';
		}

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
		$table->search_box( __( 'Search Workflows', 'baton' ), 'workflow' );
		$table->display();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Edit / new workflow page.
	 */
	private static function render_edit_page(): void {
		$post_id = isset( $_GET['workflow_id'] ) ? absint( $_GET['workflow_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_new  = isset( $_GET['action'] ) && 'new' === sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$post       = null;
		$definition = Baton_Workflow_CPT::default_definition();

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( ! $post || Baton_Workflow_CPT::POST_TYPE !== $post->post_type ) {
				wp_die( esc_html__( 'Workflow not found.', 'baton' ) );
			}
			$definition = Baton_Workflow_CPT::get_definition( $post_id );
		}

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['baton_save'] ) ) {
			self::handle_save_post( $post_id, $is_new );
			return;
		}

		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$error = sanitize_text_field( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
		}

		$list_url = admin_url( 'tools.php?page=' . self::PAGE_SLUG );
		$title    = $is_new || ! $post ? __( 'Add Workflow', 'baton' ) : __( 'Edit Workflow', 'baton' );

		echo '<div class="wrap baton-wrap baton-edit">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p><a href="' . esc_url( $list_url ) . '">&larr; ' . esc_html__( 'Back to workflows', 'baton' ) . '</a></p>';

		echo '<script type="application/json" id="aw-abilities-data">';
		echo wp_json_encode( Baton_Ability_Catalog::get_all() );
		echo '</script>';
		echo '<script type="application/json" id="aw-definition-data">';
		echo wp_json_encode( $definition );
		echo '</script>';

		echo '<form method="post" id="baton-form">';
		wp_nonce_field( self::NONCE_ACTION, 'baton_nonce' );
		echo '<input type="hidden" name="baton_save" value="1" />';
		if ( $post_id > 0 ) {
			echo '<input type="hidden" name="workflow_id" value="' . esc_attr( (string) $post_id ) . '" />';
		}

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="workflow_title">' . esc_html__( 'Title', 'baton' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="workflow_title" name="workflow_title" value="' . esc_attr( $post ? $post->post_title : '' ) . '" required /></td></tr>';
		echo '<tr><th scope="row"><label for="workflow_excerpt">' . esc_html__( 'Description', 'baton' ) . '</label></th>';
		echo '<td><textarea class="large-text" rows="2" id="workflow_excerpt" name="workflow_excerpt">' . esc_textarea( $post ? $post->post_excerpt : '' ) . '</textarea></td></tr>';
		echo '</table>';

		if ( $post_id > 0 && 'publish' === get_post_status( $post_id ) ) {
			self::render_ability_slug_panel( $post_id );
		}

		echo '<h2>' . esc_html__( 'Steps', 'baton' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Add abilities in the order they should run. Each step executes after the previous one completes.', 'baton' ) . '</p>';
		echo '<div id="aw-steps-container"></div>';
		echo '<p><button type="button" class="button" id="aw-add-step">' . esc_html__( 'Add Step', 'baton' ) . '</button></p>';

		echo '<input type="hidden" name="workflow_definition" id="workflow_definition" value="' . esc_attr( (string) wp_json_encode( $definition ) ) . '" />';

		echo '<p class="submit">';
		submit_button( __( 'Save Workflow', 'baton' ), 'primary', 'submit', false );
		if ( $post_id > 0 ) {
			$delete_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'         => self::PAGE_SLUG,
						'action'       => 'delete',
						'workflow_id'  => $post_id,
					),
					admin_url( 'tools.php' )
				),
				'delete_workflow_' . $post_id
			);
			echo ' <a class="button button-link-delete" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this workflow?', 'baton' ) ) . '\');">' . esc_html__( 'Delete', 'baton' ) . '</a>';
		}
		echo '</p>';
		echo '</form>';

		if ( $post_id > 0 ) {
			self::render_run_panel( $post_id, $definition );
		}

		echo '</div>';
	}

	/**
	 * Show the registered ability slug for this workflow.
	 *
	 * @param int $post_id Workflow post ID.
	 */
	private static function render_ability_slug_panel( int $post_id ): void {
		$ability_name = Baton_Workflow_Abilities::get_ability_name( $post_id );

		echo '<div class="baton-ability-slug-panel postbox" style="margin-top:16px;">';
		echo '<div class="inside">';
		echo '<h2 style="margin-top:0;">' . esc_html__( 'Workflow ability', 'baton' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'This workflow is registered as an ability. Use this name in other workflows, the REST API, or tooling that discovers abilities.', 'baton' ) . '</p>';
		echo '<p><code id="baton-ability-slug" class="baton-ability-slug">' . esc_html( $ability_name ) . '</code> ';
		echo '<button type="button" class="button button-small" id="baton-copy-ability-slug">' . esc_html__( 'Copy', 'baton' ) . '</button></p>';
		echo '</div></div>';
	}

	/**
	 * Run panel markup.
	 *
	 * @param int                  $post_id    Workflow post ID.
	 * @param array<string, mixed> $definition Workflow definition.
	 */
	private static function render_run_panel( int $post_id, array $definition ): void {
		$initial_json = wp_json_encode( $definition['initial_input'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		echo '<div id="aw-run-panel" class="aw-run-panel">';
		echo '<h2>' . esc_html__( 'Run Workflow', 'baton' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Optional workflow-level input is merged into the first step only.', 'baton' ) . '</p>';
		echo '<label for="aw-run-initial-input"><strong>' . esc_html__( 'Workflow input (JSON)', 'baton' ) . '</strong></label>';
		echo '<textarea id="aw-run-initial-input" class="large-text code" rows="6">' . esc_textarea( (string) $initial_json ) . '</textarea>';
		$run_label = __( 'Run Workflow', 'baton' );
		echo '<p><button type="button" class="button button-primary" id="aw-run-workflow" data-workflow-id="' . esc_attr( (string) $post_id ) . '" data-label="' . esc_attr( $run_label ) . '">' . esc_html( $run_label ) . '</button></p>';
		echo '<div id="aw-run-results" class="aw-run-results" hidden></div>';
		echo '</div>';
	}

	/**
	 * Handle save POST.
	 *
	 * @param int  $post_id Existing post ID.
	 * @param bool $is_new  Whether this is a new workflow.
	 */
	private static function handle_save_post( int $post_id, bool $is_new ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'baton' ) );
		}

		check_admin_referer( self::NONCE_ACTION, 'baton_nonce' );

		$title       = isset( $_POST['workflow_title'] ) ? sanitize_text_field( wp_unslash( $_POST['workflow_title'] ) ) : '';
		$excerpt     = isset( $_POST['workflow_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['workflow_excerpt'] ) ) : '';
		$def_raw     = isset( $_POST['workflow_definition'] ) ? wp_unslash( $_POST['workflow_definition'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$def_decoded = json_decode( $def_raw, true );

		if ( ! is_array( $def_decoded ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'        => self::PAGE_SLUG,
						'action'      => $is_new ? 'new' : 'edit',
						'workflow_id' => $post_id > 0 ? $post_id : null,
						'error'       => rawurlencode( __( 'Invalid workflow definition JSON.', 'baton' ) ),
					),
					admin_url( 'tools.php' )
				)
			);
			exit;
		}

		$definition = Baton_Workflow_CPT::sanitize_definition( $def_decoded );
		if ( is_wp_error( $definition ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'   => self::PAGE_SLUG,
						'action' => $is_new ? 'new' : 'edit',
						'error'  => rawurlencode( $definition->get_error_message() ),
					),
					admin_url( 'tools.php' )
				)
			);
			exit;
		}

		if ( '' === $title ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'   => self::PAGE_SLUG,
						'action' => $is_new ? 'new' : 'edit',
						'error'  => rawurlencode( __( 'Title is required.', 'baton' ) ),
					),
					admin_url( 'tools.php' )
				)
			);
			exit;
		}

		if ( $is_new || $post_id <= 0 ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => Baton_Workflow_CPT::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_excerpt' => $excerpt,
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				wp_die( esc_html( $post_id->get_error_message() ) );
			}
		} else {
			$updated = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => $title,
					'post_excerpt' => $excerpt,
				),
				true
			);

			if ( is_wp_error( $updated ) ) {
				wp_die( esc_html( $updated->get_error_message() ) );
			}
		}

		Baton_Workflow_CPT::save_definition( (int) $post_id, $definition );

		if ( 'publish' === get_post_status( $post_id ) ) {
			Baton_Workflow_Abilities::register_workflow_ability( (int) $post_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE_SLUG,
					'action'      => 'edit',
					'workflow_id' => $post_id,
					'saved'       => '1',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Handle delete via GET (registered separately).
	 */
	public static function maybe_handle_delete(): void {
		if ( ! isset( $_GET['action'], $_GET['workflow_id'] ) || 'delete' !== $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$post_id = absint( $_GET['workflow_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'delete_workflow_' . $post_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'baton' ) );
		}

		Baton_Workflow_Abilities::unregister_workflow_ability( $post_id );
		wp_delete_post( $post_id, true );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'deleted' => '1',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Admin hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_register_script(
			'baton-admin',
			BATON_URL . 'assets/admin.js',
			array(),
			BATON_VERSION,
			true
		);

		wp_register_style(
			'baton-admin',
			BATON_URL . 'assets/admin.css',
			array(),
			BATON_VERSION
		);

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $action, array( 'edit', 'new' ), true ) ) {
			wp_enqueue_script( 'baton-admin' );
			wp_enqueue_style( 'baton-admin' );
		}

		wp_localize_script(
			'baton-admin',
			'Baton',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'strings'  => array(
					'step'                 => __( 'Step', 'baton' ),
					'ability'              => __( 'Ability', 'baton' ),
					'selectAbility'        => __( 'Select an ability…', 'baton' ),
					'staticInput'          => __( 'Static input (JSON)', 'baton' ),
					'usePreviousOutput'    => __( 'Use previous step output as input', 'baton' ),
					'moveUp'               => __( 'Move up', 'baton' ),
					'moveDown'             => __( 'Move down', 'baton' ),
					'removeStep'           => __( 'Remove step', 'baton' ),
					'inputSchema'          => __( 'Input schema', 'baton' ),
					'outputSchema'         => __( 'Output schema', 'baton' ),
					'running'              => __( 'Running workflow…', 'baton' ),
					'runSuccess'           => __( 'Workflow completed successfully.', 'baton' ),
					'runFailed'            => __( 'Workflow failed.', 'baton' ),
					'invalidJson'          => __( 'Invalid JSON.', 'baton' ),
					'noSteps'              => __( 'Add at least one step before running.', 'baton' ),
					'fieldMappings'        => __( 'Field mappings', 'baton' ),
					'fieldMappingsHelp'    => __( 'Map a value from the previous step (or workflow input on step 1) into a specific input field. Static JSON is merged last and overrides mapped values.', 'baton' ),
					'scalarMappings'       => __( 'Scalar input mapping', 'baton' ),
					'scalarMappingsHelp'   => __( 'This ability expects a single value (e.g. an integer). Map a source path to pass that value as the entire input. Static input overrides the mapped value.', 'baton' ),
					'scalarPathPlaceholder' => __( 'e.g. 0.plan_id', 'baton' ),
					'scalarStaticHelp'     => __( 'Optional override (e.g. 142). Leave empty to use the mapped path only.', 'baton' ),
					'sourceColumn'         => __( 'Source', 'baton' ),
					'sourcePrevious'       => __( 'Previous step', 'baton' ),
					'mappingExample'       => __( 'Example: map path "id" to target "user_id" when the previous step returns a user object.', 'baton' ),
					'sourceInitial'        => __( 'Workflow input', 'baton' ),
					'sourcePath'           => __( 'Source path', 'baton' ),
					'targetField'          => __( 'Target field', 'baton' ),
					'addMapping'           => __( 'Add mapping', 'baton' ),
					'removeMapping'        => __( 'Remove', 'baton' ),
					'pathPlaceholder'      => __( 'e.g. id', 'baton' ),
					'targetPlaceholder'    => __( 'e.g. user_id', 'baton' ),
				),
			)
		);
	}

	/**
	 * AJAX: run workflow.
	 */
	public static function ajax_run_workflow(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'baton' ) ) );
		}

		$workflow_id = isset( $_POST['workflow_id'] ) ? absint( $_POST['workflow_id'] ) : 0;
		if ( $workflow_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Workflow ID is required.', 'baton' ) ) );
		}

		$post = get_post( $workflow_id );
		if ( ! $post || Baton_Workflow_CPT::POST_TYPE !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Workflow not found.', 'baton' ) ) );
		}

		$definition = Baton_Workflow_CPT::get_definition( $workflow_id );

		if ( isset( $_POST['initial_input'] ) ) {
			$initial = json_decode( wp_unslash( $_POST['initial_input'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( null === $initial && JSON_ERROR_NONE !== json_last_error() ) {
				wp_send_json_error( array( 'message' => __( 'Invalid workflow input JSON.', 'baton' ) ) );
			}
			if ( is_array( $initial ) ) {
				$definition['initial_input'] = $initial;
			}
		}

		$report = Baton_Workflow_Runner::run( $definition, $workflow_id );

		if ( $report['success'] ) {
			wp_send_json_success( $report );
		}

		wp_send_json_error( $report );
	}

	/**
	 * Edit URL for a workflow.
	 *
	 * @param int $post_id Post ID (0 for new).
	 * @return string
	 */
	public static function get_edit_url( int $post_id ): string {
		if ( $post_id <= 0 ) {
			return add_query_arg(
				array(
					'page'   => self::PAGE_SLUG,
					'action' => 'new',
				),
				admin_url( 'tools.php' )
			);
		}

		return add_query_arg(
			array(
				'page'        => self::PAGE_SLUG,
				'action'      => 'edit',
				'workflow_id' => $post_id,
			),
			admin_url( 'tools.php' )
		);
	}
}
