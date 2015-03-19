<?php

/**
 *  Helper function for filtering to non-empty items
 *
 */
function storyform_not_empty( $item ) {
	return !empty( $item );
}

/**
 *  Inserts scripts neeeded to communicate with the image text overlay selector UI which gets hosted in a thickbox.
 *
 */
function storyform_admin_enqueue_scripts( $hook ) {
	wp_register_script( "storyform-media", plugin_dir_url( __FILE__ ) . 'storyform-media.js', array( 'thickbox', 'jquery-ui-tooltip' ) );
	wp_register_style( "storyform-media", plugin_dir_url( __FILE__ ) . 'storyform-media.css');

	if( 'post.php' != $hook && 'post-new.php' != $hook && 'upload.php' != $hook ) {
		return;
	}
	
	wp_enqueue_style( "thickbox" );
	wp_enqueue_script( "thickbox" );

	//wp_enqueue_style( 'jquery-theme', '//code.jquery.com/ui/1.11.2/themes/smoothness/jquery-ui.css');
	wp_enqueue_script( "storyform-media");
	wp_enqueue_style( "storyform-media");
}
add_action( 'admin_enqueue_scripts', 'storyform_admin_enqueue_scripts' );

/**
 *  Inserts configuration data for Storyform
 *
 */
function storyform_admin_print_scripts(){
	?>
	<script> 
		var storyform = storyform || {};
		storyform.url = "<?php echo esc_js( Storyform_API::get_instance()->get_hostname() ) ?>";
	</script>
	<?php
}
add_action( 'admin_print_scripts',  'storyform_admin_print_scripts' ) ;

/**
 *  Adds a button in the sidebar of the "Add Media" Media library view and Edit media pages. The field allows
 *  the publisher to add or edit what part of a photo can be overlaid with text.
 *
 */
function storyform_attachment_fields_to_edit( $form_fields, $post ){
	wp_enqueue_script( "storyform-media");
	
	$url = esc_js( wp_get_attachment_url( $post->ID ) );
	$metadata = wp_get_attachment_metadata( $post->ID );
	$captionMeta = Storyform_Options::get_instance()->get_caption_area_for_attachment( $post->ID );
	$cropMeta = Storyform_Options::get_instance()->get_crop_area_for_attachment( $post->ID );

	$form_fields['storyform_areas'] = array(
		'label' =>  esc_attr__( "Crop/Caption areas" ),
		'input' => 'html',
		'html' => '<div>' .
			'<button class="button-primary" id="storyform-add-overlay" data-textContent-multiple="' .  esc_attr__("Edit crop/caption area(s)") . '" data-textContent="' .  esc_attr__( "Add caption/crop area" ) . '"></button>' .
			'<script> 
				(function(){
					var id = "' . $post->ID . '";
					var url = "' . $url . '";
					var areas = {
						crop: "' . wp_kses_post( $cropMeta ) . '",
						caption: "' . wp_kses_post( $captionMeta ) . '"
					};
					storyform.initAttachmentFields && storyform.initAttachmentFields(id, url, areas);
				})()
				
			</script>' .
		'</div>'
	);

	return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'storyform_attachment_fields_to_edit', 10, 2 );


/**
 *  Admin ajax call which returns the overlay data for a given attachment
 *
 */
function storyform_get_overlay_areas( ) {
	echo Storyform_Options::get_instance()->get_caption_area_for_attachment( intval( $_POST['attachment_id'] ) );
	die(); 
}
add_action( 'wp_ajax_storyform_get_overlay_areas', 'storyform_get_overlay_areas' );


/**
 *  Admin ajax call to save overlay data to attachment
 *
 */
function storyform_save_overlay_areas( ) {
	Storyform_Options::get_instance()->update_caption_area_for_attachment( intval( $_POST['attachment_id'] ), sanitize_text_field( $_POST['storyform_text_overlay_areas'] ) );
	die(); 
}
add_action( 'wp_ajax_storyform_save_overlay_areas', 'storyform_save_overlay_areas' );

/**
 *  Admin ajax call to save crop zone data to attachment
 *
 */
