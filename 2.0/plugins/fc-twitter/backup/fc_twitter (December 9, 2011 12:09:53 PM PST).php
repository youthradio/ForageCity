<?php

/**
 * Plugin Name: Forage City Twitter Connector
 * Version: 0.1beta
 * Plugin URI: http://www.foragecity.com/
 * Description: Allows users of Forage City to post messages to their twitter accounts when sharing goods. All the heavy lifting with Twitter courtesy of <a href='http://wiki.github.com/jmathai/twitter-async'>Twitter-async</a> by <a href='mailto:jaisen@jmathai.com'>Jaisen Mathai</a>.
 * Author: Zero Division
 * Author URI: http://www.zerodivisiondesign.com 
 * =======================================================================
 *
 */


require_once('EpiCurl.php');
require_once('EpiOAuth.php');
require_once('EpiTwitter.php');
require_once('fc_twitter_secret.php');

add_action('admin_menu', 'registerFCTwitterAdminMenu');
function registerFCTwitterAdminMenu(){
	add_options_page('Twitter Connector', 'Twitter Connector', 'publish_pages', 'fc-twitter-settings', 'FCTwitterAdminOptions');
}

function getTwitterUnauthObj(){
	$twitterObj = new EpiTwitter(FC_TWITTER_CONSUMER_KEY, FC_TWITTER_CONSUMER_SECRET);
	return $twitterObj;
}

function getTwitterObj($user_token = '', $user_secret = ''){
	$twitterObj = getTwitterUnauthObj();
	// $authURL = $twitterObj->getAuthorizationUrl();
	if(empty($user_token) || empty($user_secret)){
		$user_id = get_current_user_id();
		$user_token = get_user_meta( $user_id, 'twitter_token', true );
		$user_secret = get_user_meta( $user_id, 'twitter_token_secret', true );
	}
	if(!empty($user_token) && !empty($user_secret))
		$twitterObj->setToken($user_token, $user_secret);
	return $twitterObj;
}

function postToTwitter($args = array('status' => '', 'user_token' => '', 'user_secret' => '')){
	$result = false;
	try{
		if(!empty($args['status'])){
			$twitterObj = getTwitterObj($args['user_token'], $args['user_secret']);
			$status = $twitterObj->post_statusesUpdate(array('status' => $args['status']));
			$status->response;
			$result = true;
		}
	} catch(Exception $e) {
		$result = $e;
	}
	return $result;
}

function FCTwitterProfileExtras(){ ?>
	<tr><th><label>Twitter</label></th><td><span class='description'>
<?php
	$twitterObj = new EpiTwitter(FC_TWITTER_CONSUMER_KEY, FC_TWITTER_CONSUMER_SECRET);
	$authURL = $twitterObj->getAuthorizationUrl();
	$user_id = get_current_user_id();
	$user_token = get_user_meta( $user_id, 'twitter_token', true );
	$user_secret = get_user_meta( $user_id, 'twitter_token_secret', true );
	$twitterInfo = '';

	$success = true;
	if(isset($_GET['oauth_token'])){
		try{
			$twitterObj->setToken($_GET['oauth_token']);
			$token = $twitterObj->getAccessToken();
			$twitterObj->setToken($token->oauth_token, $token->oauth_token_secret);
			$twitterInfo = $twitterObj->get_accountVerify_credentials();
			$twitterInfo->response;
			$user_token = $token->oauth_token;
			$user_secret = $token->oauth_token_secret;
			update_user_meta( $user_id, 'twitter_token', $user_token );
			update_user_meta( $user_id, 'twitter_token_secret', $user_secret );
			// echo '<div class="updated"><p>Your Twitter account is now linked to your Forage City account.</p></div>';
		}
		catch(Exception $e) {
			echo 'There was a problem authorizing your Twitter account. Please try again.';
			$success = false;
		}
	} else {
		$success = !(empty($user_token) || empty($user_secret));
	}
	if($success) {
		try{
			if(empty($twitterInfo)){
				$twitterObj->setToken($user_token, $user_secret);
				$twitterInfo = $twitterObj->get_accountVerify_credentials();
				$twitterInfo->response;
			}
			echo "<span style='width:100%;text-align:center;'><a target='_blank' href='https://twitter.com/#!/{$twitterInfo->screen_name}'><img style='vertical-align:-5px;width:1.25em;height:1.25em;margin:0 3px;' src='{$twitterInfo->profile_image_url}'> {$twitterInfo->screen_name}</a></span> Your Twitter account is linked to your Forage City account.";
		} catch(Exception $e){ $success = false; }
	}
	if(!$success){
		echo '<a target="_blank" href="' . $authURL . '">Authorize with Twitter</a>';
	} ?>
	</span></td></tr><?php
}
add_action('fc_profile_extras','FCTwitterProfileExtras');

