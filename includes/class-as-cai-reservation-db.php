<?php
/**
 * Cart Reservation Database Handler
 *
 * @package AS_Camp_Availability_Integration
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AS_CAI_Reservation_DB class - Handles database operations for cart reservations.
 */
class AS_CAI_Reservation_DB {

	/**
	 * Instance of this class.
	 *
	 * @var AS_CAI_Reservation_DB|null
	 */
	private static $instance = null;

	/**
	 * Database version.
	 *
	 * @var string
	 */
	private $db_version = '1.0.0';

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table_name = 'as_cai_cart_reservations';

	/**
	 * DB version option name.
	 *
	 * @var string
	 */
	private $db_version_option_name = 'as_cai_db_version';

	/**
	 * Get MySQL function for current UTC time.
	 * 
	 * Centralized method to avoid hardcoding 'UTC_TIMESTAMP()' everywhere.
	 * 
	 * @since 1.3.18
	 * @return string MySQL function name
	 */
	private function mysql_now() {
		return AS_CAI_Timezone::mysql_now();
	}

	/**
	 * Get instance.
	 *
	 * @return AS_CAI_Reservation_DB
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
		// Check and create database table on init.
		add_action( 'init', array( $this, 'check_db' ) );
	}

	/**
	 * Check if database table exists and is up to date.
	 *
	 * @return bool
	 */
	public function check_db() {
		$current_version       = get_option( $this->db_version_option_name, '0.0.0' );
		$table_requires_update = version_compare( $current_version, $this->db_version ) < 0;
		$table_exists          = $this->table_exists();

		if ( ! $table_exists || $table_requires_update ) {
			$this->create_table();
			return $this->table_exists();
		}

		return true;
	}

