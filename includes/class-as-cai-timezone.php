<?php
/**
 * Centralized Timezone Handler
 * 
 * This class provides a single source of truth for all timezone-related operations.
 * 
 * IMPORTANT DESIGN DECISION:
 * We use UTC throughout the entire plugin for the following reasons:
 * 1. Database datetime columns are timezone-agnostic (MySQL interprets them in server TZ)
 * 2. UTC is universal and avoids DST (Daylight Saving Time) issues
 * 3. PHP's time() function returns UTC timestamp
 * 4. MySQL's UTC_TIMESTAMP() ensures consistent comparisons
 * 
 * @package AS_Camp_Availability_Integration
 * @since 1.3.18
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_CAI_Timezone class - Central timezone management
 */
class AS_CAI_Timezone {

	/**
	 * Get current datetime in UTC
	 * 
	 * @return DateTime DateTime object in UTC timezone
	 */
	public static function now() {
		return new DateTime( 'now', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Get current Unix timestamp (always UTC)
	 * 
	 * @return int Current Unix timestamp
	 */
	public static function timestamp() {
		return time(); // PHP's time() always returns UTC
	}

	/**
	 * Get UTC timezone object
	 * 
	 * @return DateTimeZone UTC timezone
	 */
	public static function get_timezone() {
		return new DateTimeZone( 'UTC' );
	}

	/**
	 * Get MySQL function name for current UTC time
	 * 
	 * Use this in SQL queries to get current time in UTC.
	 * 
	 * @return string 'UTC_TIMESTAMP()'
	 */
	public static function mysql_now() {
		return 'UTC_TIMESTAMP()';
	}

	/**
	 * Format datetime for database storage
	 * 
	 * @param DateTime|int|null $datetime DateTime object, Unix timestamp, or null for now
	 * @return string Formatted datetime string (Y-m-d H:i:s)
	 */
	public static function format_for_db( $datetime = null ) {
		if ( null === $datetime ) {
			$datetime = self::now();
		} elseif ( is_int( $datetime ) ) {
			$datetime = new DateTime( '@' . $datetime, self::get_timezone() );
		}

		return $datetime->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Add minutes to current time
	 * 
	 * @param int $minutes Number of minutes to add
	 * @return DateTime DateTime object in future
	 */
	public static function add_minutes( $minutes ) {
		$datetime = self::now();
		$datetime->modify( "+{$minutes} minutes" );
		return $datetime;
	}

	/**
	 * Calculate seconds remaining until a future timestamp
	 * 
	 * @param int $future_timestamp Unix timestamp in the future
	 * @return int Seconds remaining (0 if timestamp is in the past)
	 */
	public static function seconds_until( $future_timestamp ) {
		$remaining = $future_timestamp - self::timestamp();
		return max( 0, $remaining );
	}

	/**
	 * Get information about timezone strategy (for debugging)
	 * 
	 * @return array Debug information
	 */
	public static function get_debug_info() {
		$now = self::now();
		
		return array(
			'strategy' => 'UTC everywhere',
			'php_timezone' => 'UTC (via DateTimeZone)',
			'mysql_function' => self::mysql_now(),
			'current_utc' => $now->format( 'Y-m-d H:i:s' ),
			'current_timestamp' => self::timestamp(),
			'wordpress_timezone' => wp_timezone_string(),
			'server_timezone' => date_default_timezone_get(),
		);
	}
}
