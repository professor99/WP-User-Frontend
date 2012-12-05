<?php

/**
 * Ajax class
 *
 * @author Tareq Hasan 
 * @package WP User Frontend
 * @version 1.1-fork-2RRR-3.0 
 */

/*
== Changelog ==

= 1.1-fork-2RRR-3.0 professor99 =
* Moved featured image functions to lib/featured_image.php
*/

class WPUF_Ajax {

    function __construct() {
        add_action( 'wp_ajax_nopriv_wpuf_get_child_cats', array($this, 'get_child_cats') );
        add_action( 'wp_ajax_wpuf_get_child_cats', array($this, 'get_child_cats') );
    }

    /**
     * Returns child category dropdown on ajax request
     */
    function get_child_cats() {
        $parentCat = $_POST['catID'];
        $result = '';
        if ( $parentCat < 1 )
            die( $result );

        if ( get_categories( 'taxonomy=category&child_of=' . $parentCat . '&hide_empty=0' ) ) {
            $result .= wp_dropdown_categories( 'show_option_none=' . __( '-- Select --', 'wpuf' ) . '&class=dropdownlist&orderby=name&name=category[]&id=cat-ajax&order=ASC&hide_empty=0&hierarchical=1&taxonomy=category&depth=1&echo=0&child_of=' . $parentCat );
        } else {
            die( '' );
        }

        die( $result );
    }
}

$wpuf_ajax = new WPUF_Ajax();