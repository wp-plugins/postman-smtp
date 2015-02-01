jQuery(document).ready(function() {
	showHide();
	var $el = jQuery(postman_auth_element_name);
	$el.change(function() {
		showHide();
	});
});
function showHide() {
	var $el = jQuery(postman_auth_element_name);
	$choice = $el.val();
	var $div1 = jQuery(postman_smtp_section_element_name);
	var $div2 = jQuery(postman_oauth_section_element_name);
	var $divEl = jQuery(postman_port_element_name);
	var $hostnameEl = jQuery(postman_hostname_element_name);
	if ($choice == postman_auth_none) {
		$divEl.val(25);
		$div1.hide();
		$div2.hide();
	} else if ($choice == postman_auth_basic_ssl) {
		$divEl.val(465);
		$div1.show();
		$div2.hide();
	} else if ($choice == postman_auth_basic_tls) {
		$divEl.val(587);
		$div1.show();
		$div2.hide();
	} else {
		$hostnameEl.val('smtp.gmail.com');
		$divEl.val(465);
		$div1.hide();
		$div2.show();
	}
}
jQuery(document).ready(function() {
	var $el = jQuery('input#begin-port-test');
	$el.click(function() {
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
		'hostname' : jQuery('input#hostname').val(),
		'port' : port
	// We pass php values differently!
	};
	// We can also pass the url value separately from ajaxurl for front end AJAX
	// implementations
	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			testEl.html('<span style="color:green">Open</span>');
		} else {
			testEl.html('<span style="color:red">Failed - ' + response.message + "</span>");
		}
	});
}