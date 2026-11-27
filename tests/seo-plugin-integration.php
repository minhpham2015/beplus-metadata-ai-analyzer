<?php
/**
 * Integration check run inside WordPress with one supported SEO plugin active.
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

$expected_plugin = getenv( 'SSO_EXPECTED_SEO_PLUGIN' );
$active_plugin   = class_exists( 'SSO_Compat' ) ? SSO_Compat::active_seo_plugin() : '';

if ( ! $expected_plugin || $expected_plugin !== $active_plugin ) {
	fwrite( STDERR, sprintf( "Expected %s; Beplus detected %s.\n", $expected_plugin ?: '(unset)', $active_plugin ?: '(none)' ) );
	exit( 1 );
}

if ( ! SSO_Compat::should_defer() ) {
	fwrite( STDERR, sprintf( "Beplus did not defer to %s by default.\n", $expected_plugin ) );
	exit( 1 );
}

$post_id = wp_insert_post(
	array(
		'post_title'   => 'P1 SEO compatibility integration test',
		'post_name'    => 'p1-seo-compatibility-integration-test',
		'post_content' => 'Published content used to verify third-party SEO metadata output.',
		'post_status'  => 'publish',
		'post_type'    => 'post',
	)
);

if ( is_wp_error( $post_id ) || ! $post_id ) {
	fwrite( STDERR, "Could not create the temporary integration-test post.\n" );
	exit( 1 );
}

update_post_meta( $post_id, '_sso_meta_title', 'Beplus integration title must not be used' );
update_post_meta( $post_id, '_sso_meta_description', 'Beplus integration description must not be used.' );
update_post_meta( $post_id, '_sso_schema_type', 'Article' );
update_post_meta( $post_id, '_sso_schema_headline', 'Beplus integration schema must not be used' );

$query = new WP_Query(
	array(
		'p'         => $post_id,
		'post_type' => 'post',
	)
);

if ( ! $query->have_posts() ) {
	fwrite( STDERR, "Could not query the temporary integration-test post.\n" );
	exit( 1 );
}

$GLOBALS['wp_query']     = $query;
$GLOBALS['wp_the_query'] = $query;
$query->the_post();

$meta_box = SSO_Meta_Box::instance();
if ( 'Third-party SEO title should remain' !== $meta_box->filter_document_title( 'Third-party SEO title should remain' ) ) {
	fwrite( STDERR, sprintf( "Beplus overrode the title provided by %s.\n", $expected_plugin ) );
	exit( 1 );
}

ob_start();
do_action( 'wp_head' );
$head = (string) ob_get_clean();

$beplus_only_values = array(
	'Beplus integration title must not be used',
	'Beplus integration description must not be used.',
	'Beplus integration schema must not be used',
);
foreach ( $beplus_only_values as $value ) {
	if ( false !== strpos( $head, $value ) ) {
		fwrite( STDERR, sprintf( "Beplus SEO value leaked into wp_head while %s was active.\n", $expected_plugin ) );
		exit( 1 );
	}
}

$expected_tags = array(
	'canonical'   => '/<link\s+rel=["\']canonical["\']/i',
	'description' => '/<meta\s+name=["\']description["\']/i',
	'Open Graph'  => '/<meta\s+property=["\']og:title["\']/i',
);

foreach ( $expected_tags as $label => $pattern ) {
	$tag_count = preg_match_all( $pattern, $head );
	if ( 1 !== $tag_count ) {
		fwrite( STDERR, sprintf( "Expected exactly one %s tag from %s in wp_head output; found %d.\n", $label, $expected_plugin, $tag_count ) );
		exit( 1 );
	}
}

if ( 1 > preg_match_all( '/<script\s+type=["\']application\/ld\+json["\']/i', $head ) ) {
	fwrite( STDERR, sprintf( "Expected structured data output from %s.\n", $expected_plugin ) );
	exit( 1 );
}

wp_reset_postdata();
wp_delete_post( $post_id, true );

fwrite( STDOUT, sprintf( "WordPress + %s compatibility integration passed.\n", $expected_plugin ) );
