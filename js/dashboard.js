jQuery(document).ready(function($) {

	//
	// Make the contact groups listing an accordian
	//
	$( "#last_contacted" ).accordion({
		active: false,
		collapsible: true,
		header: 'h4',
		fillSpace: false,
		autoHeight:false,
		clearStyle:true,
		changestart: function(event, ui) {
			ui.newHeader.find('.lc_group_last_contact').hide();
			ui.newHeader.find('.lc_group_sort').show();
			ui.oldHeader.find('.lc_group_sort').hide();
			ui.oldHeader.find('.lc_group_last_contact').show();
		}
	});

	//
	// Make each contacts listing an accordian
	//
	$( '.abc' ).accordion({
		active: false,
		collapsible: true,
		header: 'h5',
		fillSpace: false,
		autoHeight:false,
		changestart: function(event, ui) {
			var contact = ui.newHeader.closest('.lc_contact');
			// Ensure the contact form is shown when the contact's details are expanded
			contact.find('.lc_new_contact_form').show();
			// Ensure the default contact method is preselected if a contact method isn't selected
			if ( contact.find('a.chosen_method').size() == 0 )
				contact.find('a.lc_default_contact_method').click();
		}
	});

	$( '#last_contacted, .js h5' ).show(); // Show things that were hidden via CSS to prevent flash of unstyled content

});