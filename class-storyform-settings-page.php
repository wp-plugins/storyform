<?php
/**
 * Handles setting up the Admin Settings > Storyform Settings page, which takes an application key.
 *
 */
class Storyform_Settings_Page
{

	/**
	 * Start up
	 */
	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page()
	{
		// This page will be under "Settings"
		add_options_page(
			'Settings Admin',
			'Storyform Settings',
			'manage_options',
			'storyform-setting-admin',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page()
	{
		?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2>Storyform Settings</h2>

		<form method="post" action="options.php">
			<?php
			// This prints out all hidden setting fields
			settings_fields( 'storyform_option_group' );
			do_settings_sections( 'storyform-setting-admin' );
			submit_button();
			?>
		</form>
		<?php if( ! function_exists( 'wpcom_vip_get_resized_attachment_url' ) ){ ?>
		<script>
			(function(){
				var hidden = false;
				function toggleAdvanced(){
					hidden = !hidden;
					table.style.display = hidden ? 'none' : '';
					toggle.textContent = toggle.getAttribute('data-' + (hidden ? 'show' : 'hide') + '-text');
				}
				var table = document.querySelector('.storyform_toggle_advanced + table');
				var toggle = document.querySelector('.storyform_toggle_advanced');
				toggleAdvanced();
				toggle.addEventListener('click', function(){
					toggleAdvanced();
					return false;
				});
			})();
			
		</script>
		<?php } ?>
	</div>
	<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init()
	{
		register_setting(
			'storyform_option_group', // Option group
			'storyform_settings', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section(
			'storyform_section_id', // ID
			'Application Settings', // Title
			array( $this, 'print_section_info' ), // Callback
			'storyform-setting-admin' // Page
		);

		add_settings_field(
			'storyform_application_key', // ID
			'Storyform application key', // Title
			array( $this, 'storyform_application_key_callback' ), // Callback
			'storyform-setting-admin', // Page
			'storyform_section_id' // Section
		);

		// In VIP you don't actually generate multiple images, its done on the fly
		if( ! function_exists( 'wpcom_vip_get_resized_attachment_url' ) ){
			add_settings_section(
				'storyform_advanced_section_id', // ID
				'Advanced Settings', // Title
				array( $this, 'print_advanced_section_info' ), // Callback
				'storyform-setting-admin' // Page
			);

			add_settings_field(
				'storyform_add_image_sizes', // ID
				'Generate additional image sizes', // Title
				array( $this, 'storyform_add_image_sizes_callback' ), // Callback
				'storyform-setting-admin', // Page
				'storyform_advanced_section_id' // Section
			);
		}

	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 */
	public function sanitize( $input )
	{
		$new_input = array();
		if( isset( $input['storyform_application_key'] ) ) {
			$new_input['storyform_application_key'] =  sanitize_text_field( $input['storyform_application_key'] );
		}

		// If its not set we know the user unchecked it
		if( isset( $input['storyform_add_image_sizes'] ) ) {
			$new_input['storyform_add_image_sizes'] = TRUE;
		} else {
			$new_input['storyform_add_image_sizes'] = FALSE;
		}

		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info()
	{
	}

	/**
	 * Print the Section text
	 */
	public function print_advanced_section_info()
	{
		print '<a href="#" class="storyform_toggle_advanced" data-hide-text="Hide advanced settings" data-show-text="Show advanced settings"></a>';
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function storyform_application_key_callback()
	{
		$key = Storyform_Options::get_instance()->get_application_key();
		printf(
			'<input type="text" id="storyform_application_key" name="storyform_settings[storyform_application_key]" value="%s" /><br />
			<label><small>Sign up at <a target="_blank	" href="http://storyform.co/#signup">Storyform</a></small></label>',
			$key ? esc_attr( $key ) : ''
		);
	}

	/**
	 *  Display whether to add additional image sizes (for different screen sizes). Default to checked.
	 */
	public function storyform_add_image_sizes_callback()
	{
		$add_image_sizes = Storyform_Options::get_instance()->get_add_image_sizes();
		printf(
			'<input type="checkbox" id="storyform_add_image_sizes" name="storyform_settings[storyform_add_image_sizes]" %s /><br />
			<label><small>Adds a 1366x768 and 1920x1080 image candidate for newly uploaded media.</small></label>',
			$add_image_sizes ? 'checked' : ''
		);
	}

}