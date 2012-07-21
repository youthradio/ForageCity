<?php
/*
	Embedded Plugin Name: WP-Password Generator
	Embedded Plugin URI: http://stevegrunwell.com/wp-password-generator
	Description: Generates a random password when creating a new WP user
	Version: 2.2
	Author: Steve Grunwell
	Author URI: http://stevegrunwell.com
	License: GPL2
*/

define('WP_PASSWORD_GENERATOR_VERSION', '2.2');

/**
 * Store the settings in a JSON-encoded array in the wp_options table
 *
 * Previous version of the readme.txt file encouraged users to edit wp_password_generator_generate()
 * in order to change characters or min/max lengths. Moving forward, the options will be stored in
 * wp_options to prevent changes to these values from being overwritten
 *
 * @return bool
 * @package WordPress
 * @subpackage WP Password Generator
 * @since 2.1
 */
function wp_password_generator_install(){
  $defaults = array(
    'version' => WP_PASSWORD_GENERATOR_VERSION,
    'min-length' => 7,
    'max-length' => 16
  );

  $opts = get_option('wp-password-generator-opts');
  if( $opts){
    // Remove 'characters', which was only used in version 2.1. We'll use whatever is defined in wp_generate_password()
    if( isset($opts['characters']) ){
      unset($opts['characters']);
    }
    if( isset($opts['min-length']) && intval($opts['min-length']) > 0 ){
      $defaults['min-length'] = intval($opts['min-length']);
    }
    if( isset($opts['max-length']) && intval($opts['max-length']) >= $defaults['min-length'] ){
      $defaults['min-length'] = intval($opts['max-length']);
    }
    /*
      We've checked what we need to. If there are other items in $stored, let them stay ($defaults won't overwrite them)
      as some dev has probably spent some time adding custom functionality to the plugin.
    */
    $defaults = array_merge($opts, $defaults);
  }
  update_option('wp-password-generator-opts', $defaults);
  return true;
}

/**
 * Instantiate the plugin/enqueue wp-password-generator.js
 *
 * @return bool
 * @package WordPress
 * @subpackage WP Password Generator
 * @since 1.0
 */
function wp_password_generator_load(){
	if( basename($_SERVER['PHP_SELF']) == 'user-new.php' ){
		wp_enqueue_script('wp-password-generator', get_bloginfo('template_url').'/js/wp-password-generator.js', array('jquery'), '2.1', true);
	}
	return true;
}

/**
 * Handle an Ajax request for a password, print response.
 *
 * Uses wp_generate_password(), a pluggable function within the WordPress core
 *
 * @return bool (echoes password)
 * @package WordPress
 * @subpackage WP Password Generator
 * @since 1.0
 */
function wp_password_generator_generate(){
	$opts = get_option('wp-password-generator-opts', false);
	if( !$opts || $opts['version'] < WP_PASSWORD_GENERATOR_VERSION ){ // No options or an older version
	  wp_password_generator_install();
	  $opts = get_option('wp-password-generator-opts', false);
	}
	$len = mt_rand($opts['min-length'], $opts['max-length']); // Min/max password lengths

	echo wp_generate_password($len, true, false);
	return true;
}

add_action('admin_print_scripts', 'wp_password_generator_load'); // run wp_password_generator_load() during admin_print_scripts
add_action('wp_ajax_generate_password', 'wp_password_generator_generate'); // Ajax hook
register_activation_hook(__FILE__, 'wp_password_generator_install');

/* ---------------------------------
   end of WP Password Generator code
   --------------------------------- */


/**
 * register a new post type with fc prefix
 * add appropriate capabilities to admin, editor, and manager roles
 *
 * e.g.:
 * 	register_zd_post_type('Contacts','Contact','contacts','contact', 16, true,
 * 		true, array('title','excerpt,'revisions')
 * 	);
 *
 * @param string $name Post type general name
 * @param string $namesingular Post type singular name
 * @param string $slug The slug
 * @param string $slugsingular Singular form of the slug (for capabilities)
 * @param integer $menuposition Position in menu order post type should appear
 * @param boolean $hierarchical Whether the post type is hierarchical
 * @param boolean $hasarchive True to enable post type archives
 * @param array|string $supports Array of thingamajigs this post type supports
 * @return object|WP_Error The registered post type object, or an error object
*/
function register_fc_post_type(
	$name, $namesingular, $slug, $slugsingular,
	$supports = array( 'title', 'editor', 'excerpt', 'revisions', 'page-attributes', 'thumbnail', 'custom-fields' ),
	$menuposition = 50, $hierarchical = false, $hasarchive = false,
	$rewrite = array('slug'=>'default')
){
	if(isset($rewrite['slug']) && $rewrite['slug'] == 'default') $rewrite['slug'] = "$slug";
	$labels = array(
		"name" => _x( $name, "post type general name" ),
		"singular_name" => _x( $namesingular, "post type singular name" ),
		"add_new" => __( "Add New $namesingular" ),
		"add_new_item" => __( "Add $namesingular" ),
		"edit_item" => __( "Edit $namesingular" ),
		"new_item" => __( "New $namesingular" ),
		"view_item" => __( "View $namesingular" ),
		"search_items" => __( "Search $name" ),
		"not_found" =>  __( "No $name found" ),
		"not_found_in_trash" => __( "No $name found in Trash" ),
		"parent_item_colon" => ""
	);
	$args = array(
		'labels' => $labels,
		'public' => true,
		'publicly_queryable' => true,
		'show_ui' => true,
		'query_var' => true,
		'rewrite' => $rewrite,
		'has_archive' => $hasarchive,
		'capability_type' => "fc$slugsingular",
		'capabilities' => array(
			'publish_posts' => "publish_fc$slug",
			'edit_posts' => "edit_fc$slug",
			'edit_others_posts' => "edit_others_fc$slug",
			'delete_posts' => "delete_fc$slug",
			'delete_others_posts' => "delete_others_fc$slug",
			'read_private_posts' => "read_private_fc$slug",
			'edit_post' => "edit_fc$slugsingular",
			'delete_post' => "delete_fc$slugsingular",
			'read_post' => "read_fc$slugsingular",
		),
		'hierarchical' => $hierarchical,
		'menu_position' => $menuposition,
		'supports' => $supports
	);

	$result = register_post_type( "fc$slug", $args);
	if(!is_wp_error($result)){
		$caps=array("publish_fc$slug", "edit_fc$slug", "edit_others_fc$slug", "delete_fc$slug", "delete_others_fc$slug", "read_private_fc$slug", "edit_fc$slugsingular", "delete_fc$slugsingular", "read_fc$slugsingular");
		$roles = array('administrator','manager','editor');
		foreach ($roles as $role){
			$caprole=get_role($role);
			foreach ($caps as $cap){
				$caprole->add_cap($cap);
			}
		}
		$caps2=array("publish_fc$slug", "edit_fc$slug", "edit_fc$slugsingular", "read_fc$slug", "read_fc$slugsingular");
		$uncaps=array("edit_others_fc$slug", "delete_fc$slug", "delete_others_fc$slug", "read_private_fc$slug", "delete_fc$slugsingular");
		$roles2 = array('forager','organization');
		foreach ($roles2 as $role){
			$caprole=get_role($role);
			foreach ($caps2 as $cap){
				$caprole->add_cap($cap);
			}
			foreach ($uncaps as $cap){
				$caprole->remove_cap($cap);
			}
		}
	}
	return $result;
}

/**
 * Hide the admin bar from non-admin users.
 */
function limit_admin_bar(){
	if(current_user_can('publish_pages'))
		return true;
	return false;
}
add_filter('show_admin_bar', 'limit_admin_bar');

/**
 * Don't let regular users see the WordPress Dashboard.
 */
function limit_dashboard(){
	if(!current_user_can('edit_pages') && !defined('DOING_AJAX')){
		wp_redirect( home_url() );
		exit;
	}
}
add_action('admin_init','limit_dashboard');

/**
 * Don't let regular users get redirected to URLs they shouldn't see.
 */
function custom_login_redirect($username) {
	global $redirect_to;
	if(strpos($redirect_to, "/wp-") !== false && username_exists($username)) {
		$userinfo = get_userdatabylogin($username);
		if(!user_can($userinfo->ID, 'edit_pages'))
			$redirect_to = get_option('home');
	}
}
add_action('wp_authenticate', 'custom_login_redirect');

/**
 * Hide some meta boxes from non-admins.
 */
function zd_tweak_meta_boxes(){
	if(!current_user_can('publish_pages')){
		remove_meta_box( 'pageparentdiv' , 'page' , 'side' );
		remove_meta_box( 'postimagediv' , 'page' , 'side' );
	}
}
add_action('do_meta_boxes','zd_tweak_meta_boxes');

/**
 * Hide most admin stuff from non-admins.
 */
function zd_adminmenu_init(){
	$generic_icon_url = esc_url( admin_url( 'images/generic.png' ) );
	$blank_icon_url = esc_url( includes_url( 'images/blank.gif' ) );
	$icon_url = '';

	if(!current_user_can('publish_pages')){
		global $menu, $submenu;
		$visible = array('Profile');
		foreach(array_keys($menu) as $menuitem){
			if(!in_array($menu[$menuitem][0], $visible)){
				unset($submenu[$menu[$menuitem][2]]);
				unset($menu[$menuitem]);
			}
		}
	}
	$info_page = get_page_by_path("info");
	$info = "post.php?post=".$info_page->ID."&action=edit";
	add_menu_page( "FC Info", "FC Info", "edit_pages", $info, "", $icon_url, 6 );

	$page_title = "Organization Users";
	$menu_title = "Organization Users";
	$capability = "edit_pages";
	$menu_slug = "organization-users";
	$function = "zd_adminmenu_org_users";
	// $icon_url = "";
	$position = 12;
	$page = add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $generic_icon_url, $position );

	$page_title = "Promote User";
	$menu_title = "Promote User";
	// $capability = "edit_pages";
	$parent_slug = "organization-users";
	$menu_slug = "promote-organization-users";
	$function = "zd_adminmenu_promote_org_users";
	// $icon_url = "";
	$position = 13;
	$page = add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );

	$page_title = "New Org. User";
	$menu_title = "New Org. User";
	// $capability = "edit_pages";
	$parent_slug = "organization-users";
	$menu_slug = "new-organization-user";
	$function = "zd_adminmenu_add_org_user";
	// $icon_url = "";
	$position = 14;
	$page = add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );

}
add_action('admin_menu','zd_adminmenu_init');

function fc_admin_head() {
	echo '<link rel="stylesheet" type="text/css" href="' . get_bloginfo('template_url') . '/css/wp-admin.css">';
}

add_action('admin_head', 'fc_admin_head');

function zd_adminmenu_org_users(){
	$new_nonce = wp_create_nonce( 'fc_org_users' ); ?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br></div>
		<h2>Organization Users</h2>
		<?php
		if(!empty($_POST)){
			$nonce = fc_get_form_value('fc_org_users_nonce');
			if(wp_verify_nonce($nonce, 'fc_org_users')) {
				$demote_user = fc_get_form_value('fc_demote_userid');
				$promote_user = fc_get_form_value('fc_promote_userid');
				if(!empty($demote_user)){
					$u = new WP_User($demote_user);
					$u->remove_role( 'organization' );
					$u->add_role( 'forager' );
					$user_info = get_userdata($demote_user);
					$username = $user_info->user_login;
					echo '<div class="updated"><form method="post"><p>User with username "'.$username.'" no longer has Organization status. <input type="hidden" value="'.$demote_user.'" name="fc_promote_userid" /><input type="hidden" name="fc_org_users_nonce" id="fc_org_users_nonce" value="'.$new_nonce.'" /><input class="button_link" type="submit" value="Undo" /></p></form></div>';
				}
				if(!empty($promote_user)){
					$u = new WP_User($promote_user);
					$u->remove_role( 'forager' );
					$u->add_role( 'organization' );
					update_user_meta($promote_user, 'email_notifications', "yes");
					$user_info = get_userdata($promote_user);
					$username = $user_info->user_login;
					echo '<div class="updated"><p>Organization status restored to user with username "'.$username.'".</p></div>';
				}
			}
		} ?>
		<table class='org-users-table'>
		<?php
		$wp_user_search = new WP_User_Query( array( 'role' => 'organization' ) );
		$organizations = $wp_user_search->get_results();
		if(!empty($organizations)) { ?>
			<tr><th>Username</th><th>First Name</th><th>Last Name</th><th>Email Address</th><th>Affiliation</th><th>Action</th></tr><?php
		} else {
			echo '<div class="updated"><p>There are no current users with Organization status.</p></div>';
		}
		foreach($organizations as $org){
			$org_user_data = get_userdata($org->ID);
			$affiliation = get_user_meta($org->ID, 'affiliation', true); ?>
			<tr><form method='post'><input type='hidden' name='fc_demote_userid' value='<?php echo $org->ID; ?>' /><input type="hidden" name="fc_org_users_nonce" id="fc_org_users_nonce" value="<?php echo $new_nonce; ?>" />
				<td><?php echo $org->user_login; ?></td>
				<td><?php echo $org_user_data->first_name; ?></td>
				<td><?php echo $org_user_data->last_name; ?></td>
				<td><?php echo $org->user_email; ?></td>
				<td><?php echo $affiliation; ?></td>
				<td><input type='submit' value='Demote' /></td>
			</form></tr>
<?php	} ?>
		</table>
	</div>
<?php
}


