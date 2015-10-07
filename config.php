<?php

/**
 * Singleton that handles versioning and end-points for the Storyform API
 *
 */
class Storyform_Api {
    private $version        = '0.6';
    private $textdomain     = 'default';

    protected static $instance = false;

    public static function get_instance() {
        if( !self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function get_static_hostname(){
        if( !Storyform_Options::get_instance()->get_preview_next_version() ){
            return '//betastatic.storyform.co';
        } else {
            return '//static.storyform.co';
        }
    }

    function get_hostname(){
        return '//storyform.co';
    }

    function get_version(){
        return $this->version;
    }

    function get_css( $version ){
        if( !$version ){
            $version = $this->version;
        }
        return $this->get_static_hostname() . '/v' . $version . '/css/read.css';
    }

    function get_js( $version ){
        if( !$version ){
            $version = $this->version;
        }
        return $this->get_static_hostname() . '/v' . $version . '/js/read.js';
    }

    function get_scroll_analytics_js( $version ){
        if( !$version ){
            $version = $this->version;
        }
        return $this->get_static_hostname() . '/v' . $version . '/js/scroll-analytics.js';    
    }

    // Hardcode v0.5 for now until we replace with new v0.6
    function get_navbar_js(){
        return $this->get_static_hostname() . '/v0.5/js/navbar.js';
    }

    function get_navbar_css(){
        return $this->get_static_hostname() . '/v0.5/css/navbar.css';
    }

    function get_textdomain(){
        return $this->textdomain;
    }


}

?>