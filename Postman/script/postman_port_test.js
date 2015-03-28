jQuery(document).ready(function() {
	var $el = jQuery('input#begin-port-test');
	jQuery(postman_hostname_element_name).focus();
	$el.click(function() {
		valid = jQuery('#port_test_form_id').valid();
		if (!valid) {
			return false;
		}
		$el.attr('disabled', 'disabled');
		var $elTestingTable = jQuery('#connectivity_test_table');
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
	resetView(port);
	show('#conclusion');
	portsToBeTested += 1;
	var testEl = jQuery(tdValue);
	testEl.html('<span style="color:blue">' + postman_port_test_testing
			+ '</span>');
	var hostname = jQuery(postman_hostname_element_name).val();
	var data = {
		'action' : 'port_quiz_test',
		'hostname' : hostname,
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
				} else {
					testEl.html('<span style="color:red">'
							+ postman_port_test_closed + '</span>');
					show('#blocked-port-help');
				}
				if (port != 443) {
					portTest2(hostname, port, button, response.success);
				} else {
					totalPortsTested += 1;
					enableButtonCheck(button);
					if (response.success) {
						addConclusion(postman_443_open);
					} else {
						addConclusion(postman_443_closed);
					}
				}
			}).fail(
			function() {
				totalPortsTested += 1;
				testEl.html('<span style="color:red">'
						+ postman_port_test_closed + '</span> ('
						+ postman_email_test.failed + ")");
				enableButtonCheck(button);
			});
}
function portTest2(hostname, port, button, open) {
	var testEl = jQuery('#smtp_test_port_' + port);
	testEl.html('<span style="color:blue">' + postman_port_test_testing
			+ '</span>');
	var data = {
		'action' : 'test_port',
		'hostname' : hostname,
		'port' : port
	};
	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			totalPortsTested += 1;
			testEl.html('<span style="color:green">SMTP</span>');
			inspectResponse(response, port);
			addConclusion(sprintf(postman_smtp_success, port, hostname));
		} else {
			// start the SMTPS test
			portTest3(hostname, port, button, open);
		}
		enableButtonCheck(button);
	}).fail(function() {
		totalPortsTested += 1;
		testEl.html('<span style="color:red">' + postman_no + '</span>');
		enableButtonCheck(button);
	});
}
function portTest3(hostname, port, button, open) {
	var testEl = jQuery('#smtp_test_port_' + port);
	testEl.html('<span style="color:blue">' + postman_port_test_testing
			+ '</span>');
	var data = {
		'action' : 'test_smtps',
		'hostname' : hostname,
		'port' : port
	};
	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			testEl.html('<span style="color:green">SMTPS</span>');
			inspectResponse(response, port);
			addConclusion(sprintf(postman_smtp_success, port, hostname));
		} else {
			testEl.html('<span style="color:red">' + postman_no + '</span>');
			if (open) {
				addConclusion(sprintf(postman_try_dif_smtp, port, hostname));
			} else {
				addConclusion(sprintf(postman_port_blocked, port));
			}
		}
		totalPortsTested += 1;
		enableButtonCheck(button);
	}).fail(function() {
		totalPortsTested += 1;
		testEl.html('<span style="color:red">' + postman_no + '</span>');
		enableButtonCheck(button);
	});
}
function enableButtonCheck(button) {
	if (totalPortsTested >= portsToBeTested) {
		enable(button);
	}
}
function inspectResponse(response, port) {
	var testEl = jQuery('#starttls_test_port_' + port);
	if (response.start_tls) {
		testEl.html('<span style="color:green">' + postman_yes + '</span>');
	} else {
		testEl.html('<span>' + postman_no + '</span>');
	}
	var testEl = jQuery('#auth_none_test_port_' + port);
	if (response.auth_none) {
		testEl.html('<span style="color:green">' + postman_yes + '</span>');
	} else {
		testEl.html('<span>' + postman_no + '</span>');
	}
	var testEl = jQuery('#auth_plain_test_port_' + port);
	if (response.auth_plain) {
		testEl.html('<span style="color:green">' + postman_yes + '</span>');
	} else {
		testEl.html('<span>' + postman_no + '</span>');
	}
	var testEl = jQuery('#auth_login_test_port_' + port);
	if (response.auth_login) {
		testEl.html('<span style="color:green">' + postman_yes + '</span>');
	} else {
		testEl.html('<span>' + postman_no + '</span>');
	}
	var testEl = jQuery('#auth_crammd5_test_port_' + port);
	if (response.auth_crammd5) {
		testEl.html('<span style="color:green">' + postman_yes + '</span>');
	} else {
		testEl.html('<span>' + postman_no + '</span>');
	}
	var testEl = jQuery('#auth_xoauth_test_port_' + port);
	if (response.auth_xoauth) {
		testEl.html('<span style="color:green">' + postman_yes + '</span>');
	} else {
		testEl.html('<span>' + postman_no + '</span>');
	}
}
function resetView(port) {
	var testEl = jQuery('#port-test-port-' + port);
	testEl.html('-');
	var testEl = jQuery('#smtp_test_port_' + port);
	testEl.html('-');
	var testEl = jQuery('#smtps_test_port_' + port);
	testEl.html('-');
	var testEl = jQuery('#starttls_test_port_' + port);
	testEl.html('-');
	var testEl = jQuery('#auth_none_test_port_' + port);
	testEl.html('-');
	var testEl = jQuery('#auth_plain_test_port_' + port);
	testEl.html('-');
	var testEl = jQuery('#auth_login_test_port_' + port);
	testEl.html('-');
	var testEl = jQuery('#auth_crammd5_test_port_' + port);
	testEl.html('-');
	var testEl = jQuery('#auth_xoauth_test_port_' + port);
	testEl.html('-');
	jQuery('ol.conclusion').html('');
}
function addConclusion(message) {
	jQuery('ol.conclusion').append('<li>' + message + '</li>');
}