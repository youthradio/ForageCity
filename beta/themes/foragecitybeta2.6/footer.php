<?php
$DEBUG = false;
$template_url = get_template_directory_uri(); ?>
		</div></div>
<div class='clear'></div>
		<div id="footer" role="contentinfo">
<?php //if(is_user_logged_in())
get_template_part("navmenu");
?>

		</div><!-- #footer -->
		<div id="loading">
			<div id="spinner"><div class="bar1"></div><div class="bar2"></div><div class="bar3"></div><div class="bar4"></div><div class="bar5"></div><div class="bar6"></div><div class="bar7"></div><div class="bar8"></div><div class="bar9"></div><div class="bar10"></div><div class="bar11"></div><div class="bar12"></div></div>
		</div> <!-- #loading -->
	</div> <!-- #container -->

	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.6.1/jquery<?php if(!$DEBUG) echo ".min"; ?>.js"></script>
	<script type="text/javascript">window.jQuery||document.write("<script src='<?php echo $template_url; ?>/js/libs/jquery-1.6.1<?php if(!$DEBUG) echo ".min"; ?>.js'>\x3C/script>")</script>
	<script src="<?php echo $template_url; ?>/js/mylibs/iScroll/iscroll-lite.js"></script>
	<script type="text/javascript">var ADMIN_AJAX_URL="<?php echo admin_url('admin-ajax.php'); ?>", HOME_URL="<?php echo home_url(); ?>", TEMPLATE_URL="<?php echo $template_url; ?>"</script>
	<!-- <script src="<?php echo $template_url; ?>/js/libs/jquery-ui-1.8.14.custom.min.js"></script> -->
	<script src="<?php echo $template_url; ?>/js/script<?php if($DEBUG) echo ".src"; ?>.js"></script>

<?php /*
	<!-- scripts concatenated and minified via ant build script -->
	<script src="<?php echo $template_url; ?>/js/mylibs/helper.js"></script>
	<!-- end concatenated and minified scripts-->

	<script>MBP.scaleFix();yepnope({test : Modernizr.mq('(min-width)'),nope : ['<?php echo $template_url; ?>/js/libs/respond.min.js']});</script>

	<!-- mathiasbynens.be/notes/async-analytics-snippet Change UA-XXXXX-X to be your site's ID -->
	<script>var _gaq=[["_setAccount","UA-XXXXX-X"],["_trackPageview"]];(function(d,t){var g=d.createElement(t),s=d.getElementsByTagName(t)[0];g.async=1;g.src=("https:"==location.protocol?"//ssl":"//www")+".google-analytics.com/ga.js";s.parentNode.insertBefore(g,s)}(document,"script"))</script>
*/
unset($_SESSION['old_post']);
unset($_SESSION['old_get']);
?>
<?php wp_footer(); ?>
</body>
</html>