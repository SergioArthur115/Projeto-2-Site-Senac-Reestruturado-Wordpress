<?php
namespace MetForm\Core\Entries;
defined( 'ABSPATH' ) || exit;

Class Metform_Shortcode{
    use \MetForm\Traits\Singleton;

    private $all_keys;
    private $all_values;
    private $all_values_escaped;
    private $main_data;

    /**
     * Replace [field-name] placeholders with the submitted values.
     *
     * Values are unauthenticated input, so callers rendering the result as HTML
     * must pass true for $escape_html. Only the values are escaped, not the
     * admin-authored template. Plain-text contexts (mail headers, subject,
     * recipients) keep it false; those use mf_sanitize_header_field() instead.
     */
    public function get_process_shortcode($string, $escape_html = false){
        $values = (array) $this->all_values;

        if ( $escape_html ) {
            // Fallback keeps the escaped set in sync if formate_values() never ran.
            $values = is_array( $this->all_values_escaped )
                ? $this->all_values_escaped
                : array_map( function( $value ){ return esc_html( (string) $value ); }, $values );
        }

        $replace = str_replace((array) $this->all_keys, $values, (string) $string);
        return $replace;
    }

    public function set_values($main_data){
        $this->main_data = $main_data;
        $this->formate_keys();
        $this->formate_values();
        return $this;
    }

    public function get_all_keys(){
        return $this->all_keys;
    }

    public function get_all_values(){
        return $this->all_values;
    }

    public function set_all_keys($main_data){
        $this->main_data = $main_data;
        $this->formate_keys();
        return $this;
    }

    public function set_all_values($main_data){
        $this->main_data = $main_data;
        $this->formate_values();
        return $this;
    }

    public function formate_keys(){

        $this->all_keys = array_map(function($v){
            return "[".$v."]";
        }, array_keys($this->main_data) );
    }

    public function formate_values(){

        $this->all_values = array_map(function($value){
            return (is_array($value) ? implode(', ', $value) : $value);
        }, $this->main_data);

        // HTML-escaped counterpart, built from $all_values so both arrays keep
        // the same order that the positional str_replace() relies on.
        $this->all_values_escaped = array_map(function($value){
            return esc_html((string) $value);
        }, $this->all_values);
    }

}