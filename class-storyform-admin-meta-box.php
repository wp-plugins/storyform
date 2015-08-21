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
		

		$post_id = get_the_ID();
		$options = Storyform_Options::get_instance();
		if( $template = $options->get_template_for_post( $post_id ) ) {
			//remove_post_type_support( get_post_type(), 'editor' );

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

	public static function templates_editor_replacement( $object, $box ) {
		$post_id = get_the_ID();
		?> 
		<style type="text/css">
			#postdivrich, .postarea {
				display: none;
			}
		</style>
		<div class="storyform-editor-replacement"> 
			<a class="button-primary" href="<?php echo admin_url( 'admin.php?page=storyform-editor&post=' . $post_id ) ?>">Edit Storyform</a>
			<br />
			<br />
			<a href="<?php echo admin_url( 'admin.php?page=storyform-editor&post=' . $post_id . '&remove' ) ?>">Turn off Storyform</a>
		</div>
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
		</a>

	<?php 
	}

	/* 
	 * Meta box setup function. 
	 * Fire our meta box setup function on the post editor screen.
	 */
	public static function post_meta_boxes_setup() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_post_meta_boxes' ) );
	}

}
