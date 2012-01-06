<?php
	$options = c2c_LastContactedSettings::get_options();
	$avatar = lc_get_avatar( '48', array( 'use_gravatar' => ( ! isset( $options['use_gravatar'] ) || $options['use_gravatar'] ) ) );
?>

<div class="lc_contact_<?php the_ID(); ?> lc_contact" data-id="<?php the_ID(); ?>">
	<h5>
		<a href="">
		<?php echo str_replace( array( "'48'", '-48 ' ), array( "'26'", '-26 ' ), $avatar ); ?>
		<?php the_title(); ?>
		</a>
		<?php lc_last_contacted(); ?>
	</h5>
	<div class="lc_details_<?php the_ID(); ?> lc_details ui-accordian">
		<?php if ( ! empty( $avatar ) ) { ?>
		<div class="lc_avatar">
			<?php echo $avatar; ?>
		</div>
		<?php } ?>
		<div class="lc_details_main">
			<div class="lc_details_info">
				<h6><?php the_title(); ?><?php lc_hide_contact( get_the_ID() ); ?></h6>
				<?php lc_email( '<div>Email: ', '</div>' ); ?>
				<?php lc_phone( '<div>Phone: ', '</div>' ); ?>

				<?php lc_last_contacted_full(); ?>
			</div>

			<div class="lc_show_contact_form"><a href="#">&#x25bc;</a></div>

			<?php $path = plugins_url( 'css/images/', dirname ( __FILE__ ) ); ?>
			<div class="lc_new_contact" style="display:none;">
				<a href="#" class="lc_hide_contact_form" title="Hide the contact form">&#x2716;</a>
				<div class="ajax_response"></div>
				<form class="lc_new_contact_form" method="post">
						<input type="hidden" name="action" value="lc_save_note" />
						<input type="hidden" name="comment_post_ID" value="<?php the_ID(); ?>" />
						<?php wp_nonce_field( c2c_LastContacted::get_nonce( 'lc_save_note', get_the_ID() ) ); ?>
						
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
