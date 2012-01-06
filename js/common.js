jQuery(document).ready(function($) {

	//
	// Configure the datepicker
	//
	$( ".datepicker" ).datepicker({
		showOn: "button",
		buttonImage: c2c_LastContacted.plugin_dir + '/css/images/calendar.png',
		buttonImageOnly: true,
		maxDate: '+0d',
		defaultDate: '+0d',
		dateFormat: 'yy-mm-dd',
		nextText: '&raquo;',
		prevText: '&laquo;'
	});

	function lc_reset_contact_form(contact) {
		var form = contact.find('form.lc_new_contact_form');

		// Reset chosen contact method selection
		form.find( '.chosen_method' ).removeClass('chosen_method');
		form.find('.lc_default_contact_method').addClass('chosen_method');
		// Reset form fields
		form.find(':input')
			.not(':button, :submit, :reset, :hidden')
			.val('')
			.removeAttr('checked')
			.removeAttr('selected');
		form.find('textarea').val('');

		// Preload today's date into the date field
		var d = new Date();
		var mth = d.getMonth()+1;
		var day = d.getDate();
		// Pad with leading 0 if necessary
		if ( mth < 10 ) mth = '0' + mth;
		if ( day < 10 ) day = '0' + day;
		form.find('input.datepicker').val(mth + '/' + day + '/' + d.getFullYear());
		form.find('input.datepicker').val(d.getFullYear() + '-' + mth + '-' + day);
		return false;
	}

	//
	// Reset contacted form
	//
	$( '.lc_reset_contact_form' ).click(function() {
		return lc_reset_contact_form($(this).closest('.lc_contact'));
	});

	//
	// Clicking of contact method linked images should set associated hidden radio button
	//
	$( '.lc_contact_methods > a' ).click(function() {
		if ( $(this).hasClass('chosen_method') )
			return;
		$(this).parent().find( '.chosen_method' ).not(this).toggleClass('chosen_method');
		$(this).addClass('chosen_method');
		// Uncheck the checked radio button
		$(this).parent().find('input[type="radio"]').removeAttr('checked');
		// Click the related hidden radio button
		$(this).parent().find('input[type="radio"][value="' + $(this).attr('data-contact-type') + '"]').attr('checked', 'checked');
		return false;
	});

	//
	// AJAX submit form for newly contacting contact
	//

	function lc_ajax_response(tgt, type, msg, fadeOut) {
		if ( typeof fadeOut == "undefined" )
			fadeOut = true;

		x = $(tgt).find('div.ajax_response').html(msg).addClass(type).show();
		if ( fadeOut ) {
			x.fadeOut(2000, 'swing', function() { //easeInQuint
				$( '.ajax_response' ).empty().removeClass(type);
			} );
		}
	}
	function lc_latest_contact_date_for_contact( contact ) {
		return $.trim(contact.find('.last_contact').text());
	}
	function lc_latest_contact_date_for_group( group ) {
		return $.trim(group.find('.lc_group_last_contact').text());
	}
	function lc_update_group_date( group, new_date ) {
		// TODO: This is only changing the text. Need to change the attribute text info too.
		group.find('.lc_group_last_contact').text(new_date).attr('title','');
	}
	function lc_sort_contact( contact, group ) {
		var new_date = lc_latest_contact_date_for_contact(contact);
		// Foreach instance of the given contact
		$.each($('.lc_contact_'+contact.attr('data-id')), function(i,v) {
			// Foreach contact in the groups containing the instances of the given contact
			$.each($(v).closest('.lc_group').find('.lc_contact'), function(j,k) {
				var move = true;
				// If reached an instance of the given contact, no need to continue sorting the group's contacts
				if ( $(k).attr('data-id') == $(v).attr('data-id') )
					move = false;
				var d = lc_latest_contact_date_for_contact($(k));
				// If the current contact being compared has no date or an older or identical date
				if ( d == '' || new_date >= d ) {
					// Move the instance of the given contact just before the current contact
					if ( move )
						$(k).before($(v));
					// Resort the group
					lc_sort_group($(k).closest('.lc_group'), new_date);
					return false;
				}
				// Stop iterating if the contact is no longer being sorted
				if ( ! move )
					return false;
			});
		});
	}
	function lc_sort_group( group, new_date ) {
		// Get or update the group's date
		if ( new_date == "undefined" )
			var new_date = lc_latest_contact_date_for_group(group);
		else
			lc_update_group_date(group, new_date);

		// Foreach group
		$.each($('.lc_group').not('.no-sort'), function(i,v) {
			// If the group is the given group, don't move it and return
			if ( $(v).attr('id') == group.attr('id') )
				return false;
			var d = lc_latest_contact_date_for_group($(v));
			// If the current group being compared has no date or an older or identical date
			if ( d == '' || new_date >= d ) {
				// Move the instance of the given group just before the current group
				$(v).before(group);
				return false;
			}
		});
	}
	$( '.lc_new_contact_form' ).submit( function(f) {
		var contact_id   = $(this).find('input[name="comment_post_ID"]').val();
		var tgt          = '.lc_details_' + contact_id;
		var contact      = $(this).closest('.lc_contact');
		var this_contact = '.lc_contact_' + contact_id;
		var group        = contact.closest('.lc_group');

		$.post(ajaxurl, $(this).serialize(), function(data) {
			if ( data == '-1' )
				data = 'error|Unable to save!';
			var resp = data.split( '|', 3 );
			var fadeOut = resp[0] != 'error';

			lc_ajax_response( tgt, resp[0], resp[1], fadeOut );

			if ( fadeOut ) {
				// Update current contact's data to new data (if new contact is latest)
				if ( resp[2] != '' ) {
					var $response = $(resp[2]);
					var new_date = lc_latest_contact_date_for_contact($response);
					var old_date = lc_latest_contact_date_for_contact($(this_contact+':first'));
					if ( old_date == '' || new_date >= old_date ) { // update contact info; resort contact. maybe resort group
						// Update contact header
						$(this_contact).find('h5').html($response.find('h5').html());
						// Update contact details
						$(this_contact).find('.lc_details_info').html($response.find('.lc_details_info').html());
						// Potentially resort the contact
						lc_sort_contact( contact, group );
						// Resort the group if the new contacted date is later or equal than its latest
						var group_date = lc_latest_contact_date_for_group(group);
						// Sort this group (again) to ensure it appears at top of list (above other groups that may contain this contact)
						if ( new_date >= group_date ) {
							// TODO: This is only changing the text. Need to change the attribute text info too.
//							group.find('.lc_group_last_contact').text(new_date).attr('title','');
							lc_sort_group( group, new_date );
						}
					}
				}

				// Reset the contact form fields
				lc_reset_contact_form(contact);


				// Hide the contact form (presume if contact was just contacted, user won't be noting another contacted-at)
				contact.find('.lc_new_contact').hide();
				contact.find('.lc_show_contact_form').show();

				// Scroll to ensure the potentially moved contact is still visible in the view port
				group.find('.lc_contacts').scrollTo(contact, 500, {
					onAfter: function() {
						$(this_contact).effect('highlight', {}, 3000);
					}
				});
//				$('html, body').animate({
//					scrollTop: (contact.offset().top - 80)
//				}, 1000);
			}
		});
		return false;
	} );

	// Hide/show a group
	$( '.lc_hide_group, .lc_show_group' ).submit(function() {
		var grp = $(this).closest('.lc_group');
		$.post(ajaxurl, $(this).serialize(), function() {
			grp.fadeOut('slow', function() {
				$(this).remove();
			});
		});
		return false;
	});

	// Hide/show a contact
	$( '.lc_hide_contact, .lc_show_contact' ).submit(function() {
		var contact = $(this).closest('.lc_contact');
		var id      = contact.attr('data-id');
		$.post(ajaxurl, $(this).serialize(), function() {
			contact.fadeOut('slow', function() {
				// For each group containing this contact, decrement the counter
				$.each( $('.lc_contact_' + id), function(i, v) {
					var group   = $(v).closest('.lc_group');
					var cnt = group.find('.lc_contacts_count').text();
					cnt -= 1;
					if ( cnt < 0 ) cnt = 0;
					group.find('.lc_contacts_count').html(cnt);
					$(v).fadeOut('fast').remove();
				});
				$(this).remove();
			});
		});
		return false;
	});


	//
	// Show new contact form via hover link
	//
	$('.lc_show_contact_form a').mouseenter(function() {
		$(this).parent().siblings('.lc_new_contact').show().find('.lc_content');
		$(this).parent().hide();
	});

	//
	// Hide display of new contacted form when link clicked
	//
	$( '.lc_hide_contact_form' ).click(function() {
		$(this).closest('.lc_new_contact').hide().siblings('.lc_show_contact_form').show();
		return false;
	});

	//
	// Toggle display of hide contact button
	//
	$('.lc_details_info h6').hover(function(){
		$(this).find('.lc_hide_contact').toggle();
	})

	$( '.js h5' ).show(); // Show things that were hidden via CSS to prevent flash of unstyled content

});