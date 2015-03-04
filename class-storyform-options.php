<?php
/**
 * Singleton that is responsible for storing the data specifying which posts should use Storyform and which 
 * Storyform templates to use. We use the get/set_options mechanism since it is loaded early enough. 
 *
 */
class Storyform_Options {

	private $option_name = 'storyform_templates';
	private $meta_name = 'storyform_template';
	private $ab_name = 'storyform_ab';
	private $featured_image_name = 'storyform_use_featured_image';
	private $layout_type_name = 'storyform_layout_type';
	private $post_meta_settings = 'storyform_settings';
	private $crop_name = 'storyform_areas_crop';

	protected static $instance = false;

	public static function get_instance() {
		if( !self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get_settings(){
		$settings = get_option( 'storyform_settings' );
		if( !$settings ){
			$result = array();
		} else {
			// Make a copy so we can update at the same time
			$result = array();
			foreach($settings as $key=>$value){
				$result[$key] = $value;
			}
		}

		return $result;
	}

	/**
	 * Gets whether template should be Storyform A/B tested
	 *
	 */
	function get_ab_for_post( $post_id ) {
		return get_post_meta( $post_id, $this->ab_name, true );
	}

	/**
	 * Sets whether to A/B test Storyform
	 *
	 */
	function update_ab_for_post( $post_id, $value ) {
		$this->update_post_meta( $post_id, $this->ab_name, $value );
	}

	protected function get_templates_options() {
		$options = get_option( $this->option_name );
		if ( gettype( $options ) != 'array' ) {
			$options = array();
		}
		return $options;
	}

	/**
	 * Gets the Storyform template for a give Post Id or Post name. Id is preferred
	 *
	 */
	function get_template_for_post( $post_id, $post_name ) {
		return get_post_meta( $post_id, $this->meta_name, true );
	}

	/**
	 * Sets the options array
	 *
	 */
	function update_template_for_post( $post_id, $post_name, $template ) {
		$this->update_post_meta( $post_id, $this->meta_name, $template );	
	}

	/**
	 * Sets what layout type to use for the post
	 *
	 */
	function update_layout_type_for_post( $post_id, $value ){
		if($value !== 'ordered' && $value !== 'slideshow' && $value !== 'freeflow'){
			delete_post_meta( $post_id, $this->layout_type_name );
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
		$this->update_post_meta( $post_id, $this->featured_image_name, !$value );
	}

	/**
	 * Gets whether to use a featured image in the post
	 *
	 */
	function get_use_featured_image_for_post( $post_id ){
		return !get_post_meta( $post_id, $this->featured_image_name, true );
		
	}

	/**
	 * Get specific post setting overrides for post.
	 *
	 */
	public function get_settings_for_post( $post_id ){
		$settings = $this->get_settings();
		$meta = get_post_meta( $post_id, $this->post_meta_settings, true );
		if( $meta ){
			foreach( $meta as $key => $value ){
				$settings[$key] = $value;
			}
		}
		return $settings;
	}

	public function get_crop_area_for_attachment( $attachment_id ){
		return get_post_meta( $attachment_id , $this->crop_name, true );
	}

	public function update_crop_area_for_attachment( $attachment_id, $value ){
		$this->update_post_meta( $attachment_id, $this->crop_name, $value);
	}

	public function get_caption_area_for_attachment( $attachment_id ) {
		return get_post_meta( $attachment_id, 'storyform_text_overlay_areas', true );
	}

	public function update_caption_area_for_attachment( $attachment_id, $value ){
		$this->update_post_meta( $attachment_id, 'storyform_text_overlay_areas', $value);
	}

	/**
	 * Gets the application key or returns null.
	 *
	 */
	function get_application_key(){
		return get_option( 'storyform_application_key' );
	}

	/**
	 * Updates the application key
	 *
	 */
	function update_application_key( $appKey ){
		$update = update_option( 'storyform_application_key', $appKey );
	}

	/**
	 * Updates whether the user has attempted to log-in and register the site with Storyform
	 *
	 */
	function update_site_registered( $value ){
		update_option( 'storyform_site_registered', $value );
	}

	/**
	 * Gets whether the user has attempted to log-in and register the site with Storyform or null.
	 *
	 */
	function get_site_registered(){
		return get_option( 'storyform_site_registered' );
	}



	/**
	 * Gets the navigation header width
	 *
	 */
	function get_navigation_width(){
		$storyform_settings = $this->get_settings_for_post( get_the_ID() );

		if( isset( $storyform_settings['storyform_navigation_width'] ) ){
			$width = $storyform_settings['storyform_navigation_width'];    
		} 

		if( !isset( $width ) || !$width ){
			$width = 'full';
		}

		return $width;
	}

	/**
	 * Gets the navigation header logo url
	 *
	 */
	function get_navigation_logo(){
		$storyform_settings = $this->get_settings_for_post( get_the_ID() );

		$logo = '';
		if( isset( $storyform_settings['storyform_navigation_logo'] ) ){
			$logo = $storyform_settings['storyform_navigation_logo'];    
		} 

		return $logo;
	}

	/**
	 * Gets whether to display the post title in the navigation header
	 *
	 */
	function get_navigation_title(){
		$storyform_settings = $this->get_settings_for_post( get_the_ID() );
		return isset( $storyform_settings['storyform_navigation_title'] ) && $storyform_settings['storyform_navigation_title'];
	}

	/**
	 * Gets the navigation header links layout
	 *
	 */
	function get_navigation_links(){
		$storyform_settings = $this->get_settings_for_post( get_the_ID() );

		if( isset( $storyform_settings['storyform_navigation_links'] ) ){
			$links = $storyform_settings['storyform_navigation_links'];    
		} 

		if( !isset( $links ) || !$links ){
			$links = 'horizontal';
		}

		return $links;
	}

	/**
	 * Gets the side the navigation links show up
	 *
	 */
	function get_navigation_side(){
		$storyform_settings = $this->get_settings_for_post( get_the_ID() );

		if( isset( $storyform_settings['storyform_navigation_side'] ) ){
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
		$storyform_settings = $this->get_settings_for_post( get_the_ID() );

		if( isset( $storyform_settings['storyform_navigation_controls'] ) ){
			$controls = $storyform_settings['storyform_navigation_controls'];    
		} 

		if( !isset( $controls ) ){
			$controls =  array('facebook', 'twitter', 'gplus', 'fullscreen');
		}

		return $controls;
	}

	/**
	 * Gets the navigation header background color
	 *
	 */
	function get_navigation_bg_color(){
		$storyform_settings = $this->get_settings_for_post( get_the_ID() );

		if( isset( $storyform_settings['storyform_navigation_bg_color'] ) ){
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
		$storyform_settings = $this->get_settings_for_post( get_the_ID() );

		if( isset( $storyform_settings['storyform_navigation_fg_color'] ) ){
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
		$storyform_settings = $this->get_settings_for_post( get_the_ID() );

		if( isset( $storyform_settings['storyform_navigation_border_bottom_width'] ) ){
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
		$storyform_settings = $this->get_settings();

		// If its not set we assume yes we should add image sizes. 
		$value = ! isset( $storyform_settings['storyform_add_image_sizes'] ) || $storyform_settings['storyform_add_image_sizes'];    
		
		return $value;
	}

	/**
	 * Resets all settings and post data.
	 *
	 */
	function reset_all(){
		global $wpdb;

		delete_option( 'storyform_all_scripts' );
		delete_option( 'storyform_all_functions' );
		delete_option( 'storyform_settings' );
		delete_option( 'storyform_site_registered' );
		delete_option( 'storyform_version' );
		delete_option( 'storyform_application_key' );
		delete_option( $this->option_name );

		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $this->meta_name ) );
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $this->ab_name ) );
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $this->featured_image_name ) );
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $this->layout_type_name ) );
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $this->post_meta_settings ) );
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => 'storyform_text_overlay_areas' ) );
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $this->crop_name ) );
		
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

		// Move application key to its own setting if it exists
		$storyform_settings = $this->get_settings();
		if( isset( $storyform_settings[ 'storyform_application_key' ] ) ) {
			$appKey = $storyform_settings[ 'storyform_application_key' ];
			$this->update_application_key( $appKey );
			unset( $storyform_settings[ 'storyform_application_key' ] );
			update_option( 'storyform_settings', $storyform_settings );	
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
		$storyform_settings = $this->get_settings();

		$scripts = null;
		if( isset( $storyform_settings['storyform_selected_scripts'] ) ){
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
		$storyform_settings = $this->get_settings();

		$functions = null;
		if( isset( $storyform_settings['storyform_selected_functions'] ) ){
			$functions = $storyform_settings['storyform_selected_functions'];    
		} 

		if ( gettype( $functions ) != 'array' ) {
			$functions = array();
		}

		return $functions;
	}

	protected function update_post_meta( $post_id, $meta_key, $meta_value ){
		if( !$meta_value ){
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}
	}
}