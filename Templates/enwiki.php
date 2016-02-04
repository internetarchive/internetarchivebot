<?php
class enwikiTemplatePointer extends TemplatePointer {
    
    protected function allmusic( $parameters ) {
        if( isset( $parameters['pure_url'] ) && $parameters['pure_url'] == "yes" ) {
            $url = "http://www.allmusic.com/";
            if( isset( $parameters['class'] ) ) {
                $url .= $parameters['class'];
            } else {
                $url .= "artist";
            }
            if( !isset( $parameters['id'] ) ) return false;
            $url .= "/".$parameters['id'];
            if( isset( $parameters['tab'] ) ) {
                $url .= "/".$parameters['tab'];
            }
            return $url;
        } else return false;
    }
}