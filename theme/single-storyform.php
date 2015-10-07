<?php
/**
 * A bare-bones template file which should load the Storyform scripts and css if opted into, 
 * or load the typical single.php template.
 *
 * @package Storyform
 * @subpackage Storyform
 */

?>
<!DOCTYPE html>
<!--[if IE 7]>
<html class="ie ie7" <?php language_attributes(); ?>>
<![endif]-->
<!--[if IE 8]>
<html class="ie ie8" <?php language_attributes(); ?>>
<![endif]-->
<!--[if !(IE 7) | !(IE 8) ]><!-->
<html <?php language_attributes(); ?>>
<!--<![endif]-->
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width,initial-scale=1,minimal-ui">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title><?php wp_title( '/', true, 'right' ); ?></title>
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
	<link rel="canonical" href="<?php echo get_permalink() ?>" />
	<meta name="generator" content="Storyform <?php echo Storyform_Api::get_instance()->get_version() ?>" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <article id="article" data-content>

	<?php if ( have_posts() ) : ?>

	<?php while ( have_posts() ) : the_post(); ?>

	<?php
	Storyform::get_instance()->remove_actions( 'the_content' );
	the_content(); 
	?>
	
	<?php if ( function_exists( 'vip_powered_wpcom' ) ) : ?>
	<p>
		<small><?php echo vip_powered_wpcom(); ?></small>
	</p>
	<?php endif; ?>

	<?php endwhile; ?>

	<?php endif; ?>
			
	</article>
	<?php Storyform::navbar_template() ?>
	<div class="primary-content">
		<div class="magazine storyform-magazine" data-win-control="Controls.FlipView" <?php echo Storyform::horizontal_attr() ?>>
	        <div data-win-control="UI.ProgressBar"></div>
	        <div data-win-control="UI.PageNumbers"></div>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>
</html>