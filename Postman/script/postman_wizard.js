portsChecked = 0;
portsToCheck = 0;
function hide($el) {
	$el.hide();
}
function show($el) {
	$el.show();
}
jQuery(document)
		.ready(
				function() {
					jQuery("#postman_wizard")
							.steps(
									{
										bodyTag : "fieldset",
										headerTag : "h1",
										transitionEffect : "slideLeft",
										stepsOrientation : "vertical",
										autoFocus : true,
										onStepChanging : function(event,
												currentIndex, newIndex) {
											// Always allow going backward even
											// if the current step contains
											// invalid
											// fields!
											if (currentIndex > newIndex) {
												return true;
											}

											// Forbid suppressing "Warning" step
											// if the user is to young
											if (newIndex === 3
													&& Number(jQuery("#age")
															.val()) < 18) {
												return false;
											}

											var form = jQuery(this);

											// Clean up if user went backward
											// before
											if (currentIndex < newIndex) {
												// To remove error styles
												jQuery(
														".body:eq("
																+ newIndex
																+ ") label.error",
														form).remove();
												jQuery(
														".body:eq(" + newIndex
																+ ") .error",
														form).removeClass(
														"error");
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
												checkEmail(jQuery(
														postman_input_sender_email)
														.val());
											} else if (currentIndex === 1) {
												// page 2 : check the port
												portsChecked = 0;
												portsToCheck = 0;
												// allow the user to choose any
												// port
												enable(jQuery('#input_auth_type_oauth2'));
												enable(jQuery('#input_auth_type_ssl'));
												enable(jQuery('#input_auth_type_tls'));
												enable(jQuery('#input_auth_type_none'));
												wizardPortTest(
														jQuery('#wizard_port_465'),
														jQuery('#wizard_port_465_status'));
												wizardPortTest(
														jQuery('#wizard_port_25'),
														jQuery('#wizard_port_25_status'));
												wizardPortTest(
														jQuery('#wizard_port_587'),
														jQuery('#wizard_port_587_status'));
											} else if (currentIndex === 2) {
												if (portsChecked < portsToCheck) {
													alert('Please wait for the check to finish');
													return false;
												}
												var chosenPort = jQuery(
														'#input_port').val();
												var hostname = jQuery(
														postman_hostname_element_name)
														.val();
												// on the Auth type drop-down,
												// add events to enable/disable
												// user/pass
												jQuery(postman_input_auth_type)
														.click(
																function() {
																	var $val = jQuery(
																			postman_input_auth_type)
																			.val();
																	var $userEl = jQuery(postman_input_basic_username);
																	var $pwEl = jQuery(postman_input_basic_password);
																	if ($val == 'none') {
																		disable($userEl);
																		disable($pwEl);
																	} else {
																		enable($userEl);
																		enable($pwEl);
																	}
																});
												hide(jQuery('.wizard-auth-oauth2'));
												hide(jQuery('.wizard-auth-basic'));
												enable(jQuery(postman_input_basic_username));
												enable(jQuery(postman_input_basic_password));
												disable(jQuery('#input_auth_type_oauth2'));
												if (hostname == 'smtp.gmail.com'
														&& chosenPort == 465
														|| hostname == 'smtp.live.com'
														&& chosenPort == 587) {
													show(jQuery('.wizard-auth-oauth2'));
													jQuery(
															postman_input_auth_type)
															.val('oauth2');
													hide(jQuery('.input_authorization_type'));
													enable(jQuery('#input_auth_type_oauth2'));
												} else if (chosenPort == 465) {
													show(jQuery('.wizard-auth-basic'));
													jQuery(
															postman_input_auth_type)
															.val('basic-ssl');
													jQuery(
															'.input_authorization_type')
															.hide();
													jQuery(
															'.port-explanation-ssl')
															.show();
													jQuery(
															'.port-explanation-tls')
															.hide();
												} else if (chosenPort == 587) {
													show(jQuery('.wizard-auth-basic'));
													jQuery(
															postman_input_auth_type)
															.val('basic-tls');
													jQuery(
															'#input_auth_type_ssl')
															.attr('disabled',
																	'disabled');
													jQuery(
															'.input_authorization_type')
															.show();
													jQuery(
															'.port-explanation-ssl')
															.hide();
													jQuery(
															'.port-explanation-tls')
															.show();
												} else {
													show(jQuery('.wizard-auth-basic'));
													jQuery(
															postman_input_auth_type)
															.val('none');
													jQuery(
															'.input_authorization_type')
															.hide();
													jQuery(
															postman_input_basic_username)
															.attr('disabled',
																	'disabled');
													jQuery(
															postman_input_basic_password)
															.attr('disabled',
																	'disabled');
												}
											}

											return true;

										},
										onStepChanged : function(event,
												currentIndex, priorIndex) {
											var chosenPort = jQuery(
													'#input_port').val();
											// Suppress (skip) "Warning" step if
											// the user is old enough and wants
											// to the previous step.
											if (currentIndex === 3
													&& priorIndex === 4
													&& chosenPort == 25) {
												jQuery(this).steps("previous");
												return;
											}
											if (currentIndex === 3
													&& chosenPort == 25) {
												jQuery(this).steps("next");
											}
										},
										onFinishing : function(event,
												currentIndex) {
											var form = jQuery(this);

											jQuery('.wizard-auth-oauth2')
													.show();
											jQuery('.wizard-auth-basic').show();
											jQuery('.input_authorization_type')
													.show();
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
				});
function checkEmail(email) {
	var data = {
		'action' : 'check_email',
		'email' : email
	// We pass php values differently!
	};
	// We can also pass the url value separately from ajaxurl for front end AJAX
	// implementations
	jQuery.post(ajaxurl, data, function(response) {
		jQuery(postman_hostname_element_name).val(response.hostname);
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
		'timeout' : '5',
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
							} else {
								if (totalAvail == 0) {
									alert("No ports are available for this SMTP server. Try a different SMTP host or contact your WordPress host for their specific solution.")
								}
							}
						}
					});
}
