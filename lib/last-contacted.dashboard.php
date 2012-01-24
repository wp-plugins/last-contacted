<?php

if ( ! class_exists( 'c2c_LastContactedDashboard' ) ) :

class c2c_LastContactedDashboard {

	private static $widget_id  = __CLASS__;

	public static function init() {
		add_action( 'load-index.php', array( __CLASS__, 'hooks_on_index' ) );
	}

	/**
	 * Hooks to fire on admin index.php page.
	 *
	 * @since 0.9.10
	 */
	public static function hooks_on_index() {
		if ( defined( 'DISABLE_LAST_CONTACTED_DASHBOARD' ) && DISABLE_LAST_CONTACTED_DASHBOARD )
			return;

		// Register and enqueue styles for dasboard widget
		self::register_styles();
		// Save the dashboard widget's configuration submission
		self::save_dashboard_settings();
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_css' ) );
		// Enqueues JS for dashboard widget
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_js' ) );
		// Add the admin dashboard widget
		add_action( 'wp_dashboard_setup',    array( __CLASS__, 'add_dashboard_widget' ) );
	}

	/**
	 * Enqueues JS.
	 */
	public static function enqueue_admin_js() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui',
			'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/jquery-ui.min.js', array( 'jquery' ), '1.8.16', true );
		wp_enqueue_script( 'jquery-scrollto',
			c2c_LastContacted::get_javascript_url( 'jquery.scrollTo-min.js' ), array( 'jquery' ), '1.2.6', true );
		wp_enqueue_script( 'c2c_LastContacted',
			c2c_LastContacted::get_javascript_url( 'common.js' ), array( 'jquery' ), '0.1', true );
		wp_enqueue_script( self::$widget_id,
			c2c_LastContacted::get_javascript_url( 'dashboard.js' ),
			array( 'jquery', 'jquery-ui', 'jquery-scrollto', 'c2c_LastContacted' ), '0.1', true );
		c2c_LastContacted::localize_shared_script();
		wp_localize_script( self::$widget_id, self::$widget_id, array(
		) );
	}

	/**
	 * Registers styles.
	 */
	public static function register_styles() {
		wp_register_style( 'jquery-ui',                 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.16/themes/base/jquery-ui.css' );
		wp_register_style( 'jquery-ui-theme',           c2c_LastContacted::get_css_url( 'jquery.ui.theme.css' ) );
		wp_register_style( 'jquery-ui-accordion',       c2c_LastContacted::get_css_url( 'jquery.ui.accordian.css' ) );
		wp_register_style( 'c2c-last-contacted-shared', c2c_LastContacted::get_css_url( 'common.css' ) );
		wp_register_style( self::$widget_id,            c2c_LastContacted::get_css_url( 'dashboard.css' ) );
	}

	/**
	 * Enqueues stylesheets.
	 */
	public static function enqueue_admin_css() {
		wp_enqueue_style( 'jquery-ui' );
		wp_enqueue_style( 'jquery-ui-theme' );
		wp_enqueue_style( 'jquery-ui-accordion' );
		wp_enqueue_style( 'c2c-last-contacted-shared' );
		wp_enqueue_style( self::$widget_id );
	}

	/**
	 * Adds the dashboard widget.
	 */
	public static function add_dashboard_widget() {
		wp_add_dashboard_widget( 'dashboard_last_contacted', __( 'Last Contacted', c2c_LastContacted::$textdomain ),
				array( __CLASS__, 'dashboard' ), array( __CLASS__, 'dashboard_configure' ) );
	}

	/**
	 * Shows the admin dashboard.
	 */
	public static function dashboard() {
		$GLOBALS['lc_groups_query'] = c2c_LastContacted::get_groups();
		load_template( c2c_LastContacted::get_template_path( 'groups.php' ) );
	}

	/**
	 * Show the dashboard's configuration options.
	 */
	public static function dashboard_configure() {
		load_template( c2c_LastContacted::get_template_path( '_flash.php' ) );
		load_template( c2c_LastContacted::get_template_path( '_import.php' ) );
	}

	/**
	 * Saves the dashboard settings.
	 */
	public static function save_dashboard_settings() {
		if ( ! isset( $_POST['widget_id'] ) || $_POST['widget_id'] != 'dashboard_last_contacted' )
			return;

		return c2c_LastContacted::handle_import();
	}

}

c2c_LastContactedDashboard::init();

endif;
?>