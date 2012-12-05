/**
 * Javascript bootstrap 
 *
 * @author Tareq Hasan 
 * @package WP User Frontend
 * @version 1.1-fork-2RRR-2.0
 */
 
/*
== Changelog ==

= 1.1-fork-2RRR-3.0 professor99 =
* Moved featured image functions to lib/featured_image.js

= 1.1-fork-2RRR-2.0 professor99 =
* Add 'required' message 
* Added TinyMCE triggerSave to fix editor submit bug
* Added wpuf prefix to some class names
* Rename wpuf-info-msg

= 1.1-fork-2RRR-1.0 professor99 =
* Fixed submit editor required bug
*/

 
jQuery(document).ready(function($) {

    var WPUF_Obj = {
        init: function () {
            $('#wpuf_new_post_form, #wpuf_edit_post_form').on('submit', this.checkSubmit);

            //editprofile password strength
            $('#pass1').val('').keyup( this.passStrength );
            $('#pass2').val('').keyup( this.passStrength );
            $('#pass-strength-result').show();

            this.ajaxCategory();
        },
        checkSubmit: function () {
            var form = $(this);

            //Save tinymce iframe to textarea
            if (typeof(tinyMCE) != "undefined") {
                tinyMCE.triggerSave();
            }

            $('#wpuf-info-msg').html('&nbsp;');

            $('*',this).each(function() {
                if( $(this).hasClass('wpuf-invalid') ) {
                    $(this).removeClass('wpuf-invalid');
                }
            });

            var hasError = false;

            $(this).find('.requiredField').each(function() {
                var el = $(this);

                if(jQuery.trim(el.val()) == '') {
                    //Highlights closest visible container.
                    //Still slight bug in tinyMCE editor when submitted when display tab is "HTML"
                    //In this case the "Visible" tab won't be highlighted but this is very insignificant.
                    el.closest(':visible').addClass('wpuf-invalid');
                    hasError = true;
                } else if(el.hasClass('email')) {
                    var emailReg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/;
                    if(!emailReg.test($.trim(el.val()))) {
                        el.closest(':visible').addClass('wpuf-invalid');
                        hasError = true;
                    }
                } else if(el.hasClass('cat')) {
                    if( el.val() == '-1' ) {
                        el.closest(':visible').addClass('wpuf-invalid');
                        hasError = true;
                    }
                }
            });

            if( ! hasError ) {
                $(this).find('input[type=submit]').attr({
                    'value': wpuf.postingMsg,
                    'disabled': true
                });

                return true;
            }
			
            $('#wpuf-info-msg').html('<div class="wpuf-error">Required field(s) empty.</div>');

            return false;
        },
        passStrength: function () {
            var pass1 = $('#pass1').val(), user = $('#user_login1').val(), pass2 = $('#pass2').val(), strength;

            $('#pass-strength-result').removeClass('short bad good strong');
            if ( ! pass1 ) {
                $('#pass-strength-result').html( pwsL10n.empty );
                return;
            }

            strength = passwordStrength(pass1, user, pass2);

            switch ( strength ) {
                case 2:
                    $('#pass-strength-result').addClass('bad').html( pwsL10n['bad'] );
                    break;
                case 3:
                    $('#pass-strength-result').addClass('good').html( pwsL10n['good'] );
                    break;
                case 4:
                    $('#pass-strength-result').addClass('strong').html( pwsL10n['strong'] );
                    break;
                case 5:
                    $('#pass-strength-result').addClass('short').html( pwsL10n['mismatch'] );
                    break;
                default:
                    $('#pass-strength-result').addClass('short').html( pwsL10n['short'] );
            }
        },
        ajaxCategory: function () {
            var el = '#cat-ajax',
                wrap = '.category-wrap';

            $(el).parent().attr('level', 0);
            if ($( wrap + ' ' + el ).val() > 0) {
                WPUF_Obj.getChildCats( $(el), 'lvl', 1, wrap, 'category');
            }

            $(wrap).on('change', el, function(){
                currentLevel = parseInt( $(this).parent().attr('level') );
                WPUF_Obj.getChildCats( $(this), 'lvl', currentLevel+1, wrap, 'category');
            });
        },
        getChildCats: function (dropdown, result_div, level, wrap_div, taxonomy) {
            cat = $(dropdown).val();
            results_div = result_div + level;
            taxonomy = typeof taxonomy !== 'undefined' ? taxonomy : 'category';

            $.ajax({
                type: 'post',
                url: wpuf.ajaxurl,
                data: {
                    action: 'wpuf_get_child_cats',
                    catID: cat,
                    nonce: wpuf.nonce
                },
                beforeSend: function() {
                    $(dropdown).parent().parent().next('.loading').addClass('wpuf-loading');
                },
                complete: function() {
                    $(dropdown).parent().parent().next('.loading').removeClass('wpuf-loading');
                },
                success: function(html) {

                    $(dropdown).parent().nextAll().each(function(){
                        $(this).remove();
                    });

                    if(html != "") {
                        $(dropdown).parent().addClass('hasChild').parent().append('<div id="'+result_div+level+'" level="'+level+'"></div>');
                        dropdown.parent().parent().find('#'+results_div).html(html).slideDown('fast');
                    }
                }
            });
        }
    };

    //run the bootstrap
    WPUF_Obj.init();

});
