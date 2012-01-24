jQuery(document).ready(function($) {

	//
	// Configure the datepicker
	//
	datepicker_config = {
		showOn: "button",
		buttonImage: c2c_LastContacted.plugin_dir + '/css/images/calendar.png',
		buttonImageOnly: true,
		maxDate: '+0d',
		defaultDate: '+0d',
		dateFormat: 'yy-mm-dd',
		nextText: '&raquo;',
		prevText: '&laquo;',
		constrainInput: true,
		gotoCurrent: true,
		hideIfNoPrevNext: true
	};
	$( ".datepicker" ).datepicker(datepicker_config);

	//
	// Configure the autocompleter for user search
	//
	var lc_contact_search_input = $('.lc_contact_search_name');
	var lc_ulist = $('#lc_found_contacts');
	var options = {
		source:   ajaxurl + '?action=lc_search_contacts',
		appendTo: 'form.lc_contact_search',
		select:   function(event,ui){ lc_user_to_list(ui.item.value,ui.item.id); $(lc_contact_search_input).removeClass('ajax-loading');},
		search:   function(){$(lc_contact_search_input).addClass('ajax-loading');},
		open:     function(){$(lc_contact_search_input).removeClass('ajax-loading');},
		close:    function(){$(lc_contact_search_input).val(''); $(lc_contact_search_input).removeClass('ajax-loading');},
		delay: 500, // miliseconds
		minLength: 2,
		autoFocus: true,
	};
	lc_contact_search_input.closest('form').submit(function(e){e.preventDefault(); });
	a = lc_contact_search_input.autocomplete(options);

	function lc_user_to_list( dname, user_id ) {
		/* Don't add if it already exists */
		if ( dname == '' || $('#lc-found-user-' + user_id).length )
			return;

		// Toggle display of things that should be shown if a search contact is listed
		lc_assess_searched_contacts_listing(true);

//		$('.lc-hide-if-no-contact').show();
//		$('.lc-hide-if-contact').hide();

		// If there is already another searched contact, then control display of items
		// that should be shown if multiple contacts are shown
//		if ( $('.lc-found-contact').length )
//			$('.lc-hide-if-no-multi-contact').show();
//		else
//			$('.lc-hide-if-no-multi-contact').hide();

//		lc_ulist.append('<li class="lc-found-user" id="lc-found-user-' + user_id + '"><span class="remove"><a href="#">x</a></span> ' + dname + '</li>');
//		lc_ulist.append('<input type="hidden" name="lc_add_ids[]" id="lc-found-input-' + user_id + '" value="' + user_id + '" />');

		lc_highlight_user(user_id);

		$('.lc_contact_'+user_id+':first')
			.clone()
			.removeClass('lc-highlight')
			.addClass('lc-found-contact')
.find('.datepicker').removeClass('hasDatepicker').datepicker(datepicker_config).closest('.lc_contact')
			.appendTo(lc_ulist)
			.find('h5')
.prepend('<span href="" class="lc-remove-search-contact" title="Click to remove contact from search list">&#x2716;</span>')
				.click( function() { return false; })
				.tooltip(tooltip_config)
			.closest('.lc_contact').find('img.ui-datepicker-trigger:last').remove();
//		$('#lc_found_contacts .lc_contact_'+user_id).find('.datepicker').datepicker(datepicker_config);

//		$('#lc_found_contacts span.remove a').bind('click', function(){
//			lc_remove_user_from_list( $(this).parents('.lc-found-contact').attr('id').split('-').pop() );
//			return false;
//		});

		lc_bind_remove_contact();

		// Add id to the multi-contact form field
		$('#lc_contact_multi form.lc_new_contact_form')
			.append('<input type="hidden" name="contact_ids[]" class="lc-found-input" id="lc-found-input-' + user_id + '" value="' + user_id + '" />');

		lc_contact_search_input.val('');
	}

	// Maybe because this is a 'span' and not an 'a', defining this via 'live' doesn't seem to take
	function lc_bind_remove_contact() {
		// Note: defining this via 'live' doesn't seem to take.
		$('.lc-remove-search-contact').click(function(e) {
			var cid = $(this).closest('.lc-found-contact').attr('data-id');
			$('.lc_contact_' + cid).removeClass('lc-highlight');
			$('#lc-found-input-' + cid).remove();
			$(this).closest('.lc-found-contact').remove();
			lc_assess_searched_contacts_listing();
			e.preventDefault();
		});
	}

	function lc_highlight_user( uid, add_highlight_class ) {
/*
		$('.lc_contact_'+uid)
			.addClass('lc-highlight')
			.closest('.lc_contacts').scrollTo($(this), 500, {
				onAfter: function() {
					$(this).closest('.lc_group').effect('highlight', {}, 3000);
				}
			});
*/
		$.each($('.lc_contact_'+uid), function(i,v) {
			$(v)
				.closest('.lc_contacts').not('#lc-search-contacts').scrollTo($(v), 500, {
					onAfter: function() {
						if ( add_highlight_class != false )
							$(v).addClass('lc-highlight');
						$(this).effect('highlight', {}, '3000');//, function() { $(v).addClass('lc-highlight'); });
					}
				});
		});
	}

//	function lc_remove_user_from_list( uid ) {
//		$('.lc_contact_' + uid).removeClass('lc-highlight');
//		$('#lc-found-contact-' + uid).remove();
//		$('#lc-found-input-' + uid).remove();
//	}

	/* Ensure proper visibility of the following classes based on
	 * contacts in the search listing:
	 *  lc-hide-if-contact, lc-hide-if-no-contact, lc-hide-if-no-multi-contact
	 */
	function lc_assess_searched_contacts_listing(assume_one) {
		var count = $('.lc-found-contact').length;
		if ( assume_one === true )
			count += 1;
		if ( count ) {
			$('.lc-hide-if-contact').hide();
			$('.lc-hide-if-no-contact').show();
			if ( count > 1 )
				$('.lc-hide-if-no-multi-contact').show();
			else
				$('.lc-hide-if-no-multi-contact').hide();
		} else {
			$('.lc-hide-if-contact').show();
			$('.lc-hide-if-no-contact').hide();
			$('.lc-hide-if-no-multi-contact').hide();
		}
		$( '.lc_contact_search_name').focus();
	}

	// Bind to button to clear all contacts from search list
	$('#lc_clear_found_form').submit(function(e) {
		$('.lc-found-contact').remove();
		$('.lc-found-input').remove();
		$('.lc-highlight').removeClass('lc-highlight');
		lc_assess_searched_contacts_listing();
		e.preventDefault();
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
	$( '.lc_reset_contact_form' ).live('click', function() {
		return lc_reset_contact_form($(this).closest('.lc_contact'));
	});

	//
	// Clicking of contact method linked images should set associated hidden radio button
	//
	$( '.lc_contact_methods > a' ).live('click', function() {
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
	// group arg isn't used
	function lc_sort_contact( contact, group ) {
//		var new_date = lc_latest_contact_date_for_contact(contact);
var contact = $(contact + ':first');
var new_date = lc_latest_contact_date_for_contact(contact);
		// Foreach instance of the given contact
		$.each($('.lc_contact_'+contact.attr('data-id')).not('.lc-found-contact'), function(i,v) {
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
		// Scroll to ensure contact is visible in group
		lc_highlight_user(contact.attr('data-id'), false);
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
	function lc_contact_contacted(contact_id, response) {
//		var contact      = $(this).closest('.lc_contact');
		var this_contact = '.lc_contact_' + contact_id;
//		var group        = contact.closest('.lc_group');
		var new_date = lc_latest_contact_date_for_contact(response);
		var old_date = lc_latest_contact_date_for_contact($(this_contact+':first'));
		if ( old_date == '' || new_date >= old_date ) { // update contact info; resort contact. maybe resort group
			// Update contact header
			$(this_contact).find('h5 span.last_contact').replaceWith(response.find('h5 span.last_contact').clone());
			// Update contact details
			$(this_contact).find('.lc_last_contacted_info').html(response.find('.lc_last_contacted_info').html());
			// Potentially resort the contact
			lc_sort_contact( this_contact, '' );
//			// If there is an instance of this contact in the search list, need to add the remove link
//			$('#lc_found_contacts .lc_contact_' + contact_id + ' h5')
//				.prepend('<span href="" class="lc-remove-search-contact" title="Click to remove contact from search list">&#x2716;</span>')
			lc_bind_remove_contact();

//			// Resort the group if the new contacted date is later or equal than its latest
//			var group_date = lc_latest_contact_date_for_group(group);
//			// Sort this group (again) to ensure it appears at top of list (above other groups that may contain this contact)
//			if ( new_date >= group_date ) {
//				// TODO: This is only changing the text. Need to change the attribute text info too.
///				group.find('.lc_group_last_contact').text(new_date).attr('title','');
//				lc_sort_group( group, new_date );
//			}
		}
	}
	$( '.lc_new_contact_form' ).live('submit', function(f) {
//		var contact_id   = $(this).find('input[name="comment_post_ID"]').val();
//		var tgt          = '.lc_details_' + contact_id;
		var contact      = $(this).closest('.lc_contact');
//		var this_contact = '.lc_contact_' + contact_id;
//		var group        = contact.closest('.lc_group');

		$.post(ajaxurl, $(this).serialize(), function(data) {
			if ( data == '-1' )
				data = 'error|Unable to save!';
			var resp = data.split( '|', 4 );
			var fadeOut = resp[0] != 'error';

//			lc_ajax_response( tgt, resp[0], resp[1], fadeOut );

			if ( fadeOut ) {
				// Update current contact's data to new data (if new contact is latest)
				if ( resp[2] != '' ) {
					$.each(resp[2].split(','), function(i,v) {
						lc_contact_contacted(v, $(resp[3]));
					});

/*
					var $response = $(resp[3]);
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
*/
				}

				// Reset the contact form fields
				lc_reset_contact_form(contact);


				// Hide the contact form (presume if contact was just contacted, user won't be noting another contacted-at)
				contact.not('#lc_contact_multi').find('.lc_new_contact').hide();
				contact.not('#lc_contact_multi').find('.lc_show_contact_form').show();
/*
				// Scroll to ensure the potentially moved contact is still visible in the view port
				group.find('.lc_contacts').scrollTo(contact, 500, {
					onAfter: function() {
						$(this_contact).effect('highlight', {}, 3000);
					}
				});
*/
//				$('html, body').animate({
//					scrollTop: (contact.offset().top - 80)
//				}, 1000);
			}
		});
		return false;
	} );

	// Hide/show a group
	$( '.lc_hide_group, .lc_show_group' ).live('submit', function() {
		var grp = $(this).closest('.lc_group');
		$.post(ajaxurl, $(this).serialize(), function() {
			grp.fadeOut('slow', function() {
				$(this).remove();
			});
		});
		return false;
	});

	// Hide/show a contact
	$( '.lc_hide_contact, .lc_show_contact' ).live('submit', function() {
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
	$('.lc_show_contact_form a').live('mouseenter', function() {
		$(this).parent().siblings('.lc_new_contact').show().find('.lc_content');
		$(this).parent().hide();
	});

	//
	// Hide display of new contacted form when link clicked
	//
	$( '.lc_hide_contact_form' ).live('click', function() {
		$(this).closest('.lc_new_contact').hide().siblings('.lc_show_contact_form').show();
		return false;
	});

	//
	// Toggle display of hide contact button
	//
	$('.lc_details_info h6').live('hover', function(){
		$(this).find('.lc_hide_contact').toggle();
	})

	$( '.js h5' ).show(); // Show things that were hidden via CSS to prevent flash of unstyled content

});