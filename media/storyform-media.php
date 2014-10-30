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
	$storyformMeta = get_post_meta( $post->ID, 'storyform_text_overlay_areas', true );

	$form_fields['storyform_text_overlay_areas'] = array(
		'label' =>  esc_attr__( "Text overlay areas" ),
		'input' => 'html',
		'html' => '<div>' .
			'<p class="storyform-overlay-count" data-textContent-multiple="' .  esc_attr__( "{{count}} text overlay area(s)" ) . '" data-textContent="' .  esc_attr__( "No text overlay areas." ) . '"></p>' .
			'<button class="button-primary" id="storyform-add-overlay" data-textContent-multiple="' .  esc_attr__("Edit text over area(s)") . '" data-textContent="' .  esc_attr__( "Add text overlay area" ) . '"></button>' .
			"<input type='hidden' id='storyform-text-overlay-areas' name='attachments[{$post->ID}][storyform_text_overlay_areas]' value='" . wp_kses_post( $storyformMeta ) . "' />" .
			'<script> 
				storyform.attachment = { 
					url: "' . $url . '"
				};
				storyform.initAttachmentFields && storyform.initAttachmentFields();
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
	$storyformMeta = get_post_meta( intval( $_POST['attachment_id'] ), 'storyform_text_overlay_areas', true );
	echo $storyformMeta;

	die(); 
}
add_action( 'wp_ajax_storyform_get_overlay_areas', 'storyform_get_overlay_areas' );


/**
 *  Admin ajax call to save overlay data to attachment
 *
 */
function storyform_save_overlay_areas( ) {
	update_post_meta( intval( $_POST['attachment_id'] ), 'storyform_text_overlay_areas', sanitize_text_field( $_POST['storyform_text_overlay_areas'] ));
	echo $_POST['areas'];
	die(); 
}
add_action( 'wp_ajax_storyform_save_overlay_areas', 'storyform_save_overlay_areas' );


/**
 *  Saves text overlay area data with the media post.
 *
 */
function storyform_attachment_field_credit_save( $post, $attachment ) {
	if( isset( $attachment['storyform_text_overlay_areas'] ) ) {
		update_post_meta( $post['ID'], 'storyform_text_overlay_areas', sanitize_text_field( $attachment['storyform_text_overlay_areas'] ) );
	}

	return $post;
}
add_filter( 'attachment_fields_to_save', 'storyform_attachment_field_credit_save', 10, 2 );

/**
 *
 * Reads a single item from the list of items coming out of the DB and converts it into a named strucure
 *
 */
function storyform_read_single_overlay ( $overlay ) {
	$parts = explode( ' ', $overlay );
	return array( 'shape' => $parts[0], 'x1' => $parts[1], 'y1' => $parts[2], 'x2' => $parts[3], 'y2' => $parts[4], 'classNames' => array_slice( $parts, 5 ) );
}

/**
 *
 *  Adds data(-)sources attributes to media when inserted into editor from Media library which 
 *  enables responsive images to choose the best image to load on the client.
 *
 */
if( ! function_exists( 'storyform_media_send_to_editor' )) :
function storyform_media_send_to_editor( $html, $attachment_id, $attachment ) {
	$post = get_post( $attachment_id );
	if ( substr($post->post_mime_type, 0, 5) == 'image' || substr($post->post_mime_type, 0, 5) == 'video' ) {

		$datasources = storyform_attachment_to_datasources( $attachment_id );
		$datasources = $datasources ? 'data-sources="' . $datasources . '"' : '';

		$textOverlay = get_post_meta( $attachment_id, 'storyform_text_overlay_areas', true );
		$textOverlay = $textOverlay ? 'data-text-overlay="' . esc_attr( $textOverlay ). '"' : '';

		$post_id = intval( $_POST['post_id'] );

		$layout_type = Storyform_Options::get_instance()->get_layout_type_for_post( $post_id );
		$decorational = ( $layout_type === 'freeflow' ) ? 'article' : 'pinned';

		// Add data-source attribute to the <img> tag (whether or not its surrounded by caption shortcode).
		// Already escaped in the function
		return preg_replace( '/(\<img|\[video) /', '$1 ' . $textOverlay . ' data-decorational="' . $decorational . '" ' . $datasources . ' ', $html);
	}

	return $html;
}
endif;
add_filter( 'media_send_to_editor', 'storyform_media_send_to_editor', 30, 3 );


/**
 *	Allow data-text-overlay, data-decorational, data-source attributes that would otherwise get stripped in VIP.
 *
 */
