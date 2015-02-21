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
	jQuery('#input_oauth_callback_domain').val(response.callback_domain);
	if (response.auth_type) {
		if (response.auth_type != '') {
			jQuery(postman_input_auth_type).val(response.auth_type);
		}
		jQuery(postman_enc_for_password_el).val(response.enc_type);
		if (response.enc_type != '') {
			jQuery(postman_enc_for_oauth2_el).val(response.enc_type);
		}
		if (response.port != '') {
			jQuery(postman_port_element_name).val(response.port);
		}
		var el25 = jQuery('#wizard_port_25');
		var el465 = jQuery('#wizard_port_465');
		var el587 = jQuery('#wizard_port_587');
		// disable the fields we don't use so validation
		// will work
		if (response.display_auth == 'oauth2') {
			show('.wizard-auth-oauth2');
			show('p#wizard_oauth2_help');
			jQuery(postman_redirect_url_el).val(response.redirect_url);
			jQuery('p#wizard_oauth2_help').html(response.help_text);
			jQuery('#client_id').html(response.client_id_label);
			jQuery('#client_secret').html(response.client_secret_label);
			jQuery('#redirect_url').html(response.redirect_url_label);
			jQuery('#callback_domain').html(response.callback_domain_label);
			el25.attr('disabled', 'disabled');
			el465.attr('disabled', 'disabled');
			el587.attr('disabled', 'disabled');
			// hide the auth type field for OAuth screen
			if (data.referer == 'wizard')
				hide(postman_enc_for_oauth2_el);
			// allow oauth2 as an authentication choice
			enable(postman_auth_option_oauth2_id);
			disable(postman_input_basic_username);
			disable(postman_input_basic_password);
		} else if (response.displayAuth == 'password') {
			show('.wizard-auth-basic');
			disable(postman_auth_option_none_id);
			enable(postman_input_basic_username);
			enable(postman_input_basic_password);
		} else {
			hide('.wizard-auth-oauth2');
			hide('.wizard-auth-basic');
		}
		if (response.port == 25) {
			el25.prop("checked", true);
			enable('#wizard_port_25');
		} else if (response.port == 465) {
			el465.prop("checked", true);
			enable('#wizard_port_465');
		} else if (response.port == 587) {
			el587.prop("checked", true);
			enable('#wizard_port_587');
		}
	}
}