jQuery(document).ready(
		function() {
			switchBetweenPasswordAndOAuth();
			var $el = jQuery(postman_input_auth_type);
			$el.change(function() {
				switchBetweenPasswordAndOAuth();
			});
			var $advanced = jQuery('#enable_advanced_settings');
			$advanced.click(function() {
				if ($advanced.is(':checked')) {
					writeable('#input_connection_timeout');
					writeable('#input_read_timeout');
					writeable('#input_reply_to');
				} else {
					readonly('#input_connection_timeout');
					readonly('#input_read_timeout');
					readonly('#input_reply_to');
				}
			});

			// setup before functions
			var typingTimer; // timer identifier
			var doneTypingInterval = 250; // time in ms, 5 second for example

			// on keyup, start the countdown
			jQuery(postman_hostname_element_name).keyup(function() {
				clearTimeout(typingTimer);
				if (jQuery(postman_hostname_element_name).val) {
					typingTimer = setTimeout(doneTyping, doneTypingInterval);
				}
			});

			// user is "finished typing," do something
			function doneTyping() {
				hostname = jQuery(postman_hostname_element_name).val();
				getRedirectUrl(hostname, postman_redirect_url_el,
						'#wizard_oauth2_help');
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
