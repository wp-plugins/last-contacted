<?php wp_nonce_field( c2c_LastContacted::get_nonce( 'lc_do_import', get_current_user_id() ) ); ?>

<p>Select the service(s) from which you wish to import/update contacts from.</p>
<p>If the service is one that you haven't authenticated this plugin with yet (as noted next to its name), then you must follow its authentication link first in order to use it. You will likely be temporarily redirected to the service in order to grant the plugin access.<p>

<ul>
	<?php
	// TODO: Abstract this section to accommodate multiple services.
	$is_authenticated = c2c_GoogleOAuth::is_authenticated_with_service( 'google' );
	?>
	<li><label class="<?php if ( ! $is_authenticated ) echo 'lc_disabled'; ?>">

	<!-- Do not allow selection of the service until it is authenticated -->

	<input type="checkbox" name="lc_contact_sources" value="google" <?php disabled( ! $is_authenticated ); ?> />
	Google Contacts
	<?php if ( ! $is_authenticated ) { ?>
		(not authenticated yet: <a href="<?php echo esc_url( c2c_GoogleOAuth::oauth_init_url() ); ?>">Authenticate now!</a>)
	<?php } else {
		$last_imported = get_user_meta( get_current_user_id(), 'lc_last_imported_google', true );
		if ( empty( $last_imported ) )
			$msg = __( 'Never imported' );
		else
			$msg = sprintf( __( 'Last imported: %s' ), $last_imported );
		echo "<span class='lc_contact_source_info'>($msg)</span>";
	} ?>

	</label></li>
</ul>

<p style="font-style:italic;">It is safe to repeat this import/update as often as you wish.</p>