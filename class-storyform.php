<?php
/**
 *
 * This class handles actually switching the theme over to a fake one in order to avoid loading functions.php of 
 * the current one (except for WordPress VIP where this doesn't matter since the theme is already loaded). This class
 * also load the appropriate template and enqueues the scripts. 
 * 
 */
class Storyform {

	protected static $instance;
	private $storyform_template;

	/**
	 *	The list of standard WordPress and Storyform actions that we want to still run on wp_head and wp_footer
	 */
	private static $default_functions = array(
		'_wp_render_title_tag'					=> array( 'wp_head', 1 ),
		'wp_enqueue_scripts' 					=> array( 'wp_head', 1 ),
		'feed_links' 							=> array( 'wp_head', 2 ),
		'feed_links_extra' 						=> array( 'wp_head', 3 ),
		'rsd_link' 								=> array( 'wp_head' ),
		'wlwmanifest_link'						=> array( 'wp_head' ),
		'adjacent_posts_rel_link_wp_head'		=> array( 'wp_head', 10, 0 ),
		'noindex'								=> array( 'wp_head', 1 ),
		'wp_print_styles'						=> array( 'wp_head', 8 ),
		'wp_print_head_scripts'					=> array( 'wp_head', 9 ),
		'rel_canonical'							=> array( 'wp_head' ),
		'wp_print_footer_scripts'				=> array( 'wp_footer', 20 ),
		'wp_shortlink_wp_head'					=> array( 'wp_head', 10, 0 ),
		'capital_P_dangit'						=> array( 'the_content', 11 ),
		'wptexturize'							=> array( 'the_content' ),							
		'convert_smilies'						=> array( 'the_content' ),							
		'convert_chars'							=> array( 'the_content' ),							
		'wpautop'								=> array( 'the_content' ),							
		'shortcode_unautop'						=> array( 'the_content' ),							
		'prepend_attachment'					=> array( 'the_content' ),
		'do_shortcode'							=> array( 'the_content', 11 ),
		'WP_Embed:autoembed'					=> array( 'the_content', 8 ),
		'WP_Embed:run_shortcode'				=> array( 'the_content', 8 ),
		'storyform_remove_src_attribute'		=> array( 'the_content', 5 ),
		'storyform_replace_crop'				=> array( 'the_content', 20 )
	);

	private static $actions_to_filter = array( 'wp_head', 'wp_footer', 'wp_print_scripts', 'wp_print_styles', 'the_content' );

