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
function getRedirectUrl(hostname, el_redirect_url, el_help_text) {
	var data = {
		'action' : 'get_redirect_url',
		'hostname' : hostname
	};
	jQuery.post(ajaxurl, data, function(response) {
		jQuery(el_redirect_url).val(response.redirect_url);
		if (typeof el_help_text !== 'undefined')
			jQuery(el_help_text).html(response.help_text);
	});
}
