<?php

class MP_Network_Shop_Profile_Addon {
	private static $_instance = null;

	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'init', array( $this, 'maybe_register_addon_settings_metabox' ) );
		add_action( 'init', array( $this, 'register_subshop_profile_metabox' ) );
	}

	private function is_profile_mode_active() {
		$mode = sanitize_key( (string) mp_get_network_setting( 'advanced->network_shop_presentation_mode', 'direct_product' ) );

		return 'shop_profile' === $mode;
	}

	public function register_menu() {
		if ( is_network_admin() || ! is_admin() || ! is_multisite() ) {
			return;
		}

		$cap = apply_filters( 'mp_store_settings_cap', 'manage_store_settings' );
		add_submenu_page(
			'store-settings',
			__( 'Shop-Profil', 'mp' ),
			__( 'Shop-Profil', 'mp' ),
			$cap,
			'store-settings-shop-profile',
			array( $this, 'render_page' )
		);
	}

	public function maybe_register_addon_settings_metabox() {
		if ( ! is_admin() ) {
			return;
		}

		if ( 'store-settings-addons' !== (string) mp_get_get_value( 'page', '' ) ) {
			return;
		}

		if ( 'MP_Network_Shop_Profile_Addon' !== (string) mp_get_get_value( 'addon', '' ) ) {
			return;
		}

		$this->register_metabox( array( 'store-settings-addons' ) );
	}

	public function register_subshop_profile_metabox() {
		if ( ! is_admin() || ! is_multisite() || is_network_admin() ) {
			return;
		}

		$this->register_metabox( array( 'store-settings-shop-profile' ) );
	}

	private function register_metabox( $page_slugs ) {
		$metabox = new PSOURCE_Metabox( array(
			'id'          => 'mp-network-shop-profile-settings',
			'page_slugs'  => $page_slugs,
			'title'       => __( 'Shop-Profil & Design', 'mp' ),
			'option_name' => 'mp_settings',
		) );

		$metabox->add_field( 'text', array(
			'name'  => 'shop_profile[display_name]',
			'label' => array( 'text' => __( 'Profilname', 'mp' ) ),
			'desc'  => __( 'Optionaler Anzeigename fuer das Shop-Profil.', 'mp' ),
		) );
		$metabox->add_field( 'text', array(
			'name'  => 'shop_profile[tagline]',
			'label' => array( 'text' => __( 'Kurzinfo', 'mp' ) ),
		) );
		$metabox->add_field( 'textarea', array(
			'name'  => 'shop_profile[about]',
			'label' => array( 'text' => __( 'Beschreibung', 'mp' ) ),
		) );
		$metabox->add_field( 'image', array(
			'name'         => 'shop_profile[hero_image_id]',
			'label'        => array( 'text' => __( 'Profilbild', 'mp' ) ),
			'preview_size' => 'medium',
		) );

		$metabox->add_field( 'text', array(
			'name'  => 'shop_profile[website_url]',
			'label' => array( 'text' => __( 'Webseite URL', 'mp' ) ),
		) );
		$metabox->add_field( 'text', array(
			'name'  => 'shop_profile[support_url]',
			'label' => array( 'text' => __( 'Support URL', 'mp' ) ),
		) );
		$metabox->add_field( 'textarea', array(
			'name'  => 'shop_profile[custom_links]',
			'label' => array( 'text' => __( 'Eigene Links', 'mp' ) ),
			'desc'  => __( 'Pro Zeile: Label|https://url', 'mp' ),
		) );

		$metabox->add_field( 'text', array(
			'name'  => 'shop_profile[social_facebook]',
			'label' => array( 'text' => __( 'Facebook URL', 'mp' ) ),
		) );
		$metabox->add_field( 'text', array(
			'name'  => 'shop_profile[social_instagram]',
			'label' => array( 'text' => __( 'Instagram URL', 'mp' ) ),
		) );
		$metabox->add_field( 'text', array(
			'name'  => 'shop_profile[social_tiktok]',
			'label' => array( 'text' => __( 'TikTok URL', 'mp' ) ),
		) );
		$metabox->add_field( 'text', array(
			'name'  => 'shop_profile[social_linkedin]',
			'label' => array( 'text' => __( 'LinkedIn URL', 'mp' ) ),
		) );
		$metabox->add_field( 'text', array(
			'name'  => 'shop_profile[social_youtube]',
			'label' => array( 'text' => __( 'YouTube URL', 'mp' ) ),
		) );

		$metabox->add_field( 'section', array(
			'label' => array( 'text' => __( 'Design', 'mp' ) ),
		) );
		$metabox->add_field( 'colorpicker', array(
			'name'          => 'shop_profile[theme_primary]',
			'label'         => array( 'text' => __( 'Primaerfarbe', 'mp' ) ),
			'default_value' => '#2f6ca3',
		) );
		$metabox->add_field( 'colorpicker', array(
			'name'          => 'shop_profile[theme_accent]',
			'label'         => array( 'text' => __( 'Akzentfarbe', 'mp' ) ),
			'default_value' => '#1e3348',
		) );
		$metabox->add_field( 'colorpicker', array(
			'name'          => 'shop_profile[theme_bg_start]',
			'label'         => array( 'text' => __( 'Hintergrund Verlauf Start', 'mp' ) ),
			'default_value' => '#eef6ff',
		) );
		$metabox->add_field( 'colorpicker', array(
			'name'          => 'shop_profile[theme_bg_end]',
			'label'         => array( 'text' => __( 'Hintergrund Verlauf Ende', 'mp' ) ),
			'default_value' => '#f8fcff',
		) );
		$metabox->add_field( 'colorpicker', array(
			'name'          => 'shop_profile[theme_card_bg]',
			'label'         => array( 'text' => __( 'Kartenhintergrund', 'mp' ) ),
			'default_value' => '#ffffff',
		) );
	}

	public function render_page() {
		$cap = apply_filters( 'mp_store_settings_cap', 'manage_store_settings' );
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'Unzureichende Berechtigung.', 'mp' ) );
		}
		?>
		<div class="wrap mp-wrap">
			<div class="icon32"><img src="<?php echo esc_url( mp_plugin_url( 'ui/images/settings.png' ) ); ?>" alt=""></div>
			<h2 class="mp-settings-title"><?php esc_html_e( 'Shop-Profil', 'mp' ); ?></h2>
			<div class="clear"></div>
			<?php if ( ! $this->is_profile_mode_active() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Die Profilseite ist derzeit im Netzwerk nicht aktiv. Daten koennen dennoch vorbereitet werden.', 'mp' ); ?></p></div>
			<?php endif; ?>
			<div class="mp-settings">
				<form method="post">
					<?php do_action( 'psource_metabox/render_settings_metaboxes' ); ?>
				</form>
			</div>
		</div>
		<?php
	}
}

