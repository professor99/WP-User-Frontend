README WP User Frontend Version: 1.1-fork-2RRR-3.0 alpha
========================================================

Modified Code: Andy Bruin (professor99)
Original Code: Tareq Hassan (tareq1988)

Introduction
-------------

This is a fork from WP User Frontend Version 1.1. 
It is a major update of WP User Frontend concentrating on useability and customerisation.
It focuses on Add Post and Edit Post functionality.
There are some bug fixes included as well.

The main differences from 1.1-fork-2RRR-2.1 follow:

- Optionally add Excerpts to the Add/Edit Post forms.
- Add/Edit Post forms re-styled to suit excerpts.
- Publish and expiration times can now be edited on the Edit Post form.
- A Delete button can be optionally added to the Edit Post Form.
- Attachment/Featured Image buttons fixed for Flash/Silverlight.
- CSS has been strengthed for use in other themes.
- CSS has been rearranged and formatted for readability.
- CSS has been tested and fixed for popular browsers using 2010/2011 WordPress themes.

Other changes below in changelog.

Some of these changes are outlined by the following items on the WP User Frontend support forum.

http://wordpress.org/support/topic/custom-editors
http://wordpress.org/support/topic/plugin-wp-user-frontend-redirecting-after-posting
http://wordpress.org/support/topic/allow-to-choose-category-filter
http://wordpress.org/support/topic/close-button-and-return-on-post
http://wordpress.org/support/topic/security-problem-doesnt-observe-user-capabilities

Status
------

This code is ALPHA! Use it at you own risk!

It currently has only been tested in the following configurations.

WordPress 3.4.2
Apache Server
PHP 5.2 & 5.3
WordPress 2010 Theme
WordPress 2011 Theme
Firefox 16.0.2
Safari 5.0.533.16 (should work 4.1+)
Chrome 23.0.1271.95m (should work 5.0+)
Internet Explorer 7.0 & 8.0 
IBM PC

This code is a public development fork of WP User Frontend.
It is not written by or supported by the author of WP User Frontend (Tareq Hasan).
It is not an official release of WP User Frontend.
Please be aware this code may not be included in the next official release of WP User Frontend.

Bugs
-----

Please report bugs via this special topic on the WP User FrontEnd forum 

http://wordpress.org/support/topic/frontend-updates-2rrr-fork

Please report only bugs here. 

All suggestions for updates to WP User Frontend need to go to the normal support forum.

http://wordpress.org/support/plugin/wp-user-frontend

Download
--------

http://2rrr.org.au/downloads/wp-user-frontend/2RRR_3_0/wp-user-frontend_1_1_2RRR_3_0_alpha.zip

A Github repository is availiable

https://github.com/professor99/WP-User-Frontend/tree/2RRR

Examples
---------

Examples of use are provided in the directory /examples.

AddPost Shortcodes
--------------------

Shortcode examples::

	[wpuf_addpost]
	[wpuf_addpost close="false"]
	[wpuf_addpost close="false" redirect="none"]

Shortcode options:

	post_type: post | <otherPostType>
		post: (default)
		<otherPostType>: other post types
	close: true | false 
		true: will display close button and redirect to last page on close (default)
		false: 
	redirect: none | auto | current | new | last 
		none: do nothing
		auto: If close==true will load last page on post. 
		      Else will reload current page on post. (default)
		current: will reload current page on post
		new: will load new page on post
		last: will load last page on post 

EditPost Shortcodes
------------------

Shortcode examples::

	[wpuf_editpost]
	[wpuf_editpost close="false"]
	[wpuf_editpost close="false" redirect="none"]

Shortcode options:

	close: true | false 
		true: will display close button and redirect to last page on close (default)
		false: 
	redirect: none | auto | current | new | last 
		none: do nothing
		auto: If close==true will load last page on post. 
		      Else will reload current page on post. (default)
		current: will reload current page on post
		new: will load new page on post
		last: will load last page on post 

Installation
------------

This update requires the pre-installation of WP User FrontEnd version 1.1.
If you have another version of WP User Front End installed this update will not work.
Changed code is in /wp-user-frontend in the same directory structure as the original files.
Before using make a copy of your original files for safe keeping just in case something breaks.
Then copy the files across.

Changelog
---------

= 1.1-fork-2RRR-3.0 professor99 = 
* Optionally add Excerpts to the Add/Edit Post forms.
* Add/Edit Post forms re-styled to suit excerpts.
* Publish and expiration times can now be edited on the Edit Post form.
* A Delete button can be optionally added to the Edit Post Form.
* Attachment calls now direct (was actions).
* Featured Image code moved to lib/featured_image.php and js/featured_image.js.
* Redirects now filtered by wpuf_post_redirect. 
* Form actions consolidated under wpuf_post_form.
* Attachment/Featured Image buttons fixed for Flash/Silverlight.
* CSS has been strengthed to avoid being messed up by themes.
* CSS has been rearranged and formatted for readability.
* CSS has been tested and fixed for popular browsers using 2010/2011 WordPress themes.

= 1.1-fork-2RRR-2.1 professor99 = 
* Replaced anonymous function with suppress_edit_post_link()

= 1.1-fork-2RRR-2.0 professor99 = 
* Now uses jquery.form to do Ajax style updates.
* Post redirect shortcut option added.
* Better info and error messages.
* Suppress "edit_post_link" on WP User Frontend pages
* Added wpuf_get_option filter
* Removed wpuf_allow_cats filter
* Re-styled buttons
* Re-styled attachment display
* Added wpuf prefix to some css classes
 
= 1.1-fork-2RRR-1.0 professor99 =
* Custom editor option added.
* Editors use max availiable width.
* Close button added as shortcut option and redirects set to suit.
* wpuf_allow_cats filter added.
* Security checks updated.
* Code updated to allow use of wpuf_can_post filter for non logged in users.
		
Last word
---------

Hope you find this useful. Please report bugs as mentioned above.

Big thanks to Tareq Hasan for his work putting together WP User Frontend.

Cheers
TheProfessor




