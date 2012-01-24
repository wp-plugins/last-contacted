<?php
/**
 * @package Last_Contacted
 * @author Scott Reilly
 * @version 0.9.14
 */
/*
Plugin Name: Last Contacted
Version: 0.9.14
Plugin URI: http://coffee2code.com/wp-plugins/last-contacted/
Author: Scott Reilly
Author URI: http://coffee2code.com/
Text Domain: last-contacted
Description: Easily keep track of the last time you interacted with your contacts.

Compatible with WordPress 3.3+
*/

/*
Copyright (c) 2011-2012 by Scott Reilly (aka coffee2code)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

if ( ! class_exists( 'c2c_LastContacted' ) ) :

class c2c_LastContacted {

	/**
	 * Name of the post type for contacts
	 */
	public static $contact_post_type        = 'c2c_last_contacted';

	/**
	 * Name of the post type for contact groups
	 */
	public static $group_post_type          = 'c2c_contact_group';

	/**
	 * Name of the taxonomy used to associate contacts with groups
	 */
	public static $contacts_groups_taxonomy = 'c2c-contacts-groups';

	/**
	 * Name of the taxonomy used to tag the service that acted as source for
	 * contact information
	 */
	public static $contacts_source_taxonomy = 'c2c-last-contacted-source';

	/**
	 * Date format for display.
	 */
	public static $date_format = 'Y-m-d';

	/**
	 * Name of the textdomain
	 */
	public static $textdomain  = 'last-contacted';

	/**
	 * Memoized path to the templates directory.
	 */
	protected static $template_dir    = '';

	/**
	 * Enable the admin dashboard widget?
	 */
	public static $enable_dashboard   = true;

	/**
	 * Enable the admin dashboard widget?
	 */
	public static $enable_admin_page  = true;

	private static $public_post_types = true; // Currently only used to expose post types for debugging

	private static $widget_id  = 'c2c_LastContacted';

	// For temporary data storage
	private static $post_date_comparator = '';
	private static $post_date = '';
	private static $post_fields = '';
	private static $posts_orderby = '';
	private static $starts_with = '';

	/**
	 * Returns version for the plugin.
	 *
	 * @since 0.9.8
	 */
	public static function version() {
		return '0.9.14';
	}

	/**
	 * Initializes the plugin.
	 */
	public static function init() {
		// Don't include comments for contact post type
		add_filter( 'comments_clauses',        array( __CLASS__, 'prevent_notes_listed_as_comments' ), 11, 2 );

		// Everything afterwards applies to admin pages only.

		if ( ! is_admin() )
			return;

		// Load localization
		add_action( 'admin_init',              array( __CLASS__, 'load_textdomain' ) );

		// Register post types, post stati, and taxonomies
		add_action( 'init',                    array( __CLASS__, 'register_post_types' ) );
		add_action( 'init',                    array( __CLASS__, 'register_post_stati' ) );
		add_action( 'init',                    array( __CLASS__, 'register_taxonomies' ) );

		// If the post type is exposed via the admin, then its meta may need to be saved
		if ( self::$public_post_types )
			add_action( 'save_post',           array( __CLASS__, 'save_meta' ), 10, 2 );

		// Configure AJAX actions
		add_action( 'wp_ajax_lc_hide_contact', array( __CLASS__, 'ajax_hide_item' ) );
		add_action( 'wp_ajax_lc_show_contact', array( __CLASS__, 'ajax_show_item' ) );
		add_action( 'wp_ajax_lc_hide_group',   array( __CLASS__, 'ajax_hide_item' ) );
		add_action( 'wp_ajax_lc_show_group',   array( __CLASS__, 'ajax_show_item' ) );
		add_action( 'wp_ajax_lc_save_note',    array( __CLASS__, 'ajax_save_note' ) );
		add_action( 'wp_ajax_lc_search_contacts', array( __CLASS__, 'ajax_search_contacts' ) );

		// Only do these things on the dashboard page
		add_action( 'load-index.php',          array( __CLASS__, 'on_load' ) );

// TODO: TEMP: For debugging purposes only. Delete prior to release.
add_action( 'load-index.php', array( __CLASS__, 'backdoor_delete' ) );

		// TODO: Google Contacts should register itself with this plugin rather than be bundled
		// Source the Google Contacts Data API
		require_once( 'lib/google-oauth.php' );
		require_once( 'lib/google-contacts.php' );

		if ( self::$enable_admin_page )
			require_once( 'lib/last-contacted.admin.php' );

		if ( self::$enable_dashboard )
			require_once( 'lib/last-contacted.dashboard.php' );

		require_once( 'lib/last-contacted.template.php' );
	}

