<?php

/*
 * Add plugin to TinyMCE Editor
 */
function storyform_add_buttons( $plugin_array ) {
	$plugin_array['storyform'] = plugin_dir_url( __FILE__ ) . 'storyform-plugin.js';

    return $plugin_array;
}
add_filter( "mce_external_plugins", "storyform_add_buttons", 1000 );

/*
 * Add pullquote button
 */
add_filter( 'mce_buttons', 'storyform_register_buttons' );
function storyform_register_buttons( $buttons ) {
    array_push( $buttons, 'pullquote' );
    array_push( $buttons, 'break-before-page' );
    return $buttons;
}

/*
 * Add our own styling to the editor's iframe
 */
function storyform_add_editor_styles() {
	add_editor_style( plugin_dir_url( __FILE__ ) . 'storyform-editor-style.css' );
}
add_action( 'after_setup_theme', 'storyform_add_editor_styles' );


/*
 * Add the current layout type as a class to the body
 */
function storyform_editor_tiny_mce_before_init( $settings ){
	global $post;
	$options = Storyform_Options::get_instance();
	if( $options->get_template_for_post( $post->ID, null ) ){
	    $layout = $options->get_layout_type_for_post( $post->ID );
		$settings['body_class'] = $settings['body_class'] . ' ' . $layout;
	}

	return $settings;
}
add_filter( 'tiny_mce_before_init', 'storyform_editor_tiny_mce_before_init', null, 1);

/*
 * Add pullquote button to "Text" view within WordPress.
 */
function storyform_quicktags () {
	if ( wp_script_is( 'quicktags' ) ) {
	?>
		<script type="text/javascript">
			QTags.addButton( 'storyform_pullquote', 'pullquote', '<span class="pullquote">', '</span>', 'p', 'Pullquote', 200 );
		</script>
	<?php
	}
}
add_action( 'admin_print_footer_scripts', 'storyform_quicktags' );


?>