<?php

/**
 * Add Post form class
 *
 * @author Tareq Hasan 
 * @package WP User Frontend
 * @version 1.1-fork-2RRR-3.0 
 */

/*
== Changelog ==

= 1.1-fork-2RRR-3.0 professor99 =
* Added excerpt
* Re-styled form to suit
* Change expiration time to actual date/time
* Made hour two digits with leading zero
* Checks for valid times
* Made attachment calls inline (was actions)
* Featured image html moved to WPUF_Featured_Image::add_post_fields()
* Removed attachment code replaced by ajax
* Can display top line info message anytime
* Removed 'Your theme doesn't support featured image' message
* Fixed javascript success clear form bug
* Redirects now filtered by wpuf_post_redirect 
* Form actions consolidated under wpuf_post_form
* Escaped info message XML tags

= 1.1-fork-2RRR-2.1 professor99 = 
* Replaced anonymous function with suppress_edit_post_link()

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
 
 /* Notes
 *
 * The action 'wpuf_post_form' is common to both this file and wpuf_add_post.php.
 * It is invoked as function($form, $location, $post_type, $post). 
 * For this file $form = 'add' and $post = ''. 
 *
 * The filter 'wpuf_post_redirect' is common to both this file and wpuf_add_post.php.
 * It is invoked as function($form, $location, $redirect_url, $post_id). 
 * For this file  $form = 'add' and $post_id = '' if not defined.
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
	var $logged_in = false;
	
	function __construct() {
		//Ajax calls for Submit Post button 
		add_action('wp_ajax_wpuf_add_post_action', array($this, 'submit_post'));
		add_action('wp_ajax_nopriv_wpuf_add_post_action', array($this, 'submit_post'));
		
		add_shortcode( 'wpuf_addpost', array($this, 'shortcode') );
	}

	/**
	 * Queue up jQuery, jQuery-form, and wpuf-add-post javascript for header
	 *
	 * @since 1.1-fork-2RRR-2.0 
	 */
	function enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-form' );
		wp_enqueue_script( 'wpuf_add_post', plugins_url( 'js/wpuf-add-post.js',  __FILE__ ) );
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

		//Add javascript files
		$this->enqueue_scripts();
				
		extract( shortcode_atts( array('post_type' => 'post', 'close' => 'true', 'redirect' => 'auto'), $atts ) );

		//Suppress "edit_post_link" on this page
		add_filter( 'edit_post_link', suppress_edit_post_link, 10, 2 ); 

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

		$this->logged_in = is_user_logged_in();

		$can_post = 'yes';
		$info = ''; 
				
		if (!$this->logged_in) {
			//Get login page url			
			if ($close == 'false') {
				$login_url = 'http://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']);			
			} else if (isset($_SERVER['QUERY_STRING'])) {
				$login_url = 'http://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']) . '&wpuf_referer=' . $this->wpuf_referer;
			} else {
				$login_url = 'http://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']) . '?wpuf_referer=' . $this->wpuf_referer;
			}

			$can_post = 'no';			
			$info = sprintf(__( "This page is restricted. Please %s to view this page.", 'wpuf' ), wp_loginout($login_url, false ) );
		}
		else if (!current_user_can( 'edit_posts' )) {
			$can_post = 'no';
			$info = __( "You are not permitted to add posts.", 'wpuf' );
		}
		
		//If you use this filter to allow non logged in users make sure use a Catcha or similar.
		$can_post = apply_filters( 'wpuf_can_post', $can_post );

		$info = apply_filters( 'wpuf_addpost_notice', $info );

		if ($info) 
			echo '<div class="wpuf-info">' . $info . '</div>';
				
		if ( $can_post == 'yes' ) {
			$this->post_form( $post_type, $close, $redirect );
		}

		//Use this filter if you want to change the return address on Close
		$redirect_url = apply_filters( 'wpuf_post_redirect', 'add', 'close', $this->wpuf_referer, '');
				
		if ($redirect_url != "" && $close == "true") {
			echo '<div id="wpuf-button-close"><a class="wpuf-button" href="' . $redirect_url . '">' . esc_attr( wpuf_get_option( 'close_label' ) ) . '</a></div>';
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
		$title = '';
		$description = '';				
?>
		<div id="wpuf-post-area">
			<form id="wpuf_new_post_form" name="wpuf_new_post_form" action="" enctype="multipart/form-data" method="POST">
				<?php wp_nonce_field( 'wpuf-add-post' ) ?>

				<ul class="wpuf-post-form">
					<?php 
					do_action( 'wpuf_post_form', 'add', 'top', $post_type, '' ); 
					wpuf_build_custom_field_form( 'top' );

					//Add featured image field if enabled and the current theme supports thumbnails

					$featured_image = wpuf_get_option( 'enable_featured_image' );
					
					if ( $featured_image == 'yes' && current_theme_supports( 'post-thumbnails' ) ) {
						WPUF_Featured_Image::add_post_fields( $curpost->post_type );
					} 
					?>

					<li>
						<label for="new-post-title">
							<?php echo wpuf_get_option( 'title_label' ); ?> <span class="required">*</span>
						</label>
						<input class="requiredField" type="text" value="<?php echo $title; ?>" name="wpuf_post_title" id="new-post-title" minlength="2">
						<div class="clear"></div>
						<?php
						$helptxt = stripslashes( wpuf_get_option( 'title_help' ) );
						if ($helptxt) 
							echo '<p class="description">' . $helptxt . '</p>';
						?>
					</li>
						
					<?php	
					do_action( 'wpuf_post_form', 'add', 'description', $post_type, '' ); 
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
						<?php
						$helptxt = stripslashes( wpuf_get_option( 'desc_help' ) );
						if ($helptxt) 
							echo '<p class="description-left">' . $helptxt . '</p>';
						?>
					</li>

					<?php
					do_action( 'wpuf_post_form', 'add', 'after_description', $post_type, '' ); 
					wpuf_build_custom_field_form( 'after_description' );

					if ( wpuf_get_option( 'allow_excerpt' ) == 'on' ) {
						$max_chars = wpuf_get_option( 'excerpt_max_chars' );
					?>
						<li>
							<label for="wpuf-excerpt">
							<?php 
							if ($max_chars == 0) {
								echo wpuf_get_option( 'excerpt_label' );
								$maxlength = '';
							} else {	
								echo wpuf_get_option( 'excerpt_label' ) . ' (max '. $max_chars . ' chars)';
								$maxlength = 'maxlength="' . $max_chars . '"';
							}	

							if ( wpuf_get_option( 'require_excerpt' ) == 'on' ) {
							?>	
								<span class="required">*</span>
							<?php
							}
							?>
							</label>
							<div class="clear"></div>
							<div class="wpuf-textarea-container">
								<?php
								if ( wpuf_get_option( 'require_excerpt' ) == 'on' ) {
								?>	
									<textarea class="requiredField" id="wpuf-excerpt" name="wpuf_excerpt" cols="80" rows="2" <?php echo $maxlength ?> ></textarea>
								<?php
								} else {
								?>
									<textarea id="wpuf-excerpt" name="wpuf_excerpt" cols="80" rows="2" <?php echo $maxlength ?> ></textarea>
								<?php	
								}
								?>
							</div>
							<div class="clear"></div>
							<?php	
							$helptxt = stripslashes( wpuf_get_option( 'excerpt_help' ) );
							if ($helptxt) 
								echo '<p class="description-left">' . $helptxt . '</p>';
							?>
						</li>
					<?php
					}
					
					if ( wpuf_get_option( 'allow_tags' ) == 'on' ) {
					?>
						<li>
							<label for="new-post-tags">
								<?php echo wpuf_get_option( 'tag_label' ); ?>
							</label>
							<input type="text" name="wpuf_post_tags" id="new-post-tags" class="new-post-tags">
							<div class="clear"></div>
							<?php	
							$helptxt = stripslashes( wpuf_get_option( 'tag_help' ) );
							if ($helptxt) 
								echo '<p class="description">' . $helptxt . '</p>';
							?>
						</li>
					<?php
					}

					do_action( 'wpuf_post_form', 'add', 'tag', $post_type, '' ); 
					wpuf_build_custom_field_form( 'tag' );

					//Add attachment fields if enabled
					
					$allow_upload = wpuf_get_option( 'allow_attachment' );
					
					if ( $allow_upload == 'yes' ) {
						WPUF_Attachment::add_post_fields( $post_type );
					}
										
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
							<?php
							$helptxt = stripslashes( wpuf_get_option( 'cat_help' ) );
							if ($helptxt) 
								echo '<p class="description">' . $helptxt . '</p>';
							?>
						</li>
					<?php
					}

					$this->publish_date_form();
					$this->expiry_date_form();

					wpuf_build_custom_field_form( 'bottom' );

					do_action( 'wpuf_post_form', 'add', 'submit', $post_type, '' ); 
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

					<?php do_action( 'wpuf_post_form', 'add', 'bottom', $post_type, '' ); ?>
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

		$timezone_format = _x( 'Y-m-d H:i:s', 'timezone date format' );
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
				<input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo date_i18n( 'd' ); ?>" name="jj">,
				<input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="4" size="4" value="<?php echo date_i18n( 'Y' ); ?>" name="aa">
				@ <input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo date_i18n( 'H' ); ?>" name="hh">
				: <input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo date_i18n( 'i' ); ?>" name="mn">
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
		
		$timezone_format = _x( 'Y-m-d H:i:s', 'timezone date format' );
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
			<?php _e( 'Expiration Time:', 'wpuf' ); ?> <span class="required">*</span>
			</label>
			<div class="timestamp-wrap">
				<select name="expiration-mm">
					<?php
					foreach ($month_array as $key => $val) {
						$selected = ( $key == $month ) ? ' selected="selected"' : '';
						echo '<option value="' . $key . '"' . $selected . '>' . $val . '</option>';
					}
					?>
				</select>
				<input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo date_i18n( 'd' ); ?>" name="expiration-jj">,
				<input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="4" size="4" value="<?php echo date_i18n( 'Y' ); ?>" name="expiration-aa">
				@ <input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo date_i18n( 'H' ); ?>" name="expiration-hh">
				: <input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo date_i18n( 'i' ); ?>" name="expiration-mn">
				<input type="checkbox" tabindex="4" value="on" name="expiration-enable">Enable
			</div>
			<div class="clear"></div>
			<p class="description"><?php _e( 'Post expiration time if enabled.', 'wpuf' ); ?></p>
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

		//$message = '<div>REQUEST=' . print_r($_REQUEST, true) . '<br>POST=' . print_r($_POST,true) . '<br>$_SERVER='. print_r($_SERVER,true) . '<br>$userdata=' . print_r($userdata,true) . '</div>'; 
 		//echo '<root><success>false></success><message>' . <htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message></root>'; 


		$title = trim( $_POST['wpuf_post_title'] );
		$content = trim( $_POST['wpuf_post_content'] );
		$excerpt = trim( strip_tags(  $_POST['wpuf_excerpt'] ) );
		$post_type = trim( strip_tags( $_POST['wpuf_post_type'] ) );

		//Set header content type to XML
		header( 'Content-Type: text/xml' );
		
		if ( !wp_verify_nonce( $_REQUEST['_wpnonce'], 'wpuf-add-post' ) ) {
			$message = wpuf_error_msg( __( 'Cheating?' ) );
			//XML message
			echo '<root><success>false</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message></root>';
			exit;
		}
		
		$errors = array();

		//validate title
		if ( empty( $title ) ) {
			$errors[] = __( 'Empty post title', 'wpuf' );
		} else {
			$title = trim( strip_tags( $title ) );
		}

		//validate categories

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

		//validate post date

		$post_date = '';
		
		$post_date_enable = wpuf_get_option( 'enable_post_date' );

		if ( $post_date_enable == 'on' ) {
			$month = $_POST['mm'];
			$day = $_POST['jj'];
			$year = $_POST['aa'];
			$hour = $_POST['hh'];
			$min = $_POST['mn'];

			if ( !checkdate( $month, $day, $year ) ) {
				$errors[] = __( 'Invalid publish date', 'wpuf' );
			}
			else {
				$date = mktime( $hour, $min, 59, $month, $day, $year );
				
				if (!$date) 
					$errors[] = __( 'Invalid publish time', 'wpuf' );
				else
					$post_date = date( 'Y-m-d H:i:s', $date );
			}
		}

		//validate post expiry date

		$post_expiry_date = '';
		
		$post_expiry_enable = wpuf_get_option( 'enable_post_expiry' );
		
		if ( $post_expiry_enable == 'on' && $_POST['expiration-enable'] == 'on') {
			$month = $_POST['expiration-mm'];
			$day = $_POST['expiration-jj'];
			$year = $_POST['expiration-aa'];
			$hour = $_POST['expiration-hh'];
			$min = $_POST['expiration-mn'];

			if ( !checkdate( $month, $day, $year ) ) {
				$errors[] = __( 'Invalid expiry date', 'wpuf' );
			}
			else {
				$post_expiry_date = mktime( $hour, $min, 59, $month, $day, $year );
				
				if (!$post_expiry_date) 
					$errors[] = __( 'Invalid expiry time', 'wpuf' );
			}
		}
		
		$errors = apply_filters( 'wpuf_add_post_validation', $errors );

		//if not any errors, proceed
		if ( $errors ) {
			$message = wpuf_error_msgs( $errors );
			//XML message
			echo '<root><success>false</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message></root>';
			exit;
		}

		//process tags

		$tags = '';
		
		if ( isset( $_POST['wpuf_post_tags'] ) ) {
			$tags = wpuf_clean_tags( $_POST['wpuf_post_tags'] );
		}
		
		if ( !empty( $tags ) ) {
			$tags = explode( ',', $tags );
		}
		
		//Set category to default if users aren't allowed to choose category
		if ( wpuf_get_option( 'allow_cats' ) == 'on' ) {
			$post_category = $_POST['category'];
		} else {
			$post_category = array(wpuf_get_option( 'default_cat' ));
		}

		//Set post status
		$post_stat = wpuf_get_option( 'post_status' );
		
		//Set author according to 'post author' option.
		//If user not logged in map user to 'map_author' option
		if (!$this->logged_in  && wpuf_get_option( 'post_author' ) == 'original') {
			$post_author = $userdata->ID;
		} else {
			$post_author = wpuf_get_option( 'map_author' );
		}
		
		$my_post = array(
			'post_title' => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status' => $post_stat,
			'post_author' => $post_author,
			'post_category' => $post_category,
			'post_type' => $post_type,
			'tags_input' => $tags
		);

		if ( $post_date_enable == 'on' ) {
			$my_post['post_date'] = $post_date ;
		}

		//plugin API to extend the functionality
		$my_post = apply_filters( 'wpuf_add_post_args', $my_post );

		//var_dump( $_POST, $my_post );die();
		
		//insert the post
		$post_id = wp_insert_post( $my_post, true);

		//if insert post ok, proceed
		if (is_wp_error($post_id)) {
			$message = wpuf_error_msg( __( 'Post insert failed. ', 'wpuf' ) . $post_id->get_error_message());

			//XML message
			echo '<root><success>false</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message></root>';
			 exit;
		}	 
		
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

		//set post expiration date
		if ( $post_expiry_enable == 'on' ) {
			add_post_meta( $post_id, 'expiration-date', $post_expiry_date, true);
		}

		//Attach featured image file to post  
		//$_POST['wpuf_featured_img']
		WPUF_Featured_Image::attach_file_to_post( $post_id );	
		
		//Attach attachment info  
		//$_POST['wpuf_attach_id'][] 
		//$_POST['wpuf_attach_title'][]
		WPUF_Attachment::attach_file_to_post( $post_id );	
		
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

		$redirect_url = apply_filters( 'wpuf_post_redirect', 'add', 'insert', $redirect_url, $post_id );
		
		$post_status = get_post_status($post_id);

		switch ($post_status) {
			case "publish":
				$message = '<div class="wpuf-success">' . __('Post published successfully', 'wpuf') . '</div>';
				echo '<root><success>true</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
				break;
			case "pending":
				$message = '<div class="wpuf-success">' . __('Post pending review', 'wpuf') . '</div>';
				echo '<root><success>true</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
				break;
			case "draft":
				$message = '<div class="wpuf-success">' . __('Post saved as draft', 'wpuf') . '</div>';
				echo '<root><success>true</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
				break;
			case "future":
				$message = '<div class="wpuf-success">' . __('Post to be published in future', 'wpuf') . '</div>';
				echo '<root><success>true</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
				break;
			default:
				$message = '<div class="wpuf-success">' . __('Post status', 'wpuf') . ': ' . $post_status . '</div></message>';
				echo '<root><success>true</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
		}
		
		exit;
	}

}

$wpuf_postform = new WPUF_Add_Post();

