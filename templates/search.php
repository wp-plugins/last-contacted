	<?php
		$action = 'lc_contact_search';
	?>

	<div id="lc_search" class="lc_group">
		<h4>Search</h4>
		<div id="lc-search-contacts" class="lc_contacts abc ui-accordian">
			<div class="lc_group_info">
			<form class="lc_contact_search" method="post">
				<?php wp_nonce_field( c2c_LastContacted::get_nonce( $action, '1' ) ); ?>
				<input type="hidden" name="action" value="<?php echo $action; ?>" />
				<input type="text" name="lc_contact_search" class="lc_contact_search_name" />
			</form>
			</div>
			<div id="lc_search_notes" class="lc-hide-if-contact">
				<p>Use the search field above to find one or more of your contacts. You can interact with
				any searched contact here as if you were looking at them under one of their groups.</p>
				<p>If you search and select two or more contacts, a multi-contact entry will appear at the
				top of the listing. Information you input and submit for that multi-contact entry will
				automatically be applied to all contacts listed here.</p>
			</div>
			<div id="lc_found_contacts" class="lc_group_contacts_list">
				<div id='lc_clear_found' class="lc-hide-if-no-multi-contact" title='Clear the listing of searched contacts'>
					<form id="lc_clear_found_form" method="get">
						<input type="submit" name="lc_clear_found" value="Clear All" class="button-secondary lc-contact-search-button" />
					</form>
				</div>
				<?php require_once( 'multi-contact.php' ); ?>
			</div>
		</div>
	</div>