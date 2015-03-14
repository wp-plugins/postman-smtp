jQuery(document)
		.ready(
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
										startIndex : parseInt(postman_setup_wizard.start_page),
										labels : {
											current : steps_current_step,
											pagination : steps_pagination,
											finish : steps_finish,
											next : steps_next,
											previous : steps_previous,
											loading : steps_loading
										},
										onStepChanging : function(event,
												currentIndex, newIndex) {
											return handleStepChange(event,
													currentIndex, newIndex,
													jQuery(this));

										},
										onInit : function() {
											jQuery(postman_input_sender_email)
													.focus();
										},
										onStepChanged : function(event,
												currentIndex, priorIndex) {
											return postHandleStepChange(event,
													currentIndex, priorIndex,
													jQuery(this));
										},
										onFinishing : function(event,
												currentIndex) {
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
										onFinished : function(event,
												currentIndex) {
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
					// add an event on the plugin selection
					jQuery('input[name="input_plugin"]').click(function() {
						getConfiguration();
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

	if (currentIndex === 1) {
		// page 1 : look-up the email
		// address for the smtp server
		checkEmail(jQuery(postman_input_sender_email).val());
	} else if (currentIndex === 2) {
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

	} else if (currentIndex === 3) {

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
		jQuery('input:radio[name="postman_options[auth_type]"]').click(
				function() {
					handleEncryptionTypeInputClick();
				});

	}

	return true;
}
function handleEncryptionTypeInputClick() {
	var $val = jQuery('input:radio[name="postman_options[auth_type]"]:checked')
			.val();
	if ($val == 'none') {
		disable(postman_input_basic_username);
		disable(postman_input_basic_password);
		hide('.input_encryption_type');
		jQuery('#input_enc_none').prop('checked', true);
	} else {
		enable(postman_input_basic_username);
		enable(postman_input_basic_password);
		show('.input_encryption_type');
		jQuery('#input_enc_tls').prop('checked', true);
	}
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
function postHandleStepChange(event, currentIndex, priorIndex, myself) {
	var chosenPort = jQuery('#input_port').val();
	// Suppress (skip) "Warning" step if
	// the user is old enough and wants
	// to the previous step.
	if (currentIndex === 2) {
		jQuery(postman_hostname_element_name).focus();
	}
	if (currentIndex === 3) {
		if (portCheckBlocksUi) {
			// this is the second place i disable the next button but Steps
			// re-enables it after the screen slides
			jQuery('li + li').addClass('disabled');
		}
	}
	if (currentIndex === 4 && priorIndex === 5 && chosenPort == 25) {
		myself.steps("previous");
		return;
	}
	if (currentIndex === 4 && chosenPort == 25) {
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
		jQuery('#wizard_recommendation').html('');
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
		// create an eventhandler for when the user changes the port
		jQuery('input[name="wizard-port"]').click(function() {
			var portCheck = {};
			portSelection = jQuery('input[name="wizard-port"]:checked');
			var host = JSON.parse(portSelection.val());
			host.available = true;
			host.port_id = portSelection.attr('id');
			portCheck[0] = host;
			var data = {
				'action' : 'get_wizard_configuration_options',
				'user_override' : true,
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
	jQuery.post(ajaxurl, data, function(response) {
		handleWizardPortTestResponse(el, elState, response, hostname);
	}).fail(
		function() {
			portsChecked++;
			elState.html(postman_port_test_closed);
			afterPortsChecked();
	});
}
function handleWizardPortTestResponse(el, elState, response, hostname) {
	portsChecked++;
	if (response.success) {
		elState.html(postman_port_test_open);
		el.removeAttr('disabled');
		totalAvail++;
	} else {
		elState.html(postman_port_test_closed);
	}
	afterPortsChecked();
}

function afterPortsChecked() {
	if (portsChecked >= portsToCheck) {
		if (totalAvail != 0) {
			jQuery('li + li').removeClass('disabled');
			portCheckBlocksUi = false;
		}
		var rows = jQuery('table#wizard_port_test tr');
		var portCheck = {};
		rows.each(function(index) {
			portSelection = jQuery('input', this);
			var host = JSON.parse(portSelection.val());
			host.available = portSelection.attr('disabled') != 'disabled';
			host.port_id = portSelection.attr('id');
			portCheck[index] = host;
		});

		var data = {
			'action' : 'get_wizard_configuration_options',
			'host_data' : portCheck
		};
		populateRedirectUrl(data);
	}
}

/**
 * Handles population of the configuration based on the options set in a
 * 3rd-party SMTP plugin
 */
function getConfiguration() {
	var plugin = jQuery('input[name="input_plugin"]' + ':checked').val();
	if (plugin != '') {
		var data = {
			'action' : 'import_configuration',
			'plugin' : plugin
		};
		jQuery.post(ajaxurl, data, function(response) {
			if (response.success) {
				jQuery('select#input_transport_type').val('smtp');
				jQuery(postman_input_sender_email).val(response.sender_email);
				jQuery(postman_input_sender_name).val(response.sender_name);
				jQuery(postman_hostname_element_name).val(response.hostname);
				jQuery(postman_port_element_name).val(response.port);
				jQuery(postman_input_auth_type).val(response.auth_type);
				jQuery('#input_enc_type').val(response.enc_type);
				jQuery(postman_input_basic_username).val(
						response.basic_auth_username);
				jQuery(postman_input_basic_password).val(
						response.basic_auth_password);
				switchBetweenPasswordAndOAuth();
			}
		});
	} else {
		jQuery(postman_input_sender_email).val('');
		jQuery(postman_input_sender_name).val('');
		jQuery(postman_input_basic_username).val('');
		jQuery(postman_input_basic_password).val('');
		jQuery(postman_hostname_element_name).val('');
		jQuery(postman_port_element_name).val('');
		jQuery(postman_input_auth_type).val('none');
		jQuery(postman_enc_for_password_el).val('none');
		switchBetweenPasswordAndOAuth();
	}
}
