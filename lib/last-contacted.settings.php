<?php

/**
 *
 * This file encapsulates all the code related to storing, retrieving,
 * managing, and interacting with settings for the plugin.
 *
 */

if ( ! class_exists( 'c2c_LastContactedSettings' ) ) :

class c2c_LastContactedSettings {
	public static $admin_options_name = 'c2c_last_contacted';
	public static $settings_page      = '';
	public static $plugin_file        = __FILE__;

	private static $options           = null;

	public static function init() {
		add_action( 'load-index.php', array( __CLASS__, 'maybe_disable_dashboard_widget' ), 1 );
		add_action( 'plugins_loaded', array( __CLASS__, 'do_init' ) );
	}

	public static function do_init() {
		add_action( 'admin_init',                 array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init',                 array( __CLASS__, 'cron_update_contacts' ) );
		add_action( 'admin_menu',                 array( __CLASS__, 'admin_menu' ) );
		add_filter( 'last_contacted_admin_pages', array( __CLASS__, 'add_as_last_contacted_admin_page' ) );
	}

	public static function add_as_last_contacted_admin_page( $pages ) {
		$pages[] = __CLASS__ . '_settings';
		return $pages;
	}

	/**
	 * Creates the admin menu link and registers plugin action link.
	 */
	public static function admin_menu() {
		add_filter( 'plugin_action_links_last-contacted/last-contacted.php', array( __CLASS__, 'plugin_action_links' ) );
		self::$settings_page = add_submenu_page( 'c2c_LastContactedAdmin', 'Settings', 'Settings', 'manage_options', __CLASS__, array( __CLASS__, 'settings_page' ) );
	}

	/**
	 * Adds a 'Settings' link to the plugin action links.
	 *
	 * @param array $action_links Existing action links
	 * @return array Links associated with a plugin on the admin Plugins page
	 */
	public static function plugin_action_links( $action_links ) {
		$settings_link = '<a href="' . self::settings_page_url() . '">' . __( 'Settings' ) . '</a>';
		array_unshift( $action_links, $settings_link );
		return $action_links;
	}

	/**
	 * Returns the link to the settings page.
	 *
	 * @return string URL to settings page
	 */
	public static function settings_page_url() {
		return menu_page_url( __CLASS__, false );
	}

	/**
	 * Settings page.
	 *
	 */
	public static function settings_page() {
		load_template( c2c_LastContacted::get_template_path( '_flash.php' ) );
		load_template( c2c_LastContacted::get_template_path( 'settings.php' ) );
	}

	public static function register_settings() {
		register_setting( self::$admin_options_name, self::$admin_options_name, array( __CLASS__, 'validate_options' ) );
		add_settings_section( 'default', '', '__return_false', self::$plugin_file );
		add_settings_field( 'enable_dashboard_widget', __( 'Enable admin dashboard widget?' ),
			array( __CLASS__, 'display_option' ),
			self::$plugin_file,
			'default',
			array( 'label_for' => 'enable_dashboard_widget' ) );
		add_settings_field( 'use_gravatar', __( 'Use Gravatars?' ),
			array( __CLASS__, 'display_option' ),
			self::$plugin_file,
			'default',
			array( 'label_for' => 'use_gravatar' ) );
		add_settings_field( 'cron_interval', __( 'Interval for auto-updating contacts' ),
			array( __CLASS__, 'display_option' ),
			self::$plugin_file,
			'default',
			array( 'label_for' => 'cron_interval' ) );
	}

	public static function validate_options( $options ) {
		$options['cron_interval']           = ( in_array( $options['cron_interval'], array( 'never', 'daily', 'weekly', 'monthly' ) ) ? $options['cron_interval'] : 'never' );
		$options['enable_dashboard_widget'] = ( $options['enable_dashboard_widget'] == 1 ? 1 : 0 );
		$options['use_gravatar']            = ( $options['use_gravatar'] == 1 ? 1 : 0 );

		return $options;
	}

	public static function get_options() {
		if ( ! self::$options )
			self::$options = get_option( self::$admin_options_name );
		return self::$options;
	}

	public static function display_option( $opt ) {
		$options = self::get_options();
		$field = $opt['label_for'];
		if ( 'enable_dashboard_widget' == $field ) {
			$val = isset( $options[$field] ) ? $options[$field] : 0;
			echo '<fieldset><legend class="screen-reader-text">Enable admin dashboard widget?</legend>';
			echo '<label for="' . self::$admin_options_name . '[enable_dashboard_widget]">';
			echo '<input name="' . self::$admin_options_name .
				'[enable_dashboard_widget]" id="lc_option_enable_dashboard_widget" type="checkbox" value="1" ' .
				checked( 1, $val, false ) . ' /> &nbsp;';
			echo '</label></fieldset>';
		} elseif ( 'use_gravatar' == $field ) {
			$val = isset( $options[$field] ) ? $options[$field] : 1;
			echo '<fieldset><legend class="screen-reader-text">Use Gravatars?</legend>';
			echo '<label for="' . self::$admin_options_name . '[use_gravatar]">';
			echo '<input name="' . self::$admin_options_name .
				'[use_gravatar]" id="lc_option_use_gravatar" type="checkbox" value="1" ' .
				checked( 1, $val, false ) . ' /> &nbsp;';
			echo '</label></fieldset>';
		} elseif ( 'cron_interval' == $field ) {
			$val = isset( $options[$field] ) ? $options[$field] : 'never';
			echo '<label for="' . self::$admin_options_name . '[cron_interval]">';
			echo '<select name="' . self::$admin_options_name . '[cron_interval]" id="lc_option_cron_interval">';
			foreach ( array( 'never', 'daily', 'weekly', 'monthly' ) as $o )
				echo "<option value='$o'" . selected( $val, $o ) . ">$o</option>";
			echo '</select>';
			echo '</label><span class="description">Regardless of this value, you can manually sync the contacts data at any time via the plugin\'s Import page.</span></fieldset>';
		}
	}

	/**
	 * Disables the dashboard widget if configured to do so.
	 */
	public static function maybe_disable_dashboard_widget() {
		$options = self::get_options();

		if ( ! $options['enable_dashboard_widget'] )
			define( 'DISABLE_LAST_CONTACTED_DASHBOARD', true );
	}

	/**
	 * A pseudo-cron approach to auto-updating contact data.
	 *
	 * May eventually use wp-cron.
	 */
	public static function cron_update_contacts() {
		$options = self::get_options();

		$interval = $options['cron_interval'];

		if ( 'never' == $interval )
			return;

// TODO: Dynamically get contact sources once that feature has been implemented
		$contact_sources = array( 'google' );
		foreach( $contact_sources as $source ) {
			$last_imported = get_user_meta( get_current_user_id(), 'lc_last_imported_' . $source, true ); // TODO: abstract
			// If the user has never imported from this source, don't do so here
			if ( empty( $last_imported ) )
				continue;
			$then = strtotime( $last_imported );
			$now  = strtotime( current_time( 'mysql' ) );
			$diff = $now - $then;
			if ( ( 'monthly' == $interval && $diff > 2419200 ) || // month+
				 ( 'weekly' == $interval && $diff > 604800 ) || // week+
				 ( 'daily' == $interval && $diff > 86400 ) ) { // day+
				c2c_LastContacted::handle_import( array( $source ) );
			}
		}
	}
}

c2c_LastContactedSettings::init();

endif;

?>