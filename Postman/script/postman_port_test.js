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
		portTest('#port-test-port-443', 443, $el);
		portTest('#port-test-port-587', 587, $el);
		portTest('#port-test-port-465', 465, $el);

		//
		return false;
	});
});
function portTest(tdValue, port, button) {
	portsToBeTested += 1;
	var testEl = jQuery(tdValue);
	testEl.html(postman_port_test_testing);
	var data = {
		'action' : 'port_quiz_test',
		'hostname' : jQuery(postman_hostname_element_name).val(),
		'port' : port
	};
	jQuery.post(
			ajaxurl,
			data,
			function(response) {
				if (response.success) {
					testEl.html('<span style="color:green">'
							+ postman_port_test_open + '</span>');
					// start the next test
					portTest2(port);
				} else {
					testEl.html('<span style="color:red">'
							+ postman_port_test_closed + '</span>');
					totalPortsTested += 1;
				}
				if (totalPortsTested >= portsToBeTested) {
					enable(button);
				}
			}).fail(
			function() {
				totalPortsTested += 1;
				testEl.html('<span style="color:red">'
						+ postman_port_test_closed + '</span> ('
						+ postman_email_test.failed + ")");
				if (totalPortsTested >= portsToBeTested) {
					enable(button);
				}
			});
	;
}
function portTest2(port) {
	portsToBeTested += 1;
	var testEl = jQuery('#smtp_test_port_' + port);
	testEl.html(postman_port_test_testing);
	var data = {
		'action' : 'test_port',
		'hostname' : jQuery(postman_hostname_element_name).val(),
		'port' : port
	};
	jQuery.post(
			ajaxurl,
			data,
			function(response) {
				totalPortsTested += 1;
				if (response.success) {
					testEl.html('<span style="color:green">' + postman_yes
							+ '</span>');
					// start the next test
				} else {
					testEl.html('<span style="color:red">' + postman_no
							+ '</span>');
					// start the next test
					portTest3(port);
				}
			}).fail(function() {
		testEl.html('<span style="color:red">' + postman_no + '</span>');
	});
	;
	totalPortsTested += 1;
}
function portTest3(port) {
	portsToBeTested += 1;
	var testEl = jQuery('#smtps_test_port_' + port);
	testEl.html(postman_port_test_testing);
	var data = {
		'action' : 'test_smtps',
		'hostname' : jQuery(postman_hostname_element_name).val(),
		'port' : port
	};
	jQuery.post(
			ajaxurl,
			data,
			function(response) {
				totalPortsTested += 1;
				if (response.success) {
					testEl.html('<span style="color:green">' + postman_yes
							+ '</span>');
					// start the next test
				} else {
					testEl.html('<span style="color:red">' + postman_no
							+ '</span>');
				}
			}).fail(function() {
		testEl.html('<span style="color:red">' + postman_no + '</span>');
	});
	;
	totalPortsTested += 1;
}