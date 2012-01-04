<?php global $lc_groups_query; ?>

<?php if ( $lc_groups_query->have_posts() ) : ?>

<div id="last_contacted" class="ui-accordion">

<?php while ( $lc_groups_query->have_posts() ) : $lc_groups_query->the_post(); $gid = get_the_ID(); ?>
	<div id="lc_group_<?php the_ID(); ?>" class="lc_group">
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
			$date = mysql2date(c2c_LastContacted::$date_format, $latest_contact->comment_date );
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
			printf( _n( '1 contact', '%d contacts', $count ), $count );
			?>
			<span><?php lc_hide_group( $gid ); ?></span>
		</div>

		<?php c2c_LastContacted::show_contacts( $gid ); ?>
	</div>
	</div>
<?php endwhile; wp_reset_query(); ?>

</div>

<?php else : ?>

	<div id="last_contacted">
		<p>Doesn't look like you have any contacts imported yet! Click the widget's 'configure' link to perform an import.</p>
	</div>

<?php endif; ?>