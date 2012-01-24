<?php
	$options = c2c_LastContactedSettings::get_options();
	$id = 'multi';
	$avatar = '<img class="avatar avatar-26 photo" width=\'48\' height=\'48\' src="' . plugins_url( 'css/images/transparent.png', dirname( __FILE__ ) ) . '" alt="">';
?>

<div id="lc_contact_<?php echo $id; ?>" class="lc_contact lc-hide-if-no-multi-contact" data-id="<?php echo $id; ?>">
	<h5>
		<a href="">
		<?php echo str_replace( array( "'48'", '-48 ' ), array( "'26'", '-26 ' ), $avatar ); ?>
		Apply to all searched contacts
		</a>
	</h5>
	<div class="lc_details_<?php echo  $id; ?> lc_details ui-accordian">
		<?php if ( ! empty( $avatar ) ) { ?>
		<div class="lc_avatar">
			<?php echo $avatar; ?>
		</div>
		<?php } ?>
		<div class="lc_details_main">
			<div class="lc_details_info">
				<h6>Apply to all searched contacts</h6>
				<div class="lc_last_contacted_info">
				The information you submit below will be applied to ALL contacts currently listed in the Search box.
				</div>
			</div>

			<?php $path = plugins_url( 'css/images/', dirname ( __FILE__ ) ); ?>
			<div class="lc_new_contact">
				<div class="ajax_response"></div>
				<form class="lc_new_contact_form" method="post">
						<input type="hidden" name="action" value="lc_save_note" />
						<input type="hidden" name="comment_post_ID" value="<?php echo $id; ?>" />
						<?php wp_nonce_field( c2c_LastContacted::get_nonce( 'lc_save_note', $id ) ); ?>
						
					<div class="lc_contact_methods" style="position:relative;">
						<label for="lc_contact_method">Method</label>
						<a href="" data-contact-type="im" class="lc_default_contact_method chosen_method lc_im" title="IM"></a>
						<a href="" data-contact-type="phone" title="phone" class="lc_phone"></a>
						<a href="" data-contact-type="person" title="in person" class="lc_person"></a>
						<a href="" data-contact-type="email" title="email" class="lc_email"></a>
						<div style="display:none;">
						<input type="radio" name="lc_contact_method" class="lc_default_contact_method" value="im" checked="checked" />IM
						<input type="radio" name="lc_contact_method" value="phone" />Phone
						<input type="radio" name="lc_contact_method" value="person" />In-person
						<input type="radio" name="lc_contact_method" value="email" />Email
						</div>
					</div>
					<div style="position:relative;">
						<label for="date">Date</label>
						<input type="text" name="date" class="datepicker" style="float:left;" value="<?php echo date(c2c_LastContacted::$date_format); ?>" />
					</div>
					<div style="clear:left;position:relative;">
						<label for="content">Note</label>
						<div class="textarea_wrap">
						<textarea class="lc_content" name="content" rows="3" cols="15" class="mceEditor"></textarea>
						</div>
					</div>
					<div style="position:relative;text-align:right;">
						<input type="submit" name="Save" value="Add" class="lc_save_note button-primary" style="float:right;" />
						<input type="button" name="Reset" value="Reset" class="lc_reset_contact_form button-secondary" style="float:right;" />
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
