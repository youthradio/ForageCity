<?php
global $custom_page_to_show;

$menu = wp_nav_menu( array( 'container_class' => 'navmenu', 'items_wrap' => '<ul id="nav-menu">
%3$s</ul>', 'theme_location' => 'primary', 'echo' => false ) );

if($custom_page_to_show == "homemenu")
	$menu = str_replace('title="home"', 'title="home" class="current"', $menu);
else
	$menu = str_replace('title="'.$custom_page_to_show.'"', 'title="'.$custom_page_to_show.'" class="current"', $menu);
echo $menu;

?>