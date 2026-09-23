<?php

use PHPUnit\Framework\TestCase;

final class SchemaCptAjaxTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['sso_test']['user_id']       = 7;
		$GLOBALS['sso_test']['can_edit']      = true;
		$GLOBALS['sso_test']['nonce_checked'] = false;
		$GLOBALS['sso_test']['posts_calls']   = 0;
		$GLOBALS['sso_test']['cache']         = array();
		$GLOBALS['sso_test']['now']           = 1000;
		$_GET                                = array( 'q' => 'alpha' );
	}

	private function request() {
		try {
			SSO_Schema_CPT::instance()->ajax_search_target_posts();
		} catch ( SSO_Test_Json_Response $response ) {
			return $response;
		}
		$this->fail( 'AJAX handler did not send JSON.' );
	}

	public function test_repeated_search_uses_per_user_cache(): void {
		$first  = $this->request();
		$second = $this->request();

		$this->assertTrue( $first->success );
		$this->assertSame( $first->data, $second->data );
		$this->assertSame( 1, $GLOBALS['sso_test']['posts_calls'] );
	}

	public function test_cache_is_scoped_per_user(): void {
		$this->request();
		$GLOBALS['sso_test']['user_id'] = 8;
		$this->request();
		$this->assertSame( 2, $GLOBALS['sso_test']['posts_calls'] );
	}

	public function test_rate_limit_rejects_excess_uncached_searches(): void {
		$response = null;
		for ( $i = 0; $i < 11; $i++ ) {
			$_GET['q'] = 'term-' . $i;
			$response   = $this->request();
		}
		$this->assertFalse( $response->success );
		$this->assertSame( 429, $response->status );
	}

	public function test_nonce_and_capability_checks_still_run_before_rate_limiting(): void {
		$GLOBALS['sso_test']['can_edit'] = false;
		$response = $this->request();
		$this->assertTrue( $GLOBALS['sso_test']['nonce_checked'] );
		$this->assertFalse( $response->success );
		$this->assertSame( 403, $response->status );
	}

	public function test_assign_search_explains_two_character_minimum(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/class-sso-schema-cpt.php' );
		$this->assertStringContainsString( 'Enter at least 2 characters.', $source );
	}
}
