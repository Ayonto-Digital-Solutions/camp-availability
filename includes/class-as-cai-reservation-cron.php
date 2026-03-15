<?php
/**
 * Cron Job for Cleaning Expired Reservations
 *
 * @package AS_Camp_Availability_Integration
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AS_CAI_Reservation_Cron {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'as_cai_cleanup_expired_reservations', array( $this, 'cleanup_expired' ) );
		add_action( 'init', array( $this, 'schedule_cleanup' ) );
	}

	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'as_cai_cleanup_expired_reservations' ) ) {
			wp_schedule_event( time(), 'hourly', 'as_cai_cleanup_expired_reservations' );
		}
	}

	public function cleanup_expired() {
		$db = AS_CAI_Reservation_DB::instance();
		$deleted = $db->delete_expired_reservations();
		
		if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'AS CAI: Cleaned up %d expired reservations', $deleted ) );
		}
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( 'as_cai_cleanup_expired_reservations' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'as_cai_cleanup_expired_reservations' );
		}
	}
}
