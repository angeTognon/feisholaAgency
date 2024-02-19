<?php

class IS_Debug {
    
	/**
	 * Logs errors to WordPress debug log.
	 *
	 * The following constants ned to be set in wp-config.php
	 * or elsewhere where turning on and off debugging makes sense.
	 *
	 *     // Essential
	 *     define('WP_DEBUG', true);  
	 *     // Enables logging to /wp-content/debug.log
	 *     define('WP_DEBUG_LOG', true);  
	 *     // Force debug messages in WordPress to be turned off (using logs instead)
	 *     define('WP_DEBUG_DISPLAY', false);  
	 *
	 * @since 1.0.0
	 * @param  mixed $message Array, object or text to output to log.
	 */
	public static function log( $message, $echo_file = false ) {		

		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		$exception = new Exception();
		$debug = array_shift( $trace );
		$caller = array_shift( $trace );
		$exception = $exception->getTrace();
		$callee = array_shift( $exception );
		$msg = null;

		if ( is_array( $message ) || is_object( $message ) ) {
			$class = isset( $caller['class'] ) ? $caller['class'] . '[' . $callee['line'] . '] ' : '';
			if ( $echo_file ) {
			    $msg = $class . print_r( $message, true ) . 'In ' . $callee['file'] . ' on line ' . $callee['line'];	
			} else {
			    $msg = $class . print_r( $message, true );	
			}
		} else {
			$class = isset( $caller['class'] ) ? $caller['class'] . '[' . $callee['line'] . ']: ' : '';
			if ( $echo_file ) {
				$msg = $class . $message . ' In ' . $callee['file'] . ' on line ' . $callee['line'];					
			} else {
				$msg = $class . $message;
			}
		}
		if( self::is_debug_mode() ) {
			error_log( $msg . PHP_EOL );
		}
	}
	
	public static function debug_trace( $return = false ) {
		$traces = debug_backtrace();
		$fields = array(
			'file',
			'line',
			'function',
			'class',
		);
		$log = array( "**************************** Trace start ****************************" );
		foreach( $traces as $i => $trace ) {
			$line = array();
			foreach( $fields as $field ) {
				if( ! empty( $trace[ $field ] ) ) {
					$line[] = "$field: {$trace[ $field ]}";
				}
			}
			$log[] = "  [$i]". implode( '; ', $line );
		}
//		$log = array_reverse( $log, true );
		if( $return ) {
			return implode( "\n", $log);	
		}
		else {
			error_log( implode( "\n", $log) );
		}
	}
	
	public static function process_error_backtrace( $errno, $errstr, $errfile, $errline, $errcontext = null ) {
		if( ! ( error_reporting() & $errno ) ) {
			return;
		}
		switch( $errno ) {
			case E_WARNING      :
			case E_USER_WARNING :
			case E_STRICT       :
			case E_NOTICE       :
			case E_USER_NOTICE  :
				$type = 'warning';
				$fatal = false;
				break;
			default             :
				$type = 'fatal error';
				$fatal = true;
				break;
		}
		$message = "[$type]: '$errstr' file: $errfile, line: $errline";
		error_log( $message );
		self::debug_trace();
		
		if( $fatal ) {
			exit(1);
		}
	}

	public static function set_debug_mode() {
		set_error_handler( array( 'IS_Debug', 'process_error_backtrace') );

		$defines = [
			'IS_DEBUG',
			'WP_DEBUG',
			'SCRIPT_DEBUG'
		];
		foreach( $defines as $define ) {
			if( ! defined( $define ) ) {
				define( $define, true );
			}
	
		}
	}

	/**
	 * Get debug mode status.
	 * 
	 * @since 1.0.0
	 * 
	 * @return boolean
	 */
	public static function is_debug_mode() {
		$debug = false;
		if( ( defined( 'WP_DEBUG' ) && true == WP_DEBUG ) || ( defined( 'IS_DEBUG' ) ) ) {
			$debug = true;
		}
		return $debug;
	} 	
}