MP_Network_Shop_Profile_Addon::get_instance();

if ( ! function_exists( 'mp_network_shop_profile_get_settings' ) ) :
	function mp_network_shop_profile_get_settings( $blog_id = 0 ) {
		$defaults = array(
			'display_name'     => '',
			'tagline'          => '',
			'about'            => '',
			'hero_image_id'    => 0,
			'hero_image_url'   => '',
			'website_url'      => '',
			'support_url'      => '',
			'custom_links'     => '',
			'social_facebook'  => '',
			'social_instagram' => '',
			'social_tiktok'    => '',
			'social_linkedin'  => '',
			'social_youtube'   => '',
			'theme_primary'    => '#2f6ca3',
			'theme_accent'     => '#1e3348',
			'theme_bg_start'   => '#eef6ff',
			'theme_bg_end'     => '#f8fcff',
			'theme_card_bg'    => '#ffffff',
		);

		$target_blog = absint( $blog_id );
		if ( $target_blog <= 0 ) {
			$target_blog = get_current_blog_id();
		}

		$current_blog = get_current_blog_id();
		if ( $target_blog !== $current_blog ) {
			switch_to_blog( $target_blog );
		}

		$settings = (array) mp_get_setting( 'shop_profile', array() );
		$legacy   = (array) get_option( 'mp_shop_profile_settings', array() );
		if ( empty( $settings ) && ! empty( $legacy ) ) {
			$settings = $legacy;
		}

		if ( ! empty( $settings['hero_image_id'] ) ) {
			$settings['hero_image_id'] = absint( $settings['hero_image_id'] );
		}

		$settings = array_merge( $defaults, $settings );

		foreach ( array( 'theme_primary', 'theme_accent', 'theme_bg_start', 'theme_bg_end', 'theme_card_bg' ) as $color_key ) {
			$sanitized = sanitize_hex_color( $settings[ $color_key ] );
			$settings[ $color_key ] = $sanitized ? $sanitized : $defaults[ $color_key ];
		}

		if ( $target_blog !== $current_blog ) {
			restore_current_blog();
		}

		return $settings;
	}
endif;
