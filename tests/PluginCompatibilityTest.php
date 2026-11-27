<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'WPSEO_VERSION' ) ) {
	define( 'WPSEO_VERSION', 'test' );
}

/**
 * Checks that Beplus yields conflicting SEO output to another SEO plugin.
 */
final class PluginCompatibilityTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['sso_test']['filters']     = array();
		$GLOBALS['sso_test']['is_singular'] = true;
		$GLOBALS['sso_test']['queried_id']  = 321;
		$GLOBALS['sso_test']['meta']        = array(
			321 => array( '_sso_meta_title' => 'Beplus custom title' ),
		);
		$GLOBALS['sso_test']['actions'] = array();
		$GLOBALS['sso_test']['removed_actions'] = array();
	}

	private function instance_without_constructor( $class ) {
		return ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
	}

	public function test_detected_seo_plugin_receives_priority_by_default(): void {
		$this->assertSame( 'Yoast SEO', SSO_Compat::active_seo_plugin() );
		$this->assertTrue( SSO_Compat::should_defer() );
	}

	public function test_custom_title_filters_preserve_other_plugin_title_by_default(): void {
		$meta_box = $this->instance_without_constructor( SSO_Meta_Box::class );

		$this->assertSame( 'Title from Yoast', $meta_box->filter_document_title( 'Title from Yoast' ) );
		$this->assertSame( 'Legacy title from Yoast', $meta_box->filter_wp_title( 'Legacy title from Yoast' ) );
	}

	public function test_filter_can_explicitly_allow_beplus_title_output(): void {
		$GLOBALS['sso_test']['filters']['sso_defer_to_other_seo_plugin'] = false;
		$meta_box = $this->instance_without_constructor( SSO_Meta_Box::class );

		$this->assertSame( 'Beplus custom title', $meta_box->filter_document_title( 'Title from Yoast' ) );
		$this->assertSame( 'Beplus custom title', $meta_box->filter_wp_title( 'Legacy title from Yoast' ) );
	}

	public function test_head_metadata_and_schema_emit_nothing_when_deferred(): void {
		$meta_box  = $this->instance_without_constructor( SSO_Meta_Box::class );
		$open_graph = $this->instance_without_constructor( SSO_OpenGraph::class );
		$schema     = $this->instance_without_constructor( SSO_Schema::class );

		ob_start();
		$meta_box->output_meta_description();
		$open_graph->output();
		$schema->output_global_schema();
		$schema->output_post_schema();
		$schema->output_breadcrumb_schema();
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	public function test_canonical_module_leaves_core_canonical_hook_intact_when_deferred(): void {
		$reflection = new ReflectionClass( SSO_Canonical::class );
		$canonical  = $reflection->newInstanceWithoutConstructor();
		$constructor = $reflection->getConstructor();
		$constructor->setAccessible( true );
		$constructor->invoke( $canonical );

		$this->assertSame( array(), $GLOBALS['sso_test']['actions'] );
		$this->assertSame( array(), $GLOBALS['sso_test']['removed_actions'] );
	}
}
