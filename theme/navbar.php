<?php

$options = Storyform_Options::get_instance(); 
$width = $options->get_navigation_width();
$links = $options->get_navigation_links();
$side = $options->get_navigation_side();
$logo = $options->get_navigation_logo();
$title = $options->get_navigation_title() ? get_the_title() ? get_the_title() : 'Title of the article' : '';
$controls = $options->get_navigation_controls();
$bg_color = $options->get_navigation_bg_color();
$bg_color_light = Storyform_Color::CSS_is_light( $bg_color );
$fg_color = $options->get_navigation_fg_color();
$bb_width = $options->get_navigation_border_bottom_width();

$navbar_class = 'navbar-' . $width;
$navbar_class .= $width === 'full' ? ' navbar-left' : ' navbar-' . $side;
$navbar_class .= ' ' . $links . '-links';
$navbar_class .= $logo == '' ? ' navbar-no-logo' : '';

?>

<style>
.navbar-site-title,
.navbar-title a, 
.navbar-links a {
	color: <?php echo $fg_color ?>;
}

.navbar-title {
	border-left: 1px solid <?php echo $bg_color_light ? 'rgba(0,0,0,0.05)' : 'rgba(242,242,242,0.5)';?>;
}

.navbar-controls .navbar-icon,
.navbar-controls .navbar-icon:active,
.navbar-controls .navbar-icon:visited,
.navbar-comments-mobile {
	color: <?php echo $fg_color ?>;
}

.comments-icon:hover {
	color: <?php echo $fg_color ?>;
	background-color: <?php echo $bg_color_light ? 'rgba(0,0,0,0.3)' : 'rgba(255,255,255,0.3)';?>;
}

.navbar-minimized.vertical-links .navbar-controls {
	background-color: <?php echo $bg_color_light ? 'rgba(0,0,0,0.05)' : 'rgba(255,255,255,0.05)';?>;
}

.fullscreen-icon,
.fullscreen-icon:active,
.fullscreen-icon:visited {
	color: <?php echo $fg_color ?>;	
}

.fullscreen-icon:hover, .comments-icon:hover {
	color: <?php echo $fg_color ?>;	
	background-color: <?php echo $bg_color_light ? 'rgba(0,0,0,0.15)' : 'rgba(255,255,255,0.3)';?>;
}

.navbar-controls {
	background-color: <?php echo $bg_color ?>;
}
@media screen and (max-width: 700px) {
	.navbar-controls {
		background-color: <?php echo $bg_color_light ? 'rgba(0,0,0,0.05)' : 'rgba(255,255,255,0.05)';?>;
	}
	
	.navbar-full.vertical-links .navbar-controls {
		background-color: <?php echo $bg_color ?>;
	}
}
.navbar-lines, .navbar-lines:before, .navbar-lines:after {
	background: <?php echo $fg_color ?>;
}

.navbar-full {
	border-bottom: <?php echo $bb_width ?>px solid <?php echo $fg_color ?>;
}

.navbar-minimized .navbar-toggle {
	background-color: <?php echo $bg_color ?>;
}

.navbar-full .navbar-toggle {
	background-color: <?php echo $bg_color_light ? 'rgba(0,0,0,0.05)' : 'rgba(255,255,255,0.05)';?>;
}
.navbar-full .navbar-toggle:hover {
	background-color: <?php echo $bg_color_light ? 'rgba(0,0,0,0.15)' : 'rgba(255,255,255,0.3)';?>;
}

.navbar-content {
	background-color: <?php echo $bg_color ?>;
}

.navbar-full.vertical-links .navbar-nav {
	top: <?php echo 50 + $bb_width ?>px;
}

.navbar-full.vertical-links .navbar-nav {
	background-color: <?php echo $bg_color ?>;
}

.navbar-minimized.vertical-links .navbar-content {
	background-color: <?php echo $bg_color ?>;
}

</style>

<header class="navbar <?php echo $navbar_class ?>" role="navigation" data-win-control="UI.NavBar">
	<div class="navbar-toggle">
		<div class="navbar-lines-button">
          <span class="navbar-lines"></span>
        </div>
	</div>
	
	<div class="navbar-content">
		<a class="navbar-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<h2 class="navbar-site-title"><?php bloginfo( 'name' ); ?></h2>
			<img src="<?php echo $logo ?>" alt="logo" />
		</a>
	<?php if( $title ) { ?>
		<div class="navbar-title">
			<h6><a href="#"><?php echo $title; ?></a></h6>
		</div>
	<?php } ?>
		
	<?php if( count( $controls ) ) { ?>
		
		<div class="navbar-controls-mobile"> 
			<?php if( ( is_single() && ( comments_open() || get_comments_number() ) ) || ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] == 'storyform-setting-admin' ) ) { ?>
			<a class="navbar-icon navbar-comments-mobile" data-win-control="UI.CommentsButton" href="#">
				<span class="navbar-comments-number"><?php echo comments_number('', '1', '%') ?></span>
			</a>
			<?php } ?>
		</div>
	
		<div class="navbar-controls">
			<?php 
			if( in_array( 'facebook', $controls ) ) { ?><a class="navbar-icon" data-win-control="UI.FacebookShare" href="#"></a><?php } 
			if( in_array( 'twitter', $controls ) ) { ?><a class="navbar-icon" data-win-control="UI.TwitterShare" href="#"></a><?php } 
			if( in_array( 'gplus', $controls ) ) { ?><a class="navbar-icon" data-win-control="UI.GooglePlusShare" href="#"></a><?php } 
			if( ( is_single() && ( comments_open() || get_comments_number() ) ) || ( is_admin() && isset( $_GET['page'] ) && $_GET['page'] == 'storyform-setting-admin' ) ) { ?><a class="navbar-icon navbar-comments-btn" data-win-control="UI.CommentsButton" href="#"><span class="navbar-comments-number"><?php echo comments_number( '', '1', '%' ) ?></span></a><?php } 
			if( in_array( 'fullscreen', $controls ) ) { ?><a class="navbar-icon" data-win-control="UI.FullScreenButton" href="#"></a><?php } 
			?>
		</div>
	<?php } ?>
		<nav class="navbar-nav navbar-links">
			<?php wp_nav_menu( array(
				'theme_location' 	=> 'storyform_navbar',
				'container' 		=> FALSE,
				'fallback_cb' 		=> FALSE,
				'echo'				=> TRUE,
				'depth'				=> 1
			) ) ?>
		</nav>
	</div>
	<?php if ( is_single() && ( comments_open() || get_comments_number() ) ) : ?>
		<div class="navbar-comments">
			<div class="navbar-comments-close"></div>
			<div class="navbar-comments-contents">
				<?php comments_template( ); ?>
			</div>
		</div>
	<?php endif; ?>
</header>
<div class="navbar-overlay"></div>