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

if (is_user_logged_in()){
	$mobile_browser = 1;
}else{
	$mobile_browser = 0;
}

function beta_mail($email){
	$email='beta@foragecity.com';
	return $email;
}

function beta_name($name){
	$name = 'Forage City Beta';
	return $name;
}

if ($mobile_browser == 0) {
	get_header('trim');
	$desktop_notice = get_page_by_path("desktop");
	$content = '<h1 style="text-align: center;">Welcome to Forage City!</h1>
<p>Our app invites users to share bounties of fresh, free food with others in their local communities-whether it\'s fruits, vegetables, or other delicious leftovers from backyards, markets, food trucks, CSA boxes, or farm lands. The app was created by a team of teenagers at Oakland\'s Peabody Award-winning Youth Radio in partnership with pro designers and developers.
<p>We\'re currently in closed beta.</p>';

	$contentold='<h1 style="text-align: center;">Thank you for coming to Forage City!</h1>
<p>Forage City is a mobile app, so instead of checking it out here, open the browser on your smartphone and go to foragecity.com.</p>
<p>Can\'t wait to see you there!</p>
<p style="text-align: right;">- <em style="font-style: italic;">Youth Radio\'s Mobile Action Lab</em></p>';
	if($desktop_notice != null)
		$content = apply_filters("the_content", $desktop_notice->post_content);
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
		echo '<p>&nbsp;</p><p>Please share your email address if you\'d like to participate. </p>';
		echo '<p>&nbsp;</p><form action="" method="post"><input type="text" style="background:white;" name="betaemail" size="20" value="EMAIL ADDRESS" /> <input type="submit" value="submit" name="submit" /></form>';
	}
	?>
	</div></div></div>

	<?php wp_footer(); ?>
</body>
</html>
<?php
} else { 

get_header();

//if (is_user_logged_in() || $custom_page_to_show == "home" || $custom_page_to_show == "find") : 

	global $custom_page_to_show;

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