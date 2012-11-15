<?php

/**
 * Edit Post form class
 *
 * @author Tareq Hasan 
 * @package WP User Frontend
 *
 * Extensively modified by Andy Bruin of KnockThemDeadProductions for 2RRR. 
 * Version: 1.1 fork: 2RRR 1.0 alpha
 *
 * Main Changes from Version 1.1:
 *
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
 * 
 *  Shortcode options:
 * 
 * 	close: true | false 
 * 		true: will display close button and redirect to last page on close (default)
 * 		false: 
 */

class WPUF_Edit_Post {
	var $wpuf_referer = '';

    function __construct() {
        add_shortcode( 'wpuf_edit', array($this, 'shortcode') );
    }

    /**
     * Handles the edit post shortcode
     *
	 * @param $atts
     * @return string generated form by the plugin
     * @global type $userdata
     */
    function shortcode( $atts ) {
		global $userdata;

		//echo '<div>REQUEST=' . print_r($_REQUEST, true) . '<br>POST=' . print_r($_POST,true) . '<br>$_GET=' . print_r($_GET,true) . '<br>$_SERVER='. print_r($_SERVER,true) . '<br>$userdata=' . print_r($userdata,true) . '</div>'; 
		
		extract( shortcode_atts( array('close' => 'true'), $atts ) );
		
        ob_start();

		//Set referer URL. 
		//NB Stop XSS attacks by using htmlspecialchars.
		if ( isset( $_POST['wpuf_edit_post_submit'] ) ) {
			$this->wpuf_referer = htmlspecialchars($_POST['wpuf_referer']);
		} else if (isset( $_GET['wpuf_referer'] ) ) {
			$this->wpuf_referer = htmlspecialchars($_GET['wpuf_referer']);
		} else if ($close == "true") {
			$this->wpuf_referer = htmlspecialchars($_SERVER['HTTP_REFERER']);
		} else {
			$this->wpuf_referer = "http://" . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']);
		}

		//Use this filter if you want to change the return address on Close or Post
		$this->wpuf_referer = apply_filters( 'wpuf_edit_post_referer_filter', $this->wpuf_referer, $post_type );

        //perform delete attachment action. NB Is this used???
        if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == "del" ) {
            check_admin_referer( 'wpuf_attach_del' );
            $attach_id = intval( $_REQUEST['attach_id'] );

            if ( $attach_id ) {
                wp_delete_attachment( $attach_id );
            }
        }

		// If POST and nonce matches submit post
		if ( isset( $_POST['wpuf_edit_post_submit'] ) ) {
			$nonce = $_REQUEST['_wpnonce'];
			
			if ( !wp_verify_nonce( $nonce, 'wpuf-edit-post' ) ) {
				wp_die( __( 'Cheating?' ) );
			}

			$this->submit_post();
		}

		//Get updated post
		$post_id = isset( $_GET['pid'] ) ? intval( $_GET['pid'] ) : 0;
 		$curpost = get_post( $post_id );

 		$invalid = false;
		
 		if ( !$curpost ) {
			$invalid = true;
			$can_post = 'no';
 			$info = __( 'Invalid post', 'wpuf' );
		}
        else if ( wpuf_get_option( 'enable_post_edit', 'yes' ) != 'yes' ) {
			$can_post = 'no';
			$info = __( 'Post Editing is disabled', 'wpuf' );
		} 
		else if (!is_user_logged_in() ) {
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
		else if (!current_user_can( 'edit_others_posts' ) && current_user_can( 'edit_posts' ) && $userdata->ID != $curpost->post_author ) {
			$can_post = 'no';
			$info = __( "You are not allowed to edit", 'wpuf' );
		}
		else {
			$can_post = 'yes';
			$info = __( "Can't post.", 'wpuf' ); //default message
		}

		if (!$invalid) {
			//If you use this filter to allow non logged in users make sure use a Catcha or similar.
			$can_post = apply_filters( 'wpuf_can_post', $can_post );

			$info = apply_filters( 'wpuf_addpost_notice', $info );
		}
		
		if ( $can_post == 'yes' ) {
			//show post form
			$this->edit_form( $curpost );
		}
		else {
			echo '<div class="info">' . $info . '</div>';
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
    function edit_form( $curpost ) {
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
                            <li>
                                <label for="post-thumbnail"><?php echo wpuf_get_option( 'ft_image_label' ); ?></label>
                                <div id="wpuf-ft-upload-container">
                                    <div id="wpuf-ft-upload-filelist">
                                        <?php
                                        $style = '';
                                        if ( has_post_thumbnail( $curpost->ID ) ) {
                                            $style = ' style="display:none"';

                                            $post_thumbnail_id = get_post_thumbnail_id( $curpost->ID );
                                            echo wpuf_feat_img_html( $post_thumbnail_id );
                                        }
                                        ?>
                                    </div>
                                    <a id="wpuf-ft-upload-pickfiles" class="button" href="#"><?php echo wpuf_get_option( 'ft_image_btn_label' ); ?></a>
                                </div>
                                <div class="clear"></div>
                            </li>
                        <?php } else { ?>
                            <div class="info"><?php _e( 'Your theme doesn\'t support featured image', 'wpuf' ) ?></div>
                        <?php
						}
                    }	?>

                    <li>
                        <label for="new-post-title">
                            <?php echo wpuf_get_option( 'title_label' ); ?> <span class="required">*</span>
                        </label>
                        <input type="text" name="wpuf_post_title" id="new-post-title" minlength="2" value="<?php echo esc_html( $curpost->post_title ); ?>">
                        <div class="clear"></div>
                        <p class="description"><?php echo stripslashes( wpuf_get_option( 'title_help' ) ); ?></p>
                    </li>
										
                    <?php
                    $allow_cats = wpuf_get_option( 'allow_cats' ); 
                    $allow_cats = apply_filters( 'wpuf_allow_cats', $allow_cats );
												
                    if ( $allow_cats == 'on' ) {
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
                        } else if ( $editor == 'plain' ) { ?>
                            <div class="clear"></div>
                            <textarea name="wpuf_post_content" class="requiredField editor-plain" id="new-post-desc" cols="60" rows="8"><?php echo esc_textarea( $description ); ?></textarea>
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
                        <input class="wpuf_submit" type="submit" name="wpuf_edit_post_submit" value="<?php echo esc_attr( wpuf_get_option( 'update_label' ) ); ?>">
                        <input type="hidden" name="wpuf_edit_post_submit" value="yes" />
                        <input type="hidden" name="post_id" value="<?php echo $curpost->ID; ?>">
						<input type="hidden" name="wpuf_referer" value="<?php echo $this->wpuf_referer ?>" />
                    </li>
					
					<?php do_action( 'wpuf_edit_post_form_bottom', $post_type ); ?>

                </ul>
            </form>
        </div>
<?php
    }

    function submit_post() {
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

        //validate cat
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
			echo wpuf_error_msg( $errors );
			return;
		}

        //users are allowed to choose category
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
			echo wpuf_error_msg(  __( 'Post update failed.', 'wpuf' ) );
			return;
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

        echo '<div class="success">' . __( 'Post updated succesfully.', 'wpuf' ) . '</div>';

		//$redirect = apply_filters( 'wpuf_after_update_redirect', get_permalink( $post_id ), $post_id );
		$redirect = apply_filters( 'wpuf_after_update_redirect', $this->wpuf_referer, $post_id );

		if ($redirect) {
			wp_redirect( $redirect );
			exit;
		}        
    }

}

$wpuf_edit = new WPUF_Edit_Post();