function zd_adminmenu_promote_org_users(){
	$new_nonce = wp_create_nonce( 'fc_promote_org_user' ); ?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br></div>
		<h2>Promote Regular User to Organizational User</h2>
		<h3>Search existing users to promote them to an organization user</h3>
	<?php
	if(!empty($_POST)){
		// var_dump($_POST);
		$nonce = fc_get_form_value('fc_promote_org_user_nonce');
		$show_all = (fc_get_form_value('fc_search_show_all') == "Show All Regular Users");
		$search_username = fc_get_form_value('fc_search_username');
		$create_new = (fc_get_form_value('fc_create_new_user') == "yes");
		$search_email = fc_get_form_value('fc_search_email');
		$promote_user = fc_get_form_value('fc_promote_userid');
		$search_affiliation = fc_get_form_value('fc_search_affiliation');
		if(wp_verify_nonce($nonce, 'fc_promote_org_user') && !empty($promote_user)){
			$u = new WP_User($promote_user);
			$u->remove_role( 'forager' );
			$u->add_role( 'organization' );
			update_user_meta($promote_user, 'email_notifications', "yes");
			$username = $u->data->user_login;
			echo '<div class="updated"><p>User with username "'.$username.'" now has Organization status.</p></div>';
		}
	}
	$no_results = false;
	$search_emailuid = false;
	$search_usernameuid = false;
	$found_uid = false;
	$found_uid2 = false;
	if(!$show_all){
		$empty_search = true;
		if(!empty($search_username)){
			$empty_search = false;
			$search_usernameuid = username_exists($search_username);
			$found_uid = $search_usernameuid;
			if($search_usernameuid == false){
				$no_results = true;
				// echo '<div class="error"><p>No users found matching the given username.</p></div>';
			}
		}
		if(!empty($search_email)){
			$empty_search = false;
			$search_emailuid = email_exists($search_email);
			if($search_emailuid == false){
				if(empty($search_username)) {
					$no_results = true;
				}
			} else {
				if($found_uid == false)
					$found_uid = $search_emailuid;
				else
					$found_uid2 = $search_emailuid;
			}
			if(!empty($search_username) && $search_usernameuid != $search_emailuid){
				echo '<div class="updated"><p>Note: Username and email address do not match.</p></div>';
				// $no_results = true;
			}
		}
		$affiliated_foragers = array();
		if(!empty($search_affiliation)){
			$empty_search = false;
			global $wpdb;
			$custom_sql = "SELECT user_id
		FROM $wpdb->usermeta
		WHERE meta_key = 'affiliation' 
			AND LOWER(meta_value) LIKE '%$search_affiliation%'";
			$affiliated_users = $wpdb->get_results( $custom_sql );
			foreach ( $affiliated_users as $frgr )
				$affiliated_foragers[] = $frgr->user_id;
			if(empty($affiliated_foragers))
				$no_results = true;
		}
	}
	$foragers = array();
	if(!$no_results && !$empty_search){
		if($found_uid == false) {
			$wp_user_search = new WP_User_Query( array( 'role' => 'forager' ) );
			$forager_results = $wp_user_search->get_results();
			foreach($forager_results as $frgr) {
				if(empty($affiliated_foragers) || in_array($frgr->ID, $affiliated_foragers))
					$foragers[] = $frgr->ID;
			}
		} else {
			if(empty($affiliated_foragers) || in_array($found_uid, $affiliated_foragers)) {
				$found_user = new WP_User($found_uid);
				if (array_key_exists('forager', $found_user->caps)) {
					$foragers[] = $found_uid;
				} else {
					echo '<div class="updated"><p>User with ';
					if(!empty($search_username))
						echo 'username "'.$search_username.'" ';
					if(!empty($search_email)){
						if(!empty($search_username))
							echo "and ";
						echo 'email address "'.$search_email.'"';
					}
					echo 'is already an organizational user.</p></div>';
					$empty_search = true;
				}
			}
			if($found_uid2 != false && (empty($affiliated_foragers) || in_array($found_uid2, $affiliated_foragers))) {
				$found_user = new WP_User($found_uid2);
				if (array_key_exists('forager', $found_user->caps)) {
					$foragers[] = $found_uid2;
				} else {
					echo '<div class="updated"><p>User with ';
					if(!empty($search_username))
						echo 'username "'.$search_username.'" ';
					if(!empty($search_email)){
						if(!empty($search_username))
							echo "and ";
						echo 'email address "'.$search_email.'"';
					}
					echo 'is already an organizational user.</p></div>';
					$empty_search = true;
				}
			}
		}
	}
	if(!$empty_search && empty($foragers)){
		echo '<div class="updated"><p>No users found matching the given search criteria.</p></div>';
	} ?>
	<form id='fc_search_users' method='post' action=''>
		<table class='form-table'><tbody>
			<tr valign='top'><th scope='row'><label for='fc_search_username'>Username</label></th><td><input type='text' name='fc_search_username' size='60' value='<?php echo $search_username; ?>' class='regular-text' /></td></tr>
			<tr valign='top'><th scope='row'><label for='fc_search_email'>Email Address</label></th><td><input type='text' name='fc_search_email' size='60' value='<?php echo $search_email; ?>' class='regular-text' /></td></tr>
			<tr valign='top'><th scope='row'><label for='fc_search_affiliation'>Affiliation</label></th><td><input type='text' name='fc_search_affiliation' size='60' value='<?php echo $search_affiliation; ?>' class='regular-text' /></td></tr>
			<tr valign='top'><td></td><td><span class="submit"><input type="submit" name="search" id="search" class="button-primary" value="Search" /></span></td></tr>
			<tr valign='top'><td><input type="submit" name="fc_search_show_all" id="fc_search_show_all" class="button-secondary" value="Show All Regular Users" /></td><td></td></tr>
		</tbody></table>
	</form>
	<table class='org-users-table'>
	<?php
	if(!empty($foragers)) { ?>
		<tr><th>Username</th><th>First Name</th><th>Last Name</th><th>Email Address</th><th>Affiliation</th><th>Action</th></tr><?php
	}
	foreach($foragers as $orgID){
		$org_user_data = get_userdata($orgID);
		$affiliation = get_user_meta($orgID, 'affiliation', true); ?>
		<tr><form method='post'><input type='hidden' name='fc_promote_userid' value='<?php echo $orgID; ?>' /><input type="hidden" name="fc_promote_org_user_nonce" id="fc_promote_org_user_nonce" value="<?php echo $new_nonce; ?>" />
			<td><?php echo $org_user_data->user_login; ?></td>
			<td><?php echo $org_user_data->first_name; ?></td>
			<td><?php echo $org_user_data->last_name; ?></td>
			<td><?php echo $org_user_data->user_email; ?></td>
			<td><?php echo $affiliation; ?></td>
			<td><input type='submit' value='Promote' /></td>
		</form></tr>
<?php	} ?>
	</table>
	</div><?php
}

function zd_adminmenu_add_org_user(){
	$new_nonce = wp_create_nonce( 'fc_add_org_user' ); ?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br></div>
		<h2>Add Organizational User</h2>
	<?php
	if(!empty($_POST)){
		// var_dump($_POST);
		$nonce = fc_get_form_value('fc_add_org_user_nonce');
		$email = fc_get_form_value('fc_org_email');
		$username = fc_get_form_value('fc_org_username');
		$firstname = fc_get_form_value('fc_org_firstname');
		$lastname = fc_get_form_value('fc_org_lastname');
		$affiliation = fc_get_form_value('fc_org_affiliation');
		if(wp_verify_nonce($nonce, 'fc_add_org_user')){
			$valid = true;
			if(empty($username)){
				echo '<div class="error"><p>You must enter a username.</p></div>';
				$valid = false;
			} else if(username_exists($username)){
				echo '<div class="error"><p>A user with that username already exists.</p></div>';
				$valid = false;
			}
			if(empty($email)){
				echo '<div class="error"><p>You must enter an email address.</p></div>';
				$valid = false;
			} else if(email_exists($email)) {
				echo '<div class="error"><p>A user with that email address already exists.</p></div>';
				$valid = false;
			}
			if($valid) {
				$random_password = wp_generate_password();
				$new_user_data = array(
					'user_login' => $username,
					'user_email' => $email,
					'user_pass' => $random_password
				);
				if(!empty($firstname))
					$new_user_data['first_name'] = $firstname;
				if(!empty($lastname))
					$new_user_data['last_name'] = $lastname;
				// $user_id = wp_create_user( $username, $random_password, $email );
				$user_id = wp_insert_user($new_user_data);
				if(!is_wp_error($user_id)){
					if(!empty($affiliation))
						update_user_meta($user_id, 'affiliation', $affiliation);
					$u = new WP_User($user_id);
					$u->remove_role( 'forager' );
					$u->add_role( 'organization' );
					update_user_meta($user_id, 'email_notifications', "yes");
					$to = $email;
					$subject = "Forage City -- Account created for your organization";
					$message = "Username: ".$username."\nTemporary password: ".$random_password."\n\nLog in at ".home_url()."/profile/edit/ to edit your profile and set a permanent password.";
					$headers = 'From: Forage City <foragecity@youthradio.org>' . "\r\n";
					if(wp_mail( $to, $subject, $message, $headers ))
						echo '<div class="updated"><p>Organization user created. Login information sent to '.$email.'.</p></div>';
					else
						echo '<div class="error"><p>Account was created with temporary password '.$random_password.' but there was a problem sending an email to the new user. Please inform them directly.</p></div>';
					$email = '';
					$username = '';
					$firstname = '';
					$lastname = '';
					$affiliation = '';
				} else {
					echo '<div class="error"><p>Could not create user. Please try again.</p></div>';
				}
			}
		}
	} ?>
	<form id='fc_org_users' method='post' action=''>
		<input type="hidden" name="fc_add_org_user_nonce" id="fc_add_org_user_nonce" value="<?php echo wp_create_nonce( 'fc_add_org_user' ); ?>" />
		<input type="hidden" name="fc_create_new_user" id="fc_create_new_user" value="yes" />
		<table class='form-table'><tbody>
			<tr valign='top'><th scope='row'><label for='fc_org_username'>* Organization Username</label></th><td><input type='text' name='fc_org_username' size='60' value='<?php echo $username; ?>' class='regular-text' /></td></tr>
			<tr valign='top'><th scope='row'><label for='fc_org_email'>* Organization Email Address</label></th><td><input type='text' name='fc_org_email' size='60' value='<?php echo $email; ?>' class='regular-text' /></td></tr>
			<tr valign='top'><th scope='row'><label for='fc_org_affiliation'>Organization Affiliation</label></th><td><input type='text' name='fc_org_affiliation' size='60' value='<?php echo $affiliation; ?>' class='regular-text' /></td></tr>
			<tr valign='top'><th scope='row'><label for='fc_org_firstname'>First Name</label></th><td><input type='text' name='fc_org_firstname' size='60' value='<?php echo $firstname; ?>' class='regular-text' /></td></tr>
			<tr valign='top'><th scope='row'><label for='fc_org_lastname'>Last Name</label></th><td><input type='text' name='fc_org_lastname' size='60' value='<?php echo $lastname; ?>' class='regular-text' /></td></tr>
			<tr valign='top'><td scope='row'><em>* Required fields</em></td><td><span class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Add New User" /></span></td></tr>
		</tbody></table>
	</form>
	</div><?php
}

add_action( 'show_user_profile', 'fc_profile_fields' ); // Add Additional Profile Fields
add_action( 'edit_user_profile', 'fc_profile_fields' ); // Add Additional Profile Fields
add_action( 'personal_options_update', 'save_fc_profile_fields' ); // saves the new profile meta data
add_action( 'edit_user_profile_update', 'save_fc_profile_fields' ); // saves the new profile meta data

/**
 * Display "Affiliation" field on profile edit page
 */
function fc_profile_fields($user) { ?>
		<h3>Additional profile information</h3>
		<table class="form-table">
			<tr>
				<th><label for="fc_org_affiliation">Affiliation</label></th>
				<td>
					<input type="text" name="fc_org_affiliation" id="fc_org_affiliation" value="<?php echo esc_attr( get_user_meta($user->ID, 'affiliation', true) ); ?>" class="regular-text" /><br />
					<span class="description">If you are affiliated with a particular foraging group (e.g. Forage Oakland), you can note that here.</span>
				</td>
			</tr>

		</table>
<?php
}

/**
 * Safe changes to "Affiliation" field on profile edit page
 */
function save_fc_profile_fields($user_id){
	$affiliation = fc_get_form_value('fc_org_affiliation');
	if (!empty($affiliation) && current_user_can( 'edit_user', $user_id )) {
		update_user_meta($user_id, 'affiliation', $affiliation);
	} else {
		return false;
	}
}

// Hide all regular wordpress widgets from admin dashboard for non-admin users
add_action('wp_dashboard_setup', 'fc_remove_dashboard_widgets' );
function fc_remove_dashboard_widgets() {
	if(!current_user_can('publish_pages')) {
		// Globalize the metaboxes array, this holds all the widgets for wp-admin
		global $wp_meta_boxes;

		// Remove the quickpress widget
		unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);
		// Remove the recent_drafts widget
		unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_recent_drafts']);
		// Remove the primary widget
		unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);
		// Remove the secondary widget
		unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary']);

		// Remove the incoming links widget
		unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links']);
		// Remove the Right Now widget
		unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);
		// Remove the Recent Comments widget
		unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments']);

// 		echo "<pre>
// "; var_dump($wp_meta_boxes['dashboard']); echo "
// </pre>";
	}
} 



/**
 * Creates new custom post types 'fcboxes', 'fcgoods', 'fcreservations'
 * and new user roles 'manager' for not-quite-full-admins
 * and 'forager' for general users
 */
