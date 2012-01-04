<?php
/**
 * Google Contacts Data API
 *
 * @link http://code.google.com/apis/contacts/
 * @link http://code.google.com/apis/contacts/docs/3.0/developers_guide.html
 */
class c2c_GoogleContacts {

	// URL for API access to Google Contacts data
	public static $contacts_url = 'https://www.google.com/m8/feeds/';

	// Private runtime variables
	private static $error_message = '';

	/**
	 * Initializer, primarily for registering hooks.
	 */
	public static function init() {
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
	 * Performs Google Data API call.
	 *
	 * @param string $action Action name
	 * @param string $id (optional) Id of specific element being queried
	 * @param array $query_args (optional) Array of query parameters
	 * @return false|SimpleXMLElement False if an error occurred, or XML-parsed object
	 */
	protected static function api_call( $action, $id = '', $query_args = array() ) {
		$defaults = empty( $id ) ? array( 'max-results' => '999999' ) : array();
		$query_args = wp_parse_args( $query_args, $defaults );
		$query = build_query( $query_args );

		$url = self::$contacts_url . $action . '/default/full';
		if ( ! empty( $id ) )
			$url .= '/' . $id;
		if ( ! empty( $query ) )
			$url .= '?' . $query;

		$ch = c2c_GoogleOAuth::curl_request( $url, array(
			CURLOPT_HTTPHEADER => array(
				c2c_GoogleOAuth::createAuthHeader( $url, 'GET' ),
				'GData-Version: 3.0'
			)
		) );

		$return = curl_exec( $ch );

		if ( curl_errno( $ch ) ) {
			curl_close( $ch );
			return c2c_GoogleOAuth::set_error( curl_error( $ch ) );
		}

		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		curl_close( $ch );

		if ( $http_code != 200 )
			return c2c_GoogleOAuth::set_error( $return );

		self::$error_message = '';

		// SimpleXMLElement doesn't parse namespaced tag names, so remove namespace
		$return = preg_replace( '/<(\/?)([a-z]+):([a-z]+)/i', '<$1$3', $return );
		// SimpleXMLElement doesn't parse namespaced attribute names, so remove namespace
		$return = preg_replace( '/(\s+)[a-z]+:([a-z]+=)/i', '$1$2', $return );

		return new SimpleXMLElement( $return );
	}

	//
	//
	// PUBLIC METHODS
	//
	//

	/**
	 * Returns a list of all contact groups.
	 *
	 * @param array $api_args (optional) API arguments
	 * @return array Array of groups. Each group is an associative array with:
	 *           'id', 'name'
	 */
	public static function get_groups( $api_args = array() ) {
		$xml = self::api_call( 'groups', '', $api_args );
		if ( ! $xml )
			return;

		$groups = array();
		foreach ( $xml->entry as $entry ) {
			if ( strpos( (string)$entry->title, 'System Group:' ) === 0 )
				continue;
			$groups[] = array(
				'id'    => (string)$entry->id,
				'name'  => (string)$entry->title
			);
		}

		return $groups;
	}

	/**
	 * Returns a list of all contacts.
	 *
	 * @param array $api_args (optional) API arguments
	 * @return array Array of contacts. Each contact is an associative array with:
	 *           'id', 'name', 'phone', 'email'
	 */
	public static function get_contacts( $api_args = array() ) {
		$xml = self::api_call( 'contacts', '', $api_args );
		if ( ! $xml )
			return;

		$contacts = array();
		foreach ( $xml->entry as $entry ) {
			$email = is_array( $entry->email ) ? array_shift( $entry->email ) : $entry->email;
			$contacts[] = array(
				'id'    => array_pop( explode( '/', (string)$entry->id ) ),
				'name'  => (string)$entry->title,
				'phone' => (string)$entry->phoneNumber,
				'email' => (string)$email['address']
			);
		}

		return $contacts;
	}

	/**
	 * Returns data for a contact.
	 *
	 * @param int $contact_id The Google Data id of the contact
	 * @param array $api_args (optional) API arguments
	 * @return array An associative array with:
	 *           'id', 'name', 'phone', 'email'
	 */
	public static function get_contact( $contact_id, $api_args = array() ) {
		$entry = self::api_call( 'contacts', $contact_id, $api_args );
		if ( ! $entry )
			return;

		$email = is_array( $entry->email ) ? array_shift( $entry->email ) : $entry->email;
		return array(
			'id'    => array_pop( explode( '/', (string)$entry->id ) ),
			'name'  => (string)$entry->title,
			'phone' => (string)$entry->phoneNumber,
			'email' => (string)$email['address']
		);

	}

}

c2c_GoogleContacts::init();

?>