function storyform_media_init() {
    $tags = array( 'img', 'video' );
    $new_attributes = array( 'data-text-overlay' => array(), 'data-sources' => array(), 'data-decorational' => array() );
 	_storyform_add_allowed_attrs( $tags, $new_attributes );
 	
 	$tags = array( 'video' );
    $new_attributes = array( 'nocontrols' => array(), 'noloop' => array(), 'autopause' => array() );
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
 *	Allow data-text-overlay, data-decorational, data-source attributes to remain between visual and text views.
 *
 */
function storyform_tiny_mce_before_init( $init ) { 
	$init['extended_valid_elements'] = isset( $init['extended_valid_elements'] ) ? $init['extended_valid_elements'] . ',img[*],video[*]' : 'img[*],video[*]';
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
 * Generate differen image sizes because we often display at full screen or full bleed sizes
 * 
 */
if ( ! function_exists( 'storyform_media_setup' ) ) :
function storyform_media_setup() {
	if( Storyform_Options::get_instance()->get_add_image_sizes() ){
		add_image_size( 'storyform_xlarge', 1366, 768, false );
		add_image_size( 'storyform_xxlarge', 1920, 1080, false );		
	}
	
}
endif; // storyform_setup
add_action( 'after_setup_theme', 'storyform_media_setup' );

/*
 * Setups up content filters.
 * Running at wp action since that is when we will be sure we have a queried post id to know what template were using.
 * 
 */
function storyform_wp(){
	if( Storyform::template_in_use() ) {
		add_filter( 'img_caption_shortcode', 'storyform_caption_shortcode', 10, 3 );
		add_filter( 'wp_video_shortcode', 'storyform_video_shortcode', 10, 2 );
		add_filter( 'shortcode_atts_video', 'storyform_shortcode_atts_video', 10, 3 );
		add_filter( 'the_content', 'storyform_remove_src_attribute', 5 ); // Run before so there is a src attribute to push to data-sources where other lazyloaders might get to it first
		add_filter( 'wp_get_attachment_image_attributes', 'storyform_get_attachment_image_attributes', 10, 2 );
		add_filter( 'post_thumbnail_size', 'storyform_post_thumbnail_size', 1000 );
	}
}
add_action( 'wp', 'storyform_wp' );

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
	 * We do not add text overlay and responsive image data to the caption shortcode, but the internal
	 * <img> element instead. This is because the editor destroys any unknown attributes on the shortcode.
	 *
	 * Format of data-sources attribute:
	 *   Comma separated list of
	 *     <url> <pixelRatio>x <width>w <height>h
	 * 
	 * Format of data-attachment attribute:
	 *   Comma separated list of
	 *     <shape> <relativeX1> <relativeY1> <relativeX2> <relativeY2> <className1> <className2> ...
	 * 
	 *   Where coordinates are fractionally relative to the the width and height of the image
	 * 
	 * 
	 * Sample Output:
	 * <figure>
	 *      <picture>
	 *          <source data-sources=“<url> <pixelRatio>x <width>w <height>h” usemap=“#urlHash”/>
	 *          <source data-sources=“<url> <pixelRatio>x <width>w <height>h” usemap=“#urlHash2”/>
	 *          <noscript>
	 *              <img src="abc.jpg" />
	 *          </noscript>
	 *      </picture>
	 *      <figcaption>…</figcaption>
	 * </figure>
	 * <map name=“urlHash”>
	 *      <area shape=“rect” coords=“0,0,100,300” class=“dark-theme otherClass” />
	 *      <area shape=“rect” coords=“100,200,300,300” class=“dark-theme otherClass” />
	 * </map>
	 * <map name=“urlHash2”>
	 *      <area shape=“rect” coords=“0,0,100,300” class=“dark-theme otherClass” />
	 *      <area shape=“rect” coords=“100,200,300,300” class=“dark-theme otherClass” />
	 * </map>
	 */

	// Used to elevate data-decorational from the <img> element up to the figure element
	$dataDecorationalPattern = '/\sdata\-decorational(?:=[\'\"]([^\'\"]*)[\'\"])?/i';
	$dataDecorational = '';
	if(preg_match( $dataDecorationalPattern, $content, $decorationalMatch )){
		$dataDecorational = $decorationalMatch[0];
	}

	$mapshtml = '';

	// Check if there is text overlay data to lookup
	$textOverlayAttrPattern = '/data-text-overlay="([^\"]*)"/';
	if( preg_match( $textOverlayAttrPattern, $content, $overlayMatch ) ) {

		$overlayData = array_map( 'storyform_read_single_overlay', array_filter( explode( ",", $overlayMatch[1] ) , 'storyform_not_empty' ) );
		
		// Verify there is actual overlay data
		if( count( $overlayData ) ) {

			if( preg_match( '/data-sources="([^\"]+)"/', $content, $sourcesMatch ) ) {
				// Use a <picture> element so each source can have a usemap attribute to specify the overlay area for that image candidate
				$html = '<picture>';

				$candidates = storyform_parse_data_sources( $sourcesMatch[1] );
				if( $candidates ){
					foreach( $candidates as $candidate ) {
						// Generate <map> elements for candidate img by transform relative coordinates to actual pixel coordinates for the given image size
						$mapname = md5( $candidate['url'] ) . '-overlay';
						$usemap = 'usemap="#' . $mapname . '"';
						$mapshtml .= '<map name="' . $mapname .'">';
						foreach( $overlayData as $item ) {
							$shape = $item['shape'];
							$x1 = round( $item['x1'] * $candidate['width'] );
							$y1 = round( $item['y1'] * $candidate['height'] );
							$x2 = round( $item['x2'] * $candidate['width'] );
							$y2 = round( $item['y2'] * $candidate['height'] );
							$classNames = join( " ", $item['classNames']);

							$mapshtml .= '<area shape="' . $shape . '" coords="' . $x1 . ',' . $y1 . ',' . $x2 . ',' . $y2 . '" class="' . $classNames . '" />';
						}
						$mapshtml .= '</map>';
						
						$html .= '<source ' . 'data-sources="' . $candidate['url'] . ' ' . $candidate['pixelRatio'] . 'x ' . $candidate['width'] . 'w ' . $candidate['height'] . 'h" ' . $usemap . ' />';
					}
					$html .= '<noscript>' . do_shortcode( $content ) . '</noscript></picture>';
				} else {
					$html = do_shortcode( $content );
				}

			} else if( preg_match( '/width=[\\\'\"](\d+)[\\\'\"]/', $content, $widthMatches ) && preg_match( '/height=[\\\'\"](\d+)[\\\'\"]/', $content, $heightMatches ) ) {
				// We can just use the <img> element with a usemap attribute
				$width = intval( $widthMatches[1] );
				$height = intval( $heightMatches[1] );

				$mapname = 'a' . rand( 1, 1000000 ) . '-overlay'; // Just use a random name
				$usemap = 'usemap="#' . esc_attr( $mapname ) . '"';
				$mapshtml .= '<map name="' . esc_attr( $mapname ).'">';
				foreach( $overlayData as $item ) {
					$shape = $item['shape'];
					$x1 = round( $item['x1'] * $width );
					$y1 = round( $item['y1'] * $height );
					$x2 = round( $item['x2'] * $width );
					$y2 = round( $item['y2'] * $height );
					$classNames = join( " ", $item['classNames']);

					$mapshtml .= '<area shape="' . $shape . '" coords="' . $x1 . ',' . $y1 . ',' . $x2 . ',' . $y2 . '" class="' . $classNames . '" />';
				}
				$mapshtml .= '</map>';
				$html = preg_replace( '/<img /', '<img ' . $usemap . ' ', do_shortcode( $content ) );

			} else {

				// No width or height, no ability to specify text overlay because its relative
				$html = do_shortcode( $content );
			}

			$html = preg_replace( $textOverlayAttrPattern, '', $html );

		} else {
			// No actual overlay data, just keep the <img>
			$html = do_shortcode( $content );
		}
	} else {

		// If there is no text overlay data, we can just keep the data-sources on the <img>
		$html = do_shortcode( $content );
	}
	
	if ( $id ) {
		$idtag = 'id="' . esc_attr( $id ) . '" ';
	}
	return '<figure ' . $idtag . 'aria-describedby="figcaption_' . $id . '" ' . $dataDecorational . ' >' . $html . '<figcaption>' . $caption . '</figcaption></figure>' . wp_kses_post( $mapshtml );
		
}
endif; // storyform_caption_shortcode


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
 *	Removes the controls attribute by default on Storyform posts. Only if the user specifies it in the shortcode.
 *  Otherwise it will get deferred to the presentational layer (templates).
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

	if( isset( $atts['data-decorational'] ) ){
		$output = preg_replace( '/\<video\s/', '<video data-decorational="' . $atts['data-decorational'] . '" ', $output);
	}

	return $output;
}
endif;

/**
 *	Adds controls, nocontrols, noloop, autopause attributes on shortcode.
 *
 */
if( ! function_exists( 'storyform_shortcode_atts_video' ) ) :
function storyform_shortcode_atts_video( $out, $pairs, $atts ){
	$supported = array( 'controls', 'nocontrols', 'noloop', 'autopause', 'data-decorational' );
	foreach ( $atts as $name => $value ) {
		if( in_array( $value, $supported ) ) {
			$out[$value] = $value;
		}
	}
	return $out;
}
endif;

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
	// Replace all imgs with src attribute
	return preg_replace_callback( '#<img([^>]+?)src=([\'"]?)([^\'"\s>]+)[\'"]?([^>]*)>#i', '_storyform_remove_src_attribute' , $content );
}
endif; // storyform_remove_src_attribute


if( ! function_exists( '_storyform_remove_src_attribute' )) :
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

?>