function FCTwitterAdminOptions(){
	// ensure we are in admin area
	if(!is_admin()) {
		die("You are not allowed to view this page");
	}
	$twitterObj = new EpiTwitter(FC_TWITTER_CONSUMER_KEY, FC_TWITTER_CONSUMER_SECRET);
	$authURL = $twitterObj->getAuthorizationUrl();
	$user_id = get_current_user_id();
	$user_token = get_user_meta( $user_id, 'twitter_token', true );
	$user_secret = get_user_meta( $user_id, 'twitter_token_secret', true );
	$twitterInfo = '';

	$success = true;
	if(isset($_GET['oauth_token'])){
		try{
			$twitterObj->setToken($_GET['oauth_token']);
			$token = $twitterObj->getAccessToken();
			$twitterObj->setToken($token->oauth_token, $token->oauth_token_secret);
			$twitterInfo = $twitterObj->get_accountVerify_credentials();
			$twitterInfo->response;
			// echo "Your twitter username is {$twitterInfo->screen_name} and your profile picture is <img src=\"{$twitterInfo->profile_image_url}\">";
			$user_token = $token->oauth_token;
			$user_secret = $token->oauth_token_secret;
			update_user_meta( $user_id, 'twitter_token', $user_token );
			update_user_meta( $user_id, 'twitter_token_secret', $user_secret );
			echo '<div class="updated"><p>Your Twitter account is now linked to your Forage City account.</p></div>';
			// $tok = file_put_contents('tok', $token->oauth_token);
			// $sec = file_put_contents('sec', $token->oauth_token_secret);
		}
		catch(Exception $e) {
			echo '<div class="error"><p>There was a problem authorizing your Twitter account. Please try again.</p></div>';
			// echo "<pre>"; var_dump($e); echo "</pre>";
			$success = false;
		}
	} else {
		$success = !(empty($user_token) || empty($user_secret));
	}
	if($success) {
		try{
			if(empty($twitterInfo)){
				$twitterObj->setToken($user_token, $user_secret);
				// $twitterObj->post_statusesUpdate(array('status' => "Just testing a webapp I'm developing..."));
				$twitterInfo = $twitterObj->get_accountVerify_credentials();
				$twitterInfo->response;
			}
			echo "<div><p>Your twitter username is {$twitterInfo->screen_name} and your profile picture is <img src=\"{$twitterInfo->profile_image_url}\"></p></div>";
		}
		catch(Exception $e){
			echo '<div class="error"><p>Something went wrong.</p></div>';
			// echo "<pre>"; var_dump($e); echo "</pre>";
			$success = false;
		}
	}
	if(!$success){
		echo '<div><p><a target="_blank" href="' . $authURL . '">Authorize with Twitter</a></p></div>';
	}
}

function FCTwitterBoxAnnounce($box_post, $good_info) {
	$gi = "something";
	if(!empty($good_info))
		$gi = $good_info;
	$status = "I just shared $gi via Forage City: ".home_url()."/fc-".$box_post."/";
	postToTwitter(array('status' => $status));
}

add_action('fc_box_shared','FCTwitterBoxAnnounce', 10, 2);

?>