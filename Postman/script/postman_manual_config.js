jQuery(document)
		.ready(
				function() {
					// hide the advanced options settings
					hide('#advanced_options_configuration_section');

					// add an event on the advanced options link
					var $advancedSettingsLink = jQuery('#advanced_options_configuration_display');
					$advancedSettingsLink.click(function() {
						show('#advanced_options_configuration_section');
						hide('#advanced_options_configuration_display');
						return false;
					});

					// add an event on the plugin drop-down
					jQuery('input[name="input_plugin"]').click(function() {
						getConfiguration();
					});

					// on first viewing, determine whether to show password or
					// oauth section
					switchBetweenPasswordAndOAuth();

					// add an event on the authentication input field
					// on user changing the auth type, determine whether to show
					// password or oauth section
					var $el = jQuery(postman_input_auth_type);
					jQuery(postman_input_auth_type).change(function() {
						switchBetweenPasswordAndOAuth();
					});

					// setup before functions
					var typingTimer; // timer identifier
					var doneTypingInterval = 250; // time in ms, 5 second for
					// example

					// add an event on the hostname input field
					// on keyup, start the countdown
					jQuery(postman_hostname_element_name).keyup(
							function() {
								clearTimeout(typingTimer);
								if (jQuery(postman_hostname_element_name).val) {
									typingTimer = setTimeout(doneTyping,
											doneTypingInterval);
								}
							});

					// user is "finished typing," do something
					function doneTyping() {
						if (jQuery(postman_input_auth_type).val() == postman_auth_oauth2) {
							hostname = jQuery(postman_hostname_element_name)
									.val();
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
	var $choice = jQuery(postman_input_auth_type).val();
	console.debug('showHide:authenticationType=' + $choice);
	if ($choice == postman_auth_none) {
		hide(postman_smtp_section_element_name);
		hide(postman_oauth_section_element_name);
		disable(postman_enc_for_password_el);
		disable(postman_enc_for_oauth2_el);
	} else if ($choice == postman_auth_plain || $choice == postman_auth_login
			|| $choice == postman_auth_crammd5) {
		show(postman_smtp_section_element_name);
		hide(postman_oauth_section_element_name);
		enable(postman_enc_for_password_el);
		disable(postman_enc_for_oauth2_el);
	} else {
		hide(postman_smtp_section_element_name);
		show(postman_oauth_section_element_name);
		disable(postman_enc_for_password_el);
		enable(postman_enc_for_oauth2_el);
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
