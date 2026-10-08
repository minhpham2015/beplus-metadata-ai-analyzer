<?php

use PHPUnit\Framework\TestCase;

final class MetaBoxSanitizeTest extends TestCase {
	private function sanitize( $value, $type = 'text' ) {
		$m = new ReflectionMethod( SSO_Meta_Box::class, 'sanitize_value' );
		$m->setAccessible( true );
		return $m->invoke( null, $value, $type );
	}

	public function test_scalars_are_sanitized_by_type(): void {
		$this->assertSame( 'hi', $this->sanitize( ' <b>hi</b> ' ) );
		$this->assertSame( 'https://a.test/x', $this->sanitize( ' https://a.test/x ', 'url' ) );
		$this->assertSame( 'line', $this->sanitize( '<i>line</i>', 'textarea' ) );
	}

	public function test_tampered_nested_arrays_become_empty_strings(): void {
		$this->assertSame( '', $this->sanitize( array( 'x' ) ) );
		$this->assertSame( '', $this->sanitize( array( 'x' ), 'url' ) );
	}
}
