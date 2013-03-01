<?php

/**
 * Edit User class
 *
 * @author Tareq Hasan 
 * @package WP User Frontend
 * @version 1.1-fork-2RRR-4.3 
 */

/*
== Changelog ==

= 1.1-fork-2RRR-4.3 professor99 =
* Fixed permalink references
* Upgraded security. Rearranged to suit.
* Suppress "edit_post_link" on this page

= 1.1-fork-2RRR-4.0 professor99 =
* Bugfix: Changed wpuf_user_edit_profile_form() to show_form()

= 1.1-fork-2RRR-2.0 professor99 =
* Added wpuf prefix to some class names
*/

require_once(ABSPATH . '/wp-admin/includes/user.php');

function wpuf_edit_users() {
    //Suppress "edit_post_link" on this page
    add_filter( 'edit_post_link', 'wpuf_suppress_edit_post_link', 10, 2 ); 

    ob_start();

    //if user is logged in
    if ( is_user_logged_in() ) {
        //this user can edit the users
        if ( current_user_can( 'edit_users' ) ) {
            $action = isset( $_GET['action'] ) ? $_GET['action'] : 'show';
            $user_id = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;

            switch ($action) {
                case 'edit':
                    wpuf_edit_user( $user_id );
                    break;

                case 'delete':
                    wpuf_delete_user( $user_id );
					wpuf_show_users();
                    break;

                case 'add':
                    wpuf_add_user();
                    break;

                default: 
                    wpuf_show_users();
            }
        } else { // user don't have any permission
            printf( __( "You don't have permission for this purpose", 'wpuf' ) );
        }
    } else { //user is not logged in
        printf( __( "This page is restricted. Please %s to view this page.", 'wpuf' ), wp_loginout( '', false ) );
    }

    return ob_get_clean();
}

add_shortcode( 'wpuf-edit-users', 'wpuf_edit_users' );

function wpuf_show_users() {
    global $wpdb;

    $sql = "SELECT ID, display_name FROM $wpdb->users ORDER BY user_registered ASC";
    $users = $wpdb->get_results( $sql );
	$self = get_permalink();

    $add_url = add_query_arg( 'action', 'add', $self );
    $edit_url = add_query_arg( array( 'action' => 'edit', 'user_id' => $user->ID ), $self );
    $delete_url = add_query_arg( array( 'action' => 'delete', 'user_id' => $user->ID ), $self );
		
    if ( current_user_can( 'create_users' ) ) {	
?>
        <a class="wpuf-button" href="<?php echo wp_nonce_url( $add_url, 'wpuf_add_user' ); ?>"><?php _e( 'Add New User', 'wpuf' ); ?></a>
    <?php 
	}

    if ( $users ) {
	?>
        <table class="wpuf-table" cellpadding="0" cellspacing="0">
            <tr>
                <th><?php _e( 'Username', 'wpuf' ); ?></th>
                <th><?php _e( 'Action', 'wpuf' ); ?></th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><a href="<?php echo get_author_posts_url( $user->ID ); ?>"><?php printf( esc_attr__( '%s', 'wpuf' ), $user->display_name ); ?></td>
                    <td>
				
                        <a href="<?php echo wp_nonce_url( $edit_url, 'wpuf_edit_user' ); ?>"><?php _e( 'Edit', 'wpuf' ); ?></a>
                        <?php if ( current_user_can( 'delete_users' ) ) : ?>
                            <a href="<?php echo wp_nonce_url( $delete_url, 'wpuf_delete_user' ); ?>" onclick="return confirm('Are you sure to delete this user?');"><span style="color: red;"><?php _e( 'Delete', 'wpuf' ); ?></span></a>
                        <?php endif; ?>
                    </td>
                </tr>

            <?php endforeach; ?>
        </table>
<?php 
	}
}

function wpuf_edit_user( $user_id ) {
    global $edit_profile;

    $nonce = $_REQUEST['_wpnonce'];

    if ( !wp_verify_nonce( $nonce, 'wpuf_edit_user' ) )
        wp_die( 'Cheating?' );
		
    //if user exists show edit profile form
     if ( $user_id && ( get_userdata( $user_id ) !== false ) ) {
        $edit_profile->show_form( $user_id );
    } else {
		echo '<div class="wpuf-error">' .  __( "User doesn't exists", 'wpuf' ) . '</div>';
    }
}

function wpuf_delete_user( $user_id ) {
    global $userdata;

    $nonce = $_REQUEST['_wpnonce'];

    if ( !wp_verify_nonce( $nonce, 'wpuf_delete_user' ) )
        wp_die( 'Cheating?' );

    if ( !current_user_can( 'delete_users' ) ) 
        wp_die( 'Cheating?' );

    //get current users ID
    $cur_user = $userdata->ID;

    //user can't delete himself and not the admin, whose id is 1
    if ( $cur_user == $user_id && $user_id == 1 ) {
        echo '<div class="wpuf-error">' . __('Cannot delete self or admin') . '</div>';
        return;
    }	

     if ( !$user_id || ( get_userdata( $user_id ) === false ) ) {
 		echo '<div class="wpuf-error">' .  __( "User doesn't exists", 'wpuf' ) . '</div>';
        return;
    }

    //delete the user
    wp_delete_user( $user_id );

    echo '<div class="wpuf-success">' . __( 'User Deleted', 'wpuf' ) . '</div>';
}