function foragecity_init() {
	add_role('manager', 'Manager', array ('edit_posts' => true, 'moderate_comments' => true, 'manage_categories' => true, 'manage_links' => true, 'upload_files' => true, 'read' => true, 'edit_others_posts' => true, 'edit_published_posts' => true, 'publish_posts' => true, 'edit_private_posts' => true, 'read_private_posts' => true, 'delete_posts' => true, 'delete_others_posts' => true, 'delete_private_posts' => true, 'edit_pages' => true, 'edit_others_pages' => true, 'edit_published_pages' => true, 'publish_pages' => false, 'edit_private_pages' => true, 'read_private_pages' => true, 'delete_pages' => false, 'delete_others_pages' => false, 'delete_published_pages' => false, 'delete_private_pages' => false, 'mediatags_assign_terms' => true) );
	add_role('forager', 'Forager', array ('edit_posts' => false, 'moderate_comments' => false, 'manage_categories' => false, 'manage_links' => false, 'upload_files' => true, 'read' => true, 'edit_others_posts' => false, 'edit_published_posts' => false, 'publish_posts' => false, 'edit_private_posts' => false, 'read_private_posts' => false, 'delete_posts' => false, 'delete_others_posts' => false, 'delete_private_posts' => false, 'edit_pages' => false, 'edit_others_pages' => false, 'edit_published_pages' => false, 'publish_pages' => false, 'edit_private_pages' => false, 'read_private_pages' => false, 'delete_pages' => false, 'delete_others_pages' => false, 'delete_published_pages' => false, 'delete_private_pages' => false, 'mediatags_assign_terms' => false) );
	add_role('organization', 'Organization', array ('edit_posts' => false, 'moderate_comments' => false, 'manage_categories' => false, 'manage_links' => false, 'upload_files' => true, 'read' => true, 'edit_others_posts' => false, 'edit_published_posts' => false, 'publish_posts' => false, 'edit_private_posts' => false, 'read_private_posts' => false, 'delete_posts' => false, 'delete_others_posts' => false, 'delete_private_posts' => false, 'edit_pages' => false, 'edit_others_pages' => false, 'edit_published_pages' => false, 'publish_pages' => false, 'edit_private_pages' => false, 'read_private_pages' => false, 'delete_pages' => false, 'delete_others_pages' => false, 'delete_published_pages' => false, 'delete_private_pages' => false, 'mediatags_assign_terms' => false) );

	register_fc_post_type('Boxes', 'Box', 'boxes', 'box');
	register_fc_post_type('Goods', 'Good', 'goods', 'good');
	
	register_fc_post_type('Reservations', 'Reservation', 'reservations', 'reservation', array( 'title', 'excerpt' ));

	flush_rewrite_rules();
}
add_action( 'init', 'foragecity_init' );

/**
 * Reorder + customize admin menus for ease of use
 */
function custom_menu_order( $menu ) {
	if(!current_user_can('publish_pages'))
		return array('profile.php','separator1','index.php','separator2');
	else
		return $menu;
}
add_filter( 'menu_order', 'custom_menu_order' );
add_filter( 'custom_menu_order', create_function('', 'return true;') );

/**
 * Tell WordPress that we're using thumbnails (for good type images)
 */
if ( function_exists( 'add_theme_support' ) ) {
	add_theme_support( 'post-thumbnails' );
	set_post_thumbnail_size( 94, 94 ); // default Post Thumbnail dimensions
}

/**
 * Tell WordPress that we're using its fancy new menu system.
 */
if ( function_exists( 'register_nav_menu' ) ) {
	register_nav_menu( 'primary', 'Navigation Menu' );
}

/**
 * Register some query vars we need for custom permalinks.
 */
function add_query_vars($aVars) {
	$aVars[] = "custom_page_to_show";
	$aVars[] = "allow_desktop";
	$aVars[] = "sort_by";
	$aVars[] = "action";
	$aVars[] = "box_post";
	return $aVars;
}
add_filter('query_vars', 'add_query_vars');

/**
 * Prettify some permalinks.
 */
function add_rewrite_rules($aRules) {
	return array(
		'home/?$' => 'index.php?custom_page_to_show=home',
		'give/?$' => 'index.php?custom_page_to_show=give',
		'find/?$' => 'index.php?custom_page_to_show=find',
		'find/nearby/?$' => 'index.php?custom_page_to_show=find&sort_by=nearby',
		'find/recent/?$' => 'index.php?custom_page_to_show=find&sort_by=recent',
		'find/wishlist/?$' => 'index.php?custom_page_to_show=find&sort_by=wishlist',
		'find/map/?$' => 'index.php?custom_page_to_show=find&sort_by=map',
		'basket/?$' => 'index.php?custom_page_to_show=basket',
		'profile/?$' => 'index.php?custom_page_to_show=profile',
		'profile/edit/?$' => 'index.php?custom_page_to_show=profile&action=edit',
		'fc-([0-9]+)/?$' => 'index.php?custom_page_to_show=find&action=box_detail&box_post=$matches[1]'
	) + $aRules;
}
add_filter('rewrite_rules_array', 'add_rewrite_rules');

/**
 * Customize the login page a bit
 */
function custom_loginpage_logo_link($url)
{
     // Return a url; in this case the homepage url of wordpress
     return get_bloginfo('wpurl');
}
function custom_loginpage_logo_title($message)
{
     // Return title text for the logo to replace 'wordpress'; in this case, the blog name.
     return get_bloginfo('name');
}
function custom_loginpage_head()
{
     /* Add a stylesheet to the login page */
     $stylesheet_uri = get_bloginfo('template_url')."/css/login.css";
     echo '<link rel="stylesheet" href="'.$stylesheet_uri.'" type="text/css" media="screen" />';
}
add_filter("login_headerurl","custom_loginpage_logo_link");
add_filter("login_headertitle","custom_loginpage_logo_title");
add_action("login_head","custom_loginpage_head");


function check_for_allowed(){
	if(!session_id()) session_start();
	global $wp_query;
	global $custom_page_to_show;
	$custom_page_to_show = 'homemenu';
	if(isset($wp_query->query_vars['custom_page_to_show'])) {
		$custom_page_to_show = $wp_query->query_vars['custom_page_to_show'];
	}
	// error_log(print_r($wp_query,true));
	$allowed = false;
	if(!(isset($_SESSION['force_login']) && $_SESSION['force_login'] == "yes")) {
		if($custom_page_to_show == "homemenu")
			$allowed = true;
		if($custom_page_to_show == "find") {
			$action = fc_get_form_value('action', 'list');
			if($action == 'box_detail')
				$allowed = true;
			if($action == 'list'){
				$sort_by = fc_get_form_value('sort_by');
				if($sort_by != "wishlist")
					$allowed = true;
			}
		}
	}
	$_SESSION['force_login'] = false;
	if(!$allowed) {
		$_SESSION['old_post'] = $_POST;
		$_SESSION['old_get'] = $_GET;
	}
	return $allowed;
}

/**
 * Makes it mandatory to be logged in before viewing most content.
 * Modified from 'WP Require Auth' plugin v. 1.0.2
 * Plugin URI: http://johnny.chadda.se/projects/wp-require-auth/
 * Plugin Author: Johnny Chadda
 * Plugin Author URI: http://johnny.chadda.se/
*/
function wprequireauth_check_auth() {
	/* Make sure that the user is logged in if the page is not wp-login.php
		or wp-register.php */
	if (
		(strpos($_SERVER["PHP_SELF"], "wp-login.php") === false) 
		&& (strpos($_SERVER['PHP_SELF'], 'wp-register.php') === false)
		&& (strpos($_SERVER['PHP_SELF'], 'async-upload.php') === false)
		&& (!is_user_logged_in())
		&& !defined('DOING_AJAX')
		&& !check_for_allowed()
	) auth_redirect();
}
add_filter('wp', 'wprequireauth_check_auth');

/**
 * Must set time zone explicitly for CRON stuff to work.
 */
date_default_timezone_set('America/Los_Angeles');
// wp_clear_scheduled_hook('my_hourly_event');
if ( !wp_next_scheduled( 'my_hourly_event' ) ) {
	wp_schedule_event(time(), 'hourly', 'my_hourly_event');
}

/**
 * Release all unfinalized reservations that are at least 24 hours old.
 */
function do_this_hourly() {
	// do something every hour
	$yesterday = time() - 24*60*60;
	$foraged = get_posts(array("post_type" => "fcreservations", 'numberposts' => -1, 'post_status' => 'draft', 'orderby' => 'post_date', 'order' => 'ASC'));
	reset($foraged);
	$rp = current($foraged);
	while($rp && strtotime($rp->post_date) < $yesterday){
		fc_release_reservation($rp->ID);
		// should probably notify user ($rp->post_author)
		// of the reservation expiration...
		$rp = next($foraged);
	}
}
add_action('my_hourly_event', 'do_this_hourly');


/**
 * Get rid of the CRON stuff upon plugin deactivation.
 */
function my_deactivation() { wp_clear_scheduled_hook('my_hourly_event'); }
register_deactivation_hook(__FILE__, 'my_deactivation');


/**
 * Change the text for the lost password link
 * Formerly hid the 'Register' link
 */
function remove_lostpassword_text ( $text ) {
	if ($text == 'Lost your password?') {
		$text = 'Lost your username or password?';
	}
	// Take out this if statement to allow registering
	if ($text == 'Register') {
		$text = '';
		if(isset($_GET['action']) && $_GET['action'] == "register")
			 $text = 'Register';
	}
	return $text;
}
add_filter( 'gettext', 'remove_lostpassword_text' );


/**
 * Sort based on distance from the search location (ascending).
 * If distances are the same, sort based on the name of the good
 * in the box (ascending).
 * If good names and distances are the same, then sort based on time
 * (descending).
 *
 * The global $box_distances variable should be populated before this
 * is called. If it's not, distances are assumed to be equal.
 * 
 *
 * @param post $a one box to compare
 * @param post $b the other box to compare
 * @return 1 if $a is closer, -1 if $b is closer, 0 if equidistant
 */
function box_cmp_distance($a,$b){
	global $box_distances;
	if(isset($box_distances[$a->ID]) && isset($box_distances[$b->ID])){
		$dista = $box_distances[$a->ID];
		$distb = $box_distances[$b->ID];
		$result = ($dista < $distb ? -1 : ($dista > $distb ? 1 : 0));
	} else $result = 0;
	if($result == 0){
		$gooda = get_good_name($a->ID);
		$goodb = get_good_name($b->ID);
		$result = ($gooda < $goodb ? -1 : ($gooda == $goodb ? 0 : 1));
		if($result == 0){
			$pda = $a->post_date;
			$pdb = $b->post_date;
			$result = ($pda > $pdb ? -1 : ($pda == $pdb ? 0 : 1));
		}
	}
	return $result;
}

/**
 * Sort based on the name of the good in the box (ascending).
 * If the good names are the same, then sort based on distance from
 * the search location (ascending).
 * If good names and distances are the same, then sort based on time
 * (descending).
 *
 * The global $box_distances variable should be populated before this
 * is called. If it's not, distances are assumed to be equal.
 *
 * @param post $a one box to compare
 * @param post $b the other box to compare
 * @return -1 if $a comes first, 1 if $b does, 0 if equal
 */
function box_cmp_good($a,$b){
	$gooda = get_good_name($a->ID);
	$goodb = get_good_name($b->ID);
	$result = ($gooda < $goodb ? -1 : ($gooda == $goodb ? 0 : 1));
	if($result == 0){
		global $box_distances;
		if(isset($box_distances[$a->ID]) && isset($box_distances[$b->ID])){
			$dista = $box_distances[$a->ID];
			$distb = $box_distances[$b->ID];
			$result = ($dista < $distb ? -1 : ($dista == $distb ? 0 : 1));
		}
		if($result == 0){
			$pda = $a->post_date;
			$pdb = $b->post_date;
			$result = ($pda > $pdb ? -1 : ($pda == $pdb ? 0 : 1));
		}
	}
	return $result;
}


/**
 * Sort boxes based on time (descending).
 *
 * @param post $a one box to compare
 * @param post $b the other box to compare
 * @return -1 if $a comes first, 1 if $b does, 0 if equal
 */
function box_cmp_date($a,$b){
	$pda = $a->post_date;
	$pdb = $b->post_date;
	$result = ($pda > $pdb ? -1 : ($pda == $pdb ? 0 : 1));
	return $result;
}


/**
 * Remove the specified reservation, freeing up the associated goods.
 *
 * @param mixed $resID the ID of the reservation post to remove
 */
function fc_release_reservation($resID){
	$result = false;
	$rp = get_post($resID);
	if(!is_null($rp)) {
		$box = $rp->post_title;
		$qty = $rp->post_excerpt;
		$oldqty = get_post_meta($box, 'claimed_quantity', true);
		update_post_meta($box, 'claimed_quantity', $oldqty - $qty);
		if(wp_delete_post($resID, true))
			$result = $box;
	}
	return $result;
}


/**
 * Get the name of the good type contained in the specified box.
 *
 * @param mixed $box_id the ID of the box post
 * @return the name of the good type contained in the specified box
 */
function get_good_name($box_id){
	$result = false;
	$id = get_post_meta($box_id, 'good_type', true);
	$good_type_post = get_post($id);
	if(!is_wp_error($good_type_post))
		$result = strtolower($good_type_post->post_title);
	return $result;
}


/**
 * Use the Google Maps API to convert an address into lat/long coordinates.
 *
 * @param string $address the address to convert into lat/long coordinates
 * @return array('lat' => latitude, 'long' => longitude), or false on failure
 */
