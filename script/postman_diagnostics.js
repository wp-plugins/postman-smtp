jQuery(document).ready(function() {
	getDiagnosticData();
});

/**
 */
function getDiagnosticData() {
	var data = {
		'action' : 'get_diagnostics'
	};
	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			jQuery('#diagnostic-text').val(response.data.message);
		}
	}).fail(function() {
		alert('The server encountered a problem handling this task.');
	});
}
