<?php
/**
 * Rate Limiter - Prevents DoS attacks
 * 
 * SECURITY FIX v1.3.56: Implements rate limiting for AJAX actions
 * to prevent DoS attacks (45 seconds to total server failure without this).
 *
 * @package AS_Camp_Availability_Integration
 * @since 1.3.56
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_CAI_Rate_Limiter class - Implements rate limiting for security.
 */
class AS_CAI_Rate_Limiter {

	/**
	 * Instance of this class.
	 *
	 * @var AS_CAI_Rate_Limiter|null
	 */
	private static $instance = null;

	/**
	 * Rate limits configuration.
	 * 
	 * Format: 'action' => ['rate' => requests, 'window' => seconds]
	 *
	 * @var array
	 */
	private $limits = array(
		// Admin AJAX actions
		'as_cai_debug_action'       => array( 'rate' => 10, 'window' => 60 ),
		'as_cai_get_stats'          => array( 'rate' => 10, 'window' => 60 ),
		'as_cai_get_debug_info'     => array( 'rate' => 5, 'window' => 60 ),
		'as_cai_clear_cache'        => array( 'rate' => 5, 'window' => 300 ),
		'as_cai_run_test'           => array( 'rate' => 3, 'window' => 300 ),
		
		// WooCommerce actions (stricter limits)
		'woocommerce_add_to_cart'   => array( 'rate' => 20, 'window' => 60 ),
		'woocommerce_remove_cart_item' => array( 'rate' => 20, 'window' => 60 ),
	);

	/**
	 * Get instance.
	 *
	 * @return AS_CAI_Rate_Limiter
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
		// Hook into AJAX actions EARLY (priority 1)
		add_action( 'wp_ajax_as_cai_debug_action', array( $this, 'check_debug_rate_limit' ), 1 );
		add_action( 'wp_ajax_as_cai_get_stats', array( $this, 'check_stats_rate_limit' ), 1 );
		add_action( 'wp_ajax_as_cai_get_debug_info', array( $this, 'check_debug_info_rate_limit' ), 1 );
		add_action( 'wp_ajax_as_cai_clear_cache', array( $this, 'check_cache_rate_limit' ), 1 );
		add_action( 'wp_ajax_as_cai_run_test', array( $this, 'check_test_rate_limit' ), 1 );
	}

	/**
	 * Check rate limit for a specific action.
	 * 
	 * @param string $action Action identifier.
	 * @param string $identifier Custom identifier (defaults to IP address).
	 * @return bool True if within limit, false if exceeded.
	 */
	public function check_rate_limit( $action, $identifier = '' ) {
		// Get action config
		if ( ! isset( $this->limits[ $action ] ) ) {
			// No limit configured - allow
			return true;
		}

		$config = $this->limits[ $action ];

		// Build unique key
		if ( empty( $identifier ) ) {
			$identifier = $this->get_client_identifier();
		}
		
		$key = 'as_cai_rate_' . md5( $action . '_' . $identifier );

		// Get current attempts
		$attempts = get_transient( $key );
		if ( false === $attempts ) {
			$attempts = 0;
		}

		// Check if limit exceeded
		if ( $attempts >= $config['rate'] ) {
			$this->handle_rate_limit_exceeded( $action, $attempts, $config );
			return false;
		}

		// Increment counter
		set_transient( $key, $attempts + 1, $config['window'] );

		return true;
	}

