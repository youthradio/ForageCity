<?php
get_header();

// if (is_user_logged_in()) : 

	global $custom_page_to_show;

	/*
		Get rid of the annoying phantom space that seems to be appearing
		at the beginning of the loaded template file.

		I don't know where the space is coming from, because it doesn't
		happen with the exact same code on my dev machine, but
		something less hacky than this should probably happen later.
		I just don't have time to care now.
	*/
	function remove_leading_space($buffer)
	{
		if(preg_match("/^﻿/", $buffer) > 0)
			return (preg_replace("/^﻿/", "", $buffer, 1));
		else
			return $buffer;
	}

	ob_start("remove_leading_space");
?>	<div id='top-buttons' class='<?php echo "$custom_page_to_show"; ?> page'><div id='home-button'><a href="<?php echo home_url(); ?>">Home</a></div><div id='info-button'><a href="<?php echo home_url(); ?>/info/">Info</a></div><div id='info-close-button'><a href="<?php echo home_url(); ?>">Close</a></div></div><div id="scroll-wrapper"><div id="content" role="main">
<?php if ( have_posts() ) while ( have_posts() ) : the_post(); ?>

				<div id="post-<?php the_ID(); ?>" <?php post_class('real-page'); ?>>
					<?php if ( is_front_page() ) { ?>
						<h2 class="entry-title"><?php the_title(); ?></h2>
					<?php } else { ?>
						<h1 class="entry-title"><?php the_title(); ?></h1>
					<?php } ?>

					<div class="entry-content">
						<?php the_content(); ?>
						<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'twentyten' ), 'after' => '</div>' ) ); ?>
						<?php edit_post_link( __( 'Edit', 'twentyten' ), '<span class="edit-link">', '</span>' ); ?>
					</div><!-- .entry-content -->
				</div><!-- #post-## -->

<?php endwhile; // end of the loop. ?>
<?php ob_end_flush();

// endif;

get_footer();

include(ABSPATH.'wp-cron.php');
?>