<?php

use PHPUnit\Framework\TestCase;

/**
 * Covers the 2-tier schema resolution (per-post override > Schemas CPT entry)
 * and the nested value swap used for non-singular views.
 */
final class SchemaResolveTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['sso_test']['posts_result'] = null;
		$GLOBALS['sso_test']['meta']         = array();
	}

	private function call_private( $method, ...$args ) {
		$ref    = new ReflectionClass( SSO_Schema::class );
		$schema = $ref->newInstanceWithoutConstructor();
		$m      = $ref->getMethod( $method );
		$m->setAccessible( true );
		return $m->invoke( $schema, ...$args );
	}

	public function test_per_post_override_wins(): void {
		$GLOBALS['sso_test']['meta'][101] = array( '_sso_schema_type' => 'Article' );
		$this->assertSame(
			array( 'enabled' => true, 'type' => 'Article', 'entry_id' => 0 ),
			$this->call_private( 'resolve_post_schema_type', 101 )
		);
	}

	public function test_none_override_disables_schema_even_with_an_entry(): void {
		$GLOBALS['sso_test']['posts_result'] = array( 900 );
		$GLOBALS['sso_test']['meta'][102]    = array( '_sso_schema_type' => 'none' );
		$resolved                            = $this->call_private( 'resolve_post_schema_type', 102 );
		$this->assertFalse( $resolved['enabled'] );
		$this->assertSame( 0, $resolved['entry_id'] );
	}

	public function test_assigned_schemas_entry_is_used_when_no_override(): void {
		$GLOBALS['sso_test']['posts_result'] = array( 900 );
		$GLOBALS['sso_test']['meta'][900]    = array(
			'_sso_schema_target_posts' => array( 103 ),
			'_sso_schema_type'         => 'FAQPage',
		);
		$this->assertSame(
			array( 'enabled' => true, 'type' => 'FAQPage', 'entry_id' => 900 ),
			$this->call_private( 'resolve_post_schema_type', 103 )
		);
	}

	public function test_no_override_and_no_entry_means_disabled(): void {
		$GLOBALS['sso_test']['posts_result'] = array();
		$resolved                            = $this->call_private( 'resolve_post_schema_type', 104 );
		$this->assertFalse( $resolved['enabled'] );
	}

	public function test_replace_recursive_swaps_deeply_nested_values_only(): void {
		$data = array(
			'url'    => 'https://x.test/?sso_schema=entry',
			'name'   => 'Entry title',
			'offers' => array( 'url' => 'https://x.test/?sso_schema=entry', 'price' => '10' ),
			'main'   => array( '@id' => 'https://x.test/?sso_schema=entry#a', 'deep' => array( 'name' => 'Entry title' ) ),
			'empty'  => '',
			'count'  => 5,
		);
		$out  = $this->call_private(
			'replace_recursive',
			$data,
			array(
				'https://x.test/?sso_schema=entry' => 'https://x.test/',
				'Entry title'                      => 'My Site',
			)
		);
		$this->assertSame( 'https://x.test/', $out['url'] );
		$this->assertSame( 'https://x.test/', $out['offers']['url'] );
		$this->assertSame( 'My Site', $out['main']['deep']['name'] );
		// Only whole-value matches are swapped; a value that merely contains the permalink is kept.
		$this->assertSame( 'https://x.test/?sso_schema=entry#a', $out['main']['@id'] );
		$this->assertSame( '10', $out['offers']['price'] );
		$this->assertSame( 5, $out['count'] );
	}
}
