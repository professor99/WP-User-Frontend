<?php

/**
 * Edit Post form class
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
* Code updated to allow use of wpuf_can_post filter for non-logged in users.
* Post updated to new post on save
*/
 
/*
 *  Shortcode examples::
 * 
 * 	[wpuf_edit]
 * 	[wpuf_edit close="false"]
 * 	[wpuf_edit close="false" redirect="none"]
 *
 *  Shortcode options:
 * 
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
 * Edit Post Class
 * 
 * @package WP User Frontend
 * @subpackage WPUF_Edit_Post
 */
class WPUF_Edit_Post {
	var $wpuf_self = '';
	var $wpuf_referer = '';

	function __construct() {
		add_action( 'wp_enqueue_scripts', array($this, 'enqueue_scripts') );
		
		//hook the Ajax call
		//for logged-in users
		add_action('wp_ajax_wpuf_edit_post_action', array($this, 'submit_post'));
		//for none logged-in users
		add_action('wp_ajax_nopriv_wpuf_edit_post_action', array($this, 'submit_post'));
		
		add_shortcode( 'wpuf_edit', array($this, 'shortcode') );
	}

	/**
	 * Queue up jQuery and jQuery-form
	 *
	 * @since 1.1-fork-2RRR-2.0 
	 */
	function enqueue_scripts() {
 		//Add scripts only if post has shortcode
		if ( has_shortcode( 'wpuf_edit' ) ) {
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'jquery-form' );
			
			//Add ajaxForm javascript for form submit
			add_action('wp_head', array($this, 'wpuf_edit_post_javascript'));
		}
	}	

	/**
	 * Adds javascript for ajaxForm
	 *
	 * @return string html
	 * @since 1.1-fork-2RRR-2.0 
	 */
	function wpuf_edit_post_javascript() {
	//Note if a file upload is included this uses an iframe instead of Ajax for the current Wordpress 3.4.2 version
	//which uses jquery.form version 2.73. Versions of jquery.form 2.90 and later can use html5 ajax to do this.
	//For iframe uploads timeout and error functions wont fire.
?>
		<script type="text/javascript">
		jQuery(document).ready(function() {
			var options = { 
				datatype:	'xml',
				beforeSubmit: wpuf_edit_post_before_submit,
				success:	wpuf_edit_post_success,
				error:		wpuf_edit_post_error,
				timeout:	3000, 
				url:		wpuf.ajaxurl,
				data:		{ action: 'wpuf_edit_post_action' }
			}

			// bind form using 'ajaxForm' 
			jQuery('#wpuf_edit_post_form').ajaxForm(options);
		});

		function wpuf_edit_post_before_submit(formData, jqForm, options) { 
			jQuery('#wpuf-info-msg').html('&nbsp;');
		}
		
		function wpuf_edit_post_success(responseXML) { 
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
					jQuery('#wpuf_edit_post_form').resetForm();
					jQuery('#wpuf_edit_post_form').clearForm();
					
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
		</script>	
<?php
	}

	
	/**
	 * Handles the edit post shortcode
	 *
	 * @param $atts
	 * @return string generated form by the plugin
	 * @global WP_User $userdata
	 */
	function shortcode( $atts ) {
		global $userdata;

		//echo '<div>REQUEST=' . print_r($_REQUEST, true) . '<br>POST=' . print_r($_POST,true) . '<br>$_GET=' . print_r($_GET,true) . '<br>$_SERVER='. print_r($_SERVER,true) . '<br>$userdata=' . print_r($userdata,true) . '</div>'; 
		
		extract( shortcode_atts( array('close' => 'true'), $atts ) );
		extract( shortcode_atts( array('close' => 'true', 'redirect' => 'auto'), $atts ) );

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

		//Get updated post
		$post_id = isset( $_GET['pid'] ) ? intval( $_GET['pid'] ) : 0;
		$curpost = get_post( $post_id );

		$invalid = false;
		
		if ( !$curpost ) {
			$invalid = true;
			$can_edit = 'no';
			$info = __( 'Invalid post', 'wpuf' );
		}
		else if ( wpuf_get_option( 'enable_post_edit', 'yes' ) != 'yes' ) {
			$can_edit = 'no';
			$info = __( 'Post Editing is disabled', 'wpuf' );
		} 
		else if (!is_user_logged_in() ) {
			$can_edit = 'no';
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
		else if (!current_user_can( 'edit_others_posts' ) && current_user_can( 'edit_posts' ) && $userdata->ID != $curpost->post_author ) {
			$can_edit = 'no';
			$info = __( "You are not allowed to edit", 'wpuf' );
		}
		else {
			$can_edit = 'yes';
			$info = __( "Can't post.", 'wpuf' ); //default message
		}

		if (!$invalid) {
			//If you use this filter to allow non logged in users make sure use a Catcha or similar.
			$can_edit = apply_filters( 'wpuf_can_edit', $can_edit );

			$info = apply_filters( 'wpuf_editpost_notice', $info );
		}
		
		if ( $can_edit == 'yes' ) {
			//show post form
			$this->edit_form( $curpost, $close, $redirect );
		}
		else {
			echo '<div class="wpuf-info">' . $info . '</div>';
		}

		//Use this filter if you want to change the return address on Close
		$wpuf_close_redirect = apply_filters( 'wpuf_edit_post_close_redirect', $this->wpuf_referer, $post_type);
				
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
	* Main edit post form
	*
	*/
	function edit_form( $curpost, $close, $redirect ) {
		$post_tags = wp_get_post_tags( $curpost->ID );
		$tagsarray = array();

		foreach ($post_tags as $tag) {
			$tagsarray[] = $tag->name;
		}
		
		$tagslist = implode( ', ', $tagsarray );
		$categories = get_the_category( $curpost->ID );
		$featured_image = wpuf_get_option( 'enable_featured_image' );
?>
		<div id="wpuf-post-area">
			<form name="wpuf_edit_post_form" id="wpuf_edit_post_form" action="" enctype="multipart/form-data" method="POST">
				<?php wp_nonce_field( 'wpuf-edit-post' ) ?>
				<ul class="wpuf-post-form">

					<?php 
					do_action( 'wpuf_add_post_form_top', $curpost->post_type, $curpost ); //plugin hook
					wpuf_build_custom_field_form( 'top', true, $curpost->ID );

					if ( $featured_image == 'yes' ) {
						if ( current_theme_supports( 'post-thumbnails' ) ) {
					?>
							<li id="wpuf-ft-upload-li">
								<label for="post-thumbnail"><?php echo wpuf_get_option( 'ft_image_label' ); ?></label>
								<div id="wpuf-ft-upload-container">
									<div id="wpuf-ft-upload-filelist">
										<?php
										$style = '';
										if ( has_post_thumbnail( $curpost->ID ) ) {
											$style = ' style="display:none;"';

											$post_thumbnail_id = get_post_thumbnail_id( $curpost->ID );
											echo wpuf_feat_img_html( $post_thumbnail_id );
										}
										?>
									</div>
									<a id="wpuf-ft-upload-pickfiles" class="wpuf-button" <?php echo $style?> href="#"><?php echo wpuf_get_option( 'ft_image_btn_label' ); ?></a>
								</div>
								<div class="clear"></div>
							</li>
						<?php
						} else {
						?>
							<div class="wpuf-info"><?php _e( 'Your theme doesn\'t support featured image', 'wpuf' ) ?></div>
						<?php
						}
					}	?>

					<li>
						<label for="new-post-title">
							<?php echo wpuf_get_option( 'title_label' ); ?> <span class="required">*</span>
						</label>
						<input class="requiredField" type="text" name="wpuf_post_title" id="new-post-title" minlength="2" value="<?php echo esc_html( $curpost->post_title ); ?>">
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

							<?php
							$exclude = wpuf_get_option( 'exclude_cats' );
							$cat_type = wpuf_get_option( 'cat_type' );

							$cats = get_the_category( $curpost->ID );
							$selected = 0;
							if ( $cats ) {
								$selected = $cats[0]->term_id;
							}
							//var_dump( $cats );
							//var_dump( $selected );
							?>
							<div class="category-wrap" style="float:left;">
								<div id="lvl0">
									<?php
									if ( $cat_type == 'normal' ) {
										wp_dropdown_categories( 'show_option_none=' . __( '-- Select --', 'wpuf' ) . '&hierarchical=1&hide_empty=0&orderby=name&name=category[]&id=cat&show_count=0&title_li=&use_desc_for_title=1&class=cat requiredField&exclude=' . $exclude . '&selected=' . $selected );
									} else if ( $cat_type == 'ajax' ) {
										wp_dropdown_categories( 'show_option_none=' . __( '-- Select --', 'wpuf' ) . '&hierarchical=1&hide_empty=0&orderby=name&name=category[]&id=cat-ajax&show_count=0&title_li=&use_desc_for_title=1&class=cat requiredField&depth=1&exclude=' . $exclude . '&selected=' . $selected );
									} else {
										wpuf_category_checklist( $curpost->ID );
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

					do_action( 'wpuf_add_post_form_description', $curpost->post_type, $curpost );
					wpuf_build_custom_field_form( 'description', true, $curpost->ID );
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
							wp_editor( $curpost->post_content, 'new-post-desc', array('textarea_name' => 'wpuf_post_content', 'editor_class' => 'requiredField', 'teeny' => false, 'textarea_rows' => 8) );
						} else if ( $editor == 'rich' ) {
							wp_editor( $curpost->post_content, 'new-post-desc', array('textarea_name' => 'wpuf_post_content', 'editor_class' => 'requiredField', 'teeny' => true, 'textarea_rows' => 8) );
						} else if ( $editor == 'plain' ) { 
						?>
						<div class="clear"></div>
						<textarea name="wpuf_post_content" class="requiredField wpuf-editor-plain" id="new-post-desc" cols="60" rows="8"><?php echo esc_textarea( $curpost->post_content ); ?></textarea>
						<?php } else {
							//Use custom editor. 
							//Two ways to enable.
							//1. wpuf_editor_type filter above.
							//2. showtime_wpuf_options_frontend filter.
							do_action('wpuf_custom_editor', $editor, $curpost->post_content, 'new-post-desc', 'wpuf_post_content');
						}
						?>

						<div class="clear"></div>
						<p class="description"><?php echo stripslashes( wpuf_get_option( 'desc_help' ) ); ?></p>
					</li>

					<?php 
					do_action( 'wpuf_add_post_form_after_description', $curpost->post_type, $curpost ); 
					wpuf_build_custom_field_form( 'tag', true, $curpost->ID ); 

					if ( wpuf_get_option( 'allow_tags' ) == 'on' ) { 
					?>
						<li>
							<label for="new-post-tags">
								<?php echo wpuf_get_option( 'tag_label' ); ?>
							</label>
							<input type="text" name="wpuf_post_tags" id="new-post-tags" value="<?php echo $tagslist; ?>">
							<p class="description"><?php echo stripslashes( wpuf_get_option( 'tag_help' ) ); ?></p>
							<div class="clear"></div>
						</li>
					<?php 
					}

					do_action( 'wpuf_add_post_form_tags', $curpost->post_type, $curpost ); 
					wpuf_build_custom_field_form( 'bottom', true, $curpost->ID ); 
					?>

					<li id="wpuf-submit-li">
						<div id="wpuf-info-msg">&nbsp;</div>
						<input class="wpuf-submit" type="submit" name="wpuf_edit_post_submit" value="<?php echo esc_attr( wpuf_get_option( 'update_label' ) ); ?>">
						<input type="hidden" name="wpuf_edit_post_submit" value="yes" />
						<input type="hidden" name="post_id" value="<?php echo $curpost->ID; ?>">
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
	* Validate and insert post message.
	* Called by AjaxForm on form submit.
	* Returns XML message via AjaxForm.
	*
	 * @global WP_User $userdata
	*/
	function submit_post() {
		global $userdata;
		//echo '<root><success>false></success><message><div>REQUEST=' . print_r($_REQUEST, true) . '<br>POST=' . print_r($_POST,true) . '<br>$_SERVER='. print_r($_SERVER,true) . '<br>$userdata=' . print_r($userdata,true) . '</div></message></root>'; 
 
		if ( !wp_verify_nonce( $_REQUEST['_wpnonce'], 'wpuf-edit-post' ) ) {
			//XML message
			echo '<root><success>false</success><message>' . wpuf_error_msg( __( 'Cheating?' ) ) . '</message></root>';
			exit;
		}
		$errors = array();

		$title = trim( $_POST['wpuf_post_title'] );
		$content = trim( $_POST['wpuf_post_content'] );

		$tags = '';
		$cat = '';
		
		if ( isset( $_POST['wpuf_post_tags'] ) ) {
			$tags = wpuf_clean_tags( $_POST['wpuf_post_tags'] );
		}

		//if there is some attachement, validate them
		if ( !empty( $_FILES['wpuf_post_attachments'] ) ) {
			$errors = wpuf_check_upload();
		}

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

		if ( empty( $content ) ) {
			$errors[] = __( 'Empty post content', 'wpuf' );
		} else {
			$content = trim( $content );
		}

		if ( !empty( $tags ) ) {
			$tags = explode( ',', $tags );
		}

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
		
		//post attachment
		$attach_id = isset( $_POST['wpuf_featured_img'] ) ? intval( $_POST['wpuf_featured_img'] ) : 0;

		$errors = apply_filters( 'wpuf_edit_post_validation', $errors );

		//if not any errors, proceed
		if ( $errors ) {
			//XML message
			echo '<root><success>false</success><message>' . wpuf_error_msg( $errors ) . '</message></root>';
			exit;
		}
					
		//Set category to default if users aren't allowed to choose category
		if ( wpuf_get_option( 'allow_cats' ) == 'on' ) {
			$post_category = $_POST['category'];
		} else {
			$post_category = array(get_option( 'wpuf_default_cat' ));
		}

		$post_update = array(
			'ID' => trim( $_POST['post_id'] ),
			'post_title' => $title,
			'post_content' => $content,
			'post_category' => $post_category,
			'tags_input' => $tags
		);

		//plugin API to extend the functionality
		$post_update = apply_filters( 'wpuf_edit_post_args', $post_update );

		$post_id = wp_update_post( $post_update );

		if ( !$post_id ) {
			//XML message
			echo '<root><success>false</success><message>' . wpuf_error_msg(  __( 'Post update failed.', 'wpuf' ) ) . '</message></root>';
			exit;
		}

		//upload attachment to the post
		wpuf_upload_attachment( $post_id );

		//set post thumbnail if has any
		if ( $attach_id ) {
			set_post_thumbnail( $post_id, $attach_id );
		}

		//add the custom fields
		if ( $custom_fields ) {
			foreach ($custom_fields as $key => $val) {
				update_post_meta( $post_id, $key, $val, false );
			}
		}

		do_action( 'wpuf_edit_post_after_update', $post_id );

		//Set after edit redirect
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
		
		$redirect_url = apply_filters( 'wpuf_after_update_redirect', $redirect_url, $post_id );

		echo '<root><success>true</success><message><div class="wpuf-success">' . __('Post updated succesfully', 'wpuf') . '</div></message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
		exit;
	}

}

$wpuf_edit = new WPUF_Edit_Post();