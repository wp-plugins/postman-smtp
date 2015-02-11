jQuery(document).ready(
		function() {
			jQuery(postman_input_sender_email).focus();
			jQuery("#postman_wizard")
					.steps(
							{
								bodyTag : "fieldset",
								headerTag : "h1",
								transitionEffect : "slideLeft",
								stepsOrientation : "vertical",
								autoFocus : true,
								onStepChanging : function(event, currentIndex,
										newIndex) {
									return handleStepChange(event,
											currentIndex, newIndex,
											jQuery(this));

								},
								onStepChanged : function(event, currentIndex,
										priorIndex) {
									return postHandleStepChange(event,
											currentIndex, priorIndex,
											jQuery(this));
								},
								onFinishing : function(event, currentIndex) {
									var form = jQuery(this);

									jQuery('.wizard-auth-oauth2').show();
									jQuery('.wizard-auth-basic').show();
									jQuery(postman_encryption_group).show();
									// Disable validation on fields that
									// are disabled.
									// At this point it's recommended to
									// do an overall check (mean
									// ignoring
									// only disabled fields)
									// form.validate().settings.ignore =
									// ":disabled";

									// Start validation; Prevent form
									// submission if false
									return form.valid();
								},
								onFinished : function(event, currentIndex) {
									var form = jQuery(this);

									// Submit form input
									form.submit();
								}
							}).validate({
						errorPlacement : function(error, element) {
							element.before(error);
						},
						rules : {
							confirm : {
								equalTo : "#password"
							}
						}
					});
		});
