<?php
/**
 * Uninstall handler: removes every option and post meta key created by the
 * plugin so nothing is left behind in the database.
 *
 * WordPress only executes this file when the plugin is deleted from the
 * Plugins screen (not on simple deactivation).
 *
 * @package Beplus_Smart_SEO_Optimizer
 */

// Bail out if this file is accessed directly instead of through WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove plugin options.
delete_option( 'sso_settings' );
delete_site_option( 'sso_settings' ); // In case the plugin was network-activated.
delete_option( 'sso_version' );
delete_site_option( 'sso_version' );
delete_option( 'sso_llms_custom_content' );
delete_site_option( 'sso_llms_custom_content' );

// Remove transients created by the plugin.
delete_transient( 'sso_sitemap_xml' );
delete_transient( 'sso_google_ping_last' );

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
	'_sso_seo_score',
	'_sso_seo_score_calculated',
);

foreach ( $meta_keys as $meta_key ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off cleanup on uninstall, no caching layer needed.
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $meta_key ) );
}

// Flush any rewrite rules left behind for the virtual /sitemap.xml endpoint.
flush_rewrite_rules();
