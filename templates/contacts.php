<?php global $lc_contacts_query; ?>

<?php if ( ! $lc_contacts_query->have_posts() ) : ?>

	<div class="lc_no_group_contacts_list">
	<?php if ( c2c_LastContacted::is_showing_hidden_contacts() ) { ?>
		<?php _e( 'This group does not have any hidden contacts.', c2c_LastContacted::$textdomain ); ?>
	<?php } else { ?>
		<?php _e( 'Sorry, this group does not have any contacts.', c2c_LastContacted::$textdomain ); ?>
	<?php } ?>
	</div>

<?php else : ?>

	<div class="lc_group_contacts_list">
	<?php while ( $lc_contacts_query->have_posts() ) : $lc_contacts_query->the_post(); ?>

		<?php c2c_LastContacted::show_contact(); ?>

	<?php endwhile; wp_reset_query(); ?>
	</div>

<?php endif; ?>