function getGeocode($address) {
	$MAPS_HOST = "maps.google.com";
	/* api key for my sandbox version:
	update_option(
		'google_maps_api_key',
		"ABQIAAAA1Bng1mkoTAy0GOzuKvu2wRQLV-kSJ8pqvT3Pd-6IyTRJeXik3BTmXNwt_ls1NPzUJtCE83KjZq17QQ"
	);
	*/

	// default to live site's API key if option isn't set
	$KEY = get_option(
		'google_maps_api_key',
		"ABQIAAAA1Bng1mkoTAy0GOzuKvu2wRSkZDizx9H1nJ5J9FTnVYdt8AJMLBQcEMpWdvGmuud_BNnUmoShbENIhQ"
	);
	$base_url = "http://$MAPS_HOST/maps/geo?output=xml&sensor=true&key=$KEY";

	// Initialize delay in geocode speed
	$delay = 0;
	$geocode_pending = true;

	$result = false;

	while ($geocode_pending) {
		$request_url = $base_url . "&q=" . urlencode($address);
		$xml = simplexml_load_file($request_url) or die("url not loading");

		$status = $xml->Response->Status->code;
		if (strcmp($status, "200") == 0) {
			// Successful geocode
			$geocode_pending = false;
			$delay = 0;

			// Format: Longitude, Latitude, Altitude
			$coordinates = $xml->Response->Placemark->Point->coordinates;

			$coordinatesSplit = split(",", $coordinates);
			// $pi_180 = atan2(1,1) / 45; // best approximation of pi/180
			// convert degrees from Google into radians
			$result = array(
				// 'latrad' => $coordinatesSplit[1] * $pi_180,
				// 'longrad' => $coordinatesSplit[0] * $pi_180,
				'lat' => $coordinatesSplit[1],
				'long' => $coordinatesSplit[0]
			);

		} else if (strcmp($status, "620") == 0) {
			// sent geocodes too fast
			$delay += 100000;
		} else {
			// failure to geocode
			$geocode_pending = false;
		}
		usleep($delay);
	}
	return $result;
}


// Earth's radius in miles, approximately, for distance calculations
define('EARTH_RADIUS', '3959');

/**
 * Given the lat/long coordinates of two points, calculate the distance
 * between them (as the crow flies, because driving distances take too much
 * to calculate, and the maps API wouldn't allow as many requests as we'd
 * want to do for them anyway). Assumes coordinates are given in degrees,
 * or in radians if $in_rads is true.
 *
 * based on http://jan.ucc.nau.edu/~cvm/latlon_formula.html
 *
 * @param float $lat1 the latitude of the first point
 * @param float $lng1 the longitude of the first point
 * @param float $lat2 the latitude of the second point
 * @param float $lng2 the longitude of the second point
 * @param boolean $in_rads if in radians instead of degrees (default false)
 * @return the distance in miles
 */
function latlongdistance($lat1, $lng1, $lat2, $lng2, $in_rads = false){
	$distance = -1;
	if(is_numeric($lat1) && is_numeric($lng1) && is_numeric($lat2) && is_numeric($lng2)){
		if(!$in_rads){ // convert degrees to radians
			$lat1 = deg2rad($lat1);
			$lng1 = deg2rad($lng1);
			$lat2 = deg2rad($lat2);
			$lng2 = deg2rad($lng2);
			// $pi_180 = atan2(1,1) / 45; // best approximation of pi/180
			// $lat1 *= $pi_180;
			// $lng1 *= $pi_180;
			// $lat2 *= $pi_180;
			// $lng2 *= $pi_180;
		}
		$distance = EARTH_RADIUS * acos(
			cos($lat1)*cos($lng1)*cos($lat2)*cos($lng2) +
			cos($lat1)*sin($lng1)*cos($lat2)*sin($lng2) + 
			sin($lat1)*sin($lat2)
		);
	}
	return $distance;
}

/**
 * Convert a radius (in miles) to a number of degrees for latitude.
 *
 * @param float $rad the radius in miles
 * @return the radius in degrees for latitude
 */
function latDelta($rad = 5){
	$delta = 0;
	if(is_numeric($rad))
		$delta = rad2deg($rad/EARTH_RADIUS);
	return $delta;
}

/**
 * Convert a radius (in miles) to a number of degrees for longitude.
 * Requires the latitude to account for curvature.
 *
 * @param float $lat the latitude at the origin
 * @param float $rad the radius in miles
 * @param boolean $in_rads if in radians instead of degrees (default false)
 * @return the radius in degrees for longitude
 */
function longDelta($lat, $rad = 5, $in_rads = false){
	$delta = 0;
	if(is_numeric($lat) && is_numeric($rad)){
		if(!$in_rads)
			$lat = deg2rad($lat);
		$delta = rad2deg($rad/EARTH_RADIUS/cos($lat));
	}
	return $delta;
}

/**
 * Find all organization users within a given range of a given point.
 * @param float $lat the latitude at the origin
 * @param float $lng the longitude at the origin
 * @param float $rad the radius in miles
 * @param boolean $in_rads if in radians instead of degrees (default false)
 * @return an array of users within that radius
 */
function usersWithinRange($lat, $lng, $rad = 5, $in_rads = false){
	$orgIDs = array();
	if(is_numeric($lat) && is_numeric($lng) && is_numeric($rad)){
		$wp_user_search = new WP_User_Query( array( 'role' => 'organization' ) );
		$organizations = $wp_user_search->get_results();
		if(!empty($organizations)) {
			$emails = array();
			foreach($organizations as $org){
				$orgIDs[$org->ID] = 0;
				$emails[$org->ID] = $org->user_email;
			}
			$latd = latDelta($rad);
			$lngd = longDelta($lat, $rad, $in_rads);
			if($in_rads){
				$lat = rad2deg($lat);
				$lng = rad2deg($lng);
			}
			global $wpdb;
			$nearby_users_lat = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value AS latitude FROM $wpdb->usermeta WHERE meta_key = %s AND CAST(meta_value AS DECIMAL(12, 10)) BETWEEN %s AND %s", "latitude", $lat - $latd, $lat + $latd ) );
			$nearby_users_lng = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value AS longitude FROM $wpdb->usermeta WHERE meta_key = %s AND CAST(meta_value AS DECIMAL(13, 10)) BETWEEN %s AND %s", "longitude", $lng - $lngd, $lng + $lngd ) );
			foreach($nearby_users_lat as $nearby_user_lat){
				$nuID = $nearby_user_lat->user_id;
				if(isset($orgIDs[$nuID])){
					$orgIDs[$nuID]++;
				}
			}
			foreach($nearby_users_lng as $nearby_user_lng){
				$nuID = $nearby_user_lng->user_id;
				if(isset($orgIDs[$nuID])){
					$orgIDs[$nuID]++;
				}
			}
			foreach($orgIDs as $orgID => $val){
				if($val < 2)
					unset($orgIDs[$orgID]);
				else
					$orgIDs[$orgID] = $emails[$orgID];
			}
		}
	}
	return $orgIDs;
}

/**
 * Given a box post ID and info, notify nearby organization users via email.
 */
function fc_notify_organizations($box_post, $good_info){
	global $wpdb;
	$giver = $wpdb->get_var( $wpdb->prepare( "SELECT post_author FROM $wpdb->posts WHERE ID = %s;", $box_post ) );
	$lat = get_post_meta($box_post, 'latitude', true);
	$lng = get_post_meta($box_post, 'longitude', true);
	$location = get_post_meta($box_post, 'location', true);
	$locationstr = '';
	if(!empty($location)) $locationstr = " @ $location";
	$headers = 'From: Forage City <foragecity@youthradio.org>' . "\r\n";
	$users = usersWithinRange($lat, $lng);
	$subject = "Forage City -- Someone shared goods near you";
	$message = "Someone shared $good_info$locationstr:  ".home_url()."/fc-".$box_post."/";
	foreach($users as $user => $user_email){
		if($user != $giver && get_user_meta($user, "email_notifications", true) == "yes")
			wp_mail( $user_email, $subject, $message, $headers );
	}
}
add_action('fc_notify_orgs', 'fc_notify_organizations', 10, 2);


/**
 * Get a value from a form POST or GET if it exists
 * (so I don't have to do the checking manually every time)
 * or, failing that, check for a query var
 *
 * @param string $name the name of the form input
 * @param string $default to use if the variable isn't set (default '')
 * @return string value if $_POST[$name] or $_GET[$name] is set, default if not
 */
function fc_get_form_value($name, $default = ''){
	global $wp_query;
	$p = $_POST;
	$g = $_GET;
	if(empty($p)) $p = $_SESSION['old_post'];
	if(empty($g)) $g = $_SESSION['old_get'];
	if(is_array($p) && isset($p[$name]))
		$value = $p[$name];
	elseif(is_array($g) && isset($g[$name]))
		$value = $g[$name];
	elseif(isset($wp_query->query_vars[$name]))
		$value = $wp_query->query_vars[$name];
	else
		$value = $default;
	return $value;
}


/**
 * Load the code that handles random unique number generation.
 */
require_once("randuniq.php");

/**
 * Get a random unique box number.
 */
function get_box_number(){
	$ruseed = get_option('foragecity_randuniq_seed');
	$rucur = get_option('foragecity_randuniq_current');
	if(empty($ruseed)){
		list($usec, $sec) = explode(' ', microtime());
		$ruseed = (float) $sec + ((float) $usec * 100000);
		update_option('foragecity_randuniq_seed', $ruseed);
		$rucur = 0;
		update_option('foragecity_randuniq_current', $rucur);
	}
	if(empty($rucur)){
		$rucur = 0;
		update_option('foragecity_randuniq_current', $rucur);
	}
	// OH GOD SO INEFFICIENT
	$rua = new RandomUnique100kArray($ruseed,$rucur);
	$claim = $rua->claim();
	$intval = $claim[0];
	if($intval<10)
		$intval = "0000".$intval;
	elseif($intval<100)
		$intval = "000".$intval;
	elseif($intval<1000)
		$intval = "00".$intval;
	elseif($intval<10000)
		$intval = "0".$intval;
	$rucur = $claim[1];
	update_option('foragecity_randuniq_current', $rucur);
	return $intval;
}


/**
 * Handles actions on the "Give" page. Pulls inputs from $_POST or $_GET.
 * Outputs results in XML format.
 *
 * Possible subactions:
 *   get_list: Outputs a list of good types whose names match the input.
 *   create_box_post: Create a post for the specified box of goods, output
 *      the information needed to allow for confirmation and finalization.
 *   finish_sharing: Finalize the specified box of goods. Output error
 *      or success notice.
 *   create_new_good_type: Create a new good type post with the specified
 *      information. Output information allowing use of the new post.
 */
function fc_load_give_callback(){
	header( "Content-Type: application/xml" );
	echo '<?xml version="1.0"?>
<result>';
	$action = fc_get_form_value('subaction');
	$good_name = fc_get_form_value('good_name');
	$good_type_post = fc_get_form_value('good_type_post');
	$quantity = fc_get_form_value('quantity');
	$location = fc_get_form_value('location');
	$latitude = fc_get_form_value('latitude');
	$longitude = fc_get_form_value('longitude');
	$instructions = fc_get_form_value('instructions');
	$description = fc_get_form_value('description');
	$good_units = fc_get_form_value('good_units');
	$good_info = fc_get_form_value('good_info');
	if($good_units == "other")
		$good_units = fc_get_form_value('good_units_other');
	else if($quantity > 1) {
		if($good_units == "box")
			$good_units = "boxes";
		else if(!empty($good_units))
			$good_units .= "s";
	}
	$box_number = fc_get_form_value('box_number');
	$box_post = fc_get_form_value('box_post');
	if($action == 'get_list'){
		$exact = false;
		$list = get_goods_list($good_name);
		$pics = goods_list_pics($list['list']);
		$key = $list['exact'];
		if($key){
			echo '
	<exact>'.$key.'</exact>
	<list>
		<good>
			<name>'.$list['list'][$key].'</name>
			<good_type>'.$key.'</good_type>
			<pic>
				<![CDATA[
'.$pics[$key].'
				]]>
			</pic>
		</good>';
			unset($list['list'][$key]);
		} else {
			echo '
	<exact>false</exact>
	<list>';
		}
		foreach($list['list'] as $gtp=>$gt){
			echo '
		<good>
			<name>'.$gt.'</name>
			<good_type>'.$gtp.'</good_type>
			<pic>
				<![CDATA[
'.$pics[$gtp].'
				]]>
			</pic>
		</good>';
		}
		echo '
	</list>';
	} else if($action == 'create_box_post'){
		// $box_number, $instructions, $quantity, $location, $good_type, $good_type_post, $good_units
		$geocode = false;
		$bad_location = "";
		if(empty($location)){
			echo "
		<error>Could not share box</error>
		<message>Valid location required.</message>";
		} else {
			if(empty($latitude) || empty($longitude)){
				$geocode = getGeocode($location);
				if(!$geocode){
					$bad_location = $location;
					$location = "";
				}
			} else {
				$geocode = array();
				$geocode['lat'] = $latitude;
				$geocode['long'] = $longitude;
			}
			if(empty($box_number)) $box_number = get_box_number();
			$new_box_data = create_box_post_ajax($box_number, $instructions, $quantity, $location, $good_type_post, $good_units, $geocode);
			$quantitystr = $quantity;
			if($good_units != "_") $quantitystr .= " $good_units";
			if(!is_wp_error($new_box_data)){
				echo '
		<new_box>
			<post>'.$new_box_data['new_box'].'</post>
			<box_number>'.$box_number.'</box_number>
			<lat>'.$new_box_data['geocode']['lat'].'</lat>
			<long>'.$new_box_data['geocode']['long'].'</long>
			<quantity_str>'.$quantitystr.'</quantity_str>
			<pic>
				<![CDATA[
	'.$new_box_data['pic'].'
				]]>
			</pic>
			<extras>';
			$extras = array();
			$extras = apply_filters('fc_give_extras', $extras);
			foreach($extras as $name => $label){
				echo "
				<extra>
					<name>$name</name>
					<label>$label</label>
				</extra>";
			}
			echo '
			</extras>
		</new_box>';
			} else {
				echo '
	<new_box>
		<error>Could not create box post</error>
		<message>'.$new_box_data->get_error_message().'</message>
	</new_box>';	
			}
		}
	} else if($action == 'finish_sharing'){
		$result = finish_sharing($box_post);
		if(is_wp_error($result)){
			echo '
	<finished>
		<error>Could not share box</error>
		<message>'.$result->get_error_message().'</message>
	</finished>';
		} else {
			$extranames = explode(';', fc_get_form_value("extra_names"));
			$extravalues = explode(';', fc_get_form_value("extra_values"));
			$extras = array();
			$i = 0;
			while($extranames[$i]){
				$extras[$extranames[$i]] = $extravalues[$i];
				$i++;
			}
			// Let plugins know a box was shared, e.g. to post to Twitter or FB
			do_action('fc_box_shared', $box_post, $good_info, $extras);
			// Notify organization users within range
			do_action('fc_notify_orgs', $box_post, $good_info);
			echo '
	<finished>
		<result>true</result>
	</finished>';
		}
	} else if($action == 'create_new_good_type'){
		$new_good_type = create_new_good_type($good_name, $good_units);
		if(is_wp_error($new_good_type)){
			echo '
	<error>Could not create good type</error>
	<message>'.$new_good_type->get_error_message().'</message>';
		} else {
			echo '
	<new_good_type>
		<post>'.$new_good_type.'</post>
	</new_good_type>';
		}
	}
	echo '
</result>';
	die(0);
}

