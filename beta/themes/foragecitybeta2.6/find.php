</div><div id="scroll-wrapper"><div id="content" role="main">
<?php
$template_url = get_template_directory_uri();
$user_id = get_current_user_id();
$wishlist = get_user_meta($user_id, 'wishlist');


$box_post = fc_get_form_value('box_post');
$action = fc_get_form_value('action', 'list');
if($action == "fc_load_find")
	$action = fc_get_form_value('subaction', 'list');
$good_type = fc_get_form_value('good_type');
$good_name = fc_get_form_value('good_name');
$available_quantity = fc_get_form_value('available_quantity');
$claim_quantity = fc_get_form_value('claim_quantity');
$back = fc_get_form_value('back');
$searched = fc_get_form_value('searched');

if($action == "finalize") {
	if(empty($claim_quantity) && $available_quantity == 1)
		$claim_quantity = 1;
	if(preg_match("/^[0-9]+$/",$claim_quantity) != 1) {
		$action = "box_detail";
		$claim_quantity = -1;
	}
	if(!empty($claim_quantity) && !empty($available_quantity) && $claim_quantity > $available_quantity) {
		$action = "box_detail";
		$claim_quantity = "toomany";
	}
}

$added_to_wishlist = false;
if($action == 'add_to_wishlist') {
	$action = 'box_detail';
	if(!empty($good_type)){
		delete_user_meta($user_id, 'wishlist', $good_type);
		add_user_meta($user_id, 'wishlist', $good_type);
		$added_to_wishlist = true;
	}
}
if($action == 'remove_from_wishlist') {
	$action = 'box_detail';
	if(!empty($good_type))
		delete_user_meta($user_id, 'wishlist', $good_type);
}

if(!empty($box_post)){
	$bp = get_post($box_post);
	if(is_null($bp) || "fcboxes" != $bp->post_type || "publish" != $bp->post_status){
		$box_post = "";
		$action = 'list';
	} else {
		$claimed_qty = get_post_meta($box_post, 'claimed_quantity', true);
	}
}

if($action == 'box_detail' && empty($box_post)){
	$action = 'list';
}

$prev = 'list';
if($action == 'finalize')
	$prev = 'box_details';

if(!empty($back)) {
	$action = $prev;
	if($action == 'list' && !empty($searched))
		$good_name = $searched;
}

if($action == "finalize") {

	// merge with any previous reservations
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
		'post_author' => $user_id,
		'post_excerpt' => $claim_quantity
	), true);

	update_post_meta($box_post, 'claimed_quantity', ($claimed_qty+$claim_quantity));
	echo "<p class='finalized-notice'>It's all yours! Visit your basket when you're ready to pick it up.</p>";
	$action = 'list';
}

