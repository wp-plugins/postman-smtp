connectivtyTestResults = {};
/**
 * Functions to run on document load
 */
jQuery(document).ready(function() {
	jQuery(postman_input_sender_email).focus();
	initializeJQuerySteps();
	// add an event on the plugin selection
	jQuery('input[name="input_plugin"]').click(function() {
		getConfiguration();
	});
});

/**
 * Initialize the Steps wizard
 */
function initializeJQuerySteps() {
	jQuery("#postman_wizard").steps(
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
				onStepChanging : function(event, currentIndex, newIndex) {
					return handleStepChange(event, currentIndex, newIndex,
							jQuery(this));

				},
				onInit : function() {
					jQuery(postman_input_sender_email).focus();
				},
				onStepChanged : function(event, currentIndex, priorIndex) {
					return postHandleStepChange(event, currentIndex,
							priorIndex, jQuery(this));
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
}

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

		// user has clicked next but we haven't finished the check
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

	}

	return true;
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
	if (currentIndex === 4) {
		if (redirectUrlWarning) {
			alert(postman_wizard_bad_redirect_url);
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

/**
 * Asks the server for a List of sockets to perform port checks upon.
 * 
 * @param hostname
 */
function getHostsToCheck(hostname) {
	var data = {
		'action' : 'get_hosts_to_test',
		'hostname' : hostname
	};
	jQuery.post(ajaxurl, data, function(response) {
		jQuery('table#wizard_port_test').html('');
		jQuery('#wizard_recommendation').html('');
		connectivtyTestResults = {};
		for ( var x in response.hosts) {
			var html = '';
			var host = response.hosts[x].host;
			var port = response.hosts[x].port
			var value = JSON.stringify(response.hosts[x]);
			var id = 'port-' + x;
			var id_status = id + '_status';
			html += '<tr><td><span>' + host + ':' + port + "</span></td>";
			html += '<td id="' + id_status + '"></td></tr>';
			jQuery('table#wizard_port_test').append(html);
			// PERFORM THE ACTUAL PORT TEST
			wizardPortTest(host, port, 'input#' + id, '#' + id_status);
		}
		// create an eventhandler for when the user changes the port
		/*
		jQuery('input[name="wizard-port"]').click(function() {
			var portCheck = {};
			portSelection = jQuery('input[name="wizard-port"]:checked');
			var host = JSON.parse(portSelection.val());
			host.available = true;
			host.port_id = portSelection.attr('id');
			portCheck[0] = host;
			var data = {
				'action' : 'get_wizard_configuration_options',
				'host_data' : portCheck
			};
			handleConfigInstructions(data);
		});
		*/
	});
}
/**
 * Called for each socket to be tested. An Ajax post performs the test, the
 * response is
 * 
 * @param hostname
 * @param port
 * @param input
 * @param state
 */
function wizardPortTest(hostname, port, input, state) {
	var el = jQuery(input);
	var elState = jQuery(state);
	var portInput = jQuery(postman_port_element_name);
	elState.html(postman_port_test_testing);
	el.attr('disabled', 'disabled');
	el.prop('checked', true);
	portsToCheck++;
	var data = {
		'action' : 'wizard_port_test',
		'hostname' : hostname,
		'port' : port
	};
	// POST THE AJAX CALL
	jQuery.post(ajaxurl, data, function(response) {
		portsChecked++;
		connectivtyTestResults[port] = response.data;
		if (response.success) {
			elState.html(postman_port_test_done);
			el.removeAttr('disabled');
			totalAvail++;
		} else {
			elState.html(postman_port_test_done);
		}
		afterPortsChecked();
	}).fail(function() {
		portsChecked++;
		elState.html(postman_port_test_done);
		afterPortsChecked();
	});
}

/**
 * This functions runs after ALL the ports have been checked. It's chief
 * function is to push the results of the port test back to the server to get a
 * suggested configuration.
 */
function afterPortsChecked() {
	if (portsChecked >= portsToCheck) {
		if (totalAvail != 0) {
			jQuery('li + li').removeClass('disabled');
			portCheckBlocksUi = false;
		}
		var data = {
			'action' : 'get_wizard_configuration_options',
			'host_data' : connectivtyTestResults
		};
		handleConfigInstructions(data);
	}
}

function handleConfigInstructions(data) {
	jQuery.post(ajaxurl, data, function(response) {
		if (response.success) {
			handleConfigurationResponse(response);
		}
	});
}
function handleConfigurationResponse(response) {
	response = response.data;
	if (response.configuration.display_auth == 'oauth2') {
		show('p#wizard_oauth2_help');
		jQuery('p#wizard_oauth2_help').html(response.configuration.help_text);
		jQuery(postman_redirect_url_el).val(response.configuration.redirect_url);
		jQuery('#input_oauth_callback_domain').val(response.configuration.callback_domain);
		jQuery('#client_id').html(response.client_id_label);
		jQuery('#client_secret').html(response.client_secret_label);
		jQuery('#redirect_url').html(response.redirect_url_label);
		jQuery('#callback_domain').html(response.callback_domain_label);
	}
	redirectUrlWarning = response.configuration.dotNotationUrl;
	jQuery('#input_transport_type').val(response.configuration.transport_type);
	jQuery('#input_auth_type').val(response.configuration.auth_type);
	jQuery('#input_auth_' + response.configuration.auth_type).prop('checked', true);
	jQuery(postman_enc_for_password_el).val(response.configuration.enc_type);
	jQuery('#input_enc_type').val(response.configuration.enc_type);
	jQuery('#input_enc_' + response.configuration.enc_type).prop('checked', true);
	if (response.configuration.port) {
		enable('#input_port');
		jQuery('#input_port').val(response.configuration.port);
	} else {
		disable('#input_port');
	}
	jQuery('#' + response.port_id).prop('checked', true);
	if (!response.user_override) {
		if (response.configuration.transport_type) {
			$message = '<span style="color:green">' + response.configuration.message
					+ '</span>';
		} else {
			$message = '<span style="color:red">' + response.configuration.message
					+ '</span>';
		}
		jQuery('#wizard_recommendation').append($message);
	}
	if (response.hide_auth) {
		hide('.input_auth_type');
	} else {
		show('.input_auth_type');
	}
	if (response.hide_enc) {
		hide('.input_encryption_type');
		enable('#input_enc_ssl');
	} else {
		show('.input_encryption_type');
		disable('#input_enc_ssl');
	}
	// disable the fields we don't use so validation
	// will work
	if (response.configuration.display_auth == 'oauth2') {
		show('.wizard-auth-oauth2');
		hide('.wizard-auth-basic');
		// allow oauth2 as an authentication choice
		enable('#input_auth_oauth2');
	} else if (response.configuration.display_auth == 'password') {
		hide('.wizard-auth-oauth2');
		show('.wizard-auth-basic');
		enable(postman_input_basic_username);
		enable(postman_input_basic_password);
		disable('#input_auth_oauth2');
	} else {
		hide('.wizard-auth-oauth2');
		hide('.wizard-auth-basic');
		enable('#input_auth_oauth2');
	}
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
