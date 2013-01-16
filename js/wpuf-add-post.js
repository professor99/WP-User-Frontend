/**
 * Add Post Javascript
 *
 * @author Tareq Hasan 
 * @package WP User Frontend
 * @version 1.1.0-fork-2RRR-4.2
 * @since 1.1-fork-2RRR-3.0  
 */
 
/*
== Changelog ==

= 1.1.0-fork-2RRR-4.2 professor99 =
* Fixed Jquery $ conflict bug

= 1.1-fork-2RRR-3.0 professor99 =
* Was wpuf_add_post_javascript() in wpuf_add_post.php
* Escaped info message XML tags
*/

//Submit Post button
//-------------------

//Uses jquery.form

//Note if a file upload is included this uses an iframe instead of Ajax for the current Wordpress 3.4.2 version
//which uses jquery.form version 2.73. Versions of jquery.form 2.90 and later can use html5 ajax to do this.
//For iframe uploads timeout and error functions wont fire.

jQuery(document).ready(function() {
	var options = { 
		datatype:	'xml',
		beforeSubmit: wpuf_add_post_before_submit,
		success:	wpuf_add_post_success,
		error:		wpuf_add_post_error,
		timeout:	3000, 
		url:		wpuf.ajaxurl,
		data:		{ action: 'wpuf_add_post_action' }
	}

	// bind form using 'ajaxForm' 
	jQuery('#wpuf_new_post_form').ajaxForm(options);
});

function wpuf_add_post_before_submit(formData, jqForm, options) { 
	jQuery('#wpuf-info-msg').html('&nbsp;');
}

function wpuf_add_post_success(responseXML) { 
	success = jQuery('success', responseXML).text();
	message = jQuery('message', responseXML).text();
	post_id = jQuery('post_id', responseXML).text();
	redirect_url = jQuery('redirect_url', responseXML).text();
	//alert('success=' + success + '\nmessage=' + message + '\npost_id=' + post_id + '\nredirect_url=' + redirect_url);
	
	jQuery('#wpuf-info-msg').html(message);
	jQuery('#wpuf-info-msg').fadeTo(0,1);

	if (success == "true") {
		if (redirect_url != "") {
			setTimeout(function() {window.location.replace(redirect_url), 3000});
		} else {
			jQuery('#wpuf-info-msg').fadeTo(4000,0);
			jQuery('#wpuf_new_post_form').resetForm();
			
			jQuery('#wpuf_new_post_form .wpuf-submit').attr({
				'value': wpuf.submit_msg,
				'disabled': false
			});	
		}
	}
	else {
		jQuery('#wpuf_new_post_form .wpuf-submit').attr({
			'value': wpuf.submit_msg,
			'disabled': false
		});	
	}
}

function wpuf_add_post_error(XMLHttpRequest, textStatus, errorThrown) {
	//triggered on ajax errors including timeout.
	jQuery("#wpuf-info-msg").html = '<div class="wpuf-error">Error: ' + textStatus + '</div>';
}