/**
 * Handles actions on the "Find" page. Pulls inputs from $_POST or $_GET.
 * Outputs results in XML format.
 *
 * Possible subactions:
 *   get_list: Outputs a list of boxes whose good type names match the input.
 *   add_to_wishlist: Add the specified good type to the user's wishlist. Output
 *      notice of success or failure.
 *   remove_from_wishlist: Remove the specified good type from the user's wishlist.
 *      Output notice of success or failure.
 *   box_detail: Output detailed information about the specified box.
 *   reserve: Reserve the specified box for the current user. Output errors
 *      or information about the new reservation post.
 */
function fc_load_find_callback(){
	header( "Content-Type: application/xml" );
	echo '<?xml version="1.0"?>
<result>';
	$action = fc_get_form_value('subaction');
	$sort_by = fc_get_form_value('sort_by', 'category');

	$good_name = fc_get_form_value('good_name');
	$good_type = fc_get_form_value('good_type');
	$claim_quantity = fc_get_form_value('claim_quantity');
	$box_post = fc_get_form_value('box_post');
	$latitude = fc_get_form_value('latitude');
	$longitude = fc_get_form_value('longitude');
	$user_id = get_current_user_id();
	$wishlist = get_user_meta($user_id, 'wishlist');

	if($action == 'get_list'){
		$data = find_boxes($good_name, $sort_by, $latitude, $longitude);
		$boxes = $data['boxes'];
		$distances = $data['distances'];
		$goodslist = $data['goodslist'];
		$box_details = $data['box_details'];
		$empty_wishlist = false;
		$wishlist_boxes = $data['wishlist_boxes'];
		if($sort_by == "wishlist"){
			if(empty($wishlist_boxes)){
				$empty_wishlist = true;
				$sort_by = "recent";
			} //else {
				$boxes = $wishlist_boxes;
			// }
		}
		$latitude = $data['search_loc']['latitude'];
		$longitude = $data['search_loc']['longitude'];
		echo "
	<search_location>
		<latitude>$latitude</latitude>
		<longitude>$longitude</longitude>
	</search_location>";
		foreach($boxes as $box){
			$box_post = $box->ID;
			$quantity = $box_details[$box_post]['quantity'];
			$claimed_qty = $box_details[$box_post]['claimed_quantity'];
			$quantityunits = htmlentities($box_details[$box_post]['quantity_units']);
			$quantitystr = htmlentities($box_details[$box_post]['quantity_str']);
			$location = htmlentities($box_details[$box_post]['location']);
			$locationstr = htmlentities($box_details[$box_post]['location_str']);
			$latitude = htmlentities($box_details[$box_post]['latitude']);
			$longitude = htmlentities($box_details[$box_post]['longitude']);
			$good_type = htmlentities($box_details[$box_post]['good_type']);
			$good_name = htmlentities($box_details[$box_post]['good_name']);
			$pic = $box_details[$box_post]['pic'];
			echo "
	<box>
		<id>$box_post</id>
		<pic>
			<![CDATA[
$pic
			]]>
		</pic>
		<good>
			<id>$good_type</id>
			<name>$good_name</name>
		</good>
		<quantity>$quantity</quantity>
		<claimed_quantity>$claimed_qty</claimed_quantity>
		<quantity_units>$quantityunits</quantity_units>
		<quantity_str>$quantitystr</quantity_str>
		<location>$location</location>
		<location_str>$locationstr</location_str>
		<latitude>$latitude</latitude>
		<longitude>$longitude</longitude>
		<distance>".sprintf('%.1F',$distances[$box_post])."</distance>
	</box>";
		}
		if(empty($boxes)){
			$goods = $data['goods'];
			foreach($goods as $good_type => $good){
				$pic = $good['pic'];
				$good_name = $good['good_name'];
				$in_wishlist = $good['in_wishlist'];
				echo "
	<good>
		<id>$good_type</id>
		<name>$good_name</name>
		<in_wishlist>$in_wishlist</in_wishlist>
		<pic>
			<![CDATA[
$pic
			]]>
		</pic>
	</good>";
			}
		}
		if(empty($wishlist))
			echo "
	<no_wishlist>true</no_wishlist>";
		if($empty_wishlist)
			echo "
	<empty_wishlist>true</empty_wishlist>";
	}
	else if($action == 'add_to_wishlist'){
		if(!empty($good_type)){
			delete_user_meta($user_id, 'wishlist', $good_type);
			add_user_meta($user_id, 'wishlist', $good_type);
			echo "
	<added>true</added>";
		} else {
			echo "
	<added>false</added>";
		}
	}
	else if($action == 'remove_from_wishlist'){
		if(!empty($good_type)){
			delete_user_meta($user_id, 'wishlist', $good_type);
			echo "
	<removed>true</removed>";
		} else {
			echo "
	<removed>false</removed>";
		}
	}
	else if($action == 'box_detail'){
		$data = get_box_data($box_post);
		if($data) {
			$wishlist = get_user_meta($user_id, 'wishlist');
			$in_wishlist = is_array($wishlist) && in_array($data['good_type'],$wishlist);
			echo "
	<box>
		<id>".htmlentities($data['box_post'])."</id>
		<good_type>".htmlentities($data['good_type'])."</good_type>
		<good_name>".htmlentities($data['good_name'])."</good_name>
		<quantity>".htmlentities($data['quantity'])."</quantity>
		<quantity_str>".htmlentities($data['quantity_str'])."</quantity_str>
		<giver>".htmlentities($data['giver'])."</giver>
		<affiliation>".htmlentities($data['affiliation'])."</affiliation>
		<location>".htmlentities($data['location'])."</location>
		<location_str>".htmlentities($data['location_str'])."</location_str>
		<instructions>".htmlentities($data['instructions'])."</instructions>
		<pic>
			<![CDATA[
".$data['pic']."
			]]>
		</pic>
		<comments>";
			$comments = get_comments(array('post_id' => $box_post));
			foreach($comments as $com){
				$current_user_data = get_userdata($com->user_id);
				$display_name = get_display_name($current_user_data);
				echo "
			<comment><user>$display_name</user><content>".$com->comment_content."</content></comment>";
			}
			echo "
		</comments>
		<in_wishlist>".($in_wishlist ? "true" : "false")."</in_wishlist>
	</box>";
		}
	}
	else if($action == 'reserve'){
		$result = finalize_reservation($claim_quantity, $box_post);
		if(is_wp_error($result)){
			echo "
	<error>Could not reserve lot.</error>
	<message>".$result->get_error_message()."</message>";
		} else {
			echo "
	<post>$result</post>";
		}
	}
	echo '
</result>';
	die(0);
}

/**
 * Given a first and last name, return the display version
 * (First name and last initial if available, or just first
 *  name if no last name available, or Anonymous if neither)
 */
function get_display_name($user_info) {
	$first_name = $user_info->first_name;
	$last_name = $user_info->last_name;
	$display_name = $first_name;
	if(!empty($last_name))
		$display_name .= (empty($display_name) ? "" : " ").substr($last_name,0,1).".";
	if(empty($display_name)) {
		$display_name = "Anonymous";
		// if(get_user_meta($user_info->ID, "allow_display_name", true) == "true")
			// $display_name = $user_info->display_name;
	}
	return $display_name;
}


/**
 * Handles user profile actions. Pulls inputs from $_POST or $_GET.
 * Outputs results in XML format.
 *
 * Possible subactions:
 *   get_list: outputs a list of boxes the user has reserved
 *   get_detail: outputs detailed information about the specified reservation
 *   release: releases the specified reservation, freeing up those goods
 *   take: verifies the submitted box number and marks the goods as taken
 */
function fc_load_basket_callback(){
	header( "Content-Type: application/xml" );
	echo '<?xml version="1.0"?>
<result>';
	$action = fc_get_form_value('subaction');
	$resID = fc_get_form_value('reservation');
	$box_number = fc_get_form_value('box_number');
	$box_post = false;
	if(!empty($resID)){
		$rp = get_post($resID);
		if(!is_null($rp))
			$box_post = $rp->post_title;
	}

	if($action == 'get_list'){
		$data = get_basket_list();
		foreach($data as $res){
			echo "
	<reservation>
		<res_ID>".htmlentities($res['reservation'])."</res_ID>
		<quantity>".htmlentities($res['quantity'])."</quantity>
		<quantity_str>".htmlentities($res['quantity_str'])."</quantity_str>
		<box>
			<id>".htmlentities($res['box_post'])."</id>
			<good_type>".htmlentities($res['good_type'])."</good_type>
			<good_name>".htmlentities($res['good_name'])."</good_name>
			<expiration>
				<![CDATA[
".$res['expiration']."
				]]>
			</expiration>
			<location>".htmlentities($res['location'])."</location>
			<location_str>".htmlentities($res['location_str'])."</location_str>
			<pic>
				<![CDATA[
	".$res['pic']."
				]]>
			</pic>
		</box>	
	</reservation>";
		}
	} else if($action == 'get_detail' && $box_post){
		$data = get_box_data($box_post);
		if($data) {
			$quantity = $rp->post_excerpt;
			$quantityunits = $data['quantity_units'];
			$quantitystr = $quantity;
			$claimed_qty = get_post_meta($box_post, 'claimed_quantity', true);
			$avail_quantity = $data['quantity'];
			$avail_quantity += $quantity;
			$avail_quantitystr = $avail_quantity;
			if($quantityunits != "_"){
				$quantitystr .= " $quantityunits";
				$avail_quantitystr .= " $quantityunits";
			}
			$quantity_input = "";
			if($avail_quantity > 0)
				$quantity_input = "<p class='label'>How many are you taking?</p><p><span class='explanation explanation-hidden'></span><input type='number' id='taking_quantity' name='taking_quantity' min='0' max='".htmlspecialchars($avail_quantity)."' value='".htmlspecialchars($quantity)."' /></label></p><p style='margin-bottom:20px;'>(There are ".htmlspecialchars($avail_quantitystr)." available. You reserved ".htmlspecialchars($quantity).".)</p>";
			echo "
	<reservation>
		<id>".htmlentities($resID)."</id>
		<box_post>".htmlentities($data['box_post'])."</box_post>
		<good_type>".htmlentities($data['good_type'])."</good_type>
		<good_name>".htmlentities($data['good_name'])."</good_name>
		<quantity>".htmlentities($quantity)."</quantity>
		<quantity_str>".htmlentities($quantitystr)."</quantity_str>
		<quantity_input>
			<![CDATA[
".$quantity_input."
			]]>
		</quantity_input>
		<giver>".htmlentities($data['giver'])."</giver>
		<location>".htmlentities($data['location'])."</location>
		<location_str>".htmlentities($data['location_str'])."</location_str>
		<instructions>".htmlentities($data['instructions'])."</instructions>
		<pic>
			<![CDATA[
".$data['pic']."
			]]>
		</pic>
	</reservation>";
		}
	} else if($action == 'release'){
		if(empty($resID))
			echo "
	<error>Could not release reservation.</error>
	<message>Reservation ID is required.</message>";
		elseif (fc_release_reservation($resID))
			echo "
	<released>true</released>";
		else
			echo "
	<error>Could not release reservation.</error>
	<message>Unknown error. Try again.</message>";
	} else if($action == 'take'){
		if(!empty($resID) && !empty($box_number)){
			$bp = get_post($box_post);
			if(is_null($bp)){
				echo "
	<error>Could not take box.</error>
	<message>Could not load box post #$box_post.</message>";
			} else {
				if($bp->post_title == $box_number){
					$claim_quantity = $rp->post_excerpt;
					$taking_quantity = fc_get_form_value('taking_quantity');
					if(empty($taking_quantity)) $taking_quantity = $claim_quantity;
					if($taking_quantity != $claim_quantity){
						$extra = $taking_quantity - $claim_quantity;
						$claimqty = get_post_meta($box_post, 'claimed_quantity', true);
						$claimqty += $extra;
						update_post_meta($box_post, 'claimed_quantity', $claimqty);
					}
					if(take_the_box($resID,$taking_quantity)) {
						$giver = new WP_User( $bp->post_author );
						if($giver->roles[0] == "organization"){
							$giver_data = get_userdata( $bp->post_author );
							$to = $giver_data->user_email;
							$headers = 'From: Forage City <foragecity@youthradio.org>' . "\r\n";
							$subject = "Forage City -- Someone picked up your shared goods";
							$message = "Someone took ".$taking_quantity." ".get_good_name( $bp->ID );
							wp_mail( $to, $subject, $message, $headers );
						}
						echo "
	<taken>true</taken>";
					} else {
						echo "
	<error>Could not take box.</error>
	<message>Unknown error. Try again.</message>";
					}
				} else {
					echo "
	<error>Could not take box.</error>
	<message>Box number does not match.";
					$current_user_data = wp_get_current_user();
					if($current_user_data->ID == 2)echo " $bp->post_title";
					echo "</message>";
				}
			}
		} else {
			echo "
	<error>Could not take box.</error>
	<message>Box number is required.</message>";
		}
	} else if($action == 'flag'){
		$problem = fc_get_form_value('problem');
		$other_problem = fc_get_form_value('other_problem');
		$bp = get_post($box_post);
		global $wpdb;
		$reservations = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_author, post_excerpt FROM $wpdb->posts WHERE post_title = %s AND post_type = %s AND post_status = %s", $box_post, 'fcreservations', 'draft'));
		$resarray = array();
		foreach($reservations as $row){
			// echo "<row><![CDATA[";
			// var_dump($row);
			// echo "]]></row>";
			$resarray[$row->ID] = array("user" => $row->post_author, "quantity" => $row->post_excerpt);
		}
		if(wp_update_post(array(
			'ID' => $box_post,
			'post_status' => 'draft'
		))){	
		// if(true){
			if($problem == "other") $problem = $other_problem;
			update_post_meta($bp->ID, 'problem', $problem);
			$giver_data = get_userdata( $bp->post_author );
			// echo "<giverdata><![CDATA[";
			// var_dump($giver_data);
			// echo "]]></giverdata>";
			$to = $giver_data->user_email;
			$headers = 'From: Forage City <foragecity@youthradio.org>' . "\r\n";
			$subject = "Forage City -- Your shared box was flagged";
			$shared_quantity = get_post_meta($box_post, 'good_quantity', true);
			$message = "Your box #".$bp->post_title." containing ".$shared_quantity." ".get_good_name( $bp->ID )." was flagged as \"".$problem."\" and has been removed.";
			wp_mail( $to, $subject, $message, $headers );
			// echo "<mail>$to $subject $message";
			// if(!wp_mail( $to, $subject, $message, $headers ))
				// echo " FAILED";
			// echo "</mail>";
			// echo "<reses><![CDATA[";
			// var_dump($resarray);
			// echo "]]></reses>";
			foreach($resarray as $resID => $resinfo){
				// echo "<res><![CDATA[";
				// var_dump($resID);
				// var_dump($resinfo);
				// echo "]]></res>";
				$reser_data = get_userdata( $resinfo['user'] );
				$to = $reser_data->user_email;
				$headers = 'From: Forage City <foragecity@youthradio.org>' . "\r\n";
				$subject = "Forage City -- Reservation canceled";
				$shared_quantity = get_post_meta($box_post, 'good_quantity', true);
				$message = "Your reservation of ".$resinfo["quantity"]." ".get_good_name( $bp->ID )." was canceled.\nThe box was flagged as \"".$problem."\" and has been removed.";
				wp_mail( $to, $subject, $message, $headers );
				// echo "<mail>$to $subject $message";
				// if(!wp_mail( $to, $subject, $message, $headers ))
				// 	echo " FAILED";
				// echo "</mail>";
				wp_trash_post($resID);
			}
			echo "
	<flagged>true</flagged>";
		} else {
			echo "
	<flagged>false</flagged>
	<error>Could not flag box.</error>
	<message>Unable to modify box post.</message>";
		}
	}
	echo '
</result>';
	die(0);
}

