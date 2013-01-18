</div><div id="scroll-wrapper"><div id="content" role="main">
<?php
$user_id = get_current_user_id();
$template_url = get_template_directory_uri();

$action = fc_get_form_value('action', 'list');
if($action == "fc_load_basket")
	$action = fc_get_form_value('subaction', 'list');
$reservation = fc_get_form_value('reservation');
$box_post = fc_get_form_value('box_post');
$box_number = fc_get_form_value('box_number');
$claim_quantity = fc_get_form_value('claim_quantity');
$taking_quantity = fc_get_form_value('taking_quantity');

$attempted = $action;

if(empty($reservation))
	$action = "list";
else {
	$rp = get_post($reservation);
	if(is_null($rp))
		$action = "list";
	else {
		$box_post = $rp->post_title;
		$bp = get_post($box_post);
		if(is_null($bp))
			$action = "list";
		else
			$bn = $bp->post_title;
	}
}

$released_note = '';
if($action == "release") {
	$action = "list";
	if(!empty($reservation) && fc_release_reservation($reservation)){
		$released_note = "<p class='afternote released'>Goods released.</p>";
	}
}

if($action == "finalize" && (empty($claim_quantity) || empty($box_number) || $box_number != $bn))
	$action = "take";

if ($action == 'finalize') {
	if(!empty($taking_quantity) && $taking_quantity != $claim_quantity){
		$extra = $taking_quantity - $claim_quantity;
		$claimqty = get_post_meta($box_post, 'claimed_quantity', true);
		$claimqty += $extra;
		update_post_meta($box_post, 'claimed_quantity', $claimqty);
	}
	$taken_box = wp_update_post(array(
		'ID' => $reservation,
		'post_status' => 'private',
		'post_excerpt' => $taking_quantity
	));
	if(is_wp_error($taken_box)){
		// do something?
		// var_dump($taken_box);
		$action = 'take';
	} else {
		$giver = new WP_User( $bp->post_author );
		if($giver->roles[0] == "organization"){
			$giver_data = get_userdata( $bp->post_author );
			$to = $giver_data->email;
			$subject = "Forage City -- Someone picked up your shared goods";
			$message = "Someone took ".$taking_quantity." ".get_good_name( $box_post );
			wp_mail( $to, $subject, $message );
		}
		// update_post_meta($box_post, 'taken_by', $user_id);
		echo "Okay, it's all yours!";
		$action = 'list';
	}
}

