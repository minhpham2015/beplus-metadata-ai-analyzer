<?php

use PHPUnit\Framework\TestCase;

final class UninstallSourceTest extends TestCase {
	public function test_uninstall_queries_schema_entries_including_published_draft_and_trash(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

		$this->assertMatchesRegularExpression(
			"/'post_status'\s*=>\s*array\(\s*'publish',\s*'draft',\s*'trash'/s",
			$source
		);
	}

	public function test_uninstall_removes_only_plugin_owned_dynamic_transients_from_option_stores(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

		foreach (
			array(
				'_transient_sso_search_',
				'_transient_timeout_sso_search_',
				'_transient_sso_rate_',
				'_transient_timeout_sso_rate_',
			) as $prefix
		) {
			$this->assertStringContainsString( '$wpdb->esc_like( \'' . $prefix . "' ) . '%'", $source );
		}

		$this->assertStringContainsString( '$wpdb->options', $source );
		$this->assertStringContainsString( '$wpdb->sitemeta', $source );
		$this->assertStringContainsString( 'is_multisite()', $source );
		$this->assertGreaterThanOrEqual( 2, substr_count( $source, '$wpdb->prepare(' ) );
		$this->assertStringNotContainsString( "LIKE '_transient_%'", $source );
	}

	public function test_uninstall_keeps_legacy_sitemap_transient_and_plugin_owned_cleanup(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

		$this->assertStringContainsString( "delete_transient( 'sso_sitemap_xml' );", $source );
		foreach ( array( 'sso_settings', 'sso_version', 'sso_llms_custom_content' ) as $option ) {
			$this->assertStringContainsString( "delete_option( '" . $option . "' );", $source );
			$this->assertStringContainsString( "delete_site_option( '" . $option . "' );", $source );
		}
		foreach ( array( '_sso_meta_title', '_sso_schema_video', '_sso_schema_target_mode', '_sso_seo_score_calculated' ) as $meta_key ) {
			$this->assertStringContainsString( "'" . $meta_key . "'", $source );
		}
	}
}