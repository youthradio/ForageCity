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

<?php wp_head(); ?>
</head>

<body <?php body_class("trim"); ?>>
	<div id="top-left-background"></div>
	<div id="top-repeat-background"></div>
	<div id="bottom-right-background"></div>
	<div id="container" class="<?php echo $custom_page_to_show; ?>">
		<div id="desktop-notice">