function storyform_save_crop_areas() {
	Storyform_Options::get_instance()->update_crop_area_for_attachment( intval( $_POST['attachment_id'] ), sanitize_text_field( $_POST['storyform_crop_areas'] ) );
	die(); 
}
add_action( 'wp_ajax_storyform_save_crop_areas', 'storyform_save_crop_areas' );

/**
 *
 * Reads a single text overlay item from the list of items coming out of the DB and converts it into a named strucure
 *
 */
function storyform_read_single_overlay ( $overlay ) {
	$parts = explode( ' ', $overlay );
	return array( 'shape' => $parts[0], 'x1' => $parts[1], 'y1' => $parts[2], 'x2' => $parts[3], 'y2' => $parts[4], 'classNames' => array_slice( $parts, 5 ) );
}

/**
 *
 * Reads a single crop zone item from the list of items coming out of the DB and converts it into a named strucure
 *
 */
function storyform_read_single_crop ( $str ) {
	$parts = explode( ' ', $str );
	return array( 'shape' => $parts[0], 'x1' => $parts[1], 'y1' => $parts[2], 'x2' => $parts[3], 'y2' => $parts[4] );
}

/**
 *
 *  Adds data attributes when inserted into editor from Media library only on Storyform posts.
 *
 */
if( ! function_exists( 'storyform_media_send_to_editor' )) :
function storyform_media_send_to_editor( $html, $attachment_id, $attachment ) {
	$post_id = intval( $_POST['post_id'] );
	if( Storyform_Options::get_instance()->get_template_for_post( $post_id, null ) ){
		return _storyform_add_data_attributes( $html, $attachment_id, $post_id );
	}

	return $html;
}
endif;
add_filter( 'media_send_to_editor', 'storyform_media_send_to_editor', 30, 3 );

/**
 *  Adds data(-)sources, data-text-overlay, data-area-crop, data-decorational attributes to image/video attachment html,
 *  which enables responsive images to choose the best image to load on the client, caption overlays and 
 *	layout control.
 *
 */
if( ! function_exists( '_storyform_add_data_attributes' )) :
function _storyform_add_data_attributes( $html, $attachment_id, $post_id ) {
	$post = get_post( $attachment_id );
	if ( substr($post->post_mime_type, 0, 5) == 'image' || substr($post->post_mime_type, 0, 5) == 'video' ) {

		$datasources = storyform_attachment_to_datasources( $attachment_id );
		$datasources = $datasources ? 'data-sources="' . $datasources . '"' : '';

		$textOverlay = Storyform_Options::get_instance()->get_caption_area_for_attachment( $attachment_id );
		$textOverlay = $textOverlay ? 'data-text-overlay="' . esc_attr( $textOverlay ). '"' : '';

		$cropZone = Storyform_Options::get_instance()->get_crop_area_for_attachment( $attachment_id );
		$cropZone = $cropZone ? 'data-area-crop="' . esc_attr( $cropZone ). '"' : '';

		$layout_type = Storyform_Options::get_instance()->get_layout_type_for_post( $post_id );
		$decorational = ( $layout_type === 'freeflow' ) ? 'article' : 'pinned';

		// Add data-source attribute to the <img> tag (whether or not its surrounded by caption shortcode).
		// Already escaped in the function
		return preg_replace( '/(\<img|\[video) /', '$1 ' . $textOverlay . ' ' . $cropZone . ' data-decorational="' . $decorational . '" ' . $datasources . ' ', $html);
	}

	return $html;
}
endif;

/**
 *	Allow data-text-overlay, data-decorational, data-area-crop, data-source attributes that would otherwise get stripped in VIP.
 *
 */
function storyform_media_init() {
    $tags = array( 'img', 'video' );
    $new_attributes = array( 'data-text-overlay' => array(), 'data-area-crop' => array(), 'data-sources' => array(), 'data-decorational' => array() );
 	_storyform_add_allowed_attrs( $tags, $new_attributes );
 	
 	$tags = array( 'video' );
    $new_attributes = array( 'nocontrols' => array(), 'noloop' => array(), 'autopause' => array(), 'usemap' => array() );
    _storyform_add_allowed_attrs( $tags, $new_attributes );

    $tags = array( 'area' );
    $new_attributes = array( 'data-type' => array() );
    _storyform_add_allowed_attrs( $tags, $new_attributes );

    $tags = array( 'storyform-ad' );
    $new_attributes = array( 'data-slot' => array() );
    _storyform_add_allowed_attrs( $tags, $new_attributes );

}
add_action( 'init', 'storyform_media_init' );

