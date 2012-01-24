<?php
// Template tags

function lc_email( $before = '', $after = '', $contact_id = null ) {
	$email = c2c_LastContacted::get_meta( $contact_id, '_lc_email' );
	if ( $email ) {
		$email = '<a href="mailto:' . esc_attr( $email ) . '">' . $email . '</a>';
		echo $before . $email . $after;
	}
}

function lc_phone( $before = '', $after = '', $contact_id = null ) {
	$phone =  c2c_LastContacted::get_meta( $contact_id, '_lc_phone' );
	if ( $phone )
		echo $before . $phone. $after;
}

// Always output the tag so that it can be replaced later
function lc_last_contacted( $contact = null ) {
	if ( is_null( $contact ) ) {
		global $post;
		$contact = $post;
	}

	$latest_contact = c2c_LastContacted::get_latest_note( $contact );

	if ( empty( $latest_contact ) ) {
//		return;
		$date = '';
		$method = $Methos = 'none';
		$title = '';
	} else {
		$date = mysql2date( c2c_LastContacted::$date_format, $contact->comment_date );
		$method = $Method = str_replace( 'lc_', '', $latest_contact->comment_type );
		if ( empty( $method ) ) {
			$method = 'im';
			$Method = 'IM';
		}
		$title = "Last contacted via $Method on $date";
	}
	echo "<span class='last_contact lc_$method' title='$title'>$date</span>";
}

function lc_last_contacted_full( $contact = null ) {
	if ( is_null( $contact ) ) {
		global $post;
		$contact = $post;
	}

	$date = mysql2date( c2c_LastContacted::$date_format, $contact->comment_date );

	$latest_contact = c2c_LastContacted::get_latest_note( $contact );

	echo '<div class="lc_last_contacted_info">';

	if ( ! empty( $latest_contact ) ) {
		$method = $Method = str_replace( 'lc_', '', $latest_contact->comment_type );
		if ( empty( $method ) ) {
			$method = 'im';
			$Method = 'IM';
		}

		echo "Last contacted via <strong>$Method</strong> on <strong>$date</strong>.<br />";
		if ( ! empty( $latest_contact->comment_content ) ) {
			echo "Note: ";
			echo '<em>' . esc_html( $latest_contact->comment_content ) . '</em>';
		}
	}

	echo '</div>';
}

function _lc_hide( $id, $action, $value, $title ) {
	echo '<form class="' . $action . '" method="post">';
	wp_nonce_field( c2c_LastContacted::get_nonce( $action, $id ) );
	echo '<input type="hidden" name="action" value="'. $action . '" />';
	echo '<input type="hidden" name="ID" value="' . $id . '" />';
	echo '<input type="submit" name="submit" value="' . esc_attr( $value ) . '" class="button-secondary lc-hide-contact-button" title="' .
		esc_attr( $title ) . '" />';
	echo '</form>';
}

function lc_hide_contact( $id ) {
	if ( get_post_status( $id ) == 'draft' ) {
		$action = 'lc_show_contact';
		$value = __( 'Show' );
		$title = __( 'Show this contact' );
	} else {
		$action = 'lc_hide_contact';
		$value = __( 'Hide' );
		$title = __( 'Hide this contact' );
	}
	return _lc_hide( $id, $action, $value, $title );
}

function lc_hide_group( $id ) {
	if ( get_post_status( $id ) == 'draft' ) {
		$action = 'lc_show_group';
		$value = __( 'Show' );
		$title = __( 'Show this group' );
	} else {
		$action = 'lc_hide_group';
		$value  = __( 'Hide' );
		$title  = __( 'Hide this group' );
	}
	return _lc_hide( $id, $action, $value, $title );
}

function lc_get_avatar( $size = '48', $args = array() ) {
	global $post;
	$defaults = array(
		'contact_id'        => isset( $args['contact_id'] ) ? $args['contact_id'] : (is_object( $post ) ? get_the_ID() : null),
		'generic_image'     => plugins_url( 'css/images/transparent.png', dirname( __FILE__ ) ),
		'return_as_html'    => true,
		'use_gravatar'      => true,
		'validate_gravatar' => false
	);
	$args = wp_parse_args( $args, $defaults );

	if ( empty( $args['contact_id'] ) )
		return;

	// See if contact has been marked as not having an avatar
	$skip_avatar  = empty( $email ) ? c2c_LastContacted::get_meta( $args['contact_id'], '_lc_no_gravatar' ) : false;
	// Should Gravatar be use?
	$use_gravatar = ! isset( $args['use_gravatar'] ) || $args['use_gravatar'];
	// Email for contact
	$email        = c2c_LastContacted::get_meta( $args['contact_id'], '_lc_email' );
	$avatar       = '';

	// If an email is present isn't one already known not to have a Gravatar
	if ( ! empty( $email ) && empty( $skip_avatar ) ) {
		// If using Gravatar
		if ( $use_gravatar ) {
			// Hash the email according to Gravatar specs
			$hash = md5( strtolower( trim( $email ) ) );
			$gurl = "http://www.gravatar.com/avatar/$hash?s=$size&d=";
			if ( $args['validate_gravatar'] ) {
				// Just do an HTTP HEAD request to see if the email has avatar
				//  (request tells Gravatar to return 404 if no image exists)
				$resp = wp_remote_head( $gurl . '404' );
				$code = wp_remote_retrieve_response_code( $resp );
				if ( $code != '404' )
					$avatar = $gurl . $args['generic_image'];
			} else {
				$avatar = $gurl . $args['generic_image'];
			}
		} else {
			$avatar = '';
		}
	}

	$classes = '';

	// Use generic image if no avatar has been assigned yet
	if ( empty( $avatar ) ) {
		// If not done so previously, mark the contact as not having a gravatar
		if ( empty( $skip_avatar ) && $use_gravatar )
			update_post_meta( $args['contact_id'], '_lc_no_gravatar', current_time( 'mysql' ) );

		$avatar = $args['generic_image'];
	} elseif ( $args['validate_gravatar'] && ! empty( $skip_avatar ) ) {
		// If validating and have an avatar, unmark the contact if they had been noted as not having a gravatar
		delete_post_meta( $args['contact_id'], '_lc_no_gravatar' );
	}

	if ( ! empty( $avatar ) && $args['return_as_html'] ) {
		$classes = $use_gravatar ? '' : 'no-avatars';
		$classes .= ( $avatar == $args['generic_image'] ) ? ' no-avatar ' : '';
		$avatar = "<img alt='' src='$avatar' class='avatar {$classes} avatar-{$size} photo' height='{$size}' width='{$size}' />";
	}

	return apply_filters( 'c2c_lc_get_avatar', $avatar, $email, $size, $args );
}
?>