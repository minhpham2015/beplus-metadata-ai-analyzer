<?php

use PHPUnit\Framework\TestCase;

final class SourceRegressionTest extends TestCase {
	public function test_plugin_support_url_is_contact_page_and_legacy_support_path_is_absent(): void {
		$root   = dirname( __DIR__ );
		$header = file_get_contents( $root . '/beplus-metadata-ai-analyzer.php' );

		$this->assertStringContainsString( 'Support URI:      https://beplusthemes.com/contact/', $header );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			$path = $file->getPathname();
			if ( __FILE__ === $path || false !== strpos( $path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR ) || ! preg_match( '/\.(?:php|txt|md|pot)$/', $path ) ) {
				continue;
			}
			$this->assertStringNotContainsString( 'https://beplusthemes.com/' . 'support/', file_get_contents( $path ), $path );
		}
	}
}