function _storyform_add_allowed_attrs( $tags, $new_attributes ){
	global $allowedposttags;
	foreach ( $tags as $tag ) {
        if ( isset( $allowedposttags[ $tag ] ) && is_array( $allowedposttags[ $tag ] ) ) {
            $allowedposttags[ $tag ] = array_merge( $allowedposttags[ $tag ], $new_attributes );
        }
    }
}

/**
 *	Allow data-text-overlay, data-decorational, data-area-crop, data-source attributes to remain between visual and text views.
 *
 */
function storyform_tiny_mce_before_init( $init ) { 
	$init['extended_valid_elements'] = isset( $init['extended_valid_elements'] ) ? $init['extended_valid_elements'] . ',img[*],video[*],storyform-ad[*]' : 'img[*],video[*],storyform-ad[*]';
	return $init;
}
add_filter('tiny_mce_before_init', 'storyform_tiny_mce_before_init'); 

/*
 *  Returns an escaped data-sources attribute value for an attachment id
 *
 */
if( ! function_exists( 'storyform_attachment_to_datasources' )) :
function storyform_attachment_to_datasources( $id ) {
	$img_url = wp_get_attachment_url( $id );
	$img_url_basename = wp_basename( $img_url );

	$full = wp_get_attachment_image_src( $id, 'full' );
	if( !$full[2] ){
		return '';
	}
	$fullAspect = $full[1] / $full[2];

	$datasources = array($img_url . ' 1x ' . $full[1] . 'w ' . $full[2] . 'h');

	// VIP doesn't store multiple sizes, it generates them on the fly, so we just generate URLs
	if( function_exists( 'wpcom_vip_get_resized_attachment_url' ) ){
		$sizes = array( 
			array( 'width' => 320,  'height' => round( 320 / $fullAspect ) ),
			array( 'width' => 667,  'height' => round( 667 / $fullAspect ) ),
			array( 'width' => 1024, 'height' => round( 1024 / $fullAspect ) ),
			array( 'width' => 1366, 'height' => round( 1366 / $fullAspect ) ),
			array( 'width' => 1920, 'height' => round( 1920 / $fullAspect ) )
		);
		foreach( $sizes as $size ){
			$url = wpcom_vip_get_resized_attachment_url( $id, $size['width'], $size['height'], false );
			array_push( $datasources, $url . ' 1x ' . $size['width'] . 'w ' . $size['height']. 'h' );
		}
		// No need to escape since its already been done from the VIP API
		return join( ', ', $datasources );
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
				
				array_push( $datasources, $url . ' 1x ' . $width . 'w ' . $height . 'h' );	
			}
			
		}
		return esc_attr( join( ', ', $datasources ) );
	}
	
}
endif;

/*
 *  Returns a 1x1 transparent image.
 *
 */
if( ! function_exists( 'storyform_get_image_placeholder' )) :
function storyform_get_image_placeholder(){
	return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
}
endif;

/*
 *  Adds the data-sources attribute value to attachment's like the Featured Image
 *
 */
if( ! function_exists( 'storyform_get_attachment_image_attributes' )) :
function storyform_get_attachment_image_attributes( $attr, $attachment ) { 
	$id = $attachment->ID;
	$attr['data-sources'] = storyform_attachment_to_datasources( $id );
	$attr['src'] = storyform_get_image_placeholder();
	return $attr;
}
endif;

/*
 *  Sets the Featured Image size to full.
 *
 */
if( ! function_exists( 'storyform_post_thumbnail_size' )) :
function storyform_post_thumbnail_size( $size ) { 
	return 'full';
}
endif;


/*
 * Generate different image sizes because we often display at full screen or full bleed sizes
 * 
 */
if ( ! function_exists( 'storyform_media_setup' ) ) :
function storyform_media_setup() {
	if( Storyform_Options::get_instance()->get_add_image_sizes() ){
		add_image_size( 'storyform_xlarge', 1366, 768, false );
		add_image_size( 'storyform_xxlarge', 1920, 1080, false );	
		add_image_size( 'storyform_xxxlarge', 2880, 1800, false );
	}
	
}
endif; // storyform_setup
add_action( 'after_setup_theme', 'storyform_media_setup' );

