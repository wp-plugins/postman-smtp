jQuery(document).ready(
		function() {

			// display password on entry
			enablePasswordDisplayOnEntry();
			
			// tabs
			jQuery("#config_tabs").tabs();

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
	var transport = jQuery('#input_transport_type').val();
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
		hide('div#smtp_config');
		hide('div#password_settings');
		show('div#oauth_settings');
	} else {
		show('div#smtp_config');
		var $choice = jQuery('select#input_auth_type').val();
		if ($choice == 'none') {
			hide('div#password_settings');
			hide('div#oauth_settings');
		} else if ($choice != 'oauth2') {
			show('div#password_settings');
			hide('div#oauth_settings');
		} else {
			hide('div#password_settings');
			show('div#oauth_settings');
		}
	}
}
