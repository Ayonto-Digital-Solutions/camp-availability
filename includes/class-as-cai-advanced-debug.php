<?php
/**
 * Advanced Debug System with granular control.
 *
 * Provides comprehensive debugging capabilities with:
 * - Separate log file (not WordPress debug.log)
 * - Granular area control (Admin, Frontend, Cart, DB, etc.)
 * - Multiple log levels (ERROR, WARNING, INFO, DEBUG)
 * - Performance tracking (execution time, memory usage)
 * - Context information (user, product, stack trace)
 * - Log rotation (automatic size management)
 * - Admin interface for viewing/filtering logs
 *
 * @package AS_Camp_Availability_Integration
 * @since 1.3.28
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Debug System class.
 */
class AS_CAI_Advanced_Debug {

	/**
	 * Instance of this class.
	 *
	 * @var AS_CAI_Advanced_Debug|null
	 */
	private static $instance = null;

	/**
	 * Log file path.
	 *
	 * @var string
	 */
	private $log_file;

	/**
	 * Log directory path.
	 *
	 * @var string
	 */
	private $log_dir;

	/**
	 * Maximum log file size in bytes (10MB).
	 *
	 * @var int
	 */
	private $max_log_size = 10485760;

	/**
	 * Debug areas configuration.
	 *
	 * @var array
	 */
	private $debug_areas = array(
		'admin'       => array(
			'label'       => 'Admin Interface',
			'description' => 'Admin pages, settings, dashboard',
			'default'     => false,
		),
		'frontend'    => array(
			'label'       => 'Frontend',
			'description' => 'Product pages, shop, buttons, timers',
			'default'     => false,
		),
		'cart'        => array(
			'label'       => 'Cart & Checkout',
			'description' => 'Add to cart, cart validation, checkout',
			'default'     => false,
		),
		'database'    => array(
			'label'       => 'Database',
			'description' => 'Queries, reservations, stock calculations',
			'default'     => false,
		),
		'cron'        => array(
			'label'       => 'Cron Jobs',
			'description' => 'Scheduled tasks, cleanup operations',
			'default'     => false,
		),
		'hooks'       => array(
			'label'       => 'Hooks & Filters',
			'description' => 'WordPress and WooCommerce hooks',
			'default'     => false,
		),
		'performance' => array(
			'label'       => 'Performance',
			'description' => 'Execution times, memory usage, bottlenecks',
			'default'     => false,
		),
	);

	/**
	 * Log levels with priorities.
	 *
	 * @var array
	 */
	private $log_levels = array(
		'ERROR'   => 1,
		'WARNING' => 2,
		'INFO'    => 3,
		'DEBUG'   => 4,
	);

	/**
	 * Performance tracking storage.
	 *
	 * @var array
	 */
	private $performance_markers = array();