/*
 * Sets up up content filters that run before post becomes visible.
 * Running at wp action since that is when we will be sure we have a queried post id to know what template were using.
 * 
 */
function storyform_wp(){
	if( Storyform::template_in_use() ) {
		add_filter( 'img_caption_shortcode', 'storyform_caption_shortcode', 10, 3 );
		add_filter( 'wp_video_shortcode', 'storyform_video_shortcode', 10, 2 );
		add_filter( 'embed_oembed_html', 'storyform_embed_oembed_html', 10, 4 );
		add_filter( 'shortcode_atts_video', 'storyform_shortcode_atts_video', 10, 3 );
		add_filter( 'shortcode_atts_embed', 'storyform_shortcode_atts_embed', 10, 3 );
		add_filter( 'the_content', 'storyform_remove_src_attribute', 5 ); // Run before so there is a src attribute to push to data-sources where other lazyloaders might get to it first
		add_filter( 'the_content', 'storyform_replace_crop', 20 ); // Run after shortcodes run to only fixup non shortcodes
		add_filter( 'wp_get_attachment_image_attributes', 'storyform_get_attachment_image_attributes', 10, 2 );
		add_filter( 'post_thumbnail_size', 'storyform_post_thumbnail_size', 1000 );
	}
}
add_action( 'wp', 'storyform_wp' );

/*
 * Adds data-autopause to embeds if specified on shortcode.
 * 
 */
if( ! function_exists( 'storyform_embed_oembed_html' )) :
function storyform_embed_oembed_html( $cache, $url, $attr, $post_ID ) {
	if( in_array( 'autopause', $attr ) ){
		$cache = preg_replace( '/<iframe /i', '<iframe data-autopause ', $cache );
	}

	$cache = preg_replace( '/ src=/i', ' data-src=', $cache );

	return $cache;
}
endif; // storyform_embed_oembed_html

/*
 * Get caption shortcodes to use HTML5 standard <figure> elements. Also, use optional data-sources attribute to 
 * produce a responsive <picture> element to denote various image source candidates and <map> elements to denote text overlay
 * areas.
 * 
 */
if( ! function_exists( 'storyform_caption_shortcode' )) :
function storyform_caption_shortcode( $val, $attr, $content = null ) {
	extract( shortcode_atts( array(
		'id'            => '',
		'align'         => 'alignnone',
		'width'         => '',
		'caption'       => ''
	), $attr ) );

	if ( 1 > (int) $width || empty( $caption ) ) {
		return $content;
	}

	/**
	 *
	 * We do not add map/area and responsive image data to the caption shortcode, but the internal
	 * <img> element instead. This is because the editor destroys any unknown attributes on the shortcode.
	 *
	 * Format of data-sources attribute:
	 *   Comma separated list of
	 *     <url> <pixelRatio>x <width>w <height>h
	 * 
	 * Format of data- map/area attribute:
	 *   Comma separated list of
	 *     <shape> <relativeX1> <relativeY1> <relativeX2> <relativeY2> [<className1> <className2> ...]
	 * 
	 *   Where coordinates are fractionally relative to the the width and height of the image
	 * 
	 * Sample Output:
	 * <figure>
	 *      <picture>
	 *          <source data-sources=“/url.jpg 1x 1024w 768h” usemap=“#urlHash”/>
	 *          <source data-sources=“/url2.jpg 1x 1920w 1080h” usemap=“#urlHash2”/>
	 *          <noscript>
	 *              <img src="abc.jpg" />
	 *          </noscript>
	 *      </picture>
	 *      <figcaption>…</figcaption>
	 * </figure>
	 * <map name=“urlHash”>
	 *      <area shape=“rect” coords=“0,0,100,300” class=“dark-theme otherClass” data-type="caption" />
	 *      <area shape=“rect” coords=“100,200,300,300” data-type="crop" />
	 * </map>
	 * <map name=“urlHash2”>
	 *      <area shape=“rect” coords=“0,0,100,300” class=“dark-theme otherClass” data-type="caption" />
	 *      <area shape=“rect” coords=“100,200,300,300” data-type="crop" />
	 * </map>
	 */

	// Used to elevate data-decorational from the <img> element up to the figure element
	$dataDecorationalPattern = '/\sdata\-decorational(?:=[\'\"]([^\'\"]*)[\'\"])?/i';
	$dataDecorational = '';
	if(preg_match( $dataDecorationalPattern, $content, $decorationalMatch )){
		$dataDecorational = $decorationalMatch[0];
	}

	$imageAndMap = storyform_get_image_and_map( $content );

	$html = !$imageAndMap ? do_shortcode( $content ) : $imageAndMap['image'];
	
	if ( $id ) {
		$idtag = 'id="' . esc_attr( $id ) . '" ';
	}
	$figure = '<figure ' . $idtag . 'aria-describedby="figcaption_' . $id . '" ' . $dataDecorational . ' >' . $html . '<figcaption>' . $caption . '</figcaption></figure>';

	if( $imageAndMap ){
		$figure .= wp_kses_post( $imageAndMap['map'] );
	}
	return $figure;
		
}
endif; // storyform_caption_shortcode

