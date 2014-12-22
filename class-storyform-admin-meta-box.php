<?php 

/**
 *	Handles creating and saving Storyform admin meta box
 *
 */
class Storyform_Admin_Meta_Box {

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'save_meta_box_data' ) );
		add_action( 'load-post.php', array( __CLASS__, 'post_meta_boxes_setup' ) );
		add_action( 'load-post-new.php', array( __CLASS__, 'post_meta_boxes_setup' ) );
	}

	/** 
	 * Create a meta boxes to be displayed on the post editor screen to let the user choose whether to use
	 * Storyform on this post or opt to not.
	 *
	 */
	public static function add_post_meta_boxes() {
		add_meta_box(
			'storyform-templates',          // Unique ID
			esc_html__( 'Storyform templates', Storyform_Api::get_instance()->get_textdomain() ),     // Title
			array( __CLASS__, 'templates_meta_box' ),      // Callback function
			'post',                 // Admin page (or post type)
			'side',                 // Context
			'default'                   // Priority
		);
	}

	/* 
	 * Display the post meta box. 
	 */
	public static function templates_meta_box( $object, $box ) {
		$post_id = get_the_ID();
		
		$options = Storyform_Options::get_instance();
		$app_key = $options->get_application_key();
		// Get any previously specified template
		$template = $options->get_template_for_post( $post_id, null ); 
		$layout_type = $options->get_layout_type_for_post( $post_id );
		$use_featured_image = $options->get_use_featured_image_for_post( $post_id ) ? 'checked' : '';
		$ab = $options->get_ab_for_post( $post_id ) ? 'checked' : '';
		
		$hostname = Storyform_Api::get_instance()->get_hostname();

		wp_nonce_field( 'storyform_meta_box', 'storyform_meta_box_nonce' );

		?>

		<div class="storyform-admin-meta-box">
			<label for="storyform-templates"><?php printf( __( 'Choose which Storyform templates to use for this post or <a href="%s" target="_blank">create/edit a template</a>.', Storyform_Api::get_instance()->get_textdomain() ), Storyform_Api::get_instance()->get_hostname() . '/user/templates' ); ?></label>
			<p id="storyform-status"></p>
			<br />
			<select class="widefat storyform-form-item" name="storyform-templates" id="storyform-templates-select" >
				<option id="storyform-default-theme" value="pthemedefault">[Do not use Storyform]</option>
				<option id="storyform-templates-loading" value="loading" disabled="true">Loading templates (reopen to view)...</option>
			</select>
			<div class="storyform-post-options">
				<div class="storyform-input-group">
					<strong>Layout</strong>
					<label class="storyform-radio-label"><input type="radio" class="storyform-layout-type" name="storyform-layout-type" value="freeflow" <?php echo $layout_type === 'freeflow' ? 'checked' : '' ?>/>Free flow <a id="storyform-layout-description-freeflow" class="storyform-layout-description"></a></label>
					<div data-storyform-tooltip="storyform-layout-description-freeflow">
						<div class="storyform-tooltip-title">Recommended when media applies to any part of the content.</div>
						Spreads media throughout the content (images and video unpinned by default) and flows text across pages.
					</div>
					<label class="storyform-radio-label"><input type="radio" class="storyform-layout-type" name="storyform-layout-type" value="ordered" <?php echo $layout_type === 'ordered' ? 'checked' : '' ?>/>Ordered <a class="storyform-layout-description" id="storyform-layout-description-ordered"></a></label>
					<div data-storyform-tooltip="storyform-layout-description-ordered">
						<div class="storyform-tooltip-title">Recommended when media needs to maintain its placement.</div>
						Maintains order of content (images and video pinned by default) and flows text across pages.
					</div>
					<label class="storyform-radio-label"><input type="radio" class="storyform-layout-type" name="storyform-layout-type" value="slideshow" <?php echo $layout_type === 'slideshow' ? 'checked' : '' ?>/>Slideshow <a class="storyform-layout-description" id="storyform-layout-description-slideshow"></a></label>
					<div data-storyform-tooltip="storyform-layout-description-slideshow">
						<div class="storyform-tooltip-title">Recommended when text regions shouldn’t be broken up.</div>
						Maintains order of content (images and video pinned by default) and discourages the flow of text across pages.
					</div>
				</div>
				<label id="storyform-use-featured-image">
					<input class="storyform-form-item" type="checkbox" name="storyform-use-featured-image" <?php echo $use_featured_image; ?> />
					<?php esc_attr_e( 'Insert Featured Image into post as cover photo.', Storyform_Api::get_instance()->get_textdomain() ); ?>
				</label>
				<div>
					<label id="storyform-ab-test" class="hidden">
						<input class="storyform-form-item" type="checkbox" name="storyform-ab" <?php echo $ab; ?> />
						<?php esc_attr_e( 'A/B test Storyform (50% with / 50% without)', Storyform_Api::get_instance()->get_textdomain() ); ?>
					</label>
				</div>
			</div>
			<script>
				var storyform = window.storyform || {};
				storyform.template = '<?php if( $template && $template != 'pthemedefault' ) { echo $template; } ?>';
				storyform.hostname = '<?php echo $hostname ?>';
				storyform.app_key = '<?php echo $app_key ?>';
				storyform.settingsUrl = "<?php echo admin_url( 'options-general.php?page=storyform-setting-admin' ) ?>";
			</script>

		</div>

	<?php }

	/* 
	 * Meta box setup function. 
	 * Fire our meta box setup function on the post editor screen.
	 */
	public static function post_meta_boxes_setup() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_post_meta_boxes' ) );
	}

	/**
	 * When the post is saved, saves our Storyform template choice.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public static function save_meta_box_data( $post_id ) {
		global $_POST;
		global $storyform_media;

		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['storyform_meta_box_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['storyform_meta_box_nonce'], 'storyform_meta_box' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// If this is a revision, don't do anything
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}

		} else {

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}

		/* OK, its safe for us to save the data now. */
		
		// Make sure that it is set.
		if ( ! isset( $_POST['storyform-templates'] ) ) {
			return;
		}

		// Sanitize user input.
		$id  = intval( $post_id );
		$template = sanitize_text_field( $_POST['storyform-templates'] );
		$template = ( $template == 'pthemedefault' ) ? null: $template;
		$name = sanitize_text_field( strtolower( $_POST['post_name'] ) );
		$layout_type = sanitize_text_field( strtolower( $_POST['storyform-layout-type'] ) );
		$use_featured_image = $_POST['storyform-use-featured-image'] === 'on';
		$ab = isset( $_POST['storyform-ab'] );
		
		$options = Storyform_Options::get_instance();
		$options->update_template_for_post( $id, $name, $template);
		$options->update_ab_for_post( $id, $ab);
		$options->update_layout_type_for_post( $id, $layout_type );
		$options->update_use_featured_image_for_post( $id, $use_featured_image );

		// Avoid infinite loop by removing save_post hook temporarily
		remove_action( 'save_post', array( __CLASS__, 'save_meta_box_data' ) );
		if( $template ) {
			$content = $storyform_media->add_data_attributes( $id, $_POST['content'] );
		} else {
			$content = $storyform_media->remove_data_attributes( $id, $_POST['content'] );
		}
		wp_update_post( array( 'ID' => $id, 'post_content' => $content ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_box_data' ) );
	}
}