	/**
	 * Handle rate limit exceeded.
	 * 
	 * @param string $action Action that exceeded limit.
	 * @param int    $attempts Number of attempts.
	 * @param array  $config Rate limit configuration.
	 */
	private function handle_rate_limit_exceeded( $action, $attempts, $config ) {
		// Log the incident
		AS_CAI_Logger::instance()->warning( 'Rate limit exceeded', array(
			'action' => $action,
			'attempts' => $attempts,
			'limit' => $config['rate'],
			'window' => $config['window'],
			'ip' => $this->get_client_ip(),
			'user_agent' => $this->get_user_agent(),
		) );

		// Send 429 Too Many Requests
		status_header( 429 );
		
		// For AJAX requests, send JSON response
		if ( wp_doing_ajax() ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						__( 'Zu viele Anfragen. Limit: %d Anfragen pro %d Sekunden.', 'as-camp-availability-integration' ),
						$config['rate'],
						$config['window']
					),
					'retry_after' => $config['window'],
				),
				429
			);
		}

		// For regular requests, die with message
		wp_die(
			sprintf(
				__( 'Zu viele Anfragen. Bitte warten Sie %d Sekunden.', 'as-camp-availability-integration' ),
				$config['window']
			),
			__( 'Rate Limit Exceeded', 'as-camp-availability-integration' ),
			array( 'response' => 429 )
		);
	}

	/**
	 * Get client identifier (IP + User Agent hash).
	 * 
	 * @return string Client identifier.
	 */
	private function get_client_identifier() {
		$ip = $this->get_client_ip();
		$user_agent = $this->get_user_agent();
		
		// Combine IP + User Agent for more accurate tracking
		return md5( $ip . $user_agent );
	}

	/**
	 * Get client IP address (handles proxies).
	 * 
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_FORWARDED_FOR',  // Proxy
			'HTTP_X_REAL_IP',        // Nginx
			'REMOTE_ADDR',           // Standard
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				
				// Handle comma-separated IPs (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip = trim( $ips[0] );
				}
				
				// Validate IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Get User Agent.
	 * 
	 * @return string User agent.
	 */
	private function get_user_agent() {
		if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		}
		return 'unknown';
	}

	/**
	 * Specific rate limit checks for AJAX actions.
	 */
	public function check_debug_rate_limit() {
		if ( ! $this->check_rate_limit( 'as_cai_debug_action' ) ) {
			exit; // Rate limit exceeded, already handled
		}
	}

	public function check_stats_rate_limit() {
		if ( ! $this->check_rate_limit( 'as_cai_get_stats' ) ) {
			exit;
		}
	}

	public function check_debug_info_rate_limit() {
		if ( ! $this->check_rate_limit( 'as_cai_get_debug_info' ) ) {
			exit;
		}
	}

	public function check_cache_rate_limit() {
		if ( ! $this->check_rate_limit( 'as_cai_clear_cache' ) ) {
			exit;
		}
	}

	public function check_test_rate_limit() {
		if ( ! $this->check_rate_limit( 'as_cai_run_test' ) ) {
			exit;
		}
	}

	/**
	 * Get remaining attempts for an action.
	 * 
	 * @param string $action Action identifier.
	 * @return array Array with 'remaining' and 'reset_in' keys.
	 */
	public function get_remaining_attempts( $action ) {
		if ( ! isset( $this->limits[ $action ] ) ) {
			return array(
				'remaining' => 999,
				'reset_in' => 0,
			);
		}

		$config = $this->limits[ $action ];
		$identifier = $this->get_client_identifier();
		$key = 'as_cai_rate_' . md5( $action . '_' . $identifier );

		$attempts = get_transient( $key );
		if ( false === $attempts ) {
			$attempts = 0;
		}

		$remaining = max( 0, $config['rate'] - $attempts );
		
		// Get TTL for transient (when it resets)
		$reset_in = $config['window'];

		return array(
			'remaining' => $remaining,
			'reset_in' => $reset_in,
		);
	}

	/**
	 * Reset rate limit for a specific action/identifier.
	 * 
	 * @param string $action Action identifier.
	 * @param string $identifier Custom identifier (defaults to current client).
	 */
	public function reset_rate_limit( $action, $identifier = '' ) {
		if ( empty( $identifier ) ) {
			$identifier = $this->get_client_identifier();
		}
		
		$key = 'as_cai_rate_' . md5( $action . '_' . $identifier );
		delete_transient( $key );
	}

	/**
	 * Get rate limit configuration.
	 * 
	 * @return array Rate limits.
	 */
	public function get_limits() {
		return $this->limits;
	}

	/**
	 * Update rate limit configuration.
	 * 
	 * @param string $action Action identifier.
	 * @param int    $rate Maximum requests.
	 * @param int    $window Time window in seconds.
	 */
	public function set_limit( $action, $rate, $window ) {
		$this->limits[ $action ] = array(
			'rate' => $rate,
			'window' => $window,
		);
	}
}
