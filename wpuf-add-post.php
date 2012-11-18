<?php

/**
 * Add Post form class
 *
 * @author Tareq Hasan 
 * @package WP User Frontend
 * @version 1.1-fork-2RRR-2.0 
 */

/*
== Changelog ==

= 1.1-fork-2RRR-2.0 professor99 =
* Now uses jquery.form to do Ajax style updates.
* Post redirect shortcut option added.
* Better info and error messages.
* Suppress "edit_post_link" on this page
* Added wpuf prefix to some css classes
* Re-styled buttons.
* Re-styled attachment display

= 1.1-fork-2RRR-1.0 professor99 =
* Custom editor option added.
* Editors use max availiable width.
* Close button added as shortcut option and redirects set to suit.
* wpuf_allow_cats filter added.
* Security checks updated.
* Code updated to allow use of wpuf_can_post filter for non logged in users.
*/

/*
 *  Shortcode examples::
 * 
 * 	[wpuf_addpost]
 * 	[wpuf_addpost close="false"]
 * 	[wpuf_addpost close="false" redirect="none"]
 * 
 *  Shortcode options:
 * 
 * 	post_type: post | <otherPostType>
 * 		post: (default)
 * 		<otherPostType>: other post types
 * 	close: true | false 
 * 		true: will display close button and redirect to last page on close (default)
 * 		false: 
 * 	redirect: none | auto | current | new | last 
 * 		none: do nothing
 * 		auto: If close==true will load last page on post. 
 *		      Else will reload current page on post. (default)
 * 		current: will reload current page on post
 * 		new: will load new page on post
 * 		last: will load last page on post 
 */
 
/**
 * Add Post Class
 * 
 * @package WP User Frontend
 * @subpackage WPUF_Add_Post
 */
class WPUF_Add_Post {
	var $wpuf_self = '';
	var $wpuf_referer = '';

	
	function __construct() {
		add_action( 'wp_enqueue_scripts', array($this, 'enqueue_scripts') );
		
		//hook the Ajax call
		//for logged-in users
		add_action('wp_ajax_wpuf_add_post_action', array($this, 'submit_post'));
		//for none logged-in users
		add_action('wp_ajax_nopriv_wpuf_add_post_action', array($this, 'submit_post'));
		
		add_shortcode( 'wpuf_addpost', array($this, 'shortcode') );
	}

	/**
	 * Queue up jQuery and jQuery-form
	 *
	 * @since 1.1-fork-2RRR-2.0 
	 */
	function enqueue_scripts() {
		//Add scripts only if post has shortcode
        if ( has_shortcode( 'wpuf_addpost' ) ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-form' );
			
			//Add ajaxForm javascript for form submit
			add_action('wp_head', array($this, 'wpuf_add_post_javascript'));
        }
	}	
	