/**
 *	Generates a <picture> or <img> element and corresponding <map> element from source <img> 
 *
 *	@param content {String} HTML for the element
 *
 *	@return HTML string for the new image and map
 */
if( ! function_exists( 'storyform_get_image_and_map' ) ) :
function storyform_get_image_and_map( $content ) {
	$mapshtml = '';

	// Check if there is map area data
	$mapData = storyform_get_map_data( $content );
	if( count( $mapData ) ) {

		if( preg_match( '/data-sources="([^\"]+)"/', $content, $sourcesMatch ) ) {
			// Use a <picture> element so each source can have a usemap attribute to specify the overlay area for that image candidate
			$html = '<picture>';

			$candidates = storyform_parse_data_sources( $sourcesMatch[1] );
			if( $candidates ){
				foreach( $candidates as $candidate ) {
					// Generate <map> elements for candidate img by transform relative coordinates to actual pixel coordinates for the given image size
					$mapname = md5( $candidate['url'] ) . '-overlay';
					$usemap = 'usemap="#' . $mapname . '"';
					$mapshtml .= '<map name="' . $mapname .'">' . storyform_area_html( $mapData, $candidate['width'], $candidate['height'] ) . '</map>';
					$html .= '<source ' . 'data-sources="' . $candidate['url'] . ' ' . $candidate['pixelRatio'] . 'x ' . $candidate['width'] . 'w ' . $candidate['height'] . 'h" ' . $usemap . ' />';
				}
				$html .= '<noscript>' . do_shortcode( $content ) . '</noscript></picture>';
			} else {

				// Unable to find any image candidates
				return FALSE;
			}

		} else if( preg_match( '/width=[\\\'\"](\d+)[\\\'\"]/', $content, $widthMatches ) && preg_match( '/height=[\\\'\"](\d+)[\\\'\"]/', $content, $heightMatches ) ) {
			// We can just use the <img> element with a usemap attribute
			$width = intval( $widthMatches[1] );
			$height = intval( $heightMatches[1] );

			$mapname = 'a' . rand( 1, 1000000 ) . '-overlay'; // Just use a random name
			$usemap = 'usemap="#' . esc_attr( $mapname ) . '"';
			$mapshtml .= '<map name="' . esc_attr( $mapname ).'">' . storyform_area_html( $mapData, $width, $height ) . '</map>';	
			$html = preg_replace( '/<img /', '<img ' . $usemap . ' ', do_shortcode( $content ) );

		} else {

			// No width or height, no ability to specify text overlay because its relative
			return FALSE;
		}

		$html = storyform_remove_area_attributes( $html );
		return array( 'image' => $html, 'map' => $mapshtml );
	}
	return FALSE;
}
endif;

/**
 *	Generates structured data from HTML content string with data- attributes.
 *
 *	@param content {String} HTML for the element
 *
 *	@return The area data found from the HTML attributes
 */
