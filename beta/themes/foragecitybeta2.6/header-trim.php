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

	<title>Forage City</title>
    <meta name="description" content="A free mobile app to find and share food">
    <meta name="author" content="Youth Radio">

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
	<link rel="stylesheet" type="text/css" href="<?php echo $template_url; ?>/css/splash.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $template_url; ?>/css/960.css" />
    <link href='http://fonts.googleapis.com/css?family=Coustard:400,900' rel='stylesheet' type='text/css' />

    <script type="text/javascript">
    
        var _gaq = _gaq || [];
        _gaq.push(['_setAccount', 'UA-33948904-1']);
        _gaq.push(['_trackPageview']);
        
        (function() {
        var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
        ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
        var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
        })();
    
    </script>
<?php wp_head(); ?>
</head>

<body <?php body_class("trim"); ?>>
	<div id="container" class="<?php echo $custom_page_to_show; ?>">
		<div id="desktop-notice">