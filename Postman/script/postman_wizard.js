jQuery(document).ready(
		function() {
			jQuery(postman_input_sender_email).focus();
			jQuery("#postman_wizard")
					.steps(
							{
								bodyTag : "fieldset",
								headerTag : "h5",
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

		getHostsToCheck(jQuery(postman_hostname_element_name).val());

	} else if (currentIndex === 2) {

		// user has clicked next but we haen't finished the check
		if (portsChecked < portsToCheck) {
			alert(postman_wizard_wait);
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
		var authType = jQuery(postman_input_auth_type).val()

		// on the Auth type drop-down, add events to enable/disable user/pass
		jQuery(postman_input_auth_type).click(function() {
			var $val = jQuery(postman_input_auth_type).val();
			if ($val == 'none') {
				disable(postman_input_basic_username);
				disable(postman_input_basic_password);
				disable('select#input_enc_type');
				setEncryptionType(postman_enc_none);
			} else {
				enable(postman_input_basic_username);
				enable(postman_input_basic_password);
				// for the next two lines, i assume this is port 587 because
				// that's currently the only other time a click event can be
				// fired here
				enable('select#input_enc_type');
				setEncryptionType(postman_enc_tls);
			}
		});

		// hide both the oauth section and the password section
		if (authType == postman_auth_oauth2) {
			// in oauth2 mode everything is already set to go
			// in password mode, a lot changes based on the port
			// the user chooses....
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

			disable('.input_auth_type_oauth2');
			// disallow ssl, set encryption to tls
			disable(postman_enc_option_ssl_id);
			hide(postman_enc_option_ssl_id);
			jQuery('input.input_enc_type_tls').prop('checked', true);

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
	jQuery('select#input_enc_type').val($encType);
}
function enablePasswordFields() {
	setAuthType(postman_auth_plain);
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
function getHostsToCheck(hostname) {
	var data = {
		'action' : 'get_hosts_to_test',
		'hostname' : hostname
	};
	jQuery.post(ajaxurl, data, function(response) {
		jQuery('table#wizard_port_test').html('');
		for ( var x in response.hosts) {
			var html = '';
			var host = response.hosts[x].host;
			var port = response.hosts[x].port
			var value = JSON.stringify(response.hosts[x]);
			var id = 'port-' + x;
			var id_status = id + '_status';
			html += '<tr><td><span>' + host + ':' + port + "</span></td>";
			html += '<td><input type="radio" id="' + id
					+ '" name="wizard-port" value=\'' + value
					+ '\' class="required" style="margin-top: 0px" /></td>';
			html += '<td id="' + id_status + '"></td></tr>';
			jQuery('table#wizard_port_test').append(html);
			wizardPortTest(host, port, 'input#' + id, '#' + id_status);
		}
		jQuery('input[name="wizard-port"]').click(function() {
			var portCheck = {};
			portSelection = jQuery('input[name="wizard-port"]:checked');
			var host = JSON.parse(portSelection.val());
			host.available = true;
			host.port_id = portSelection.attr('id');
			portCheck[0] = host;
			var data = {
				'action' : 'get_redirect_url',
				'referer' : 'wizard',
				'user_override' : true,
				'hostname' : hostname,
				'host_data' : portCheck
			};
			populateRedirectUrl(data);
		});
	});
}
function checkEmail(email) {
	var data = {
		'action' : 'check_email',
		'email' : email
	};
	jQuery.post(ajaxurl, data, function(response) {
		if (response.hostname != '') {
			jQuery(postman_hostname_element_name).val(response.hostname);
		}
	});
}
function wizardPortTest(hostname, port, input, state) {
	var el = jQuery(input);
	var elState = jQuery(state);
	var portInput = jQuery(postman_port_element_name);
	elState.html(postman_port_test_testing);
	el.attr('disabled', 'disabled');
	el.prop('checked', true);
	portsToCheck++;
	var data = {
		'action' : 'test_port',
		'hostname' : hostname,
		'port' : port
	};
	jQuery.post(ajaxurl, data,
			function(response) {
				portsChecked++;
				if (response.success) {
					elState.html(postman_port_test_open);
					el.removeAttr('disabled');
					totalAvail++;
				} else {
					elState.html(postman_port_test_closed);
				}
				if (portsChecked >= portsToCheck) {
					if (totalAvail == 0) {
						alert(postman_wizard_no_ports);
					} else {
						var rows = jQuery('table#wizard_port_test tr');
						var portCheck = {};
						rows
								.each(function(index) {
									portSelection = jQuery('input', this);
									var host = JSON.parse(portSelection.val());
									host.available = portSelection
											.attr('disabled') != 'disabled';
									host.port_id = portSelection.attr('id');
									portCheck[index] = host;
								});

						var data = {
							'action' : 'get_redirect_url',
							'referer' : 'wizard',
							'hostname' : hostname,
							'host_data' : portCheck
						};
						populateRedirectUrl(data);
						jQuery('li + li').removeClass('disabled');
						portCheckBlocksUi = false;
					}

				}
			});
}