/**
 * DEBUGGING ONLY: DELETES ALL GROUPS, CONTACTS, and RELATED DATA
 *
 * TODO: TEMP: For debugging purposes only. Delete prior to release.
 *
 * Just visit http://site/wp-admin?lc_backdoor_delete=yes
 */
public static function backdoor_delete() {
	if ( isset( $_GET['lc_backdoor_delete'] ) && $_GET['lc_backdoor_delete'] == 'yes' ) {
		$posts = get_posts( array(
			'post_status' => array( 'publish', 'draft', 'orphan' ),
			'posts_per_page' => '-1',
			'post_type' => array( self::$contact_post_type, self::$group_post_type )
		) );

		foreach ( $posts as $post )
			wp_delete_post( $post->ID, true );

		wp_redirect( admin_url( '' ) );
	}
}

	/**
	 * Loads the localization textdomain for the plugin.
	 *
	 * Translations go into 'lang' sub-directory.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( self::$textdomain, false, basename( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'lang' );
	}

	/**
	 * Filters to fire on admin index.php.
	 *
	 * @since 0.9.9
	 */
	public static function on_load() {
		// Hack to prevent read privileges for post types created by this plugin so that their
		// comments don't get listed in the stock Recent Comments admin dashboard widget.
		add_filter( 'user_has_cap', array( __CLASS__, 'dont_show_notes_in_recent_comments_widget' ), 1, 3 );
	}

	/**
	 * Registers post stati.
	 *
	 */
	public static function register_post_stati() {
		register_post_status( 'orphan', array(
			'label'                     => __( 'Orphan' ),
			'label_count'               => _n_noop( 'Orphan <span class="count">(%s)</span>', 'Orphans <span class="count">(%s)</span>' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_status_list' => true,
			'show_in_admin_all_list'    => true
		) );
	}

	/**
	 * Registers post types.
	 *
	 * One post type is for the contact groups. The other is for the contacts themselves.
	 */
	public static function register_post_types() {
		register_post_type( self::$contact_post_type, array(
			'label'               => __( 'Last Contacted', self::$textdomain ),
			'public'              => self::$public_post_types,
			'hierarchical'        => true,
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
			'register_meta_box_cb' => array( __CLASS__, 'add_contact_metabox' )
		) );
		register_post_type( self::$group_post_type, array(
			'label'               => __( 'Contact Groups', self::$textdomain ),
			'public'              => self::$public_post_types,
			'hierarchical'        => true,
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' )
		) );
	}

	/**
	 * Registers taxonomies.
	 *
	 * Currently, only a single taxonomy is used, to designate the service
	 * the contact was originally imported from.
	 */
	public static function register_taxonomies() {
		register_taxonomy( self::$contacts_groups_taxonomy, array( self::$contact_post_type ), array(
			'label'             => __( 'Contact\'s Groups', self::$textdomain ),
			'public'            => self::$public_post_types,
			'show_in_nav_menus' => false,
			'query_var'         => false,
			'rewrite'           => false
		) );
		register_taxonomy( self::$contacts_source_taxonomy, array( self::$contact_post_type, self::$group_post_type ), array(
			'label'             => __( 'Contact Source', self::$textdomain ),
			'public'            => self::$public_post_types,
			'show_in_nav_menus' => false,
			'query_var'         => false,
			'rewrite'           => false
		) );
	}

	/**
	 * Provide localized strings for JavaScript.
	 */
	public static function localize_shared_script() {
		wp_localize_script( self::$widget_id, self::$widget_id, array(
			'plugin_dir' => plugins_url( '', __FILE__ )
		) );
	}

	/**
	 * Don't allow comments to the contact post_type from being included in general
	 * comment queries unless explicitly being requested.
	 *
	 * @since 0.9.8
	 *
	 * @param array $pieces The components of the comments query
	 * @param WP_Comment_Query $comment_query_obj The comment query object
	 * @return array The potentially modified query
	 */
	public static function prevent_notes_listed_as_comments( $pieces, $comment_query_obj ) {
		global $wpdb;
		$qv = $comment_query_obj->query_vars;

		// If the request is for a specific post's comments, or a specific comment, allow the request.
		if (	( isset( $qv['ID'] ) && ! empty( $qv['ID'] ) ) ||
				( isset( $qv['post_ID'] ) && ! empty( $qv['post_ID'] ) ) ||
				( isset( $qv['post_id'] ) && ! empty( $qv['post_id'] ) ) )
			return $pieces;

		// If the comment_query_obj wasn't specifically requesting the post_type, then its comments must be excluded
		if ( ! isset( $qv['post_type'] ) || empty( $qv['post_type'] ) || ! in_array( self::$contact_post_type, (array) $qv['post_type'] ) ) {
			// Join on the posts table if it isn't already being joined.
			// Note: assuming if a JOIN is present, that it's on the posts table. Could regexp to be certain.
			if ( empty( $pieces['join'] ) )
				$pieces['join'] = " JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->comments.comment_post_ID ";

			// Modify the WHERE clause
			if ( ! empty( $pieces['where'] ) )
				$pieces['where'] .= ' AND ';
			$pieces['where'] .= $wpdb->prepare( " ( {$wpdb->posts}.post_type != %s ) ", self::$contact_post_type );
		}
		return $pieces;
	}

	/**
	 * Hack to prevent stock 'Recent Comments' admin dashboard widget from
	 * displaying notes made about contacting contacts.
	 *
	 * The 'Recent Comments' widget displays all comments in the system
	 * regardless of comment_type or post_type. There does not appear to be a
	 * hook in place in order to change this. In order to prevent notes made
	 * about contacting a contact from appearing, this function is meant
	 * to be called when hooking the 'user_has_cap' filter to make it look like
	 * the user doesn't have read capabilities for the post.
	 *
	 * @param array $allcaps Array of all capabilities the user has
	 * @param string $cap The cap the user is being checked for
	 * @param array $args Array of arguments sent during request for cap check
	 * @return array
	 */
	public static function dont_show_notes_in_recent_comments_widget( $allcaps, $caps, $args ) {
		// This hides the plugin's known comment types from being listed on the
		// admin dashboard from anything that checks if the user has access to read the
		// comment's post.
		// TODO: Add a way to circumvent this check.
		if ( ! in_array( 'read', (array)$caps ) )
			return $allcaps;

		if ( isset( $args[2] ) ) {
			$p = get_post( absint( $args[2] ) );
			if ( $p && in_array( $p->post_type, array( self::$contact_post_type, self::$group_post_type ) ) )
				$allcaps = array();
		}
		return $allcaps;
	}

	/**
	 * Handle the kickoff of the import process.
	 *
	 * @param null|string|array $sources Names of sources to import. If null, then get from $_POST['lc_contact_sources']. If 'all', then gets from all
	 */
	public static function handle_import( $sources = null ) {
		$valid_contact_sources = array( 'google' ); // TODO: Abstract
		$set_message = false;
		if ( 'all' == $sources ) {
			$sources = $valid_contact_sources;
		} elseif ( is_null( $sources ) ) {
			$set_message = true;
			if ( ! isset( $_POST['lc_contact_sources'] ) ) {
				$_REQUEST['error_message'] = __( 'Please pick a service to import contacts from.' );
				return;
			}
			check_admin_referer( self::get_nonce( 'lc_do_import', get_current_user_id() ) );
			$sources = (array)$_POST['lc_contact_sources'];
		}

		foreach ( $sources as $source ) {
			if ( ! in_array( $source, $valid_contact_sources ) )
				continue;

			// TODO: Abstract call to contact source importer.
			// TODO: Error checking
			// For now, coded to import from Google Contacts.
			c2c_LastContacted::import_groups( c2c_GoogleContacts::get_groups() );
			// Record import date
			update_user_meta( get_current_user_id(), 'lc_last_imported_' . $source, current_time( 'mysql' ) );
		}

		if ( $set_message )
			$_REQUEST['info_message'] = __( 'Contacts successfully imported!' );
	}

	/**
	 * Import groups.
	 *
	 * @param array $groups The groups to import
	 */
	protected static function import_groups( $groups ) {
		$begin_date = current_time( 'mysql' );
		foreach ( (array) $groups as $group ) {
			$existing_group = self::get_groups(
				array(
					'post_status' => array( 'publish', 'draft', 'orphan' ),
					'meta_query'  => array( array( 'key' => '_lc_id', 'value' => $group['id'] ) )
				),
				ARRAY_N
			);
			if  ( empty( $existing_group ) ) {
				$post = array(
					'post_status' => 'publish',
					'post_type'   => self::$group_post_type,
					'post_title'  => $group['name']
				);
				$id = wp_insert_post( $post );
			} else {
				$post_obj = $existing_group[0];
				$post     = array(
					'ID'         => $post_obj->ID,
					'post_title' => $group['name']
				);
				if ( 'orphan' == $post_obj->post_status )
					$post['post_status'] = 'publish';
				$id = wp_update_post( $post );
			}
			update_post_meta( $id, '_lc_id', $group['id'] );
			self::import_contacts( $id, $group );
		}

		// Now handle groups that haven't been added or updated.
		// These are basically orphaned groups.

		$orphaned_groups = c2c_LastContacted::get_groups( array(
			'post_modified' => $begin_date,
			'post_status' => array( 'publish', 'draft' )
		), ARRAY_N );
		foreach ( $orphaned_groups as $orphan ) {
			$count_contacts = count( self::get_contacts( $orphan->ID, array(
				'fields'      => 'ID',
				'post_status' => array( 'publish', 'draft', 'orphan' ),
			), ARRAY_N ) );
			// If group being orphaned has no contacts, then it can be deleted
			if ( $count_contacts < 1 ) {
				wp_delete_post( $orphan->ID, true );
			} else {
// TODO: If all contacts for the group don't have any comments, then all contacts can be
// de-associated from the group and the group can be deleted. Then scan for any
// un-associated contacts delete then as well.
				$post = array( 'ID' => $orphan->ID, 'post_status' => 'orphan' );
				wp_update_post( $post );
			}
		}

		// Handle contacts that haven't been added or updated. This is
		// done here as opposed to in import_contacts() because it needs to
		// consider contacts outside of scope of any particular group.

		$orphaned_contacts = c2c_LastContacted::get_contacts( null, array(
			'post_modified' => $begin_date,
			'post_status' => array( 'publish', 'draft' )
		), ARRAY_N );
		foreach ( $orphaned_contacts as $orphan ) {
			// Only orphan a contact if it is no longer part of a group.
			// Otherwise, the contact being orphaned may just not have
			// updated because its group was orphaned (thereby being
			// indirectly orphaned)
			$contacts_groups = wp_get_post_terms( $orphan->ID, self::$contacts_groups_taxonomy, array( 'fields' => 'names' ) );
			if ( empty( $contacts_groups ) ) {
				$latest_note = self::get_latest_note( $orphan->ID );
				if ( empty( $latest_note ) ) {
					wp_delete_post( $orphan->ID, true );
				} else {
					$post = array( 'ID' => $orphan->ID, 'post_status' => 'orphan' );
					wp_update_post( $post );
				}
			}
		}
	}

	/**
	 * Import contacts.
	 *
	 * @param int $group_id The id of the group post type.
	 * @param array $group Data about the group being imported whose contacts are getting imported (namely 'id')
	 */
	protected static function import_contacts( $group_id, $group ) {
		$contacts = c2c_GoogleContacts::get_contacts( array( 'group' => $group['id'] ) );
		$begin_date = current_time( 'mysql' );
		foreach ( $contacts as $contact ) {
			$existing_contact = self::get_contacts( null, // We don't care whether the contact is part of this group or not
				array(
					'post_status' => array( 'publish', 'draft', 'orphan' ), // Include draft in case contact was hidden
					'meta_query' => array( array( 'key' => '_lc_id', 'value' => $contact['id'] ) )
				),
				ARRAY_N
			);

			$name = $contact['name'];
			if ( preg_match( '/[A-Z]/', $name ) == 0 )
				$name = ucwords( $name );

			if  ( empty( $existing_contact ) ) {
				$post = array(
					'post_status' => 'publish',
					'post_type'   => self::$contact_post_type,
					'post_title'  => $name
				);
				$id = wp_insert_post( $post );
			} else {
				$post_obj = $existing_contact[0];
				$post     = array(
					'ID'         => $post_obj->ID,
					'post_title' => $name
				);
				if ( 'orphan' == $post_obj->post_status )
					$post['post_status'] = 'publish';
				$id = wp_update_post( $post );
			}
			$contacts_groups = wp_get_post_terms( $id, self::$contacts_groups_taxonomy, array( 'fields' => 'names' ) );
			if ( ! in_array( $group_id, $contacts_groups ) )
				wp_set_post_terms( $id, $group_id, self::$contacts_groups_taxonomy, true );
			wp_set_post_terms( $id, 'google', self::$contacts_source_taxonomy, true );
			update_post_meta( $id, '_lc_id', $contact['id'] );
			update_post_meta( $id, '_lc_email', $contact['email'] );
			update_post_meta( $id, '_lc_phone', $contact['phone'] );
		}

		// If contact was not imported, then they must have been removed from
		// the group at the source, so de-associate the contact from the group.
		// NOTE: Not orphaning the contact as they may still be a member of
		// another group. import_groups() has check to orphan contacts.

		$removed_group_contacts = self::get_contacts( $group_id,
			array(
				'post_modified' => $begin_date,
				'post_status'   => array( 'publish', 'draft' ) // Include draft in case contact was hidden
			),
			ARRAY_N
		);
		foreach ( $removed_group_contacts as $removed ) {
			$contacts_groups = wp_get_post_terms( $removed->ID, self::$contacts_groups_taxonomy, array( 'fields' => 'names' ) );
			// Inexplicably using array_search() to find the $group_id in $contacts_groups was problematic.
			$new_contacts_groups = array();
			foreach ( $contacts_groups as $g ) {
				if ( $g != $group_id )
					$new_contacts_groups[] = $g;
			}
			wp_set_post_terms( $removed->ID, $new_contacts_groups, self::$contacts_groups_taxonomy, false );
		}
	}

	/**
	 * Registers metabox for displaying/inputting contact info about the contact.
	 *
	 * Only used if the self::$$public_post_types setting is true (i.e. debugging purposes)
	 */
	public static function add_contact_metabox() {
		add_meta_box( 'c2c_last_contacted_info', 'Contact Info', array( __CLASS__, 'display_contact_metabox' ), self::$contact_post_type, 'side', 'default' );
	}

	public static function display_contact_metabox() {
		global $post;

		$email = self::get_meta( $post->ID, '_lc_email', true );
		$phone = self::get_meta( $post->ID, '_lc_phone', true );

		echo <<<HTML
		<label for="_lc_email" >Email:
		<input name="_lc_email" id="_lc_email" class="widefat" value="$email" />
		</label>
		<label for="_lc_phone" >Phone:
		<input name="_lc_phone" id="_lc_phone" class="widefat" value="$phone" />
		</label>
HTML;
	}

	/**
	 * Saves metabox data of contact info about the contact.
	 *
	 * Only used if the self::$$public_post_types setting is true (i.e. debugging purposes)
	 */
	public static function save_meta( $post_id, $post ) {
//		if ( 'revision' == $post->post_type )
		if ( wp_is_post_revision( $post ) )
			return;
        
		//TODO: Verify nonce

		if ( ! current_user_can( 'edit_post', $post->ID ) )
			return;

		foreach( array( '_lc_email', '_lc_phone' ) as $meta ) {
			if ( isset( $_POST[$meta] ) && ! empty( $_POST[$meta] ) )
				update_post_meta( $post->ID, $meta, $_POST[$meta] );
			else
				delete_post_meta( $post->ID, $meta );
		}
	}

	/**
	 * AJAX handler for hiding a group or contact.
	 */
	public static function ajax_hide_item() {
		global $wpdb;
		$id = absint( $_POST['ID'] );
		check_admin_referer( self::get_nonce( $_POST['action'], $id ) );
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $id ) );
		exit('1');
	}

	/**
	 * AJAX handler for unhiding a group or contact.
	 */
	public static function ajax_show_item() {
		global $wpdb;
		$id = absint( $_POST['ID'] );
		check_admin_referer( self::get_nonce( $_POST['action'], $id ) );
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $id ) );
		exit('1');
	}

	/**
	 * AJAX handler for saving a new contact event.
	 */
	public static function ajax_save_note() {
		check_admin_referer( self::get_nonce( $_POST['action'], $_POST['comment_post_ID'] ) );

		global $wpdb;
		$user = wp_get_current_user();
//TODO:
//		if ( current_user_can( 'unfiltered_html' ) ) {
//			if ( wp_create_nonce( 'unfiltered-html-comment_' . $comment_post_ID ) != $_POST['_wp_unfiltered_html_comment'] ) {
//				kses_remove_filters(); // start with a clean slate
//				kses_init_filters(); // set up the filters
//			}
//		}

		$comment_type = 'lc_' . $_POST['lc_contact_method'];

		$valid_contact_methods = array( 'lc_phone', 'lc_im', 'lc_person', 'lc_email' );
		if ( ! in_array( $comment_type, $valid_contact_methods ) )
			$comment_type = 'lc_im';

		$date = $_POST['date'];
		if ( empty( $date ) ) {
			$date = current_time( 'mysql' );
		} else {
			// REDO:
			list( $aa, $mm, $jj ) = explode( '-', $date );
			$time = mysql2date( 'H:i:s', current_time( 'mysql' ) );
			list( $hh, $mn, $ss ) = explode( ':', $time );
			$date = sprintf( "%04d-%02d-%02d %02d:%02d:%02d", $aa, $mm, $jj, $hh, $mn, $ss );
		}

		// TODO: Handle if the user selects a future date

		$post_id = '';
		$posts = 'multi' == $_POST['comment_post_ID'] ? $_POST['contact_ids'] : array( $_POST['comment_post_ID'] );
		foreach ( $posts as $post_id ) {
			$post_id = absint( $post_id );
			wp_insert_comment( array(
				'comment_agent'        => substr($_SERVER['HTTP_USER_AGENT'], 0, 254 ),
				'comment_author'       => $wpdb->escape( $user->display_name ),
				'comment_author_email' => $wpdb->escape( $user->user_email ),
				'comment_author_url'   => $wpdb->escape( $user->user_url ),
				'comment_content'      => trim( $_POST['content'] ),
				'comment_author_IP'    => preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] ),
				'comment_date'         => $date,
				'comment_date_gmt'     => get_gmt_from_date( $date ),
				'comment_post_ID'      => $post_id,
				'comment_type'         => $comment_type,
				'user_id'              => $user->ID
			) );
		}

		echo 'success|Saved!|';
		echo implode( ',', $posts ) . '|';
		// Even if multi-contact submission, just need to return info for one of the contacts
		echo self::show_contact( absint( $post_id ) );
		exit();
	}

	/**
	 * AJAX handler for searching for contacts.
	 *
	 * @since 0.9.14
	 */
	public static function ajax_search_contacts() {
		$search = $_REQUEST['term'];
		$names = array();
		$contacts = self::get_contacts( null, array(
			'fields'         => array( 'ID', 'post_title' ),
			'orderby'        => 'post_title',
			'posts_per_page' => 25, // arbitrary limit
			'starts_with'    => $search,
		), ARRAY_N );
		$return = array();
		if ( empty( $contacts ) ) {
			$return[] = '';
		} else {
			foreach ( $contacts as $contact ) {
				$user          = array();
				$user['label'] = $contact->post_title;
				$user['id']    = $contact->ID;
				$return[]      = $user;
			}
		}
		echo json_encode( $return );
		exit();
	}

	public static function coalesce_comments( $fields ) {
		global $wpdb;
		if ( $fields )
			$fields .= ', ';

		$fields .= "COALESCE(
				(
					SELECT MAX(comment_date)
					FROM $wpdb->comments wpc
					WHERE wpc.comment_post_ID = $wpdb->posts.ID
				),
				NULL
			) AS comment_date";
		return $fields;
	}

	/**
	 * Returns the SQL ORDER BY clause.
	 *
	 * @param string $orderby The existing orderby
	 * @return string The modified orderby
	 */
	public static function orderby( $orderby ) {
		$orderby = self::$posts_orderby ? self::$posts_orderby : ' post_title ASC ';
		return $orderby;
	}

	/**
	 * Returns groups.
	 *
	 * @param array $conditions (optional) Additional conditions to pass to the WP_Query
	 * @param string $output (optional) Constant for return type, either OBJECT (for WP_Query object) or ARRAY_N (array of posts)
	 * @return WP_Query|array Either the WP_Query object used to query the groups, or an array of matching group objects
	 */
	public static function get_groups( $conditions = array(), $output = OBJECT ) {
		$defaults = array(
			'post_author'    => get_current_user_id(),
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'post_type'      => self::$group_post_type
		);
		$conditions = wp_parse_args( $conditions, $defaults );

		$q = self::do_query( $conditions, 'group' );

		// If orderby is not explicity defined, or if it is and it equals 'comment_date', sort by comment_date
		if ( ! isset( $conditions['orderby'] ) || $conditions['orderby'] == 'comment_date' ) {
			// HACK: Until I can properly do this fully in the initial query
			$posts = $q->posts;
			if ( ! empty( $posts ) ) {
				$sorted_posts = array();
				$no_date = array();
				foreach ( $posts as $post ) {
					$latest_contact = self::get_latest_contact( $post->ID );
					$post->comment_date = $latest_contact ? $latest_contact->comment_date : '';
				}
				usort( $posts, array(__CLASS__,'group_sort'));
				$q->posts = $posts;
			}
		}

		return ( OBJECT == $output ) ? $q : $q->posts;
	}

	/**
	 * Helper function to sort groups first by comment_date, then by post_title.
	 * Intended for use by usort().
	 *
	 * @param WP_Post $a A post
	 * @param WP_Post $b Another post
	 * @return int -1, 0, or 1 to indicate relative sort between the two posts.
	 */
	public static function group_sort( $a, $b ) {
		if ( $a->comment_date == $b->comment_date ) {
			if ( $a->post_title == $b->post_title )
				return 0;
			return ( $a->post_title < $b->post_title ) ? -1 : 1;
		}
		return ( $a->comment_date > $b->comment_date ) ? -1 : 1;
	}

	/**
	 * Performs the WP_Query for the given conditions.
	 *
	 * If a 'post_modified' key is sent as part of $conditions, then a date
	 * comparison is performed relative to the date value supplied for that
	 * key. By default this is '<', but a 'post_date_comparator' can be
	 * sent to override that.
	 *
	 * @param array $conditions Array of conditions for the query
	 * @param string $coalesce_type Type of coalesce to include in the query
	 * @return WP_Query The WP_Query object created to perform the query
	 */
	private static function do_query( $conditions, $coalesce_type = 'contact' ) {
		$do_where = false;

		if ( ! isset( $conditions['coalesce_comments'] ) || ! $conditions['coalesce_comments'] )
			$coalesce = '';
		else
			$coalesce = 'coalesce_comments';

		$fields = '';
		if ( isset( $conditions['fields'] ) && $conditions['fields'] ) {
			global $wpdb;
			if ( ! is_array( $conditions['fields'] ) )
				$conditions['fields'] = explode( ' ', $conditions['fields'] );
			foreach ( $conditions['fields'] as $field ) {
				if ( $fields )
					$fields .= ', ';
				$fields .= $wpdb->posts . '.' . $field;
			}
		}

		if ( isset( $conditions['starts_with'] ) && $conditions['starts_with'] ) {
			self::$starts_with = $conditions['starts_with'];
			$do_where = true;
		}

		$date_compare = isset( $conditions['post_modified'] ) && ! empty( $conditions['post_modified'] );

		if ( $fields ) {
			self::$post_fields = $fields;
			add_filter( 'posts_fields',   array( __CLASS__, 'posts_select' ) );
		}

		if ( $coalesce ) {
			add_filter( 'posts_fields',   array( __CLASS__, $coalesce ) );
			self::$posts_orderby = ' comment_date DESC, post_title ASC ';
		}

		add_filter( 'posts_orderby',  array( __CLASS__, 'orderby' ) );

		if ( $date_compare ) {
			self::$post_date_comparator = ( isset( $conditions['post_date_comparator'] ) ? $conditions['post_date_comparator'] : '<' );
			self::$post_date            = $conditions['post_modified'];
			$do_where = true;
			unset( $conditions['post_modified'] );
		}

		if ( $do_where )
			add_filter( 'posts_where',     array( __CLASS__, 'posts_where' ) );

		$q = new WP_Query( $conditions );

		if ( $do_where ) {
			self::$post_date_comparator = '';
			self::$post_date            = '';
			self::$starts_with          = '';
			remove_filter( 'posts_where',   array( __CLASS__, 'posts_where' ) );
		}

		if ( $fields ) {
			self::$post_fields = '';
			remove_filter( 'posts_fields',  array( __CLASS__, 'posts_select' ) );
		}

		if ( $coalesce ) {
			self::$posts_orderby = '';
			remove_filter( 'posts_fields',  array( __CLASS__, $coalesce ) );
			remove_filter( 'posts_orderby', array( __CLASS__, 'orderby' ) );
		}

		return $q;
	}

	/**
	 * Modifies query SELECT fields.
	 *
	 * @since 0.9.14
	 *
	 * @param string $fields Fields string
	 * @return string
	 */
	public static function posts_select( $fields ) {
		if ( self::$post_fields )
			$fields = self::$post_fields;

		return $fields;
	}

	/**
	 * Modifies query WHERE clause to do date and/or starts_with comparisons.
	 *
	 * @param string $where Existing WHERE clause
	 * @return string Modified WHERE clause
	 */
	public static function posts_where( $where ) {
		if ( ! empty( self::$post_date_comparator ) && ! empty( self::$post_date ) )
			$where .= ' AND (post_modified ' . self::$post_date_comparator . ' \'' . self::$post_date . '\') ';

		if ( ! empty( self::$starts_with ) )
			$where .= ' AND (post_title LIKE \'' . esc_sql( self::$starts_with ) . '%\')';

		return $where;
	}

	/**
	 * Returns contacts
	 *
	 * @param int $group The ID of the group
	 * @param array $conditions (optional) Additional conditions to pass to the WP_Query
	 * @param string $output (optional) Constant for return type, either OBJECT (for WP_Query object) or ARRAY_N (array of posts)
	 * @return WP_Query|array Either the WP_Query object used to query the contacts, or an array of matching contact objects
	 */
	public static function get_contacts( $group, $conditions = array(), $output = OBJECT ) {
		if ( self::is_showing_hidden_contacts() )
			$status = 'draft';
		else
			$status = 'publish';
		$defaults = array(
			'post_author'    => get_current_user_id(),
			'posts_per_page' => -1,
			'post_status'    => array( $status ),
			'post_type'      => self::$contact_post_type
		);
		if ( ! empty( $group ) ) {
			$defaults['tax_query'] = array(
				array( 'taxonomy' => self::$contacts_groups_taxonomy, 'terms' => array( $group ), 'field' => 'slug' )
			);
		}
		$conditions = wp_parse_args( $conditions, $defaults );

		$q = self::do_query( $conditions );

		return ( OBJECT == $output ) ? $q : $q->posts;
	}

	/**
	 * Template tag to query for the contacts of the specified group and output
	 * them via the contacts.php template.
	 *
	 * @param int $group The group ID
	 */
	public static function show_contacts( $group ) {
		$GLOBALS['lc_contacts_query'] = self::get_contacts( $group, array( 'coalesce_comments' => true ) );
		load_template( dirname( __FILE__ ) . '/templates/contacts.php', false );
	}

	/**
	 * Returns the number of contacts for the specified group.
	 *
	 * @param int $group The ID of the group
	 * @return int The count of contacts for the group
	 */
	public static function count_contacts( $group, $hidden = false ) {
		return count( get_posts( array(
			'fields'         => 'ID',
			'post_status'    => array( $hidden ? 'draft' : 'publish' ),
			'posts_per_page' => '-1',
			'post_type'      => array( self::$contact_post_type ),
			'tax_query'      => array( array(
				'taxonomy' => self::$contacts_groups_taxonomy,
				'terms'    => array( $group ),
				'field'    => 'slug'
			) )
		) ) );
	}

	/**
	 * Template tag to query for a specific contact and output it via the
	 * contact.php template.
	 *
	 * @param null|WP_Post $contact The contact object, or NULL to get the current post object
	 */
	public static function show_contact( $contact = null ) {
		global $post;
		if ( is_object( $contact ) ) {
			$post = $contact;
		} elseif ( is_integer( $contact ) ) {
			$c = self::get_contacts( '', array( 'p' => $contact, 'posts_per_page' => 1, 'coalesce_comments' => true ), ARRAY_N );
			if ( empty( $c ) )
				return;
			$post = array_shift( $c );
		}

		load_template( dirname( __FILE__ ) . '/templates/contact.php', false );
	}

	/**
	 * Returns the contact most recently contacted for a given group.
	 *
	 * @param int $group The ID of the group
	 * @return WP_Post The most recently contacted contact
	 */
	public static function get_latest_contact( $group ) {
		$contacts = self::get_contacts( $group, array( 'posts_per_page' => 1, 'coalesce_comments' => true ), ARRAY_N );
		if ( empty( $contacts ) )
			return array();
		$contact = array_shift( $contacts );
		return $contact;
	}

	/**
	 * Returns a meta value for the given contact and meta_key.
	 *
	 * @param int|null The ID of the contact. Gets the contact via get_the_ID() if sent NULL
	 * @param string $meta_key The name of the meta key.
	 * @return string The value of the meta
	 */
	public static function get_meta( $contact_id, $meta_key ) {
		if ( is_null( $contact_id ) )
			$contact_id = get_the_ID();
		return get_post_meta( $contact_id, $meta_key, true );
	}

	/**
	 * Returns the comment object for the latest note for the specified contact.
	 *
	 * @param object|int The contact object or ID
	 * @return object The comment object
	 */
	public static function get_latest_note( $contact ) {
		if ( is_object( $contact ) ) {
			if ( property_exists( $contact, 'comment_date' ) && empty( $contact->comment_date ) )
				return;
			$contact = $contact->ID;
		}

		$latest_note = wp_cache_get( $contact, 'lc_latest_note' );
		if ( $latest_note ) {
			if ( 'none' == $latest_note )
				$latest_note = null;
			return $latest_note;
		}

		$latest_note = get_comments( array('status' => 'approved', 'post_id' => $contact, 'number' => 1 ) );
		if ( $latest_note )
			$latest_note = array_shift( $latest_note );

		$cache_note = $latest_note ? $latest_note : 'none'; // To avoid caching null/false
		wp_cache_set( $contact, $cache_note, 'lc_latest_note' );

		return $latest_note;
	}

	/**
	 * Creates a nonce string for a given action and object id.
	 *
	 * @param string $action The name of the action
	 * @param int $obj_id The ID of the object
	 * @return string The nonce string
	 */
	public static function get_nonce( $action, $obj_id ) {
		return self::$widget_id . "-{$action}_$obj_id";
	}

	/**
	 * Returns the full filesystem path to the specified template file.
	 *
	 * If file is not specified, then it returns the path to the templates directory.
	 *
	 * @param string $template The template file name (relative to the templates directory)
	 * @return string The path
	 */
	public static function get_template_path( $template = '' ) {
		if ( empty( self::$template_dir ) ) {
			$default_dir = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
			self::$template_dir = apply_filters( 'lc_template_directory', $default_dir );
		}
		return self::$template_dir . $template;
	}

	/**
	 * Returns the URL for a CSS file.
	 *
	 * @param string $file CSS file name (path relative to CSS root directory)
	 * @return string The URL
	 */
	public static function get_css_url( $file = '' ) {
		return plugins_url( 'css/' . $file, __FILE__ );
	}

	/**
	 * Returns the URL for a JavaScript file.
	 *
	 * @param string $file JS file name (path relative to JavaScript root directory)
	 * @return string The URL
	 */
	public static function get_javascript_url( $file = '' ) {
		return plugins_url( 'js/' . $file, __FILE__ );
	}

	public static function is_showing_hidden_contacts() {
		return isset( $_GET['show_hidden_contacts'] ) && $_GET['show_hidden_contacts'] == '1';
	}

	public static function is_showing_hidden_groups() {
		return isset( $_GET['show_hidden_groups'] ) && $_GET['show_hidden_groups'] == '1';
	}
}

c2c_LastContacted::init();

endif;

?>