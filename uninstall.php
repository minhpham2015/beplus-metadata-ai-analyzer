<?php
/**
 * Uninstall handler: removes every option and post meta key created by the
 * plugin so nothing is left behind in the database.
 *
 * WordPress only executes this file when the plugin is deleted from the
 * Plugins screen (not on simple deactivation).
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

// Bail out if this file is accessed directly instead of through WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'sso_settings' );
delete_site_option( 'sso_settings' ); // In case the plugin was network-activated.
delete_option( 'sso_version' );
delete_site_option( 'sso_version' );
delete_option( 'sso_llms_custom_content' );
delete_site_option( 'sso_llms_custom_content' );

// Remove transients created by the plugin. The sitemap caches the shared URL
// list plus one XML transient per page (`sso_sitemap_xml_{N}`, N = 0 for the
// index / single sitemap, 1..MAX_CACHED_PAGES for chunks).
delete_transient( 'sso_sitemap_urls' );
delete_transient( 'sso_google_ping_last' );
// Pre-pagination releases used this un-suffixed sitemap cache key.
delete_transient( 'sso_sitemap_xml' );
for ( $sso_page = 0; $sso_page <= 500; $sso_page++ ) {
	delete_transient( 'sso_sitemap_xml_' . $sso_page );
}

// Search-result and rate-limit transient names contain dynamic user/query hashes,
// so remove only rows beginning with the plugin-owned prefixes.
global $wpdb;
$sso_transient_patterns = array(
	$wpdb->esc_like( '_transient_sso_search_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_sso_search_' ) . '%',
	$wpdb->esc_like( '_transient_sso_rate_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_sso_rate_' ) . '%',
);
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must remove dynamic option names.
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$sso_transient_patterns[0],
		$sso_transient_patterns[1],
		$sso_transient_patterns[2],
		$sso_transient_patterns[3]
	)
);

if ( is_multisite() ) {
	$sso_site_transient_patterns = array(
		$wpdb->esc_like( '_site_transient_sso_search_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_sso_search_' ) . '%',
		$wpdb->esc_like( '_site_transient_sso_rate_' ) . '%',
		$wpdb->esc_like( '_site_transient_timeout_sso_rate_' ) . '%',
	);
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must remove dynamic site-option names.
		$wpdb->prepare(
			"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s",
			$sso_site_transient_patterns[0],
			$sso_site_transient_patterns[1],
			$sso_site_transient_patterns[2],
			$sso_site_transient_patterns[3]
		)
	);
}

// Remove every post meta key the plugin ever writes.
$meta_keys = array(
	'_sso_meta_title',
	'_sso_meta_description',
	'_sso_focus_keyword',
	'_sso_canonical_url',
	'_sso_noindex',
	'_sso_nofollow',
	'_sso_sitemap_exclude',
	'_sso_og_image',
	'_sso_og_title',
	'_sso_og_description',
	'_sso_schema_type',
	'_sso_schema_headline',
	'_sso_schema_author',
	'_sso_schema_faq',
	'_sso_schema_local_business',
	'_sso_schema_howto',
	'_sso_schema_event',
	'_sso_schema_video',
	'_sso_schema_recipe',
	'_sso_schema_job',
	'_sso_schema_course',
	'_sso_schema_review',
	// "Schemas" CPT assignment keys.
	'_sso_schema_target_mode',
	'_sso_schema_target_posts',
	'_sso_schema_target_post_type',
	'_sso_seo_score',
	'_sso_seo_score_calculated',
);

foreach ( $meta_keys as $meta_key ) {
	delete_post_meta_by_key( $meta_key );
}

// Remove every "Schemas" CPT entry (any status, including trash) with its meta.
$sso_schema_entries = get_posts(
	array(
		'post_type'      => 'sso_schema',
		'post_status'    => array( 'publish', 'draft', 'trash', 'pending', 'private', 'future', 'auto-draft', 'inherit' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $sso_schema_entries as $sso_schema_entry_id ) {
	wp_delete_post( $sso_schema_entry_id, true );
}

// Flush any rewrite rules left behind for the virtual /sitemap.xml endpoint.
flush_rewrite_rules();