if($action == "take") {
	$good_type = get_post_meta($box_post, 'good_type', true);
	$good_type_post = get_post($good_type);
	$count_type = get_post_meta($good_type, 'good_quantity_type', true);
	$good_name = strtolower($good_type_post->post_title);
	$quantity = $rp->post_excerpt;
	$quantityunits = get_post_meta($box_post, 'good_quantity_units', true);
	$quantitystr = $quantity;

$claimed_qty = get_post_meta($box_post, 'claimed_quantity', true);
$avail_quantity = get_post_meta($box_post, 'good_quantity', true);
if(empty($claimed_qty))$claimed_qty=0;
$avail_quantity -= $claimed_qty;
$avail_quantity += $quantity;
$avail_quantitystr = $avail_quantity;
if($quantityunits != "_") $avail_quantitystr .= " $quantityunits";

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
	$user_info = get_userdata($bp->post_author);
	$giver = $user_info->first_name." ".substr($user_info->last_name,0,1)."."; ?>
		<div class='box-to-claim'>
			<div class='box-pic'><?php echo $pic; ?></div>
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
				<h3>Instructions</h3><p><?php echo $instructions; ?></p><?php
		} ?>
			</div><?php
	} ?>
			<div class='pickup'>
				<form method='post'>
					<input type='hidden' name='reservation' value='<?php echo htmlspecialchars($rp->ID); ?>' />
					<input type='hidden' name='claim_quantity' value='<?php echo htmlspecialchars($quantity); ?>' />
					<input type='hidden' name='action' value='finalize' />
					<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($box_post); ?>' />
					<?php if($avail_quantity > 0) : ?>
					<p class='label'>How many are you taking?</p>
					<p><span class="explanation explanation-hidden"></span><input type='number' id='taking_quantity' name='taking_quantity' min='0' max='<?php echo htmlspecialchars($avail_quantity); ?>' value='<?php echo htmlspecialchars($quantity); ?>' /></label></p><p style="margin-bottom:20px;">(There are <?php echo htmlspecialchars($avail_quantitystr); ?> available. You reserved <?php echo htmlspecialchars($quantity); ?>.)</p>
					<?php endif; ?>
					<p>Once you see the items, please enter the code to check them out.</p>
					<input type='number' class='box-number' size='5' name='box_number' min='0' max='99999' step='1' value='' />
					<?php if($attempted == "finalize"){?>
						<p class='error'>Incorrect box number. Please try again.<?php echo $bn; ?></p>
					<?php } ?>
					<input id="take_lot" type='submit' value="I'm taking this!" />
				</form>
			</div>
			<form id="reservation-release-form" method='post'>
				<input type='hidden' name='reservation' value='<?php echo htmlspecialchars($rp->ID); ?>' />
				<input type='hidden' name='action' value='release' />
				<input type='submit' value="Release these goods." />
			</form>
			<form id="reservation-problem-form" method='post'>
				<p class="label">Something wrong?</p>
				<input type='hidden' name='reservation' value='<?php echo htmlspecialchars($rp->ID); ?>' />
				<input type='hidden' name='action' value='flag' />
				<p><input type='radio' name='problem' value='missing' />	Nothing here</p>
				<p><input type='radio' name='problem' value='rotten' />	The items are rotten</p>
				<p><input type='radio' name='problem' value='other' onchange="if(jQuery('input:radio[name=problem]:checked').val()=='other'){jQuery('#other_problem').removeAttr('disabled');jQuery('#other_problem').focus()}else jQuery('#other_problem').attr('disabled','disabled');" />	<span class="explanation">Other</span><input type='text' name='other_problem' id='other_problem' disabled="disabled" /></p>
				<input type='submit' value="Flag this box" />
			</form>
		</div><?php
} else /* if (action == "list") */ {
	$reservations = get_posts(array("post_type" => "fcreservations", 'numberposts' => -1, 'post_status' => 'draft', 'author' => $user_id ));
	echo "<div id='pickup-notice'>
	<h2>ITEMS THAT I NEED TO PICK UP</h2>
	<p class='basket_count'>".count($reservations)."</p>
</div>";
	echo $released_note;
	$showline = false;
	foreach($reservations as $res){
		if($showline)
			echo "<p class='separating-line'></p>";
		$showline = true;
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
		$timeleft = 24*60*60 - (time() - strtotime($res->post_date));
		$expire = time() + $timeleft;
		$instructions = $bp->post_excerpt;
		$user_info = get_userdata($bp->post_author);
		$giver = $user_info->first_name." ".substr($user_info->last_name,0,1)."."; ?>
		<div class='box-o-goods basket-box'>
			<div class='box-pic'><?php echo $pic; ?></div>
			<div class='box-info'>
				<form method='post'>
					<input type='hidden' name='reservation' value='<?php echo htmlspecialchars($res->ID); ?>' />
					<input type='hidden' name='claim_quantity' value='<?php echo htmlspecialchars($quantity); ?>' />
					<input type='hidden' name='action' value='take' />
					<input type='hidden' name='box_post' value='<?php echo htmlspecialchars($box_post); ?>' />
				<p><?php echo "$good_name: $quantitystr"; ?><br><?php echo $locationstr; ?><span class='expiration'><img src='<?php echo $template_url; ?>/img/l/clock.png' width='20' height='20'> <?php echo date("g:ia",$expire); ?></span> <input type='submit' value='Take' /></p>
				</form>
			</div>
		</div><?php
	}
}
?>