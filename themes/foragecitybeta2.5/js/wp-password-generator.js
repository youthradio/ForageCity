/*
	Plugin Name: WP-Password Generator
	Plugin URI: http://stevegrunwell.com/wp-password-generator
	Version: 2.2
*/

(function($){
	$(document).ready(function(){
		$('#pass-strength-result').before('<br /><button id="password_generator" class="button-primary">Generate Password</button><br />');

		$('#password_generator').bind('click', function(){
			$.post(ajaxurl, { action : 'generate_password' }, function(p){
				$('#pass1, #pass2').val(p).trigger('keyup');
				$('#password_generator_toggle kbd').html(p);

				/* Append the 'Show password' link and bind the click event */
				if( $('#password_generator_toggle').length === 0 ){
					$('#send_password').attr('checked', 'checked'); // Only do this the first time
					$('#password_generator').after('<span id="password_generator_toggle" style="margin-left:.25em;"><a href="#">Show password</a></span>');
					$('#password_generator_toggle a').live('click', function(){
						$(this).fadeOut(200, function(){
							$('#password_generator_toggle').html('<kbd style="font-size:1.2em;">' + $('#pass1').val() + '</kbd>');
						});
						return false;
					});
				}
			});
			return false;
		});
	});
})(jQuery);