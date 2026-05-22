<?php
/**
 * Uninstall Baton — remove workflow posts and definition meta.
 *
 * @package Baton
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$post_ids = get_posts(
	array(
		'post_type'      => 'baton_workflow',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

if ( is_array( $post_ids ) ) {
	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
}
