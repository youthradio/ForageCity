<?php
global $wp_query;
global $custom_page_to_show;
$custom_page_to_show = 'homemenu';
if(isset($wp_query->query_vars['custom_page_to_show'])) {
	$custom_page_to_show = $wp_query->query_vars['custom_page_to_show'];
}

$template_url = get_template_directory_uri();
$action = fc_get_form_value('action', 'view');
?><!doctype html>
<?php /*
<!-- Conditional comment for mobile ie7 http://blogs.msdn.com/b/iemobile/ -->
<!-- Appcache Facts http://appcachefacts.info/ -->
*/ ?>
<!--[if IEMobile 7 ]>    <html class="no-js iem7" <?php language_attributes(); ?>> <![endif]-->
<!--[if (gt IEMobile 7)|!(IEMobile)]><!--> <html class="no-js" <?php language_attributes(); ?>> <!--<![endif]-->

<head>
	<meta charset="utf-8">

	<title><?php
		// Print the <title> tag based on what is being viewed.
		global $page, $paged;

		wp_title( '|', true, 'right' );

		// Add the blog name.
		bloginfo( 'name' );

		// Add the blog description for the home/front page.
		$site_description = get_bloginfo( 'description', 'display' );
		if ( $site_description && ( is_home() || is_front_page() ) )
			echo " | $site_description";

		// Add a page number if necessary:
		if ( $paged >= 2 || $page >= 2 )
			echo ' | ' . sprintf( __( 'Page %s', 'twentyten' ), max( $paged, $page ) );
?></title>
	<meta name="description" content="">
	<meta name="author" content="">
	<meta name="HandheldFriendly" content="True">
	<meta name="MobileOptimized" content="320">
	<meta name="viewport" content="width=320, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, target-densitydpi=medium-dpi, user-scalable=no">
	<link rel="apple-touch-icon" sizes="114x114" href="<?php echo $template_url; ?>/img/h/apple-touch-icon.png">
	<link rel="apple-touch-icon" sizes="72x72" href="<?php echo $template_url; ?>/img/m/apple-touch-icon.png">
	<link rel="apple-touch-icon" href="<?php echo $template_url; ?>/img/l/apple-touch-icon.png">
	<link rel="shortcut icon" href="<?php echo $template_url; ?>/img/favicon.ico">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black">
	<link rel="apple-touch-startup-image" href="<?php echo $template_url; ?>/img/l/splash.png">
	<meta http-equiv="cleartype" content="on">
	<!-- meta name="format-detection" content="telephone=no" -->
	<!-- meta name="format-detection" content="address=no" -->
	<link rel="profile" href="http://gmpg.org/xfn/11" />
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />

	<link rel="stylesheet" type="text/css" media="all" href="<?php bloginfo( 'stylesheet_url' ); ?>" />

	<!-- script src="<?php echo $template_url; ?>/js/libs/modernizr-custom.js"></script -->
	<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=true"></script>
	<script type="text/javascript">
	var geocoder, map, initialize = function() {
		geocoder = new google.maps.Geocoder();
		// var latlng = new google.maps.LatLng(-34.397, 150.644);
		// var myOptions  = {
			// zoom: 8,
			// center: latlng,
			// mapTypeId: google.maps.MapTypeId.ROADMAP
		// }
		// map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
	},
	codeAddress = function(address) {
		// var address = document.getElementById("address").value;
		geocoder.geocode( { 'address': address}, function(results, status) {
			if (status == google.maps.GeocoderStatus.OK) {
				var loc = results[0].geometry.location;
				// alert(results[0].geometry.location);
				// console.log(loc.lat()+","+loc.lng());
				// map.setCenter(results[0].geometry.location);
				// var marker = new google.maps.Marker({
					// map: map,
					// position: results[0].geometry.location
				// });
			} else {
				// alert("Geocode was not successful for the following reason: " + status);
				console.log("Geocode was unsuccessful for the following reason:");
				console.log(status);
			}
		});
	};
	</script>

<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<div id="container" class="<?php echo $custom_page_to_show; ?>">
		<div id="header">
			<div id="masthead">
				<div id="branding" role="banner">
					<?php $heading_tag = ( is_home() || is_front_page() ) ? 'h1' : 'h1'; ?>
					<<?php echo $heading_tag; ?> id="site-title">
						<span>
							<?php /* <a href="<?php echo home_url( '/' ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"> */ ?>
							<?php bloginfo( 'name' ); ?>
							<?php /* </a> */ ?> 
						</span>
					</<?php echo $heading_tag; ?>>
				</div><!-- #branding -->

				<div id="access" role="navigation">
				  <?php /*  Allow screen readers / text browsers to skip the navigation menu and get right to the good stuff */ ?>
					<div class="skip-link visuallyhidden"><a href="#content" title="<?php esc_attr_e( 'Skip to content', 'twentyten' ); ?>"><?php _e( 'Skip to content', 'twentyten' ); ?></a></div>
				</div><!-- #access -->
			</div><!-- #masthead -->
		</div><!-- #header -->
		<div id="top-left-background"></div>
		<div id="top-repeat-background"></div>
		<div id="bottom-right-background"></div>
