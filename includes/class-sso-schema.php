<?php
/**
 * Schema.org structured data (JSON-LD): a site-wide Organization/Person + WebSite
 * graph, plus per-post-type Article / Product / FAQPage / LocalBusiness markup.
 *
 * @package Beplus_Smart_SEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Schema
 */
class SSO_Schema {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Schema|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Schema
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'wp_footer', array( $this, 'output_global_schema' ) );
		add_action( 'wp_head', array( $this, 'output_post_schema' ), 20 );
	}

	/* ------------------------------------------------------------------ *
	 * Global schema: Organization/Person + WebSite (with SearchAction)
	 * ------------------------------------------------------------------ */

	/**
	 * Build the Organization or Person schema node from Settings.
	 *
	 * @return array
	 */
	public function build_organization_schema() {
		$schema = SSO_Settings::get( 'schema' );
		$type   = ( isset( $schema['entity_type'] ) && 'person' === $schema['entity_type'] ) ? 'Person' : 'Organization';

		$data = array(
			'@type' => $type,
			'@id'   => home_url( '/#' . strtolower( $type ) ),
			'name'  => ! empty( $schema['name'] ) ? $schema['name'] : get_bloginfo( 'name' ),
			'url'   => ! empty( $schema['url'] ) ? $schema['url'] : home_url( '/' ),
		);

		if ( ! empty( $schema['logo'] ) ) {
			$logo_url = wp_get_attachment_image_url( (int) $schema['logo'], 'medium' );
			if ( $logo_url ) {
				$data['logo'] = array(
					'@type' => 'ImageObject',
					'url'   => $logo_url,
				);
				if ( 'Organization' === $type ) {
					$data['image'] = $logo_url;
				}
			}
		}

		if ( ! empty( $schema['sameas'] ) && is_array( $schema['sameas'] ) ) {
			$sameas = array_values( array_filter( $schema['sameas'] ) );
			if ( $sameas ) {
				$data['sameAs'] = $sameas;
			}
		}

		return $data;
	}

	/**
	 * Build the WebSite schema node, including the sitelinks SearchAction.
	 *
	 * @return array
	 */
	public function build_website_schema() {
		return array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'name'            => get_bloginfo( 'name' ),
			'url'             => home_url( '/' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => home_url( '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Output the site-wide Organization/Person + WebSite graph once per page, via wp_footer.
	 */
	public function output_global_schema() {
		if ( is_admin() ) {
			return;
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				$this->build_organization_schema(),
				$this->build_website_schema(),
			),
		);

		$this->print_ld_json( $data );
	}

	/* ------------------------------------------------------------------ *
	 * Per post type schema: Article / Product / FAQPage / LocalBusiness
	 * ------------------------------------------------------------------ */

	/**
	 * Resolve the schema type to use for the current singular post, honoring
	 * the per-post override before falling back to the Settings default.
	 *
	 * @param int $post_id Post ID.
	 * @return array{enabled:bool,type:string}
	 */
	private function resolve_post_schema_type( $post_id ) {
		$post_type     = get_post_type( $post_id );
		$type_settings = SSO_Settings::get( 'schema', 'post_types', array() );
		$enabled       = ! empty( $type_settings[ $post_type ]['enabled'] );
		$schema_type   = isset( $type_settings[ $post_type ]['type'] ) ? $type_settings[ $post_type ]['type'] : '';

		$override = get_post_meta( $post_id, '_sso_schema_type', true );
		if ( $override ) {
			if ( 'none' === $override ) {
				return array(
					'enabled' => false,
					'type'    => '',
				);
			}
			return array(
				'enabled' => true,
				'type'    => $override,
			);
		}

		return array(
			'enabled' => $enabled,
			'type'    => $schema_type,
		);
	}

	/**
	 * Output the post-specific JSON-LD schema on singular views.
	 */
	public function output_post_schema() {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$post_id     = get_queried_object_id();
		$resolved    = $this->resolve_post_schema_type( $post_id );

		if ( ! $resolved['enabled'] || ! $resolved['type'] ) {
			return;
		}

		$data = null;

		switch ( $resolved['type'] ) {
			case 'article':
				$data = $this->build_article_schema( $post_id );
				break;
			case 'product':
				$data = $this->build_product_schema( $post_id );
				break;
			case 'faqpage':
				$data = $this->build_faqpage_schema( $post_id );
				break;
			case 'localbusiness':
				$data = $this->build_localbusiness_schema( $post_id );
				break;
		}

		$data = apply_filters( 'sso_schema_post_data', $data, $post_id, $resolved['type'] );

		if ( ! $data ) {
			return;
		}

		$this->print_ld_json( array_merge( array( '@context' => 'https://schema.org' ), $data ) );
	}

	/**
	 * Build an Article schema node for the given post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_article_schema( $post_id ) {
		$headline = get_post_meta( $post_id, '_sso_schema_headline', true );
		if ( ! $headline ) {
			$headline = get_the_title( $post_id );
		}

		$author_name = get_post_meta( $post_id, '_sso_schema_author', true );
		if ( ! $author_name ) {
			$author_name = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
		}

		$data = array(
			'@type'            => 'Article',
			'headline'         => wp_strip_all_tags( $headline ),
			'datePublished'    => get_the_date( 'c', $post_id ),
			'dateModified'     => get_the_modified_date( 'c', $post_id ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => $author_name,
			),
			'publisher'        => $this->build_organization_schema(),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post_id ),
			),
		);

		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		return $data;
	}

	/**
	 * Build a Product schema node. Pulls live data from WooCommerce when active.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_product_schema( $post_id ) {
		$data = array(
			'@type' => 'Product',
			'name'  => get_the_title( $post_id ),
		);

		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		$description = get_post_meta( $post_id, '_sso_meta_description', true );
		if ( ! $description ) {
			$description = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ), 30, '' );
		}
		if ( $description ) {
			$data['description'] = $description;
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );

			if ( $product ) {
				$data['sku'] = $product->get_sku() ? $product->get_sku() : (string) $post_id;

				$data['offers'] = array(
					'@type'         => 'Offer',
					'price'         => $product->get_price() ? (string) $product->get_price() : '0',
					'priceCurrency' => get_woocommerce_currency(),
					'availability'  => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
					'url'           => get_permalink( $post_id ),
				);

				$rating_count = $product->get_rating_count();
				if ( $rating_count > 0 ) {
					$data['aggregateRating'] = array(
						'@type'       => 'AggregateRating',
						'ratingValue' => $product->get_average_rating(),
						'reviewCount' => $rating_count,
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Build a FAQPage schema node from the meta box FAQ repeater.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Null when there is nothing valid to output.
	 */
	public function build_faqpage_schema( $post_id ) {
		$faq = get_post_meta( $post_id, '_sso_schema_faq', true );
		if ( empty( $faq ) || ! is_array( $faq ) ) {
			return null;
		}

		$main_entity = array();
		foreach ( $faq as $item ) {
			if ( empty( $item['question'] ) || empty( $item['answer'] ) ) {
				continue;
			}
			$main_entity[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $item['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( $item['answer'] ),
				),
			);
		}

		if ( ! $main_entity ) {
			return null;
		}

		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $main_entity,
		);
	}

	/**
	 * Build a LocalBusiness schema node from the meta box fields.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function build_localbusiness_schema( $post_id ) {
		$lb = get_post_meta( $post_id, '_sso_schema_local_business', true );
		if ( ! is_array( $lb ) ) {
			$lb = array();
		}

		$data = array(
			'@type' => 'LocalBusiness',
			'name'  => ! empty( $lb['name'] ) ? $lb['name'] : get_the_title( $post_id ),
			'url'   => get_permalink( $post_id ),
		);

		$address = array_filter(
			array(
				'streetAddress'   => isset( $lb['street'] ) ? $lb['street'] : '',
				'addressLocality' => isset( $lb['city'] ) ? $lb['city'] : '',
				'addressRegion'   => isset( $lb['region'] ) ? $lb['region'] : '',
				'postalCode'      => isset( $lb['postal'] ) ? $lb['postal'] : '',
				'addressCountry'  => isset( $lb['country'] ) ? $lb['country'] : '',
			)
		);

		if ( $address ) {
			$data['address'] = array_merge( array( '@type' => 'PostalAddress' ), $address );
		}

		if ( ! empty( $lb['phone'] ) ) {
			$data['telephone'] = $lb['phone'];
		}
		if ( ! empty( $lb['price_range'] ) ) {
			$data['priceRange'] = $lb['price_range'];
		}
		if ( has_post_thumbnail( $post_id ) ) {
			$data['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
		}

		return $data;
	}

	/* ------------------------------------------------------------------ *
	 * Output helper
	 * ------------------------------------------------------------------ */

	/**
	 * Encode and print a JSON-LD script tag, bailing out on invalid JSON so a
	 * malformed graph never reaches Google Rich Results.
	 *
	 * @param array $data Schema data.
	 */
	public function print_ld_json( $data ) {
		// Slashes are intentionally left escaped (no JSON_UNESCAPED_SLASHES) so a
		// literal "</script>" inside any string value cannot break out of the tag.
		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );

		if ( ! $json || JSON_ERROR_NONE !== json_last_error() ) {
			return;
		}

		echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() output validated above; safe JSON-LD payload.
	}
}
