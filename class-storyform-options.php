<?php
/**
 * Singleton that is responsible for storing the data specifying which posts should use Storyform and which 
 * Storyform templates to use. We use the get/set_options mechanism since it is loaded early enough. 
 *
 */
class Storyform_Options {

	private $option_name = 'storyform_templates';
	private $meta_name = 'storyform_template';
	private $featured_image_name = 'storyform_use_featured_image';
	private $layout_type_name = 'storyform_layout_type';

	protected static $instance = false;

	public static function get_instance() {
		if( !self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Gets the Storyform template for a give Post Id or Post name. Id is preferred
	 *
	 */
	function get_template_for_post( $post_id, $post_name ) {
		return get_post_meta( $post_id, $this->meta_name, true );
	}

	protected function get_templates_options() {
		$options = get_option( $this->option_name );
		if ( gettype( $options ) != 'array' ) {
			$options = array();
		}
		return $options;
	}

	/**
	 * Sets the options array
	 *
	 */
	function update_template_for_post( $post_id, $post_name, $template ) {
		update_post_meta( $post_id, $this->meta_name, $template );
	}

	/**
	 * Sets what layout type to use for the post
	 *
	 */
	function update_layout_type_for_post( $post_id, $value ){
		if($value !== 'ordered' && $value !== 'slideshow' && $value !== 'freeflow'){
			return false;
		}
		// Invert so our default is true
		return update_post_meta( $post_id, $this->layout_type_name, $value );
	}

	/**
	 * Gets what layout type to use for the post
	 *
	 */
	function get_layout_type_for_post( $post_id ){
		$layout = get_post_meta( $post_id, $this->layout_type_name, true );
		if($layout){
			return $layout;
		}
		return 'freeflow';
		
	}

	/**
	 * Sets whether to use a featured image in the post
	 *
	 */
	function update_use_featured_image_for_post( $post_id, $value ){
		// Invert so our default is true
		update_post_meta( $post_id, $this->featured_image_name, !$value );
	}

	/**
	 * Gets whether to use a featured image in the post
	 *
	 */
	function get_use_featured_image_for_post( $post_id ){
		return !get_post_meta( $post_id, $this->featured_image_name, true );
		
	}

	/**
	 * Gets the application key or returns null.
	 *
	 */
	function get_application_key(){
		$storyform_settings = get_option( 'storyform_settings' );

		// Get application key so we can figure out which templates are supported for this site
		if( $storyform_settings && isset( $storyform_settings['storyform_application_key'] ) ){
			$app_key = $storyform_settings['storyform_application_key'];    
		} else {
			$app_key = null;
		}
		return $app_key;
	}

	/**
	 * Gets the navigation header width
	 *
	 */
	function get_navigation_width(){
		$storyform_settings = get_option( 'storyform_settings' );

		if( $storyform_settings && isset( $storyform_settings['storyform_navigation_width'] ) ){
			$width = $storyform_settings['storyform_navigation_width'];    
		} 

		if( !isset( $width ) || !$width ){
			$width = 'minimized';
		}

		return $width;
	}

	/**
	 * Gets the navigation header logo url
	 *
	 */
	function get_navigation_logo(){
		$storyform_settings = get_option( 'storyform_settings' );

		$logo = '';
		if( $storyform_settings && isset( $storyform_settings['storyform_navigation_logo'] ) ){
			$logo = $storyform_settings['storyform_navigation_logo'];    
		} 

		return $logo;
	}

	/**
	 * Gets whether to display the post title in the navigation header
	 *
	 */
	function get_navigation_title(){
		$storyform_settings = get_option( 'storyform_settings' );
		return isset( $storyform_settings['storyform_navigation_title'] ) && $storyform_settings['storyform_navigation_title'];
	}

	/**
	 * Gets the navigation header links layout
	 *
	 */
	function get_navigation_links(){
		$storyform_settings = get_option( 'storyform_settings' );

		if( $storyform_settings && isset( $storyform_settings['storyform_navigation_links'] ) ){
			$links = $storyform_settings['storyform_navigation_links'];    
		} 

		if( !isset( $links ) || !$links ){
			$links = 'vertical';
		}

		return $links;
	}

	/**
	 * Gets the side the navigation links show up
	 *
	 */
	function get_navigation_side(){
		$storyform_settings = get_option( 'storyform_settings' );

		if( $storyform_settings && isset( $storyform_settings['storyform_navigation_side'] ) ){
			$side = $storyform_settings['storyform_navigation_side'];    
		} 

		if( !isset( $side ) || !$side ){
			$side = 'left';
		}

		return $side;
	}

	/**
	 * Gets the navigation header controls
	 *
	 */
	function get_navigation_controls(){
		$storyform_settings = get_option( 'storyform_settings' );

		if( $storyform_settings && isset( $storyform_settings['storyform_navigation_controls'] ) ){
			$controls = $storyform_settings['storyform_navigation_controls'];    
		} 

		if( !isset( $controls ) ){
			$controls = ['facebook', 'twitter', 'gplus', 'fullscreen'];
		}

		return $controls;
	}

	/**
	 * Gets the navigation header background color
	 *
	 */
	function get_navigation_bg_color(){
		$storyform_settings = get_option( 'storyform_settings' );

		if( $storyform_settings && isset( $storyform_settings['storyform_navigation_bg_color'] ) ){
			$color = $storyform_settings['storyform_navigation_bg_color'];    
		} 

		if( !isset( $color ) || !$color ){
			$color = '#242424';
		}

		return $color;
	}

	/**
	 * Gets the navigation header foreground color
	 *
	 */
	function get_navigation_fg_color(){
		$storyform_settings = get_option( 'storyform_settings' );

		if( $storyform_settings && isset( $storyform_settings['storyform_navigation_fg_color'] ) ){
			$color = $storyform_settings['storyform_navigation_fg_color'];    
		} 

		if( !isset( $color ) || !$color ){
			$color = '#FFF';
		}

		return $color;
	}

	/**
	 * Gets border thickness at bottom of navigation bar
	 *
	 */
	function get_navigation_border_bottom_width(){
		$storyform_settings = get_option( 'storyform_settings' );

		if( $storyform_settings && isset( $storyform_settings['storyform_navigation_border_bottom_width'] ) ){
			$val = $storyform_settings['storyform_navigation_border_bottom_width'];    
		} 

		if( !isset( $val ) || !$val ){
			$val = 0;
		}

		return $val;
	}

	/**
	 * Gets whether to generate additional image sizes for new uploaded media.
	 *
	 */
	function get_add_image_sizes(){
		$storyform_settings = get_option( 'storyform_settings' );

		// If its not set we assume yes we should add image sizes. 
		if( $storyform_settings ){
			$value = ! isset( $storyform_settings['storyform_add_image_sizes'] ) || $storyform_settings['storyform_add_image_sizes'];    
		} else {
			$value = TRUE;
		}
		return $value;
	}

	/**
	 * Migrates old Narrative plugin data over to Storyform
	 *
	 */
	function migrate(){
		global $wpdb, $storyform_version;

		// Migrate appkey
		$old_settings = get_option( 'narrative_settings' );
		if( $old_settings != FALSE ){
			$settings = array(
				'storyform_application_key' => $old_settings['narrative_application_key']
			);
			update_option( 'storyform_settings', $settings );	
			delete_option( 'narrative_settings' );
		}
		
		// Migrate which posts use Storyform
		$old_templates = unserialize( get_option( 'narrative_theme' ) ); // Was serializing for no reason
		if( $old_templates != FALSE ){
			$posts = array();
			foreach( $old_templates as $dt ){
				$post = array(
					'id' 		=> $dt['id'],
					'name'		=> $dt['url'],
					'template'	=> $dt['theme']
				);
				array_push( $posts, $post );
			}
			update_option( $this->option_name, $posts );
			delete_option( 'narrative_theme' );
		}

		// Migrate overlay data
		$wpdb->update( 
			$wpdb->postmeta, 
			array( 'meta_key' => 'storyform_text_overlay_areas' ),
			array( 'meta_key' => 'narrative_text_overlay_areas' )
		);

		// Unspecified layout types are freeflow
		$version = get_option( 'storyform_version', '' );	
		if( $version === '' ) {
			$options = $this->get_templates_options();
			foreach( $options as $option ) {
				$id = $option['id'];
				$layout = get_post_meta( $id, $this->layout_type_name, true );
				if($layout === ''){
					update_post_meta( $id, $this->layout_type_name, 'freeflow' );
				}
			}
		}
		update_option( 'storyform_version', $storyform_version );
		
		// Duplicate wp_options data in post_meta
		$options = $this->get_templates_options();
		foreach( $options as $option ) {
			if( $option['id'] && $option['template'] ) {
				update_post_meta( $option['id'], $this->meta_name, $option['template'] );
			}
		}
	}

	function get_all_scripts(){
		$scripts = get_option( 'storyform_all_scripts' );
		if ( gettype( $scripts ) != 'array' ) {
			$scripts = array();
		}
		return $scripts;
	}

	function update_all_scripts( $scripts ){
		update_option( 'storyform_all_scripts', $scripts );
	}

	function get_selected_scripts(){
		$storyform_settings = get_option( 'storyform_settings' );

		$scripts = null;
		if( $storyform_settings && isset( $storyform_settings['storyform_selected_scripts'] ) ){
			$scripts = $storyform_settings['storyform_selected_scripts'];    
		} 

		if ( gettype( $scripts ) != 'array' ) {
			$scripts = array();
		}

		return $scripts;
	}

	function get_all_functions(){
		$functions = get_option( 'storyform_all_functions' );
		if ( gettype( $functions ) != 'array' ) {
			$functions = array();
		}
		return $functions;
	}

	function update_all_functions( $functions ){
		update_option( 'storyform_all_functions', $functions );
	}

	function get_selected_functions(){
		$storyform_settings = get_option( 'storyform_settings' );

		$functions = null;
		if( $storyform_settings && isset( $storyform_settings['storyform_selected_functions'] ) ){
			$functions = $storyform_settings['storyform_selected_functions'];    
		} 

		if ( gettype( $functions ) != 'array' ) {
			$functions = array();
		}

		return $functions;
	}
}