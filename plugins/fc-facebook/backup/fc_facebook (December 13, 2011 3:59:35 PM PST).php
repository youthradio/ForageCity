<?php

/**
 * Plugin Name: Forage City Facebook Connector
 * Version: 0.1beta
 * Plugin URI: http://www.foragecity.com/
 * Description: Allows users of Forage City to post messages to their Facebook accounts when sharing goods.
 * Author: Zero Division
 * Author URI: http://www.zerodivisiondesign.com 
 * =======================================================================
 *
 */

require_once('facebook.php');
require_once('fc_facebook_secret.php');

global $facebookObj;
$facebookObj = getFacebookUnauthObj();

add_action('admin_menu', 'registerFCFacebookAdminMenu');
function registerFCFacebookAdminMenu(){
	add_options_page('Facebook Connector', 'Facebook Connector', 'publish_pages', 'fc-facebook-settings', 'FCFacebookAdminOptions');
}

function getFacebookUnauthObj(){
	$facebookObj = new Facebook(array(
		'appId'  => FC_FACEBOOK_APPID,
		'secret' => FC_FACEBOOK_APPSECRET,
	));
	return $facebookObj;
}

function getFacebookObj($user_token = ''){
	$facebookObj = getFacebookUnauthObj();
	
	// $authURL = $facebookObj->getAuthorizationUrl();
	if(empty($user_token)){
		$user_id = get_current_user_id();
		$user_token = get_user_meta( $user_id, 'facebook_token', true );
		// echo "<p>$user_token</p>";
	}
	if(!empty($user_token)) {
		$facebookObj->setAccessToken($user_token);
		// echo "<pre>";var_dump($facebookObj->api( '/me', 'GET', array( 'access_token' => $user_token )));echo "</pre>";
	}
	// echo "<p>".$facebookObj->getAccessToken()."</p>";
	return $facebookObj;
}

function postToFacebook($args = array('status' => '', 'link' => '', 'user_token' => '', 'user_secret' => '')){
	$result = false;

	$facebook = getFacebookObj();
	$user = $facebook->getUser();
	if ($user) {
		try {
			$link = $args['link'];
			if(empty($link)) $link = home_url();
			$ret_obj = $facebook->api('/me/feed', 'POST', array(
				'link' => $link,
				'message' => $args['status']
			));
			$result = true;
		} catch (FacebookApiException $e) {
			$result = $e;
		}
	}
	

	return $result;
}

function FCFacebookAdminOptions(){
	// ensure we are in admin area
	if(!is_admin()) {
		die("You are not allowed to view this page");
	}

	$facebook = getFacebookObj();
	// Get User ID
	$user = $facebook->getUser();

	if ($user) {
		try {
			// Proceed knowing you have a logged in user who's authenticated.
			$user_profile = $facebook->api('/me');
			$user_id = get_current_user_id();
			update_user_meta( $user_id, 'facebook_userid', $user );
			update_user_meta( $user_id, 'facebook_token', $facebook->getAccessToken() );
		} catch (FacebookApiException $e) {
			error_log($e);
			$user = null;
		}
	} else try {
		$user_profile = $facebook->api('/me');?><pre><?php print_r($user_profile); ?></pre><?php
	} catch (FacebookApiException $e) {
	}
	//} else {
		// echo "<p><strong>user:</strong> ";
		// var_dump($user);
		// echo " || ".$facebook->getAccessToken();
		// echo " || ".get_user_meta($user_id, 'facebook_token', true);
		// echo "</p>";
		// echo "<h3>fb object</h3><pre>";
		// var_dump($facebook);
		// echo "</pre>";
	// }

 ?>
   <h3>PHP Session</h3>
   <pre><?php print_r($_SESSION); ?></pre>

   <?php if ($user): ?>
     <h3>You</h3>
     <img src="https://graph.facebook.com/<?php echo $user; ?>/picture">

     <h3>Your User Object (/me)</h3>
     <pre><?php print_r($user_profile); ?></pre>
     <a href="<?php echo $facebook->getLogoutUrl(); ?>">Logout of Facebook</a>
     <a href="<?php echo $facebook->getLoginUrl(array('scope' => 'offline_access')); ?>">Login with Facebook</a>
   <?php else: ?>
     <strong><em>You are not Connected.</em></strong>
     <div>
       Login using OAuth 2.0 handled by the PHP SDK:
       <a href="<?php echo $facebook->getLoginUrl(array('scope' => 'offline_access')); ?>">Login with Facebook</a>
     </div>
   <?php endif ?>

<?php
}