if( ! function_exists( 'storyform_get_map_data' )) :
function storyform_get_map_data( $content ) {
	$result = array();

	// Check if there is text overlay data to lookup
	$textOverlayAttrPattern = '/data-text-overlay="([^\"]*)"/';
	$cropZoneAttrPattern = '/data-area-crop="([^\"]*)"/';
	$textOverlayMatch = preg_match( $textOverlayAttrPattern, $content, $overlayMatches );
	$cropZoneMatch = preg_match( $cropZoneAttrPattern, $content, $cropMatches );
	if( $textOverlayMatch && $cropZoneMatch ) {

		if( $textOverlayMatch ) {
			$result['caption'] = array_map( 'storyform_read_single_overlay', array_filter( explode( ",", $overlayMatches[1] ) , 'storyform_not_empty' ) );	
		}
		if( $cropZoneMatch ) {
			$result['crop'] = array_map( 'storyform_read_single_crop', array_filter( explode( ",", $cropMatches[1] ) , 'storyform_not_empty' ) );
		}
	}
	return $result;
}
endif; // storyform_get_map_data

/**
 *	Generates <area> HTML from map data and a media width and height
 *
 *	@param mapData {Array[caption|crop]} Associative array of area data
 *	@param width {int} Width of the media to turn percentage coords to actual
 *	@param height {int} Height of the media to turn percentage coords to actual
 *
 *	@return HTML string
 */
if( ! function_exists( 'storyform_area_html' )) :
function storyform_area_html( $mapData, $width, $height ) {
	$html = '';
	if( isset( $mapData['caption'] ) ) {
		foreach( $mapData['caption'] as $item ) {
			$shape = $item['shape'];
			$x1 = round( $item['x1'] * $width );
			$y1 = round( $item['y1'] * $height );
			$x2 = round( $item['x2'] * $width );
			$y2 = round( $item['y2'] * $height );
			$classNames = join( " ", $item['classNames']);

			$html .= '<area shape="' . $shape . '" coords="' . $x1 . ',' . $y1 . ',' . $x2 . ',' . $y2 . '" class="' . $classNames . '" data-type="caption" />';
		}
	}

	if( isset( $mapData['crop'] ) ) {
		foreach( $mapData['crop'] as $item ) {
			$shape = $item['shape'];
			$x1 = round( $item['x1'] * $width );
			$y1 = round( $item['y1'] * $height );
			$x2 = round( $item['x2'] * $width );
			$y2 = round( $item['y2'] * $height );

			$html .= '<area shape="' . $shape . '" coords="' . $x1 . ',' . $y1 . ',' . $x2 . ',' . $y2 . '" data-type="crop" />';
		}
	}
	return $html;
}
endif; // storyform_area_html

/**
 *	Removes data- attributes related to area elements
 *
 *	@param html {String} html string
 *	@param width {int} Width of the media to turn percentage coords to actual
 *	@param height {int} Height of the media to turn percentage coords to actual
 *
 *	@return HTML string
 */
if( ! function_exists( 'storyform_remove_area_attributes' )) :
function storyform_remove_area_attributes( $html ) {
	$textOverlayAttrPattern = '/data-text-overlay="([^\"]*)"/';
	$cropZoneAttrPattern = '/data-area-crop="([^\"]*)"/';
	$html = preg_replace( $textOverlayAttrPattern, '', $html );
	$html = preg_replace( $cropZoneAttrPattern, '', $html );
	return $html;
}
endif; // storyform_remove_area_attributes

/**
 *	Parses the value of the data-sources attribute.
 *
 *	@param str {String} The data-source attribute value.
 *
 *	@return An array of associative arrays with the following properties for each candidate. FALSE if string is empty or no valid candidates.
 *				url, pixelRatio, width, height
 */
if( ! function_exists( 'storyform_parse_data_sources' )) :
function storyform_parse_data_sources( $str ){
	$candidates = array_filter( array_map( 'trim', explode( ",", $str ) ), 'storyform_not_empty' );
	$arr = array();
	if( count( $candidates ) ) {
		foreach( $candidates as $candidate ) {
			$parsed_candidate = array();

			if( preg_match( '/^([^\s]+)/', $candidate, $urlMatches ) 
				&& preg_match( '/\s+(\d+)x\s+/', $candidate, $pixelMatches )
				&& preg_match( '/\s+(\d+)w\s+/', $candidate, $widthMatches )
				&& preg_match( '/\s+(\d+)h/', $candidate, $heightMatches ) ) {
				
				$parsed_candidate['url'] = $urlMatches[1];
				$parsed_candidate['pixelRatio'] = $pixelMatches[1];
				$parsed_candidate['width'] = $widthMatches[1];
				$parsed_candidate['height'] = $heightMatches[1];

				array_push( $arr, $parsed_candidate );
			}
		}

		if( count( $arr ) ) {
			return $arr;
		}
		return FALSE;
	} else {
		return FALSE;
	}
}
endif; // storyform_parse_data_sources

