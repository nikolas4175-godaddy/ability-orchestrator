<?php
/**
 * Workflows list table.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for ability workflows.
 */
class Baton_Workflow_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'workflow',
				'plural'   => 'workflows',
				'ajax'     => false,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_columns(): array {
		return array(
			'title'       => __( 'Workflow', 'baton' ),
			'steps'       => __( 'Steps', 'baton' ),
			'modified'    => __( 'Last Modified', 'baton' ),
			'actions'     => __( 'Actions', 'baton' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_sortable_columns(): array {
		return array(
			'title'    => array( 'title', false ),
			'modified' => array( 'modified', true ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function prepare_items(): void {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'modified'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$allowed_orderby = array( 'title', 'modified' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'modified';
		}

		$order = 'asc' === strtolower( $order ) ? 'ASC' : 'DESC';

		$query_args = array(
			'post_type'      => Baton_Workflow_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => $orderby,
			'order'          => $order,
		);

		if ( ! empty( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$query_args['s'] = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$query = new WP_Query( $query_args );

		$this->items = $query->posts;

		$this->set_pagination_args(
			array(
				'total_items' => (int) $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => (int) $query->max_num_pages,
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Default column handler.
	 *
	 * @param WP_Post $item        Post item.
	 * @param string  $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ): string {
		return '';
	}

	/**
	 * Title column.
	 *
	 * @param WP_Post $item Post item.
	 * @return string
	 */
	protected function column_title( WP_Post $item ): string {
		$edit_url = Baton_Admin::get_edit_url( (int) $item->ID );

		$title = sprintf(
			'<strong><a href="%s">%s</a></strong>',
			esc_url( $edit_url ),
			esc_html( $item->post_title )
		);

		if ( ! empty( $item->post_excerpt ) ) {
			$title .= '<br><span class="description">' . esc_html( $item->post_excerpt ) . '</span>';
		}

		return $title;
	}

	/**
	 * Steps column.
	 *
	 * @param WP_Post $item Post item.
	 * @return string
	 */
	protected function column_steps( WP_Post $item ): string {
		$definition = Baton_Workflow_CPT::get_definition( (int) $item->ID );
		$count      = count( $definition['steps'] ?? array() );

		return esc_html( (string) $count );
	}

	/**
	 * Modified column.
	 *
	 * @param WP_Post $item Post item.
	 * @return string
	 */
	protected function column_modified( WP_Post $item ): string {
		return esc_html( get_the_modified_date( '', $item ) );
	}

	/**
	 * Actions column.
	 *
	 * @param WP_Post $item Post item.
	 * @return string
	 */
	protected function column_actions( WP_Post $item ): string {
		$edit_url = Baton_Admin::get_edit_url( (int) $item->ID );
		$run_url  = $edit_url . '#aw-run-panel';

		return sprintf(
			'<a href="%1$s" class="button button-small">%2$s</a> <a href="%3$s" class="button button-small button-primary">%4$s</a>',
			esc_url( $edit_url ),
			esc_html__( 'Edit', 'baton' ),
			esc_url( $run_url ),
			esc_html__( 'Run', 'baton' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function no_items(): void {
		esc_html_e( 'No workflows yet. Create one to chain abilities together.', 'baton' );
	}
}
