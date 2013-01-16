<?php

/**
 * Edit Post form class
 *
 * @author Tareq Hasan 
 * @package WP User Frontend
 * @version 1.1-fork-2RRR-4.1
 */
 
/*
== Changelog ==

= 1.1-fork-2RRR-4.1 professor99 =
* Implemented Post Format field.

= 1.1-fork-2RRR-4.0 professor99 =
* Implemented "enable_post_edit" default option.
* Implemented "enable_post_del" default option.
* Better language support for info div
* Enhanced security
* Added $post_id parameter to wpuf_can_edit filter.
* Added wpuf_can_delete filter
* Fixed Description alignment for all users

= 1.1-fork-2RRR-3.0 professor99 =
* Added excerpt
* Added publish date
* Added expiration time
* Re-styled form to suit
* Made attachment calls inline (was actions)
* Featured image html moved to WPUF_Featured_Image::add_post_fields()
* Removed attachment code replaced by ajax
* Fixed custom fields update bug
* Can display top line info message anytime
* Removed 'Your theme doesn't support featured image' message
* Fixed javascript success clear form bug
* Added optional delete button
* Redirects now filtered by wpuf_post_redirect 
* Form actions consolidated under wpuf_post_form
* Escaped info message XML tags
* Added wpuf_error_msgs();

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
 * 	redirect: none | auto | current | last 
 * 		none: do nothing
 * 		auto: If close==true will load last page on post. 
 *		      Else will reload current page on post. (default)
 * 		current: will reload current page on post
 * 		last: will load last page on post 
 */