	/**
	 * Adds javascript for ajaxForm
	 *
	 * @return string html
	 * @since 1.1-fork-2RRR-2.0 
	 */
	function wpuf_add_post_javascript() {
	//Note if a file upload is included this uses an iframe instead of Ajax for the current Wordpress 3.4.2 version
	//which uses jquery.form version 2.73. Versions of jquery.form 2.90 and later can use html5 ajax to do this.
	//For iframe uploads timeout and error functions wont fire.
?>
		<script type="text/javascript">
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
			success = $('success', responseXML).text();
			message = $('message', responseXML).html();
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
					jQuery('#wpuf_new_post_form').resetForm();
					jQuery('#wpuf_new_post_form').clearForm();
					
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
		</script>	
<?php
	}

	
	/**
	 * Handles the add post shortcode
	 *
	 * @param array $atts attributes
	 * @return string generated form by the plugin
	 * @global WP_User $userdata
	 */
	function shortcode( $atts ) {
		global $userdata;

		//echo '<div>REQUEST=' . print_r($_REQUEST, true) . '<br>POST=' . print_r($_POST,true) . '<br>$_GET=' . print_r($_GET,true) . '<br>$_SERVER='. print_r($_SERVER,true) . '<br>$userdata=' . print_r($userdata,true) . '</div>'; 
		
		extract( shortcode_atts( array('post_type' => 'post', 'close' => 'true', 'redirect' => 'auto'), $atts ) );

		//Suppress "edit_post_link" on this page
		add_filter( 'edit_post_link', function(){}, 10, 1 ); 

		ob_start();

		//Set referer URL. 
		//NB Stop XSS attacks by using htmlspecialchars.
		if (isset( $_GET['wpuf_referer'] ) ) {
			//login referral
			$this->wpuf_referer = htmlspecialchars($_GET['wpuf_referer']);
		} else {
			$this->wpuf_referer = htmlspecialchars($_SERVER['HTTP_REFERER']);
		}

		//URL of this page
		$this->wpuf_self = "http://" . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']);
		
		if (!is_user_logged_in() ) {
			$can_post = 'no';
			$info = __( "User is not logged in.", 'wpuf' );
			
			if ($close == 'false') {
				$url = 'http://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']);			
			} else if (isset($_SERVER['QUERY_STRING'])) {
				$url = 'http://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']) . '&wpuf_referer=' . $this->wpuf_referer;
			} else {
				$url = 'http://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']) . '?wpuf_referer=' . $this->wpuf_referer;
			}
			
			$info = sprintf(__( "This page is restricted. Please %s to view this page.", 'wpuf' ), wp_loginout($url, false ) );
		}
		else if (!current_user_can( 'edit_posts' )) {
			$can_post = 'no';
			$info = __( "User doesn't have post capability.", 'wpuf' );
		}
		else {
			$can_post = 'yes';
			$info = __( "Can't post.", 'wpuf' ); //default message
		}

		//If you use this filter to allow non logged in users make sure use a Catcha or similar.
		$can_post = apply_filters( 'wpuf_can_post', $can_post );

		$info = apply_filters( 'wpuf_addpost_notice', $info );

		if ( $can_post == 'yes' ) {
			$this->post_form( $post_type, $close, $redirect );
		}
		else {
			echo '<div class="wpuf-info">' . $info . '</div>';
		}

		//Use this filter if you want to change the return address on Close
		$wpuf_close_redirect = apply_filters( 'wpuf_add_post_close_redirect', $this->wpuf_referer, $post_type);
				
		if ($wpuf_close_redirect != "" && $close == "true") {
			//Swap the following lines if you don't want to be bothered updating admin/settings-options.php and wpuf-options-value.php
			//echo '<div id="wpuf-button-close"><a class="wpuf-button" href="' . $wpuf_close_redirect . '">Close</a></div>';
			echo '<div id="wpuf-button-close"><a class="wpuf-button" href="' . $wpuf_close_redirect . '">' . esc_attr( wpuf_get_option( 'close_label' ) ) . '</a></div>';
		}

		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}


	/**
	 * Add posting main form
	 *
	 * @param string $post_type post type
	 * @param string $close Display Close Button "true"|"false"
	 * @param string $redirect Redirect after post "auto"|"current"|"last"|"new"
	 * @return string html
	 */
	function post_form( $post_type, $close, $redirect ) {
		$featured_image = wpuf_get_option( 'enable_featured_image' );
		$title = '';
		$description = '';				
?>
		<div id="wpuf-post-area">
			<form id="wpuf_new_post_form" name="wpuf_new_post_form" action="" enctype="multipart/form-data" method="POST">
				<?php wp_nonce_field( 'wpuf-add-post' ) ?>

				<ul class="wpuf-post-form">
					<?php 
					do_action( 'wpuf_add_post_form_top', $post_type ); //plugin hook
					wpuf_build_custom_field_form( 'top' );

					if ( $featured_image == 'yes' ) {
						if ( current_theme_supports( 'post-thumbnails' ) ) {
					?>
							<li id="wpuf-ft-upload-li">
								<label for="post-thumbnail"><?php echo wpuf_get_option( 'ft_image_label' ); ?></label>
								<div id="wpuf-ft-upload-container">
									<div id="wpuf-ft-upload-filelist"></div>
									<a id="wpuf-ft-upload-pickfiles" class="wpuf-button" href="#"><?php echo wpuf_get_option( 'ft_image_btn_label' ); ?></a>
								</div>
								<div class="clear"></div>
							</li>
						<?php } else { ?>
							<div class="wpuf-info"><?php _e( 'Your theme doesn\'t support featured image', 'wpuf' ) ?></div>
						<?php
						} 
					} ?>

					<li>
						<label for="new-post-title">
							<?php echo wpuf_get_option( 'title_label' ); ?> <span class="required">*</span>
						</label>
						<input class="requiredField" type="text" value="<?php echo $title; ?>" name="wpuf_post_title" id="new-post-title" minlength="2">
						<div class="clear"></div>
						<p class="description"><?php echo stripslashes( wpuf_get_option( 'title_help' ) ); ?></p>
					</li>
						
					<?php
					if ( wpuf_get_option( 'allow_cats' ) == 'on' ) {
					?>
						<li>
							<label for="new-post-cat">
								<?php echo wpuf_get_option( 'cat_label' ); ?> <span class="required">*</span>
							</label>

							<div class="category-wrap" style="float:left;">
								<div id="lvl0">
									<?php
									$exclude = wpuf_get_option( 'exclude_cats' );
									$cat_type = wpuf_get_option( 'cat_type' );

									if ( $cat_type == 'normal' ) {
										wp_dropdown_categories( 'show_option_none=' . __( '-- Select --', 'wpuf' ) . '&hierarchical=1&hide_empty=0&orderby=name&name=category[]&id=cat&show_count=0&title_li=&use_desc_for_title=1&class=cat requiredField&exclude=' . $exclude );
									} else if ( $cat_type == 'ajax' ) {
										wp_dropdown_categories( 'show_option_none=' . __( '-- Select --', 'wpuf' ) . '&hierarchical=1&hide_empty=0&orderby=name&name=category[]&id=cat-ajax&show_count=0&title_li=&use_desc_for_title=1&class=cat requiredField&depth=1&exclude=' . $exclude );
									} else {
										wpuf_category_checklist();
									}
									?>
								</div>
							</div>
							<div class="loading"></div>
							<div class="clear"></div>
							<p class="description"><?php echo stripslashes( wpuf_get_option( 'cat_help' ) ); ?></p>
						</li>
					<?php
					}

					do_action( 'wpuf_add_post_form_description', $post_type );
					wpuf_build_custom_field_form( 'description' );
					?>
					<li>
						<label for="new-post-desc">
							<?php echo wpuf_get_option( 'desc_label' ); ?> <span class="required">*</span>
						</label>

						<?php
						$editor = wpuf_get_option( 'editor_type' );

						//Filter $editor. Useful for adding custom editors or assigning editors according to users..
						$editor = apply_filters( 'wpuf_editor_type', $editor );

						if ( $editor == 'full' ) {
							wp_editor( $description, 'new-post-desc', array('textarea_name' => 'wpuf_post_content', 'editor_class' => 'requiredField', 'teeny' => false, 'textarea_rows' => 8) ); 
						} else if ( $editor == 'rich' ) {
							wp_editor( $description, 'new-post-desc', array('textarea_name' => 'wpuf_post_content', 'editor_class' => 'requiredField', 'teeny' => true, 'textarea_rows' => 8) ); 
						} else if ( $editor == 'plain' ) { 
						?>
						<div class="clear"></div>
							<textarea name="wpuf_post_content" class="requiredField wpuf-editor-plain" id="new-post-desc" cols="60" rows="8"><?php echo esc_textarea( $description ); ?></textarea>
						<?php } else { 
							//Use custom editor. 
							//Two ways to enable.
							//1. wpuf_editor_type filter above.
							//2. showtime_wpuf_options_frontend filter.
							do_action('wpuf_custom_editor', $editor, $description, 'new-post-desc', 'wpuf_post_content');
						}
						?>
						<div class="clear"></div>
						<p class="description"><?php echo stripslashes( wpuf_get_option( 'desc_help' ) ); ?></p>
					</li>

					<?php
					do_action( 'wpuf_add_post_form_after_description', $post_type );

					$this->publish_date_form();
					$this->expiry_date_form();

					wpuf_build_custom_field_form( 'tag' );

					if ( wpuf_get_option( 'allow_tags' ) == 'on' ) {
					?>
						<li>
							<label for="new-post-tags">
								<?php echo wpuf_get_option( 'tag_label' ); ?>
							</label>
							<input type="text" name="wpuf_post_tags" id="new-post-tags" class="new-post-tags">
							<p class="description"><?php echo stripslashes( wpuf_get_option( 'tag_help' ) ); ?></p>
							<div class="clear"></div>
						</li>
					<?php
					}

					do_action( 'wpuf_add_post_form_tags', $post_type );
					wpuf_build_custom_field_form( 'bottom' );
					?>

					<li id="wpuf-submit-li">
						<div id="wpuf-info-msg">&nbsp;</div>
						<input class="wpuf-submit" type="submit" name="wpuf_new_post_submit" value="<?php echo esc_attr( wpuf_get_option( 'submit_label' ) ); ?>">
						<input type="hidden" name="wpuf_post_type" value="<?php echo $post_type; ?>" />
						<input type="hidden" name="wpuf_close" value="<?php echo $close ?>" />
						<input type="hidden" name="wpuf_redirect" value="<?php echo $redirect ?>" />
						<input type="hidden" name="wpuf_self" value="<?php echo $this->wpuf_self ?>" />
						<input type="hidden" name="wpuf_referer" value="<?php echo $this->wpuf_referer ?>" />
					</li>

					<?php do_action( 'wpuf_add_post_form_bottom', $post_type ); ?>

				</ul>
			</form>
		</div>
<?php
	}

	
	/**
	 * Prints the post publish date on form
	 *
	 * @return bool|string
	 */
	function publish_date_form() {
		$enable_date = wpuf_get_option( 'enable_post_date' );

		if ( $enable_date != 'on' ) {
				return;
		}

		$timezone_format = _x( 'Y-m-d G:i:s', 'timezone date format' );
		$month = date_i18n( 'm' );
		$month_array = array(
			'01' => 'Jan',
			'02' => 'Feb',
			'03' => 'Mar',
			'04' => 'Apr',
			'05' => 'May',
			'06' => 'Jun',
			'07' => 'Jul',
			'08' => 'Aug',
			'09' => 'Sep',
			'10' => 'Oct',
			'11' => 'Nov',
			'12' => 'Dec'
		);
?>
		<li>
			<label for="timestamp-wrap">
			<?php _e( 'Publish Time:', 'wpuf' ); ?> <span class="required">*</span>
			</label>
			<div class="timestamp-wrap">
				<select name="mm">
					<?php
					foreach ($month_array as $key => $val) {
						$selected = ( $key == $month ) ? ' selected="selected"' : '';
						echo '<option value="' . $key . '"' . $selected . '>' . $val . '</option>';
					}
					?>
				</select>
				<input type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo date_i18n( 'd' ); ?>" name="jj">,
				<input type="text" autocomplete="off" tabindex="4" maxlength="4" size="4" value="<?php echo date_i18n( 'Y' ); ?>" name="aa">
				@ <input type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo date_i18n( 'G' ); ?>" name="hh">
				: <input type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo date_i18n( 'i' ); ?>" name="mn">
			</div>
			<div class="clear"></div>
			<p class="description"></p>
		</li>
<?php
	}

	
	/**
	* Prints post expiration date on the form
	*
	* @return bool|string
	*/
	function expiry_date_form() {
		$post_expiry = wpuf_get_option( 'enable_post_expiry' );

		if ( $post_expiry != 'on' ) {
			return;
		}
?>
		<li>
			<label for="timestamp-wrap">
				<?php _e( 'Expiration Time:', 'wpuf' ); ?><span class="required">*</span>
			</label>
			<select name="expiration-date">
				<?php
				for ($i = 1; $i <= 90; $i++) {
					if ( $i % 2 != 0 ) {
						continue;
					}

					printf( '<option value="%1$d">%1$d %2$s</option>', $i, __( 'days', 'wpuf' ) );
				}
				?>
			</select>
			<div class="clear"></div>
			<p class="description"><?php _e( 'Post expiration time in day after publishing.', 'wpuf' ); ?></p>
		</li>
<?php
	}

	
	/**
	* Validate and insert post message.
	* Called by AjaxForm on form submit.
	* Returns XML message via AjaxForm.
	*
	 * @global WP_User $userdata
	*/
	function submit_post() {
		global $userdata;
		
		//echo '<root><success>false></success><message><div>REQUEST=' . print_r($_REQUEST, true) . '<br>POST=' . print_r($_POST,true) . '<br>$_SERVER='. print_r($_SERVER,true) . '<br>$userdata=' . print_r($userdata,true) . '</div></message></root>'; 
 
		if ( !wp_verify_nonce( $_REQUEST['_wpnonce'], 'wpuf-add-post' ) ) {
			//XML message
			echo '<root><success>false</success><message>' . wpuf_error_msg( __( 'Cheating?' ) ) . '</message></root>';
			exit;
		}
		
		$errors = array();

		//if there is some attachement, validate them
		if ( !empty( $_FILES['wpuf_post_attachments'] ) ) {
			$errors = wpuf_check_upload();
		}

		$title = trim( $_POST['wpuf_post_title'] );
		$content = trim( $_POST['wpuf_post_content'] );

		$tags = '';
		if ( isset( $_POST['wpuf_post_tags'] ) ) {
			$tags = wpuf_clean_tags( $_POST['wpuf_post_tags'] );
		}

		//validate title
		if ( empty( $title ) ) {
			$errors[] = __( 'Empty post title', 'wpuf' );
		} else {
			$title = trim( strip_tags( $title ) );
		}

		//validate category

		if ( wpuf_get_option( 'allow_cats' ) == 'on' ) {
			$cat_type = wpuf_get_option( 'cat_type' );
			if ( !isset( $_POST['category'] ) ) {
				$errors[] = __( 'Please choose a category', 'wpuf' );
			} else if ( $cat_type == 'normal' && $_POST['category'][0] == '-1' ) {
				$errors[] = __( 'Please choose a category', 'wpuf' );
			} else {
				if ( count( $_POST['category'] ) < 1 ) {
					$errors[] = __( 'Please choose a category', 'wpuf' );
				}
			}
		}

		//validate post content
		if ( empty( $content ) ) {
			$errors[] = __( 'Empty post content', 'wpuf' );
		} else {
			$content = trim( $content );
		}

		//process tags
		if ( !empty( $tags ) ) {
			$tags = explode( ',', $tags );
		}

		//post attachment
		$attach_id = isset( $_POST['wpuf_featured_img'] ) ? intval( $_POST['wpuf_featured_img'] ) : 0;

		//post type
		$post_type = trim( strip_tags( $_POST['wpuf_post_type'] ) );

		//process the custom fields
		$custom_fields = array();

		$fields = wpuf_get_custom_fields();
		if ( is_array( $fields ) ) {

			foreach ($fields as $cf) {
				if ( array_key_exists( $cf['field'], $_POST ) ) {

					$temp = trim( strip_tags( $_POST[$cf['field']] ) );
					//var_dump($temp, $cf);

					if ( ( $cf['type'] == 'yes' ) && !$temp ) {
						$errors[] = sprintf( __( '%s is missing', 'wpuf' ), $cf['label'] );
					} else {
						$custom_fields[$cf['field']] = $temp;
					}
				} //array_key_exists
			} //foreach
		} //is_array

		$post_date_enable = wpuf_get_option( 'enable_post_date' );
		$post_expiry = wpuf_get_option( 'enable_post_expiry' );

		//check post date
		if ( $post_date_enable == 'on' ) {
			$month = $_POST['mm'];
			$day = $_POST['jj'];
			$year = $_POST['aa'];
			$hour = $_POST['hh'];
			$min = $_POST['mn'];

			if ( !checkdate( $month, $day, $year ) ) {
				$errors[] = __( 'Invalid date', 'wpuf' );
			}
		}

		$errors = apply_filters( 'wpuf_add_post_validation', $errors );

		//if not any errors, proceed
		if ( $errors ) {
			//XML message
			echo '<root><success>false</success><message>' . wpuf_error_msg( $errors ) . '</message></root>';
			exit;
		}

		$post_stat = wpuf_get_option( 'post_status' );

		//Set author according to 'post author' option.
		//If not logged in user ($userdata null) map user to 'map_author' option
		if (isset($userdata) && wpuf_get_option( 'post_author' ) == 'original') {
			$post_author = $userdata->ID;
		} else {
			$post_author = wpuf_get_option( 'map_author' );
		}

		//Set category to default if users aren't allowed to choose category
		if ( wpuf_get_option( 'allow_cats' ) == 'on' ) {
			$post_category = $_POST['category'];
		} else {
			$post_category = array(wpuf_get_option( 'default_cat' ));
		}

		$my_post = array(
			'post_title' => $title,
			'post_content' => $content,
			'post_status' => $post_stat,
			'post_author' => $post_author,
			'post_category' => $post_category,
			'post_type' => $post_type,
			'tags_input' => $tags
		);

		if ( $post_date_enable == 'on' ) {
			$month = $_POST['mm'];
			$day = $_POST['jj'];
			$year = $_POST['aa'];
			$hour = $_POST['hh'];
			$min = $_POST['mn'];

			$post_date = mktime( $hour, $min, 59, $month, $day, $year );
			$my_post['post_date'] = date( 'Y-m-d H:i:s', $post_date );
		}

		//plugin API to extend the functionality
		$my_post = apply_filters( 'wpuf_add_post_args', $my_post );

		//var_dump( $_POST, $my_post );die();
		
		//insert the post
		$post_id = wp_insert_post( $my_post, true);

		//if insert post ok, proceed
		if (is_wp_error($post_id)) {
			 echo '<root><success>false</success><message>' . wpuf_error_msg( __( 'Post insert failed. ', 'wpuf' ) . $post_id->get_error_message()) . '</message></root>';
			 exit;
		}	 
		
		//upload attachment to the post
		wpuf_upload_attachment( $post_id );

		//send mail notification
		if ( wpuf_get_option( 'post_notification' ) == 'yes' ) {
			if (isset($userdata)) {
				wpuf_notify_post_mail( $userdata, $post_id );
			} else {
				//If not logged in user ($userdata null) map user to 'map_author' option
				wpuf_notify_post_mail(get_userdata(wpuf_get_option( 'map_author' )), $post_id );
			}
		}

		//add the custom fields
		if ( $custom_fields ) {
			foreach ($custom_fields as $key => $val) {
				add_post_meta( $post_id, $key, $val, true );
			}
		}

		//set post thumbnail if has any
		if ( $attach_id ) {
			set_post_thumbnail( $post_id, $attach_id );
		}

		//Set Post expiration date if has any
		if ( !empty( $_POST['expiration-date'] ) && $post_expiry == 'on' ) {
			$post = get_post( $post_id );
			$post_date = strtotime( $post->post_date );
			$expiration = (int) $_POST['expiration-date'];
			$expiration = $post_date + ($expiration * 60 * 60 * 24);

			add_post_meta( $post_id, 'expiration-date', $expiration, true );
		}

		//plugin API to extend the functionality
		do_action( 'wpuf_add_post_after_insert', $post_id );

		//Set after post redirect
		switch ($_POST['wpuf_redirect']) {
			case "auto":
				if ($_POST['wpuf_close'] == true) {
					$redirect_url = $_POST['wpuf_referer'];
				} else {
					$redirect_url = $_POST['wpuf_self'];
				}
				break;
			case "current":
				$redirect_url = $_POST['wpuf_self'];
				break;
			case "last":
				$redirect_url = $_POST['wpuf_referer'];
				break;
			case "new":
				$redirect_url = get_permalink( $post_id );
				break;
			default:
				$redirect_url = "";
		}

		$redirect_url = apply_filters( 'wpuf_after_post_redirect', $redirect_url, $post_id );
		
		$post_status = get_post_status($post_id);

		switch ($post_status) {
			case "publish":
				echo '<root><success>true</success><message><div class="wpuf-success">' . __('Post published successfully', 'wpuf') . '</div></message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
				break;
			case "pending":
				echo '<root><success>true</success><message><div class="wpuf-success">' . __('Post pending review', 'wpuf') . '</div></message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
				break;
			case "draft":
				echo '<root><success>true</success><message><div class="wpuf-success">' . __('Post saved as draft', 'wpuf') . '</div></message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
				break;
			case "future":
				echo '<root><success>true</success><message><div class="wpuf-success">' . __('Post to be published in future', 'wpuf') . '</div></message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
				break;
			default:
				echo '<root><success>true</success><message><div class="wpuf-success">' . __('Post status', 'wpuf') . ': ' . $post_status . '</div></message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
		}
		
		exit;
	}

}


$wpuf_postform = new WPUF_Add_Post();

