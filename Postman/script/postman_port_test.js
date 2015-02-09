jQuery(document).ready(function() {
	var $el = jQuery('input#begin-port-test');
	$el.click(function() {
		valid = jQuery('#port_test_form_id').valid();
		if (!valid) {
			return false;
		}
		$el.attr('disabled', 'disabled');
		var $elTestingTable = jQuery('#testing_table');
		$elTestingTable.show();

		portTest('#port-test-port-25', 25);
		portTest('#port-test-port-587', 587);
		portTest('#port-test-port-465', 465);

		//
		return false;
	});
});
function portTest(tdValue, port) {
	var testEl = jQuery(tdValue);
	testEl.html('Testing');
	var data = {
		'action' : 'test_port',
		'hostname' : jQuery(postman_hostname_element_name).val(),
		'port' : port
	// We pass php values differently!
	};
	// We can also pass the url value separately from ajaxurl for front end AJAX
	// implementations
	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			testEl.html('<span style="color:green">Open</span>');
		} else {
			testEl.html('<span style="color:red">Closed (' + response.message
					+ ")</span>");
		}
	});
}