/**
 * Handles user profile actions. Pulls inputs from $_POST or $_GET.
 * Outputs results in XML format.
 *
 * Possible subactions:
 *   get_info: outputs user profile data as well as user history.
 *   edit_info: outputs just user profile data and nonce to allow editing.
 *   save_edits: (if nonce is verified) saves user profile data from
 *      $_POST/$_GET. Outputs errors or notice of success.
 *   edit_shared: outputs information about a specified shared box of goods.
 *   remove_shared: removes a specified box of goods from the system.
 *      Outputs errors or notice of success.
 */
function fc_load_profile_callback(){
	header( "Content-Type: application/xml" );
	echo '<?xml version="1.0"?>
<result>';
	$action = fc_get_form_value('subaction');
	$user_id = get_current_user_id();
	$user = fc_get_form_value('user', $user_id);
	$from = fc_get_form_value('from');
	$DEFAULT_RADIUS = "60"; //miles
	if($action == 'get_info'){
		$current_user_data = get_userdata($user);
		$display_name = get_display_name($current_user_data);
		$description = $current_user_data->user_description;
		$address = get_user_meta($user, 'address', true);
		$latitude = get_user_meta($user, 'latitude', true);
		$longitude = get_user_meta($user, 'longitude', true);
		$affiliation = get_user_meta($user, 'affiliation', true);
		$profile_pic = get_template_directory_uri()."/img/person.png";
		$profile_pic = apply_filters("fc_profile_pic", $profile_pic, $user);
		echo "
	<user>
		<display_name>$display_name</display_name>
		<profile_pic>$profile_pic</profile_pic>
		<bio>$description</bio>
		<affiliation>$affiliation</affiliation>
		<address>".urlencode($address)."</address>
		<latitude>$latitude</latitude>
		<longitude>$longitude</longitude>
	</user>";
		$data = get_profile_history($user);
		if(isset($data['shared']) && !empty($data['shared'])){
			echo "
	<shared>";
			$shared = $data['shared'];
			foreach($shared as $box){
				echo "
		<box>
			<box_post>".$box['box_post']."</box_post>
			<good_name>".$box['good_name']."</good_name>
			<quantity_str>".$box['quantity_str']."</quantity_str>
			<date_str>".$box['date_str']."</date_str>
			<pic>
				<![CDATA[
".$box['pic']."
				]]>
			</pic>
		</box>";
			}
			echo "
	</shared>";
		}
		if(isset($data['foraged']) && !empty($data['foraged'])){
			echo "
	<foraged>";
			$foraged = $data['foraged'];
			foreach($foraged as $res){
				echo "
		<res>
			<reservation>".$res['reservation']."</reservation>
			<box_post>".$res['box_post']."</box_post>
			<good_name>".$res['good_name']."</good_name>
			<claim_quantity>".$res['claim_quantity']."</claim_quantity>
			<quantity_str>".$res['quantity_str']."</quantity_str>
			<date_str>".$res['date_str']."</date_str>
			<pic>
				<![CDATA[
".$res['pic']."
				]]>
			</pic>
		</res>";
			}
			echo "
	</foraged>";
		}
		if(isset($data['activity']) && !empty($data['activity'])){
			$activity = $data['activity'];
			echo "
	<activity>";
			foreach($activity as $box){
				echo "
		<box>";
				if($box['post_type'] == 'fcreservations'){
					echo "
			<post_type>fcreservations</post_type>
			<reservation>".$box['reservation']."</reservation>
			<box_post>".$box['box_post']."</box_post>
			<claim_quantity>".$box['claim_quantity']."</claim_quantity>";
				} else {
					echo "
			<post_type>fcboxes</post_type>
			<box_post>".$box['box_post']."</box_post>";
				}
				echo "
			<good_name>".$box['good_name']."</good_name>
			<quantity_str>".$box['quantity_str']."</quantity_str>
			<date_str>".$box['date_str']."</date_str>
			<status_msg>".$box['status_msg']."</status_msg>
			<pic>
				<![CDATA[
".$box['pic']."
				]]>
			</pic>
		</box>";
			}
			echo "
	</activity>";
		}
	}
	elseif($action == 'edit_info'){
		if($user == $user_id){
			$current_user_data = get_userdata($user);
			$username = $current_user_data->user_login;
			$first_name = $current_user_data->first_name;
			$last_name = $current_user_data->last_name;
			$email = $current_user_data->user_email;
			$description = htmlentities($current_user_data->user_description);
			$address = htmlentities(get_user_meta($user, 'address', true));
			$search_radius = get_user_meta($user, 'radius', true);
			if(empty($search_radius)) $search_radius = $DEFAULT_RADIUS;
			echo "
	<user>
		<id>$user</id>
		<username>$username</username>
		<first_name>$first_name</first_name>
		<last_name>$last_name</last_name>
		<email>$email</email>
		<bio>$description</bio>
		<affiliation>$affiliation</affiliation>";
			$wpuser = new WP_User($user);
			if($wpuser->roles[0] == "organization"){
				$email_notifications = get_user_meta($user, 'email_notifications', true);
				if($email_notifications != "yes") $email_notifications = "no";
				echo "
		<email_notifications>$email_notifications</email_notifications>";
			}
			echo "
		<address>$address</address>
		<search_radius>$search_radius</search_radius>
		<nonce>
			<![CDATA[
";
			wp_nonce_field('update-user_' . $user_id);
			echo "
			]]>
		</nonce>
		<extras>";
			$prof_extras = array();
			$prof_extras = apply_filters('fc_profile_extras_js', $prof_extras);
			foreach($prof_extras as $x) {
				echo "
			<extra>
				<![CDATA[";
				echo "
$x
				]]>
			</extra>";
			}
			echo "
		</extras>
	</user>
	<logout_url>".wp_logout_url()."</logout_url>";
		}
		// $username $first_name $last_name $email $address $search_radius $description $user_id
	}
	else if($action == 'save_edits'){
		if($from == 'profile'){
			$profile_errors = false;
			require_once(ABSPATH . 'wp-admin/includes/user.php');
			$result = edit_user($user);
			if(is_wp_error($result)) {
				$profile_errors = $result->errors;
				$action = "edit";
			}
			$address = fc_get_form_value('address');
			if(!empty($address) && $address != get_user_meta($user, 'address', true)){
				$latitude = fc_get_form_value('latitude');
				$longitude = fc_get_form_value('longitude');
				$geocode = false;
				if(empty($latitude) || empty($longitude)){
					$geocode = getGeocode($address);
				} else {
					$geocode = array();
					$geocode['lat'] = $latitude;
					$geocode['long'] = $longitude;
				}
				if($geocode){
					update_user_meta($user, 'address', $address);
					update_user_meta($user, 'latitude', $geocode['lat']);
					update_user_meta($user, 'longitude', $geocode['long']);
				} else {
					$profile_errors['address'] = 'geocode';
					$action = "edit";
				}
			}
			$affiliation = fc_get_form_value('affiliation');
			if(empty($affiliation))
				delete_user_meta($user, 'affiliation');
			else
				update_user_meta($user, 'affiliation', $affiliation);
			$email_notifications = fc_get_form_value('email_notifications');
			if($email_notifications == "yes")
				update_user_meta($user, 'email_notifications', $email_notifications);
			else
				delete_user_meta($user, 'email_notifications');
			$search_radius = fc_get_form_value('search_radius');
			if(!empty($search_radius))
				update_user_meta($user, 'radius', $search_radius);
			if($profile_errors){
				echo "
		<errors>";
				foreach($profile_errors as $name => $msgs){
					echo "
			<error>
				<name>$name</name>";
					foreach($msgs as $msg){
						$message = preg_replace('/.*>: /', '', $msg);
						echo "
				<message>$message</message>";
					}
					echo "
			</error>";
				}
				echo "
		</errors>";
			} else {
				echo "
		<saved>true</saved>";
			}
		} else {
			echo "
	<error>Invalid source.</error>";
		}
	} else if($action == 'edit_shared' || $action == 'edit_foraged'){
		$box_post = fc_get_form_value('box_post');
		$bp = get_post($box_post);
		if(is_null($bp)){
			echo "
	<error>Could not edit shared box.</error>
	<message>Box post not found.</message>";
		} else {
			$box_number = $bp->post_title;
			if($action == 'edit_foraged')
				$box_number = "secret";
			$good_type = get_post_meta($box_post, 'good_type', true);
			$good_type_post = get_post($good_type);
			$count_type = get_post_meta($good_type, 'good_quantity_type', true);
			$good_name = strtolower($good_type_post->post_title);
			$quantity = get_post_meta($box_post, 'good_quantity', true);
			$quantityunits = get_post_meta($box_post, 'good_quantity_units', true);
			if(empty($claimed_qty))$claimed_qty=0;
			$quantity -= $claimed_qty;
			$quantitystr = $quantity;
			if($quantityunits != "_") $quantitystr .= " $quantityunits";
			$location = get_post_meta($box_post, 'location', true);
			$locationstr = '';
			$latitude = get_post_meta($box_post, 'latitude', true);
			$longitude = get_post_meta($box_post, 'longitude', true);
			$pic = get_the_post_thumbnail($box_post, array(47,47));
			if(empty($pic)){
				$pic = get_the_post_thumbnail($good_type, array(47,47));
				if(empty($pic))
					$pic = "<img src='".get_template_directory_uri()."/img/default.png' width='47' height='47'>";
			}
			$instructions = $bp->post_excerpt;
			$description = $bp->post_content;

			echo "
	<box>
		<id>$box_post</id>
		<box_number>$box_number</box_number>
		<good_name>$good_name</good_name>
		<quantity_str>$quantitystr</quantity_str>
		<location>$location</location>
		<latitude>$latitude</latitude>
		<longitude>$longitude</longitude>
		<instructions>".htmlentities($instructions)."</instructions>
		<description>".htmlentities($description)."</description>
		<pic>
			<![CDATA[
".$pic."
			]]>
		</pic>
		<comments>";
			$comments = get_comments(array('post_id' => $bp->ID));
			foreach($comments as $com){
				$current_user_data = get_userdata($com->user_id);
				$display_name = get_display_name($current_user_data);
				echo "
			<comment><user>$display_name</user><content>".$com->comment_content."</content></comment>";
			}
			echo "
		</comments>
	</box>";
		}
	} else if($action == "add_comment"){
		$box_post = fc_get_form_value('box_post');
		$comment_content = fc_get_form_value('comment_content');
		if(empty($comment_content)){
			echo "
	<error>Could not add comment.</error>
	<message>Empty comment.</message>";
		} else if(empty($box_post)){
			echo "
	<error>Could not add comment.</error>
	<message>Box post ID is required.</message>";
		} else {
			if(add_comment($box_post, $comment_content)){
				$current_user_data = get_userdata($user_id);
				$display_name = get_display_name($current_user_data);
				echo "
	<added>true</added>
	<user>$display_name</user>";
			}
		}
	} else if($action == "remove_shared"){
		$box_post = fc_get_form_value('box_post');
		if($box_post == ''){
			echo "
	<removed>false</removed>
	<error>Could not remove box.</error>
	<message>Box post ID is required.</message>";
		} else {
			$bp = get_post($box_post);
			if(is_null($bp)){
				echo "
	<removed>false</removed>
	<error>Could not remove box.</error>
	<message>Could not find box post.</message>";
			} else if($user_id != $bp->post_author){
				echo "
	<removed>false</removed>
	<error>Could not remove box.</error>
	<message>That isn't your box.</message>";
			} else {
				global $wpdb;
				$reservations = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type= %s", $box_post, 'fcreservations'));
				foreach($reservations as $row){
					wp_delete_post($row->ID);
				}
				if(wp_delete_post($box_post)){
					echo "
	<removed>true</removed>";
				// echo "<p class='finalized-notice'>Okay, your box has been taken off the list.</p>";
				} else {
					echo "
	<removed>false</removed>
	<error>Could not remove box.</error>
	<message>Unable to delete box post.</message>";
				}
			}
		}
	}
	echo '
</result>';
	die(0);
}


