</div><?php
$DEFAULT_RADIUS = "60"; //miles

$template_url = get_template_directory_uri();
$user_id = get_current_user_id();
$user = fc_get_form_value('user', $user_id);

$default_action = 'view';
global $wp_query;
if(isset($wp_query->query_vars['action'])) {
	$default_action = $wp_query->query_vars['action'];
}
$action = fc_get_form_value('action', $default_action);
if($action == "fc_load_profile")
	$action = fc_get_form_value('subaction', $default_action);
$box_post = fc_get_form_value('box_post');
$comment_content = fc_get_form_value('comment_content');

$bp = get_post($box_post);

if($action == 'add_comment' && !empty($box_post) && !empty($comment_content)){
	add_comment($box_post, $comment_content);
	$old_action = fc_get_form_value('old_action', $default_action);
}

if($action == 'remove_box' && !empty($box_post)){
	if(!is_null($bp) && $user_id == $bp->post_author){
		$reservations = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type= %s", $box_post, 'fcreservations'));
		foreach($reservations as $row){
			wp_delete_post($row->ID);
		}
		if(wp_delete_post($box_post)){
			echo "<p class='finalized-notice'>Okay, your box has been taken off the list.</p>";
		}
	}
	$action = 'view';
}

$profile_errors = array();

if($action == 'update' && !empty($_POST['user_id']) && isset($_POST['from']) && $_POST['from'] == 'profile'){
	$action = "view";
	$user_to_edit = $_POST['user_id'];
	require_once(ABSPATH . 'wp-admin/includes/user.php');
	$result = edit_user($user_to_edit);
	if(is_wp_error($result)) {
		$profile_errors = $result->errors;
		$action = "edit";
	}


	$first_name = fc_get_form_value('first_name');
	$last_name = fc_get_form_value('last_name');
	$email = fc_get_form_value('email');
	$description = fc_get_form_value('description');
	$address = fc_get_form_value('address');
	$latitude = fc_get_form_value('latitude');
	$longitude = fc_get_form_value('longitude');
	$search_radius = fc_get_form_value('search_radius');
	$affiliation = fc_get_form_value('affiliation');
	$email_notifications = fc_get_form_value('email_notifications');

	if($email_notifications == "yes")
		update_user_meta($user_to_edit, 'email_notifications', $email_notifications);
	else
		delete_user_meta($user_to_edit, 'email_notifications');

	if(empty($affiliation))
		delete_user_meta($user_to_edit, 'affiliation');
	else
		update_user_meta($user_to_edit, 'affiliation', $affiliation);

	if(!empty($address)){
		$geocode = false;
		if(empty($latitude) || empty($longitude)){
			$geocode = getGeocode($address);
		} else {
			$geocode = array();
			$geocode['lat'] = $latitude;
			$geocode['long'] = $longitude;
		}
		if($geocode){
			update_user_meta($user_to_edit, 'address', $address);
			update_user_meta($user_to_edit, 'latitude', $geocode['lat']);
			update_user_meta($user_to_edit, 'longitude', $geocode['long']);
		} else {
			$profile_errors['address'] = 'geocode';
			$action = "edit";
		}
	}
	if(!empty($search_radius))
		update_user_meta($user_to_edit, 'radius', $search_radius);
}


$current_user_data = get_userdata($user);
$username = $current_user_data->user_login;

if(count($profile_errors) == 0){
	$first_name = $current_user_data->first_name;
	$last_name = $current_user_data->last_name;
	$email = $current_user_data->user_email;
	$description = $current_user_data->user_description;
	$address = get_user_meta($user, 'address', true);
	$latitude = get_user_meta($user, 'latitude', true);
	$longitude = get_user_meta($user, 'longitude', true);
	$search_radius = get_user_meta($user, 'radius', true);
	$affiliation = get_user_meta($user, 'affiliation', true);
	if(empty($search_radius)) $search_radius = $DEFAULT_RADIUS;
}


if(!empty($first_name)) {
	if(!empty($last_name)) {
		$display_name = $first_name." ".substr($last_name,0,1).".";
	}
} else {
	$display_name = $current_user_data->display_name;
}

