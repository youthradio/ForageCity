</div><div id="scroll-wrapper"><div id="content" role="main">
<?php
$template_url = get_template_directory_uri();
$user_id = get_current_user_id();

$action = fc_get_form_value('action', 'list');
if($action == "fc_load_give")
	$action = fc_get_form_value('subaction', 'list');
$good_type_post = fc_get_form_value('good_type_post');
$good_name = fc_get_form_value('good_name');
$quantity = fc_get_form_value('quantity');
$location = fc_get_form_value('location');
$latitude = fc_get_form_value('latitude');
$longitude = fc_get_form_value('longitude');
$instructions = fc_get_form_value('instructions');
$description = fc_get_form_value('description');
$good_units = fc_get_form_value('good_units');
if($good_units == "other"){
	$good_units = fc_get_form_value('good_units_other');
}
$create_box = fc_get_form_value('create_box');
$box_number = fc_get_form_value('box_number');
$box_post = fc_get_form_value('box_post');
$back = fc_get_form_value('back');
$searched = fc_get_form_value('searched');
$something_went_wrong = false;

/**
 * After processing a finalized shared box, go back to good type list
 */
if($action == 'finalize') {
	// If we don't have a box post, we're not actually ready for the
	// finalize step; go back to confirm
	if (empty($box_post))
		$action = 'confirm';
	else {
		$bp = get_post($box_post);
		if(!is_null($bp) && $bp->post_type == "fcboxes") {
			$shared_box = wp_update_post(array(
				'ID' => $box_post,
				'post_status' => 'publish'
			));
			if(is_wp_error($shared_box)){
				// do something?
				$something_went_wrong = true;
				$action = 'confirm';
			} else {
				$good_info = $good_quantity + " " + $good_units;
				if(!empty($good_units)) $good_info += " of";
				$good_info += " " + $good_name;
				// notify orgnization users within radius
				do_action('fc_notify_orgs', $box_post, $good_info);
				echo "Box shared. Share more?";
				$good_name = $good_type_post = '';
				$action = 'list';
			}
		} else {
			$something_went_wrong = true;
			$action = 'confirm';
		}
	}
}

// If we don't have quantity and location, can't go any futher than
// box_details step.
if($action == 'confirm' && (empty($quantity) || empty($location)))
	$action = 'box_details';

// If the user created a new good type to share, store it and continue
if($action == 'save_new_good_type'){
	$action = 'list';
	if(!empty($good_name)) {
		global $wpdb;
		// don't create dupes
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s", $good_name, 'fcgoods'));
		if($existing){
			$new_good = $existing;
			wp_update_post(array('ID' => $existing, 'post_status' => 'publish'));
		} else {
			// create the good type
			$new_good = wp_insert_post(array(
				'post_title' => $good_name,
				'post_status' => 'publish',
				'post_type' => 'fcgoods'
			));
		}
		if(is_wp_error($new_good)){
			// do something?
		} else {
			if(!empty($good_units))
				update_post_meta($new_good, 'good_quantity_units', $good_units);
			$good_type_post = $new_good;
			$action = 'box_details';
		}
	}
}

if($action == "list") {
	$args = array( 'post_type' => 'fcgoods', 'numberposts' => -1, 'order'=> 'ASC', 'orderby' => 'title' );
	$postslist = get_posts( $args );
	$goodslist = array();
	foreach($postslist as $post)
		$goodslist[$post->ID] = strtolower($post->post_title);

	if(!empty($back))
		$good_name = $searched;
	if(!empty($good_name)){
		$key = array_search(strtolower($good_name), $goodslist);
		if(empty($back) && $key) {
			$gtp = get_post($key);
			if(!is_wp_error($gtp)) {
				$good_type_post = $key;
				$action = 'box_details';
			}
		}
		$newgoodslist = preg_grep("/$good_name/i", $goodslist);
		if(empty($newgoodslist))
			$newgoodslist = preg_grep("/".str_replace("y","ie",$good_name)."/i", $goodslist);
		$goodslist = $newgoodslist;
		$searched = $good_name;
	}
}

if($action == 'box_details' && empty($good_type_post))
	$action = 'list';

$prev = 'list';
if($action == 'confirm')
	$prev = 'box_details';
if($action == 'finalize')
	$prev = 'confirm';

if($action == 'confirm' && empty($box_number)) $box_number = get_box_number();

