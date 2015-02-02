portsChecked = 0;
portsToCheck = 0;
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

											// look-up the email address for the
											// smtp server
											if (currentIndex === 0) {
												checkEmail(jQuery(
														postman_input_sender_email)
														.val());
											} else if (currentIndex === 1) {
												portsChecked = 0;
												portsToCheck = 0;
												// allow the user to choose any
												// port
												jQuery(
														'#input_auth_type_oauth2')
														.removeAttr('disabled');
												jQuery('#input_auth_type_ssl')
														.removeAttr('disabled');
												jQuery('#input_auth_type_tls')
														.removeAttr('disabled');
												jQuery('#input_auth_type_none')
														.removeAttr('disabled');
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
												jQuery(
														'#input_authorization_type')
														.click(
																function() {
																	var $val = jQuery(
																			'#input_authorization_type')
																			.val();
																	if ($val == 'none') {
																		jQuery(
																				'#input_basic_auth_username')
																				.attr(
																						'disabled',
																						'disabled');
																		jQuery(
																				'#input_basic_auth_password')
																				.attr(
																						'disabled',
																						'disabled');
																	} else {
																		jQuery(
																				'#input_basic_auth_username')
																				.removeAttr(
																						'disabled');
																		jQuery(
																				'#input_basic_auth_password')
																				.removeAttr(
																						'disabled');
																	}
																});
												jQuery('.wizard-auth-oauth2')
														.hide();
												jQuery('.wizard-auth-basic')
														.show();
												jQuery(
														'#input_basic_auth_username')
														.removeAttr('disabled');
												jQuery(
														'#input_basic_auth_password')
														.removeAttr('disabled');
												jQuery(
														'#input_auth_type_oauth2')
														.attr('disabled',
																'disabled');
												if (hostname == 'smtp.gmail.com'
														&& chosenPort == 465) {
													jQuery(
															'.wizard-auth-oauth2')
															.show();
													jQuery('.wizard-auth-basic')
															.hide();
													jQuery(
															input_authorization_type)
															.val('oauth2');
													jQuery(
															'.input_authorization_type')
															.hide();
													jQuery(
															'#input_auth_type_oauth2')
															.removeAttr(
																	'disabled');
												} else if (chosenPort == 465) {
													jQuery(
															input_authorization_type)
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
													jQuery(
															input_authorization_type)
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
													jQuery(
															input_authorization_type)
															.val('none');
													jQuery(
															'.input_authorization_type')
															.hide();
													jQuery(
															'#input_basic_auth_username')
															.attr('disabled',
																	'disabled');
													jQuery(
															'#input_basic_auth_password')
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
							} else if (totalAvail == 1) {
								var enable = true;
								if (el25_avail) {
									el25.prop('checked', enable);
									portInput.val(25);
								}
								if (el465_avail) {
									el465.prop('checked', enable);
									portInput.val(465);
								}
								if (el587_avail) {
									el587.prop('checked', enable);
									portInput.val(587);
								}
							} else {
								if (totalAvail == 0) {
									alert("No ports are available for this SMTP server. Try a different SMTP host or contact your WordPress host for their specific solution.")
								}
							}
						}
					});
}
