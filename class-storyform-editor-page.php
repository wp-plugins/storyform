<?php
/**
 * Handles setting up the Storyform > Add New page, which takes an application key.
 *
 */
class Storyform_Editor_Page
{

	/**
	 * Start up
	 */
	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ), 500 );
		add_action( 'wp_ajax_storyform_get_post', array( $this, 'storyform_get_post' ) );
		add_action( 'wp_ajax_storyform_create_post', array( $this, 'storyform_create_post' ) );
		add_action( 'wp_ajax_storyform_update_post', array( $this, 'storyform_update_post' ) );
		add_action( 'wp_ajax_storyform_publish_post', array( $this, 'storyform_publish_post' ) );
		add_action( 'wp_ajax_storyform_delete_post', array( $this, 'storyform_delete_post' ) );
		add_action( 'wp_ajax_storyform_get_post_types', array( $this, 'storyform_get_post_types' ) );	
		add_action( 'wp_ajax_storyform_get_media_sizes', array( $this, 'storyform_get_media_sizes' ) );	
		
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page()
	{
		$hook_suffix = add_menu_page(
			'Storyform',
			'Storyform',
			'manage_options',
			'storyform-editor',
			array( $this, 'create_post_page' ),
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjEwIDAgMzAwLjMzMzMgMjY5LjMzMzM0IiBoZWlnaHQ9IjI2OS4zMzMiIHdpZHRoPSIzMDAuMzMzIj48ZGVmcz48Y2xpcFBhdGggaWQ9ImEiPjxwYXRoIGQ9Ik0wIDIzMmg3NjBWMEgweiIvPjwvY2xpcFBhdGg+PC9kZWZzPjxwYXRoIGQ9Ik0yMjMuMDA3IDI1OC45MjVIMTAzLjcwM2wtNTkuNjQ4LTEwMy4yNCA1OS42NDgtMTAzLjI0MmgxMTkuMzA0bDU5LjY0OCAxMDMuMjR6TTIzMC4yIDM5Ljk4SDk2LjUxTDI5LjY2NiAxNTUuNjg1bDY2Ljg0NiAxMTUuN0gyMzAuMmw2Ni44NDYtMTE1Ljd6IiBmaWxsPSIjMjQyNDI0Ii8+PGcgY2xpcC1wYXRoPSJ1cmwoI2EpIiB0cmFuc2Zvcm09Im1hdHJpeCgxLjMzMzMzIDAgMCAtMS4zMzMzMyAwIDMwOS4zMzMpIj48cGF0aCBkPSJNMTE0LjkyOCAxMzAuOTU4Yy0uNDY2IDEwLjM2LTUuMDQgMTUuNzctMTEuMzg1IDE1Ljc3LTYuMjUyIDAtOC43NzItNC4zODUtOC43NzItOS4wNSAwLTUuNiAzLjY0LTguNjggMTAuMjY3LTEyLjc4NSA5Ljg5Mi02LjI1MyAxMy40MzgtOS44IDEzLjQzOC0xNy43MyAwLTExLjI5Mi03LjY1Mi0xOS45Ny0yMy40MjMtMjAuMTU4LTYuOTk4LS4wOTMtMTUuODYzIDEuODY3LTIwLjYyMiA3LjQ2NWwxLjk2IDE2LjA1MmgzLjU0NmMxLjEyLTEyLjY5MiA4LjU4NS0xOS40MSAxNS4zOTctMTkuNDEgNi4xNiAwIDkuNTIgMy45MiA5LjUyIDkuNzk4IDAgNS43ODYtNC4yIDkuMTQ1LTguNjggMTIuMTMtOS43OTcgNi4zNDctMTQuNTU3IDEwLjgyNi0xNC41NTcgMTkuMTMyIDAgNy41NiA0LjAxMyAxOC44NSAyMi4zOTcgMTguODUgNS4zMiAwIDEyLjU5OC0xLjc3MyAxNy40NS01LjZsLTIuODkyLTE0LjQ2NHptMTguMjktMy44MjZsMS4yMTIgNC4zODZoNy4zNzJjMS42OCA2LjA2NSAzLjM2IDExLjAxMiA3LjM3MiAxNS40OSAzLjI2NyAzLjY0IDcuOTMzIDYuMzQ3IDE0LjE4NSA2LjM0NyA4LjY3NyAwIDExLjI5LTMuMzYgMTEuMjktNi44MTIgMC0zLjI2Ny0zLjczMy02LjE2LTYuMzQ2LTYuMTYtMi4yNCAwLTMuMTczIDEuMjEzLTMuNjQgMi44LTEuNCA0LjU3Mi0yLjQyNiA2LjA2Ni0zLjU0NiA2LjA2Ni0xLjMwNyAwLTIuMzM0LTEuNjgtMy4wOC00Ljk0OGwtMi44LTEyLjc4NWg5LjQyNWwtMS4xMi00LjM4NkgxNTQuNGwtOC4zMDUtMzYuNThjLTMuNDUzLTE1LjU4NC0xMS40NzgtMjMuNjEtMjIuMzAzLTIzLjYxLTcgMC0xMS43NTggMi44OTMtMTEuNzU4IDYuNjI3IDAgMi44IDMuNTQ2IDYuOTA1IDYuNTMyIDYuOTA1IDIuMTQ2IDAgMy4xNzMtMS40IDMuODI2LTIuOCAyLjYxMy01LjQxMiA0LjI5My02LjM0NSA1LjEzMi02LjM0NSAxLjQgMCAyLjE0NyAyLjUyIDIuNTIgNC4wMTMgMi42MTMgMTAuNTQ1IDguMzA2IDM4LjU0IDEwLjgyNSA1MS43OTJ6IiBmaWxsPSIjMjQyNDI0Ii8+PC9nPjwvc3ZnPg==',
			6
		);

		add_submenu_page(
			'storyform-editor', 
			'Add new Storyform', 
			'Add new', 
			'manage_options', 
			'storyform-editor'
		);

		add_action( 'load-' . $hook_suffix , array( $this, 'hook_create_page' ), 99999 );
	}

	public function hook_create_page(){
		if( isset( $_GET[ 'remove' ] ) && isset( $_GET[ 'post' ] ) ){
			$post_id = intval( $_GET['post'] );
			Storyform_Options::get_instance()->update_template_for_post( $post_id, null );	
			wp_redirect( get_edit_post_link( $post_id ) );
		}

		add_filter( 'admin_body_class', array( $this, 'add_folded_class' ) );
		add_filter( 'admin_footer_text', array( $this, 'remove_footer_admin' ), 99999 );
		add_filter( 'update_footer', array( $this, 'remove_footer_admin' ), 99999);
		add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_files' ), 99999 );
	}

	public function add_folded_class( $classes ){
		return $classes . ' folded ';

	}

	public function remove_footer_admin() {}

	public function enqueue_files(){
		wp_enqueue_style( 'storyform-wp-editor', plugin_dir_url( __FILE__ ) . 'editor/editor.css');
		wp_enqueue_script( 'storyform-wp-editor', plugin_dir_url( __FILE__ ) . 'editor/editor.js');
		wp_enqueue_media();
	}

	public function create_post_page(){
		global $storyform_version;

		$ajax_nonce = wp_create_nonce( "storyform-post-nonce" );
		
		$hostname = Storyform_API::get_instance()->get_hostname();

		$post_id = isset( $_GET[ 'post' ] ) ? intval( $_GET['post'] ) : null;
		$post_type = isset( $_GET[ 'post_type' ] ) ? $_GET['post_type'] : null;

		if($post_id){
			$url = $hostname . '/posts/' . $post_id . '/edit-wp?' . ('wp_version=' . get_bloginfo('version')) . '&' . ('sfp_version=' . $storyform_version);

			$options = Storyform_Options::get_instance();
			if( !$options->get_template_for_post( $post_id ) ){
				$options->update_template_for_post( $post_id, 'Puget' );	
			}

		} else {
			$url = $hostname . '/posts/new-wp?' . ($post_type ? 'post_type=' . $post_type : '') . ('wp_version=' . get_bloginfo('version')) . '&' . ('sfp_version=' . $storyform_version);
		}
		
		?>
		<script>
			var storyform_nonce = '<?php echo $ajax_nonce ?>';
		</script>
		<iframe id="storyform-editor" style="width: 100%; height: 100%;" src="<?php echo $url ?>"></iframe>
		<?php
	}


	public function storyform_get_post(){
		check_ajax_referer( 'storyform-post-nonce' );
		$id =  sanitize_text_field( $_POST['id'] );
		$post = get_post( $id )->to_array();

		// If published, grab the latest revision as there may be some unpublished changes
		if( $post['post_status'] === 'publish' ){
			$array = array_values( wp_get_post_revisions( $id ) );
			$revision = $array[0]->to_array();
			$post['post_content'] = $revision['post_content'];
			$post['post_title'] = $revision['post_title'];
			$post['post_excerpt'] =	$revision['post_excerpt'];
			$post['unpublished_changes'] = true;
		}

		$post['post_content'] = apply_filters( 'the_content', $post['post_content'] );

		$post['byline'] = get_userdata( $post['post_author'])->display_name;
		$post['display_date'] = get_the_date( get_option('date_format'), $post );
		
		echo json_encode( $post );
		
		die(); 
	}

	public function storyform_create_post(){
		check_ajax_referer( 'storyform-post-nonce' );
		$title =  sanitize_text_field( $_POST['post_title'] );
		$content =  $_POST['post_content'];
		$template = sanitize_text_field( $_POST['template'] );
		$post_type = sanitize_text_field( $_POST['post_type'] );
		
		$post = array(
		  'post_content'   => $content,
//		  'post_name'      => [ <string> ] // The name (slug) for your post
		  'post_title'     => $title,
		  'post_status'    => 'draft',
		  'post_type'      => $post_type,
		);  
		$ID = wp_insert_post( $post );

		$options = Storyform_Options::get_instance();
		$options->update_template_for_post( $ID, $template );

		echo json_encode( array( 'ID' => $ID ) );
		die();
	}

	public function storyform_update_post(){
		check_ajax_referer( 'storyform-post-nonce' );
		$id = sanitize_text_field( $_POST['id'] );
		$template = isset( $_POST['template'] ) ? sanitize_text_field( $_POST['template'] ) : null;

		// Check if we've already published, if so create revision
		$post = get_post( $id )->to_array();
		if( $post['post_status'] === 'publish' ){

			$post = array(
				'post_type' => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $post['ID'],
				'post_content' => $post['post_content'],
				'post_title' => $post['post_title'],
				'post_excerpt' => $post['post_excerpt'],
				'post_name' => $post['ID'] . '-storyform-revision'
			);

			// Delete more than 5 revisions
			$revisions = array_values( wp_get_post_revisions( $id ) );
			if( count( $revisions ) > 4 ){
				for( $i = 4; $i < count( $revisions ); $i++ ){
					wp_delete_post( $revisions[ $i ]->ID );	
				}

				// Of course grab the latest version of the revisions as the current to update
				$revision = $revisions[0]->to_array();
				$post['post_content'] = $revision['post_content'];
				$post['post_title'] = $revision['post_title'];
				$post['post_excerpt'] =	$revision['post_excerpt'];
			} 

		} else {
			$post = array( 'ID'	=> $id );
		}

		if( isset( $_POST['post_title'] ) ){
			$post['post_title'] = sanitize_text_field( $_POST['post_title'] );
		}
		if( isset( $_POST['post_content'] )){
			$post['post_content'] = $_POST['post_content'];
		}
		if( isset( $_POST['post_type'] )){
			$post['post_type'] = sanitize_text_field( $_POST['post_type'] );
		} 

		if( isset( $post['ID'] ) ){
			add_filter( 'wp_revisions_to_keep', array( $this, 'revisions_to_keep' ), 10, 2 );
			wp_update_post( $post );

			if( $template ){
				$options = Storyform_Options::get_instance();
				$options->update_template_for_post( $id, $template );
			}
		} else {
			$id = wp_insert_post( $post );
			$post['ID'] = $id;
		}

		echo json_encode( array( $post ) );
		die();
	}

	public function revisions_to_keep( $num, $post ){
		if( Storyform_Options::get_instance()->get_template_for_post( $post->ID ) ){
			$num = 5;
		}
		return $num;
	}

	public function storyform_publish_post(){
		check_ajax_referer( 'storyform-post-nonce' );
		$id = sanitize_text_field( $_POST['id'] );
		$name = sanitize_title( $_POST['name'] );

		// Update post with revision if already published, keep name
		$post = get_post( $id )->to_array();
		$revisions = wp_get_post_revisions( $id );

		if( $post['post_status'] === 'publish' && count( $revisions ) > 0 ){
			$array = array_values( $revisions );
			$revision = $array[0]->to_array();
			$post = array(
				'ID' => $id,
				'post_content' => $revision['post_content'],
				'post_title' => $revision['post_title'],
				'post_excerpt' =>	$revision['post_excerpt']
			);

		} else {
			// Update post with name
			$post = array(
				'ID' 			=> $id,
				'post_name'		=> $name
			);
		}

		wp_update_post( $post );
		wp_publish_post( $id );

		echo json_encode( array( 'url' => get_permalink( $id ) ) );
		die();
	}

	public function storyform_delete_post(){
		check_ajax_referer( 'storyform-post-nonce' );
		$id = sanitize_text_field( $_POST['id'] );

		wp_delete_post( $id );
		echo json_encode( array( ) );
		die();
	}

	public function storyform_get_post_types(){
		check_ajax_referer( 'storyform-post-nonce' );
		$post_types = get_post_types(); 
		$types = array();
		$ignore = array( 'attachment', 'revision', 'nav_menu_item' );
		foreach( $post_types as $type ){
			if( !in_array( $type, $ignore ) ){
				array_push( $types, $type );
			}
		}
		echo json_encode( $types );
		die();
	}

	public function storyform_get_media_sizes(){
		check_ajax_referer( 'storyform-post-nonce' );

		$ids = array_map('trim',explode( ',', sanitize_text_field( $_POST['ids'] ) ));
		$id_sizes = array();
		foreach( $ids as $id ){
			$img_url = wp_get_attachment_url( $id );
			$img_url_basename = wp_basename( $img_url );
			
			$sizes = array();

			$full = wp_get_attachment_image_src( $id, 'full' );
			if( !$full[2] ){
				return '';
			}
			$fullAspect = $full[1] / $full[2];
			
			// VIP doesn't store multiple sizes, it generates them on the fly, so we just generate URLs
			if( function_exists( 'wpcom_vip_get_resized_attachment_url' ) ){
				$szs = array( 
					array( 'width' => 320,  'height' => round( 320 / $fullAspect ) ),
					array( 'width' => 667,  'height' => round( 667 / $fullAspect ) ),
					array( 'width' => 1024, 'height' => round( 1024 / $fullAspect ) ),
					array( 'width' => 1366, 'height' => round( 1366 / $fullAspect ) ),
					array( 'width' => 1920, 'height' => round( 1920 / $fullAspect ) )
				);
				foreach( $szs as $size ){
					$url = wpcom_vip_get_resized_attachment_url( $id, $size['width'], $size['height'], false );
					array_push( $sizes, array( 'url' => $url, 'width' => $size['width'], 'height' => $size['height'] ) );
				}
			} else {
				$sizeNames = get_intermediate_image_sizes();
				foreach( $sizeNames as $name) {
					$intermediate = image_get_intermediate_size( $id, $name );
					if( $intermediate ){
						$url = str_replace( $img_url_basename, $intermediate['file'], $img_url );
						$width = $intermediate['width'];
						$height = $intermediate['height'];
						if( !$height ){
							continue;
						}
						$aspect = $width / $height;

						// Only use scaled images not cropped images (pixel rounding can occur, thus the 0.01)
						if( $aspect > $fullAspect + 0.01 || $aspect < $fullAspect - 0.01) {  
							continue;
						}
						
						array_push( $sizes, array( 'url' => $url, 'width' => $width, 'height' => $height ) );
					}
				}
			}
			$id_sizes[$id] = $sizes;
		}
		

		echo json_encode( $id_sizes );
		die();
	}

}