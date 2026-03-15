<?php
/**
 * Logger Class - Structured logging system
 * 
 * @package AS_Camp_Availability_Integration
 * @since 1.3.14
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AS_CAI_Logger {
	private static $instance = null;
	private $log_file = null;
	private $max_logs = 500; // Maximum number of log entries to keep
	
	// Log levels
	const DEBUG = 'DEBUG';
	const INFO = 'INFO';
	const WARNING = 'WARNING';
	const ERROR = 'ERROR';
	const CRITICAL = 'CRITICAL';
	
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		$upload_dir = wp_upload_dir();
		$log_dir = $upload_dir['basedir'] . '/as-cai-logs';
		
		if ( ! file_exists( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		
		$this->log_file = $log_dir . '/debug.log';
		
		// Create .htaccess to protect log files
		$htaccess_file = $log_dir . '/.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, 'Deny from all' );
		}
	}
	
	/**
	 * Log a debug message
	 */
	public function debug( $message, $context = array() ) {
		$this->log( self::DEBUG, $message, $context );
	}
	
	/**
	 * Log an info message
	 */
	public function info( $message, $context = array() ) {
		$this->log( self::INFO, $message, $context );
	}
	
	/**
	 * Log a warning message
	 */
	public function warning( $message, $context = array() ) {
		$this->log( self::WARNING, $message, $context );
	}
	
	/**
	 * Log an error message
	 */
	public function error( $message, $context = array() ) {
		$this->log( self::ERROR, $message, $context );
	}
	
	/**
	 * Log a critical message
	 */
	public function critical( $message, $context = array() ) {
		$this->log( self::CRITICAL, $message, $context );
	}
	
	/**
	 * Main logging method
	 */
	private function log( $level, $message, $context = array() ) {
		// CRITICAL: Respect Plugin Debug Settings!
		// Only log if Plugin Debug Logging is explicitly enabled
		if ( 'yes' !== get_option( 'as_cai_debug_log', 'no' ) ) {
			return;
		}
		
		// Additionally require WP_DEBUG for safety
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		
		$timestamp = current_time( 'mysql', true ); // UTC time
		$context_str = ! empty( $context ) ? ' | Context: ' . wp_json_encode( $context ) : '';
		
		$log_entry = sprintf(
			"[%s] [%s] [AS-CAI v%s] %s%s\n",
			$timestamp,
			$level,
			AS_CAI_VERSION,
			$message,
			$context_str
		);
		
		// Write to file
		file_put_contents( $this->log_file, $log_entry, FILE_APPEND );
		
		// Also write to WordPress debug.log if available
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( rtrim( $log_entry ) );
		}
		
		// Trim log file if it gets too large
		$this->trim_log_file();
	}
	
	/**
	 * Trim log file to keep only recent entries
	 */
	private function trim_log_file() {
		if ( ! file_exists( $this->log_file ) ) {
			return;
		}
		
		$lines = file( $this->log_file );
		if ( count( $lines ) > $this->max_logs ) {
			$trimmed = array_slice( $lines, -$this->max_logs );
			file_put_contents( $this->log_file, implode( '', $trimmed ) );
		}
	}
	
	/**
	 * Get recent log entries
	 */
	public function get_recent_logs( $count = 50 ) {
		if ( ! file_exists( $this->log_file ) ) {
			return array();
		}
		
		$lines = file( $this->log_file );
		$recent = array_slice( $lines, -$count );
		return array_reverse( $recent );
	}
	
	/**
	 * Clear all logs
	 */
	public function clear_logs() {
		if ( file_exists( $this->log_file ) ) {
			unlink( $this->log_file );
		}
	}
	
	/**
	 * Get log file path
	 */
	public function get_log_file_path() {
		return $this->log_file;
	}
	
	/**
	 * Get log file size
	 */
	public function get_log_file_size() {
		if ( file_exists( $this->log_file ) ) {
			return filesize( $this->log_file );
		}
		return 0;
	}
}
