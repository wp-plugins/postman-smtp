		jQuery(document).ready(function(){
			showHide();
			var $el = jQuery(postman_auth_element_name);
			$el.change(function(){
	    	   showHide();
	    	});
	    });
        function showHide() {
			var $el = jQuery(postman_auth_element_name);
        $choice = $el.val();
			var $div1 = jQuery(postman_smtp_section_element_name);
			var $div2 = jQuery(postman_oauth_section_element_name);
			if($choice == postman_auth_none) {
        	   $div1.hide();
        	   $div2.hide();
			} else if($choice == postman_auth_basic) {
	        	   $div1.show();
	        	   $div2.hide();
			} else {
		     	   $div1.hide();
	        	   $div2.show();
			}		
        }
