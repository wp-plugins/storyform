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
		$use_featured_image = $options->get_use_featured_image_for_post( $post_id ) ? 'checked' : '';
		
		$apiversiondir = Storyform_Api::get_instance()->get_version_directory();

		wp_nonce_field( 'storyform_meta_box', 'storyform_meta_box_nonce' );


		?>

		<p>
			<style type="text/css">
				#storyform-status {
					padding: 0;
					margin: 0;
					color: red;
				}

				.storyform-form-item {
					margin-bottom:10px;
				}

			</style>
			<label for="storyform-templates"><?php esc_attr_e( 'Choose which Storyform templates to use for this post.', Storyform_Api::get_instance()->get_textdomain() ); ?></label>
			<p id="storyform-status"></p>
			<br />
			<select class="widefat storyform-form-item" name="storyform-templates" id="storyform-templates-select" >
				<option id="storyform-default-theme" value="pthemedefault">[Do not use Storyform]</option>
				<option id="storyform-templates-loading" value="loading" disabled="true">Loading other themes...</option>
			</select>
			<label id="storyform-use-featured-image" style="display:none;">
				<input class="storyform-form-item" type="checkbox" name="storyform-use-featured-image" <?php echo $use_featured_image; ?> />
				<?php esc_attr_e( 'Insert Featured Image into post as cover photo.', Storyform_Api::get_instance()->get_textdomain() ); ?>
			</label>
			<script>
				function xhr(options, cb){
					var req = new XMLHttpRequest();
					req.onreadystatechange = function () {
						if (req.readyState === 4) {
							if (req.status >= 200 && req.status < 300) {
								cb(req);
							} else {
								cb(null, req);
							}
							req.onreadystatechange = function () { };
						} else {
							cb(null, null, req);
						}
					};

					req.open('GET', options.url, false);
					req.send();

				}

				var el = document.getElementById('storyform-templates-select');
				var loading = document.getElementById('storyform-templates-loading');
				var featuredImage = document.getElementById('storyform-use-featured-image');

				<?php if( $template && $template != 'pthemedefault' ) { ?>

				var option = document.createElement('option');
				option.value = '<?php echo $template ?>';
				option.textContent = '<?php echo $template ?>';
				option.setAttribute('selected', true);
				featuredImage.style.display = '';

				el.appendChild(option);
				el.appendChild(loading); // Move loading to the end

				<?php } ?>

				el.addEventListener('focus', function clickSelect(){
					xhr({url: '<?php echo $apiversiondir ?>data/templategroups?app_key=<?php echo $app_key ?>'}, function(response, err){
						if(response){
							loading.parentNode.removeChild(loading);
							var templates = JSON.parse(response.responseText);
							templates.forEach(function(template){
								if(template.id !== '<?php echo $template ?>'){
									var option = document.createElement('option');
									option.value = template.id;
									option.textContent = template.id;
									el.appendChild(option);
								}
								
							});
						} else if(err){
							document.getElementById('storyform-status').textContent = 'Cannot retrieve templates. Ensure Settings > Storyform Settings > Application key is set correctly.';

						}
					});
					el.removeEventListener('focus', clickSelect, false);
				}, false);

				el.addEventListener('change', function change(){
					featuredImage.style.display = (this.value === 'pthemedefault') ? 'none' : '';	
				}, false);
				
			</script>

		</p>

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
		$use_featured_image = $_POST['storyform-use-featured-image'] === 'on';

		$options = Storyform_Options::get_instance();
		$options->update_template_for_post( $id, $name, $template);
		$options->update_use_featured_image_for_post( $id, $use_featured_image );

	}
}