$map_disabled = false;
if($action == 'list'){
	$DEFAULT_SORT = 'recent';
	$goup = home_url()."/find/";
	global $wp_query;
	$sort_by = $DEFAULT_SORT;
	// $goup = "";
	if(isset($wp_query->query_vars['sort_by'])) {
	// 	$goup = "../";
		$sort_by = $wp_query->query_vars['sort_by'];
	}

	$search_radius = get_user_meta($user_id, 'radius', true);
	// default radius 5 miles
	if(empty($search_radius)) $search_radius = "60";

	$searched = $good_name;
	$data = find_boxes($good_name, $sort_by);
	$boxes = $data['boxes'];
	$box_details = $data['box_details'];
	$distances = $data['distances'];
	$goodslist = $data['goodslist'];
	$empty_wishlist = false;
	$no_wishlist = false;
	$wishlist_boxes = $data['wishlist_boxes'];
	if($sort_by == "wishlist"){
		if(empty($wishlist_boxes)){
			$empty_wishlist = true;
			$no_wishlist = empty($wishlist);
			// $sort_by = "recent";
		} //else {
			$boxes = $wishlist_boxes;
		// }
	}
?>
<div id='good-type-search' class='find search-bar'>
	<form method='post'>
		<input type='text' name='good_name' value='<?php echo htmlspecialchars($good_name); ?>' />
		<input type='submit' value='Search' />
	</form>
</div>
<ul class="submenu">
	<li id="sort-by-wishlist"<?php if($sort_by == 'wishlist') echo ' class="current"'; /* else if($empty_wishlist) echo ' class="disabled"'; */ ?>><a <?php if(!$empty_wishlist) echo ' href="'.$goup.'wishlist/"'; ?>>Wishlist</a></li>
	<li id="sort-by-recent"<?php if($sort_by == 'recent') echo ' class="current"'?>><a href="<?php echo $goup; ?>recent/">Recent</a></li>
	<li id="sort-by-"<?php if($sort_by == "nearby") echo 'map"'; else echo 'nearby"'; if($sort_by == 'map' || $sort_by == 'nearby') echo ' class="current"'?>><a<?php if(!$map_disabled)echo ' href="'.$goup.$sort_by.'/"';?>>Nearby/Map</a></li>
</ul>
<?php
	if($sort_by == 'map'){ ?>
	<p class='jsnote'>Loading map...</p>
	<p class='nojsnote'>Map view requires JavaScript.</p><?php
	} else {
		$showline = false;
		foreach($boxes as $box){
			$box_post = $box->ID;
			$quantity = $box_details[$box_post]['quantity'];
			if($distances[$box->ID]>$search_radius)
				$quantity = 0;
			if($quantity > 0) {
				if($showline)
					echo "<p class='separating-line'></p>";
				$showline = true;
				$quantitystr = $box_details[$box_post]['quantity_str'];
				$locationstr = $box_details[$box_post]['location_str'];
				$good_name = $box_details[$box_post]['good_name'];
				$pic = $box_details[$box_post]['pic']; ?>
	<div class='box-o-goods find-box'>
		<div class='box-pic'><?php echo $pic; ?></div>
		<div class='box-info'>
			<form method='post'>
				<input type='hidden' name='searched' value='<?php echo htmlspecialchars($searched); ?>' />
				<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($box_post); ?>' />
				<input type='hidden' name='action' value='box_detail' />
			<p><?php echo "$good_name: $quantitystr"; ?><br><?php echo $locationstr; ?><span><?php printf('%.1F',$distances[$box->ID]); ?> mi</span> <input type='submit' value='claim' /></p>
			</form>
		</div>
	</div>
<?php
			}
		}
		if($no_wishlist && empty($boxes)) { ?>
	<p class='notice'>There’s nothing on your wishlist right now. You can add a good to your wishlist to make it easier to find the next time someone shares that item.</p>
<?php	}
		else if($empty_wishlist && empty($boxes)) { ?>
	<p class='notice'>Nobody's sharing anything on your wishlist right now. Check out what people have shared recently or nearby instead.</p>
<?php	}
	}
} else{
	if($searched || $good_name || $good_type) {
	// display "Back" button ?>
	<div id='back-button'>
		<form method='post'>
			<input type='hidden' name='action' value='<?php echo htmlspecialchars($prev); ?>' />
			<input type='hidden' name='good_name' value='<?php echo htmlspecialchars($good_name); ?>' />
			<input type='hidden' name='searched' value='<?php echo htmlspecialchars($searched); ?>' />
			<input type='hidden' name='good_type' value='<?php echo htmlspecialchars($good_type); ?>' />
			<input type='hidden' name='available_quantity' value='<?php echo htmlspecialchars($available_quantity); ?>' />
			<input type='hidden' name='claim_quantity' value='<?php echo htmlspecialchars($claim_quantity); ?>' />
			<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($box_post); ?>' />
			<input type='submit' name='back' value='Back' />
		</form>
	</div><?php
	}
	if($action == "box_detail") { 
		$bn = $bp->post_title;
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
		if(!empty($location)) $locationstr = " @ $location";
		$pic = get_the_post_thumbnail($box_post, array(47,47));
		if(empty($pic)){
			$pic = get_the_post_thumbnail($good_type, array(47,47));
			if(empty($pic))
				$pic = "<img src='$template_url/img/default.png' width='47' height='47'>";
		}
		$instructions = $bp->post_excerpt;
		$good_description = $bp->post_content;
		$user_info = get_userdata($bp->post_author);
		$giver = $user_info->first_name." ".substr($user_info->last_name,0,1)."."; ?>
	<div class='box-to-claim'>
		<div class='box-pic'><?php echo $pic; ?></div><?php
		if(!in_array($good_type,$wishlist)) {?>
		<form id='wishlist_form' method='post'>
			<input type='hidden' name='action' value='add_to_wishlist' />
			<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($box_post); ?>' />
			<input type='hidden' name='good_type' value='<?php echo htmlspecialchars($good_type); ?>' />
			<input id='add_to_wishlist' type='submit' value='Add <?php echo $good_name; ?> to wishlist' />
		</form><?php
		} else { ?>
		<form id='wishlist_form' method='post'>
			<input type='hidden' name='action' value='remove_from_wishlist' />
			<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($box_post); ?>' />
			<input type='hidden' name='good_type' value='<?php echo htmlspecialchars($good_type); ?>' /><?php
			if($added_to_wishlist){ ?>
			<h3><?php echo $good_type_post->post_title; ?> added to wishlist <input type='submit' value='Undo'/></h3><?php
			} else { ?>
			<h3><input id='remove_from_wishlist' type='submit' value='Remove <?php echo $good_name; ?> from wishlist' /></h3><?php
			} ?>
		</form><?php
		} ?>
		<div class='box-info'>
			<p><?php echo "$good_name: $quantitystr"; ?></p>
			<p>Given by <?php echo $giver; ?></p>
		</div><?php
		if(!empty($location) || !empty($instructions)) { ?>
		<div class='box-info'><?php
			if(!empty($location)) { ?>
			<h3>Pickup location</h3><p><?php echo $location; ?></p><?php
			} 
			if(!empty($instructions)) { ?>
			<h3>Instructions:::</h3><p><?php echo $instructions; ?></p><?php
			} ?>
			if(!empty($good_description)) { ?>
			<h3>Description</h3><p><?php echo $good_description; ?></p><?php
			} ?>
		</div><?php
		} ?>
		<div class='take-it'>
			<form method='post'>
				<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($box_post); ?>' />
				<input type='hidden' name='action' value='finalize' />
				<input type='hidden' name='available_quantity' value='<?php echo htmlspecialchars($quantity); ?>' /><?php
		if($quantity > 1) { ?>
				<p class='label'>Quantity</p>
				<p><span class="explanation">How many are you taking?</span><input type='number' id='claim_quantity' name='claim_quantity' value='<?php echo (($claim_quantity == -1 || $claim_quantity == "toomany") ? $quantity : $claim_quantity); ?>' /></p><?php
			if($claim_quantity == -1) { ?>
				<p class='error'>Invalid quantity. Please try again.</p><?php
			}
			if($claim_quantity == "toomany") { ?>
				<p class='error'>There aren't that many available. Please try again.</p><?php
			}
		} ?>
				<input id='reserve_lot' type='submit' value="Reserve this lot" />
			</form>
		</div>
	</div><?php
	}
}
?>