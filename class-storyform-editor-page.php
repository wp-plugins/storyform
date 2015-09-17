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
		add_action( 'init', array( $this, 'storyform_publish_post' ) );
		add_action( 'post_row_actions', array( $this, 'add_post_row_action' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ), 500 );
		add_action( 'wp_ajax_storyform_get_post', array( $this, 'storyform_get_post' ) );
		add_action( 'wp_ajax_storyform_create_post', array( $this, 'storyform_create_post' ) );
		add_action( 'wp_ajax_storyform_update_post', array( $this, 'storyform_update_post' ) );
		add_action( 'wp_ajax_storyform_get_publish_url', array( $this, 'storyform_get_publish_url' ) );
		add_action( 'wp_ajax_storyform_delete_post', array( $this, 'storyform_delete_post' ) );
		add_action( 'wp_ajax_storyform_get_post_types', array( $this, 'storyform_get_post_types' ) );	
		add_action( 'wp_ajax_storyform_get_media_sizes', array( $this, 'storyform_get_media_sizes' ) );
		add_action( 'wp_ajax_storyform_redirect_admin_edit', array( $this, 'storyform_redirect_admin_edit' ) );
		
		
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page()
	{
		$hook_suffix = add_menu_page(
			'Storyform',
			'Storyform',
			'publish_posts',
			'storyform-editor',
			array( $this, 'create_post_page' ),
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjEwIDAgMzAwLjMzMzMgMjY5LjMzMzM0IiBoZWlnaHQ9IjI2OS4zMzMiIHdpZHRoPSIzMDAuMzMzIj48ZGVmcz48Y2xpcFBhdGggaWQ9ImEiPjxwYXRoIGQ9Ik0wIDIzMmg3NjBWMEgweiIvPjwvY2xpcFBhdGg+PC9kZWZzPjxwYXRoIGQ9Ik0yMjMuMDA3IDI1OC45MjVIMTAzLjcwM2wtNTkuNjQ4LTEwMy4yNCA1OS42NDgtMTAzLjI0MmgxMTkuMzA0bDU5LjY0OCAxMDMuMjR6TTIzMC4yIDM5Ljk4SDk2LjUxTDI5LjY2NiAxNTUuNjg1bDY2Ljg0NiAxMTUuN0gyMzAuMmw2Ni44NDYtMTE1Ljd6IiBmaWxsPSIjMjQyNDI0Ii8+PGcgY2xpcC1wYXRoPSJ1cmwoI2EpIiB0cmFuc2Zvcm09Im1hdHJpeCgxLjMzMzMzIDAgMCAtMS4zMzMzMyAwIDMwOS4zMzMpIj48cGF0aCBkPSJNMTE0LjkyOCAxMzAuOTU4Yy0uNDY2IDEwLjM2LTUuMDQgMTUuNzctMTEuMzg1IDE1Ljc3LTYuMjUyIDAtOC43NzItNC4zODUtOC43NzItOS4wNSAwLTUuNiAzLjY0LTguNjggMTAuMjY3LTEyLjc4NSA5Ljg5Mi02LjI1MyAxMy40MzgtOS44IDEzLjQzOC0xNy43MyAwLTExLjI5Mi03LjY1Mi0xOS45Ny0yMy40MjMtMjAuMTU4LTYuOTk4LS4wOTMtMTUuODYzIDEuODY3LTIwLjYyMiA3LjQ2NWwxLjk2IDE2LjA1MmgzLjU0NmMxLjEyLTEyLjY5MiA4LjU4NS0xOS40MSAxNS4zOTctMTkuNDEgNi4xNiAwIDkuNTIgMy45MiA5LjUyIDkuNzk4IDAgNS43ODYtNC4yIDkuMTQ1LTguNjggMTIuMTMtOS43OTcgNi4zNDctMTQuNTU3IDEwLjgyNi0xNC41NTcgMTkuMTMyIDAgNy41NiA0LjAxMyAxOC44NSAyMi4zOTcgMTguODUgNS4zMiAwIDEyLjU5OC0xLjc3MyAxNy40NS01LjZsLTIuODkyLTE0LjQ2NHptMTguMjktMy44MjZsMS4yMTIgNC4zODZoNy4zNzJjMS42OCA2LjA2NSAzLjM2IDExLjAxMiA3LjM3MiAxNS40OSAzLjI2NyAzLjY0IDcuOTMzIDYuMzQ3IDE0LjE4NSA2LjM0NyA4LjY3NyAwIDExLjI5LTMuMzYgMTEuMjktNi44MTIgMC0zLjI2Ny0zLjczMy02LjE2LTYuMzQ2LTYuMTYtMi4yNCAwLTMuMTczIDEuMjEzLTMuNjQgMi44LTEuNCA0LjU3Mi0yLjQyNiA2LjA2Ni0zLjU0NiA2LjA2Ni0xLjMwNyAwLTIuMzM0LTEuNjgtMy4wOC00Ljk0OGwtMi44LTEyLjc4NWg5LjQyNWwtMS4xMi00LjM4NkgxNTQuNGwtOC4zMDUtMzYuNThjLTMuNDUzLTE1LjU4NC0xMS40NzgtMjMuNjEtMjIuMzAzLTIzLjYxLTcgMC0xMS43NTggMi44OTMtMTEuNzU4IDYuNjI3IDAgMi44IDMuNTQ2IDYuOTA1IDYuNTMyIDYuOTA1IDIuMTQ2IDAgMy4xNzMtMS40IDMuODI2LTIuOCAyLjYxMy01LjQxMiA0LjI5My02LjM0NSA1LjEzMi02LjM0NSAxLjQgMCAyLjE0NyAyLjUyIDIuNTIgNC4wMTMgMi42MTMgMTAuNTQ1IDguMzA2IDM4LjU0IDEwLjgyNSA1MS43OTJ6IiBmaWxsPSIjMjQyNDI0Ii8+PC9nPjwvc3ZnPg==',
			'6.23'
		);

		add_submenu_page(
			'storyform-editor', 
			'Add new Storyform', 
			'Add new', 
			'publish_posts', 
			'storyform-editor'
		);

		add_submenu_page(
			null, 
			'Publish post', 
			'Publish post', 
			'publish_posts', 
			'storyform-publish-post',
			array( $this, 'storyform_publish_post' )
		);

		add_action( 'load-' . $hook_suffix , array( $this, 'hook_create_page' ), 99999 );
	}

	public function add_post_row_action( $actions, $post ){
		$template = Storyform_Options::get_instance()->get_template_for_post( $post->ID );
		if( $template ){
			array_unshift( $actions, '<a href="' . admin_url( 'admin.php?page=storyform-editor&post=' . $post->ID ) .'">Edit Storyform</a>' );
		}
		return $actions;
	}

	public function hook_create_page(){
		$nonce = isset( $_GET['_wpnonce'] ) ? $_GET[ '_wpnonce' ] : FALSE;
		$remove = isset( $_GET[ 'remove' ] ) ? $_GET[ 'remove' ] : FALSE;
		$post_id = isset( $_GET[ 'post' ] ) ? intval( $_GET[ 'post' ] ) : FALSE;
		if( $remove && $post_id ){
			if ( ! wp_verify_nonce( $nonce, 'storyform-post-nonce' ) ) {
     			die( 'Invalid Nonce' ); 
     		}
			Storyform_Options::get_instance()->delete_template_for_post( $post_id );	
			wp_redirect( get_edit_post_link( $post_id, '&' ) );
			die();
			return;
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
		global $storyform_version;
		wp_enqueue_style( 'storyform-wp-editor', plugin_dir_url( __FILE__ ) . 'editor/editor.css', null, $storyform_version);
		wp_enqueue_script( 'storyform-wp-editor', plugin_dir_url( __FILE__ ) . 'editor/editor.js', null, $storyform_version);
		wp_enqueue_media();
	}

	public function create_post_page(){
		global $storyform_version;

		$ajax_nonce = wp_create_nonce( "storyform-post-nonce" );
		
		$hostname = Storyform_API::get_instance()->get_hostname();

		$post_id = isset( $_GET[ 'post' ] ) ? intval( $_GET['post'] ) : null;
		$post_type = isset( $_GET[ 'post_type' ] ) ? $_GET['post_type'] : null;

		$version_params = ('wp_version=' . get_bloginfo('version')) . '&' . ('sfp_version=' . $storyform_version) . '&' . ('app_key=' . Storyform_Options::get_instance()->get_application_key());

		if( $post_id ){
			$url = $hostname . '/posts/' . $post_id . '/edit-wp?' . $version_params;

			// Setting template if not for existing post
			$options = Storyform_Options::get_instance();
			if( !$options->get_template_for_post( $post_id ) ){
				$options->update_template_for_post( $post_id, 'Puget' );	
			}

		} else {
			$url = $hostname . '/posts/new-wp?' . ( $post_type ? 'post_type=' . ( $post_type . '&' ) : '' ) . $version_params;
		}
		
		?>
		<script>
			var storyform_nonce = '<?php echo $ajax_nonce ?>';
		</script>
		<iframe id="storyform-editor" style="width: 100%; height: 100%;" src="<?php echo $url ?>"></iframe>
		<?php
	}


	public function storyform_get_post(){
		global $wp_query;
		check_ajax_referer( 'storyform-post-nonce' );
		$id =  sanitize_text_field( $_POST['id'] );
		
		// Setup main loop to establish is_single() + is_page() for the_content filters to read
		$wp_query = new WP_Query( array( 'p' => $id, 'post_type' => 'any' ) );
		if( $wp_query->have_posts() ) {
  			while ( $wp_query->have_posts() ) {
  				$wp_query->the_post();
  			}
  		}
  		global $post;
  		$wp_query->is_page = ($post->post_type === 'page'); // Doesn't do this automatically on return
  		$data = $post->to_array();

		// If published, grab the latest revision as there may be some unpublished changes
		if( $data['post_status'] === 'publish' ){
			$array = array_values( wp_get_post_revisions( $id ) );

			if( count( $array ) ){
				$revision = $array[0]->to_array();
				$data['post_content'] = $revision['post_content'];
				$data['post_title'] = $revision['post_title'];
				$data['post_excerpt'] =	$revision['post_excerpt'];
				$data['unpublished_changes'] = true;
			}
		}

		$data['post_content'] = apply_filters( 'the_content', $data['post_content'] );
		$data['template'] = Storyform_Options::get_instance()->get_template_for_post( $id );
		$data['byline'] = get_userdata( $data['post_author'])->display_name;
		$data['display_date'] = get_the_date( get_option('date_format'), $data );
		
		echo json_encode( $data );
		
		die(); 
	}

	public function storyform_create_post(){
		check_ajax_referer( 'storyform-post-nonce' );
		$title 		= sanitize_text_field( $_POST['post_title'] );
		$content 	= $_POST['post_content'];
		$excerpt 	= sanitize_text_field( $_POST['post_excerpt'] );
		$template 	= sanitize_text_field( $_POST['template'] );
		$post_type 	= sanitize_text_field( $_POST['post_type'] );

		add_filter( 'pre_option_use_balanceTags', array( $this, 'avoid_balance_tags' ) );
		kses_remove_filters();
		
		$post = array(
		  'post_content'   => $content,
//		  'post_name'      => [ <string> ] // The name (slug) for your post
		  'post_title'     => $title,
		  'post_status'    => 'draft',
		  'post_excerpt'   => $excerpt,
		  'post_type'      => $post_type,
		);  
		$ID = wp_insert_post( $post );

		// Setting template for new Storyform
		$options = Storyform_Options::get_instance();
		$options->update_template_for_post( $ID, $template );

		echo json_encode( array( 'ID' => $ID ) );
		die();
	}

	public function storyform_update_post(){
		check_ajax_referer( 'storyform-post-nonce' );
		$id = sanitize_text_field( $_POST['id'] );
		$template = isset( $_POST['template'] ) ? sanitize_text_field( $_POST['template'] ) : null;

		// Ensure XHTML balancing doesn't ruin custom elements with "-" in them (<post-publisher>)
		// and ensure even Author's (not just admins) can add <picture> elements and other custom elements
		add_filter( 'pre_option_use_balanceTags', array( $this, 'avoid_balance_tags' ) );
		kses_remove_filters();

		// Check if we've already published, if so create revision
		$post = get_post( $id )->to_array();
		if( $post['post_status'] === 'publish' ){

			$post = array(
				'post_type' 	=> 'revision',
				'post_status' 	=> 'inherit',
				'post_parent' 	=> $post['ID'],
				'post_content' 	=> $post['post_content'],
				'post_excerpt' 	=> $post['post_excerpt'],
				'post_title' 	=> $post['post_title'],
				'post_excerpt' 	=> $post['post_excerpt'],
				'post_name' 	=> $post['ID'] . '-storyform-revision'
			);

			// Delete more than 5 revisions
			$revisions = array_values( wp_get_post_revisions( $id ) );
			if( count( $revisions ) > 4 ){
				for( $i = 4; $i < count( $revisions ); $i++ ){
					wp_delete_post( $revisions[ $i ]->ID );	
				}

				// Of course grab the latest version of the revisions as the current to update
				$revision = $revisions[0]->to_array();
				$post['post_content'] 	= $revision['post_content'];
				$post['post_excerpt'] 	= $revision['post_excerpt'];
				$post['post_title'] 	= $revision['post_title'];
				$post['post_excerpt'] 	= $revision['post_excerpt'];
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
		if( isset( $_POST['post_excerpt'] )){
			$post['post_excerpt'] = $_POST['post_excerpt'];
		}
		if( isset( $_POST['post_type'] )){
			$post['post_type'] = sanitize_text_field( $_POST['post_type'] );
		} 

		if( isset( $post['ID'] ) ){
			add_filter( 'wp_revisions_to_keep', array( $this, 'revisions_to_keep' ), 10, 2 );
			wp_update_post( $post );

		} else {
			$id = wp_insert_post( $post );
			$post['ID'] = $id;
		}

		// Be sure to set template
		if( $template ){
			$options = Storyform_Options::get_instance();
			$options->update_template_for_post( $id, $template );
		}

		// Make sure we make this the most recent version of Storyform
		Storyform_Options::get_instance()->update_storyform_version_for_post( $post['ID'], false );

		echo json_encode( array( $post ) );
		die();
	}

	/**
	 *	Unfortunately WordPress's balance XHTML tags setting doesn't allow for <post-publisher> or other custom elements
	 *
	 */
	public function avoid_balance_tags( $balance ){
		return 0;
	}

	public function revisions_to_keep( $num, $post ){
		if( Storyform_Options::get_instance()->get_template_for_post( $post->ID ) ){
			$num = 5;
		}
		return $num;
	}

	public function storyform_get_publish_url(){
		check_ajax_referer( 'storyform-post-nonce' );
		$nonce = wp_create_nonce( 'storyform-post-nonce' );
		$id = intval( $_POST['id'] );
		$name = sanitize_title( $_POST['name'] );

		$url = admin_url( "admin.php?page=storyform-publish-post&_wpnonce={$nonce}&id={$id}&name={$name}" );

		echo json_encode( array( 'url' => $url ) );
		die();
	}

	public function storyform_publish_post(){
		if( !is_admin() || !isset( $_GET[ 'page' ] ) || $_GET[ 'page' ] !== 'storyform-publish-post' ){
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? $_GET[ '_wpnonce' ] : FALSE;
		$id = intval( $_GET['id'] );
		$name = sanitize_title( $_GET['name'] );

		if ( ! wp_verify_nonce( $nonce, 'storyform-post-nonce' ) ) {
 			die( 'Invalid Nonce' ); 
 		}		

 		add_filter( 'pre_option_use_balanceTags', array( $this, 'avoid_balance_tags' ) );
 		kses_remove_filters();

		// Update post with revision if already published, keep name
		$post = get_post( $id )->to_array();
		$revisions = array_values( wp_get_post_revisions( $id ) );

		if( $post['post_status'] === 'publish' && count( $revisions ) > 0 ){
			$revision = $revisions[0]->to_array();
			$post = array(
				'ID' => $id,
				'post_content' 	=> $revision['post_content'],
				'post_excerpt' 	=> $revision['post_excerpt'],
				'post_title' 	=> $revision['post_title']
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

		wp_redirect( get_permalink( $id ) );
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

	public function storyform_redirect_admin_edit(){
		check_ajax_referer( 'storyform-post-nonce' );
		$id = sanitize_text_field( $_POST['id'] );

		$array = array(
			'url' => get_edit_post_link( $id, '&' )
		);
		

		echo json_encode( $array );
		die();
	}

}