/**
 *	Removes the [video] shortcode controls attribute by default on Storyform posts. Only if the user 
 *  specifies it in the shortcode. Otherwise it will get deferred to the presentational layer (Storyform layouts).
 *
 */
if( ! function_exists( 'storyform_video_shortcode' ) ) :
function storyform_video_shortcode( $output, $atts ){
	if( !isset( $atts['controls'] ) ){
		$output = str_replace( 'controls="controls"', '', $output );
	}

	if( isset( $atts['nocontrols'] ) ){
		$output = preg_replace( '/\<video\s/', '<video nocontrols="nocontrols" ', $output);
	}

	if( isset( $atts['noloop'] ) ){
		$output = preg_replace( '/\<video\s/', '<video noloop="noloop" ', $output);
	}

	if( isset( $atts['autopause'] ) ){
		$output = preg_replace( '/\<video\s/', '<video autopause="autopause" ', $output);
	}

	if( isset( $atts['usemap'] ) ){
		$output = preg_replace( '/\<video\s/', '<video usemap="' . $atts['usemap'] . '" ', $output);
	}

	if( isset( $atts['data-decorational'] ) ){
		$output = preg_replace( '/\<video\s/', '<video data-decorational="' . $atts['data-decorational'] . '" ', $output);
	}

	return $output;
}
endif;

/**
 *	Adds controls, nocontrols, noloop, autopause attributes on [video] shortcode.
 *
 */
if( ! function_exists( 'storyform_shortcode_atts_video' ) ) :
function storyform_shortcode_atts_video( $out, $pairs, $atts ){
	$supported = array( 'controls', 'nocontrols', 'noloop', 'autopause', 'usemap', 'data-decorational' );
	foreach ( $atts as $name => $value ) {
		if( in_array( $name, $supported ) ) {
			$out[$name] = $value;
		}
	}
	return $out;
}
endif;

if( ! function_exists( 'storyform_shortcode_atts_embed' ) ) :
function storyform_shortcode_atts_embed( $out, $pairs, $atts ){
	$supported = array( 'autopause' );
	foreach ( $atts as $name => $value ) {
		if( in_array( $name, $supported ) ) {
			$out[$name] = $value;
		}
	}
	return $out;
}
endif; // storyform_shortcode_atts_embed


/**
 *	Removes src attribute to do lazy-loading. Only replaces the src attribute with a placeholder if
 *  there is an equivalent src in data-sources attribute. Only replaces on Storyform posts.
 *
 *	@param content {String} The content
 *
 *	@return The HTML content without the src attribute
 */
if( ! function_exists( 'storyform_remove_src_attribute' )) :
function storyform_remove_src_attribute( $content ){
	return preg_replace_callback( '#<img([^>]+?)src=([\'"]?)([^\'"\s>]+)[\'"]?([^>]*)>#i', '_storyform_remove_src_attribute' , $content );
}
endif;

/**
 *	Replaces data-area-crop attribute, important for non caption shortcodes. Only replaces on Storyform posts.
 *
 *	@param content {String} The content
 *
 *	@return The HTML content
 */
if( ! function_exists( 'storyform_replace_crop' )) :
function storyform_replace_crop( $content ){
	return preg_replace_callback( '#<img([^>]+?)data-area-crop=([\'"]?)([^\'"\s>]+)[\'"]?([^>]*)>#i', '_storyform_replace_crop_attribute' , $content );
}
endif; // storyform_replace_crop


if( ! function_exists( '_storyform_replace_crop_attribute' ) ) :
function _storyform_replace_crop_attribute( $matches ) {
	$content = $matches[0];

	$imageAndMap = storyform_get_image_and_map( $content );

	if( !$imageAndMap ){
		return $content;
	}

	return $imageAndMap['image'] . $imageAndMap['map'];
}
endif;

