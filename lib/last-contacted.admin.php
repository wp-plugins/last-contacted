<?php

if ( ! class_exists( 'c2c_LastContactedAdmin' ) ) :

class c2c_LastContactedAdmin {

	public static $admin_page    = '';
	public static $import_page   = '';
	private static $class_id     = __CLASS__;

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'do_init' ) );
		require_once( 'last-contacted.settings.php' );
	}

	public static function do_init() {
		global $pagenow;

		// Add admin menu
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );

		// Filter query args for Google Contacts API
		add_filter( 'c2c_google_contacts-base_query_args', array( __CLASS__, 'filter_google_contacts_oauth_query' ) );
		add_filter( 'c2c_google_contacts-base_admin_path', array( __CLASS__, 'filter_google_contacts_admin_page' ) );

		// Register and enqueue global admin styles
		add_action( 'admin_init',                          array( __CLASS__, 'register_styles' ) );
		add_action( 'admin_print_styles',                  array( __CLASS__, 'enqueue_general_css' ) );

		// Do plugin page specific things
		$pages = apply_filters( 'last_contacted_admin_pages', array( __CLASS__, __CLASS__ . '_import' ) );
		if ( basename( $pagenow, '.php' ) == 'admin' && isset( $_REQUEST['page'] ) && in_array( $_REQUEST['page'], $pages ) ) {
			// Maybe import contact data
			add_action( 'admin_init',                      array( __CLASS__, 'maybe_import' ) );
			// Enqueues JS for admin page
			add_action( 'admin_enqueue_scripts',           array( __CLASS__, 'enqueue_admin_js' ) );
			// Register and enqueue styles for admin page
			add_action( 'admin_print_styles',              array( __CLASS__, 'enqueue_admin_css' ) );
		}
	}

	/**
	 * Enqueues JS.
	 */
	public static function enqueue_admin_js() {
		wp_enqueue_script( 'jquery' );
//		wp_enqueue_script( 'jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/jquery-ui.min.js', array(), '1.8.16', true );
		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-ui-autocomplete' );
		wp_enqueue_script( 'jquery-effects-highlight' );
		wp_enqueue_script( 'jquery-tooltip',    c2c_LastContacted::get_javascript_url( 'jquery.tools.min.js' ), array( 'jquery' ), '1.2.6', true );
		wp_enqueue_script( 'jquery-scrollto',   c2c_LastContacted::get_javascript_url( 'jquery.scrollTo-min.js' ), array( 'jquery' ), '1.2.6', true );
		wp_enqueue_script( 'c2c_LastContacted', c2c_LastContacted::get_javascript_url( 'common.js' ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-datepicker', 'jquery-ui-autocomplete','jquery-effects-highlight' ), c2c_LastContacted::version(), true );
		wp_enqueue_script( self::$class_id,     c2c_LastContacted::get_javascript_url( 'admin.js' ), array( 'jquery-tooltip', 'jquery-scrollto', 'c2c_LastContacted' ), c2c_LastContacted::version(), true );
		c2c_LastContacted::localize_shared_script();
		wp_localize_script( self::$class_id, self::$class_id, array(
		) );
	}

	/**
	 * Registers styles.
	 */
	public static function register_styles() {
		wp_register_style( 'jquery-ui',                 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/themes/base/jquery-ui.css' );
		wp_register_style( 'jquery-ui-theme',           c2c_LastContacted::get_css_url( 'jquery.ui.theme.css' ) );
		wp_register_style( 'c2c-last-contacted-shared', c2c_LastContacted::get_css_url( 'common.css' ) );
		wp_register_style( self::$class_id,             c2c_LastContacted::get_css_url( 'admin.css' ) );
		wp_register_style( self::$class_id . '_general', c2c_LastContacted::get_css_url( 'general.css' ) );
	}

	/**
	 * Enqueues stylesheets.
	 */
	public static function enqueue_admin_css() {
		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_style( 'jquery-ui-theme' );
		wp_enqueue_style( 'c2c-last-contacted-shared' );
		wp_enqueue_style( self::$class_id );
	}

	/**
	 * Enqueues general stylesheets.
	 *
	 * @since 0.9.13
	 */
	public static function enqueue_general_css() {
		wp_enqueue_style( self::$class_id  . '_general' );
	}

	/**
	 * Creates the admin menu link and registers plugin action link.
	 */
	public static function admin_menu() {
		add_filter( 'plugin_action_links_last-contacted/last-contacted.php', array( __CLASS__, 'plugin_action_links' ) );
		self::$admin_page = add_menu_page( 'Last Contacted', 'Last Contacted', 'manage_options', __CLASS__, array( __CLASS__, 'admin_page' ) );
		self::$import_page = add_submenu_page( __CLASS__, 'Import', 'Import', 'manage_options', __CLASS__ . '_import', array( __CLASS__, 'import_page' ) );

		add_action( 'load-' . self::$import_page, array( __CLASS__, 'maybe_prefetch_gravatar' ) );
	}

	/**
	 * Returns the link to the admin page.
	 *
	 * @return string URL to admin page
	 */
	public static function admin_page_url() {
		return menu_page_url( __CLASS__, false );
	}

	/**
	 * Returns the link to the import page.
	 *
	 * @since 0.9.8
	 *
	 * @return string URL to import page
	 */
	public static function import_page_url() {
		return menu_page_url( __CLASS__ . '_import', false );
	}

	/**
	 * Adds a 'Contacts' link to the plugin action links.
	 *
	 * @param array $action_links Existing action links
	 * @return array Links associated with a plugin on the admin Plugins page
	 */
	public static function plugin_action_links( $action_links ) {
		$settings_link = '<a href="' . self::admin_page_url() . '">' . __( 'Contacts' ) . '</a>';
		array_unshift( $action_links, $settings_link );
		return $action_links;
	}

	/**
	 * Is the plugin currently processing an import request?
	 *
	 * @return bool True if processing import request, false if not
	 */
	private static function is_processing_import() {
		return ( get_query_var( 'page' ) == __CLASS__ . '_import' );
	}

	/**
	 * Displays the admin page.
	 */
	public static function admin_page() {
		echo '<h2>' . __( 'Last Contacted' ) . '</h2>';

		$conds = array( 'orderby' => 'post_title' );

		if ( c2c_LastContacted::is_showing_hidden_groups() )
			$conds['post_status'] = array( 'draft' );

		$GLOBALS['lc_groups_query'] = c2c_LastContacted::get_groups( $conds );

		load_template( c2c_LastContacted::get_template_path( '_flash.php' ) );
		load_template( c2c_LastContacted::get_template_path( 'groups.php' ) );
	}

	/**
	 * Settings page.
	 *
	 */
	public static function import_page() {
		load_template( c2c_LastContacted::get_template_path( '_flash.php' ) );
		load_template( c2c_LastContacted::get_template_path( 'import.php'  ) );
	}

	/**
	 * Returns the base admin page used by the plugin.
	 *
	 * Intended to override setting used by Google Contacts API plugin for
	 * generating URLs back to this plugin.
	 *
	 * @return string Base admin page
	 */
	public static function filter_google_contacts_admin_page( $admin_page ) {
		if ( self::is_processing_import() )
			$admin_page = 'admin.php';
		return $admin_page;
	}

	/**
	 * Adds default query args used to generate link back to plugin's import
	 * page.
	 *
	 * Intended to override setting used by Google Contacts API plugin for
	 * generating URLs back to this plugin.
	 *
	 * @return string Base admin page
	 */
	public static function filter_google_contacts_oauth_query( $query_args ) {
		if ( self::is_processing_import() )
			$query_args = wp_parse_args( array( 'page' => __CLASS__ . '_import' ), $query_args );
		return $query_args;
	}

	/**
	 * Imports contacts.
	 *
	 * Primarily recognizes the import request and defers processing to
	 * c2c_LastContacted::handle_import().
	 */
	public static function maybe_import() {
		if ( ! isset( $_POST['action'] ) || $_POST['action'] != 'lc_import' )
			return;

		return c2c_LastContacted::handle_import();
	}

	/**
	 * Query Gravatar for each user to determine if they have an avatar.
	 *
	 * @since 0.9.11
	 *
	 */
	public static function maybe_prefetch_gravatar() {
		if ( isset( $_POST['action'] ) && $_POST['action'] == 'lc_import_gravatar' ) {
			// Get list of all contact IDs
			$posts = c2c_LastContacted::get_contacts( null, array(
				'fields'      => array( 'ID', 'post_type' ),
				'post_status' => array( 'publish', 'draft' ),
			), ARRAY_N );

			$do_import = isset( $_POST['import_gravatar'] );

			foreach ( $posts as $post ) {
				delete_post_meta( $post->ID, '_lc_no_gravatar' );
				if ( $do_import )
					lc_get_avatar( '16', array( 'contact_id' => $post->ID, 'return_as_html' => false, 'validate_gravatar' => true ) );
			}

			if ( $do_import )
				$msg = __( 'Gravatar information has been fetched.' );
			else
				$msg = __( 'Pre-fetched Gravatar information has been cleared.' );
			add_settings_error('general', 'settings_updated', $msg, 'updated');
		}
	}

}

c2c_LastContactedAdmin::init();

endif;
?>