/* Notes
 *
 * The action 'wpuf_post_form' is common to both this file and wpuf_add_post.php.
 * It is invoked as function($form, $location, $post_type, $post).
 * For this file $form = 'edit'.
 *
 * The filter 'wpuf_post_redirect' is common to both this file and wpuf_add_post.php.
 * It is invoked as function($form, $location, $redirect_url, $post_id).
 * For this file $form = 'edit'.
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
	var $logged_in = false;

	function __construct() {
		//Ajax calls for Update Post button
		add_action('wp_ajax_wpuf_edit_post_action', array($this, 'submit_post'));
		add_action('wp_ajax_nopriv_wpuf_edit_post_action', array($this, 'submit_post'));

		//Ajax calls for Delete Post button
		add_action('wp_ajax_wpuf_delete_post_action', array($this, 'delete_post'));
		add_action('wp_ajax_nopriv_wpuf_delete_post_action', array($this, 'delete_post'));
		
		add_shortcode( 'wpuf_edit', array($this, 'shortcode') );
	}

	/**
	 * Queue up jQuery, jQuery-form, and wpuf-edit-post javascript for header
	 *
	 * @since 1.1-fork-2RRR-2.0 
	 */
	function enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-form' );
		wp_enqueue_script( 'wpuf_edit_post', plugins_url( 'js/wpuf-edit-post.js',  __FILE__ ) );
	}	

	/**
	* Delete a post
	*/
	function delete_post() {
		//Set header content type to XML
		header( 'Content-Type: text/xml' );

		$post_id = trim( $_POST['post_id'] );
		
		if ( !wp_verify_nonce( $_POST['nonce'], 'wpuf-delete-post' . $post_id  ) ) {
			$message = wpuf_error_msg( __( 'Cheating?' ) );
			echo '<root><success>false</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message></root>';
			exit;
		}

		//Delete post
		wp_delete_post( $post_id );
		
		//Set after delete redirect
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
			default:
				$redirect_url = "";
		}
		
		//Use this filter if you want to change the return address on delete
		$redirect_url = apply_filters( 'wpuf_post_redirect', 'edit', 'delete', $redirect_url, $post_id );

		$message = wpuf_error_msg( __('Post deleted', 'wpuf') );
		echo '<root><success>true</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
		
		exit;
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

		//Add javascript files
		$this->enqueue_scripts();
		
		extract( shortcode_atts( array('close' => 'true', 'redirect' => 'auto'), $atts ) );

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
		
		//Get updated post
		$post_id = isset( $_GET['pid'] ) ? intval( $_GET['pid'] ) : 0;
		$curpost = get_post( $post_id );

		$invalid = false;

		$can_edit = 'yes';
		$info = ''; 

		$enable_post_edit = wpuf_get_option( 'enable_post_edit', 'default' );
		
		if ( !$curpost ) {
			$invalid = true;
			$can_edit = 'no';
			$info = 'Invalid post';
		}
		else if ( $enable_post_edit == 'no' ) {
			$can_edit = 'no';
			$info = 'Post Editing is disabled';
		} 
		else if (!$this->logged_in) {
			//Get login page url			
			if ($close == 'false') {
				$login_url = 'http://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']);			
			} else if (isset($_SERVER['QUERY_STRING'])) {
				$login_url = 'http://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']) . '&wpuf_referer=' . $this->wpuf_referer;
			} else {
				$login_url = 'http://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']) . '?wpuf_referer=' . $this->wpuf_referer;
			}

			$can_edit = 'no';						
			$info = 'restricted';
		} 
		else if ( !current_user_can( 'edit_post', $post_id ) ) {
			if ( $enable_post_edit != 'yes' || $userdata->ID != $curpost->post_author ) {
				$can_edit = 'no';
				$info = 'You are not allowed to edit this post';
			}
		}

		if (!$invalid) {
			$can_edit = apply_filters( 'wpuf_can_edit', $can_edit, $post_id );
			$info = apply_filters( 'wpuf_editpost_notice', $info );
		}

		if ($info) {
			if ( $info == 'restricted' )
				$info = sprintf(__( "This page is restricted. Please %s to view this page.", 'wpuf' ), wp_loginout($login_url, false ) );
			else
				$info = __( $info, 'wpuf' );
				
			echo '<div class="wpuf-info">' . $info . '</div>';
		}	

		if ( $can_edit == 'yes' ) {
			//show post form
			$this->edit_form( $curpost, $close, $redirect );
			
			if ( wpuf_get_option( 'enable_delete_button' ) == 'yes' ) {
				$can_delete = false;
				
				$enable_post_del = wpuf_get_option( 'enable_post_del', 'default' );
				
				if ( $enable_post_del != 'no' && $this->logged_in ) {
					if ( current_user_can( 'delete_post', $post_id ) ) {
						$can_delete = true;
					} else if ( $enable_post_del == 'yes' && $userdata->ID == $curpost->post_author ) {
						$can_delete = true;
					}	
				}
				
				$can_delete = apply_filters( 'wpuf_can_delete', $can_delete, $post_id );
		
				if ( $can_delete ) {
					$nonce = wp_create_nonce( 'wpuf-delete-post' . $post_id );
					$onclick = "wpuf_delete_post( $post_id, \"$redirect\", \"$close\", \"$this->wpuf_referer\", \"$this->wpuf_self\", \"$nonce\" );return false;";
					$delete_label = esc_attr( wpuf_get_option( 'delete_label' ) );
					echo '<div id="wpuf-button-delete"><button class="wpuf-button" type="button" onclick=\'' . $onclick . '\'>' . $delete_label . '</button></div>';
				}	
			} 
		}

		//Use this filter if you want to change the return address on close
		$redirect_url = apply_filters( 'wpuf_post_redirect', 'edit', 'close', $this->wpuf_referer, $post_id);
		
		if ($redirect_url != "" && $close == "true") {
			echo '<div id="wpuf-button-close"><a class="wpuf-button" href="' . $redirect_url . '">' . esc_attr( wpuf_get_option( 'close_label' ) ) . '</a></div>';
		}
		
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * Main edit post form
	 *
	 * @param string $curpost current post
	 * @param string $close Display Close Button "true"|"false"
	 * @param string $redirect Redirect after post "auto"|"current"|"last"|"new"
	 * @return string html
	 * @global wpdb $wpdb
	 */
	function edit_form( $curpost, $close, $redirect ) {
		global $wpdb;
		
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
				<?php wp_nonce_field( 'wpuf-edit-post' . $curpost->ID ) ?>
				<ul class="wpuf-post-form">

					<?php 
					do_action( 'wpuf_post_form', 'edit', 'top', $curpost->post_type, $curpost ); 
					wpuf_build_custom_field_form( 'top', true, $curpost->ID );

					if ( $featured_image == 'yes' && current_theme_supports( 'post-thumbnails' ) ) {
						WPUF_Featured_Image::add_post_fields( $curpost->post_type, $curpost );
					}	
					?>
					
					<li>
						<label for="new-post-title">
							<?php echo wpuf_get_option( 'title_label' ); ?> <span class="required">*</span>
						</label>
						<input class="requiredField" type="text" name="wpuf_post_title" id="new-post-title" minlength="2" value="<?php echo esc_html( $curpost->post_title ); ?>">
						<div class="clear"></div>
						<?php
						$helptxt = stripslashes( wpuf_get_option( 'title_help' ) );
						if ($helptxt) 
							echo '<p class="description">' . $helptxt . '</p>';
						?>
					</li>
											
					<?php
					do_action( 'wpuf_post_form', 'edit', 'description', $curpost->post_type, $curpost ); 
					wpuf_build_custom_field_form( 'description', true, $curpost->ID );

					?>
					
					<li>
						<label for="new-post-desc">
							<?php echo wpuf_get_option( 'desc_label' ); ?> <span class="required">*</span>
						</label>
						<div class="clear"></div>
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
						<?php
						$helptxt = stripslashes( wpuf_get_option( 'desc_help' ) );
						if ($helptxt) 
							echo '<p class="description-left">' . $helptxt . '</p>';
						?>
					</li>

					<?php 
					do_action( 'wpuf_post_form', 'edit', 'after_description', $curpost->post_type, $curpost ); 
					wpuf_build_custom_field_form( 'after_description', true, $curpost->ID );

					if ( wpuf_get_option( 'allow_excerpt' ) == 'on' ) {
						$max_chars = wpuf_get_option( 'excerpt_max_chars' );

						//Get excerpt
						$query = "SELECT post_excerpt FROM $wpdb->posts WHERE ID=$curpost->ID LIMIT 1";
						$result = $wpdb->get_results($query, ARRAY_A);
						$excerpt = $result[0]['post_excerpt'];						
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
									<textarea class="requiredField" id="wpuf-excerpt" name="wpuf_excerpt" cols="80" rows="2" <?php echo $maxlength ?> ><?php echo $excerpt; ?></textarea>
								<?php
								} else {
								?>
									<textarea id="wpuf-excerpt" name="wpuf_excerpt" cols="80" rows="2" <?php echo $maxlength ?> ><?php echo $excerpt; ?></textarea>
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
							<input type="text" name="wpuf_post_tags" id="new-post-tags" value="<?php echo $tagslist; ?>">
							<div class="clear"></div>
							<?php
							$helptxt = stripslashes( wpuf_get_option( 'tag_help' ) );
							if ($helptxt) 
								echo '<p class="description">' . $helptxt . '</p>';
							?>
						</li>
					<?php 
					}

					do_action( 'wpuf_post_form', 'edit', 'tag', $curpost->post_type, $curpost ); 
					wpuf_build_custom_field_form( 'tag', true, $curpost->ID ); 
					
					//Add attachment fields if enabled
					
					$allow_upload = wpuf_get_option( 'allow_attachment' );
					
					if ( $allow_upload == 'yes' ) {
						WPUF_Attachment::add_post_fields( $post_type, $curpost );
					}

					//Add Post Format field if enabled and is supported by current theme and post type

					if ( wpuf_get_option( 'allow_format' ) == 'on' && current_theme_supports( 'post-formats' ) && post_type_supports( $curpost->post_type, 'post-formats' ) ) {
						$post_formats = get_theme_support( 'post-formats' );
						
						if ( is_array( $post_formats[0] ) ) {
							$post_format = get_post_format( $curpost->ID );

							if ( !$post_format ) {
								$post_format = '0';
							} else if ( !in_array( $post_format, $post_formats[0] ) ) {
								// Add the format to the post format array if it isn't there.
								$post_formats[0][] = post_format;
							}	
					?>
							<li>
								<label for="new-post-format">
									<?php echo wpuf_get_option( 'format_label' ); ?>
								</label>

								<select name="wpuf_post_format"  id="new-post-format">
									<option <?php selected( $post_format, '0' ); ?> value="0" > <?php _e('Standard'); ?></option>
									<?php 
									foreach ( $post_formats[0] as $format ) { 
									?>
										<option <?php selected( $post_format, $format ); ?> value="<?php echo esc_attr( $format ); ?>" > <?php echo esc_html( get_post_format_string( $format ) ); ?></option>
									<?php 
									}
									?>
								</select>
								<div class="clear"></div>
								<?php
								$helptxt = stripslashes( wpuf_get_option( 'format_help' ) );
								if ($helptxt) 
									echo '<p class="description">' . $helptxt . '</p>';
								?>
							</li>
					<?php
						}
					}

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
							<?php
							$helptxt = stripslashes( wpuf_get_option( 'cat_help' ) );
							if ($helptxt) 
								echo '<p class="description">' . $helptxt . '</p>';
							?>
						</li>
					<?php
					}
					
					$this->publish_date_form( $curpost );
					$this->expiry_date_form( $curpost );

					wpuf_build_custom_field_form( 'bottom', true, $curpost->ID ); 
					
					do_action( 'wpuf_post_form', 'edit', 'submit', $curpost->post_type, $curpost ); 
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
						
					<?php do_action( 'wpuf_post_form', 'edit', 'bottom', $curpost->post_type, $curpost ); ?>
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
	function publish_date_form( $curpost ) {
		$enable_date = wpuf_get_option( 'enable_post_date' );

		if ( $enable_date != 'on' ) {
				return;
		}

		$datetime = $curpost->post_date;
		
		sscanf( $datetime, "%4s-%2s-%2s %2s:%2s:%2s", $year, $month, $day, $hour, $minute, $second );

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
				<input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo $day; ?>" name="jj">,
				<input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="4" size="4" value="<?php echo $year; ?>" name="aa">
				@ <input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo $hour; ?>" name="hh">
				: <input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo $minute; ?>" name="mn">
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
	function expiry_date_form( $curpost ) {
		$post_expiry = wpuf_get_option( 'enable_post_expiry' );

		if ( $post_expiry != 'on' ) {
			return;
		}

		$expiration_date = get_post_meta($curpost->ID, 'expiration-date', true);
		
		if ($expiration_date) {
			$checked = 'checked="checked"';
			$datetime = date('Y-m-d H:i:s', $expiration_date);
		} else {
			$checked = '';
			$datetime = $curpost->post_date;
		}

		sscanf( $datetime, "%4s-%2s-%2s %2s:%2s:%2s", $year, $month, $day, $hour, $minute, $second );

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
				<input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo $day; ?>" name="expiration-jj">,
				<input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="4" size="4" value="<?php echo $year; ?>" name="expiration-aa">
				@ <input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo $hour; ?>" name="expiration-hh">
				: <input class="requiredField" type="text" autocomplete="off" tabindex="4" maxlength="2" size="2" value="<?php echo $minute; ?>" name="expiration-mn">
				<input type="checkbox" tabindex="4" value="on" <?php echo $checked ?> name="expiration-enable">Enable
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

		//$message = '<div>REQUEST=' . print_r($_REQUEST, true) . '<br>POST=' . print_r($_POST,true) . '<br>$_SERVER='. print_r($_SERVER,true) . '<br>$userdata=' . print_r($userdata,true) . '</div>' ;
 		//echo '<root><success>false></success><message>' . <htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message></root>'; 

		$post_id = trim( $_POST['post_id'] );
		$title = trim( $_POST['wpuf_post_title'] );
		$content = trim( $_POST['wpuf_post_content'] );
		$excerpt = trim( strip_tags(  $_POST['wpuf_excerpt'] ) );

		//Set header content type to XML
		header( 'Content-Type: text/xml' );

		if ( !wp_verify_nonce( $_REQUEST['_wpnonce'], 'wpuf-edit-post' . $post_id) ) {
			$message = wpuf_error_msg( __( 'Cheating?' ) );
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

		$errors = apply_filters( 'wpuf_edit_post_validation', $errors );

		//if not any errors, proceed
		if ( $errors ) {
			$message = wpuf_error_msgs( $errors );
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
			$post_category = array(get_option( 'wpuf_default_cat' ));
		}

		$post_update = array(
			'ID' => $post_id,
			'post_title' => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_category' => $post_category,
			'tags_input' => $tags
		);
		
		if ( $post_date_enable == 'on' ) {
			$post_update['post_date'] = $post_date;
		}

		//plugin API to extend the functionality
		$post_update = apply_filters( 'wpuf_edit_post_args', $post_update );

		$post_id = wp_update_post( $post_update );

		if ( !$post_id ) {
			$message = wpuf_error_msg(  __( 'Post update failed.', 'wpuf' ) );
			echo '<root><success>false</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message></root>';
			exit;
		}

		//add the custom fields
		if ( $custom_fields ) {
			foreach ($custom_fields as $key => $val) {
				update_post_meta( $post_id, $key, $val );
			}
		}
		
		//set post format
		if ( isset( $_POST['wpuf_post_format'] ) )
			set_post_format( $post_id, $_POST['wpuf_post_format'] );
		

		//set post expiration date
		if ( $post_expiry_enable == 'on' ) {
			update_post_meta( $post_id, 'expiration-date', $post_expiry_date);
		}

		//Attach featured image file to post  
		//$_POST['wpuf_featured_img']
		WPUF_Featured_Image::attach_file_to_post( $post_id );	
				
		//Attach attachment info  
		//$_POST['wpuf_attach_id'][] 
		//$_POST['wpuf_attach_title'][]
		WPUF_Attachment::attach_file_to_post( $post_id );	
		
		//plugin API to extend the functionality
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
			default:
				$redirect_url = "";
		}
		
		//Use this filter if you want to change the return address on update
		$redirect_url = apply_filters( 'wpuf_post_redirect', 'edit', 'update', $redirect_url, $post_id );

		$message = '<div class="wpuf-success">' . __('Post updated succesfully', 'wpuf') . '</div>';
		
		echo '<root><success>true</success><message>' . htmlspecialchars($message, ENT_QUOTES, "UTF-8") . '</message><post_id>' . $post_id . '</post_id><redirect_url>' . $redirect_url . '</redirect_url></root>';
		exit;
	}

}

$wpuf_edit = new WPUF_Edit_Post();