README 2RRR 1.0 For WP User Frontend Version: 1.1 fork: 2RRR 1.0 alpha
======================================================================

Introduction
------------

This is a fork from WP User Frontend Version 1.1. 
It currently only applies to the Add Post and Edit Post functionality on WP User Frontend.
Use of AjaxForm for Ajax style posts is the next step in this project.
It focuses on both useability and ease of customerization.
There are some bug fixes included as well.

Some of these changes are outlined by the following items on the WP User Frontend support forum.

http://wordpress.org/support/topic/custom-editors
http://wordpress.org/support/topic/plugin-wp-user-frontend-redirecting-after-posting
http://wordpress.org/support/topic/allow-to-choose-category-filter
http://wordpress.org/support/topic/close-button-and-return-on-post
http://wordpress.org/support/topic/security-problem-doesnt-observe-user-capabilities

Changes from WP User Frontend Version 1.1
-----------------------------------------

 * Main Changes from Version 1.1:

 * Custom editor option added.
 * Editors use max availiable width.
 * Close button added as shortcut option and redirects set to suit.
 * wpuf_allow_cats filter added.
 * Security checks updated.
 * Code updated to allow use of wpuf_can_post filter for non logged in users.

Status
-------

This code is ALPHA! Use it at you own risk!

It currently has only been tested in the following configuration.

Wordpress 3.4.2
Firefox 16.0.2
IBM PC

This code is a public development fork of WP User Frontend.
It is not written by or supported by the author of WP User Frontend (Tareq Hasan) and is not an official release of WP User Frontend.
So please be aware this code may not be included in the next official release of WP User Frontend.

Bugs
-------

Please report bugs via this special topic on the WP User FrontEnd forum 

http://wordpress.org/support/topic/frontend-updates-2rrr-fork

Please report only bugs here. 

All suggestions for updates to WP User Frontend need to go to the normal support forum.

http://wordpress.org/support/plugin/wp-user-frontend

Download
--------

http://2rrr.org.au/downloads/wp-user-frontend/wp-user-frontend_1_1_2RRR_1_0_alpha.zip


Installation
------------

This update requires the pre-installation of WP User FrontEnd version 1.1.
If you have another version of WP User Front End installed this update will not work.
Changed code is in /wp-user-frontend in the same directory structure as the original files.
Before using make a copy of your original files for safe keeping just in case something breaks.
Then copy the files across.

Examples
--------

Examples of use are provided in the directory /examples.

Add Post Shortcodes
-------------------

Shortcode examples::

	[wpuf_addpost]
	[wpuf_addpost close="false"]

Shortcode options:

	post_type: post | <otherPostType>
		post: (default)
		<otherPostType>: other post types
	close: true | false 
		true: will display close button and redirect to last page on close (default)
		false: 

Edit Post Shortcodes
-------------------

Shortcode examples::

	[wpuf_edit]
	[wpuf_edit close="false"]

Shortcode options:

	close: true | false 
		true: will display close button and redirect to last page on close (default)
		false: 

Last word
----------

Hope you find this useful. Please report bugs as mentioned above.

Big thanks to Tareq Hasan for his work putting together WP User Frontend.

Cheers
TheProfessor




