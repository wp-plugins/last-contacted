jQuery(document).ready(function($) {
	//
	// Prevent groups from being resorted when newly contacted
	//
	$( '.lc_group' ).addClass('no-sort');

	//
	// Don't do anything when group header is clicked
	// (it was originally only a link to be used by accordian on dashboard widget)
	//
	$( '.lc_group h4 a' ).click(function() {
		return false;
	});

	//
	// Give each contact a tooltip
	//
	$( '.lc_contact h5' )
	.click( function() { return false; })
	.tooltip({
		effect: 'toggle',
		predelay: 250,
		relative: true,
		position: 'bottom right',
		offset: [-30, -300],
		tipClass: 'tooltip',
		onBeforeShow: function(event, position) {
			var contact = this.getTrigger().closest('.lc_contact');
			// Ensure the contact form is shown when the contact's details are expanded
			contact.find('.lc_new_contact_form').show();
			// Ensure the default contact method is preselected if a contact method isn't selected
			if ( contact.find('a.chosen_method').size() == 0 )
				contact.find('a.lc_default_contact_method').click();
		},
		onBeforeHide: function(event, position) {
			if ( $( '.ui-datepicker-calendar').is(':visible') )
				event.preventDefault();
		}
	});

	//
	// After submitting a note about newly contacting contact, hide the tooltip
	//
	$( '.lc_save_note' ).click(function() {
		$(this).closest('.lc_details').prev('h5').data('tooltip').hide();
	});
});