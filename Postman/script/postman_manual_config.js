jQuery(document).ready(function() {
	// hide the advanced options settings
	hide('section#advanced_options_config');

	// on first viewing, determine whether to show password or
	// oauth section
	switchBetweenPasswordAndOAuth();

	// add an event on the plugin drop-down
	jQuery('input[name="input_plugin"]').click(function() {
		getConfiguration();
	});

	// add an event on the transport input field
	// when the user changes the transport, determine whether
	// to show or hide the SMTP Settings
	jQuery('select#input_transport_type').change(function() {
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
		if (jQuery(postman_input_auth_type).val() == postman_auth_oauth2) {
			hostname = jQuery(postman_hostname_element_name).val();
			var data = {
				'action' : 'get_redirect_url',
				'referer' : 'manual_config',
				'hostname' : hostname,
			};
			getRedirectUrl(data);
		}
	}
});
function switchBetweenPasswordAndOAuth() {
	console.debug('showHide:authenticationType=' + $choice);
	if (jQuery('select#input_transport_type').val() == 'gmail_api') {
		hide('section#smtp_config');
		hide('section#password_auth_config');
		show('section#oauth_auth_config');
	} else {
		show('section#smtp_config');
		var $choice = jQuery('select#input_auth_type').val();
		if ($choice == postman_auth_none) {
			hide('section#password_auth_config');
			hide('section#oauth_auth_config');
		} else if ($choice == postman_auth_plain
				|| $choice == postman_auth_login
				|| $choice == postman_auth_crammd5) {
			show('section#password_auth_config');
			hide('section#oauth_auth_config');
		} else {
			hide('section#password_auth_config');
			show('section#oauth_auth_config');
		}
	}
}

function getConfiguration() {
	var plugin = jQuery('input[name="input_plugin"]' + ':checked').val();
	if (plugin != '') {
		var data = {
			'action' : 'get_configuration',
			'plugin' : plugin
		};
		jQuery.post(ajaxurl, data, function(response) {
			if (response.success) {
				jQuery(postman_input_sender_email).val(response.sender_email);
				jQuery(postman_input_sender_name).val(response.sender_name);
				jQuery(postman_hostname_element_name).val(response.hostname);
				jQuery(postman_port_element_name).val(response.port);
				jQuery(postman_input_auth_type).val(response.auth_type);
				jQuery(postman_enc_for_password_el).val(response.enc_type);
				jQuery(postman_input_basic_username).val(
						response.basic_auth_username);
				jQuery(postman_input_basic_password).val(
						response.basic_auth_password);
				switchBetweenPasswordAndOAuth();
			}
		});
	} else {
		jQuery(postman_input_sender_email).val('');
		jQuery(postman_input_sender_name).val('');
		jQuery(postman_input_basic_username).val('');
		jQuery(postman_input_basic_password).val('');
		jQuery(postman_hostname_element_name).val('');
		jQuery(postman_port_element_name).val('');
		jQuery(postman_input_auth_type).val(postman_auth_none);
		jQuery(postman_enc_for_password_el).val(postman_enc_none);
		switchBetweenPasswordAndOAuth();
	}
}
