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
	// add an event on the user port override field
	jQuery('select#user_socket_override').change(function() {
		userOverrideMenu();
	});
	// add an event on the user port override field
	jQuery('select#user_auth_override').change(function() {
		userOverrideMenu();
	});

	// add an event on the transport input field
	// when the user changes the transport, determine whether
	// to show or hide the SMTP Settings
	jQuery('select#input_transport_type').change(function() {
		hide('#wizard_oauth2_help');
		reloadOauthSection();
		switchBetweenPasswordAndOAuth();
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
	var chosenPort = jQuery('#input_auth_type').val();
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
			jQuery('li').addClass('disabled');
		}
	}
	if (currentIndex === 4) {
		if (redirectUrlWarning) {
			alert(postman_wizard_bad_redirect_url);
		}
	}
	if (currentIndex === 4 && priorIndex === 5 && chosenPort == 'none') {
		myself.steps("previous");
		return;
	}
	if (currentIndex === 4 && chosenPort == 'none') {
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
		handleHostsToCheckResponse(response);
	});
}

/**
 * Handles the response from the server of the list of sockets to check.
 * 
 * @param hostname
 * @param response
 */
function handleHostsToCheckResponse(response) {
	jQuery('table#wizard_port_test').html('');
	jQuery('#wizard_recommendation').html('');
	hide('#user_override');
	connectivtyTestResults = {};
	for ( var x in response.hosts) {
		var hostname = response.hosts[x].host;
		var port = response.hosts[x].port
		portsToCheck++;
		updateStatus(postman_test_in_progress + portsToCheck);
		var data = {
			'action' : 'wizard_port_test',
			'hostname' : hostname,
			'port' : port
		};
		postThePortTest(hostname, port, data);
	}
}

/**
 * Asks the server to run a connectivity test on the given port
 * 
 * @param hostname
 * @param port
 * @param data
 */
function postThePortTest(hostname, port, data) {
	jQuery.post(ajaxurl, data, function(response) {
		handlePortTestResponse(hostname, port, data, response);
	}).fail(function() {
		portsChecked++;
		afterPortsChecked();
	});
}

/**
 * Handles the result of the port test
 * 
 * @param hostname
 * @param port
 * @param data
 * @param response
 */
function handlePortTestResponse(hostname, port, data, response) {
	if (!response.data.try_smtps) {
		portsChecked++;
		updateStatus(postman_test_in_progress + (portsToCheck - portsChecked));
		connectivtyTestResults[hostname + '_' + port] = response.data;
		if (response.success) {
			// a totalAvail > 0 is our signal to go to the next step
			totalAvail++;
		}
		afterPortsChecked();
	} else {
		// SMTP failed, try again on the SMTPS port
		data['action'] = 'wizard_port_test_smtps';
		postThePortTest(hostname, port, data);
	}
}

/**
 * 
 * @param message
 */
function updateStatus(message) {
	jQuery('#port_test_status').html(
			'<span style="color:blue">' + message + '</span>');
}

/**
 * This functions runs after ALL the ports have been checked. It's chief
 * function is to push the results of the port test back to the server to get a
 * suggested configuration.
 */
function afterPortsChecked() {
	if (portsChecked >= portsToCheck) {
		if (totalAvail != 0) {
			jQuery('li').removeClass('disabled');
			portCheckBlocksUi = false;
		}
		var data = {
			'action' : 'get_wizard_configuration_options',
			'host_data' : connectivtyTestResults
		};
		postTheConfigurationRequest(data);
		updateStatus(postman_test_in_progress + postman_port_test_done);
	}
}

function userOverrideMenu() {
	disable('select#user_socket_override');
	disable('select#user_auth_override');
	var data = {
		'action' : 'get_wizard_configuration_options',
		'user_port_override' : jQuery('select#user_socket_override').val(),
		'user_auth_override' : jQuery('select#user_auth_override').val(),
		'host_data' : connectivtyTestResults
	};
	postTheConfigurationRequest(data);
}

function postTheConfigurationRequest(data) {
	jQuery.post(ajaxurl, data, function(response) {
		var $message = '';
		if (response.success) {
			$message = '<span style="color:green">'
					+ response.data.configuration.message + '</span>';
			handleConfigurationResponse(response.data);
			enable('select#user_socket_override');
			enable('select#user_auth_override');
			// enable both next/back buttons
			jQuery('li').removeClass('disabled');
		} else {
			$message = '<span style="color:red">'
					+ response.data.configuration.message + '</span>';
			// enable the back button only
			jQuery('li').removeClass('disabled');
			jQuery('li + li').addClass('disabled');
		}
		if(!response.data.configuration.user_override) {
			jQuery('#wizard_recommendation').append($message);
		}
	});
}
function handleConfigurationResponse(response) {
	if (response.configuration.display_auth == 'oauth2') {
		show('p#wizard_oauth2_help');
		jQuery('p#wizard_oauth2_help').html(response.configuration.help_text);
		jQuery(postman_redirect_url_el)
				.val(response.configuration.redirect_url);
		jQuery('#input_oauth_callback_domain').val(
				response.configuration.callback_domain);
		jQuery('#client_id').html(response.client_id_label);
		jQuery('#client_secret').html(response.client_secret_label);
		jQuery('#redirect_url').html(response.redirect_url_label);
		jQuery('#callback_domain').html(response.callback_domain_label);
	}
	redirectUrlWarning = response.configuration.dotNotationUrl;
	jQuery('#input_transport_type').val(response.configuration.transport_type);
	jQuery('#input_auth_type').val(response.configuration.auth_type);
	jQuery('#input_enc_type').val(response.configuration.enc_type);
	jQuery('#input_port').val(response.configuration.port);

	// populate user Port Override menu
	var el1 = jQuery('#user_socket_override');
	el1.html('');
	for (i = 0; i < response.override_menu.length; i++) {
		el1.append(jQuery('<option/>', {
			selected : response.override_menu[i].selected,
			value : response.override_menu[i].value,
			text : response.override_menu[i].description
		}));
		// populate user Auth Override menu
		if (response.override_menu[i].selected) {
			var el2 = jQuery('#user_auth_override');
			el2.html('');
			for (j = 0; j < response.override_menu[i].auth_items.length; j++) {
				var x = jQuery(
						'<option/>',
						{
							selected : response.override_menu[i].auth_items[j].selected,
							value : response.override_menu[i].auth_items[j].value,
							text : response.override_menu[i].auth_items[j].name
						});
				el2.append(x);
			}
		}

	}

	show('#user_override');
	// hide the fields we don't use so validation
	// will work
	if (response.configuration.display_auth == 'oauth2') {
		// where authentication is oauth2
		show('.wizard-auth-oauth2');
		hide('.wizard-auth-basic');
	} else if (response.configuration.display_auth == 'password') {
		// where authentication is password
		hide('.wizard-auth-oauth2');
		show('.wizard-auth-basic');
	} else {
		// where authentication is none
		hide('.wizard-auth-oauth2');
		hide('.wizard-auth-basic');
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
