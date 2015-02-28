if (!console)
	console = {
		log : function() {
		}
	}
function disable(identifier) {
	var el = jQuery(identifier);
	console.debug('disabling ' + identifier);
	el.attr('disabled', 'disabled');
}
function enable(identifier) {
	var el = jQuery(identifier);
	console.debug('enabling ' + identifier);
	el.removeAttr('disabled');
}
function hide(identifier) {
	var el = jQuery(identifier);
	console.debug('hiding ' + identifier);
	el.hide();
}
function show(identifier) {
	var el = jQuery(identifier);
	console.debug('showing ' + identifier);
	el.show();
}
function writeable(identifier) {
	var el = jQuery(identifier);
	el.prop("readonly", false);
}
function readonly(identifier) {
	var el = jQuery(identifier);
	el.prop("readonly", true);
}
function getRedirectUrl(data) {
	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			handleConfigurationResponse(response);
		}
	});
}
function handleConfigurationResponse(response) {
	response = response.data;
	if (response.display_auth == 'oauth2') {
		show('p#wizard_oauth2_help');
		jQuery('p#wizard_oauth2_help').html(response.help_text);
		jQuery(postman_redirect_url_el).val(response.redirect_url);
		jQuery('#input_oauth_callback_domain').val(response.callback_domain);
		jQuery('#client_id').html(response.client_id_label);
		jQuery('#client_secret').html(response.client_secret_label);
		jQuery('#redirect_url').html(response.redirect_url_label);
		jQuery('#callback_domain').html(response.callback_domain_label);
	}
	if (response.referer == 'wizard') {
		jQuery('#input_transport_type').val(response.transport_type);
		jQuery('#input_auth_type').val(response.auth_type);
		jQuery('#input_auth_' + response.auth_type).prop('checked', true);
		jQuery(postman_enc_for_password_el).val(response.enc_type);
		jQuery('#input_enc_type').val(response.enc_type);
		jQuery('#input_enc_' + response.enc_type).prop('checked', true);
		if (response.port) {
			enable('#input_port');
			jQuery('#input_port').val(response.port);
		} else {
			disable('#input_port');
		}
		jQuery('#' + response.port_id).prop('checked', true);
		if (!response.user_override) {
			if (response.transport_type) {
				$message = '<span style="color:green">' + response.message
						+ '</span>';
			} else {
				$message = '<span style="color:red">' + response.message
						+ '</span>';
			}
			jQuery('#wizard_recommendation').append($message);
		}
		if (response.hide_auth) {
			hide('.input_auth_type');
		} else {
			show('.input_auth_type');
		}
		if (response.hide_enc) {
			hide('.input_encryption_type');
			enable('#input_enc_ssl');
		} else {
			show('.input_encryption_type');
			disable('#input_enc_ssl');
		}
		// disable the fields we don't use so validation
		// will work
		if (response.display_auth == 'oauth2') {
			show('.wizard-auth-oauth2');
			hide('.wizard-auth-basic');
			// allow oauth2 as an authentication choice
			enable('#input_auth_oauth2');
		} else if (response.display_auth == 'password') {
			hide('.wizard-auth-oauth2');
			show('.wizard-auth-basic');
			enable(postman_input_basic_username);
			enable(postman_input_basic_password);
			disable('#input_auth_oauth2');
		} else {
			hide('.wizard-auth-oauth2');
			hide('.wizard-auth-basic');
			enable('#input_auth_oauth2');
		}
	}
}
// add an event on the authentication input field
// on user changing the auth type, determine whether to show
// password or oauth section
jQuery(document).ready(function() {
	jQuery('a#show-diagnostics').click(function() {
		show('#diagnostic-text');
	});
});
