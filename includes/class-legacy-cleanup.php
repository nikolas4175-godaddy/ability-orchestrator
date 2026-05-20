<?php
/**
 * Removes legacy Ability Workflows prototype data.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes posts from the old ability_workflow CPT.
 */
final class Baton_Legacy_Cleanup {

	public const LEGACY_POST_TYPE = 'ability_workflow';

	public const LEGACY_META_KEY = '_ability_workflow_definition';

	/**
	 * Force-delete all legacy workflow posts and orphan meta.
	 */
	public static function delete_legacy_workflows(): void {
		$ids = get_posts(
			array(
				'post_type'      => self::LEGACY_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}

		self::delete_orphan_meta();
	}

	/**
	 * Remove orphan legacy meta rows.
	 */
	private static function delete_orphan_meta(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::LEGACY_META_KEY
			)
		);
	}
}
