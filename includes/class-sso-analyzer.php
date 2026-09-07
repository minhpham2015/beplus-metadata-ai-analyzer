<?php
/**
 * Focus keyword analyzer configuration.
 *
 * The actual analysis (keyword-in-title, density, etc.) runs entirely client-side
 * in admin/js/admin-script.js so editors get instant feedback without an AJAX
 * round-trip. This class only supplies the thresholds and labels the JS needs,
 * localized onto the script by SSO_Meta_Box.
 *
 * @package Beplus_Metadata_AI_Analyzer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Analyzer
 */
class SSO_Analyzer {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Analyzer|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Analyzer
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Nothing to hook here, config is pulled on demand by SSO_Meta_Box.
	 */
	private function __construct() {}

	/**
	 * Get the thresholds and translated labels used by the client-side analyzer.
	 *
	 * @return array
	 */
	public function get_config() {
		return array(
			'titleMin'   => 30,
			'titleMax'   => 60,
			'descMin'    => 120,
			'descMax'    => 160,
			'densityMin' => 0.5,
			'densityMax' => 2.5,
			'labels'     => array(
				'keywordMissing'        => __( 'Enter a focus keyword to run the SEO analysis.', 'beplus-metadata-ai-analyzer' ),
				'keywordInTitle'        => __( 'Focus keyword found in the SEO title', 'beplus-metadata-ai-analyzer' ),
				'keywordNotInTitle'     => __( 'Focus keyword is missing from the SEO title', 'beplus-metadata-ai-analyzer' ),
				'keywordInDesc'         => __( 'Focus keyword found in the meta description', 'beplus-metadata-ai-analyzer' ),
				'keywordNotInDesc'      => __( 'Focus keyword is missing from the meta description', 'beplus-metadata-ai-analyzer' ),
				'keywordInSlug'         => __( 'Focus keyword found in the URL slug', 'beplus-metadata-ai-analyzer' ),
				'keywordNotInSlug'      => __( 'Focus keyword is missing from the URL slug', 'beplus-metadata-ai-analyzer' ),
				'keywordInHeading'      => __( 'Focus keyword found in a heading', 'beplus-metadata-ai-analyzer' ),
				'keywordNotInHeading'   => __( 'Focus keyword is missing from any heading', 'beplus-metadata-ai-analyzer' ),
				'keywordInFirstPara'    => __( 'Focus keyword found in the first paragraph', 'beplus-metadata-ai-analyzer' ),
				'keywordNotInFirstPara' => __( 'Focus keyword is missing from the first paragraph', 'beplus-metadata-ai-analyzer' ),
				'densityGood'           => __( 'Keyword density is within a healthy range', 'beplus-metadata-ai-analyzer' ),
				'densityBad'            => __( 'Keyword density is too low or too high', 'beplus-metadata-ai-analyzer' ),
				'titleLengthGood'       => __( 'SEO title length is good', 'beplus-metadata-ai-analyzer' ),
				'titleLengthBad'        => __( 'SEO title length should be between 30 and 60 characters', 'beplus-metadata-ai-analyzer' ),
				'descLengthGood'        => __( 'Meta description length is good', 'beplus-metadata-ai-analyzer' ),
				'descLengthBad'         => __( 'Meta description length should be between 120 and 160 characters', 'beplus-metadata-ai-analyzer' ),
			),
		);
	}
}