	/**
	 * Get instance.
	 *
	 * @return AS_CAI_Advanced_Debug
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->setup_log_directory();
		$this->init_hooks();
	}

	/**
	 * Setup log directory and file.
	 */
	private function setup_log_directory() {
		$upload_dir     = wp_upload_dir();
		$this->log_dir  = $upload_dir['basedir'] . '/as-cai-logs';
		$this->log_file = $this->log_dir . '/debug.log';

		// Create directory if it doesn't exist.
		if ( ! file_exists( $this->log_dir ) ) {
			wp_mkdir_p( $this->log_dir );
			// Create .htaccess to protect logs.
			$htaccess = $this->log_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Deny from all\n" );
			}
		}
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Register AJAX handlers.
		add_action( 'wp_ajax_as_cai_get_debug_logs', array( $this, 'ajax_get_debug_logs' ) );
		add_action( 'wp_ajax_as_cai_clear_debug_logs', array( $this, 'ajax_clear_debug_logs' ) );
		add_action( 'wp_ajax_as_cai_download_debug_logs', array( $this, 'ajax_download_debug_logs' ) );
	}

	/**
	 * Check if a debug area is enabled.
	 *
	 * @param string $area Debug area name.
	 * @return bool
	 */
	public function is_area_enabled( $area ) {
		// Check if advanced debug is globally enabled.
		if ( 'yes' !== get_option( 'as_cai_advanced_debug', 'no' ) ) {
			return false;
		}

		// Check if specific area is enabled.
		return 'yes' === get_option( "as_cai_debug_area_{$area}", 'no' );
	}

	/**
	 * Log a message.
	 *
	 * @param string $area    Debug area.
	 * @param string $level   Log level (ERROR, WARNING, INFO, DEBUG).
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function log( $area, $level, $message, $context = array() ) {
		// Check if logging is enabled for this area.
		if ( ! $this->is_area_enabled( $area ) ) {
			return;
		}

		// Check log file size and rotate if needed.
		$this->rotate_log_if_needed();

		// Build log entry.
		$timestamp = current_time( 'Y-m-d H:i:s' );
		$user_id   = get_current_user_id();
		$user_info = $user_id ? "User:{$user_id}" : 'Guest';

		// Format context.
		$context_str = '';
		if ( ! empty( $context ) ) {
			$context_formatted = array();
			foreach ( $context as $key => $value ) {
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = wp_json_encode( $value );
				}
				$context_formatted[] = "{$key}={$value}";
			}
			$context_str = ' | ' . implode( ', ', $context_formatted );
		}

		// Build log line.
		$log_line = sprintf(
			"[%s] [%s] [%s] [%s] %s%s\n",
			$timestamp,
			strtoupper( $level ),
			strtoupper( $area ),
			$user_info,
			$message,
			$context_str
		);

		// Write to file.
		error_log( $log_line, 3, $this->log_file );
	}

	/**
	 * Log with ERROR level.
	 *
	 * @param string $area    Debug area.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function error( $area, $message, $context = array() ) {
		$this->log( $area, 'ERROR', $message, $context );
	}

	/**
	 * Log with WARNING level.
	 *
	 * @param string $area    Debug area.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function warning( $area, $message, $context = array() ) {
		$this->log( $area, 'WARNING', $message, $context );
	}

	/**
	 * Log with INFO level.
	 *
	 * @param string $area    Debug area.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function info( $area, $message, $context = array() ) {
		$this->log( $area, 'INFO', $message, $context );
	}

	/**
	 * Log with DEBUG level.
	 *
	 * @param string $area    Debug area.
	 * @param string $message Log message.
	 * @param array  $context Additional context data.
	 */
	public function debug( $area, $message, $context = array() ) {
		$this->log( $area, 'DEBUG', $message, $context );
	}

	/**
	 * Start performance tracking.
	 *
	 * @param string $marker Unique marker name.
	 */
	public function performance_start( $marker ) {
		if ( ! $this->is_area_enabled( 'performance' ) ) {
			return;
		}

		$this->performance_markers[ $marker ] = array(
			'start_time'   => microtime( true ),
			'start_memory' => memory_get_usage(),
		);
	}

	/**
	 * End performance tracking and log results.
	 *
	 * @param string $marker  Unique marker name.
	 * @param array  $context Additional context data.
	 */
	public function performance_end( $marker, $context = array() ) {
		if ( ! $this->is_area_enabled( 'performance' ) ) {
			return;
		}

		if ( ! isset( $this->performance_markers[ $marker ] ) ) {
			return;
		}

		$start       = $this->performance_markers[ $marker ];
		$end_time    = microtime( true );
		$end_memory  = memory_get_usage();
		$duration    = round( ( $end_time - $start['start_time'] ) * 1000, 2 );
		$memory_used = round( ( $end_memory - $start['start_memory'] ) / 1024, 2 );

		$message = sprintf(
			'Performance: %s | Duration: %sms | Memory: %sKB',
			$marker,
			$duration,
			$memory_used
		);

		$this->log( 'performance', 'INFO', $message, $context );

		// Clean up marker.
		unset( $this->performance_markers[ $marker ] );
	}

	/**
	 * Get all debug areas.
	 *
	 * @return array
	 */
	public function get_debug_areas() {
		return $this->debug_areas;
	}

	/**
	 * Rotate log file if it exceeds max size.
	 */
	private function rotate_log_if_needed() {
		if ( ! file_exists( $this->log_file ) ) {
			return;
		}

		$file_size = filesize( $this->log_file );
		if ( $file_size < $this->max_log_size ) {
			return;
		}

		// Rotate: rename current log to .old and start fresh.
		$old_log = $this->log_dir . '/debug.old.log';
		if ( file_exists( $old_log ) ) {
			unlink( $old_log );
		}
		rename( $this->log_file, $old_log );
	}

	/**
	 * Read log file.
	 *
	 * @param int    $lines  Number of lines to read (from end).
	 * @param string $filter Filter by level or area.
	 * @return array
	 */
	public function read_logs( $lines = 100, $filter = '' ) {
		if ( ! file_exists( $this->log_file ) ) {
			return array();
		}

		// Read file.
		$file_lines = file( $this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( false === $file_lines ) {
			return array();
		}

		// Reverse to get most recent first.
		$file_lines = array_reverse( $file_lines );

		// Apply filter if provided.
		if ( ! empty( $filter ) ) {
			$file_lines = array_filter(
				$file_lines,
				function( $line ) use ( $filter ) {
					return stripos( $line, $filter ) !== false;
				}
			);
		}

		// Limit lines.
		$file_lines = array_slice( $file_lines, 0, $lines );

		return $file_lines;
	}

	/**
	 * Clear log file.
	 */
	public function clear_logs() {
		if ( file_exists( $this->log_file ) ) {
			unlink( $this->log_file );
		}
		if ( file_exists( $this->log_dir . '/debug.old.log' ) ) {
			unlink( $this->log_dir . '/debug.old.log' );
		}
	}

	/**
	 * Get log file size.
	 *
	 * @return string Formatted file size.
	 */
	public function get_log_size() {
		if ( ! file_exists( $this->log_file ) ) {
			return '0 KB';
		}

		$size = filesize( $this->log_file );
		return size_format( $size, 2 );
	}

	/**
	 * Get log file path.
	 *
	 * @return string
	 */
	public function get_log_file() {
		return $this->log_file;
	}

	/**
	 * AJAX handler: Get debug logs.
	 */
	public function ajax_get_debug_logs() {
		check_ajax_referer( 'as_cai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$lines  = isset( $_POST['lines'] ) ? intval( $_POST['lines'] ) : 100;
		$filter = isset( $_POST['filter'] ) ? sanitize_text_field( $_POST['filter'] ) : '';

		$logs = $this->read_logs( $lines, $filter );

		wp_send_json_success(
			array(
				'logs'      => $logs,
				'file_size' => $this->get_log_size(),
				'file_path' => $this->get_log_file(),
			)
		);
	}

	/**
	 * AJAX handler: Clear debug logs.
	 */
	public function ajax_clear_debug_logs() {
		check_ajax_referer( 'as_cai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$this->clear_logs();

		wp_send_json_success( 'Logs cleared successfully' );
	}

	/**
	 * AJAX handler: Download debug logs.
	 */
	public function ajax_download_debug_logs() {
		check_ajax_referer( 'as_cai_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		if ( ! file_exists( $this->log_file ) ) {
			wp_die( 'No log file found' );
		}

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="as-cai-debug-' . current_time( 'Y-m-d-H-i-s' ) . '.log"' );
		header( 'Content-Length: ' . filesize( $this->log_file ) );
		readfile( $this->log_file );
		exit;
	}
}