	/**
	 * 	Get the Singleton instance.
	 */
	public static function get_instance() {
		if( !self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 *	Gets the list of actions we are filtering on Storyform posts.
	 */
	public static function get_actions_to_filter() {
		return self::$actions_to_filter;
	}

	/**
	 *	Gets the list of default functions we don't filter on Storyform posts.
	 */
	public static function get_default_functions() {
		return self::$default_functions;
	}	

	/*
	 *
	 *	@public Be sure to not remove this.
	 *	Returns the template in use, or null.
	 */
	public static function template_in_use() {
		$storyform = Storyform::get_instance();
		return $storyform->get_storyform_template();	
	}

	/*
	 * Sets up the necessary actions and filters. 
	 */
	public function init(){
		add_filter( 'template_include', array( &$this, 'template_include' ), 9999 );
		add_filter( 'comments_template', array( &$this, 'comments_template' ), 5 );
			
	}

	/** 
	 * Loads our comments template. We must hook here since calling the function with file is always relative to 
	 * the theme.
	 *
	 */
	function comments_template( $file ) {
		if( $this->get_storyform_template() ) {
			return dirname( __FILE__ ) . '/theme/comments.php';
		}
		return $file;
	}

	/** 
	 * Loads our navbar template and allows for it to be overridden.
	 *
	 */
	public static function navbar_template() {
		$navbar_template = dirname( __FILE__ ) . '/navbar.php';
		/**
		 * Filter the path to the navbar template file used for the navbar
		 *
		 *
		 * @param string $navbar_template The path to the navbar template file.
		 */
		$include = apply_filters( 'storyform_navbar_template', $navbar_template );
		if( file_exists( $include ) ){
			require ( $include );
		} else {
			require( dirname( __FILE__ ) . '/theme/navbar.php' );	
		}
	}


	/** 
	 * Switches the template file (the actual HTML) to the one in this plugin, not to the theme's template.
	 * It also ensures just the right scripts and styles get loaded.
	 *
	 */
	function template_include( $template ) {
		global $content_width;

		// Check if we are supposed to change this template
		if( $this->get_storyform_template() ) {
			$template = dirname( __FILE__ ) . '/theme/single-storyform.php';


			// Remove all wp_head and wp_footer actions and script printing
			$this->update_functions();
			$this->remove_all_actions();
			
			add_action( 'wp_print_scripts', array( &$this, 'print_inline' ) );
			add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_files' ), 10000 );


			$content_width = 1920;

		// Check if this is a non-Storyform side of a/b tested Storyform article
		} else if( $this->is_sometimes_storyform() ) {
			add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_non_storyform' ) );
		}

		return $template;
	}

	/**
	 *	Removes all action-hooked functions we care about that are not in the default list or selected by the user.
	 *
	 */
	protected function remove_all_actions() {
		foreach( Storyform::get_actions_to_filter() as $tag ) {
			$this->remove_actions( $tag );
		}
	}

	public function remove_actions( $tag ) {
		global $wp_filter;

		$selected_functions = Storyform_Options::get_instance()->get_selected_functions();
		if( isset( $wp_filter[$tag] ) ){
			foreach( $wp_filter[$tag] as $priority => $functions ){
				foreach( $functions as $function ){
					$name = Storyform::get_persistent_callback_id( $function['function'] );
					if( !in_array( $name, array_keys( Storyform::get_default_functions() ) ) && !in_array( $name, $selected_functions )  ){
						remove_action( $tag, $function['function'], $priority );
					}
				}
			}	
		}
	}

	/**
	 *	Update our settings page with the list of all possible functions the author might want to allow
	 *
	 */
	protected function update_functions(){
		global $wp_filter;

		$all_functions = array();
		foreach( Storyform::get_actions_to_filter() as $tag ){
			if( isset( $wp_filter[$tag] ) ){
				foreach( $wp_filter[$tag] as $priority => $functions ){
					foreach( $functions as $function ){
						$name = Storyform::get_persistent_callback_id( $function['function'] );
						array_push( $all_functions, $name );
					}
				}	
			}
		}
		$non_default_functions = array_diff( $all_functions, array_keys( Storyform::get_default_functions() ) );
		
		$current_list = Storyform_Options::get_instance()->get_all_functions();
		if( count( array_diff( $non_default_functions, $current_list ) ) ){
			Storyform_Options::get_instance()->update_all_functions( $non_default_functions );
		}
	}

	/*
	 *	Similar to _wp_filter_build_unique_id this gets a global identifier for a function, but generates one that 
	 *  is persistent across sessions, albeit we can't truely identify two instances of the same class.
	 *
	 */
	public static function get_persistent_callback_id( $function ){
		if ( is_string( $function ) ){
			return $function;
		}

		if ( is_object( $function ) ) {
			// Closures are currently implemented as objects
			$function = array( $function, '' );
		} else {
			$function = (array) $function;
		}

		if (is_object( $function[0] ) ) {
			return get_class( $function[0] ) . ":" . $function[1];
			
		} else if ( is_string( $function[0] ) ) {
			// Static Calling
			return $function[0] . '::' . $function[1];
		}
	}

	/*
	 *	Gets the Storyform template (i.e. Puget) if already determined or does a check against stored values
	 *  for the current queried object.
	 *
	 *	@return {String|null} Returns the Storyform template to use (i.e. Puget) or null if none.
	 */
	public function get_storyform_template(){
		// Haven't figured out whether to use Storyform
		if( ! isset( $this->storyform_template ) ) {
			$id = get_queried_object_id();
			if( $id && is_single( $id ) ) {
				// Explicitly turned off storyform for this pageview
				if ( isset($_GET['storyform']) && $_GET['storyform'] === 'false' ){
					$this->storyform_template = null;	

				// Check if storyform post
				} else {
					$this->storyform_template = Storyform_Options::get_instance()->get_template_for_post( $id, null );

					if( !isset($_GET['storyform']) || $_GET['storyform'] !== 'true' ) {
						// A/B testing 
						$storyform_ab = Storyform_Options::get_instance()->get_ab_for_post( $id );
						if( $this->storyform_template && $storyform_ab && rand( 0, 1 ) < 0.5 ){
							wp_redirect( get_permalink( $id ) . "?storyform=false" );
							exit();
						}	
					}
					
				}
			}
		}
		return $this->storyform_template;
	}

	/**
	 *	Inrespective of whether this is a/b tested or storyform=false, returns if the current post is a Storyform post.
	 *
	 */
	public function is_sometimes_storyform(){
		if( $this->get_storyform_template() ){
			return TRUE;
		}

		$id = get_queried_object_id();
		if( $id && is_single( $id ) ) {
			return !!Storyform_Options::get_instance()->get_template_for_post( $id, null );
		}
		return FALSE;
	}

	/*
	 *	De/enqueue the JS/CSS files needed and not needed.
	 */
	function enqueue_files() {
		global $wp_styles;
		global $wp_scripts;

		// Dequeue all enqueued styles by default
		$default_dequeue_styles = array();
		if ( is_a( $wp_styles, 'WP_Styles' ) ) {
			foreach( $wp_styles->queue as $key => $value ){
				array_push( $default_dequeue_styles, $value );
			}
		}

		/**
		 * Filter the styles to dequeue on Storyform posts.
		 *
		 * @param array $styles The styles that will be dequeued. Defaults to all styles.
		 */
		$dequeue_styles = apply_filters( 'storyform-dequeue-styles', $default_dequeue_styles );

		if ( $dequeue_styles && is_array( $dequeue_styles ) ) {
			foreach( $dequeue_styles as $handle ) {
				wp_dequeue_style( $handle );
			}
		}

		// Dequeue all enqueued scripts by default
		$default_dequeue_scripts = array();
		if ( is_a( $wp_scripts, 'WP_Scripts' ) ) {
			foreach( $wp_scripts->queue as $key => $value ){
				array_push( $default_dequeue_scripts, $value );
			}
		}

		// Check if any new scripts have been registered and store them so we can present them to the user in settings
		$all_scripts = Storyform_Options::get_instance()->get_all_scripts();
		if( count( array_diff( $default_dequeue_scripts, $all_scripts ) ) ) {
			Storyform_Options::get_instance()->update_all_scripts( $default_dequeue_scripts );
		}

		// Do not dequeue those the user chose to keep
		$selected_scripts = Storyform_Options::get_instance()->get_selected_scripts();
		$selected_dequeue_scripts = array_diff( $default_dequeue_scripts, $selected_scripts );
		 
		/**
		 * Filter the scripts to dequeue on Storyform posts.
		 *
		 * @param array $scripts The scripts that will be dequeued. Defaults to all scripts.
		 */
		$dequeue_scripts = apply_filters( 'storyform-dequeue-scripts', $selected_dequeue_scripts );

		if ( $dequeue_scripts && is_array( $dequeue_scripts ) ) {
			foreach ( $dequeue_scripts as $handle ) {
				wp_dequeue_script( $handle );
			}
		}

		$instance = Storyform_Api::get_instance();
		wp_enqueue_script( 'storyform_js', $instance->get_js(), array(), false, false );
		wp_enqueue_style( 'storyform_css', $instance->get_css(), array(), false, false );

	}

	function enqueue_non_storyform() {
		$instance = Storyform_Api::get_instance();
		wp_enqueue_script( 'storyform_scroll_analytics', $instance->get_scroll_analytics_js(), array(), false, false );
	}

	/*
	 *	Specify the Storyform template to use, version and host in the client HTML
	 */
	function print_inline() {
		global $storyform_plugin_identifier;
		?>
		 <script>
			var _template = {
				group: '<?php echo $this->get_storyform_template() ?>',
				version: 'v<?php echo Storyform_Api::get_instance()->get_version() ?>',
				host: '<?php echo Storyform_Api::get_instance()->get_hostname() ?>',
				generator: '<?php echo $storyform_plugin_identifier ?>',
				appKey: '<?php echo Storyform_Options::get_instance()->get_application_key() ?>'
			};

			/*-- START RENDER SCRIPT --*/!function(a){function b(a,b){var c=new XMLHttpRequest;c.onreadystatechange=function(){4===c.readyState&&(c.status>=200&&c.status<300?b(null,c):b(c),c.onreadystatechange=function(){})},c.open("GET",a.uri,!0),c.send()}document.documentElement.className+=" js";var c=void 0===_template.host?"//storyform.co":_template.host,d=_template.version||"v0.5",e=_template.generator?"&generator="+_template.generator:"",f=c+"/"+d+"/render/"+a._template.group+"?appKey="+encodeURIComponent(_template.appKey)+"&uri="+encodeURIComponent(document.location.href)+"&lastModified="+encodeURIComponent(document.lastModified)+"&title="+document.title.substr(0,200)+"&width="+window.innerWidth+"&height="+window.innerHeight+"&deviceWidth="+window.screen.width+"&deviceHeight="+window.screen.height+e;a.App=a.App||{},a.App.Data=a.App.Data||{},a.App.Data.render={callback:function(){},data:null,uri:f},b({uri:f},function(b,c){if(c){var d=a.App.Data.render.data=JSON.parse(c.responseText);a.App.Data.render.callback(null,d),a.App.Data.render.callback=function(){}}else a.App.Data.render.error=b,a.App.Data.render.callback(b),a.App.Data.render.callback=function(){}})}(this);/*-- END RENDER SCRIPT --*/
		</script>
		<?php
	}

}