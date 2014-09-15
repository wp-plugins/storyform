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
		// In VIP we store things on the post meta, given max size of options and that
		// we don't avoid loading functions.php anyways.
		if( ! function_exists( 'wpcom_vip_load_plugin' ) ) {
			return $this->get_template_for_post_from_option( $post_id, $post_name );

		} else {
			return get_post_meta( $post_id, $this->meta_name, true );
		}
	}

	protected function get_template_for_post_from_option( $post_id, $post_name ) {
		$options = $this->get_templates_options();

		foreach( $options as $option ) {
			if( ( $post_id && $option['id'] == $post_id ) || ($post_name && $option['name'] == $post_name ) ) {
				return $option['template'];
			}
		}

		return FALSE;
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
		// In VIP we store things in post meta
		if( ! function_exists( 'wpcom_vip_load_plugin' ) ) {
			$this->update_template_for_post_in_option( $post_id, $post_name, $template );
			
		} else {
			update_post_meta( $post_id, $this->meta_name, $template );
		}
	}

	protected function update_template_for_post_in_option( $post_id, $post_name, $template ) {
		// Create object to save
		$post = array();
		$post['id']  = intval( $post_id );
		$post['name'] =  strtolower( $post_name ) ;
		$post['template'] = $template;

		// Get and update current options
		$newOptions = array();
		foreach( $this->get_templates_options() as $option ) {
			if( $option['id'] == $post_id ) {
				continue;
			}
			array_push( $newOptions, $option );
		}
		if( $template ) {
			array_push( $newOptions, $post ); // add new option for this post
		}

		update_option( $this->option_name, $newOptions );
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
		if( $storyform_settings ){
			$app_key = $storyform_settings['storyform_application_key'];    
		} else {
			$app_key = null;
		}
		return $app_key;
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
		global $wpdb;

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
	}

}