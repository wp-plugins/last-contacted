<?php
	global $lc_groups_query;
	$showing_hidden_groups   = c2c_LastContacted::is_showing_hidden_groups();
	$showing_hidden_contacts = c2c_LastContacted::is_showing_hidden_contacts();
?>

<?php if ( $showing_hidden_groups ) { ?>
	<h4>Hidden group are currently being listed.</h4>
	<p><a href="<?php echo remove_query_arg( 'show_hidden_groups' ); ?>" class="lc_link_show_hidden_groups">View non-hidden groups</a>.</p>
<?php } elseif ( $showing_hidden_contacts ) { ?>
	<h4>Hidden contacts for active groups are currently being listed.</h4>
	<p><a href="<?php echo remove_query_arg( 'show_hidden_contacts' ); ?>" class="lc_link_show_hidden_contacts">View non-hidden contacts</a>.</p>
<?php } else { ?>
	<p>Make note of when you contact your contacts using the tool below. If you're just getting started, or would like to refresh the contact information in the system, <a href="<?php menu_page_url( 'c2c_LastContactedAdmin_import' ); ?>" class="lc_link_import">click here to import/update contact data</a>.</p>
<?php } ?>


<?php if ( $lc_groups_query->have_posts() ) : ?>

<?php if ( ! $showing_hidden_groups && ! $showing_hidden_contacts ) { ?>
	<p>
		<a href="<?php echo add_query_arg( 'show_hidden_groups', 1 ); ?>" class="lc_link_view_hidden">View hidden groups</a> |
		<a href="<?php echo add_query_arg( 'show_hidden_contacts', 1 ); ?>" class="lc_link_view_hidden">View hidden contacts</a>
	</p>
<?php } ?>

<div id="last_contacted" class="ui-accordion-header ui-helper-reset ui-state-default ui-corner-all">

	<?php require_once( 'search.php' ); ?>

<?php while ( $lc_groups_query->have_posts() ) : $lc_groups_query->the_post(); $gid = get_the_ID(); ?>
	<div id="lc_group_<?php the_ID(); ?>" class="lc_group<?php if ( $showing_hidden_groups ) { echo ' lc_hidden_group'; } ?>">
	<h4>
		<div class="lc_group_sort" style="display:none;">
<!-- TODO: Temporarily disabled since sorting is not supported at the moment.
			Sort:
			<select name="lc_group_sort">
				<option>Last contact date (desc)</option>
				<option>Last contact date (asc)</option>
				<option>Name (asc)</option>
				<option>Name (desc)</option>
			</select>
-->
		</div>
		<?php
		$latest_contact = c2c_LastContacted::get_latest_contact( $gid );
		if ( ! empty( $latest_contact ) ) :
			$date = mysql2date( c2c_LastContacted::$date_format, $latest_contact->comment_date );
		?>
		<div class="lc_group_last_contact" title="Most recently contacted <?php echo get_the_title( $latest_contact->ID ); ?> on <?php echo $date; ?>">
			<?php echo $date; ?>
		</div>
		<?php endif; ?>
		<a href="#"><?php the_title(); ?></a>
	</h4>
	<div class="lc_contacts abc ui-accordian">
		<div class="lc_group_info">
			<?php
			$count = c2c_LastContacted::count_contacts( $gid, c2c_LastContacted::is_showing_hidden_contacts() );
			printf( _n( '<span class="lc_contacts_count">1</span> contact', '<span class="lc_contacts_count">%d</span> contacts', $count ), $count );
			?>
			<span><?php lc_hide_group( $gid ); ?></span>
		</div>

		<?php
		if ( $showing_hidden_groups )
			echo '<p>Contacts for hidden groups are not shown.</p>';
		else
			c2c_LastContacted::show_contacts( $gid );
		?>
	</div>
	</div>
<?php endwhile; wp_reset_query(); ?>

</div>

<?php else : ?>

	<div id="last_contacted">
		<?php if ( $showing_hidden_groups ) { ?>
			<p><em>You haven't hidden any groups.</em></p>
		<?php } else { ?>
			<p>Doesn't look like you have any contacts imported yet! Click the import/update link above to perform an import.</p>
		<?php } ?>
	</div>

<?php endif; ?>