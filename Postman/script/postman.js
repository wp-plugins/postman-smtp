if (!console)
	console = {
		log : function() {
		}
	}
debug = false;
function disable($el) {
	$el.attr('disabled', 'disabled');
}
function enable($el) {
	$el.removeAttr('disabled');
}
jQuery(document).ready(function() {
	$passwordSection = jQuery(postman_smtp_section_element_name);
	$oauthSection = jQuery(postman_oauth_section_element_name);
	$passwordEncryptionInput = jQuery(postman_enc_for_password_el);
	$oauthEncryptionInput = jQuery(postman_enc_for_oauth2_el);
	console.debug('el:passwordSection=' + $passwordSection);
	console.debug('el:oauthSection=' + $oauthSection);
	console.debug('el:passwordEncryption=' + $passwordEncryptionInput.val());
	console.debug('el:oauthEncryption=' + $oauthEncryptionInput.val());
	switchBetweenPasswordAndOAuth();
	var $el = jQuery(postman_input_auth_type);
	$el.change(function() {
		switchBetweenPasswordAndOAuth();
	});
});
function switchBetweenPasswordAndOAuth() {
	var $choice = jQuery(postman_input_auth_type).val();
	console.debug('showHide:authenticationType=' + $choice);
	if ($choice == postman_auth_none) {
		if (!debug) {
			$passwordSection.hide();
			$oauthSection.hide();
		}
		disable($passwordEncryptionInput);
		disable($oauthEncryptionInput);
	} else if ($choice == postman_auth_plain || $choice == postman_auth_login
			|| $choice == postman_auth_crammd5) {
		if (!debug) {
			$passwordSection.show();
			$oauthSection.hide();
		}
		enable($passwordEncryptionInput);
		disable($oauthEncryptionInput);
	} else {
		if (!debug) {
			$passwordSection.hide();
			$oauthSection.show();
		}
		disable($passwordEncryptionInput);
		enable($oauthEncryptionInput);
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
			testEl.html('<span style="color:red">Failed - ' + response.message
					+ "</span>");
		}
	});
}