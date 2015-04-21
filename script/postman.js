var redirectUrlWarning = false;
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
	el.hide("fast");
}
function show(identifier) {
	var el = jQuery(identifier);
	console.debug('showing ' + identifier);
	el.show("fast");
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
}
// add an event on the authentication input field
// on user changing the auth type, determine whether to show
// password or oauth section
jQuery(document).ready(function() {
	jQuery('a#show-diagnostics').click(function() {
		show('#diagnostic-text');
	});
});
