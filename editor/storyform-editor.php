<?php

/*
 * Add plugin to TinyMCE Editor
 */
add_filter( "mce_external_plugins", "storyform_add_buttons" );
function storyform_add_buttons( $plugin_array ) {
    $plugin_array['storyform'] = plugin_dir_url( __FILE__ ) . 'storyform-plugin.js';
    return $plugin_array;
}

/*
 * Add pullquote button
 */
add_filter( 'mce_buttons', 'storyform_register_buttons' );
function storyform_register_buttons( $buttons ) {
    array_push( $buttons, 'pullquote' );
    return $buttons;
}

/*
 * Make sure there is a visual indication of the pullquote.
 */
function storyform_add_editor_styles() {
    add_editor_style( plugin_dir_url( __FILE__ ) . 'storyform-editor-style.css' );
}
add_action( 'after_setup_theme', 'storyform_add_editor_styles' );

/*
 * Add pullquote button to "Text" view within WordPress.
 */
add_action( 'admin_print_footer_scripts', 'storyform_quicktags' );
function storyform_quicktags () {
	if ( wp_script_is( 'quicktags' ) ) {
	?>
		<script type="text/javascript">
			QTags.addButton( 'storyform_pullquote', 'pullquote', '<span class="pullquote">', '</span>', 'p', 'Pullquote', 200 );
		</script>
	<?php
	}
}



?>