/**
 * Register the AJAX callback functions defined above.
 */
add_action( 'wp_ajax_fc_load_give', 'fc_load_give_callback' );
add_action( 'wp_ajax_fc_load_find', 'fc_load_find_callback' );
add_action( 'wp_ajax_fc_load_basket', 'fc_load_basket_callback' );
add_action( 'wp_ajax_fc_load_profile', 'fc_load_profile_callback' );



/**
 * If user attempts an ajax call while logged out that requires
 * user information, note the destination and specify redirect.
 * (I can't get the redirect to work, but at least I can now
 * detect when the user needs to login and make them do that
 * and then send them to the home page, rather than just
 * processing an empty response with no clear indication of
 * anything being wrong).
 */
function fc_should_redirect($dest = ""){
	if(!session_id()) session_start();
	$_SESSION['old_post'] = $_POST;
	$_SESSION['old_get'] = $_GET;
	header( "Content-Type: application/xml" );
	echo '<?xml version="1.0"?>
<redirect><![CDATA['.home_url().'/'.$dest.']]></redirect>';
	die(0);
}
function fc_login_give() { fc_should_redirect("give/"); };
function fc_login_find() { fc_should_redirect("find/"); };
function fc_login_basket() { fc_should_redirect("basket/"); };
function fc_login_profile() {
	$redir = "profile/";
	$action = fc_get_form_value('subaction');
	if($action ==  'edit_info')
		$redir .= "edit/";
	fc_should_redirect($redir);
}
add_action( 'wp_ajax_nopriv_fc_load_give', 'fc_login_give' );
// add_action( 'wp_ajax_nopriv_fc_load_find', 'fc_login_find' );
add_action( 'wp_ajax_nopriv_fc_load_basket', 'fc_login_basket' );
add_action( 'wp_ajax_nopriv_fc_load_profile', 'fc_login_profile' );

/**
 * If user tries to go beyond just the list on the find page,
 * require login.
 */
function fc_check_find(){
	$action = fc_get_form_value('subaction');
	$sort_by = fc_get_form_value('sort_by');
	if($action != "box_detail" && ($action != "get_list" || $sort_by == "wishlist")) {
		if(!session_id()) session_start();
		$_SESSION['force_login'] = "yes";
		// $box_post = fc_get_form_value('box_post');
		// $good_type = fc_get_form_value('good_type');
		// $good_name = fc_get_form_value('good_name');
		// $sort_by = fc_get_form_value('sort_by');
		// $available_quantity = fc_get_form_value('available_quantity');
		// $claim_quantity = fc_get_form_value('claim_quantity');
		$redir = "find/";
		// $redir .= "?action=".urlencode($action);
		// $redir .= "&box_post=".urlencode($box_post);
		// $redir .= "&good_type=".urlencode($good_type);
		// $redir .= "&good_name=".urlencode($good_name);
		// $redir .= "&sort_by=".urlencode($sort_by);
		// $redir .= "&available_quantity=".urlencode($available_quantity);
		// $redir .= "&claim_quantity=".urlencode($claim_quantity);
		fc_should_redirect($redir);
	} else {
		fc_load_find_callback();
	}
}
add_action( 'wp_ajax_nopriv_fc_load_find', 'fc_check_find' );




/* ******************************************************************
	USED BY GIVE
****************************************************************** */


function create_box_post_ajax($box_number, $instructions, $quantity, $location, $good_type_post, $good_units, $geocode){
	$result = false;
	$new_box = wp_insert_post(array(
		// 'ID' => '12',
		'post_title' => $box_number,
		'post_content' => '',
		'post_status' => 'draft', 
		'post_type' => 'fcboxes',
		'post_excerpt' => $instructions
	), true);
	if(is_wp_error($new_box)){
		// do something?
		// var_dump($new_box);
		$result = $new_box;
	} else {
		update_post_meta($new_box, 'good_quantity', $quantity);
		update_post_meta($new_box, 'location', $location);
		update_post_meta($new_box, 'good_type', $good_type_post);
		if(!empty($good_units))
			update_post_meta($new_box, 'good_quantity_units', $good_units);
		if($geocode){
			update_post_meta($new_box, 'latitude', $geocode['lat']);
			update_post_meta($new_box, 'longitude', $geocode['long']);
		}

		$pic = get_the_post_thumbnail($new_box, array(47,47));
		if(empty($pic)){
			$pic = get_the_post_thumbnail($good_type_post, array(47,47));
			if(empty($pic))
				$pic = "<img src='".get_template_directory_uri()."/img/default.png' width='47' height='47'>";
		}
		$result = array('new_box' => $new_box, 'geocode' => $geocode, 'pic' => $pic);
	}
	return $result;
}


/**
 * Given an array of good types, return an array of the associated images.
 * @param array $goodslist A list of good posts as $post->ID => $post->post_title
 * @return array as $post->ID => "<img src='THUMBNAIL' width='47' height='47'>"
 */
function goods_list_pics($goodslist){
	$pics = array();
	foreach($goodslist as $gtp=>$gt) {
		$pic = get_the_post_thumbnail($gtp, array(47,47));
		if(empty($pic))
			$pic = "<img src='".get_template_directory_uri()."/img/default.png' width='47' height='47'>";
		$pics[$gtp] = $pic;
	}
	return $pics;
}

/**
 * Create a new good type post.
 * @param string $good_type the name of the good type
 * @param string $good_units the units to use for counting the good type
 * @return mixed the ID of the new good type post, or a WP_Error object
 */
function create_new_good_type($good_type, $good_units){
	global $wpdb;
	// don't make dupes
	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type= %s", $good_type, 'fcgoods'));
	if($existing){
		$new_good = $existing;
		wp_update_post(array('ID' => $existing, 'post_status' => 'publish'));
	} else {
		// create the good type
		$new_good = wp_insert_post(array(
			'post_title' => $good_type,
			'post_status' => 'publish',
			'post_type' => 'fcgoods'
		));
	}
	if(is_wp_error($new_good)){
		// do something?
	} else {
		if(!empty($good_units))
			update_post_meta($new_good, 'good_quantity_units', $good_units);
	}
	return $new_good;
}

/**
 * Get a list of good types whose names match the given string
 * @param string $good_type The string to match against
 * @return array with two elements, 'list' an array of matching goods
 *   and 'exact' the ID of a good type post that exactly matches the
 *   input string, if one exists.
 *
 * e.g. input "apple" could return 'list' => ( 1 => 'apple', 2 => 'pineapple' )
 * and 'exact' => 1, assuming the 'apple' good type post has ID 1 and the
 * 'pineapple' good type post has ID 2. (protip: they don't.)
 */
function get_goods_list($good_type){
	$args = array( 'post_type' => 'fcgoods', 'numberposts' => -1, 'order'=> 'ASC', 'orderby' => 'title' );
	$goodslist = array();
	$postslist = get_posts( $args );
	foreach($postslist as $post){
		$goodslist[$post->ID] = strtolower($post->post_title);
	}
	$good_type_post = false;

	if(!empty($good_type)){
		$key = array_search(strtolower($good_type), $goodslist);
		if($key)
			$good_type_post = $key;
		$newgoodslist = preg_grep("/$good_type/i", $goodslist);
		if(empty($newgoodslist))
			$newgoodslist = preg_grep("/".str_replace("y","ie",$good_type)."/i", $goodslist);
		$goodslist = $newgoodslist;
	}
	return array('list' => $goodslist, 'exact' => $good_type_post);
}

/**
 * Given the ID of a box post, mark it as available.
 * @param mixed $box_post the ID of the box post
 * @return true on success, false on error
 */
function finish_sharing($box_post){
	$result = false;
	$bp = get_post($box_post);
	if(!is_wp_error($bp) && $bp->post_type == "fcboxes") {
		$shared_box = wp_update_post(array(
			'ID' => $box_post,
			'post_status' => 'publish'
		));
		if(!is_wp_error($shared_box)){
			$result = true;
		}
	}
	return $result;
}


/* ******************************************************************
	USED BY FIND
****************************************************************** */

/**
 * Get a list of available shared boxes containing good types whose
 * names match the given string.
 * @param string $good_name the good type name to match against
 * @param string $sort_by 'nearby' for distance, 'category' for
 *    alphabetical by good type name.
 * @return array (
 *  'boxes' => an array of matching boxes,
 *  'distances => array of distances between those boxes and user's location,
 *  'goodslist' => array of matching good types if no currently shared boxes,
 *  'wishlist_boxes' => array of matching boxes with goods on user's wishlist,
 *  'goods' => array of details about the goods in goodslist,
 *  'search_loc' => array('latitude' => lat, 'longitude' => long) where
 *    lat,long are the user's search location
 * )
 */
function find_boxes($good_name, $sort_by, $search_lat = "", $search_long = ""){
	$DEFAULT_RADIUS = "60"; // miles
	$args = array( 'post_type' => 'fcgoods', 'numberposts' => -1, 'order'=> 'ASC', 'orderby' => 'title' );
	$postslist = get_posts( $args );
	$goodslist = array();
	foreach($postslist as $post){
		$goodslist[$post->ID] = strtolower($post->post_title);
	}

	if(!empty($good_name)){
		$newgoodslist = preg_grep("/$good_name/i", $goodslist);
		if(empty($newgoodslist))
			$newgoodslist = preg_grep("/".str_replace("y","ie",$good_name)."/i", $goodslist);
		$goodslist = $newgoodslist;
	}

	$args = array( 'post_type' => 'fcboxes', 'numberposts' => -1, 'order'=> 'DESC', 'orderby' => 'post_date', 'post_status' => 'publish' );
	$boxes = get_posts($args);

	$user_id = get_current_user_id();
	$wishlist = get_user_meta($user_id, 'wishlist');
	if(empty($search_lat) || empty($search_long)) {
		$search_lat = get_user_meta($user_id, 'latitude', true);
		$search_long = get_user_meta($user_id, 'longitude', true);
	}
	$search_radius = get_user_meta($user_id, 'radius', true);
	if(empty($search_radius)) $search_radius = $DEFAULT_RADIUS;
	// if no data, pretend user's at Youth Radio
	if(empty($search_lat)) $search_lat = "37.806906";
	if(empty($search_long)) $search_long = "-122.269952";
	$no_wishlist = false;
	if(!is_array($wishlist)){
		$wishlist = array();
		$no_wishlist = true;
	}

	$distances = array();
	$box_details = array();
	$wishlist_boxes = array();
	foreach($boxes as $key => $box){
		$box_post = $box->ID;
		$good_type = get_post_meta($box_post, 'good_type', true);
		if(!isset($goodslist[$good_type]))
			unset($boxes[$key]);
		else{
			$box_lat = get_post_meta($box_post, 'latitude', true);
			$box_long = get_post_meta($box_post, 'longitude', true);
			// if no data, pretend it's at Youth Radio
			if(empty($box_lat)) $box_lat = "37.806906";
			if(empty($box_long)) $box_long = "-122.269952";
			$distances[$box->ID] = latlongdistance($search_lat, $search_long, $box_lat, $box_long);
			$pic = get_the_post_thumbnail($box_post, array(47,47));
			if(empty($pic)){
				$pic = get_the_post_thumbnail($good_type, array(47,47));
				if(empty($pic))
					$pic = "<img src='".get_template_directory_uri()."/img/default.png' width='47' height='47'>";
			}
			$good_type_post = get_post($good_type);
			$good_name = strtolower($good_type_post->post_title);
			$quantity = get_post_meta($box_post, 'good_quantity', true);
			$quantityunits = get_post_meta($box_post, 'good_quantity_units', true);
			$claimed_qty = get_post_meta($box_post, 'claimed_quantity', true);
			if(empty($claimed_qty))$claimed_qty=0;
			$quantity -= $claimed_qty;
			if(!empty($search_radius) && $distances[$box->ID] > $search_radius)
				$quantity = 0;
			$quantitystr = $quantity;
			if($quantityunits != "_") $quantitystr .= " $quantityunits";
			$location = get_post_meta($box_post, 'location', true);
			$locationstr = '';
			if(!empty($location)) $locationstr = " @ $location";

			if($quantity > 0){
				$box_details[$box_post] = array(
					'pic' => $pic,
					'good_type' => $good_type,
					'good_name' => $good_name,
					'quantity' => $quantity,
					'claimed_quantity' => $claimed_qty,
					'quantity_units' => $quantityunits,
					'quantity_str' => $quantitystr,
					'location' => $location,
					'location_str' => $locationstr,
					'latitude' => $box_lat,
					'longitude' => $box_long
				);

				if(in_array($good_type,$wishlist)){
					$wishlist_boxes[$key] = $box;
				}
			} else {
				unset($boxes[$key]);
			}
		}
	}
	$goods = array();
	if(empty($box_details)){
		foreach($goodslist as $good_type => $good_name){
			$in_wishlist = in_array($good_type,$wishlist) ? "true" : "false";
			$pic = get_the_post_thumbnail($good_type, array(47,47));
			if(empty($pic))
				$pic = "<img src='".get_template_directory_uri()."/img/default.png' width='47' height='47'>";
			$goods[$good_type] = array('good_type' => $good_type, 'good_name' => $good_name, 'pic' => $pic, 'in_wishlist' => $in_wishlist);
		}
	}

	global $box_distances;
	$box_distances = $distances;
	if($sort_by == 'nearby'){
		usort($boxes, "box_cmp_distance");
	} elseif($sort_by == 'category'){
		usort($boxes, "box_cmp_good");
	}

	return array('boxes' => $boxes, 'distances' => $distances, 'goodslist' => $goodslist, 'box_details' => $box_details, 'wishlist_boxes' => $wishlist_boxes, 'goods' => $goods, 'search_loc' => array('latitude' => $search_lat, 'longitude' => $search_long), 'no_wishlist' => ($no_wishlist == true));
}

