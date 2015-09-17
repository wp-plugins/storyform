<?php 

/**
 *	Handles creating and saving Storyform admin meta box
 *
 */
class Storyform_Admin_Meta_Box {

	public static function init() {
		add_action( 'load-post.php', array( __CLASS__, 'post_meta_boxes_setup' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'post_meta_boxes_setup' ) );
	}

	/** 
	 * Create a meta boxes to be displayed on the post editor screen to let the user choose whether to use
	 * Storyform on this post or opt to not.
	 *
	 */
	public static function add_post_meta_boxes() {
		global $post;

		$post_id = get_the_ID();
		$options = Storyform_Options::get_instance();
		if( $template = $options->get_template_for_post( $post_id ) ) {

			add_filter( 'wp_editor_settings' , array( 'Storyform_Admin_Meta_Box', 'turn_off_editor' ), 9999, 2 );

			// If published use the latest revision
			if( $post->post_status === 'publish' ){
				$revisions = array_values( wp_get_post_revisions( $post_id ) );

				if( count( $revisions ) ){
					$post->post_content = $revisions[0]->post_content;
					$post->post_title = $revisions[0]->post_title;
					$post->post_excerpt = $revisions[0]->post_excerpt;
				}
			}

			add_meta_box(
				'storyform-editor-replacement',
				esc_html__( 'Storyform Editor', Storyform_Api::get_instance()->get_textdomain() ), 
				array( __CLASS__, 'templates_editor_replacement' ), 
				get_post_type(),
				'normal', 
				'high' 
			);
		} else {
			$post_types = get_post_types(); 
			$ignore = array( 'attachment', 'revision', 'nav_menu_item' );
			foreach( $post_types as $type ){
				if( !in_array( $type, $ignore ) ){
					add_meta_box(
						'storyform-templates',
						esc_html__( 'Storyform templates', Storyform_Api::get_instance()->get_textdomain() ), 
						array( __CLASS__, 'templates_meta_box' ), 
						$type,
						'side', 
						'default' 
					);
				}
			}
		}
		
	}

	/**
	 *	Turns off basics about the editor. Important as TinyMCE will destroy certain elements
	 *	in the Storyform editor output like <picture> or <post-publisher>.
	 *
	 */
	public static function turn_off_editor( $settings, $editor_id ) {
		return array(
			'wpautop'             => false,
			'media_buttons'       => false,
			'default_editor'      => 'html',
			'drag_drop_upload'    => false,
			'textarea_name'       => $editor_id,
			'textarea_rows'       => 20,
			'tabindex'            => '',
			'tabfocus_elements'   => ':prev,:next',
			'editor_css'          => '',
			'editor_class'        => '',
			'teeny'               => false,
			'dfw'                 => false,
			'_content_editor_dfw' => false,
			'tinymce'             => false,
			'quicktags'           => false
		);
	}

	public static function templates_editor_replacement( $object, $box ) {
		$post_id = get_the_ID();
		$nonce = wp_create_nonce( "storyform-post-nonce" );
		$unpublished_changes = false;

		$post = get_post( $post_id )->to_array();

		// If published, grab the latest revision as there may be some unpublished changes
		if( $post['post_status'] === 'publish' ){
			$array = array_values( wp_get_post_revisions( $post_id ) );
			if( count( $array ) ){
				$revision = $array[0]->to_array();
				if( strtotime( $revision['post_date'] ) > strtotime( $post['post_modified'] ) ){
					$unpublished_changes = true;
				}
			}
		}
		?> 
		<style type="text/css">
			#postdivrich, 
			.postarea {
				display: none;
			}
		</style>
		<div class="storyform-editor-replacement"> 
			<p><?php echo $unpublished_changes ? 'You have unpublished changes to the title, excerpt and/or content.' : '' ?></p>
			<a class="button-primary" href="<?php echo admin_url( 'admin.php?page=storyform-editor&post=' . $post_id ) ?>">Edit Storyform</a>
			<br />
			<br />
			<a href="<?php echo admin_url( "admin.php?page=storyform-editor&post={$post_id}&remove=true&_wpnonce={$nonce}" ) ?>">Turn off Storyform</a> | <a href="" id="storyform_view_published_html">View HTML</a> 
		</div>
		<script>
			jQuery('#storyform_view_published_html').click(function(ev){
				jQuery('#postdivrich, .postarea').show();
				ev.preventDefault();
				return false;
			});
		</script>
		<?php
	}

	/* 
	 * Display the post meta box. 
	 */
	public static function templates_meta_box( $object, $box ) {
		self::_templates_meta_box( $object, $box, 'post' );
	}

	public static function _templates_meta_box( $object, $box, $type ) {
		$post_id = get_the_ID();
		
		$options = Storyform_Options::get_instance();
		$template = $options->get_template_for_post( $post_id ); 

		?>

		<a class="button-primary" href="<?php echo admin_url( 'admin.php?page=storyform-editor&post=' . $post_id ) ?>">
			<?php echo $template ? 'Edit Storyform' : 'Create Storyform with ' . $type; ?>
		</a><br />
		<small>Save, publish or update prior to switching</small>

	<?php 
	}

	/* 
	 * Meta box setup function. 
	 * Fire our meta box setup function on the post editor screen.
	 */
	public static function post_meta_boxes_setup() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_post_meta_boxes' ), 1 );
	}

}
