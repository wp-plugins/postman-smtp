jQuery(document).ready(function() {
	// hide the advanced options settings
	hide('section#advanced_options_config');

	// on first viewing, determine whether to show password or
	// oauth section
	reloadOauthSection();
	switchBetweenPasswordAndOAuth();

	// add an event on the transport input field
	// when the user changes the transport, determine whether
	// to show or hide the SMTP Settings
	jQuery('select#input_transport_type').change(function() {
		hide('#wizard_oauth2_help');
		reloadOauthSection();
		switchBetweenPasswordAndOAuth();
	});

	// add an event on the authentication input field
	// on user changing the auth type, determine whether to show
	// password or oauth section
	jQuery('select#input_auth_type').change(function() {
		switchBetweenPasswordAndOAuth();
		doneTyping();
	});

	// add an event on the advanced options link
	jQuery('#advanced_options_config a').click(function() {
		show('section#advanced_options_config');
		hide('#advanced_options_config a');
		return false;
	});

	// setup before functions
	var typingTimer; // timer identifier
	var doneTypingInterval = 250; // time in ms, 5 second for
	// example

	// add an event on the hostname input field
	// on keyup, start the countdown
	jQuery(postman_hostname_element_name).keyup(function() {
		clearTimeout(typingTimer);
		if (jQuery(postman_hostname_element_name).val) {
			typingTimer = setTimeout(doneTyping, doneTypingInterval);
		}
	});

	// user is "finished typing," do something
	function doneTyping() {
		if (jQuery(postman_input_auth_type).val() == 'oauth2') {
			reloadOauthSection();
		}
	}
});
function reloadOauthSection() {
	var hostname = jQuery(postman_hostname_element_name).val();
	var transport = jQuery('select#input_transport_type').val();
	var authtype = jQuery('select#input_auth_type').val();
	var data = {
		'action' : 'manual_config',
		'auth_type' : authtype,
		'hostname' : hostname,
		'transport' : transport,
	};
	getRedirectUrl(data);
}
function switchBetweenPasswordAndOAuth() {
	console.debug('showHide:authenticationType=' + $choice);
	if (jQuery('select#input_transport_type').val() == 'gmail_api') {
		hide('section#smtp_config');
		hide('section#password_auth_config');
		show('section#oauth_auth_config');
	} else {
		show('section#smtp_config');
		var $choice = jQuery('select#input_auth_type').val();
		if ($choice == 'none') {
			hide('section#password_auth_config');
			hide('section#oauth_auth_config');
		} else if ($choice != 'oauth2') {
			show('section#password_auth_config');
			hide('section#oauth_auth_config');
		} else {
			hide('section#password_auth_config');
			show('section#oauth_auth_config');
		}
	}
}

