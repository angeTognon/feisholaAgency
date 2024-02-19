<?php

/**
 * Defines plugin options abstract class.
 *
 * @package IS
 * @subpackage IS/includes
 * @since 5.0
 */
class IS_Base_Options
{
    /**
     * WP Options Key to save this class properties.
     *
     * @since 5.0
     */
    public static  $ID = 'is_base_options' ;
    /**
     * Object lock timestamp.
     * 
     * @since 1.0.0
     */
    protected  $lock ;
    /**
     * Dummy field used to test.
     *
     * @since 5.0
     */
    private  $field ;
    protected static  $opt ;
    /**
     * Singleton class.
     *
     * @var static
     * @since 5.0
     */
    protected static  $_instance ;
    /**
     * Which field should not be saved in the DB.
     *
     * @since 5.0
     */
    protected  $ignore_fields = array(
        '_instance',
        'ignore_fields',
        'is_premium_plugin',
        'opt'
    ) ;
    /**
     * Indicates wheter it is a premium plugin.
     *
     * @since 5.0
     */
    protected  $is_premium_plugin = false ;
    /**
     * Gets the instance of this class.
     *
     * @since 5.0
     * @return static
     */
    public static function getInstance()
    {
        return static::load();
    }
    
    /**
     * Instantiates the plugin by setting up the core properties and loading
     * all necessary dependencies and defining the hooks.
     *
     * The constructor uses internal functions to import all the
     * plugin dependencies, and will leverage the Ivory_Search for
     * registering the hooks and the callback functions used throughout the plugin.
     *
     * @since 5.0
     */
    protected function __construct()
    {
    }
    
    /**
     * Load Option object.
     *
     * Option objects are singletons.
     *
     * @since 5.0
     *
     * @param bool $force Force reloading from DB.
     * @return IS_Option The retrieved object of this class or inherited class.
     */
    public static function load( $force = false )
    {
        $model = new static();
        $class = get_class( $model );
        $exists = false;
        
        if ( static::$_instance && get_class( static::$_instance ) == $class ) {
            $model = static::$_instance;
            $exists = true;
        }
        
        
        if ( !$exists || $force ) {
            $settings = get_option( static::$ID );
            $model->set_defaults();
            $fields = get_object_vars( $model );
            foreach ( $fields as $field => $val ) {
                if ( in_array( $field, $model->ignore_fields ) ) {
                    continue;
                }
                if ( is_object( $val ) ) {
                    continue;
                }
                if ( isset( $settings[$field] ) ) {
                    $model->{$field} = $settings[$field];
                }
            }
        }
        
        static::$_instance = $model;
        return apply_filters( 'is_model_option_load', $model, $class );
    }
    
    /**
     * Save content in wp_option table.
     *
     * Update WP cache and instance singleton.
     *
     * @since 5.0
     */
    public function save()
    {
        $settings = array();
        $class = get_class( $this );
        $fields = get_object_vars( $this );
        foreach ( $fields as $field => $val ) {
            if ( in_array( $field, $this->ignore_fields ) ) {
                continue;
            }
            if ( is_object( $val ) ) {
                continue;
            }
            $settings[$field] = $this->{$field};
        }
        update_option( $class::$ID, $settings );
        $class::$_instance = $this;
    }
    
    /**
     * Set property values in this object.
     * 
     * @since 5.0
     * @param array The values to set in the form of property => value.
     */
    public function set_properties( $values = array() )
    {
        if ( !empty($values) && is_array( $values ) ) {
            foreach ( $values as $property => $val ) {
                $this->__set( $property, $val );
            }
        }
    }
    
    /**
     * Set default values in this object.
     * 
     * @since 5.0
     * @param bool $force Force set defaults.
     */
    public function set_defaults( $force = false )
    {
        $defaults = $this->get_defaults();
        $this->set_properties( $defaults );
        do_action( 'is_options_set_defaults', $this );
    }
    
    /**
     * Get default values to set in this object.
     * 
     * Override it in the child classed.
     *
     * @since 5.0
     * @return array The default values in the form of key => values.
     */
    public function get_defaults()
    {
        return array(
            'field' => 'is_default_value',
        );
    }
    
    /**
     * Get default value of a class property.
     * 
     * Override it in the child classed.
     *
     * @since 5.0
     * @param string $property Optional. The property name to filter value.
     * @return mixed The default value.
     */
    public function get_default( $property )
    {
        $default = null;
        $defaults = $this->get_defaults();
        if ( isset( $defaults[$property] ) ) {
            $default = $defaults[$property];
        }
        return $default;
    }
    
    /**
     * Get saved IS option value.
     *
     * @since 5.0
     * @param string $id The option name id.
     * @param string $option The suboption name.
     * @param string $default The default value if no saved value found.
     * @return array|string The option value.
     */
    public function get_is_option( $option, $default = '' )
    {
        if ( empty(static::$opt) ) {
            static::$opt = get_option( 'is_settings', array() );
        }
        $value = $default;
        if ( !empty(static::$opt[$option]) ) {
            $value = static::$opt[$option];
        }
        if ( is_array( $default ) && !empty($default) ) {
            $value = wp_parse_args( $value, $default );
        }
        return $value;
    }
    
    /**
     * Delete object options saved in the DB.
     *
     * @since 5.0
     */
    public static function delete()
    {
        delete_option( static::$ID );
    }
    
    /**
     * Verify if the object is currently being edited.
     *
     * @since 1.0.0
     * @see wp_check_post_lock.
     *
     * @return boolean True if locked.
     */
    public function check_object_lock()
    {
        $locked = false;
        $time = $this->lock;
        $time_window = @ini_get( 'max_execution_time' ) / 10;
        $time_window = max( $time_window, 30 );
        if ( $time && $time > time() - $time_window ) {
            $locked = true;
        }
        return $locked;
    }
    
    /**
     * Mark the object as currently being edited.
     *
     * @since 1.0.0
     * Based in the wp_set_post_lock
     *
     * @return bool|int
     */
    public function set_object_lock( $save = true )
    {
        $this->lock = time();
        if ( $save ) {
            $this->save();
        }
        return $this->lock;
    }
    
    /**
     * Delete object lock.
     * 
     * @since 1.0.0
     */
    public function delete_object_lock( $save = true )
    {
        $this->lock = null;
        if ( $save ) {
            $this->save();
        }
    }
    
    /**
     * Get existing properties values.
     *
     * @since 5.0
     * @param string $property The name of a property.
     * @return mixed Returns mixed value of a property or NULL if a property doesn't exist.
     */
    public function __get( $property )
    {
        $value = null;
        if ( property_exists( $this, $property ) ) {
            switch ( $property ) {
                default:
                    $value = $this->{$property};
                    break;
            }
        }
        return $value;
    }
    
    /**
     * Magic method to set protected properties.
     * Sanitize fields before set.
     *
     * @since 5.0
     * @param string $property The name of a property to associate.
     * @param mixed  $value The value of a property.
     */
    public function __set( $property, $value )
    {
        if ( property_exists( $this, $property ) ) {
            switch ( $property ) {
                default:
                    $this->{$property} = sanitize_text_field( $value );
                    break;
            }
        }
    }

}