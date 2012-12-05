/**
 * Edit Post Javascript
 *
 * @author Tareq Hasan 
 * @package WP User Frontend
 * @version 1.1-fork-2RRR-3.0
 * @since 1.1-fork-2RRR-3.0  
 */
 
/*
== Changelog ==

= 1.1-fork-2RRR-3.0 professor99 =
* Was wpuf_edit_post_javascript() in wpuf_edit_post.php
* Escaped info message XML tags
*/

//update button
//-------------

//Uses jquery.form

//Note if a file upload is included this uses an iframe instead of Ajax for the current Wordpress 3.4.2 version
//which uses jquery.form version 2.73. Versions of jquery.form 2.90 and later can use html5 ajax to do this.
//For iframe uploads timeout and error functions wont fire.

jQuery(document).ready(function() {
	var options = { 
		datatype:	'xml',
		beforeSubmit: wpuf_edit_post_before_submit,
		success:	wpuf_edit_post_success,
		error:		wpuf_edit_post_error,
		timeout:	3000, 
		url:		wpuf.ajaxurl,
		data:		{ action: 'wpuf_edit_post_action' }
	};

	// bind form using 'ajaxForm' 
	jQuery('#wpuf_edit_post_form').ajaxForm(options);
});

function wpuf_edit_post_before_submit(formData, jqForm, options) { 
	jQuery('#wpuf-info-msg').html('&nbsp;');
}

function wpuf_edit_post_success(responseXML) { 
	success = $('success', responseXML).text();
	message = $('message', responseXML).text();
	post_id = $('post_id', responseXML).text();
	redirect_url = $('redirect_url', responseXML).text();
	//alert('success=' + success + '\nmessage=' + message + '\npost_id=' + post_id + '\nredirect_url=' + redirect_url);
	
	jQuery('#wpuf-info-msg').html(message);
	jQuery('#wpuf-info-msg').fadeTo(0,1);

	if (success == "true") {
		if (redirect_url != "") {
			setTimeout(function() {window.location.replace(redirect_url), 3000});
		} else {
			jQuery('#wpuf-info-msg').fadeTo(4000,0);
			
			jQuery('#wpuf_edit_post_form .wpuf-submit').attr({
				'value': wpuf.update_msg,
				'disabled': false
			});	
		}
	}
	else {
		jQuery('#wpuf_edit_post_form .wpuf-submit').attr({
			'value': wpuf.update_msg,
			'disabled': false
		});	
	}
}

function wpuf_edit_post_error(XMLHttpRequest, textStatus, errorThrown) {
	//triggered on ajax errors including timeout.
	jQuery("#wpuf-info-msg").html = '<div class="wpuf-error">Error: ' + textStatus + '</div>';
}

//delete button
//-------------

function wpuf_delete_post (postID, redirect, close, referer, self) {
	if( confirm(wpuf.delete_confirm_msg) ) {

		$.ajax({
			type: 'post',
			datatype:	'xml',
			success: wpuf_delete_post_success,
			error: wpuf_delete_post_error,
			timeout:	3000, 
			url: wpuf.ajaxurl,
			data: {
				action: 'wpuf_delete_post_action',
				post_id: postID,
				wpuf_redirect: redirect,
				wpuf_close: close,
				wpuf_referer: referer,
				wpuf_self: self,
				nonce: wpuf.nonce
			}
		});
	}
}	

function wpuf_delete_post_success(responseXML) { 
	success = $('success', responseXML).text();
	message = $('message', responseXML).text();
	post_id = $('post_id', responseXML).text();
	redirect_url = $('redirect_url', responseXML).text();
	//alert('success=' + success + '\nmessage=' + message + '\npost_id=' + post_id + '\nredirect_url=' + redirect_url);
	
	jQuery('#wpuf-info-msg').html(message);
	jQuery('#wpuf-info-msg').fadeTo(0,1);

	if (success == "true") {
		if (redirect_url != "") {
			setTimeout(function() {window.location.replace(redirect_url), 3000});
		} else {
			jQuery('#wpuf-info-msg').fadeTo(4000,0);
		}
	}
}

function wpuf_delete_post_error(XMLHttpRequest, textStatus, errorThrown) {
	//triggered on ajax errors including timeout.
	jQuery("#wpuf-info-msg").html = '<div class="wpuf-error">Error: ' + textStatus + '</div>';
}
