<?php

/**
 * Singleton that handles versioning and end-points for the Storyform API
 *
 */
class Storyform_Api {
    private $version        = '0.4';
    private $textdomain     = 'default';

    protected static $instance = false;

    public static function get_instance() {
        if( !self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    function get_static_hostname(){
        if( defined( 'STORYFORM_LOCALHOST' ) && STORYFORM_LOCALHOST ){
            return 'http://localhost/static';
        } else {
            return 'http://static.storyform.co';
        }
    }

    function get_hostname(){

        if( defined( 'STORYFORM_LOCALHOST' ) && STORYFORM_LOCALHOST ){
            return 'http://localhost';
        } else {
            return 'http://storyform.co';
        }
    }

    function get_version(){
        return $this->version;
    }

    function get_css(){
        return $this->get_static_hostname() . '/v' . $this->version . '/css/read.css';
    }

    function get_js(){
        return $this->get_static_hostname() . '/v' . $this->version . ( defined( 'STORYFORM_LOCALHOST' ) && STORYFORM_LOCALHOST ? '/js/wp-localhost.js' : '/js/read.js' );
    }

    function get_static_version_directory(){
        return $this->get_static_hostname() . '/v' . $this->version;
    }

    function get_version_directory(){
        return $this->get_hostname() . '/v' . $this->version . '/';
    }

    function get_textdomain(){
        return $this->textdomain;
    }


}

?>