function wpuf_add_user() {
    global $wp_error;
    //get admin template file. wp_dropdown_role is there :(
    require_once(ABSPATH . '/wp-admin/includes/template.php');

    $nonce = $_REQUEST['_wpnonce'];

    if ( !wp_verify_nonce( $nonce, 'wpuf_add_user' ) )
        wp_die( 'Cheating?' );

    if ( current_user_can( 'create_users' ) ) : 
    ?>
        <h3><?php _e( 'Add New User', 'wpuf' ); ?></h3>

        <?php
        if ( isset( $_POST['wpuf_new_user_submit'] ) ) {
            $errors = array();

            $username = sanitize_user( $_POST['user_login'] );
            $email = trim( $_POST['user_email'] );
            $role = $_POST['role'];

            $error = null;
            $error = wpuf_register_new_user( $username, $email, $role );
            if ( !is_wp_error( $error ) ) {
                echo '<div class="wpuf-success">' . __( 'User Added', 'wpuf' ) . '</div>';
            } else {
                echo '<div class="wpuf-error">' . $error->get_error_message() . '</div>';
            }
        }
        ?>

        <form action="" method="post">

            <ul class="wpuf-post-form">
                <li>
                    <label for="user_login">
                        <?php _e( 'Username', 'wpuf' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="user_login" id="user_login" minlength="2" value="<?php if ( isset( $_POST['user_login'] ) ) echo wpuf_clean_tags( $_POST['user_login'] ); ?>">
                    <div class="clear"></div>
                </li>

                <li>
                    <label for="user_email">
                        <?php _e( 'Email', 'wpuf' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="user_email" id="user_email" minlength="2" value="<?php if ( isset( $_POST['user_email'] ) ) echo wpuf_clean_tags( $_POST['user_email'] ); ?>">
                    <div class="clear"></div>
                </li>

                <li>
                    <label for="role">
                        <?php _e( 'Role', 'wpuf' ); ?>
                    </label>

                    <select name="role" id="role">
                        <?php
                        if ( !$new_user_role ) {
                            $new_user_role = !empty( $current_role ) ? $current_role : get_option( 'default_role' );
                        }
                        wp_dropdown_roles( $new_user_role );
                        ?>
                    </select>

                    <div class="clear"></div>
                </li>

                <li>
                    <label>&nbsp;</label>
                    <input class="wpuf_submit" type="submit" name="wpuf_new_user_submit" value="<?php echo esc_attr( __( 'Add New User', 'wpuf' ) ); ?>">
                </li>

            </ul>

        </form>

    <?php endif; ?>

    <?php
}

/**
 * Handles registering a new user.
 *
 * @param string $user_login User's username for logging in
 * @param string $user_email User's email address to send password and add
 * @return int|WP_Error Either user's ID or error on failure.
 */
function wpuf_register_new_user( $user_login, $user_email, $role ) {
    $errors = new WP_Error();

    $sanitized_user_login = sanitize_user( $user_login );
    $user_email = apply_filters( 'user_registration_email', $user_email );

    // Check the username
    if ( $sanitized_user_login == '' ) {
        $errors->add( 'empty_username', __( '<strong>ERROR</strong>: Please enter a username.' ) );
    } elseif ( !validate_username( $user_login ) ) {
        $errors->add( 'invalid_username', __( '<strong>ERROR</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.' ) );
        $sanitized_user_login = '';
    } elseif ( username_exists( $sanitized_user_login ) ) {
        $errors->add( 'username_exists', __( '<strong>ERROR</strong>: This username is already registered, please choose another one.' ) );
    }

    // Check the e-mail address
    if ( $user_email == '' ) {
        $errors->add( 'empty_email', __( '<strong>ERROR</strong>: Please type your e-mail address.' ) );
    } elseif ( !is_email( $user_email ) ) {
        $errors->add( 'invalid_email', __( '<strong>ERROR</strong>: The email address isn&#8217;t correct.' ) );
        $user_email = '';
    } elseif ( email_exists( $user_email ) ) {
        $errors->add( 'email_exists', __( '<strong>ERROR</strong>: This email is already registered, please choose another one.' ) );
    }

    do_action( 'register_post', $sanitized_user_login, $user_email, $errors );

    $errors = apply_filters( 'registration_errors', $errors, $sanitized_user_login, $user_email );

    if ( $errors->get_error_code() )
        return $errors;

    $user_pass = wp_generate_password( 12, false );
    //$user_id = wp_create_user( $sanitized_user_login, $user_pass, $user_email );

    $userdata = array(
        'user_login' => $sanitized_user_login,
        'user_email' => $user_email,
        'user_pas' => $user_pass,
        'role' => $role
    );

    $user_id = wp_insert_user( $userdata );

    if ( !$user_id ) {
        $errors->add( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !' ), get_option( 'admin_email' ) ) );
        return $errors;
    }

    update_user_option( $user_id, 'default_password_nag', true, true ); //Set up the Password change nag.

    wp_new_user_notification( $user_id, $user_pass );

    return $user_id;
}


