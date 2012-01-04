<h2>Last Contacted &#8212; Import</h2>

<?php settings_errors(); ?>

<form id="lc_import" method="post">

<?php
	// Load the _import partial
	load_template( dirname( __FILE__ ) . '/_import.php' );
?>

	<input type="hidden" name="action" value="lc_import" />
	<a href="<?php echo c2c_LastContactedAdmin::admin_page_url(); ?>">Return to contacts</a> |
	<input type="submit" value="Import" name="import" class="button-primary" />
</form>

<h3 style="border-top:1px solid #ddd;margin-top:20px;padding-top:20px;margin-right:20px">Gravatar Pre-fetch</h3>

<p>While not really an import per se, you can improve the pageload performance of your contact listings by pre-fetching from Gravatar to determine which contacts have Gravatar images associated with their email addresses. The plugin can then use this information to abstain from requesting Gravatars for people known not to have them.</p>

<p>Use the 'Clear' button to delete any pre-fetched information previously obtained from Gravatar.</p>

<p><em>Note: This does not cache Gravatar images. <strong>Be patient; pre-fetching takes a few moments to complete.</strong></em></p>

<form id="lc_import_gravatar" method="post">
	<input type="hidden" name="action" value="lc_import_gravatar" />
	<input type="submit" value="Clear" name="import_gravatar_clear" class="button-secondary" />
	<input type="submit" value="Pre-fetch" name="import_gravatar" class="button-primary" />
</form>