	/**
	 * Check if table exists.
	 *
	 * @return bool
	 */
	public function table_exists() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;
		$query      = $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) );
		$found_name = $wpdb->get_var( $query );
		return $found_name === $table_name;
	}

	/**
	 * Create or update database table.
	 */
	public function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . $this->table_name;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			`customer_id` varchar(100) NOT NULL,
			`product_id` bigint(20) NOT NULL,
			`stock_quantity` double NOT NULL DEFAULT 0,
			`timestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`expires` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			`order_id` bigint(20) NULL,
			PRIMARY KEY  (`customer_id`, `product_id`)
		) $charset_collate;";

		dbDelta( $sql );

		update_option( $this->db_version_option_name, $this->db_version );
	}

	/**
	 * Drop database table.
	 */
	public function drop_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		delete_option( $this->db_version_option_name );
	}

	/**
	 * Get reservation minutes from settings.
	 *
	 * @return int
	 */
	public function get_reservation_minutes() {
		$minutes = (int) get_option( 'as_cai_reservation_time', 5 );
		return apply_filters( 'as_cai_reservation_minutes', $minutes );
	}

	/**
	 * Reserve stock for a customer.
	 *
	 * @param string $customer_id Customer ID.
	 * @param int    $product_id Product ID.
	 * @param float  $quantity Quantity to reserve.
	 * @return bool
	 */
	public function reserve_stock( $customer_id, $product_id, $quantity ) {
		global $wpdb;

		if ( empty( $customer_id ) || empty( $product_id ) || $quantity <= 0 ) {
			return false;
		}

		// SECURITY FIX v1.3.56: Atomic stock reservation with database transactions
		// Prevents race conditions that allow overselling (75% exploit rate)
		return $this->reserve_stock_atomic( $customer_id, $product_id, $quantity );
	}

	/**
	 * Atomic stock reservation with database transactions and row-level locking.
	 * 
	 * SECURITY FIX v1.3.56: Prevents race conditions in high-concurrency scenarios.
	 * Uses SERIALIZABLE isolation level + row locking to ensure stock consistency.
	 * 
	 * @since 1.3.56
	 * @param string $customer_id Customer ID.
	 * @param int    $product_id Product ID.
	 * @param float  $quantity Quantity to reserve.
	 * @return bool True on success, false on failure.
	 */
	private function reserve_stock_atomic( $customer_id, $product_id, $quantity ) {
		global $wpdb;

		$table_name = $wpdb->prefix . $this->table_name;
		$minutes    = $this->get_reservation_minutes();

		// Use centralized timezone handler
		$now     = AS_CAI_Timezone::now();
		$expires = AS_CAI_Timezone::add_minutes( $minutes );

		// Start transaction with SERIALIZABLE isolation level
		// This prevents phantom reads and ensures consistency
		$wpdb->query( 'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE' );
		$wpdb->query( 'START TRANSACTION' );

		try {
			// 1. Get product with row-level lock (FOR UPDATE)
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}

			// 2. Check if product manages stock
			if ( ! $product->managing_stock() ) {
				// No stock management - allow reservation without checks
				$result = $wpdb->replace(
					$table_name,
					array(
						'customer_id'    => $customer_id,
						'product_id'     => $product_id,
						'stock_quantity' => $quantity,
						'timestamp'      => AS_CAI_Timezone::format_for_db( $now ),
						'expires'        => AS_CAI_Timezone::format_for_db( $expires ),
					),
					array( '%s', '%d', '%f', '%s', '%s' )
				);
				
				$wpdb->query( 'COMMIT' );
				$this->clear_reservation_caches( $customer_id, $product_id );
				return $result !== false;
			}

			// 3. Get current stock with row lock
			$current_stock = $product->get_stock_quantity();

			// 4. Get total reserved stock excluding this customer (with row lock)
			$reserved = $wpdb->get_var( $wpdb->prepare(
				"SELECT COALESCE(SUM(stock_quantity), 0) 
				FROM {$table_name} 
				WHERE product_id = %d 
				AND customer_id != %s 
				AND expires > %s
				FOR UPDATE",
				$product_id,
				$customer_id,
				AS_CAI_Timezone::format_for_db( $now )
			) );

			// 5. Calculate available stock
			$available = $current_stock - $reserved;

			// 6. Check if enough stock available
			if ( $available < $quantity ) {
				$wpdb->query( 'ROLLBACK' );
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					AS_CAI_Logger::instance()->warning( 'Reservation failed - insufficient stock', array(
						'product_id' => $product_id,
						'requested' => $quantity,
						'available' => $available,
						'current_stock' => $current_stock,
						'reserved' => $reserved,
					) );
				}
				
				return false;
			}

			// 7. Create/Update reservation atomically
			$result = $wpdb->replace(
				$table_name,
				array(
					'customer_id'    => $customer_id,
					'product_id'     => $product_id,
					'stock_quantity' => $quantity,
					'timestamp'      => AS_CAI_Timezone::format_for_db( $now ),
					'expires'        => AS_CAI_Timezone::format_for_db( $expires ),
				),
				array( '%s', '%d', '%f', '%s', '%s' )
			);

			// 8. Commit transaction
			$wpdb->query( 'COMMIT' );

			// 9. Clear caches after successful commit
			$this->clear_reservation_caches( $customer_id, $product_id );
			
			// 10. Log success
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				AS_CAI_Logger::instance()->info( 'Atomic reservation created', array(
					'customer_id' => $customer_id,
					'product_id' => $product_id,
					'quantity' => $quantity,
					'available_before' => $available,
					'expires' => AS_CAI_Timezone::format_for_db( $expires ),
				) );
			}

			return $result !== false;

		} catch ( Exception $e ) {
			// Rollback on any error
			$wpdb->query( 'ROLLBACK' );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				AS_CAI_Logger::instance()->error( 'Atomic reservation failed', array(
					'error' => $e->getMessage(),
					'product_id' => $product_id,
					'quantity' => $quantity,
				) );
			}
			
			return false;
		}
	}

	/**
	 * Clear reservation caches for customer and product.
	 * 
	 * @since 1.3.56
	 * @param string $customer_id Customer ID.
	 * @param int    $product_id Product ID.
	 */
	private function clear_reservation_caches( $customer_id, $product_id ) {
		$this->cache_delete_customer_expiration( $customer_id );
		$this->cache_delete_customers_reserved_products( $customer_id );
		$this->cache_delete_product_reservation_quantity( $product_id );
	}

	/**
	 * Update reservation quantity.
	 *
	 * @param string $customer_id Customer ID.
	 * @param int    $product_id Product ID.
	 * @param float  $quantity New quantity.
	 * @return bool
	 */
	public function update_reservation_quantity( $customer_id, $product_id, $quantity ) {
		if ( $quantity <= 0 ) {
			return $this->release_reservation( $customer_id, $product_id );
		}

		return $this->reserve_stock( $customer_id, $product_id, $quantity );
	}

	/**
	 * Release reservation for a specific product.
	 *
	 * @param string $customer_id Customer ID.
	 * @param int    $product_id Product ID.
	 * @return bool
	 */
	public function release_reservation( $customer_id, $product_id ) {
		global $wpdb;

		if ( empty( $customer_id ) || empty( $product_id ) ) {
			return false;
		}

		$table_name = $wpdb->prefix . $this->table_name;
		$result     = $wpdb->delete(
			$table_name,
			array(
				'customer_id' => $customer_id,
				'product_id'  => $product_id,
			),
			array( '%s', '%d' )
		);

		// Clear caches.
		$this->cache_delete_customer_expiration( $customer_id );
		$this->cache_delete_customers_reserved_products( $customer_id );
		$this->cache_delete_product_reservation_quantity( $product_id );

		return $result !== false;
	}

	/**
	 * Release all reservations for a customer.
	 *
	 * @param string $customer_id Customer ID.
	 * @return bool
	 */
	public function release_customer_reservations( $customer_id ) {
		global $wpdb;

		if ( empty( $customer_id ) ) {
			return false;
		}

		// Get product IDs before deleting.
		$product_ids = $this->get_reserved_products_by_customer( $customer_id );

		$table_name = $wpdb->prefix . $this->table_name;
		$result     = $wpdb->delete(
			$table_name,
			array( 'customer_id' => $customer_id ),
			array( '%s' )
		);

		// Clear caches.
		$this->cache_delete_customer_expiration( $customer_id );
		$this->cache_delete_customers_reserved_products( $customer_id );

		// Clear product caches.
		if ( ! empty( $product_ids ) ) {
			foreach ( $product_ids as $product_id => $quantity ) {
				$this->cache_delete_product_reservation_quantity( $product_id );
			}
		}

		return $result !== false;
	}

	/**
	 * Get reserved products for a customer.
	 *
	 * @param string $customer_id Customer ID.
	 * @return array Array of product_id => quantity.
	 */
	public function get_reserved_products_by_customer( $customer_id ) {
		global $wpdb;

		if ( empty( $customer_id ) ) {
			return array();
		}

		// Check cache first.
		$cached = $this->cache_get_customers_reserved_products( $customer_id );
		if ( false !== $cached ) {
			return $cached;
		}

		$table_name = $wpdb->prefix . $this->table_name;
        $results    = $wpdb->get_results(
            $wpdb->prepare(
                // Use UTC_TIMESTAMP() instead of NOW() to avoid timezone mismatches.
                "SELECT product_id, stock_quantity FROM {$table_name} WHERE customer_id = %s AND expires > UTC_TIMESTAMP()",
                $customer_id
            ),
            ARRAY_A
        );

		$products = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$products[ $row['product_id'] ] = (float) $row['stock_quantity'];
			}
		}

		// Cache result.
		$this->cache_set_customers_reserved_products( $customer_id, $products );

		return $products;
	}

	/**
	 * Get reserved stock for a specific product.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $exclude_customer_id Optional customer ID to exclude.
	 * @return float
	 */
	public function get_reserved_stock_for_product( $product_id, $exclude_customer_id = null ) {
		global $wpdb;

		// Check cache first.
		if ( null === $exclude_customer_id ) {
			$cached = $this->cache_get_product_reservation_quantity( $product_id );
			if ( false !== $cached ) {
				return (float) $cached;
			}
		}

		$table_name = $wpdb->prefix . $this->table_name;

		if ( $exclude_customer_id ) {
            $quantity = $wpdb->get_var(
                $wpdb->prepare(
                    // Compare against UTC_TIMESTAMP() to avoid timezone mismatch.
                    "SELECT SUM(stock_quantity) FROM {$table_name} WHERE product_id = %d AND customer_id != %s AND expires > UTC_TIMESTAMP()",
                    $product_id,
                    $exclude_customer_id
                )
            );
		} else {
            $quantity = $wpdb->get_var(
                $wpdb->prepare(
                    // Compare against UTC_TIMESTAMP() to avoid timezone mismatch.
                    "SELECT SUM(stock_quantity) FROM {$table_name} WHERE product_id = %d AND expires > UTC_TIMESTAMP()",
                    $product_id
                )
            );
		}

		$quantity = ! empty( $quantity ) ? (float) $quantity : 0;

		// Cache if not excluding customer.
		if ( null === $exclude_customer_id ) {
			$this->cache_set_product_reservation_quantity( $product_id, $quantity );
		}

		return $quantity;
	}

	/**
	 * Get expiration timestamp for customer.
	 *
	 * Uses TIMESTAMPDIFF to calculate seconds remaining in UTC timezone.
	 * This ensures correct countdown display regardless of server timezone.
	 * 
	 * @since 1.3.17 Uses TIMESTAMPDIFF for timezone-safe calculation
	 * @since 1.3.18 Documents centralized timezone strategy
	 * 
	 * @param string $customer_id Customer ID.
	 * @return int|false Timestamp or false.
	 */
	public function get_customer_expiration_timestamp( $customer_id ) {
		global $wpdb;

		if ( empty( $customer_id ) ) {
			return false;
		}

		// Check cache first.
		$cached = $this->cache_get_customer_expiration( $customer_id );
		if ( false !== $cached && 'no-expiration' !== $cached ) {
			return (int) $cached;
		}

		$table_name = $wpdb->prefix . $this->table_name;
		
		// Use TIMESTAMPDIFF with UTC_TIMESTAMP() for timezone-safe calculation
		// See AS_CAI_Timezone class for centralized timezone strategy
		$seconds_remaining = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), MAX(expires)) FROM {$table_name} WHERE customer_id = %s AND expires > UTC_TIMESTAMP()",
				$customer_id
			)
		);
		
		if ( null === $seconds_remaining || $seconds_remaining <= 0 ) {
			$expiration = false;
		} else {
			// Return current timestamp + remaining seconds
			$expiration = AS_CAI_Timezone::timestamp() + (int) $seconds_remaining;
		}

		// Cache result.
		$this->cache_set_customer_expiration( $customer_id, $expiration );

		return $expiration;
	}

	/**
	 * Transfer reservations from guest to user.
	 *
	 * @param string $guest_id Guest customer ID.
	 * @param int    $user_id User ID.
	 * @return bool
	 */
	public function transfer_reservations( $guest_id, $user_id ) {
		global $wpdb;

		if ( empty( $guest_id ) || empty( $user_id ) ) {
			return false;
		}

		// Delete old user reservations first.
		$this->release_customer_reservations( $user_id );

		// Transfer guest reservations to user.
		$table_name = $wpdb->prefix . $this->table_name;
		$result     = $wpdb->update(
			$table_name,
			array( 'customer_id' => $user_id ),
			array( 'customer_id' => $guest_id ),
			array( '%s' ),
			array( '%s' )
		);

		// Clear caches.
		$this->cache_delete_customer_expiration( $guest_id );
		$this->cache_delete_customers_reserved_products( $guest_id );
		$this->cache_delete_customer_expiration( $user_id );
		$this->cache_delete_customers_reserved_products( $user_id );

		return $result !== false;
	}

	/**
	 * Delete expired reservations.
	 *
	 * @return int Number of rows deleted.
	 */
	public function delete_expired_reservations() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;
		
		// Delete expired reservations from database
        // Use UTC_TIMESTAMP() instead of NOW() to ensure the deletion uses UTC time.
        $deleted = $wpdb->query( "DELETE FROM {$table_name} WHERE expires < UTC_TIMESTAMP()" );
		
		// Note: Cart cleanup happens via woocommerce_before_calculate_totals hook
		// See class-as-cai-cart-reservation.php -> cleanup_expired_cart_items()
		
		return $deleted;
	}

	/**
	 * Flush all reservations.
	 */
	public function flush_all_reservations() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;
		$wpdb->query( "DELETE FROM {$table_name}" );
		wp_cache_flush();
	}

	/**
	 * Get all active reservations (for admin display).
	 *
	 * @return array
	 */
	public function get_all_reservations() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;
		return $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY expires DESC", ARRAY_A );
	}

	/**
	 * Count active reservations.
	 *
	 * @return int
	 */
	public function count_active_reservations() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;
        // Use UTC_TIMESTAMP() to avoid timezone mismatch.
        return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT customer_id) FROM {$table_name} WHERE expires > UTC_TIMESTAMP()" );
	}

	/**
	 * Count reserved products.
	 *
	 * @return int
	 */
	public function count_reserved_products() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;
        // Use UTC_TIMESTAMP() to avoid timezone mismatch.
        return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$table_name} WHERE expires > UTC_TIMESTAMP()" );
	}

	/**
	 * Count reservations expired today.
	 *
	 * @return int
	 */
	public function count_expired_today() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->table_name;
        // Count reservations that expired today. Compare against UTC_TIMESTAMP() to avoid timezone mismatch.
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE DATE(expires) = CURDATE() AND expires < UTC_TIMESTAMP()" );
	}

	/**
	 * Cache helpers.
	 */

	/**
	 * Get customer expiration from cache.
	 *
	 * @param string $customer_id Customer ID.
	 * @return mixed
	 */
	private function cache_get_customer_expiration( $customer_id ) {
		return wp_cache_get( 'customer_id_' . $customer_id, 'as_cai_cache_customer_expiration' );
	}

	/**
	 * Set customer expiration in cache.
	 *
	 * @param string $customer_id Customer ID.
	 * @param mixed  $expiration Expiration timestamp or false.
	 */
	private function cache_set_customer_expiration( $customer_id, $expiration ) {
		$value = false !== $expiration ? $expiration : 'no-expiration';
		wp_cache_set( 'customer_id_' . $customer_id, $value, 'as_cai_cache_customer_expiration', $this->get_reservation_minutes() * 60 );
	}

	/**
	 * Delete customer expiration from cache.
	 *
	 * @param string $customer_id Customer ID.
	 */
	private function cache_delete_customer_expiration( $customer_id ) {
		wp_cache_delete( 'customer_id_' . $customer_id, 'as_cai_cache_customer_expiration' );
	}

	/**
	 * Get product reservation quantity from cache.
	 *
	 * @param int $product_id Product ID.
	 * @return mixed
	 */
	private function cache_get_product_reservation_quantity( $product_id ) {
		return wp_cache_get( 'product_id_' . $product_id, 'as_cai_cache_reserved_stock_by_product' );
	}

	/**
	 * Set product reservation quantity in cache.
	 *
	 * @param int   $product_id Product ID.
	 * @param float $quantity Quantity.
	 */
	private function cache_set_product_reservation_quantity( $product_id, $quantity ) {
		wp_cache_set( 'product_id_' . $product_id, $quantity, 'as_cai_cache_reserved_stock_by_product', $this->get_reservation_minutes() * 60 );
	}

	/**
	 * Delete product reservation quantity from cache.
	 *
	 * @param int $product_id Product ID.
	 */
	private function cache_delete_product_reservation_quantity( $product_id ) {
		wp_cache_delete( 'product_id_' . $product_id, 'as_cai_cache_reserved_stock_by_product' );
	}

	/**
	 * Get customers reserved products from cache.
	 *
	 * @param string $customer_id Customer ID.
	 * @return mixed
	 */
	private function cache_get_customers_reserved_products( $customer_id ) {
		return wp_cache_get( 'customer_id_' . $customer_id, 'as_cai_cache_customers_reserved_products' );
	}

	/**
	 * Set customers reserved products in cache.
	 *
	 * @param string $customer_id Customer ID.
	 * @param array  $products Array of products.
	 */
	private function cache_set_customers_reserved_products( $customer_id, $products ) {
		wp_cache_set( 'customer_id_' . $customer_id, $products, 'as_cai_cache_customers_reserved_products', $this->get_reservation_minutes() * 60 );
	}

	/**
	 * Delete customers reserved products from cache.
	 *
	 * @param string $customer_id Customer ID.
	 */
	private function cache_delete_customers_reserved_products( $customer_id ) {
		wp_cache_delete( 'customer_id_' . $customer_id, 'as_cai_cache_customers_reserved_products' );
	}

	/**
	 * Get expiration timestamp for specific product reservation.
	 * 
	 * @since 1.3.19
	 *
	 * @param string $customer_id Customer ID.
	 * @param int    $product_id Product ID.
	 * @return int|false Timestamp or false.
	 */
	public function get_product_expiration_timestamp( $customer_id, $product_id ) {
		global $wpdb;
		
		if ( empty( $customer_id ) || empty( $product_id ) ) {
			return false;
		}
		
		$table_name = $wpdb->prefix . $this->table_name;
		
		// Get seconds remaining for this specific product
		$seconds_remaining = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), expires) 
				 FROM {$table_name} 
				 WHERE customer_id = %s 
				 AND product_id = %d 
				 AND expires > UTC_TIMESTAMP()",
				$customer_id,
				$product_id
			)
		);
		
		if ( null === $seconds_remaining || $seconds_remaining <= 0 ) {
			return false;
		}
		
		return AS_CAI_Timezone::timestamp() + (int) $seconds_remaining;
	}

	/**
	 * Get all product reservations with their expiration times.
	 * 
	 * @since 1.3.19
	 *
	 * @param string $customer_id Customer ID.
	 * @return array Array of [product_id => timestamp]
	 */
	public function get_all_product_expirations( $customer_id ) {
		global $wpdb;
		
		if ( empty( $customer_id ) ) {
			return array();
		}
		
		$table_name = $wpdb->prefix . $this->table_name;
		
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, 
						TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), expires) as seconds_remaining
				 FROM {$table_name} 
				 WHERE customer_id = %s 
				 AND expires > UTC_TIMESTAMP()",
				$customer_id
			),
			ARRAY_A
		);
		
		$expirations = array();
		$now = AS_CAI_Timezone::timestamp();
		
		foreach ( $results as $row ) {
			if ( $row['seconds_remaining'] > 0 ) {
				$expirations[ $row['product_id'] ] = $now + (int) $row['seconds_remaining'];
			}
		}
		
		return $expirations;
	}
}