if($action == "view") {
	echo "
	<form method='post' class='top-right-button'>
		<input type='hidden' name='action' value='edit' />
		<input id='edit_profile' type='submit' value='Settings' />
	</form>";
}
?>
<div id="scroll-wrapper"><div id="content" role="main">
<div id='user-info'>
<?php if($action == "edit") { ?>
	<div id='logout'><p><a href='<?php echo wp_logout_url(); ?>'>Logout</a></p></div>
	<form id="your-profile" method="post" action="<?php echo home_url(); ?>/profile/" class="profile-form">
		<?php wp_nonce_field('update-user_' . $user_id) ?>

		<table class="form-table"><tbody>
		<tr>
			<th><label for="user_login">Username</label></th>
			<td><input type="text" name="user_login_cannot_change" id="user_login_cannot_change" value="<?php echo $username; ?>" disabled="disabled" class="regular-text" /><!-- br><span class="description">(Usernames cannot be changed.)</span --></td>
		</tr>

		<tr>
			<th><label for="first_name">First Name</label></th>
			<td><input type="text" name="first_name" id="first_name" value="<?php echo $first_name; ?>" class="regular-text" /></td>
		</tr>

		<tr>
			<th><label for="last_name">Last Name</label></th>
			<td><input type="text" name="last_name" id="last_name" value="<?php echo $last_name; ?>" class="regular-text" /></td>
		</tr>

		<tr>
			<th><label for="email">E-mail <span class="description">(required)</span></label></th>
			<td><input type="text" name="email" id="email" value="<?php echo $email; ?>" class="regular-text" /><?php if(isset($profile_errors['email_exists'])) : ?><br>
			<span class="description error"><strong>NOT UPDATED</strong>: The email you entered is already registered.</span>
	<?php endif; ?></td>
		</tr>

		<tr>
			<th><label for="address">Default Location</label></th>
			<td><input type="text" name="address" id="address" value="<?php echo $address; ?>" class="regular-text" /><?php if(isset($profile_errors['address'])) : ?><br>
			<span class="description error"><strong>Bad address</strong>: I couldn't find you.</span>
	<?php endif; ?></td>
		</tr>

		<tr>
			<th><label for="search_radius">Default Search Radius</label></th>
			<td><input type="text" name="search_radius" id="search_radius" value="<?php echo $search_radius; ?>" class="regular-text" /> 
			<span class="description">(in miles)</span></td>
		</tr>

		<tr>
			<th><label for="description">Biographical Info</label></th>
			<td><textarea name="description" id="description" rows="5" cols="30"><?php echo $description; ?></textarea> 
			<span class="description">Share a little biographical information to fill out your profile. This will be shown publicly.</span></td>
		</tr>

		<tr>
			<th><label for="affiliation">Affiliation</label></th>
			<td><input type="text" name="affiliation" id="affiliation" value="<?php echo $affiliation; ?>" class="regular-text" /> 
			<span class="description">If you are affiliated with a particular foraging group (e.g. Forage Oakland), you can note that here.</span></td>
		</tr>

		<?php
		$wpuser = new WP_User( $user_id );
		if($wpuser->roles[0] == "organization") {
		 	$email_notifications = get_user_meta($user_id, 'email_notifications', true); ?>
		<tr>
			<th><label for="email_notifications">Email Notifications</label></th>
			<td>
			<span class="description"><input type="checkbox" name="email_notifications" id="email_notifications" value="yes" <?php if($email_notifications == "yes") echo 'checked="checked"'; ?> /> Check this box if you would like to receive notification via email when someone shares goods near you. (Make sure your default location is set correctly!)</span></td>
		</tr>
		<?php }else{ ?>
		<tr>
			<th><label for="email_notifications">Email Notifications</label></th>
			<td>
			<span class="description">Do you work for an org that serves your community? Let us know how you'll share Forage City goods, and we'll email you when goods show up near you. (Make sure your default location is set correctly!). <a href="mailto:lissa@youthradio.org?subject=I would like community provider status in Forage City!">Email Forage City</a></span></td>
		</tr>		
		<?php } ?>
		<tr id="password">
			<th><label for="pass1">New Password</label></th>
			<td><input type="password" name="pass1" id="pass1" size="16" value="" autocomplete="off" /> <span class="description">If you would like to change your password, type a new one. Otherwise leave this blank.</span><br>
				<input type="password" name="pass2" id="pass2" size="16" value="" autocomplete="off" /> <span class="description">Type your new password again.</span>
			<?php if(isset($profile_errors['pass'])) {
				foreach($profile_errors['pass'] as $err) { ?><br>
				<span class="description error"><?php echo $err; ?></span>
		<?php }} ?></td>
		</tr>

		<?php do_action('fc_profile_extras', true); ?>

		</tbody></table>

		<input type="hidden" name="from" value="profile" />
		<input type="hidden" name="action" value="update" />
		<input type="hidden" name="user_id" id="user_id" value="<?php echo $user_id; ?>" />

		<p class="submit"><input type="submit" id="save_profile_edits" class="button-primary" value="Update Profile" /></p>
	</form>
	<form method="post" id="cancel_profile_edit" action="<?php echo home_url(); ?>/profile/" class="profile-form"><p class="submit"><input type="submit" class="button-primary" value="Cancel" /></p></form>
<?php } elseif($action == 'view') {
	$shared = get_posts(array("post_type" => "fcboxes", 'numberposts' => -1, 'post_status' => 'all', 'author' => $user));
	$foraged = get_posts(array("post_type" => "fcreservations", 'numberposts' => -1, 'post_status' => 'private', 'author' => $user ));

	$activity = array_merge($shared, $foraged);
	usort($activity, "box_cmp_date");
	$profile_pic = "$template_url/img/person.png";
	$profile_pic = apply_filters("fc_profile_pic",$profile_pic, $user);
	// "http://graph.facebook.com/kangaechigai/picture?type=square";
	$display_name = "$first_name ".substr($last_name,0,1).".";
	if($display_name == " .")
		$display_name = "Anonymous";

	echo "<div id='user-pic'><img src='$profile_pic' width='44' height='44' alt='person' title='person'></div><h3>$display_name</h3>";
	echo "<p class='user-bio'>$description</p>";
	echo "<h1>History</h1><div id='user-history'>";
	$drafts = 0;
	$showline = "";
	foreach($activity as $box){
		if($box->post_status == 'draft')
			$drafts++;
		else {
			if($box->post_type == "fcboxes") :
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
				$claimed_qty = get_post_meta($box_post, 'claimed_quantity', true);
				if(empty($claimed_qty))$claimed_qty=0;
				if($claimed_qty > 0) {
					$quantity -= $claimed_qty;
					if($quantity > 0)
						$quantitystr .= " ($quantity remaining)";
					else
						$quantitystr .= " (fully foraged)";
				}

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


				$reservations = $wpdb->get_results( $wpdb->prepare( "SELECT post_author, post_excerpt FROM $wpdb->posts WHERE post_title = %s AND post_type = %s ORDER BY post_modified DESC", $box_post, 'fcreservations'), ARRAY_A);
				$foraged_qty = 0;
				$forager_name = "Anon";
				foreach($reservations as $row){
					if($foraged_qty == 0){
						$forager = get_userdata($row['post_author']);
						$forager_name = $forager->first_name." ".substr($forager->last_name,0,1).".";
						$foraged_qty = $row["post_excerpt"];
					}
				}


				$pic = get_the_post_thumbnail($box_post, array(47,47));
				if(empty($pic)){
					$pic = get_the_post_thumbnail($gt, array(47,47));
					if(empty($pic))
						$pic = "<img src='$template_url/img/default.png' width='47' height='47'>";
				} ?>
			<div class='box-o-goods profile-shared-box<?php echo $showline; ?>'>
				<div class='box-pic'><?php echo $pic; ?></div>
				<div class='box-info'>
					<form method='post'>
						<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($box_post); ?>' />
						<input type='hidden' name='action' value='edit_shared' />
					<p><?php echo "$good_name: $quantitystr"; ?><br /><?php if($foraged_qty == 0) echo "Nobody has claimed them yet."; else echo "<strong>$forager_name</strong> took $foraged_qty" ?><br /><span class='history-date'>Given <?php echo $datestr; ?></span> <input type='submit' value='Detail' /></p>
					</form>
				</div>
			</div><?php else :
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
				$giver = $user_info->first_name." ".substr($user_info->last_name,0,1).".";

				$date = strtotime($res->post_date);
				$midnight = strtotime("today");
				$yestermidnight = $midnight - 24*60*60;
				if($date >= $midnight) {
					$datestr = "Today";
				} elseif($date >= $yestermidnight) {
					$datestr = "Yesterday";
				} else {
					$datestr = round((time() - $date) / (24*60*60))." days ago";
				} ?>
				<div class='box-o-goods foraged-box<?php echo $showline; ?>'>
					<div class='box-pic'><?php echo $pic; ?></div>
					<div class='box-info'>
						<form method='post'>
							<input type='hidden' name='reservation' value='<?php echo htmlspecialchars($res->ID); ?>' />
							<input type='hidden' name='claim_quantity' value='<?php echo htmlspecialchars($quantity); ?>' />
							<input type='hidden' name='action' value='take' />
							<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($box_post); ?>' />
						<p><?php echo "$good_name: $quantitystr"; ?><br />Given by <strong><?php echo $giver; ?></strong><br /><span class='history-date'>Foraged <?php echo $datestr; ?></span> <input type='submit' value='Take' /></p>
						</form>
					</div>
				</div><?php
			endif;
			$showline = " line-above";
		}
	}
	echo "</div>";
} elseif($action == 'edit_shared') {
	$box_number = $bp->post_title;
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
			$pic = "<img src='$template_url/img/default.png' width='47' height='47'>";
	}
	$instructions = $bp->post_excerpt;
	$description = $bp->post_content; ?>
	<div id='shared-box'>
		<div id='box-pic'><?php echo $pic; ?></div>
		<div id='box-info'>
			<p id='box-quantity'><?php echo "$good_name: $quantity $quantityunits"; ?></p>
			<p class='strong'>Pickup location</p>
			<p id='box-location'><?php echo $location; ?></p><?php
		if(!empty($latitude) &&!empty($longitude)) {
		echo "<p style='text-align:center;'><a href='http://maps.google.com/maps?q=".urlencode($location)."&ll=$latitude,$longitude' target='_blank'><img width='300' height='150' src='http://maps.google.com/maps/api/staticmap?center=$latitude,$longitude&zoom=15&size=300x150&maptype=roadmap&markers=$latitude,$longitude&sensor=true'></a></p>";
	} ?>
		</div><?php
	if(!empty($instructions)) { ?>
		<div id='box-instructions'>
			<p class='strong'>Instructions</p>
			<p><?php echo $instructions; ?></p>
		</div><?php
	}
	if(!empty($description)) { ?>
		<div id='box-description'>
			<p class='strong'>Description</p>
			<p><?php echo $description; ?></p>
		</div><?php
	} ?>
		<div id='what-to-do'>
			<p class='strong'>Box number</p>
			<p id='box-number'><?php echo $box_number; ?></p>
		</div>
		<br>
		<div id='comments'>
			<?php
			$comments = get_comments(array('post_id' => $bp->ID));
			foreach($comments as $com){
				$current_user_data = get_userdata($com->user_id);
				$first_name = $current_user_data->first_name;
				$last_name = $current_user_data->last_name;
				if(!empty($first_name)) {
					if(!empty($last_name)) {
						$display_name = $first_name." ".substr($last_name,0,1).".";
					}
				} else {
					$display_name = $current_user_data->display_name;
				}
				echo "<p class='comment'><strong>$display_name</strong> says \"".$com->comment_content."\"</p>";
			} ?>
		</div>
		<form method='post' id='comment_form'>
			<input type='hidden' name='box_post' value='<?php echo $box_post; ?>' />
			<input type='hidden' name='old_action' value='edit_shared' />
			<input type='hidden' name='action' value='add_comment' />
			<p><textarea name="comment_content" id="comment_content" rows="3" cols="30"></textarea></p>
			<p><input id='post_comment' type='submit' value='Add Comment' /></p>
		</form>
		<form method='post'>
			<input type='hidden' name='box_post' value='<?php echo $box_post; ?>' />
			<input type='hidden' name='action' value='remove_box' />
			<p><input id='remove_shared_box' type='submit' value='Remove this box' /></p>
		</form>
	</div><?php
} ?>
</div>