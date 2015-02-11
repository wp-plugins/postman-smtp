portsChecked = 0;
portsToCheck = 0;
jQuery(document).ready(
		function() {
			jQuery(postman_input_sender_email).focus();
			jQuery("#postman_test_email_wizard")
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
								},
								onFinished : function(event, currentIndex) {
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
		//
	}

	return true;
}
function postHandleStepChange(event, currentIndex, priorIndex, myself) {
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
		jQuery(postman_hostname_element_name).val(response.hostname);
	});
}
