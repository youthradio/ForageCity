<?php
$mobile_browser = '0';

if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
    $mobile_browser++;
}
 
if ((strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') > 0) or ((isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])))) {
    $mobile_browser++;
}    
 
$mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
$mobile_agents = array(
    'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
    'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
    'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
    'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
    'newt','noki','oper','palm','pana','pant','phil','play','port','prox',
    'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
    'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
    'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
    'wapr','webc','winw','winw','xda ','xda-');
 
if (in_array($mobile_ua,$mobile_agents)) {
    $mobile_browser++;
}
 
if (isset($_SERVER['ALL_HTTP']) && strpos(strtolower($_SERVER['ALL_HTTP']),'OperaMini') > 0) {
    $mobile_browser++;
}
 
if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']),'windows') > 0) {
    $mobile_browser = 0;
}

$current_user_data = wp_get_current_user();
if($current_user_data->ID == 2)
	$mobile_browser = 1;

if(isset($wp_query->query_vars['allow_desktop']) && $wp_query->query_vars['allow_desktop'] == "true") {
	$mobile_browser = 1;
}
/*
if (is_user_logged_in()){
	$mobile_browser = 1;
}else{
	$mobile_browser = 0;
}
*/
function beta_mail($email){
	$email='beta@foragecity.com';
	return $email;
}

function beta_name($name){
	$name = 'Forage City Beta';
	return $name;
}

