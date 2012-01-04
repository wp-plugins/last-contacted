<div class='wrap'>
<?php screen_icon( 'options-general' ); ?>
<h2>Last Contacted &#8212; Settings</h2>

<?php settings_errors(); ?>
	
<form action='<?php echo admin_url( 'options.php' ); ?>' method='post' class='c2c-form'>

<?php
	settings_fields( c2c_LastContactedSettings::$admin_options_name );
	do_settings_sections( c2c_LastContactedSettings::$plugin_file );
?>

	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php echo esc_attr__( 'Save Changes' ); ?>" />
	</p>
</form>

</div>