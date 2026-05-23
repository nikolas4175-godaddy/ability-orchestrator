<?php
/**
 * Uninstall Baton — remove workflow posts and definition meta.
 *
 * @package Baton
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$baton_workflow_post_ids = get_posts(
	array(
		'post_type'      => 'baton_workflow',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

if ( is_array( $baton_workflow_post_ids ) ) {
	foreach ( $baton_workflow_post_ids as $baton_workflow_post_id ) {
		wp_delete_post( (int) $baton_workflow_post_id, true );
	}
}