/**
 * Finalize a reservation -- mark the goods as claimed.
 * @param int $claim_quantity The number being claimed
 * @param int $box_post The ID of the box post whose goods are being claimed
 * @return int The ID of the created reservation post, or WP_Error object
 */
function finalize_reservation($claim_quantity, $box_post){
	$reservation = false;
	$user_id = get_current_user_id();
	$quantity = get_post_meta($box_post, 'good_quantity', true);
	$claimed_qty = get_post_meta($box_post, 'claimed_quantity', true);
	if(empty($claimed_qty))$claimed_qty=0;
	$quantity -= $claimed_qty;
	if(empty($claim_quantity)) $claim_quantity = $quantity;
	if($claim_quantity > $quantity) {
		$reservation = new WP_Error('toomuch', __("There aren't that many available."));
	} else {
		$existing_reservations = get_posts(array("post_type" => "fcreservations", 'numberposts' => -1, 'post_status' => 'draft', 'author' => $user_id));
		foreach($existing_reservations as $res){
			if($res->post_title == $box_post){
				$qty = $res->post_excerpt;
				$claim_quantity += $qty;
				fc_release_reservation($res->ID);
			}
		}

		$reservation = wp_insert_post(array(
			'post_title' => $box_post,
			'post_content' => '',
			'post_status' => 'draft',
			'post_type' => 'fcreservations',
			'post_excerpt' => $claim_quantity
		), true);

		update_post_meta($box_post, 'claimed_quantity', ($claimed_qty+$claim_quantity));
	}

	return $reservation;
}

/**
 * Get detailed information about a given box of goods.
 * @param int $box_post The ID of the box post to get info for
 * @return array of box post details.
 */
function get_box_data($box_post){
	$result = false;
	if(!empty($box_post)){
		$bp = get_post($box_post);
		$user_info = get_userdata($bp->post_author);
		$giver = get_display_name($user_info);
		$affiliation = get_user_meta($bp->post_author, 'affiliation', true);
		$instructions = $bp->post_excerpt;
		$good_type = get_post_meta($box_post, 'good_type', true);
		$good_type_post = get_post($good_type);
		$good_name = strtolower($good_type_post->post_title);
		$quantity = get_post_meta($box_post, 'good_quantity', true);
		$quantityunits = get_post_meta($box_post, 'good_quantity_units', true);
		$claimed_qty = get_post_meta($box_post, 'claimed_quantity', true);
		if(empty($claimed_qty))$claimed_qty=0;
		$quantity -= $claimed_qty;
		$quantitystr = $quantity;
		if($quantityunits != "_") $quantitystr .= " $quantityunits";
		$location = get_post_meta($box_post, 'location', true);
		$locationstr = '';
		if(!empty($location)) $locationstr = " @ $location";
		$pic = get_the_post_thumbnail($box_post, array(47,47));
		if(empty($pic)){
			$pic = get_the_post_thumbnail($good_type, array(47,47));
			if(empty($pic))
				$pic = "<img src='".get_template_directory_uri()."/img/default.png' width='47' height='47'>";
		}
		$result = array(
			'box_post' => $box_post,
			'good_type' => $good_type,
			'good_name' => $good_name,
			'quantity' => $quantity,
			'quantity_units' => $quantityunits,
			'quantity_str' => $quantitystr,
			'giver' => $giver,
			'affiliation' => $affiliation,
			'location' => $location,
			'location_str' => $locationstr,
			'instructions' => $instructions,
			'pic' => $pic
		);
	}
	return $result;
}


/* ******************************************************************
	USED BY BASKET
****************************************************************** */

/**
 * Get a list of boxes in the user's "basket"
 * @return array of boxes as array(boxID => array(...details...), ...)
 */
function get_basket_list(){
	$user_id = get_current_user_id();
	$reservations = get_posts(array("post_type" => "fcreservations", 'numberposts' => -1, 'post_status' => 'draft', 'author' => $user_id ));
	$result = array();
	foreach($reservations as $res){
		$box_info = array();
		$box_post = $res->post_title;
		$bp = get_post($box_post);
		$gt = get_post_meta($box_post, 'good_type', true);
		$good_post = get_post($gt);
		$good_name = strtolower($good_post->post_title);
		$good_type = $good_post->post_title;
		$quantity = $res->post_excerpt;
		$quantityunits = get_post_meta($box_post, 'good_quantity_units', true);
		$quantitystr = $quantity;
		if($quantityunits != "_") $quantitystr .= " $quantityunits";
		$location = get_post_meta($box_post, 'location', true);
		$locationstr = '';
		if(!empty($location)) $locationstr = " @ $location";
		$pic = get_the_post_thumbnail($box_post, array(47,47));
		if(empty($pic)){
			$pic = get_the_post_thumbnail($gt, array(47,47));
			if(empty($pic))
				$pic = "<img src='".get_template_directory_uri()."/img/default.png' width='47' height='47'>";
		}
		$timeleft = 24*60*60 - (time() - strtotime($res->post_date));
		$expire = time() + $timeleft;
		$expiration = "<span class='expiration'><img src='".get_bloginfo('template_url')."/img/l/clock.png' width='20' height='20'> ".date("g:ia",$expire)."</span>";
		$instructions = $bp->post_excerpt;
		$user_info = get_userdata($bp->post_author);
		$giver = get_display_name($user_info);
		$result[$box_post] = array(
			'reservation' => $res->ID,
			'box_post' => $box_post,
			'good_type' => $gt,
			'good_name' => $good_name,
			'quantity' => $quantity,
			'quantity_units' => $quantityunits,
			'quantity_str' => $quantitystr,
			'expiration' => $expiration,
			'location' => $location,
			'location_str' => $locationstr,
			'instructions' => $instructions,
			'pic' => $pic,
			'giver' => $giver
		);
	}
	return $result;
}

/**
 * Mark a box (or a portion of one) as picked up.
 * @param int $reservation the ID of the reservation post
 * @return true on success, false on error
 */
function take_the_box($reservation,$taking_quantity){
	$success = true;
	$taken_box = wp_update_post(array(
		'ID' => $reservation,
		'post_status' => 'private',
		'post_excerpt' => $taking_quantity
	));
	if(is_wp_error($taken_box))
		$success = false;
	return $success;
}


/* ******************************************************************
	USED BY PROFILE
****************************************************************** */

function add_comment($box_post, $comment_content){
	$time = current_time('mysql');
	$user_id = get_current_user_id();
	$data = array(
		'comment_post_ID' => $box_post,
		// 'comment_author' => 'admin',
		// 'comment_author_email' => 'admin@admin.com',
		// 'comment_author_url' => 'http://',
		'comment_content' => $comment_content,
		// 'comment_type' => ,
		// 'comment_parent' => 0,
		'user_id' => $user_id,
		// 'comment_author_IP' => '127.0.0.1',
		// 'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
		'comment_date' => $time,
		'comment_approved' => 1,
	);

	return wp_new_comment($data);
}


/**
 * Get a list of the given user's sharing/foraging history.
 * @param int $user ID of the user whose history is needed
 * @return array of user's shared boxes and reservations
 */
function get_profile_history($user){
	$template_url = get_template_directory_uri();
	// $shared = array();
	$shared_boxes = get_posts(array("post_type" => "fcboxes", 'numberposts' => -1, 'post_status' => 'all', 'author' => $user));
	$foraged_boxes = get_posts(array("post_type" => "fcreservations", 'numberposts' => -1, 'post_status' => 'private', 'author' => $user ));
	// var_dump($foraged_boxes);
	$activity = array();
	$activity_boxes = array_merge($shared_boxes, $foraged_boxes);
	usort($activity_boxes,'box_cmp_date');
	foreach($activity_boxes as $box){
		if($box->post_type == "fcboxes" && $box->post_status != 'draft') {
			$box_post = $box->ID;
			$gt = get_post_meta($box_post, 'good_type', true);
			$good_post = get_post($gt);
			$good_name = strtolower($good_post->post_title);
			$good_type = $good_post->post_title;
			$quantity = get_post_meta($box_post, 'good_quantity', true);
			$units = "";
			$quantityunits = get_post_meta($box_post, 'good_quantity_units', true);
			$quantitystr = $quantity;
			if($quantityunits != "_") $quantitystr .= " $quantityunits";

			$date = strtotime($box->post_date);
			$midnight = strtotime("today");
			$yestermidnight = $midnight - 24*60*60;
			if($date >= $midnight) {
				$datestr = "Today";
			} elseif($date >= $yestermidnight) {
				$datestr = "Yesterday";
			} else {
				$datestr = round((time() - $date) / (24*60*60))." days ago";
			}

			$pic = get_the_post_thumbnail($box_post, array(47,47));
			if(empty($pic)){
				$pic = get_the_post_thumbnail($gt, array(47,47));
				if(empty($pic))
					$pic = "<img src='$template_url/img/default.png' width='47' height='47'>";
			}

			global $wpdb;
			$reservations = $wpdb->get_results( $wpdb->prepare( "SELECT post_author, post_excerpt FROM $wpdb->posts WHERE post_title = %s AND post_type = %s ORDER BY post_modified DESC", $box_post, 'fcreservations'), ARRAY_A);
			$foraged_qty = 0;
			$forager_name = "Anon";
			foreach($reservations as $row){
				if($foraged_qty == 0){
					$forager = get_userdata($row['post_author']);
					$forager_name = get_display_name($forager);
					$foraged_qty = $row["post_excerpt"];
				}
			}
			$statusmsg = "Nobody has claimed them yet.";
			if($foraged_qty > 0)
				$statusmsg = htmlentities("<strong>$forager_name</strong> took $foraged_qty");

			$activity[$box_post."b"] = array(
				'box_post' => $box_post,
				'post_type' => "fcboxes",
				'good_name' => $good_name,
				'quantity_str' => $quantitystr,
				'date_str' => $datestr,
				'status_msg' => $statusmsg,
				'pic' => $pic
			);
		} elseif($box->post_type == "fcreservations") {
			$res = $box;
			$box_post = $res->post_title;
			$bp = get_post($box_post);
			$gt = get_post_meta($box_post, 'good_type', true);
			$good_post = get_post($gt);
			$good_name = strtolower($good_post->post_title);
			$good_type = $good_post->post_title;
			$quantity = $res->post_excerpt;
			$units = "";
			$quantityunits = get_post_meta($box_post, 'good_quantity_units', true);
			$quantitystr = $quantity;
			if($quantityunits != "_") $quantitystr .= " $quantityunits";
			$location = get_post_meta($box_post, 'location', true);
			$locationstr = '';
			if(!empty($location)) $locationstr = " @ $location";
			$pic = get_the_post_thumbnail($box_post, array(47,47));
			if(empty($pic)){
				$pic = get_the_post_thumbnail($gt, array(47,47));
				if(empty($pic))
					$pic = "<img src='$template_url/img/default.png' width='47' height='47'>";
			}
			$instructions = $bp->post_excerpt;
			$user_info = get_userdata($bp->post_author);
			$giver = get_display_name($user_info);
			$date = strtotime($res->post_date);
			$midnight = strtotime("today");
			$yestermidnight = $midnight - 24*60*60;
			if($date >= $midnight) {
				$datestr = "Today";
			} elseif($date >= $yestermidnight) {
				$datestr = "Yesterday";
			} else {
				$datestr = round((time() - $date) / (24*60*60))." days ago";
			}

			$activity[$box_post."r"] = array(
				'reservation' => $res->ID,
				'post_type' => "fcreservations",
				'box_post' => $box_post,
				'good_name' => $good_name,
				'claim_quantity' => $quantity,
				'quantity_str' => $quantitystr,
				'date_str' => $datestr,
				'status_msg' => htmlentities("Given by <strong>$giver</strong>"),
				'pic' => $pic
			);
		}
	}

	// return array('shared' => $shared, 'foraged' => $foraged);
	return array('activity' => $activity);
}

?>