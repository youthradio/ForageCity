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

function getFacebookUnauthObj(){
	$facebookObj = new Facebook(array(
		'appId'  => FC_FACEBOOK_APPID,
		'secret' => FC_FACEBOOK_APPSECRET,
	));
	return $facebookObj;
}

function getFacebookObj($user_token = ''){
	$facebookObj = getFacebookUnauthObj();
	if(!empty($user_token)) {
		$facebookObj->setAccessToken($user_token);
	}
	return $facebookObj;
}

function canPostAsUser($facebook_userid = ''){
	if(empty($facebook_userid)){
		$user_id = get_current_user_id();
		$facebook_userid = get_user_meta( $user_id, 'facebook_userid', true );
	}
	$result = false;
	if($facebook_userid) try {
		$facebook = getFacebookObj();
		$perms = $facebook->api("/$facebook_userid/permissions");
		if(isset($perms["data"][0]["publish_stream"]) && $perms["data"][0]["publish_stream"] == 1)
			$result = $facebook_userid;
		else
			delete_user_meta( $user_id, 'facebook_userid' );
	} catch (Exception $e) { }
	return $result;
}

function postToFacebook($args = array('status' => '', 'link' => '', 'user_token' => '', 'user_secret' => '', 'caption' => '')){
	$result = false;
	$user_id = get_current_user_id();
	$facebook_userid = get_user_meta( $user_id, 'facebook_userid', true );
	$facebook = getFacebookObj();
	$link = $args['link'];
	if(empty($link)) $link = home_url();
	$args = array(
		'message'   => $args['status'],
		'link'      => $link,
		'caption'   => $args['caption']
	);
	if($facebook_userid) try {
		$post_id = $facebook->api("/$facebook_userid/feed", "post", $args);
		echo "
	<facebook>Success</facebook>";
		$result = true;
	} catch (FacebookApiException $e) {
		echo "
	<facebook>Error</facebook>";
// 		echo "
// <facebook_error>
// 		<![CDATA[
// "; var_dump($facebook_userid); var_dump($args); var_dump($e); echo "
// 		]]>
// </facebook_error>";
		$result = $e;
	}

	return $result;
}

function FCFB_redirect_url(){
	$redirect_url = home_url().'/profile/edit/';
	return $redirect_url;
}

function FCFacebookBoxAnnounce($box_post, $good_info, $should_I_post) {
	if($should_I_post['post_to_facebook'] == 'yes') {
		$gi = "something";
		$caption = "Forage City";
		if(!empty($good_info)) {
			$gi = $good_info;
			$caption .= " > $good_info";
		}
		$status = "I just shared $gi via Forage City!";
		$link = home_url()."/fc-".$box_post."/";
		postToFacebook(array('status' => $status, 'link' => $link, 'caption' => $caption));
	}
}

add_action('fc_box_shared','FCFacebookBoxAnnounce', 10, 3);

function FCFacebookProfileExtrasHTML($echo = true){
	$html = "<tr><th><label>Facebook</label></th><td><span class='description'>";

	$user_id = get_current_user_id();

	$facebook = getFacebookObj();
	$me = "me";
	$user = canPostAsUser();
	if($user){
		$me = $user;
	} else {
		$user = $facebook->getUser();
	}

	$success = false;
	$clear_user_metas = false;
	if ($user) {
		try {
			$user_profile = $facebook->api("/$me");
			update_user_meta( $user_id, 'facebook_userid', $user );
			$success = true;
			$html .= "<span style='width:100%;text-align:center;'><a target='_blank' href='{$user_profile["link"]}'><img style='vertical-align:-5px;width:1.25em;height:1.25em;margin:0 3px;' src='https://graph.facebook.com/$user/picture'>{$user_profile["name"]}</a></span> <span style='width:100%;text-align:center;'>Your Facebook account is linked.</span>";
		} catch (FacebookApiException $e) {
			$clear_user_metas = true;
			error_log($e);
			// $html .= "Error connecting to Facebook. {".print_r($e, true)."}";
			$user = null;
		}
	}
	if(!$success) {
		$html .= "<span style='width:100%;text-align:center;'><a href='".$facebook->getLoginUrl(array('scope' => 'publish_stream', 'redirect_uri' => home_url().'/profile/edit/'))."'>Connect to Facebook</a></span>";
		// $html .= "<span style='width:100%;text-align:center;'><a href='".$facebook->getLoginUrl()."'>Login with Facebook</a></span>";
	}
	$html .= "</span></td></tr>";
	if($echo) echo $html;
	return $html;
}

function FCFacebookProfileExtras(){
	FCFacebookProfileExtrasHTML(true);
}
add_action('fc_profile_extras', 'FCFacebookProfileExtras');

function FCFacebookProfileExtrasJS($extrasJS){
	$extrasJS[] = FCFacebookProfileExtrasHTML(false);
	return $extrasJS;
}
add_filter('fc_profile_extras_js', 'FCFacebookProfileExtrasJS');

function FCFacebookGiveExtras($extras){
	$user_id = get_current_user_id();
	$facebook_userid = get_user_meta( $user_id, 'facebook_userid', true );
	if(!empty($facebook_userid))
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
/* These methods are provided by Facebook
   <http://developers.facebook.com/docs/authentication/canvas> */
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
function base64_url_decode($input) { return base64_decode(strtr($input, '-_', '+/')); }
/* --- End of Facebook-provided methods --- */

?>