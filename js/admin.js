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
	tooltip_config = {
		effect: 'toggle',
		predelay: 250,
		relative: true,
		position: 'bottom center',
		offset: [-30, 20], // If changing the -30 vertical offset, change it also in onShow handler
		tipClass: 'tooltip',
		onBeforeShow: function(event, position) {
			if ( this.getTrigger().data('over-span') === true ) {
				event.preventDefault();
				return;
			}
			var contact = this.getTrigger().closest('.lc_contact');
			// Ensure the contact form is shown when the contact's details are expanded
			contact.find('.lc_new_contact_form').show();
			// Ensure the default contact method is preselected if a contact method isn't selected
			if ( contact.find('a.chosen_method').size() == 0 )
				contact.find('a.lc_default_contact_method').click();
		},
		// The onShow handler exists solely to vertically adjust a tooltip for trigger objects taller than normal
		onShow: function(event, position) {
			var trigger_top = Math.round(this.getTrigger().offset().top);
			var tip_top     = Math.round(this.getTip().css('top').slice(0,-2));
			var diff = trigger_top - tip_top;
			if ( diff - 30 != 0 ) // The 30 is the original offset set above
				this.getTip().css('top',tip_top - diff - 2);
		},
		onBeforeHide: function(event, position) {
			if ( $( '.ui-datepicker-calendar').is(':visible') )
				event.preventDefault();
		}
	};
	$( '.lc_contact h5' )
		.click( function() { return false; })
		.tooltip(tooltip_config);

	// Prevent contact popup from appearing if hovering over last contact date
	$( '.lc_contact h5 span' ).live('mouseenter', function(){
		$(this).parent().data('over-span',true);
	}).live('mouseleave', function() {
		$(this).parent().data('over-span',false);
	});

	//
	// After submitting a note about newly contacting contact, hide the tooltip
	//
	$( '.lc_save_note' ).live('click', function() {
		$(this).closest('.lc_details').prev('h5').data('tooltip').hide();
	});

	$( '.lc_contact_search_name').focus();
});