if($action == 'list') { ?>
	<div id='good-type-search' class='give search-bar'>
		<form method='post'>
			<input type='text' name='good_name' value='<?php echo htmlspecialchars($good_name); ?>' />
			<input type='submit' value='Search' />
		</form>
	</div><div class='spacer-for-search-bar'></div><?php
	if(!empty($good_name) && $key == false) { ?>
	<div id='create-good-type'>
		<form method='post'>
			<input type='hidden' name='good_name' value='<?php echo htmlspecialchars($good_name); ?>' />
			<input type='hidden' name='action' value='create_good_type' />
			<input type='submit' value='Add "<?php echo htmlspecialchars($good_name); ?>"' />
		</form>
	</div><?php
	} ?>
	<?php
	$showline=false;
	foreach($goodslist as $gtp=>$gt) {
		if($showline)
			echo "<p class='separating-line'></p>";
		$showline = true;
		$pic = get_the_post_thumbnail($gtp, array(47,47));
		if(empty($pic))
			$pic = "<img src='$template_url/img/default.png' width='47' height='47'>";
		?>
	<div class='box-o-goods give-box'>
		<div class='box-pic'><?php echo $pic; ?></div>
		<div class='box-info'>
			<form method='post' class='good-type-list'>
				<input type='hidden' name='good_type_post' value='<?php echo htmlspecialchars($gtp); ?>' />
				<input type='hidden' name='good_name' value='<?php echo htmlspecialchars($gt); ?>' />
				<input type='hidden' name='searched' value='<?php echo htmlspecialchars($searched); ?>' />
				<input type='hidden' name='action' value='box_details' />
				<p><?php echo $gt; ?>	<input type='submit' value='<?php echo htmlspecialchars($gt); ?>' /></p>
			</form>
		</div>
	</div><?php
	}
} else {
	// display "Back" button ?>
	<div id='back-button'>
		<form method='post'>
			<input type='hidden' name='action' value='<?php echo htmlspecialchars($prev); ?>' />
			<input type='hidden' name='good_name' value='<?php echo htmlspecialchars($good_name); ?>' />
			<input type='hidden' name='searched' value='<?php echo htmlspecialchars($searched); ?>' />
			<input type='hidden' name='good_type_post' value='<?php echo htmlspecialchars($good_type_post); ?>' />
			<input type='hidden' name='quantity' value='<?php echo htmlspecialchars($quantity); ?>' />
			<input type='hidden' name='location' value='<?php echo htmlspecialchars($location); ?>' />
			<input type='hidden' name='latitude' value='<?php echo htmlspecialchars($latitude); ?>' />
			<input type='hidden' name='longitude' value='<?php echo htmlspecialchars($longitude); ?>' />
			<input type='hidden' name='instructions' value='<?php echo htmlspecialchars($instructions); ?>' />
			<input type='hidden' name='description' value='<?php echo htmlspecialchars($description); ?>' />
			<input type='hidden' name='good_units' value='<?php echo htmlspecialchars($good_units); ?>' />
			<input type='hidden' name='create_box' value='<?php echo htmlspecialchars($create_box); ?>' />
			<input type='hidden' name='box_number' value='<?php echo htmlspecialchars($box_number); ?>' />
			<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($box_post); ?>' />
			<input type='submit' name='back' value='Back' />
		</form>
	</div><?php
/* ?>	<pre>searched: '<?php echo htmlspecialchars($searched); ?>'</pre><?php */
	if($action == 'create_good_type') { ?>
	<div id='save-good-type'>
		<form method='post'>
			<input type='hidden' name='good_name' value='<?php echo htmlspecialchars($good_name); ?>' />
			<p class='label'>Quantity Units</p>
			<p><span class='explanation' id='good_units_explanation'>How do you usually count this good? (e.g., lbs, boxes, bunches, jars, etc.)</span><input type='text' id='good_units' name='good_units' value='' /></p>
			<input type='hidden' name='action' value='save_new_good_type' />
			<input type='submit' value='Continue' />
		</form>
	</div><?php
	} elseif($action == 'box_details') {
		if(empty($good_name)){
			$gtp = get_post($good_type_post);
			$good_name = strtolower($gtp->post_title);
		} ?>
		<div id='add-box'>
			<form method='post'>
				<p class='note strong'>Giving <?php echo $good_name; ?>:</p>
				<p class='label'>Quantity Units</p>
				<p>
					<select id='good_units' name='good_units' onchange='if(this.value == "other"){jQuery(".show-for-other").show();jQuery("input.show-for-other").focus();}else jQuery(".show-for-other").hide();'>
						<option value=""><?php echo $good_name; ?></option>
						<option value="oz">oz(s)</option>
						<option value="lb">lb(s)</option>
						<option value="bag">bag(s)</option>
						<option value="box">box(es)</option>
						<option value="can">can(s)</option>
						<option value="jar">jar(s)</option>
						<option value="other">other</option>
					</select>
					<span class="explanation show-for-other" style='display:none;margin-top:20px;'>How do you count this good?</span><input type='text' class='show-for-other' style='display:none;' id='good_units_other' name='good_units_other' value='<?php echo htmlspecialchars($good_units); ?>' />
				</p>
				<p class='label'>Quantity</p>
				<p><span class="explanation">How much have you got?</span><input id='quantity' type='number' name='quantity' value='<?php echo htmlspecialchars($quantity); ?>' /></p>
				<p class='label'>Address</p>
				<p><span class="explanation explanation-small"><?php echo empty($bad_location) ? "What is the drop off address? (street, city)" : $bad_location;?></span><input type='text' id='location' name='location' value='<?php echo htmlspecialchars($location); ?>' /><?php if(!empty($bad_location)) : ?><br>
				<span class="description error">I can't find that.</span>
		<?php endif; ?></p>
				<script type="text/javascript" charset="utf-8">
					if(navigator.geolocation) {
						navigator.geolocation.getCurrentPosition(function(position) {
							geoLoc = position.coords;
							var elt=document.getElementById("location"),
								eltp=elt.parentNode;
							var geocoder = new google.maps.Geocoder(),
								latlng = new google.maps.LatLng(position.coords.latitude,position.coords.longitude);
							elt.value = "current location";
							eltp.getElementsByTagName("span")[0].className += " explanation-hidden";
							latinp = document.createElement("input");
							latinp.value = position.coords.latitude;
							latinp.name = "latitude";
							latinp.type = "hidden";
							eltp.appendChild(latinp);
							longinp = document.createElement("input");
							longinp.value = position.coords.longitude;
							longinp.name = "longitude";
							longinp.type = "hidden";
							eltp.appendChild(longinp);
							geocoder.geocode({'latLng': latlng}, function(results, status) {
								if (status == google.maps.GeocoderStatus.OK) {
									if (results[0]) {
										elt.value = results[0].formatted_address;
									}
								}
							});
						}, function() {});
					}
				</script>
				<p class='label'>Pickup Instructions</p>
				<p><span class="explanation explanation-small">How do I find it? (e.g. behind the front desk)</span><input id='instructions' type='text' name='instructions' value='<?php echo htmlspecialchars($instructions); ?>' /></p>
				<p class='label'>Description</p>
				<p><span class="explanation">What's special about your goods?</span><input type='text' id='description' name='description' value='<?php echo htmlspecialchars($description); ?>' /></p>
				<input type='hidden' name='good_name' value='<?php echo htmlspecialchars($good_name); ?>' />
				<input type='hidden' name='good_type_post' value='<?php echo htmlspecialchars($good_type_post); ?>' />
				<input type='hidden' name='box_number' value='<?php echo htmlspecialchars($box_number); ?>' />
				<input type='hidden' name='action' value='confirm' />
				<input class='submit' type='submit' name='create_box' value='Continue' />
			</form>
		</div><?php
	} elseif($action == 'confirm') {
		// if(empty($box_number)) $box_number = get_box_number();
		$new_box = wp_insert_post(array(
			'post_title' => $box_number,
			'post_content' => $description,
			'post_status' => 'draft', 
			'post_type' => 'fcboxes',
			'post_author' => $user_id,
			'post_excerpt' => $instructions
		), true);
		if(is_wp_error($new_box)){
			// do something?
			// var_dump($new_box);
		} else {
			update_post_meta($new_box, 'good_quantity', $quantity);
			update_post_meta($new_box, 'good_type', $good_type_post);
			if(!empty($good_units))
				update_post_meta($new_box, 'good_quantity_units', $good_units);
			$geocode = false;
			$bad_location = "";
			if(!empty($location)){
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
			}
			if($geocode){
				update_post_meta($new_box, 'location', $location);
				update_post_meta($new_box, 'latitude', $geocode['lat']);
				update_post_meta($new_box, 'longitude', $geocode['long']);
			}
			$pic = get_the_post_thumbnail($new_box, array(47,47));
			if(empty($pic)){
				$pic = get_the_post_thumbnail($good_type_post, array(47,47));
				if(empty($pic))
					$pic = "<img src='$template_url/img/default.png' width='47' height='47'>";
			} ?>
		<div id='added-box'>
			<div id='box-pic'><?php echo $pic; ?></div>
			<div id='box-info'>
				<p id='box-quantity'><?php echo "$good_name: $quantity $good_units"; ?></p>
				<p class='strong'>Pickup location</p>
				<p id='box-location'><?php echo "$location"; ?></p><?php
			if($geocode){
				$latitude = $geocode['lat'];
				$longitude = $geocode['long'];
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
			<hr>
			<div id='what-to-do'>
				<p>Write this number on your container of goods, then press share so that they can be publicly listed.</p>
				<p id='box-number'><?php echo $box_number; ?></p>
				<form method='post'>
					<input type='hidden' name='good_name' value='<?php echo htmlspecialchars($good_name); ?>' />
					<input type='hidden' name='quantity' value='<?php echo htmlspecialchars($quantity); ?>' />
					<input type='hidden' name='good_units' value='<?php echo htmlspecialchars($good_units); ?>' />
					<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($new_box); ?>' />
					<input type='hidden' name='action' value='finalize' />
				<p><input id='share_it' type='submit' value='Share It' /></p>
				</form>
			</div>
		</div><?php
		}
	}
} ?>