function handleStepChange(event, currentIndex, newIndex, form) {
	// Always allow going backward even if
	// the current step contains invalid fields!
	if (currentIndex > newIndex) {
		return true;
	}

	// Clean up if user went backward
	// before
	if (currentIndex < newIndex) {
		// To remove error styles
		jQuery(".body:eq(" + newIndex + ") label.error", form).remove();
		jQuery(".body:eq(" + newIndex + ") .error", form).removeClass("error");
	}

	// Disable validation on fields that
	// are disabled or hidden.
	form.validate().settings.ignore = ":disabled,:hidden";

	// Start validation; Prevent going
	// forward if false
	valid = form.valid();
	if (!valid) {
		return false;
	}

	if (currentIndex === 0) {
		// page 1 : look-up the email
		// address for the smtp server
		checkEmail(jQuery(postman_input_sender_email).val());
	} else if (currentIndex === 1) {
		// page 2 : check the port
		portsChecked = 0;
		portsToCheck = 0;
		// allow the user to choose any
		// port
		portCheckBlocksUi = true;
		wizardPortTest(jQuery('#wizard_port_465'),
				jQuery('#wizard_port_465_status'));
		wizardPortTest(jQuery('#wizard_port_25'),
				jQuery('#wizard_port_25_status'));
		wizardPortTest(jQuery('#wizard_port_587'),
				jQuery('#wizard_port_587_status'));
	} else if (currentIndex === 2) {

		// user has clicked next from ports-check page
		if (portCheckBlocksUi) {
			return false;
		}
		var chosenPort = jQuery(postman_port_element_name).val();
		var hostname = jQuery(postman_hostname_element_name).val();

		// on the Auth type drop-down, add events to enable/disable user/pass
		jQuery(postman_input_auth_type).click(function() {
			var $val = jQuery(postman_input_auth_type).val();
			if ($val == 'none') {
				disable(postman_input_basic_username);
				disable(postman_input_basic_password);
				disable(postman_enc_for_password_el);
				setEncryptionType(postman_enc_none);
			} else {
				enable(postman_input_basic_username);
				enable(postman_input_basic_password);
				// for the next two lines, i assume this is port 587 because
				// that's
				// currently the only time the user can change the auth type
				enable(postman_enc_for_password_el);
				setEncryptionType(postman_enc_tls);
			}
		});

		// hide both the oauth section and the password section
		hide('.wizard-auth-oauth2');
		hide('.wizard-auth-basic');
		disable(postman_auth_option_oauth2_id);
		disable(postman_auth_option_none_id);
		if (hostname == 'smtp.gmail.com' && chosenPort == 465) {
			// setup Gmail with OAuth2
			populateRedirectUrl(hostname);
			setAuthType(postman_auth_oauth2);
			setEncryptionType(postman_enc_ssl);
			// hide the auth type field for OAuth screen
			show('.wizard-auth-oauth2');
			hide(postman_enc_for_oauth2_el);
			// allow oauth2 as an authentication choice
			enable(postman_auth_option_oauth2_id);
		} else if (hostname == 'smtp.live.com' && chosenPort == 587) {
			// setup Hotmail with OAuth2
			populateRedirectUrl(hostname);
			setAuthType(postman_auth_oauth2);
			setEncryptionType(postman_enc_tls);
			// hide the auth type field for OAuth screen
			show('.wizard-auth-oauth2');
			hide(postman_enc_for_oauth2_el);
			enable(postman_auth_option_oauth2_id);
		} else if (chosenPort == 465) {

			// set authentication and encryption types
			setAuthType(postman_auth_login);
			setEncryptionType(postman_enc_ssl);

			// eanble user/pass fields
			enablePasswordFields();

			show('.wizard-auth-basic');
			enable(postman_enc_option_ssl_id);
			hide(postman_encryption_group);
			jQuery('.port-explanation-ssl').show();
			hide('.port-explanation-tls');
		} else if (chosenPort == 587) {
			// allow none as an authentication choice
			enable(postman_auth_option_none_id);

			// set authentication and encryption types
			setAuthType(postman_auth_login);
			setEncryptionType(postman_enc_tls);

			// eanble user/pass fields
			enablePasswordFields();

			disable(postman_enc_option_ssl_id);
			show('.wizard-auth-basic');
			jQuery(postman_encryption_group).show();
			hide('.port-explanation-ssl');
			jQuery('.port-explanation-tls').show();
		} else {
			// allow none as an authentication choice
			enable(postman_auth_option_none_id);

			// set authentication and encryption types
			setAuthType(postman_auth_none);
			setEncryptionType(postman_enc_none);

			show('.wizard-auth-basic');
			hide(postman_encryption_group);
			disable(postman_input_basic_username);
			disable(postman_input_basic_password);
		}
	}

	return true;
}
function populateRedirectUrl(hostname) {
	getRedirectUrl(hostname, postman_redirect_url_el, '#wizard_oauth2_help');
}
function setAuthType($authType) {
	jQuery(postman_input_auth_type).val($authType);
}
function setEncryptionType($encType) {
	jQuery(postman_enc_for_password_el).val($encType);
	jQuery(postman_enc_for_oauth2_el).val($encType);
}
function enablePasswordFields() {
	enable(postman_input_basic_username);
	enable(postman_input_basic_password);
}
function postHandleStepChange(event, currentIndex, priorIndex, myself) {
	var chosenPort = jQuery('#input_port').val();
	// Suppress (skip) "Warning" step if
	// the user is old enough and wants
	// to the previous step.
	if (currentIndex === 2) {
		if (portCheckBlocksUi) {
			jQuery('li + li').addClass('disabled');
		}
	}
	if (currentIndex === 3 && priorIndex === 4 && chosenPort == 25) {
		myself.steps("previous");
		return;
	}
	if (currentIndex === 3 && chosenPort == 25) {
		myself.steps("next");
	}

}
function checkEmail(email) {
	var data = {
		'action' : 'check_email',
		'email' : email
	// We pass php values differently!
	};
	// We can also pass the url value separately from ajaxurl for front end AJAX
	// implementations
	jQuery.post(ajaxurl, data, function(response) {
		if (response.hostname != '') {
			jQuery(postman_hostname_element_name).val(response.hostname);
		}
	});
}
function wizardPortTest(input, state) {
	var hostname = jQuery(postman_hostname_element_name).val();
	var el = jQuery(input);
	var elState = jQuery(state);
	var portInput = jQuery(postman_port_element_name);
	elState.html('Checking..');
	el.attr('disabled', 'disabled');
	el.prop('checked', false);
	el.click(function() {
		jQuery(postman_port_element_name).val(el.val());
	});
	portsToCheck++;
	var data = {
		'action' : 'test_port',
		'timeout' : postman_port_check_timeout,
		'hostname' : hostname,
		'port' : el.val()
	// We pass php values differently!
	};
	// We can also pass the url value separately from ajaxurl for front end AJAX
	// implementations
	jQuery
			.post(
					ajaxurl,
					data,
					function(response) {
						portsChecked++;
						if (response.success) {
							elState.html('Ok');
							el.removeAttr('disabled');
						} else {
							elState.html('Closed');
						}
						if (portsChecked >= portsToCheck) {
							var el25 = jQuery('#wizard_port_25');
							var el465 = jQuery('#wizard_port_465');
							var el587 = jQuery('#wizard_port_587');
							var el25_avail = el25.attr('disabled') != 'disabled';
							var el465_avail = el465.attr('disabled') != 'disabled';
							var el587_avail = el587.attr('disabled') != 'disabled';
							var totalAvail = 0;
							if (el25_avail)
								totalAvail++;
							if (el465_avail)
								totalAvail++;
							if (el587_avail)
								totalAvail++;
							if (hostname == 'smtp.gmail.com' && el465_avail) {
								// select OAuth2 if the user can use it
								el25.attr('disabled', 'disabled');
								el465.prop("checked", true);
								el587.attr('disabled', 'disabled');
								portInput.val(465);
							} else if (hostname == 'smtp.live.com'
									&& el587_avail) {
								// select OAuth2 if the user can use it
								el25.attr('disabled', 'disabled');
								el465.attr('disabled', 'disabled');
								el587.attr("checked", true);
								portInput.val(587);
							} else if (el465_avail) {
								el465.prop("checked", true);
								portInput.val(465);
							} else if (el587_avail) {
								el587.attr("checked", true);
								portInput.val(587);
							} else if (el25_avail) {
								el25.attr("checked", true);
								portInput.val(25);
							}
							if (totalAvail == 0) {
								alert("No ports are available for this SMTP server. Try a different SMTP host or contact your WordPress host for their specific solution.")
							} else {
								jQuery('li + li').removeClass('disabled');
								portCheckBlocksUi = false;
							}

						}
					});
}
