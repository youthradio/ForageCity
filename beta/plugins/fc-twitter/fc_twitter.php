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

function FCTwitterCanPostAsUser($user_id){
	if(empty($user_id))
		$user_id = get_current_user_id();
	$user_token = get_user_meta( $user_id, 'twitter_token', true );
	$user_secret = get_user_meta( $user_id, 'twitter_token_secret', true );
	$result = false;
	try {
		$twitterObj = new EpiTwitter(FC_TWITTER_CONSUMER_KEY, FC_TWITTER_CONSUMER_SECRET);
		$twitterObj->setToken($user_token, $user_secret);
		$twitterInfo = $twitterObj->get_accountVerify_credentials();
		$result = array("user_token" => $user_token, "user_secret" => $user_secret, "twitter_info" => $twitterInfo);
	} catch(EpiTwitterNotAuthorizedException $e){
		delete_user_meta( $user_id, 'twitter_token' );
		delete_user_meta( $user_id, 'twitter_token_secret' );
	} catch(Exception $e) {
	}
	return $result;
}

function FCTwitterProfileExtrasHTML($echo = true) {
	$html = "<tr><th><label>Twitter</label></th><td><span class='description'>";
	try{
		$twitterObj = new EpiTwitter(FC_TWITTER_CONSUMER_KEY, FC_TWITTER_CONSUMER_SECRET);
		$authURL = $twitterObj->getAuthorizationUrl();
		$user_id = get_current_user_id();
		$check = FCTwitterCanPostAsUser($user_id);
		$twitterInfo = '';
		$success = false;

		if($check){
			$twitterInfo = $check["twitter_info"];
			$success = true;
		}

		if(isset($_GET['oauth_token'])){
			try{
				$twitterObj->setToken($_GET['oauth_token']);
				$token = $twitterObj->getAccessToken();
				$twitterObj->setToken($token->oauth_token, $token->oauth_token_secret);
				$ti = $twitterObj->get_accountVerify_credentials();
				$user_token = $token->oauth_token;
				$user_secret = $token->oauth_token_secret;
				update_user_meta( $user_id, 'twitter_token', $user_token );
				update_user_meta( $user_id, 'twitter_token_secret', $user_secret );
				$success = true;
				$twitterInfo = $ti;
				// echo '<div class="updated"><p>Your Twitter account is now linked to your Forage City account.</p></div>';
			} catch(Exception $e) {
			}
		}
		if($success) {
			try{
				$html .= "<span style='width:100%;text-align:center;'><a target='_blank' href='https://twitter.com/#!/{$twitterInfo->screen_name}'><img style='vertical-align:-5px;width:1.25em;height:1.25em;margin:0 3px;' src='{$twitterInfo->profile_image_url}'> {$twitterInfo->screen_name}</a></span> <span style='width:100%;text-align:center;'>Your Twitter account is linked.</span>";
				$html .= "<span style='width:100%;text-align:center;'><a target='_blank' href='https://twitter.com/settings/applications'>Follow this link</a> to disconnect.</span>";
				$success = true;
			} catch(Exception $e){
				$html .= "<pre>".print_r($e,true)."</pre>";
				$success = false;
			}
		} else {
			$html .= '<span style="width:100%;text-align:center;"><a target="_blank" href="' . $authURL . '">Connect to Twitter</a></span>';
		}
	} catch(Exception $e) {}
	$html .= "</span></td></tr>";
	if($echo) echo $html;
	return $html;
}

function FCTwitterProfileExtras(){
	FCTwitterProfileExtrasHTML(true);
}
add_action('fc_profile_extras', 'FCTwitterProfileExtras');

function FCTwitterProfileExtrasJS($extrasJS){
	$extrasJS[] = FCTwitterProfileExtrasHTML(false);
	return $extrasJS;
}
add_filter('fc_profile_extras_js', 'FCTwitterProfileExtrasJS');

function FCTwitterGiveExtras($extras){
	if(FCTwitterCanPostAsUser(""))
		$extras['post_to_twitter'] = 'Tell your Twitter followers you shared goods through Forage City';
	return $extras;
}
add_filter('fc_give_extras','FCTwitterGiveExtras');

function FCTwitterBoxAnnounce($box_post, $good_info, $should_I_post) {
	if($should_I_post['post_to_twitter'] == 'yes') {
		$gi = "something";
		if(!empty($good_info))
			$gi = $good_info;
		$status = "I just shared $gi via Forage City: ".home_url()."/fc-".$box_post."/";
		postToTwitter(array('status' => $status));
	// } else {
	// 	error_log("Not supposed to share with Twitter!");
	}
}

add_action('fc_box_shared','FCTwitterBoxAnnounce', 10, 3);

function getTwitterProfilePic($picURL, $user_id){
	$check = FCTwitterCanPostAsUser($user_id);
	if($check){
		$twitterInfo = $check["twitter_info"];
		$picURL = $twitterInfo->profile_image_url;
	}
	return $picURL;
}
add_filter('fc_profile_pic','getTwitterProfilePic', 10, 2);

?>