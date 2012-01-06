<?php
/**
 * Google OAuth API
 *
 * @link http://code.google.com/apis/accounts/docs/OAuth_ref.html
 * @link http://code.google.com/apis/accounts/docs/OAuthForInstalledApps.html
 */
class c2c_GoogleOAuth {

	// Parameterize user meta key names since they're used in multiple places.
	protected static $oa_anon_token_name  = 'c2c_gdata_oa_anon_token';
	protected static $oa_anon_secret_name = 'c2c_gdata_oa_anon_secret';
	protected static $oa_token_name       = 'c2c_gdata_oa_token';
	protected static $oa_secret_name      = 'c2c_gdata_oa_secret';

	// Private runtime variables
	private static $oauth_token   = '';
	private static $oauth_secret  = '';
	private static $error_message = '';

	/**
	 * Initializer, primarily for registering hooks.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'require_oauth_lib' ) );
		add_action( 'admin_init',     array( __CLASS__, 'oauth_handler' ) );
	}

	/**
	 * Requires the OAuth library.
	 *
	 * This is via a hook to (a) ensure it is only sourced in the admin, but
	 * mostly (b) to attempt to mitigate conflicts with another plugin
	 * already sourcing its own copy of the OAuth.php file. Presuming that
	 * it's the same file (good assumption) everything will be ok. In the
	 * end the copy of the OAuth.php file may need to have all of its
	 * classes renamed to something unique to avoid conflicts.
	 */
	public static function require_oauth_lib() {
		require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'OAuth.php' );
	}

	/**
	 * Handles Google Data OAuth processing if requested or in progress.
	 *
	 * Note: Injects HTTP headers.
	 */
	public static function oauth_handler() {
		if ( isset( $_REQUEST['lc_gd_login_type'] ) && $_REQUEST['lc_gd_login_type'] == 'oauth' )
			self::oauth_get_request_token();
		elseif ( isset( $_REQUEST['oauth_return'] ) )
			self::oauth_get_access_token();
		elseif( isset( $_REQUEST['error_message'] ) )
			die( 'GOT ERROR: ' . $_REQUEST['error_message'] );
	}

	/**
	 * Returns the URL for the page where the plugin is listening.
	 *
	 * @param array $query_args (optional) Array of query parameters to stringify and add to URL
	 * @return string The admin URL
	 */
	protected static function admin_url( $query_args = array() ) {
		$admin_path = apply_filters( 'c2c_google_contacts-base_admin_path', '' );
		$default    = apply_filters( 'c2c_google_contacts-base_query_args', array() );
		$query_args = wp_parse_args( $query_args, $default );
		if ( ! empty( $query_args ) )
			$admin_path .= '?' . build_query( $query_args );
		return admin_url( $admin_path );
	}

	/**
	 * Redirects to admin URL with an error message.
	 *
	 * @param string $msg The error message
	 */
	protected static function error_redirect( $msg ) {
		header( 'Location: ' . self::admin_url( array( 'error_message' => $msg ) ) );
		die('');
	}

	/**
	 * Returns a given Google OAuth request URL.
	 *
	 * @param string $type One of: request_token, authorize_token, access_token
	 */
	protected static function oauth_url( $type ) {
		$urls = array(
			'request_token'   => 'https://www.google.com/accounts/OAuthGetRequestToken',
			'authorize_token' => 'https://www.google.com/accounts/OAuthAuthorizeToken',
			'access_token'    => 'https://www.google.com/accounts/OAuthGetAccessToken'
		);
		return isset( $urls[$type] ) ? $urls[$type] : '';
	}

	/**
	 * Wrapper for making cURL requests.
	 *
	 * @param string $url The request URL
	 * @param array $settings (optional) Additional settings to configure via curl_setopt()
	 * @return cURL handle
	 */
	public static function curl_request( $url, $settings = array() ) {
		// Initialize cURL
		$ch = curl_init();

		// Return result rather than outputting it
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		// Trust any SSL certificate
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		// Configure the request URL
		curl_setopt( $ch, CURLOPT_URL,            $url );

		// Configure cURL
		foreach ( $settings as $k => $v )
			curl_setopt( $ch, $k, $v );

		// Return cURL handle
		return $ch;
	}

	/**
	 * Returns the URL for initializing OAuth authentication with remote service.
	 *
	 * @return string The URL
	 */
	public static function oauth_init_url() {
		return self::admin_url( array( 'lc_gd_login_type' => 'oauth' ) );
	}

	/**
	 * Gets request token from Google.
	 */
	private static function oauth_get_request_token() {
		$user_id = get_current_user_id();

		delete_user_meta( $user_id, self::$oa_anon_token_name );
		delete_user_meta( $user_id, self::$oa_anon_secret_name );

		$signature_method = new C2C_OAuthSignatureMethod_HMAC_SHA1();

		// TODO: The 'edit' and displayname should both be configured externally
		$params = array();
		$params['oauth_callback']     = c2c_LastContactedAdmin::import_page_url() . '&oauth_return=true';
		//self::admin_url( array( 'oauth_return' => 'true', 'edit' => 'dashboard_last_contacted#dashboard_last_contacted' ) );
		// TODO: Add filter to the scope
		$params['scope']              = c2c_GoogleContacts::$contacts_url; // Space seperated list of applications to be made accessible
		$params['xoauth_displayname'] = 'Last Contacted plugin for WordPress by coffee2code';

		$consumer = new C2C_OAuthConsumer( 'anonymous', 'anonymous', NULL );
		$request = C2C_OAuthRequest::from_consumer_and_token( $consumer, NULL, 'GET', self::oauth_url( 'request_token' ), $params );
		$request->sign_request( $signature_method, $consumer, NULL );

		$ch = self::curl_request( $request->to_url() );

		$oa_response = curl_exec( $ch );

		if ( curl_errno( $ch ) )
			self::error_redirect( curl_error( $ch ) );

		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		if ( $http_code == 200 ) {
			$access_params = wp_parse_args( $oa_response );

			update_user_meta( $user_id, self::$oa_anon_token_name,  $access_params['oauth_token'] );
			update_user_meta( $user_id, self::$oa_anon_secret_name, $access_params['oauth_token_secret'] );

			$url = self::oauth_url( 'authorize_token' ) . '?' . build_query( array( 'oauth_token' => $access_params['oauth_token'] ) );
			header( 'Location: ' . $url );
		} else {
			self::error_redirect( $oa_response );
		}

		die('');
	}

	/**
	 * Gets access token from Google.
	 */
	private static function oauth_get_access_token() {
		if ( ! current_user_can( 'manage_options' ) )
			die( __( 'Cheatin&#8217; uh?' ) );

		$signature_method = new C2C_OAuthSignatureMethod_HMAC_SHA1();

		$params = array();
		$params['oauth_verifier'] = $_REQUEST['oauth_verifier'];

		$consumer = new C2C_OAuthConsumer( 'anonymous', 'anonymous', NULL );

		$user_id = get_current_user_id();

		$upgrade_token = new C2C_OAuthConsumer(
			get_user_meta( $user_id, self::$oa_anon_token_name, true ),
			get_user_meta( $user_id, self::$oa_anon_secret_name, true )
		);

		$request = C2C_OAuthRequest::from_consumer_and_token( $consumer, $upgrade_token, 'GET', self::oauth_url( 'access_token' ), $params );

		$request->sign_request( $signature_method, $consumer, $upgrade_token );

		$ch = self::curl_request( $request->to_url() );

		$oa_response = curl_exec( $ch );

		if ( curl_errno( $ch ) )
			self::error_redirect( curl_error( $ch ) );

		delete_user_meta( $user_id, self::$oa_anon_token_name );
		delete_user_meta( $user_id, self::$oa_anon_secret_name );

		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		if ( $http_code == 200 ) {
		  $access_params = wp_parse_args( $oa_response );

		  update_user_meta( $user_id, self::$oa_token_name,  $access_params['oauth_token'] );
		  update_user_meta( $user_id, self::$oa_secret_name, $access_params['oauth_token_secret'] );

			// TODO: This needs to be handled generically
//		  header( "Location: " . self::admin_url( array( 'info_message' => __( 'Authenticated!' ), 'edit' => 'dashboard_last_contacted#dashboard_last_contacted' ) ) );
			header( "Location: " . c2c_LastContactedAdmin::import_page_url() . '&info_message=' . __( 'Authenticated!' ) );
		} else {
			self::error_redirect( $oa_response );
		}

		die('');
	}

	/**
	 * Checks if the current user is authenticated with the supplied service.
	 *
	 * @param string $service The label for the service
	 * @return bool True if authentication, else false
	 */
	public static function is_authenticated_with_service( $service ) {
		// TODO: Abstract
		$auth_token = self::get_oauth_token();
		return ! empty( $auth_token );
	}


	/**
	 * Returns the user's OAuth token value.
	 *
	 * @return string
	 */
	public static function get_oauth_token() {
		if ( empty( self::$oauth_token ) )
			self::$oauth_token = get_user_meta( get_current_user_id(), self::$oa_token_name, true );

		return self::$oauth_token;
	}

	/**
	 * Returns the user's OAuth secret value.
	 *
	 * @return string
	 */
	public static function get_oauth_secret() {
		if ( empty( self::$oauth_secret ) )
			self::$oauth_secret = get_user_meta( get_current_user_id(), self::$oa_secret_name, true );

		return self::$oauth_secret;
	}

	public static function createAuthHeader( $url = null, $request_type = null ) {
		if ( $url == NULL )
			error_log( 'No URL to sign.' );

		$signature_method = new C2C_OAuthSignatureMethod_HMAC_SHA1();

		$params = array();

		$consumer = new C2C_OAuthConsumer( 'anonymous', 'anonymous', NULL );

		$oauth_token  = self::get_oauth_token();
		$oauth_secret = self::get_oauth_secret();

		$token = new C2C_OAuthConsumer( $oauth_token, $oauth_secret );

		$oauth_req = C2C_OAuthRequest::from_consumer_and_token( $consumer, $token, $request_type, $url, $params );

		$oauth_req->sign_request( $signature_method, $consumer, $token );

		return $oauth_req->to_header();
	}

	/**
	 * Sets an error message.
	 *
	 * @param string $msg The error message
	 * @return false
	 */
	public static function set_error( $msg ) {
		self::$error_message = $msg;
		echo $msg;
		return false;
	}
}

c2c_GoogleOAuth::init();

?>