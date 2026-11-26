<?php

use PHPUnit\Framework\TestCase;

/**
 * Verifies machine-readable SEO output contracts independently of WordPress boot.
 */
final class SeoOutputContractTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['sso_test']['options'] = array();
		$GLOBALS['sso_test']['meta']    = array();
	}

	private function private_method( $class, $method ) {
		$reflection = new ReflectionClass( $class );
		$instance   = $reflection->newInstanceWithoutConstructor();
		$callable   = $reflection->getMethod( $method );
		$callable->setAccessible( true );

		return array( $instance, $callable );
	}

	public function test_sitemap_urlset_is_well_formed_xml_with_expected_namespace_and_urls(): void {
		list( $instance, $method ) = $this->private_method( SSO_Sitemap::class, 'build_urlset_xml' );
		$xml                       = $method->invoke( $instance, array(
			array(
				'loc'      => 'https://example.test/a?x=1&y=2',
				'lastmod'  => '2026-01-02',
				'priority' => '0.7',
			),
		) );

		$document = new DOMDocument();
		$this->assertTrue( $document->loadXML( $xml ), 'Sitemap must parse as XML.' );
		$this->assertSame( 'urlset', $document->documentElement->localName );
		$this->assertSame( 'http://www.sitemaps.org/schemas/sitemap/0.9', $document->documentElement->namespaceURI );
		$this->assertSame( 'https://example.test/a?x=1&y=2', $document->getElementsByTagNameNS( $document->documentElement->namespaceURI, 'loc' )->item( 0 )->textContent );
	}

	public function test_robots_txt_has_one_plugin_sitemap_reference_when_enabled(): void {
		$GLOBALS['sso_test']['options']['sso_settings'] = array( 'sitemap' => array( 'enabled' => 1 ) );
		list( $instance, $method ) = $this->private_method( SSO_Sitemap::class, 'filter_robots_txt' );
		$output                    = $method->invoke( $instance, "User-agent: *\nSitemap: https://example.test/wp-sitemap.xml\n" );

		$this->assertSame( 1, preg_match_all( '/^Sitemap:/mi', $output ) );
		$this->assertStringContainsString( 'Sitemap: https://example.test/sitemap.xml', $output );
		$this->assertStringNotContainsString( 'wp-sitemap.xml', $output );
	}

	public function test_sitemap_disable_preserves_existing_robots_directives_and_core_sitemap(): void {
		$GLOBALS['sso_test']['options']['sso_settings'] = array( 'sitemap' => array( 'enabled' => 0 ) );
		list( $instance, $method ) = $this->private_method( SSO_Sitemap::class, 'filter_robots_txt' );
		$robots                    = "User-agent: *\nSitemap: https://example.test/wp-sitemap.xml\n";

		$this->assertSame( $robots, $method->invoke( $instance, $robots ) );
		$this->assertTrue( $instance->maybe_disable_core_sitemap( true ) );
	}

	public function test_article_schema_encodes_as_valid_json_ld_with_required_article_fields(): void {
		$schema_reflection = new ReflectionClass( SSO_Schema::class );
		$schema            = $schema_reflection->newInstanceWithoutConstructor();
		$data   = $schema->build_article_schema( 12 );
		$json   = wp_json_encode( $data );
		$decoded = json_decode( $json, true );

		$this->assertSame( JSON_ERROR_NONE, json_last_error() );
		$this->assertSame( 'Article', $decoded['@type'] );
		$this->assertSame( 'Test Article', $decoded['headline'] );
		$this->assertSame( 'Person', $decoded['author']['@type'] );
		$this->assertSame( 'Organization', $decoded['publisher']['@type'] );
		$this->assertArrayHasKey( '@id', $decoded['mainEntityOfPage'] );
	}
}
