<?php
/**
 * Per-post SEO meta box (General / Social / Schema tabs) and the document title /
 * meta description output that belongs to it.
 *
 * @package Beplus_Smart_SEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Meta_Box
 */
class SSO_Meta_Box {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Meta_Box|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Meta_Box
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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Document title / meta description output (feature #1 of the spec).
		add_filter( 'pre_get_document_title', array( $this, 'filter_document_title' ), 20 );
		add_action( 'wp_head', array( $this, 'output_meta_description' ), 4 );
	}

	/**
	 * Public post types this meta box applies to (attachments excluded).
	 *
	 * @return string[]
	 */
	public function get_public_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );
		return array_values( $post_types );
	}

	/**
	 * Register the meta box on every public post type edit screen.
	 */
	public function add_meta_box() {
		foreach ( $this->get_public_post_types() as $post_type ) {
			add_meta_box(
				'sso_meta_box',
				__( 'Smart SEO Google & AI', 'beplus-smart-seo-google-ai' ),
				array( $this, 'render' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Enqueue meta box assets only on the relevant post edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, $this->get_public_post_types(), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'sso-admin-style', SSO_PLUGIN_URL . 'admin/css/admin-style.css', array(), SSO_VERSION );
		wp_enqueue_script( 'sso-admin-script', SSO_PLUGIN_URL . 'admin/js/admin-script.js', array( 'jquery' ), SSO_VERSION, true );

		global $post;
		$slug = $post ? $post->post_name : '';

		wp_localize_script(
			'sso-admin-script',
			'ssoAdmin',
			array(
				'context'  => 'meta-box',
				'siteName' => get_bloginfo( 'name' ),
				'siteUrl'  => home_url( '/' ),
				'postSlug' => $slug,
				'analyzer' => SSO_Analyzer::instance()->get_config(),
				'i18n'     => array(
					'chooseImage' => __( 'Choose Image', 'beplus-smart-seo-google-ai' ),
					'useImage'    => __( 'Use this image', 'beplus-smart-seo-google-ai' ),
					'removeImage' => __( 'Remove', 'beplus-smart-seo-google-ai' ),
					'question'    => __( 'Question', 'beplus-smart-seo-google-ai' ),
					'answer'      => __( 'Answer', 'beplus-smart-seo-google-ai' ),
					'remove'      => __( 'Remove', 'beplus-smart-seo-google-ai' ),
					'scoreGood'   => __( 'Good', 'beplus-smart-seo-google-ai' ),
					'scoreOk'     => __( 'OK', 'beplus-smart-seo-google-ai' ),
					'scorePoor'   => __( 'Poor', 'beplus-smart-seo-google-ai' ),
				),
			)
		);
	}

	/**
	 * Render the meta box markup (tab nav + General / Social / Schema panels).
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render( $post ) {
		wp_nonce_field( 'sso_save_meta_box', 'sso_meta_box_nonce' );

		$meta_title       = get_post_meta( $post->ID, '_sso_meta_title', true );
		$meta_description = get_post_meta( $post->ID, '_sso_meta_description', true );
		$focus_keyword    = get_post_meta( $post->ID, '_sso_focus_keyword', true );
		$canonical_url    = get_post_meta( $post->ID, '_sso_canonical_url', true );
		$noindex          = get_post_meta( $post->ID, '_sso_noindex', true );
		$nofollow         = get_post_meta( $post->ID, '_sso_nofollow', true );
		$sitemap_exclude  = get_post_meta( $post->ID, '_sso_sitemap_exclude', true );

		$og_image_id      = get_post_meta( $post->ID, '_sso_og_image', true );
		$og_title         = get_post_meta( $post->ID, '_sso_og_title', true );
		$og_description   = get_post_meta( $post->ID, '_sso_og_description', true );
		$og_image_url     = $og_image_id ? wp_get_attachment_image_url( (int) $og_image_id, 'medium' ) : '';

		$schema_type      = get_post_meta( $post->ID, '_sso_schema_type', true );
		$schema_headline  = get_post_meta( $post->ID, '_sso_schema_headline', true );
		$schema_author    = get_post_meta( $post->ID, '_sso_schema_author', true );
		$schema_faq       = get_post_meta( $post->ID, '_sso_schema_faq', true );
		$schema_faq       = is_array( $schema_faq ) ? $schema_faq : array();
		$local_business   = get_post_meta( $post->ID, '_sso_schema_local_business', true );
		$local_business   = is_array( $local_business ) ? $local_business : array();

		$type_settings    = SSO_Settings::get( 'schema', 'post_types', array() );
		$default_type     = isset( $type_settings[ $post->post_type ]['type'] ) ? $type_settings[ $post->post_type ]['type'] : '';
		?>
		<div class="sso-meta-box">
			<h2 class="nav-tab-wrapper sso-tabs-nav">
				<a href="#sso-tab-general" class="nav-tab nav-tab-active" data-tab="general"><?php esc_html_e( 'General', 'beplus-smart-seo-google-ai' ); ?></a>
				<a href="#sso-tab-social" class="nav-tab" data-tab="social"><?php esc_html_e( 'Social', 'beplus-smart-seo-google-ai' ); ?></a>
				<a href="#sso-tab-schema" class="nav-tab" data-tab="schema"><?php esc_html_e( 'Schema', 'beplus-smart-seo-google-ai' ); ?></a>
			</h2>

			<div class="sso-tab-panel" id="sso-tab-general" data-tab-panel="general">

				<div class="sso-snippet-preview">
					<p class="sso-snippet-url"><?php echo esc_html( home_url( '/' ) ); ?><span class="sso-snippet-slug"><?php echo esc_html( $post->post_name ); ?></span></p>
					<p class="sso-snippet-title"></p>
					<p class="sso-snippet-desc"></p>
				</div>

				<p class="sso-field">
					<label for="sso_meta_title"><?php esc_html_e( 'Meta Title', 'beplus-smart-seo-google-ai' ); ?></label>
					<input type="text" id="sso_meta_title" name="sso_meta_title" value="<?php echo esc_attr( $meta_title ); ?>" class="widefat" />
					<span class="sso-char-count" data-target="sso_meta_title" data-max="60"></span>
				</p>

				<p class="sso-field">
					<label for="sso_meta_description"><?php esc_html_e( 'Meta Description', 'beplus-smart-seo-google-ai' ); ?></label>
					<textarea id="sso_meta_description" name="sso_meta_description" class="widefat" rows="3"><?php echo esc_textarea( $meta_description ); ?></textarea>
					<span class="sso-char-count" data-target="sso_meta_description" data-max="160"></span>
				</p>

				<p class="sso-field">
					<label for="sso_focus_keyword"><?php esc_html_e( 'Focus Keyword', 'beplus-smart-seo-google-ai' ); ?></label>
					<input type="text" id="sso_focus_keyword" name="sso_focus_keyword" value="<?php echo esc_attr( $focus_keyword ); ?>" class="widefat" />
				</p>

				<div class="sso-analyzer-results">
					<span class="sso-score-badge" id="sso-score-badge">&nbsp;</span>
					<ul id="sso-analyzer-list"></ul>
				</div>

				<p class="sso-field">
					<label for="sso_canonical_url"><?php esc_html_e( 'Canonical URL Override', 'beplus-smart-seo-google-ai' ); ?></label>
					<input type="url" id="sso_canonical_url" name="sso_canonical_url" value="<?php echo esc_attr( $canonical_url ); ?>" class="widefat" placeholder="<?php echo esc_attr( get_permalink( $post ) ); ?>" />
				</p>

				<p class="sso-field sso-checkboxes">
					<label><input type="checkbox" name="sso_noindex" value="1" <?php checked( '1', $noindex ); ?> /> <?php esc_html_e( 'Noindex this content', 'beplus-smart-seo-google-ai' ); ?></label>
					<label><input type="checkbox" name="sso_nofollow" value="1" <?php checked( '1', $nofollow ); ?> /> <?php esc_html_e( 'Nofollow links on this content', 'beplus-smart-seo-google-ai' ); ?></label>
					<label><input type="checkbox" name="sso_sitemap_exclude" value="1" <?php checked( '1', $sitemap_exclude ); ?> /> <?php esc_html_e( 'Exclude from XML sitemap', 'beplus-smart-seo-google-ai' ); ?></label>
				</p>
			</div>

			<div class="sso-tab-panel" id="sso-tab-social" style="display:none;" data-tab-panel="social">
				<p class="sso-field">
					<label><?php esc_html_e( 'OG Image', 'beplus-smart-seo-google-ai' ); ?></label>
				</p>
				<div class="sso-media-field">
					<input type="hidden" class="sso-media-input" name="sso_og_image" value="<?php echo esc_attr( $og_image_id ); ?>" />
					<div class="sso-media-preview">
						<?php if ( $og_image_url ) : ?>
							<img src="<?php echo esc_url( $og_image_url ); ?>" alt="" />
						<?php endif; ?>
					</div>
					<button type="button" class="button sso-media-select"><?php esc_html_e( 'Choose Image', 'beplus-smart-seo-google-ai' ); ?></button>
					<button type="button" class="button sso-media-remove" <?php echo $og_image_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'beplus-smart-seo-google-ai' ); ?></button>
					<p class="description"><?php esc_html_e( 'Falls back to the featured image, then the site default image, if left empty.', 'beplus-smart-seo-google-ai' ); ?></p>
				</div>

				<p class="sso-field">
					<label for="sso_og_title"><?php esc_html_e( 'OG Title Override', 'beplus-smart-seo-google-ai' ); ?></label>
					<input type="text" id="sso_og_title" name="sso_og_title" value="<?php echo esc_attr( $og_title ); ?>" class="widefat" />
				</p>
				<p class="sso-field">
					<label for="sso_og_description"><?php esc_html_e( 'OG Description Override', 'beplus-smart-seo-google-ai' ); ?></label>
					<textarea id="sso_og_description" name="sso_og_description" class="widefat" rows="3"><?php echo esc_textarea( $og_description ); ?></textarea>
				</p>
			</div>

			<div class="sso-tab-panel" id="sso-tab-schema" style="display:none;" data-tab-panel="schema">
				<p class="sso-field">
					<label for="sso_schema_type"><?php esc_html_e( 'Schema Type', 'beplus-smart-seo-google-ai' ); ?></label>
					<select id="sso_schema_type" name="sso_schema_type">
						<?php
						$options = array(
							''              => sprintf(
								/* translators: %s: default schema type configured in Settings. */
								__( 'Auto (Settings default: %s)', 'beplus-smart-seo-google-ai' ),
								$default_type ? $default_type : __( 'None', 'beplus-smart-seo-google-ai' )
							),
							'article'       => __( 'Article', 'beplus-smart-seo-google-ai' ),
							'product'       => __( 'Product', 'beplus-smart-seo-google-ai' ),
							'faqpage'       => __( 'FAQPage', 'beplus-smart-seo-google-ai' ),
							'localbusiness' => __( 'LocalBusiness', 'beplus-smart-seo-google-ai' ),
							'none'          => __( 'None (disable schema)', 'beplus-smart-seo-google-ai' ),
						);
						foreach ( $options as $value => $label ) :
							?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schema_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<div class="sso-schema-fields" data-schema-fields="article">
					<p class="sso-field">
						<label for="sso_schema_headline"><?php esc_html_e( 'Headline Override', 'beplus-smart-seo-google-ai' ); ?></label>
						<input type="text" id="sso_schema_headline" name="sso_schema_headline" value="<?php echo esc_attr( $schema_headline ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_schema_author"><?php esc_html_e( 'Author Name Override', 'beplus-smart-seo-google-ai' ); ?></label>
						<input type="text" id="sso_schema_author" name="sso_schema_author" value="<?php echo esc_attr( $schema_author ); ?>" class="widefat" />
					</p>
				</div>

				<div class="sso-schema-fields" data-schema-fields="faqpage" style="display:none;">
					<label><?php esc_html_e( 'FAQ Items', 'beplus-smart-seo-google-ai' ); ?></label>
					<table class="widefat sso-faq-repeater" id="sso-faq-repeater">
						<tbody>
							<?php foreach ( $schema_faq as $item ) : ?>
								<tr>
									<td><input type="text" name="sso_schema_faq_question[]" value="<?php echo esc_attr( $item['question'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Question', 'beplus-smart-seo-google-ai' ); ?>" class="widefat" /></td>
									<td><input type="text" name="sso_schema_faq_answer[]" value="<?php echo esc_attr( $item['answer'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Answer', 'beplus-smart-seo-google-ai' ); ?>" class="widefat" /></td>
									<td><button type="button" class="button sso-faq-remove">&times;</button></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<button type="button" class="button" id="sso-faq-add"><?php esc_html_e( 'Add FAQ item', 'beplus-smart-seo-google-ai' ); ?></button>
				</div>

				<div class="sso-schema-fields" data-schema-fields="localbusiness" style="display:none;">
					<p class="sso-field">
						<label for="sso_lb_name"><?php esc_html_e( 'Business Name', 'beplus-smart-seo-google-ai' ); ?></label>
						<input type="text" id="sso_lb_name" name="sso_lb_name" value="<?php echo esc_attr( $local_business['name'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_lb_street"><?php esc_html_e( 'Street Address', 'beplus-smart-seo-google-ai' ); ?></label>
						<input type="text" id="sso_lb_street" name="sso_lb_street" value="<?php echo esc_attr( $local_business['street'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field sso-field-inline">
						<span><label for="sso_lb_city"><?php esc_html_e( 'City', 'beplus-smart-seo-google-ai' ); ?></label><input type="text" id="sso_lb_city" name="sso_lb_city" value="<?php echo esc_attr( $local_business['city'] ?? '' ); ?>" /></span>
						<span><label for="sso_lb_region"><?php esc_html_e( 'Region', 'beplus-smart-seo-google-ai' ); ?></label><input type="text" id="sso_lb_region" name="sso_lb_region" value="<?php echo esc_attr( $local_business['region'] ?? '' ); ?>" /></span>
						<span><label for="sso_lb_postal"><?php esc_html_e( 'Postal Code', 'beplus-smart-seo-google-ai' ); ?></label><input type="text" id="sso_lb_postal" name="sso_lb_postal" value="<?php echo esc_attr( $local_business['postal'] ?? '' ); ?>" /></span>
					</p>
					<p class="sso-field sso-field-inline">
						<span><label for="sso_lb_country"><?php esc_html_e( 'Country', 'beplus-smart-seo-google-ai' ); ?></label><input type="text" id="sso_lb_country" name="sso_lb_country" value="<?php echo esc_attr( $local_business['country'] ?? '' ); ?>" /></span>
						<span><label for="sso_lb_phone"><?php esc_html_e( 'Phone', 'beplus-smart-seo-google-ai' ); ?></label><input type="text" id="sso_lb_phone" name="sso_lb_phone" value="<?php echo esc_attr( $local_business['phone'] ?? '' ); ?>" /></span>
						<span><label for="sso_lb_price_range"><?php esc_html_e( 'Price Range', 'beplus-smart-seo-google-ai' ); ?></label><input type="text" id="sso_lb_price_range" name="sso_lb_price_range" value="<?php echo esc_attr( $local_business['price_range'] ?? '' ); ?>" placeholder="$$" /></span>
					</p>
				</div>

				<div class="sso-schema-fields" data-schema-fields="product" style="display:none;">
					<p class="description"><?php esc_html_e( 'Product data (price, SKU, stock, rating) is pulled automatically from WooCommerce when available.', 'beplus-smart-seo-google-ai' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Persist all meta box fields.
	 *
	 * @param int $post_id Post ID being saved.
	 */
	public function save( $post_id ) {
		if ( ! isset( $_POST['sso_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sso_meta_box_nonce'] ) ), 'sso_save_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		$cap       = 'page' === $post_type ? 'edit_page' : 'edit_post';
		if ( ! current_user_can( $cap, $post_id ) ) {
			return;
		}

		// General tab.
		$this->save_text_field( $post_id, '_sso_meta_title', 'sso_meta_title' );
		$this->save_textarea_field( $post_id, '_sso_meta_description', 'sso_meta_description' );
		$this->save_text_field( $post_id, '_sso_focus_keyword', 'sso_focus_keyword' );
		$this->save_url_field( $post_id, '_sso_canonical_url', 'sso_canonical_url' );
		$this->save_checkbox( $post_id, '_sso_noindex', 'sso_noindex' );
		$this->save_checkbox( $post_id, '_sso_nofollow', 'sso_nofollow' );
		$this->save_checkbox( $post_id, '_sso_sitemap_exclude', 'sso_sitemap_exclude' );

		// Social tab.
		if ( isset( $_POST['sso_og_image'] ) ) {
			$image_id = absint( $_POST['sso_og_image'] );
			if ( $image_id ) {
				update_post_meta( $post_id, '_sso_og_image', $image_id );
			} else {
				delete_post_meta( $post_id, '_sso_og_image' );
			}
		}
		$this->save_text_field( $post_id, '_sso_og_title', 'sso_og_title' );
		$this->save_textarea_field( $post_id, '_sso_og_description', 'sso_og_description' );

		// Schema tab.
		if ( isset( $_POST['sso_schema_type'] ) ) {
			$type = sanitize_key( wp_unslash( $_POST['sso_schema_type'] ) );
			if ( $type ) {
				update_post_meta( $post_id, '_sso_schema_type', $type );
			} else {
				delete_post_meta( $post_id, '_sso_schema_type' );
			}
		}
		$this->save_text_field( $post_id, '_sso_schema_headline', 'sso_schema_headline' );
		$this->save_text_field( $post_id, '_sso_schema_author', 'sso_schema_author' );

		if ( isset( $_POST['sso_schema_faq_question'] ) && isset( $_POST['sso_schema_faq_answer'] ) ) {
			$questions = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['sso_schema_faq_question'] ) );
			$answers   = array_map( 'wp_kses_post', wp_unslash( (array) $_POST['sso_schema_faq_answer'] ) );
			$faq       = array();
			foreach ( $questions as $index => $question ) {
				$answer = isset( $answers[ $index ] ) ? $answers[ $index ] : '';
				if ( '' === trim( $question ) || '' === trim( $answer ) ) {
					continue;
				}
				$faq[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
			if ( $faq ) {
				update_post_meta( $post_id, '_sso_schema_faq', $faq );
			} else {
				delete_post_meta( $post_id, '_sso_schema_faq' );
			}
		}

		$lb_fields = array( 'name', 'street', 'city', 'region', 'postal', 'country', 'phone', 'price_range' );
		$lb_data   = array();
		foreach ( $lb_fields as $field ) {
			$post_key = 'sso_lb_' . $field;
			if ( isset( $_POST[ $post_key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
				if ( '' !== $value ) {
					$lb_data[ $field ] = $value;
				}
			}
		}
		if ( $lb_data ) {
			update_post_meta( $post_id, '_sso_schema_local_business', $lb_data );
		} else {
			delete_post_meta( $post_id, '_sso_schema_local_business' );
		}
	}

	/**
	 * Save a plain text meta field, deleting it when empty.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $post_key $_POST key.
	 */
	private function save_text_field( $post_id, $meta_key, $post_key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in save().
		if ( ! isset( $_POST[ $post_key ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in save().
		$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
		if ( '' !== $value ) {
			update_post_meta( $post_id, $meta_key, $value );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/**
	 * Save a textarea meta field, deleting it when empty.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $post_key $_POST key.
	 */
	private function save_textarea_field( $post_id, $meta_key, $post_key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in save().
		if ( ! isset( $_POST[ $post_key ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in save().
		$value = sanitize_textarea_field( wp_unslash( $_POST[ $post_key ] ) );
		if ( '' !== $value ) {
			update_post_meta( $post_id, $meta_key, $value );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/**
	 * Save a URL meta field, deleting it when empty.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $post_key $_POST key.
	 */
	private function save_url_field( $post_id, $meta_key, $post_key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in save().
		if ( ! isset( $_POST[ $post_key ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in save().
		$value = esc_url_raw( wp_unslash( $_POST[ $post_key ] ) );
		if ( '' !== $value ) {
			update_post_meta( $post_id, $meta_key, $value );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/**
	 * Save a checkbox as '1' or delete the key entirely when unchecked.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key.
	 * @param string $post_key $_POST key.
	 */
	private function save_checkbox( $post_id, $meta_key, $post_key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified in save().
		if ( ! empty( $_POST[ $post_key ] ) ) {
			update_post_meta( $post_id, $meta_key, '1' );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/**
	 * Override wp_title/document title with the custom meta title, when set.
	 *
	 * @param string $title Default title.
	 * @return string
	 */
	public function filter_document_title( $title ) {
		if ( is_singular() ) {
			$custom = get_post_meta( get_queried_object_id(), '_sso_meta_title', true );
			if ( $custom ) {
				return $custom;
			}
		}
		return $title;
	}

	/**
	 * Output <meta name="description"> for singular content.
	 */
	public function output_meta_description() {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$post_id     = get_queried_object_id();
		$description = get_post_meta( $post_id, '_sso_meta_description', true );

		if ( ! $description ) {
			$description = wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post_id ) ), 30, '' );
		}

		if ( $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
	}
}
