<?php
/**
 * Per-post SEO meta box (General / Social / Schema tabs) and the document title /
 * meta description output that belongs to it.
 *
 * @package Beplus_Metadata_AI_Analyzer
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
				__( 'Beplus Smart SEO', 'beplus-metadata-ai-analyzer' ),
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
				'titleSep' => SSO_Settings::get( 'general', 'title_separator', '–' ),
				'siteUrl'  => home_url( '/' ),
				'postSlug' => $slug,
				'analyzer' => SSO_Analyzer::instance()->get_config(),
				'i18n'     => array(
					'chooseImage'       => __( 'Choose Image', 'beplus-metadata-ai-analyzer' ),
					'useImage'          => __( 'Use this image', 'beplus-metadata-ai-analyzer' ),
					'removeImage'       => __( 'Remove', 'beplus-metadata-ai-analyzer' ),
					'question'          => __( 'Question', 'beplus-metadata-ai-analyzer' ),
					'answer'            => __( 'Answer', 'beplus-metadata-ai-analyzer' ),
					'remove'            => __( 'Remove', 'beplus-metadata-ai-analyzer' ),
					'scoreGood'         => __( 'Good', 'beplus-metadata-ai-analyzer' ),
					'scoreOk'           => __( 'OK', 'beplus-metadata-ai-analyzer' ),
					'scorePoor'         => __( 'Poor', 'beplus-metadata-ai-analyzer' ),
					'suggestion'        => __( 'Suggestion', 'beplus-metadata-ai-analyzer' ),
					'apply'             => __( 'Apply', 'beplus-metadata-ai-analyzer' ),
					'contentAnalyzed'   => __( 'Content analyzed!', 'beplus-metadata-ai-analyzer' ),
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
			<?php
			// SEO Score badge — hiển thị khi đã tính điểm ít nhất 1 lần.
			$sso_score = (int) get_post_meta( $post->ID, '_sso_seo_score', true );
			if ( $sso_score > 0 ) {
				$sso_color = $sso_score >= 75 ? '#00a32a' : ( $sso_score >= 50 ? '#dba617' : '#d63638' );
				$sso_label = $sso_score >= 75 ? __( 'Good', 'beplus-metadata-ai-analyzer' ) : ( $sso_score >= 50 ? __( 'Average', 'beplus-metadata-ai-analyzer' ) : __( 'Needs improvement', 'beplus-metadata-ai-analyzer' ) );
				echo '<div style="display:flex;align-items:center;gap:10px;padding:10px 0 14px;border-bottom:1px solid #e0e0e0;margin-bottom:12px;">';
				printf(
					'<svg viewBox="0 0 36 36" width="40" height="40">
					  <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e0e0e0" stroke-width="3.5"/>
					  <circle cx="18" cy="18" r="15.9" fill="none" stroke="%s" stroke-width="3.5"
					    stroke-dasharray="%d 100" stroke-dashoffset="25" stroke-linecap="round"/>
					  <text x="18" y="22" text-anchor="middle" font-size="10" font-weight="700" fill="%s">%d</text>
					</svg>',
					esc_attr( $sso_color ),
					absint( $sso_score ),
					esc_attr( $sso_color ),
					absint( $sso_score )
				);
				printf(
					'<div><strong style="color:%s;">SEO Score: %d/100</strong><br><span style="font-size:11px;color:#646970;">%s</span></div>',
					esc_attr( $sso_color ),
					absint( $sso_score ),
					esc_html( $sso_label )
				);
				echo '</div>';
			}
			?>
			<h2 class="nav-tab-wrapper sso-tabs-nav">
				<a href="#sso-tab-general" class="nav-tab nav-tab-active" data-tab="general"><?php esc_html_e( 'General', 'beplus-metadata-ai-analyzer' ); ?></a>
				<a href="#sso-tab-social" class="nav-tab" data-tab="social"><?php esc_html_e( 'Social', 'beplus-metadata-ai-analyzer' ); ?></a>
				<a href="#sso-tab-schema" class="nav-tab" data-tab="schema"><?php esc_html_e( 'Schema', 'beplus-metadata-ai-analyzer' ); ?></a>
			</h2>

			<div class="sso-tab-panel" id="sso-tab-general" data-tab-panel="general">

				<div class="sso-snippet-preview">
					<p class="sso-snippet-url"><?php echo esc_html( home_url( '/' ) ); ?><span class="sso-snippet-slug"><?php echo esc_html( $post->post_name ); ?></span></p>
					<p class="sso-snippet-title"></p>
					<p class="sso-snippet-desc"></p>
				</div>

				<p class="sso-field">
					<button type="button" id="sso-autofill-btn" class="button"><?php esc_html_e( '✨ Auto-fill SEO', 'beplus-metadata-ai-analyzer' ); ?></button>
					<span id="sso-autofill-status"></span>
				</p>

				<p class="sso-field">
					<label for="sso_meta_title"><?php esc_html_e( 'Meta Title', 'beplus-metadata-ai-analyzer' ); ?></label>
					<input type="text" id="sso_meta_title" name="sso_meta_title" value="<?php echo esc_attr( $meta_title ); ?>" class="widefat" />
					<span class="sso-char-count" data-target="sso_meta_title" data-max="60"></span>
				</p>

				<p class="sso-field">
					<label for="sso_meta_description"><?php esc_html_e( 'Meta Description', 'beplus-metadata-ai-analyzer' ); ?></label>
					<textarea id="sso_meta_description" name="sso_meta_description" class="widefat" rows="3"><?php echo esc_textarea( $meta_description ); ?></textarea>
					<span class="sso-char-count" data-target="sso_meta_description" data-max="160"></span>
				</p>

				<p class="sso-field">
					<label for="sso_focus_keyword"><?php esc_html_e( 'Focus Keyword', 'beplus-metadata-ai-analyzer' ); ?></label>
					<input type="text" id="sso_focus_keyword" name="sso_focus_keyword" value="<?php echo esc_attr( $focus_keyword ); ?>" class="widefat" />
				</p>

				<div class="sso-analyzer-results">
					<span class="sso-score-badge" id="sso-score-badge">&nbsp;</span>
					<ul id="sso-analyzer-list"></ul>
				</div>

				<p class="sso-field">
					<label for="sso_canonical_url"><?php esc_html_e( 'Canonical URL Override', 'beplus-metadata-ai-analyzer' ); ?></label>
					<input type="url" id="sso_canonical_url" name="sso_canonical_url" value="<?php echo esc_attr( $canonical_url ); ?>" class="widefat" placeholder="<?php echo esc_attr( get_permalink( $post ) ); ?>" />
				</p>

				<p class="sso-field sso-checkboxes">
					<label><input type="checkbox" name="sso_noindex" value="1" <?php checked( '1', $noindex ); ?> /> <?php esc_html_e( 'Noindex this content', 'beplus-metadata-ai-analyzer' ); ?></label>
					<label><input type="checkbox" name="sso_nofollow" value="1" <?php checked( '1', $nofollow ); ?> /> <?php esc_html_e( 'Nofollow links on this content', 'beplus-metadata-ai-analyzer' ); ?></label>
					<label><input type="checkbox" name="sso_sitemap_exclude" value="1" <?php checked( '1', $sitemap_exclude ); ?> /> <?php esc_html_e( 'Exclude from XML sitemap', 'beplus-metadata-ai-analyzer' ); ?></label>
				</p>
			</div>

			<div class="sso-tab-panel" id="sso-tab-social" style="display:none;" data-tab-panel="social">
				<p class="sso-field">
					<label><?php esc_html_e( 'OG Image', 'beplus-metadata-ai-analyzer' ); ?></label>
				</p>
				<div class="sso-media-field">
					<input type="hidden" class="sso-media-input" name="sso_og_image" value="<?php echo esc_attr( $og_image_id ); ?>" />
					<div class="sso-media-preview">
						<?php if ( $og_image_url ) : ?>
							<img src="<?php echo esc_url( $og_image_url ); ?>" alt="" />
						<?php endif; ?>
					</div>
					<button type="button" class="button sso-media-select"><?php esc_html_e( 'Choose Image', 'beplus-metadata-ai-analyzer' ); ?></button>
					<button type="button" class="button sso-media-remove" <?php echo $og_image_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'beplus-metadata-ai-analyzer' ); ?></button>
					<p class="description"><?php esc_html_e( 'Falls back to the featured image, then the site default image, if left empty.', 'beplus-metadata-ai-analyzer' ); ?></p>
				</div>

				<p class="sso-field">
					<label for="sso_og_title"><?php esc_html_e( 'OG Title Override', 'beplus-metadata-ai-analyzer' ); ?></label>
					<input type="text" id="sso_og_title" name="sso_og_title" value="<?php echo esc_attr( $og_title ); ?>" class="widefat" />
				</p>
				<p class="sso-field">
					<label for="sso_og_description"><?php esc_html_e( 'OG Description Override', 'beplus-metadata-ai-analyzer' ); ?></label>
					<textarea id="sso_og_description" name="sso_og_description" class="widefat" rows="3"><?php echo esc_textarea( $og_description ); ?></textarea>
				</p>
			</div>

			<div class="sso-tab-panel" id="sso-tab-schema" style="display:none;" data-tab-panel="schema">
				<p class="sso-field">
					<label for="sso_schema_type"><?php esc_html_e( 'Schema Type', 'beplus-metadata-ai-analyzer' ); ?></label>
					<select id="sso_schema_type" name="sso_schema_type">
						<?php
						$options = array(
							''              => sprintf(
								/* translators: %s: default schema type configured in Settings. */
								__( 'Auto (Settings default: %s)', 'beplus-metadata-ai-analyzer' ),
								$default_type ? $default_type : __( 'None', 'beplus-metadata-ai-analyzer' )
							),
							'article'       => __( 'Article', 'beplus-metadata-ai-analyzer' ),
							'blogposting'   => __( 'BlogPosting', 'beplus-metadata-ai-analyzer' ),
							'webpage'       => __( 'WebPage', 'beplus-metadata-ai-analyzer' ),
							'product'       => __( 'Product', 'beplus-metadata-ai-analyzer' ),
							'faqpage'       => __( 'FAQPage', 'beplus-metadata-ai-analyzer' ),
							'howto'         => __( 'HowTo', 'beplus-metadata-ai-analyzer' ),
							'event'         => __( 'Event', 'beplus-metadata-ai-analyzer' ),
							'video'         => __( 'VideoObject', 'beplus-metadata-ai-analyzer' ),
							'recipe'        => __( 'Recipe', 'beplus-metadata-ai-analyzer' ),
							'jobposting'    => __( 'JobPosting', 'beplus-metadata-ai-analyzer' ),
							'course'        => __( 'Course', 'beplus-metadata-ai-analyzer' ),
							'review'        => __( 'Review', 'beplus-metadata-ai-analyzer' ),
							'localbusiness' => __( 'LocalBusiness', 'beplus-metadata-ai-analyzer' ),
							'none'          => __( 'None (disable schema)', 'beplus-metadata-ai-analyzer' ),
						);
						foreach ( $options as $value => $label ) :
							?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schema_type, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<div class="sso-schema-fields" data-schema-fields="article">
					<p class="sso-field">
						<label for="sso_schema_headline"><?php esc_html_e( 'Headline Override', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_schema_headline" name="sso_schema_headline" value="<?php echo esc_attr( $schema_headline ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_schema_author"><?php esc_html_e( 'Author Name Override', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_schema_author" name="sso_schema_author" value="<?php echo esc_attr( $schema_author ); ?>" class="widefat" />
					</p>
				</div>

				<div class="sso-schema-fields" data-schema-fields="faqpage" style="display:none;">
					<label><?php esc_html_e( 'FAQ Items', 'beplus-metadata-ai-analyzer' ); ?></label>
					<table class="widefat sso-faq-repeater" id="sso-faq-repeater">
						<tbody>
							<?php foreach ( $schema_faq as $item ) : ?>
								<tr>
									<td><input type="text" name="sso_schema_faq_question[]" value="<?php echo esc_attr( $item['question'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Question', 'beplus-metadata-ai-analyzer' ); ?>" class="widefat" /></td>
									<td><input type="text" name="sso_schema_faq_answer[]" value="<?php echo esc_attr( $item['answer'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Answer', 'beplus-metadata-ai-analyzer' ); ?>" class="widefat" /></td>
									<td><button type="button" class="button sso-faq-remove">&times;</button></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<button type="button" class="button" id="sso-faq-add"><?php esc_html_e( 'Add FAQ item', 'beplus-metadata-ai-analyzer' ); ?></button>
				</div>

				<div class="sso-schema-fields" data-schema-fields="localbusiness" style="display:none;">
					<p class="sso-field">
						<label for="sso_lb_name"><?php esc_html_e( 'Business Name', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_lb_name" name="sso_lb_name" value="<?php echo esc_attr( $local_business['name'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_lb_street"><?php esc_html_e( 'Street Address', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_lb_street" name="sso_lb_street" value="<?php echo esc_attr( $local_business['street'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field sso-field-inline">
						<span><label for="sso_lb_city"><?php esc_html_e( 'City', 'beplus-metadata-ai-analyzer' ); ?></label><input type="text" id="sso_lb_city" name="sso_lb_city" value="<?php echo esc_attr( $local_business['city'] ?? '' ); ?>" /></span>
						<span><label for="sso_lb_region"><?php esc_html_e( 'Region', 'beplus-metadata-ai-analyzer' ); ?></label><input type="text" id="sso_lb_region" name="sso_lb_region" value="<?php echo esc_attr( $local_business['region'] ?? '' ); ?>" /></span>
						<span><label for="sso_lb_postal"><?php esc_html_e( 'Postal Code', 'beplus-metadata-ai-analyzer' ); ?></label><input type="text" id="sso_lb_postal" name="sso_lb_postal" value="<?php echo esc_attr( $local_business['postal'] ?? '' ); ?>" /></span>
					</p>
					<p class="sso-field sso-field-inline">
						<span><label for="sso_lb_country"><?php esc_html_e( 'Country', 'beplus-metadata-ai-analyzer' ); ?></label><input type="text" id="sso_lb_country" name="sso_lb_country" value="<?php echo esc_attr( $local_business['country'] ?? '' ); ?>" /></span>
						<span><label for="sso_lb_phone"><?php esc_html_e( 'Phone', 'beplus-metadata-ai-analyzer' ); ?></label><input type="text" id="sso_lb_phone" name="sso_lb_phone" value="<?php echo esc_attr( $local_business['phone'] ?? '' ); ?>" /></span>
						<span><label for="sso_lb_price_range"><?php esc_html_e( 'Price Range', 'beplus-metadata-ai-analyzer' ); ?></label><input type="text" id="sso_lb_price_range" name="sso_lb_price_range" value="<?php echo esc_attr( $local_business['price_range'] ?? '' ); ?>" placeholder="$$" /></span>
					</p>
				</div>

				<div class="sso-schema-fields" data-schema-fields="product" style="display:none;">
					<p class="description"><?php esc_html_e( 'Product data (price, SKU, stock, rating) is pulled automatically from WooCommerce when available.', 'beplus-metadata-ai-analyzer' ); ?></p>
				</div>

				<?php
				// ---- HowTo fields ----
				$howto_meta  = get_post_meta( $post->ID, '_sso_schema_howto', true );
				$howto_meta  = is_array( $howto_meta ) ? $howto_meta : array();
				$howto_steps = isset( $howto_meta['steps'] ) && is_array( $howto_meta['steps'] ) ? $howto_meta['steps'] : array();
				?>
				<div class="sso-schema-fields" data-schema-fields="howto" style="display:none;">
					<p class="sso-field sso-field-inline">
						<span>
							<label for="sso_howto_total_time"><?php esc_html_e( 'Total Time (ISO 8601, e.g. PT30M)', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_howto_total_time" name="sso_howto[total_time]" value="<?php echo esc_attr( $howto_meta['total_time'] ?? '' ); ?>" placeholder="PT30M" />
						</span>
						<span>
							<label for="sso_howto_cost"><?php esc_html_e( 'Estimated Cost', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_howto_cost" name="sso_howto[estimated_cost]" value="<?php echo esc_attr( $howto_meta['estimated_cost'] ?? '' ); ?>" placeholder="0" />
						</span>
						<span>
							<label for="sso_howto_currency"><?php esc_html_e( 'Currency', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_howto_currency" name="sso_howto[currency]" value="<?php echo esc_attr( $howto_meta['currency'] ?? 'VND' ); ?>" placeholder="VND" style="width:80px;" />
						</span>
					</p>
					<label><?php esc_html_e( 'Steps', 'beplus-metadata-ai-analyzer' ); ?></label>
					<table class="widefat sso-howto-repeater">
						<thead><tr>
							<th><?php esc_html_e( 'Step Name', 'beplus-metadata-ai-analyzer' ); ?></th>
							<th><?php esc_html_e( 'Description', 'beplus-metadata-ai-analyzer' ); ?></th>
							<th></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $howto_steps as $step ) : ?>
								<tr>
									<td><input type="text" name="sso_howto_step_name[]" value="<?php echo esc_attr( $step['name'] ?? '' ); ?>" class="widefat" /></td>
									<td><input type="text" name="sso_howto_step_text[]" value="<?php echo esc_attr( $step['text'] ?? '' ); ?>" class="widefat" /></td>
									<td><button type="button" class="button sso-row-remove">&times;</button></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<button type="button" class="button sso-howto-add"><?php esc_html_e( '+ Add Step', 'beplus-metadata-ai-analyzer' ); ?></button>
				</div>

				<?php
				// ---- Event fields ----
				$event_meta = get_post_meta( $post->ID, '_sso_schema_event', true );
				$event_meta = is_array( $event_meta ) ? $event_meta : array();
				?>
				<div class="sso-schema-fields" data-schema-fields="event" style="display:none;">
					<p class="sso-field">
						<label for="sso_event_name"><?php esc_html_e( 'Event Name', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_event_name" name="sso_event[name]" value="<?php echo esc_attr( $event_meta['name'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field sso-field-inline">
						<span>
							<label for="sso_event_start"><?php esc_html_e( 'Start Date/Time', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="datetime-local" id="sso_event_start" name="sso_event[start_date]" value="<?php echo esc_attr( $event_meta['start_date'] ?? '' ); ?>" />
						</span>
						<span>
							<label for="sso_event_end"><?php esc_html_e( 'End Date/Time', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="datetime-local" id="sso_event_end" name="sso_event[end_date]" value="<?php echo esc_attr( $event_meta['end_date'] ?? '' ); ?>" />
						</span>
					</p>
					<p class="sso-field sso-field-inline">
						<span>
							<label for="sso_event_status"><?php esc_html_e( 'Event Status', 'beplus-metadata-ai-analyzer' ); ?></label>
							<select id="sso_event_status" name="sso_event[status]">
								<?php
								$event_statuses = array(
									'EventScheduled'   => __( 'Scheduled', 'beplus-metadata-ai-analyzer' ),
									'EventPostponed'   => __( 'Postponed', 'beplus-metadata-ai-analyzer' ),
									'EventCancelled'   => __( 'Cancelled', 'beplus-metadata-ai-analyzer' ),
									'EventRescheduled' => __( 'Rescheduled', 'beplus-metadata-ai-analyzer' ),
								);
								foreach ( $event_statuses as $val => $lbl ) :
									?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $event_meta['status'] ?? 'EventScheduled', $val ); ?>><?php echo esc_html( $lbl ); ?></option>
								<?php endforeach; ?>
							</select>
						</span>
						<span>
							<label for="sso_event_mode"><?php esc_html_e( 'Attendance Mode', 'beplus-metadata-ai-analyzer' ); ?></label>
							<select id="sso_event_mode" name="sso_event[attendance_mode]">
								<?php
								$attendance_modes = array(
									'OfflineEventAttendanceMode' => __( 'In-Person', 'beplus-metadata-ai-analyzer' ),
									'OnlineEventAttendanceMode'  => __( 'Online', 'beplus-metadata-ai-analyzer' ),
									'MixedEventAttendanceMode'   => __( 'Mixed', 'beplus-metadata-ai-analyzer' ),
								);
								foreach ( $attendance_modes as $val => $lbl ) :
									?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $event_meta['attendance_mode'] ?? 'OfflineEventAttendanceMode', $val ); ?>><?php echo esc_html( $lbl ); ?></option>
								<?php endforeach; ?>
							</select>
						</span>
					</p>
					<p class="sso-field">
						<label for="sso_event_location"><?php esc_html_e( 'Venue Name', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_event_location" name="sso_event[location_name]" value="<?php echo esc_attr( $event_meta['location_name'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_event_address"><?php esc_html_e( 'Venue Address', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_event_address" name="sso_event[location_address]" value="<?php echo esc_attr( $event_meta['location_address'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_event_organizer"><?php esc_html_e( 'Organizer', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_event_organizer" name="sso_event[organizer]" value="<?php echo esc_attr( $event_meta['organizer'] ?? '' ); ?>" class="widefat" />
					</p>
				</div>

				<?php
				// ---- VideoObject fields ----
				$video_meta = get_post_meta( $post->ID, '_sso_schema_video', true );
				$video_meta = is_array( $video_meta ) ? $video_meta : array();
				?>
				<div class="sso-schema-fields" data-schema-fields="video" style="display:none;">
					<p class="sso-field">
						<label for="sso_video_name"><?php esc_html_e( 'Video Title', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_video_name" name="sso_video[name]" value="<?php echo esc_attr( $video_meta['name'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_video_description"><?php esc_html_e( 'Video Description', 'beplus-metadata-ai-analyzer' ); ?></label>
						<textarea id="sso_video_description" name="sso_video[description]" class="widefat" rows="2"><?php echo esc_textarea( $video_meta['description'] ?? '' ); ?></textarea>
					</p>
					<p class="sso-field">
						<label for="sso_video_content_url"><?php esc_html_e( 'Content URL (direct video file)', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="url" id="sso_video_content_url" name="sso_video[content_url]" value="<?php echo esc_attr( $video_meta['content_url'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_video_embed_url"><?php esc_html_e( 'Embed URL (YouTube/Vimeo)', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="url" id="sso_video_embed_url" name="sso_video[embed_url]" value="<?php echo esc_attr( $video_meta['embed_url'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field sso-field-inline">
						<span>
							<label for="sso_video_duration"><?php esc_html_e( 'Duration (ISO 8601, e.g. PT4M33S)', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_video_duration" name="sso_video[duration]" value="<?php echo esc_attr( $video_meta['duration'] ?? '' ); ?>" placeholder="PT4M33S" />
						</span>
						<span>
							<label for="sso_video_upload_date"><?php esc_html_e( 'Upload Date', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="date" id="sso_video_upload_date" name="sso_video[upload_date]" value="<?php echo esc_attr( $video_meta['upload_date'] ?? '' ); ?>" />
						</span>
					</p>
					<p class="description"><?php esc_html_e( 'Thumbnail uses the featured image automatically. Set a direct URL only as fallback.', 'beplus-metadata-ai-analyzer' ); ?></p>
					<p class="sso-field">
						<label for="sso_video_thumbnail_url"><?php esc_html_e( 'Thumbnail URL (fallback)', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="url" id="sso_video_thumbnail_url" name="sso_video[thumbnail_url]" value="<?php echo esc_attr( $video_meta['thumbnail_url'] ?? '' ); ?>" class="widefat" />
					</p>
				</div>

				<?php
				// ---- Recipe fields ----
				$recipe_meta = get_post_meta( $post->ID, '_sso_schema_recipe', true );
				$recipe_meta = is_array( $recipe_meta ) ? $recipe_meta : array();
				?>
				<div class="sso-schema-fields" data-schema-fields="recipe" style="display:none;">
					<p class="sso-field">
						<label for="sso_recipe_name"><?php esc_html_e( 'Recipe Name', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_recipe_name" name="sso_recipe[name]" value="<?php echo esc_attr( $recipe_meta['name'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field sso-field-inline">
						<span>
							<label for="sso_recipe_prep_time"><?php esc_html_e( 'Prep Time (e.g. PT15M)', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_recipe_prep_time" name="sso_recipe[prep_time]" value="<?php echo esc_attr( $recipe_meta['prep_time'] ?? '' ); ?>" placeholder="PT15M" />
						</span>
						<span>
							<label for="sso_recipe_cook_time"><?php esc_html_e( 'Cook Time (e.g. PT30M)', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_recipe_cook_time" name="sso_recipe[cook_time]" value="<?php echo esc_attr( $recipe_meta['cook_time'] ?? '' ); ?>" placeholder="PT30M" />
						</span>
						<span>
							<label for="sso_recipe_total_time"><?php esc_html_e( 'Total Time (e.g. PT45M)', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_recipe_total_time" name="sso_recipe[total_time]" value="<?php echo esc_attr( $recipe_meta['total_time'] ?? '' ); ?>" placeholder="PT45M" />
						</span>
					</p>
					<p class="sso-field sso-field-inline">
						<span>
							<label for="sso_recipe_yield"><?php esc_html_e( 'Yield (servings)', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_recipe_yield" name="sso_recipe[recipe_yield]" value="<?php echo esc_attr( $recipe_meta['recipe_yield'] ?? '' ); ?>" />
						</span>
						<span>
							<label for="sso_recipe_category"><?php esc_html_e( 'Category', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_recipe_category" name="sso_recipe[recipe_category]" value="<?php echo esc_attr( $recipe_meta['recipe_category'] ?? '' ); ?>" />
						</span>
						<span>
							<label for="sso_recipe_cuisine"><?php esc_html_e( 'Cuisine', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_recipe_cuisine" name="sso_recipe[recipe_cuisine]" value="<?php echo esc_attr( $recipe_meta['recipe_cuisine'] ?? '' ); ?>" />
						</span>
					</p>
					<p class="sso-field">
						<label for="sso_recipe_ingredients"><?php esc_html_e( 'Ingredients (one per line)', 'beplus-metadata-ai-analyzer' ); ?></label>
						<textarea id="sso_recipe_ingredients" name="sso_recipe[ingredients]" class="widefat" rows="5"><?php echo esc_textarea( $recipe_meta['ingredients'] ?? '' ); ?></textarea>
					</p>
					<p class="sso-field">
						<label for="sso_recipe_instructions"><?php esc_html_e( 'Instructions (one step per line)', 'beplus-metadata-ai-analyzer' ); ?></label>
						<textarea id="sso_recipe_instructions" name="sso_recipe[instructions]" class="widefat" rows="5"><?php echo esc_textarea( $recipe_meta['instructions'] ?? '' ); ?></textarea>
					</p>
				</div>

				<?php
				// ---- JobPosting fields ----
				$job_meta = get_post_meta( $post->ID, '_sso_schema_job', true );
				$job_meta = is_array( $job_meta ) ? $job_meta : array();
				?>
				<div class="sso-schema-fields" data-schema-fields="jobposting" style="display:none;">
					<p class="sso-field">
						<label for="sso_job_title"><?php esc_html_e( 'Job Title', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_job_title" name="sso_job[title]" value="<?php echo esc_attr( $job_meta['title'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_job_hiring_org"><?php esc_html_e( 'Hiring Organization', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_job_hiring_org" name="sso_job[hiring_org]" value="<?php echo esc_attr( $job_meta['hiring_org'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field sso-field-inline">
						<span>
							<label for="sso_job_location"><?php esc_html_e( 'Job Location (city)', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_job_location" name="sso_job[location]" value="<?php echo esc_attr( $job_meta['location'] ?? '' ); ?>" />
						</span>
						<span>
							<label for="sso_job_type"><?php esc_html_e( 'Employment Type', 'beplus-metadata-ai-analyzer' ); ?></label>
							<select id="sso_job_type" name="sso_job[employment_type]">
								<?php
								$emp_types = array( 'FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'INTERN', 'VOLUNTEER', 'PER_DIEM', 'OTHER' );
								foreach ( $emp_types as $et ) :
									?>
									<option value="<?php echo esc_attr( $et ); ?>" <?php selected( $job_meta['employment_type'] ?? '', $et ); ?>><?php echo esc_html( str_replace( '_', ' ', $et ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</span>
					</p>
					<p class="sso-field sso-field-inline">
						<span>
							<label for="sso_job_salary"><?php esc_html_e( 'Base Salary', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_job_salary" name="sso_job[salary]" value="<?php echo esc_attr( $job_meta['salary'] ?? '' ); ?>" placeholder="10000000" />
						</span>
						<span>
							<label for="sso_job_currency"><?php esc_html_e( 'Currency', 'beplus-metadata-ai-analyzer' ); ?></label>
							<input type="text" id="sso_job_currency" name="sso_job[salary_currency]" value="<?php echo esc_attr( $job_meta['salary_currency'] ?? 'VND' ); ?>" style="width:80px;" />
						</span>
						<span>
							<label for="sso_job_period"><?php esc_html_e( 'Period', 'beplus-metadata-ai-analyzer' ); ?></label>
							<select id="sso_job_period" name="sso_job[salary_period]">
								<?php foreach ( array( 'MONTH', 'YEAR', 'WEEK', 'DAY', 'HOUR' ) as $p ) : ?>
									<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $job_meta['salary_period'] ?? 'MONTH', $p ); ?>><?php echo esc_html( $p ); ?></option>
								<?php endforeach; ?>
							</select>
						</span>
					</p>
					<p class="sso-field">
						<label for="sso_job_valid_through"><?php esc_html_e( 'Valid Through', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="date" id="sso_job_valid_through" name="sso_job[valid_through]" value="<?php echo esc_attr( $job_meta['valid_through'] ?? '' ); ?>" />
					</p>
				</div>

				<?php
				// ---- Course fields ----
				$course_meta = get_post_meta( $post->ID, '_sso_schema_course', true );
				$course_meta = is_array( $course_meta ) ? $course_meta : array();
				?>
				<div class="sso-schema-fields" data-schema-fields="course" style="display:none;">
					<p class="sso-field">
						<label for="sso_course_name"><?php esc_html_e( 'Course Name', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_course_name" name="sso_course[name]" value="<?php echo esc_attr( $course_meta['name'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_course_provider"><?php esc_html_e( 'Provider (Organization)', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_course_provider" name="sso_course[provider]" value="<?php echo esc_attr( $course_meta['provider'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_course_provider_url"><?php esc_html_e( 'Provider URL', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="url" id="sso_course_provider_url" name="sso_course[provider_url]" value="<?php echo esc_attr( $course_meta['provider_url'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_course_url"><?php esc_html_e( 'Course URL (if different from post)', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="url" id="sso_course_url" name="sso_course[url]" value="<?php echo esc_attr( $course_meta['url'] ?? '' ); ?>" class="widefat" />
					</p>
				</div>

				<?php
				// ---- Review fields ----
				$review_meta = get_post_meta( $post->ID, '_sso_schema_review', true );
				$review_meta = is_array( $review_meta ) ? $review_meta : array();
				?>
				<div class="sso-schema-fields" data-schema-fields="review" style="display:none;">
					<p class="sso-field">
						<label for="sso_review_item"><?php esc_html_e( 'Item Reviewed (name)', 'beplus-metadata-ai-analyzer' ); ?></label>
						<input type="text" id="sso_review_item" name="sso_review[item_reviewed]" value="<?php echo esc_attr( $review_meta['item_reviewed'] ?? '' ); ?>" class="widefat" />
					</p>
					<p class="sso-field">
						<label for="sso_review_rating"><?php esc_html_e( 'Rating (1–5)', 'beplus-metadata-ai-analyzer' ); ?></label>
						<select id="sso_review_rating" name="sso_review[rating]">
							<?php for ( $r = 1; $r <= 5; $r++ ) : ?>
								<option value="<?php echo esc_attr( $r ); ?>" <?php selected( (int) ( $review_meta['rating'] ?? 5 ), $r ); ?>><?php echo esc_html( $r ); ?></option>
							<?php endfor; ?>
						</select>
					</p>
					<p class="sso-field">
						<label for="sso_review_body"><?php esc_html_e( 'Review Body', 'beplus-metadata-ai-analyzer' ); ?></label>
						<textarea id="sso_review_body" name="sso_review[body]" class="widefat" rows="3"><?php echo esc_textarea( $review_meta['body'] ?? '' ); ?></textarea>
					</p>
				</div>

				<div class="sso-schema-fields" data-schema-fields="blogposting" style="display:none;">
					<p class="description"><?php esc_html_e( 'BlogPosting uses the same Article fields (Headline and Author) above.', 'beplus-metadata-ai-analyzer' ); ?></p>
				</div>
				<div class="sso-schema-fields" data-schema-fields="webpage" style="display:none;">
					<p class="description"><?php esc_html_e( 'WebPage schema is built automatically from the post title, description and URL — no extra fields needed.', 'beplus-metadata-ai-analyzer' ); ?></p>
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

		// HowTo schema fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified.
		if ( isset( $_POST['sso_howto'] ) && is_array( $_POST['sso_howto'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$howto_raw  = wp_unslash( $_POST['sso_howto'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$howto_data = array(
				'total_time'     => sanitize_text_field( $howto_raw['total_time'] ?? '' ),
				'estimated_cost' => sanitize_text_field( $howto_raw['estimated_cost'] ?? '' ),
				'currency'       => sanitize_text_field( $howto_raw['currency'] ?? 'VND' ),
			);
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$step_names = isset( $_POST['sso_howto_step_name'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['sso_howto_step_name'] ) ) : array();
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$step_texts = isset( $_POST['sso_howto_step_text'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['sso_howto_step_text'] ) ) : array();
			$steps      = array();
			foreach ( $step_names as $i => $sname ) {
				if ( '' === trim( $sname ) ) {
					continue;
				}
				$steps[] = array(
					'name' => $sname,
					'text' => $step_texts[ $i ] ?? '',
				);
			}
			$howto_data['steps'] = $steps;
			update_post_meta( $post_id, '_sso_schema_howto', $howto_data );
		}

		// Event schema fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['sso_event'] ) && is_array( $_POST['sso_event'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$event_raw  = wp_unslash( $_POST['sso_event'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$event_data = array_map( 'sanitize_text_field', $event_raw );
			if ( array_filter( $event_data ) ) {
				update_post_meta( $post_id, '_sso_schema_event', $event_data );
			} else {
				delete_post_meta( $post_id, '_sso_schema_event' );
			}
		}

		// VideoObject schema fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['sso_video'] ) && is_array( $_POST['sso_video'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$video_raw  = wp_unslash( $_POST['sso_video'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$video_data = array();
			foreach ( $video_raw as $key => $value ) {
				if ( in_array( $key, array( 'content_url', 'embed_url', 'thumbnail_url' ), true ) ) {
					$video_data[ $key ] = esc_url_raw( $value );
				} elseif ( 'description' === $key ) {
					$video_data[ $key ] = sanitize_textarea_field( $value );
				} else {
					$video_data[ $key ] = sanitize_text_field( $value );
				}
			}
			if ( array_filter( $video_data ) ) {
				update_post_meta( $post_id, '_sso_schema_video', $video_data );
			} else {
				delete_post_meta( $post_id, '_sso_schema_video' );
			}
		}

		// Recipe schema fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['sso_recipe'] ) && is_array( $_POST['sso_recipe'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$recipe_raw  = wp_unslash( $_POST['sso_recipe'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$recipe_data = array();
			foreach ( $recipe_raw as $key => $value ) {
				$recipe_data[ $key ] = in_array( $key, array( 'ingredients', 'instructions' ), true )
					? sanitize_textarea_field( $value )
					: sanitize_text_field( $value );
			}
			if ( array_filter( $recipe_data ) ) {
				update_post_meta( $post_id, '_sso_schema_recipe', $recipe_data );
			} else {
				delete_post_meta( $post_id, '_sso_schema_recipe' );
			}
		}

		// JobPosting schema fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['sso_job'] ) && is_array( $_POST['sso_job'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$job_raw  = wp_unslash( $_POST['sso_job'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$job_data = array_map( 'sanitize_text_field', $job_raw );
			if ( array_filter( $job_data ) ) {
				update_post_meta( $post_id, '_sso_schema_job', $job_data );
			} else {
				delete_post_meta( $post_id, '_sso_schema_job' );
			}
		}

		// Course schema fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['sso_course'] ) && is_array( $_POST['sso_course'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$course_raw  = wp_unslash( $_POST['sso_course'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$course_data = array();
			foreach ( $course_raw as $key => $value ) {
				$course_data[ $key ] = in_array( $key, array( 'provider_url', 'url' ), true )
					? esc_url_raw( $value )
					: sanitize_text_field( $value );
			}
			if ( array_filter( $course_data ) ) {
				update_post_meta( $post_id, '_sso_schema_course', $course_data );
			} else {
				delete_post_meta( $post_id, '_sso_schema_course' );
			}
		}

		// Review schema fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['sso_review'] ) && is_array( $_POST['sso_review'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$review_raw  = wp_unslash( $_POST['sso_review'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$review_data = array(
				'item_reviewed' => sanitize_text_field( $review_raw['item_reviewed'] ?? '' ),
				'rating'        => absint( $review_raw['rating'] ?? 5 ),
				'body'          => sanitize_textarea_field( $review_raw['body'] ?? '' ),
			);
			if ( $review_data['item_reviewed'] || $review_data['body'] ) {
				update_post_meta( $post_id, '_sso_schema_review', $review_data );
			} else {
				delete_post_meta( $post_id, '_sso_schema_review' );
			}
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
