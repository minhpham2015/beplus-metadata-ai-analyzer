<?php
/**
 * Settings module: admin settings page (General / Social / Schema / Sitemap tabs)
 * and the shared option accessor used by every other module.
 *
 * @package Beplus_Smart_SEO_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSO_Settings
 */
class SSO_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var SSO_Settings|null
	 */
	private static $instance = null;

	/**
	 * Option key used to store the whole settings array.
	 */
	const OPTION_KEY = 'sso_settings';

	/**
	 * Get the singleton instance.
	 *
	 * @return SSO_Settings
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
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Default settings structure.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'general'     => array(
				'title_separator'    => '-',
				'noindex_search'     => 1,
				'noindex_post_types' => array(),
				'noindex_archives'   => array(
					'category' => 0,
					'post_tag' => 1,
					'author'   => 1,
					'date'     => 1,
				),
			),
			'social'      => array(
				'default_og_image' => 0,
				'facebook_url'     => '',
				'twitter_username' => '',
			),
			'schema'      => array(
				'entity_type' => 'organization',
				'name'        => get_bloginfo( 'name' ),
				'logo'        => 0,
				'url'         => home_url( '/' ),
				'sameas'      => array(
					'facebook'  => '',
					'twitter'   => '',
					'linkedin'  => '',
					'instagram' => '',
					'youtube'   => '',
				),
				'post_types'  => array(),
			),
			'sitemap'     => array(
				'enabled'             => 1,
				'exclude_post_types'  => array(),
			),
			'breadcrumbs' => array(
				'separator'     => '&raquo;',
				'homepage_text' => __( 'Home', 'beplus-smart-seo-google-ai' ),
				'show_category' => 1,
			),
		);
	}

	/**
	 * Get the full settings array merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		return self::recursive_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single settings value.
	 *
	 * @param string      $group   Top level group (general|social|schema|sitemap|breadcrumbs).
	 * @param string|null $key     Optional sub key. Null returns the whole group.
	 * @param mixed       $default Fallback value.
	 * @return mixed
	 */
	public static function get( $group, $key = null, $default = '' ) {
		$settings = self::get_settings();

		if ( ! isset( $settings[ $group ] ) ) {
			return $default;
		}

		if ( null === $key ) {
			return $settings[ $group ];
		}

		return isset( $settings[ $group ][ $key ] ) ? $settings[ $group ][ $key ] : $default;
	}

	/**
	 * Recursively merge saved values on top of defaults so partial saves never drop other tabs.
	 *
	 * @param array $args     Saved values.
	 * @param array $defaults Default values.
	 * @return array
	 */
	private static function recursive_parse_args( $args, $defaults ) {
		$args   = (array) $args;
		$merged = $defaults;

		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = self::recursive_parse_args( $value, $merged[ $key ] );
			} else {
				$merged[ $key ] = $value;
			}
		}

		return $merged;
	}

	/**
	 * Register the settings page under Settings > Smart SEO Google & AI.
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Smart SEO Google & AI', 'beplus-smart-seo-google-ai' ),
			__( 'Smart SEO Google & AI', 'beplus-smart-seo-google-ai' ),
			'manage_options',
			'sso-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register the option and its sanitize callback.
	 */
	public function register_settings() {
		register_setting(
			'sso_settings_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Enqueue admin assets on our settings screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_sso-settings' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'sso-admin-style', SSO_PLUGIN_URL . 'admin/css/admin-style.css', array(), SSO_VERSION );
		wp_enqueue_script( 'sso-admin-script', SSO_PLUGIN_URL . 'admin/js/admin-script.js', array( 'jquery' ), SSO_VERSION, true );

		wp_localize_script(
			'sso-admin-script',
			'ssoAdmin',
			array(
				'context' => 'settings',
				'i18n'    => array(
					'chooseImage' => __( 'Choose Image', 'beplus-smart-seo-google-ai' ),
					'useImage'    => __( 'Use this image', 'beplus-smart-seo-google-ai' ),
					'removeImage' => __( 'Remove', 'beplus-smart-seo-google-ai' ),
					'addRow'      => __( 'Add FAQ item', 'beplus-smart-seo-google-ai' ),
					'removeRow'   => __( 'Remove', 'beplus-smart-seo-google-ai' ),
				),
			)
		);
	}

	/**
	 * Sanitize the whole settings array before saving. Only groups actually present
	 * in the submitted form are overwritten so switching tabs never loses data.
	 *
	 * @param array $input Raw posted value.
	 * @return array
	 */
	public function sanitize( $input ) {
		$existing = self::recursive_parse_args( get_option( self::OPTION_KEY, array() ), self::defaults() );
		$input    = (array) $input;

		foreach ( $input as $group => $values ) {
			$existing[ $group ] = $this->sanitize_group( $group, (array) $values );
		}

		return $existing;
	}

	/**
	 * Sanitize a single settings group.
	 *
	 * @param string $group  Group key.
	 * @param array  $values Raw values for the group.
	 * @return array
	 */
	private function sanitize_group( $group, $values ) {
		switch ( $group ) {
			case 'general':
				return array(
					'title_separator'    => isset( $values['title_separator'] ) ? sanitize_text_field( $values['title_separator'] ) : '-',
					'noindex_search'     => ! empty( $values['noindex_search'] ) ? 1 : 0,
					'noindex_post_types' => isset( $values['noindex_post_types'] ) ? array_map( 'absint', (array) $values['noindex_post_types'] ) : array(),
					'noindex_archives'   => isset( $values['noindex_archives'] ) ? array_map( 'absint', (array) $values['noindex_archives'] ) : array(),
				);

			case 'social':
				return array(
					'default_og_image' => isset( $values['default_og_image'] ) ? absint( $values['default_og_image'] ) : 0,
					'facebook_url'     => isset( $values['facebook_url'] ) ? esc_url_raw( $values['facebook_url'] ) : '',
					'twitter_username' => isset( $values['twitter_username'] ) ? sanitize_text_field( ltrim( $values['twitter_username'], '@' ) ) : '',
				);

			case 'schema':
				$post_types = array();
				if ( isset( $values['post_types'] ) && is_array( $values['post_types'] ) ) {
					foreach ( $values['post_types'] as $post_type => $config ) {
						$post_types[ sanitize_key( $post_type ) ] = array(
							'enabled' => ! empty( $config['enabled'] ) ? 1 : 0,
							'type'    => isset( $config['type'] ) ? sanitize_key( $config['type'] ) : '',
						);
					}
				}

				$sameas = array();
				if ( isset( $values['sameas'] ) && is_array( $values['sameas'] ) ) {
					foreach ( $values['sameas'] as $network => $url ) {
						$sameas[ sanitize_key( $network ) ] = esc_url_raw( $url );
					}
				}

				return array(
					'entity_type' => ( isset( $values['entity_type'] ) && 'person' === $values['entity_type'] ) ? 'person' : 'organization',
					'name'        => isset( $values['name'] ) ? sanitize_text_field( $values['name'] ) : '',
					'logo'        => isset( $values['logo'] ) ? absint( $values['logo'] ) : 0,
					'url'         => isset( $values['url'] ) ? esc_url_raw( $values['url'] ) : home_url( '/' ),
					'sameas'      => $sameas,
					'post_types'  => $post_types,
				);

			case 'sitemap':
				return array(
					'enabled'            => ! empty( $values['enabled'] ) ? 1 : 0,
					'exclude_post_types' => isset( $values['exclude_post_types'] ) ? array_map( 'absint', (array) $values['exclude_post_types'] ) : array(),
				);

			case 'breadcrumbs':
				return array(
					'separator'     => isset( $values['separator'] ) ? sanitize_text_field( $values['separator'] ) : '&raquo;',
					'homepage_text' => isset( $values['homepage_text'] ) ? sanitize_text_field( $values['homepage_text'] ) : __( 'Home', 'beplus-smart-seo-google-ai' ),
					'show_category' => ! empty( $values['show_category'] ) ? 1 : 0,
				);

			default:
				return array();
		}
	}

	/**
	 * Get the list of public post types (minus attachments) as objects.
	 *
	 * @return WP_Post_Type[]
	 */
	private function get_public_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );
		return $post_types;
	}

	/**
	 * Render the settings page shell + tab navigation.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs       = array(
			'general'     => __( 'General', 'beplus-smart-seo-google-ai' ),
			'social'      => __( 'Social', 'beplus-smart-seo-google-ai' ),
			'schema'      => __( 'Schema', 'beplus-smart-seo-google-ai' ),
			'sitemap'     => __( 'Sitemap', 'beplus-smart-seo-google-ai' ),
			'breadcrumbs' => __( 'Breadcrumbs', 'beplus-smart-seo-google-ai' ),
		);
		$active_tab = isset( $_GET['tab'] ) && isset( $tabs[ sanitize_key( wp_unslash( $_GET['tab'] ) ) ] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab navigation.

		?>
		<div class="wrap sso-settings-wrap">
			<h1><?php esc_html_e( 'BePlus Smart SEO Google & AI', 'beplus-smart-seo-google-ai' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'sso-settings', 'tab' => $tab_key ), admin_url( 'options-general.php' ) ) ); ?>"
						class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="options.php" class="sso-settings-form">
				<?php
				settings_fields( 'sso_settings_group' );

				switch ( $active_tab ) {
					case 'social':
						$this->render_social_tab();
						break;
					case 'schema':
						$this->render_schema_tab();
						break;
					case 'sitemap':
						$this->render_sitemap_tab();
						break;
					case 'breadcrumbs':
						$this->render_breadcrumbs_tab();
						break;
					default:
						$this->render_general_tab();
						break;
				}

				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * General tab: title separator, default robots behaviour.
	 */
	private function render_general_tab() {
		$general = self::get( 'general' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sso_title_separator"><?php esc_html_e( 'Title Separator', 'beplus-smart-seo-google-ai' ); ?></label></th>
				<td>
					<input type="text" id="sso_title_separator" name="sso_settings[general][title_separator]" value="<?php echo esc_attr( $general['title_separator'] ); ?>" class="small-text" maxlength="5" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Search Results', 'beplus-smart-seo-google-ai' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="sso_settings[general][noindex_search]" value="1" <?php checked( ! empty( $general['noindex_search'] ) ); ?> />
						<?php esc_html_e( 'Add noindex to internal search results pages', 'beplus-smart-seo-google-ai' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Archive Defaults', 'beplus-smart-seo-google-ai' ); ?></th>
				<td>
					<fieldset>
						<label><input type="checkbox" name="sso_settings[general][noindex_archives][category]" value="1" <?php checked( ! empty( $general['noindex_archives']['category'] ) ); ?> /> <?php esc_html_e( 'Noindex category archives', 'beplus-smart-seo-google-ai' ); ?></label><br />
						<label><input type="checkbox" name="sso_settings[general][noindex_archives][post_tag]" value="1" <?php checked( ! empty( $general['noindex_archives']['post_tag'] ) ); ?> /> <?php esc_html_e( 'Noindex tag archives', 'beplus-smart-seo-google-ai' ); ?></label><br />
						<label><input type="checkbox" name="sso_settings[general][noindex_archives][author]" value="1" <?php checked( ! empty( $general['noindex_archives']['author'] ) ); ?> /> <?php esc_html_e( 'Noindex author archives', 'beplus-smart-seo-google-ai' ); ?></label><br />
						<label><input type="checkbox" name="sso_settings[general][noindex_archives][date]" value="1" <?php checked( ! empty( $general['noindex_archives']['date'] ) ); ?> /> <?php esc_html_e( 'Noindex date archives', 'beplus-smart-seo-google-ai' ); ?></label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Default Robots per Post Type', 'beplus-smart-seo-google-ai' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $this->get_public_post_types() as $post_type ) : ?>
							<label>
								<input type="checkbox" name="sso_settings[general][noindex_post_types][<?php echo esc_attr( $post_type->name ); ?>]" value="1" <?php checked( ! empty( $general['noindex_post_types'][ $post_type->name ] ) ); ?> />
								<?php
								/* translators: %s: post type label. */
								echo esc_html( sprintf( __( 'Noindex %s by default', 'beplus-smart-seo-google-ai' ), $post_type->label ) );
								?>
							</label><br />
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Used only when a post does not explicitly set noindex in its SEO meta box.', 'beplus-smart-seo-google-ai' ); ?></p>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Social tab: default OG image + social profile URLs used for og/twitter fallback.
	 */
	private function render_social_tab() {
		$social    = self::get( 'social' );
		$image_url = $social['default_og_image'] ? wp_get_attachment_image_url( (int) $social['default_og_image'], 'medium' ) : '';
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Default OG Image', 'beplus-smart-seo-google-ai' ); ?></th>
				<td>
					<div class="sso-media-field">
						<input type="hidden" class="sso-media-input" name="sso_settings[social][default_og_image]" value="<?php echo esc_attr( $social['default_og_image'] ); ?>" />
						<div class="sso-media-preview">
							<?php if ( $image_url ) : ?>
								<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
							<?php endif; ?>
						</div>
						<button type="button" class="button sso-media-select"><?php esc_html_e( 'Choose Image', 'beplus-smart-seo-google-ai' ); ?></button>
						<button type="button" class="button sso-media-remove" <?php echo $image_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'beplus-smart-seo-google-ai' ); ?></button>
						<p class="description"><?php esc_html_e( 'Used as the Open Graph / Twitter image fallback when a post has no featured image.', 'beplus-smart-seo-google-ai' ); ?></p>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sso_facebook_url"><?php esc_html_e( 'Facebook Page URL', 'beplus-smart-seo-google-ai' ); ?></label></th>
				<td><input type="url" id="sso_facebook_url" name="sso_settings[social][facebook_url]" value="<?php echo esc_attr( $social['facebook_url'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="sso_twitter_username"><?php esc_html_e( 'Twitter/X Username', 'beplus-smart-seo-google-ai' ); ?></label></th>
				<td>@<input type="text" id="sso_twitter_username" name="sso_settings[social][twitter_username]" value="<?php echo esc_attr( $social['twitter_username'] ); ?>" class="regular-text" /></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Schema tab: Organization/Person + per post type toggles + live JSON-LD preview.
	 */
	private function render_schema_tab() {
		$schema    = self::get( 'schema' );
		$logo_url  = $schema['logo'] ? wp_get_attachment_image_url( (int) $schema['logo'], 'medium' ) : '';
		$type_opts = array(
			''              => __( 'None', 'beplus-smart-seo-google-ai' ),
			'article'       => __( 'Article', 'beplus-smart-seo-google-ai' ),
			'product'       => __( 'Product', 'beplus-smart-seo-google-ai' ),
			'faqpage'       => __( 'FAQPage', 'beplus-smart-seo-google-ai' ),
			'localbusiness' => __( 'LocalBusiness', 'beplus-smart-seo-google-ai' ),
		);
		?>
		<h2 class="title"><?php esc_html_e( 'Organization / Person', 'beplus-smart-seo-google-ai' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Entity Type', 'beplus-smart-seo-google-ai' ); ?></th>
				<td>
					<label><input type="radio" name="sso_settings[schema][entity_type]" value="organization" <?php checked( 'organization', $schema['entity_type'] ); ?> /> <?php esc_html_e( 'Organization', 'beplus-smart-seo-google-ai' ); ?></label>
					&nbsp;&nbsp;
					<label><input type="radio" name="sso_settings[schema][entity_type]" value="person" <?php checked( 'person', $schema['entity_type'] ); ?> /> <?php esc_html_e( 'Person', 'beplus-smart-seo-google-ai' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sso_schema_name"><?php esc_html_e( 'Name', 'beplus-smart-seo-google-ai' ); ?></label></th>
				<td><input type="text" id="sso_schema_name" name="sso_settings[schema][name]" value="<?php echo esc_attr( $schema['name'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="sso_schema_url"><?php esc_html_e( 'URL', 'beplus-smart-seo-google-ai' ); ?></label></th>
				<td><input type="url" id="sso_schema_url" name="sso_settings[schema][url]" value="<?php echo esc_attr( $schema['url'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Logo', 'beplus-smart-seo-google-ai' ); ?></th>
				<td>
					<div class="sso-media-field">
						<input type="hidden" class="sso-media-input" name="sso_settings[schema][logo]" value="<?php echo esc_attr( $schema['logo'] ); ?>" />
						<div class="sso-media-preview">
							<?php if ( $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
							<?php endif; ?>
						</div>
						<button type="button" class="button sso-media-select"><?php esc_html_e( 'Choose Image', 'beplus-smart-seo-google-ai' ); ?></button>
						<button type="button" class="button sso-media-remove" <?php echo $logo_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'beplus-smart-seo-google-ai' ); ?></button>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Social Profiles (sameAs)', 'beplus-smart-seo-google-ai' ); ?></th>
				<td>
					<?php foreach ( array( 'facebook', 'twitter', 'linkedin', 'instagram', 'youtube' ) as $network ) : ?>
						<p>
							<label for="sso_sameas_<?php echo esc_attr( $network ); ?>" style="display:inline-block;width:90px;text-transform:capitalize;"><?php echo esc_html( $network ); ?></label>
							<input type="url" id="sso_sameas_<?php echo esc_attr( $network ); ?>" name="sso_settings[schema][sameas][<?php echo esc_attr( $network ); ?>]" value="<?php echo esc_attr( isset( $schema['sameas'][ $network ] ) ? $schema['sameas'][ $network ] : '' ); ?>" class="regular-text" />
						</p>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Schema per Post Type', 'beplus-smart-seo-google-ai' ); ?></h2>
		<table class="widefat sso-schema-post-types">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Enabled', 'beplus-smart-seo-google-ai' ); ?></th>
					<th><?php esc_html_e( 'Post Type', 'beplus-smart-seo-google-ai' ); ?></th>
					<th><?php esc_html_e( 'Schema Type', 'beplus-smart-seo-google-ai' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $this->get_public_post_types() as $post_type ) :
					$config = isset( $schema['post_types'][ $post_type->name ] ) ? $schema['post_types'][ $post_type->name ] : array( 'enabled' => 0, 'type' => '' );
					?>
					<tr>
						<td><input type="checkbox" name="sso_settings[schema][post_types][<?php echo esc_attr( $post_type->name ); ?>][enabled]" value="1" <?php checked( ! empty( $config['enabled'] ) ); ?> /></td>
						<td><?php echo esc_html( $post_type->label ); ?></td>
						<td>
							<select name="sso_settings[schema][post_types][<?php echo esc_attr( $post_type->name ); ?>][type]">
								<?php foreach ( $type_opts as $opt_value => $opt_label ) : ?>
									<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $config['type'], $opt_value ); ?>><?php echo esc_html( $opt_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Individual posts can override the schema type from the "Schema" tab of their SEO meta box.', 'beplus-smart-seo-google-ai' ); ?></p>

		<h2 class="title"><?php esc_html_e( 'JSON-LD Preview (Organization + Website)', 'beplus-smart-seo-google-ai' ); ?></h2>
		<?php
		$preview = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				SSO_Schema::instance()->build_organization_schema(),
				SSO_Schema::instance()->build_website_schema(),
			),
		);
		?>
		<pre class="sso-json-preview"><?php echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
		<?php
	}

	/**
	 * Sitemap tab.
	 */
	private function render_sitemap_tab() {
		$sitemap = self::get( 'sitemap' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'XML Sitemap', 'beplus-smart-seo-google-ai' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="sso_settings[sitemap][enabled]" value="1" <?php checked( ! empty( $sitemap['enabled'] ) ); ?> />
						<?php esc_html_e( 'Enable XML sitemap', 'beplus-smart-seo-google-ai' ); ?>
					</label>
					<p class="description">
						<?php
						printf(
							/* translators: %s: sitemap URL. */
							esc_html__( 'Your sitemap is available at: %s', 'beplus-smart-seo-google-ai' ),
							'<a href="' . esc_url( home_url( '/sitemap.xml' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( home_url( '/sitemap.xml' ) ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Exclude Post Types', 'beplus-smart-seo-google-ai' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $this->get_public_post_types() as $post_type ) : ?>
							<label>
								<input type="checkbox" name="sso_settings[sitemap][exclude_post_types][<?php echo esc_attr( $post_type->name ); ?>]" value="1" <?php checked( ! empty( $sitemap['exclude_post_types'][ $post_type->name ] ) ); ?> />
								<?php echo esc_html( $post_type->label ); ?>
							</label><br />
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Breadcrumbs tab.
	 */
	private function render_breadcrumbs_tab() {
		$breadcrumbs = self::get( 'breadcrumbs' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sso_bc_separator"><?php esc_html_e( 'Separator', 'beplus-smart-seo-google-ai' ); ?></label></th>
				<td><input type="text" id="sso_bc_separator" name="sso_settings[breadcrumbs][separator]" value="<?php echo esc_attr( $breadcrumbs['separator'] ); ?>" class="small-text" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="sso_bc_home"><?php esc_html_e( 'Homepage Text', 'beplus-smart-seo-google-ai' ); ?></label></th>
				<td><input type="text" id="sso_bc_home" name="sso_settings[breadcrumbs][homepage_text]" value="<?php echo esc_attr( $breadcrumbs['homepage_text'] ); ?>" class="regular-text" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Category', 'beplus-smart-seo-google-ai' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="sso_settings[breadcrumbs][show_category]" value="1" <?php checked( ! empty( $breadcrumbs['show_category'] ) ); ?> />
						<?php esc_html_e( 'Show category in post breadcrumbs', 'beplus-smart-seo-google-ai' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Template Tag', 'beplus-smart-seo-google-ai' ); ?></th>
				<td><code>&lt;?php if ( function_exists( 'sso_breadcrumbs' ) ) { sso_breadcrumbs(); } ?&gt;</code> <?php esc_html_e( 'or shortcode', 'beplus-smart-seo-google-ai' ); ?> <code>[sso_breadcrumb]</code></td>
			</tr>
		</table>
		<?php
	}
}
