jQuery(document).ready(function() {
	var $el = jQuery('input#begin-port-test');
	jQuery(postman_hostname_element_name).focus();
	$el.click(function() {
		valid = jQuery('#port_test_form_id').valid();
		if (!valid) {
			return false;
		}
		$el.attr('disabled', 'disabled');
		var $elTestingTable = jQuery('#testing_table');
		$elTestingTable.show();

		totalPortsTested = 0;
		portsToBeTested = 0;
		portTest('#port-test-port-25', 25, $el);
		portTest('#port-test-port-587', 587, $el);
		portTest('#port-test-port-465', 465, $el);

		//
		return false;
	});
});
function portTest(tdValue, port, button) {
	portsToBeTested += 1;
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
		totalPortsTested += 1;
		if (response.success) {
			testEl.html('<span style="color:green">Open</span>');
		} else {
			testEl.html('<span style="color:red">Closed (' + response.message
					+ ")</span>");
		}
		if (totalPortsTested >= portsToBeTested) {
			enable(button);
		}
	});
}