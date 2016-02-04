<?php
abstract class TemplatePointer {
    
    public function getURL( $name, $parameters ) {
        if( !method_exists( WIKIPEDIA."TemplatePointer", $name ) ) return false;
        return $this->$name( $parameters );
    }
    
}