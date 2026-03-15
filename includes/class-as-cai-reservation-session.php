<?php
/**
 * Cart Reservation Session Handler
 *
 * @package AS_Camp_Availability_Integration
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_CAI_Reservation_Session class - Manages customer sessions for reservations.
 */
class AS_CAI_Reservation_Session {

	/**
	 * Get customer ID from session.
	 *
	 * @return string|false
	 */
	public static function get_customer_id() {
		if ( ! did_action( 'woocommerce_init' ) ) {
			return false;
		}

		// Try getting from WooCommerce session.
		if ( WC()->session ) {
			$customer_id = WC()->session->get_customer_id();
			if ( ! empty( $customer_id ) ) {
				return $customer_id;
			}
		}

		// Fall back to user ID if logged in.
		if ( is_user_logged_in() ) {
			return (string) get_current_user_id();
		}

		// Generate a guest ID based on session.
		if ( WC()->session ) {
			return 'guest_' . WC()->session->get_customer_id();
		}

		return false;
	}

	/**
	 * Check if customer has any active reservations.
	 *
	 * @param string $customer_id Optional customer ID.
	 * @return bool
	 */
	public static function has_active_reservations( $customer_id = null ) {
		if ( null === $customer_id ) {
			$customer_id = self::get_customer_id();
		}

		if ( empty( $customer_id ) ) {
			return false;
		}

		$db       = AS_CAI_Reservation_DB::instance();
		$products = $db->get_reserved_products_by_customer( $customer_id );

		return ! empty( $products );
	}

	/**
	 * Get time remaining for customer's reservation.
	 *
	 * @param string $customer_id Optional customer ID.
	 * @return int Time remaining in seconds, or 0.
	 */
	public static function get_time_remaining( $customer_id = null ) {
		if ( null === $customer_id ) {
			$customer_id = self::get_customer_id();
		}

		if ( empty( $customer_id ) ) {
			return 0;
		}

		$db         = AS_CAI_Reservation_DB::instance();
		$expires_ts = $db->get_customer_expiration_timestamp( $customer_id );

		if ( ! $expires_ts ) {
			return 0;
		}

		// Use centralized timezone handler (v1.3.18)
		return AS_CAI_Timezone::seconds_until( $expires_ts );
	}
}
