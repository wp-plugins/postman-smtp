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
		jQuery(postman_redirect_url_el).val(response.redirect_url);
		jQuery('#wizard_oauth2_help').html(response.help_text);
		if (response.auth_type) {
			jQuery(postman_input_auth_type).val(response.auth_type);
			jQuery(postman_enc_for_password_el).val(response.enc_type);
			jQuery(postman_enc_for_oauth2_el).val(response.enc_type);
			jQuery(postman_port_element_name).val(response.port);
			var el25 = jQuery('#wizard_port_25');
			var el465 = jQuery('#wizard_port_465');
			var el587 = jQuery('#wizard_port_587');
			if (response.auth_type == postman_auth_oauth2) {
				el25.attr('disabled', 'disabled');
				el465.attr('disabled', 'disabled');
				el587.attr('disabled', 'disabled');
				// hide the auth type field for OAuth screen
				show('.wizard-auth-oauth2');
				hide(postman_enc_for_oauth2_el);
				// allow oauth2 as an authentication choice
				enable(postman_auth_option_oauth2_id);
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
	});
}