if (!is_user_logged_in()) {
    $template_url = get_template_directory_uri();
	get_header('trim');
	$intro = get_page_by_path("intro");
	

	if($mobile_browser){
		//$content = apply_filters("the_content", $intro->post_content);
		?>
<div>
    <div>
        <img src="<?php echo $template_url; ?>/img/bannerforagecity.jpg" alt="Forage City Banner.jpg" width="100%" />
    </div>
    <div><!--open who we are area-->
        <p>
            <h1>Who We Are</h1>
            Forage City is a free, open-source mobile app that allows people to share surplus food in their community. The app was developed by the <a href="http://www.youthradio.org/mobileapplab/">Youth Radio Mobile Action Lab</a>, a project that brings young people together with professional programmers to create apps for social change.
        </p>
    </div>
    <div><!--open how it works video and fruit section -->
        <h1>How it Works</h1>
        <object><param name="movie" value="http://www.youtube.com/v/I2ZD0iR-hbk?version=3&amp;hl=en_US&amp;rel=0"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/I2ZD0iR-hbk?version=3&amp;hl=en_US&amp;rel=0" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true"></embed></object>
    </div><!--END HOW IT WORKS VIDEO AND FRUIT AREA-->
    <div>
        <p>
            <h1>Help us test the app</h1>
            Forage City is now in open beta! At this time, we're gearing up to launch in the San Francisco Bay Area, but no matter where you live, we invite you to play with the app and give us feedback.
        </p>
        <p>Now's your chance to help shape the Forage City community! If you have a comment, find a glitch, or have ideas for improvements, please contact us at <a href="mailto:applab@youthradio.org">applab@youthradio.org</a></p>
        <p>Forage City is sized for smart phones.  If you don't mind a work in progress, check us out on your computer. But we look best on your mobile device.</p>
    </div><!--END WHO WE ARE AND TESTER area-->
    <div>
        <p><a href="http://www.foragecity.com/wp-login.php" target="_blank"><img class="button" src="<?php echo $template_url; ?>/img/Foragecitybutton.jpg" alt="Launch Forage City Beta button." /></a>
    </div> <!--END BUTTON-->
    <div>
        <p>NOTE: If you're playing with the app to learn how the whole giving process works, you can try sharing imaginary goods. Just hit GIVE and type "test" in front of the item you want to share (example: "test bananas"). Then add that "test" item. This will allow people to clearly see that your good is a test, and not something they can actually pick up.</p>
    </div><!--END BOTTOM TEXT AREA-->
</div><!--END CONTAINER-->
<?php
	}else{ // desktop splash page
?>
<div class="container_12"><!-- open 12-bar container-->
       <div class="grid_12"> <!--open header-->
             <p><img src="<?php echo $template_url; ?>/img/bannerforagecity.jpg " width="1020" height="215" alt="Forage City Banner.jpg" />
             </p></div><!--END HEADER-->
	       <div class="grid_6"><!--open who we are area-->
					<h1>Who We Are</h1>
					<p> Forage City is a free, open-source mobile app that allows people to share surplus food in their community. The app was developed by the <a href="http://www.youthradio.org/mobileapplab/">Youth Radio Mobile Action Lab</a>, a project that brings young people together with professional programmers to create apps for social change.</p>
					<h1>Help us test the app</h1>
					<p>Forage City is now in open beta! At this time, we're gearing up to launch in the San Francisco Bay Area, but no matter where you live, we invite you to play with the app and give us feedback</p>
					<p>Now's your chance to help shape the Forage City community! If you have a comment, find a glitch, or have ideas for improvements, please contact us at <a href="mailto:applab@youthradio.org">applab@youthradio.org</a></p>
					<p>Forage City is sized for smart phones.  If you don't mind a work in progress, check us out on your computer. But we look best on your mobile device.</p>
				</div><!--END WHO WE ARE AND TESTER area-->
				<div class="grid_6"><!--open how it works video and fruit section -->
					<h1>How it Works</h1>
					<p><object width="470" height="264"><param name="movie" value="http://www.youtube.com/v/I2ZD0iR-hbk?version=3&amp;hl=en_US&amp;rel=0"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/I2ZD0iR-hbk?version=3&amp;hl=en_US&amp;rel=0" type="application/x-shockwave-flash" width="470" height="264" allowscriptaccess="always" allowfullscreen="true"></embed></object></p>
					 <p><img src="<?php echo $template_url; ?>/img/ForageCityFruit.gif " width="460" height="260" alt="Forage Fruit Picture" />
				</div><!--END HOW IT WORKS VIDEO AND FRUIT AREA-->
	<div class="clear"></div>
       <div class= "grid_12">
       		<p><a href="http://www.foragecity.com/wp-login.php" target="_blank"><img class="button" src="<?php echo $template_url; ?>/img/Foragecitybutton.jpg" width="296" height="102" alt="Launch Forage City Beta button." /></a> </div> <!--END BUTTON-->
       <div class= "grid_12">
       	       <p>NOTE: If you're playing with the app to learn how the whole giving process works, you can try sharing imaginary goods. Just hit GIVE and type "test" in front of the item you want to share (example: "test bananas"). Then add that "test" item. This will allow people to clearly see that your good is a test, and not something they can actually pick up.</p>
       </div><!--END BOTTOM TEXT AREA-->
</div><!--END CONTAINER-->
<?php
    }
	echo $content; 
	if (isset($_POST['betaemail'])){
		if (is_email($_POST['betaemail'])){
			add_filter('wp_mail_from', 'beta_mail');
			add_filter('wp_mail_from_name', 'beta_name');
			$user_email='lissa@youthradio.org';
			$subject = 'Forage City Beta Request';
			$message = $_POST['betaemail'] . ' would like to participate in the Forage City closed beta.';
			wp_mail( $user_email, $subject, $message );
			echo '<p>&nbsp;</p><p style="color:red;">Thank you for sharing your contact information with Forage City. </p>
			<p>&nbsp;</p><p style="color:red;">You\'ll be hearing from us soon!</p>';
		}else{
			echo '<p>&nbsp;</p><p style="color:red;">Please make sure you share a valid email address if you\'d like to participate. </p>';
			echo '<p>&nbsp;</p><form action="" method="post"><input type="text" style="background:white;" name="betaemail" size="20" value="EMAIL ADDRESS" /> <input type="submit" value="submit" name="submit" /></form>';
		}
	}else{
		//echo '<p>&nbsp;</p><p>Please share your email address if you\'d like to participate. </p>';
		//echo '<p>&nbsp;</p><form action="" method="post"><input type="text" style="background:white;" name="betaemail" size="20" value="EMAIL ADDRESS" /> <input type="submit" value="submit" name="submit" /></form>';
	}
	?>
	</div></div></div>
	<?php wp_footer(); ?>
</body>
</html>
<?php
} else { 
global $custom_page_to_show;
get_header();

	/*
		Get rid of the annoying phantom space that seems to be appearing
		at the beginning of the loaded template file.

		I don't know where the space is coming from, because it doesn't
		happen with the exact same code on my dev machine, but
		something less hacky than this should probably happen later.
		I just don't have time to care now.
	*/
	function remove_leading_space($buffer)
	{
		$pattern = '/^ /';
		if(!empty($buffer) && preg_match($pattern, $buffer) > 0)
			return (preg_replace($pattern, "", $buffer));
		else
			return $buffer;
	}

	ob_start("remove_leading_space");
?>	<div id='top-buttons' class='<?php echo "$custom_page_to_show"; ?>'><div id='home-button'><a href="<?php echo home_url(); ?>">Home</a></div><div id='info-button'><a href="<?php echo home_url(); ?>/info/">Info</a></div><div id='info-close-button'><a href="<?php echo home_url(); ?>">Close</a></div></div><div style='display:none;'><?php locate_template( array("$custom_page_to_show.php", 'loop.php'), true);
	ob_end_flush();

// endif;

get_footer();

include(ABSPATH.'wp-cron.php');

}
?>