function FCFacebookBoxAnnounce($box_post, $good_info, $should_I_post) {
	if($should_I_post['post_to_facebook'] == 'yes') {
		$gi = "something";
		if(!empty($good_info))
			$gi = $good_info;
		$status = "I just shared $gi via Forage City!";
		$link = home_url()."/fc-".$box_post."/";
		postToFacebook(array('status' => $status, 'link' => $link));
	// } else {
	// 	error_log("Not supposed to share with Facebook!");
	}
}

add_action('fc_box_shared','FCFacebookBoxAnnounce', 10, 3);

function FCFacebookProfileExtras(){ ?>
	<tr><th><label>Facebook</label></th><td><span class='description'>
	<?php
	$facebook = getFacebookObj();
	// Get User ID
	$user = $facebook->getUser();
	$success = false;
	if ($user) {
		try {
			// Proceed knowing you have a logged in user who's authenticated.
			$user_profile = $facebook->api('/me');
			// echo"</span><pre>";var_dump($user_profile);echo"</pre><span class='description'>";
			$user_id = get_current_user_id();
			update_user_meta( $user_id, 'facebook_userid', $user );
			update_user_meta( $user_id, 'facebook_token', $facebook->getAccessToken() );
			$success = true;
			echo "<span style='width:100%;text-align:center;'><a target='_blank' href='{$user_profile["link"]}'><img style='vertical-align:-5px;width:1.25em;height:1.25em;margin:0 3px;' src='https://graph.facebook.com/$user/picture'>{$user_profile["name"]}</a></span> <span style='width:100%;text-align:center;'>Your Facebook account is linked.</span>";
/*	     <a href="<?php echo $facebook->getLogoutUrl(); ?>">Logout of Facebook</a> */
		} catch (FacebookApiException $e) {
			error_log($e);
			$user = null;
		}
	} else try {
		$user_profile = $facebook->api('/me');?><pre><?php print_r($user_profile); ?></pre><?php
	} catch (FacebookApiException $e) {
	}
	if(!$success) { ?>
		<span style='width:100%;text-align:center;'><a target='_blank' href="<?php echo $facebook->getLoginUrl(array('scope' => 'offline_access')); ?>">Login with Facebook</a></span><?php
	} ?>
	</span></td></tr><?php
}
add_action('fc_profile_extras', 'FCFacebookProfileExtras');

function FCFacebookGiveExtras($extras){
	$user_token = get_user_meta( $user_id, 'facebook_token', true );
	if(!empty($user_token))
		$extras['post_to_facebook'] = 'Let your Facebook friends know about these goods';
	return $extras;
}
add_filter('fc_give_extras','FCFacebookGiveExtras');

/**
 * I need to put this somewhere that has access without login...
 */
function FCFacebookProfileRemove(){
	try {
		$data       =   parse_signed_request($_REQUEST['signed_request'], FC_FACEBOOK_APPSECRET);
		$fbUserId   =   $data['user_id'];
		if($fbUserId) {
			global $wpdb;
			$usermeta = $wpdb->prefix . 'usermeta';
			$select_user = "SELECT user_id FROM $usermeta WHERE meta_key = 'facebook_userid' AND meta_value = '$fbUserId'";
			$user_ids = $wpdb->get_results($select_user);
			foreach($user_ids as $user)
				delete_user_meta( $user->user_id, 'facebook_token' );
		}
	} catch(Exception $e){
		// Nothin' to do here!
	}
}

/*
These methods are provided by Facebook
<http://developers.facebook.com/docs/authentication/canvas>
*/
function parse_signed_request($signed_request, $secret) {
	list($encoded_sig, $payload) = explode('.', $signed_request, 2);

	// decode the data
	$sig = base64_url_decode($encoded_sig);
	$data = json_decode(base64_url_decode($payload), true);

	if (strtoupper($data['algorithm']) !== 'HMAC-SHA256') {
		error_log('Unknown algorithm. Expected HMAC-SHA256');
		return null;
	}

	// check sig
	$expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
	if ($sig !== $expected_sig) {
		error_log('Bad Signed JSON signature!');
		return null;
	}

	return $data;
}

function base64_url_decode($input) {
	return base64_decode(strtr($input, '-_', '+/'));
}
/* --- End of Facebook-provided methods --- */

?>