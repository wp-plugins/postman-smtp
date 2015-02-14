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
								onInit : function() {
									jQuery(postman_input_sender_email).focus();
								},
								onStepChanged : function(event, currentIndex,
										priorIndex) {
									return postHandleStepChange(event,
											currentIndex, priorIndex,
											jQuery(this));
								},
								onFinishing : function(event, currentIndex) {
									var form = jQuery(this);

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
	if (currentIndex != 2) {
		valid = form.valid();
		if (!valid) {
			return false;
		}
	}

	if (currentIndex === 0) {
		// page 1 : look-up the email
		// address for the smtp server
		checkEmail(jQuery(postman_input_sender_email).val());
	} else if (currentIndex === 1) {
		// page 2 : check the port
		portsChecked = 0;
		portsToCheck = 0;
		totalAvail = 0;
		// allow the user to choose any
		// port
		portCheckBlocksUi = true;
		// this should be the only place i disable the next button but Steps
		// enables it after the screen slides
		jQuery('li + li').addClass('disabled');
		wizardPortTest(jQuery('#wizard_port_465'),
				jQuery('#wizard_port_465_status'));
		wizardPortTest(jQuery('#wizard_port_25'),
				jQuery('#wizard_port_25_status'));
		wizardPortTest(jQuery('#wizard_port_587'),
				jQuery('#wizard_port_587_status'));
	} else if (currentIndex === 2) {

		// user has clicked next but we haen't finished the check
		if (portsChecked < portsToCheck) {
			alert('Please wait for the check to finish');
			return false;
		}
		// or all ports are unavailable
		if (portCheckBlocksUi) {
			return false;
		}
		valid = form.valid();
		if (!valid) {
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
		if (hostname == 'smtp.gmail.com' && chosenPort == 465) {
			// setup Gmail with OAuth2
		} else if (hostname == 'smtp.live.com' && chosenPort == 587) {
			// setup Hotmail with OAuth2
		} else if (chosenPort == 465) {
			// eanble user/pass fields
			enablePasswordFields();

			// allow ssl, set encryption to ssl
			enable(postman_enc_option_ssl_id);
			setEncryptionType(postman_enc_ssl);

			// hide the encryption menu
			hide(postman_encryption_group);
		} else if (chosenPort == 587) {
			// eanble user/pass fields
			enablePasswordFields();

			// disallow ssl, set encryption to tls
			setEncryptionType(postman_enc_tls);
			disable(postman_enc_option_ssl_id);

			// show the encryption menu
			show(postman_encryption_group);

			// allow none as an authentication choice
			enable(postman_auth_option_none_id);

		} else {
			// allow none as an authentication choice
			enable(postman_auth_option_none_id);

			// set authentication and encryption types
			setAuthType(postman_auth_none);
			setEncryptionType(postman_enc_none);

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
	setAuthType(postman_auth_login);
	enable(postman_input_basic_username);
	enable(postman_input_basic_password);
	show('.wizard-auth-basic');
}
function postHandleStepChange(event, currentIndex, priorIndex, myself) {
	var chosenPort = jQuery('#input_port').val();
	// Suppress (skip) "Warning" step if
	// the user is old enough and wants
	// to the previous step.
	if (currentIndex === 1) {
		jQuery(postman_hostname_element_name).focus();
	}
	if (currentIndex === 2) {
		if (portCheckBlocksUi) {
			// this is the second place i disable the next button but Steps
			// re-enables it after the screen slides
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
							totalAvail++;
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
							// ask the server what to do: oauth and on which
							// port, or password and on which port
							if (totalAvail == 0) {
								alert("No ports are available for this SMTP server. Try a different SMTP host or contact your WordPress host for their specific solution.")
							} else {
								var data = {
									'action' : 'get_redirect_url',
									'hostname' : hostname,
									'avail25' : el25_avail,
									'avail465' : el465_avail,
									'avail587' : el587_avail
								};
								hide('.wizard-auth-oauth2');
								hide('.wizard-auth-basic');
								disable(postman_auth_option_oauth2_id);
								disable(postman_auth_option_none_id);
								populateRedirectUrl(data);
								jQuery('li + li').removeClass('disabled');
								portCheckBlocksUi = false;
							}

						}
					});
}
