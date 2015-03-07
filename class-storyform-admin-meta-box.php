<?php 

/**
 *	Handles creating and saving Storyform admin meta box
 *
 */
class Storyform_Admin_Meta_Box {

	public static function init() {
		add_filter( 'content_save_pre', array( __CLASS__, 'save_add_remove_attributes' ) );
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
				<div class="storyform-input-group storyform-improve">
					<progress class="storyform-improve-progress storyform-improve-low" value="1" max="4" ></progress><span class="storyform-improve-text storyform-improve-low"><span class="storyform-improve-count">0</span> post recommendation(s)</span>
					<ul class="storyform-improve-items">
						<li class="storyform-improve-bad" id="storyform-improve-description-pullquote"><span id="storyform-improve-pullquote-text" data-no="No pullquotes" data-yes="Includes pullquote"></span>
							<div data-storyform-tooltip="storyform-improve-description-pullquote">
								<div class="storyform-tooltip-title">Identify interesting snippets of text</div>
								Add pullquotes by highlighting a snippet of text in your post and clicking the pullquote button in the editor toolbar. <br /> <img width="23" height="23" title="" alt="" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAC4AAAAuCAYAAABXuSs3AAABfGlDQ1BJQ0MgUHJvZmlsZQAAKJFjYGAqSSwoyGFhYGDIzSspCnJ3UoiIjFJgv8PAzcDDIMRgxSCemFxc4BgQ4MOAE3y7xsAIoi/rgsxK8/x506a1fP4WNq+ZclYlOrj1gQF3SmpxMgMDIweQnZxSnJwLZOcA2TrJBUUlQPYMIFu3vKQAxD4BZIsUAR0IZN8BsdMh7A8gdhKYzcQCVhMS5AxkSwDZAkkQtgaInQ5hW4DYyRmJKUC2B8guiBvAgNPDRcHcwFLXkYC7SQa5OaUwO0ChxZOaFxoMcgcQyzB4MLgwKDCYMxgwWDLoMjiWpFaUgBQ65xdUFmWmZ5QoOAJDNlXBOT+3oLQktUhHwTMvWU9HwcjA0ACkDhRnEKM/B4FNZxQ7jxDLX8jAYKnMwMDcgxBLmsbAsH0PA4PEKYSYyjwGBn5rBoZt5woSixLhDmf8xkKIX5xmbARh8zgxMLDe+///sxoDA/skBoa/E////73o//+/i4H2A+PsQA4AJHdp4IxrEg8AAAAJcEhZcwAAFiUAABYlAUlSJPAAAAGbaVRYdFhNTDpjb20uYWRvYmUueG1wAAAAAAA8eDp4bXBtZXRhIHhtbG5zOng9ImFkb2JlOm5zOm1ldGEvIiB4OnhtcHRrPSJYTVAgQ29yZSA1LjQuMCI+CiAgIDxyZGY6UkRGIHhtbG5zOnJkZj0iaHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyI+CiAgICAgIDxyZGY6RGVzY3JpcHRpb24gcmRmOmFib3V0PSIiCiAgICAgICAgICAgIHhtbG5zOmV4aWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20vZXhpZi8xLjAvIj4KICAgICAgICAgPGV4aWY6UGl4ZWxYRGltZW5zaW9uPjQ2PC9leGlmOlBpeGVsWERpbWVuc2lvbj4KICAgICAgICAgPGV4aWY6UGl4ZWxZRGltZW5zaW9uPjQ2PC9leGlmOlBpeGVsWURpbWVuc2lvbj4KICAgICAgPC9yZGY6RGVzY3JpcHRpb24+CiAgIDwvcmRmOlJERj4KPC94OnhtcG1ldGE+CtvE32YAAAHnSURBVGgF7Zk7qgIxFIb/GS8+sFHw1dha2drqGtyAu7B1AW5ErLS31QUIIoqglY0Kgm9EvfcmMEVwwsSrJ9xAAjKek8f5/HNyjOgcj8dvGNhcA5k5sgXXvXNWcd2KfwUFbDabuFwuQcPI+huNhu/aNlV8ZSF0WsUJxfVd2iruKwuh01jFA+u4qmj1eh2xWOxp+O/tE/v9HrvdDuPxGKPRCI/H42ncq46PgcsCx+NxsFcul0OhUEClUkG73cZqtZJNUfJrT5VkMolqtYpQKKQEKBtEovhsNkOn04HjOIhGo8hmsyiXy1x1BsLsYrGI4XAo4wr0kyh+v9/5/eZ8PmO73WIymaDVagkw6XRasF81SMD9INgBZR/Ea6lUynv7p6c28EwmI1Sd0+n0J2BvkhZw13VRKpW8mPy5Xq8F+1WD5HDm83nUajXOEolEkEgkBLVvtxvYAX6nkYB7tVsG1uv1sNlsZN1KfhJwWeT5fI7BYIDFYiEbouwnAV8ul+j3+xyC/ew7HA78a/96vSqDBQ0kAWelbzqdBsV+q19LVXmLUDLZWPCPpQorb+FwmOvDcpy6fQy82+1SswrrG5sqFlzYRw2GVVyDyEIIYxUPLIfsWvofm2P/ddO8LcbmuAXXnCmwilvFFRX4AaQ/f2y1tlKbAAAAAElFTkSuQmCC" />
							</div>
						</li>
						<li class="storyform-improve-bad" id="storyform-improve-crop"><span id="storyform-improve-description-crop"><span id="storyform-improve-crop-count">0</span> of <span id="storyform-improve-crop-total">12</span> images optimized.</span> <a class="storyform-improve-crop-action">Optimize</a>
							<div data-storyform-tooltip="storyform-improve-description-crop">
								<div class="storyform-tooltip-title">Optimize images for full bleed</div>
								Enable smart cropping of images to keep them full bleed on different screen sizes. By identifying the subject of an image, images can appropriately be cropped while still keeping what's important.
							</div>
						</li>
						
						<li class="storyform-improve-bad" id="storyform-improve-caption"><span id="storyform-improve-description-caption"><span id="storyform-improve-caption-count">0</span> of <span id="storyform-improve-caption-total">0</span> captions optimized.</span> <a class="storyform-improve-caption-action">Optimize</a>
							<div data-storyform-tooltip="storyform-improve-description-caption">
								<div class="storyform-tooltip-title">Specify caption placement</div>
								Allow captions to be placed directly on top of the photo without a background plate by specifying areas of the photo where light or dark text can still be visible.
							</div>
						</li>
					</ul>

				</div>
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
		$template = ( $template == 'pthemedefault' ) ? null : $template;
		$name = sanitize_text_field( strtolower( $_POST['post_name'] ) );

		$layout_type = $template ? sanitize_text_field( strtolower( $_POST['storyform-layout-type'] ) ) : null;
		$use_featured_image = $template ? isset( $_POST['storyform-use-featured-image'] ) && $_POST['storyform-use-featured-image'] === 'on' : true;
		
		$ab = isset( $_POST['storyform-ab'] );
		
		$options = Storyform_Options::get_instance();
		$options->update_template_for_post( $id, $name, $template);
		$options->update_ab_for_post( $id, $ab);
		$options->update_layout_type_for_post( $id, $layout_type );
		$options->update_use_featured_image_for_post( $id, $use_featured_image );
	}

	/**
	 * Adds and removes data- attributes as Storyform is turned off and on on a post.
	 *
	 * @param string $content The content of a post
	 */
	public static function save_add_remove_attributes( $content ) {
		global $post;
		global $_POST;
		global $storyform_media;

		if( !is_object($post) || !$post->ID ){
			return $content;
		}

    	$post_id = $post->ID;

    	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $content;
		}

		// If this is a revision, don't do anything
		if ( wp_is_post_revision( $post_id ) ) {
			return $content;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $content;
			}

		} else {

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $content;
			}
		}

    	// Make sure that it is set.
		if ( ! isset( $_POST['storyform-templates'] ) ) {
			return $content;
		}

    	// Read POST as we may be changing the value
		$template = sanitize_text_field( $_POST['storyform-templates'] );
		$template = ( $template == 'pthemedefault' ) ? null : $template;

		$options = Storyform_Options::get_instance();
		$old_template = $options->get_template_for_post( $post_id, null );

		// Only run if changing from Storyform to not or vise versa
		if( !$old_template && $template ) {
			$content = $storyform_media->add_data_attributes( $post_id, $content );
		} else if($old_template && !$template) {
			$content = $storyform_media->remove_data_attributes( $post_id, $content );
		}
		return $content;
	}
}
