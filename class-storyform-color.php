<?php
/**
 *
 * This class handles actually switching the theme over to a fake one in order to avoid loading functions.php of 
 * the current one (except for WordPress VIP where this doesn't matter since the theme is already loaded). This class
 * also load the appropriate template and enqueues the scripts. 
 * 
 */
class Storyform_Color {

	public static function CSS_is_light ( $color ){
		$brightness = Storyform_Color::RGB_to_Brightness( Storyform_Color::CSS_to_RGB( $color ) );
		return $brightness > 130;
	}

	public static function RGB_to_Brightness ( $rgb ) {
		list ( $r, $g, $b ) = $rgb;
		return sqrt( ( .241 * $r * $r ) + ( .691 * $g * $g ) + ( .068 * $b * $b ) );
	}

	public static function CSS_to_RGB( $color ) {
		$color = trim( $color );

		if ( $color[0] == '#' ) {
	        $color = substr( $color, 1 );
		    if ( strlen( $color ) == 6 ) {
		    	list( $r, $g, $b ) = array(
		    		$color[0] . $color[1],
	            	$color[2] . $color[3],
		            $color[4] . $color[5]
		        );
		    } else if ( strlen( $color ) == 3 ) {
		        list( $r, $g, $b ) = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
		    } else {
		        return false;
		    }

		    $r = hexdec( $r );
		    $g = hexdec( $g ); 
		    $b = hexdec( $b );

		    return array( $r, $g, $b );

	    } else if ( substr( $color, 0,  4 ) == 'rgba' ) {
	    	$color = substr( trim( $color ), 5, -1 ) ;
	    	list( $r, $g, $b, $a ) = explode( ',' , $color );
	    	return array( intval( $r ), intval( $g ), intval( $b ) );

	    } else if ( substr( $color, 0, 3 ) == 'rgb' ) {
	    	$color = substr( trim( $color ), 4, -1 );
	    	list( $r, $g, $b ) = explode( ',' , $color );
	    	return array( intval( $r ), intval( $g ), intval( $b ) );

	    }
	}

}