if( ! function_exists( '_storyform_remove_src_attribute' ) ) :
function _storyform_remove_src_attribute( $srcMatches ) {

	$imgHtml = $srcMatches[0];
	$before = $srcMatches[1];
	$quote = $srcMatches[2];
	$src = $srcMatches[3];
	$after = $srcMatches[4];


	if( preg_match( '#<img([^>]+?)width=[\'"]?([^\'">]+)[\'"]?([^>]*)>#i', $imgHtml, $widthMatches ) ){
		$width =  $widthMatches[2];	
	}
	if( preg_match( '#<img([^>]+?)height=[\'"]?([^\'">]+)[\'"]?([^>]*)>#i', $imgHtml, $heightMatches ) ) {
		$height =  $heightMatches[2];	
	}
	
	// Match data-sources attribute
	if ( preg_match( '#<img([^>]+?)data-sources=[\'"]?([^\'">]+)[\'"]?([^>]*)>#i', $imgHtml, $dataSourceMatches ) ) {
		$dataSources = $dataSourceMatches[2];

		$candidates = storyform_parse_data_sources( $dataSources );

		$hasSameDataSource = false;
		foreach( $candidates as $candidate ){
			if( $candidate['url'] === $src) {
				$hasSameDataSource = true;
			}
		}

		// Just replace the src with the placeholder
		if( $hasSameDataSource ) {
			return '<img' . $before . 'src=' . $quote . storyform_get_image_placeholder() . $quote . $after . '>';
		
		// Replace the src with the placeholder and inject a new data-sources candidate
		} else if( isset( $width ) && isset( $height ) ) {
			$result = preg_replace( '#data-sources=[\'"]?([^\'">]+)[\'"]?#', 'data-sources=' . $quote . $dataSources . ',' . $src . ' 1x ' . $width . 'w ' . $height . 'h' . $quote, $imgHtml );
			$result = preg_replace( '#src=[\'"]?([^\'">]+)[\'"]?#', 'src=' . $quote . storyform_get_image_placeholder() . $quote, $result );
			return $result;

		// Can't do anything since we don't know the size
		} else {
			return $imgHtml;
		}
	} else {
		// Create the data-sources for things like external src urls
		if ( isset( $width ) && isset( $height ) ) {
			return '<img data-sources="' . $src . ' 1x ' . $width . 'w ' . $height . 'h"' . $before . 'src=' . $quote . storyform_get_image_placeholder() . $quote . $after . '>';
		
		// Can't do anything since we don't know the size
		} else {
			return $imgHtml;
		}
		
	}

}
endif;


class Storyform_Media {
	private $post_id = null;

	/**
	 *	Adds data-sources, data-decorational, data- map/area.
	 *
	 *	@return The post data
	 */
	public function add_data_attributes( $post_id , $content ){
		$this->post_id = $post_id;
		return preg_replace_callback( '/(?:\<img|\[video)([^>]+?)>/i', array( $this, '_add_data_attributes' ), $content );
	}

	public function _add_data_attributes( $matches ) {
		$html = $matches[0];
		$attributes = $matches[1];
		if( strpos( $attributes, 'data-sources' ) === FALSE ){
			// Get the attachment id
			if( preg_match( '/wp-image-(\d+)/', $attributes, $matches ) ){
				$attachment_id = intval( $matches[1] );
				$html = _storyform_add_data_attributes( $html, $attachment_id, $this->post_id );
			}
		}
		return $html;
	}

	/**
	 *	Removes data-sources, data-decorational, data-text-overlay, data-area-crop
	 *
	 *	@return The post data
	 */
	public function remove_data_attributes( $post_id , $content ){
		$patterns = array(
			'# data-sources=\\\(?:\'|")([^\'">]+)\\\(?:\'|")#',
			'# data-text-overlay=\\\(?:\'|")([^\'">]+)\\\(?:\'|")#',
			'# data-area-crop=\\\(?:\'|")([^\'">]+)\\\(?:\'|")#',
			'# data-decorational=\\\(?:\'|")([^\'">]+)\\\(?:\'|")#'
		);
		return preg_replace($patterns, '', $content );
	}

	
}

?>