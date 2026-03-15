<?php
/**
 * Cart Reservation Actions
 * 
 * @package AS_Camp_Availability_Integration
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AS_CAI_Cart_Reservation {
	private static $instance = null;
	private $db = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->db = AS_CAI_Reservation_DB::instance();
		$this->init_hooks();
	}

	private function init_hooks() {
		if ( get_option( 'as_cai_enable_cart_reservation', 'yes' ) !== 'yes' ) {
			return;
		}

		// Cart actions
		add_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart' ), 200, 6 );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'update_quantity' ), 100, 3 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'cart_item_removed' ), 200, 2 );
		add_action( 'woocommerce_cart_emptied', array( $this, 'cart_emptied' ), 10 );
		
		// Stock validation
		add_filter( 'woocommerce_is_purchasable', array( $this, 'is_purchasable' ), 50, 2 );
		add_filter( 'woocommerce_product_is_in_stock', array( $this, 'is_in_stock' ), 50, 2 );
		
		// Add to cart validation (v1.3.6 - prevent duplicate products)
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		
		// Cart cleanup on session load (v1.3.8 - remove expired reservations)
		add_filter( 'woocommerce_pre_remove_cart_item_from_session', array( $this, 'pre_remove_cart_item_from_session' ), 100, 4 );
		
		// CRITICAL: Cart loaded from session hook (v1.3.12 - ensure cleanup ALWAYS runs!)
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'cleanup_expired_items_after_session_load' ), 10, 1 );
		
		// Cart cleanup AFTER load (v1.3.9 - Backup cleanup for Seat Planner compatibility)
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'force_cleanup_expired_cart_items' ), 999, 1 );
		
		// Login transfer
		add_action( 'wp_login', array( $this, 'transfer_on_login' ), 5, 2 );
	}

	public function add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		// Advanced Debug: Track cart operations (v1.3.28).
		if ( class_exists( 'AS_CAI_Advanced_Debug' ) ) {
			AS_CAI_Advanced_Debug::instance()->performance_start( 'cart_add_to_cart' );
			AS_CAI_Advanced_Debug::instance()->debug( 'cart', 'Product added to cart', array(
				'product_id' => $product_id,
				'quantity'   => $quantity,
				'cart_key'   => $cart_item_key,
			) );
		}

		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		if ( ! $customer_id ) {
			if ( class_exists( 'AS_CAI_Advanced_Debug' ) ) {
				AS_CAI_Advanced_Debug::instance()->warning( 'cart', 'No customer ID found for reservation' );
				AS_CAI_Advanced_Debug::instance()->performance_end( 'cart_add_to_cart' );
			}
			return;
		}

		$this->db->reserve_stock( $customer_id, $product_id, $quantity );

		// Advanced Debug: Track completion.
		if ( class_exists( 'AS_CAI_Advanced_Debug' ) ) {
			AS_CAI_Advanced_Debug::instance()->info( 'cart', 'Stock reserved successfully', array(
				'customer_id' => $customer_id,
				'product_id'  => $product_id,
				'quantity'    => $quantity,
			) );
			AS_CAI_Advanced_Debug::instance()->performance_end( 'cart_add_to_cart', array(
				'product_id' => $product_id,
				'quantity'   => $quantity,
			) );
		}
	}

	public function update_quantity( $cart_item_key, $quantity, $old_quantity ) {
		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		if ( ! $customer_id ) { return; }

		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		if ( ! $cart_item ) { return; }

		$product_id = $cart_item['product_id'];
		$this->db->update_reservation_quantity( $customer_id, $product_id, $quantity );
	}

	public function cart_item_removed( $cart_item_key, $cart ) {
		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		if ( ! $customer_id ) { return; }

		$cart_item = $cart->removed_cart_contents[ $cart_item_key ];
		if ( ! $cart_item ) { return; }

		$product_id = $cart_item['product_id'];
		$this->db->release_reservation( $customer_id, $product_id );
	}

	public function cart_emptied() {
		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		if ( ! $customer_id ) { return; }
		$this->db->release_customer_reservations( $customer_id );
	}

	public function is_purchasable( $purchasable, $product ) {
		// Advanced Debug: Track purchasability checks (v1.3.28).
		if ( class_exists( 'AS_CAI_Advanced_Debug' ) ) {
			AS_CAI_Advanced_Debug::instance()->performance_start( 'cart_is_purchasable' );
			AS_CAI_Advanced_Debug::instance()->debug( 'hooks', 'Cart Reservation: is_purchasable called', array(
				'product_id'     => $product->get_id(),
				'product_type'   => $product->get_type(),
				'incoming_value' => $purchasable ? 'true' : 'false',
			) );
		}

		// v1.3.30: Simplified logic
		// Our new AS_CAI_Product_Availability runs at Priority 5 (before us at Priority 50)
		// So if purchasable is false here, it's already been properly handled
		if ( ! $purchasable ) {
			if ( class_exists( 'AS_CAI_Advanced_Debug' ) ) {
				AS_CAI_Advanced_Debug::instance()->debug( 'hooks', 'Product not purchasable - respecting availability decision', array(
					'product_id' => $product->get_id(),
				) );
				AS_CAI_Advanced_Debug::instance()->performance_end( 'cart_is_purchasable' );
			}
			return $purchasable;
		}

		$product_id = $product->get_id();
		
		// FIX v1.3.19: Check stock management instead of availability counter.
		// This ensures reservation logic works for ALL products with stock management,
		// not just those with Availability Scheduler counter.
		if ( ! $product->managing_stock() ) {
			if ( class_exists( 'AS_CAI_Advanced_Debug' ) ) {
				AS_CAI_Advanced_Debug::instance()->debug( 'cart', 'Product not managing stock, skipping reservation check', array(
					'product_id' => $product_id,
				) );
				AS_CAI_Advanced_Debug::instance()->performance_end( 'cart_is_purchasable' );
			}
			return $purchasable; // No stock management = no reservation needed
		}

		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		if ( ! $customer_id ) {
			if ( class_exists( 'AS_CAI_Advanced_Debug' ) ) {
				AS_CAI_Advanced_Debug::instance()->performance_end( 'cart_is_purchasable' );
			}
			return $purchasable;
		}

		$stock = $product->get_stock_quantity();
		
		if ( null === $stock ) {
			if ( class_exists( 'AS_CAI_Advanced_Debug' ) ) {
				AS_CAI_Advanced_Debug::instance()->performance_end( 'cart_is_purchasable' );
			}
			return $purchasable;
		}

		// Get reserved stock (excluding this customer's reservation)
		$reserved = $this->db->get_reserved_stock_for_product( $product_id, $customer_id );
		
		// Get this customer's reservation
		$customer_reserved = $this->db->get_reserved_products_by_customer( $customer_id );
		$customer_has = isset( $customer_reserved[ $product_id ] ) ? $customer_reserved[ $product_id ] : 0;

		// Available stock = Total - Reserved (without customer's own)
		$available = $stock - $reserved;
		
		// Result
		$result = ( $available > 0 || $customer_has > 0 );

		// Advanced Debug: Log purchasability result (v1.3.28).
		if ( class_exists( 'AS_CAI_Advanced_Debug' ) ) {
			AS_CAI_Advanced_Debug::instance()->debug( 'cart', 'Purchasability check completed', array(
				'product_id'      => $product_id,
				'stock'           => $stock,
				'reserved_others' => $reserved,
				'customer_has'    => $customer_has,
				'available'       => $available,
				'result'          => $result,
			) );
			AS_CAI_Advanced_Debug::instance()->performance_end( 'cart_is_purchasable', array(
				'product_id' => $product_id,
				'result'     => $result,
			) );
		}
		
		// Legacy debug logging (when WP_DEBUG is active)
		if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			AS_CAI_Logger::instance()->debug( 'is_purchasable check', array(
				'product_id' => $product_id,
				'stock' => $stock,
				'reserved_others' => $reserved,
				'customer_has' => $customer_has,
				'available' => $available,
				'result' => $result
			));
		}
		
		// Purchasable if: (Available > 0) OR (Customer already has reservation)
		return $result;
	}

	public function is_in_stock( $in_stock, $product ) {
		// Use the same logic as is_purchasable (v1.3.4 fix).
		return $this->is_purchasable( $in_stock, $product );
	}

	/**
	 * Validate add to cart - prevent duplicate products in cart (v1.3.6)
	 * Enhanced in v1.3.19 - better stock validation for all products
	 * 
	 * @param bool $passed Validation result
	 * @param int $product_id Product ID
	 * @param int $quantity Quantity to add
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		// FIX v1.3.19: Check stock management instead of availability counter
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->managing_stock() ) {
			return $passed; // No stock management = use default validation
		}

		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		if ( ! $customer_id ) {
			return $passed;
		}

		// Optional: Check if product has availability counter
		// If yes, only allow 1x in cart (like Auditorium products)
		$availability = AS_CAI_Availability_Check::get_product_availability( $product_id );
		$has_counter = $availability['has_counter'];

		// Check if product is already in cart (only for products with counter!)
		if ( $has_counter && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( $cart_item['product_id'] == $product_id ) {
					// Product already in cart - prevent duplicate
					wc_add_notice(
						__( 'Dieses Produkt befindet sich bereits in deinem Warenkorb. Du kannst es nur einmal buchen.', 'as-camp-availability-integration' ),
						'error'
					);
					return false;
				}
			}
		}

		// Check available stock
		$stock = $product->get_stock_quantity();
		
		if ( null !== $stock ) {
			// Get reserved stock (excluding this customer's reservation)
			$reserved = $this->db->get_reserved_stock_for_product( $product_id, $customer_id );
			
			// Get this customer's reservation
			$customer_reserved = $this->db->get_reserved_products_by_customer( $customer_id );
			$customer_has = isset( $customer_reserved[ $product_id ] ) ? $customer_reserved[ $product_id ] : 0;
			
			$available = $stock - $reserved;
			
			// Debug logging
			if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				AS_CAI_Logger::instance()->debug( 'validate_add_to_cart', array(
					'product_id' => $product_id,
					'quantity_adding' => $quantity,
					'stock' => $stock,
					'reserved_others' => $reserved,
					'customer_has' => $customer_has,
					'available' => $available,
				));
			}
			
			// Check if enough available (consider already reserved quantity)
			$needed = $quantity - $customer_has;
			
			if ( $needed > $available ) {
				wc_add_notice(
					sprintf(
						__( 'Dieses Produkt ist nicht in der gewünschten Menge verfügbar. Verfügbar: %d (Du hast bereits: %d)', 'as-camp-availability-integration' ),
						$available + $customer_has,
						$customer_has
					),
					'error'
				);
				return false;
			}
		}

		return $passed;
	}

	/**
	 * Pre-remove cart item from session (v1.3.8, enhanced v1.3.9)
	 * 
	 * Checks if reservation has expired before cart item is loaded from session.
	 * This is more reliable than trying to remove items after they're loaded.
	 * 
	 * v1.3.9: Added debug logging and Seat Planner meta cleanup
	 * 
	 * Based on the approach used by "Reserved Stock Pro" plugin, which uses this
	 * hook to prevent expired items from being loaded into the cart at all.
	 * 
	 * @param bool $should_remove Whether to remove the item
	 * @param string $cart_item_key Cart item key
	 * @param array $cart_item Cart item data
	 * @param WC_Product $product Product object
	 * @return bool
	 */
	public function pre_remove_cart_item_from_session( $should_remove, $cart_item_key, $cart_item, $product ) {
		// If already being removed, honor that
		if ( $should_remove ) {
			return $should_remove;
		}
		
		// Skip AJAX requests to avoid interfering with cart updates
		if ( wp_doing_ajax() ) {
			return $should_remove;
		}
		
		// Get product ID
		$product_id = isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0;
		if ( ! $product_id ) {
			return $should_remove;
		}
		
		// Check if the product has an availability counter. If it does not, still
		// continue with reservation handling, because products without a visible counter
		// may still be subject to reservation expiry (e.g., "simple" or "auditorium" products).
		// We intentionally do not early-return here so that any reservation record for
		// this product can be evaluated and cleaned up. See #1.3.15.
		
		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		if ( ! $customer_id ) {
			return $should_remove;
		}
		
		// Check if reservation still exists
		$reserved_products = $this->db->get_reserved_products_by_customer( $customer_id );
		
        // Determine if the customer has a reservation for this product. Use array key lookup instead of in_array(),
        // since the reserved products array is keyed by product ID.
        if ( ! isset( $reserved_products[ $product_id ] ) ) {
			// No reservation found - remove from cart!
			
			// DEBUG LOGGING (v1.3.14 - use structured logger)
			AS_CAI_Logger::instance()->info( 'Removing expired product from cart', array(
				'product_id' => $product_id,
				'customer_id' => $customer_id,
				'cart_key' => $cart_item_key,
				'reserved_products' => $reserved_products,
			) );
			
			// 1. Remove from session cart (v1.3.12 - exact Reserved Stock Pro syntax!)
			$session_cart = WC()->session->get( 'cart', );  // Note the comma - Reserved Stock Pro syntax!
			if ( isset( $session_cart[ $cart_item_key ] ) ) {
				unset( $session_cart[ $cart_item_key ] );
				WC()->session->set( 'cart', $session_cart );
				
				if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[AS-CAI v1.3.13] Removed from session cart' );
				}
			}
			
			// 2. Remove from persistent cart (logged-in users)
			if ( get_current_user_id() ) {
				$saved_cart = get_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), true );
				if ( isset( $saved_cart['cart'][ $cart_item_key ] ) ) {
					unset( $saved_cart['cart'][ $cart_item_key ] );
					update_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), $saved_cart );
					
					if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( '[AS-CAI v1.3.13] Removed from persistent cart (user meta)' );
					}
				}
			}
			
			// 3. SEAT PLANNER SPECIFIC: Remove seat planner transients and meta data (v1.3.13 - CRITICAL FIX!)
			// Seat Planner stores reservations as transients: stachesepl_reserved_seat_{product_id}_{seat_id}
			// We MUST delete these transients when removing items from cart!
			if ( isset( $cart_item['seat_data'] ) && is_object( $cart_item['seat_data'] ) && isset( $cart_item['seat_data']->seatId ) ) {
				$seat_id = $cart_item['seat_data']->seatId;
				
				if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( '[AS-CAI v1.3.13] Detected Seat Planner seat_data - Seat ID: %s, Product ID: %d', $seat_id, $product_id ) );
				}
				
				// DELETE the Seat Planner transient - this is CRITICAL!
				$transient_key = "stachesepl_reserved_seat_{$product_id}_{$seat_id}";
				delete_transient( $transient_key );
				
				if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( '[AS-CAI v1.3.13] DELETED Seat Planner transient: %s', $transient_key ) );
				}
			}
			
			// Also clean up legacy Seat Planner session keys (if they exist)
			$seat_keys_to_check = array(
				'stachethemes_seat_selection_' . $product_id,
				'stachethemes_seat_data_' . $product_id,
				'stachethemes_reserved_seats_' . $product_id,
			);
			
			foreach ( $seat_keys_to_check as $seat_key ) {
				if ( WC()->session->get( $seat_key ) ) {
					WC()->session->set( $seat_key, null );
					
					if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( '[AS-CAI v1.3.13] Cleared Seat Planner session: %s', $seat_key ) );
					}
				}
			}
			
			// 4. Show notice to user
			wc_add_notice(
				__( 'Einige Produkte wurden aus deinem Warenkorb entfernt, da ihre Reservierung abgelaufen ist.', 'as-camp-availability-integration' ),
				'error'
			);
			
			if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[AS-CAI v1.3.13] Cart cleanup completed - returning true to remove item' );
			}
			
			return true; // Item will be removed from cart!
		}
		
		return false; // Keep item in cart
	}

	/**
	 * Cleanup expired items after session load (v1.3.12)
	 * 
	 * This method is triggered by 'woocommerce_cart_loaded_from_session' hook,
	 * which is called EVERY time the cart is loaded from the session.
	 * This ensures that expired items are ALWAYS removed when the cart page is opened.
	 * 
	 * Based on Reserved Stock Pro's working implementation.
	 * 
	 * @param WC_Cart $cart Cart object
	 */
	public function cleanup_expired_items_after_session_load( $cart ) {
		if ( ! $cart || is_admin() || wp_doing_ajax() ) {
			return;
		}
		
		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		if ( ! $customer_id ) {
			return;
		}

		// Get reserved products for this customer
		$reserved_products = $this->db->get_reserved_products_by_customer( $customer_id );
		
		// Get current cart contents
		$cart_contents = $cart->get_cart();
		$items_to_remove = array();
		
		if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[AS-CAI v1.3.13] cleanup_expired_items_after_session_load - Customer: %s, Reserved Products: %s, Cart Items: %d',
				$customer_id,
				json_encode($reserved_products),
				count($cart_contents)
			) );
		}
		
		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];
			
			// Do not skip products without an availability counter. Products may still
			// be subject to reservation logic even if the counter is not shown.
			
            // Check if reservation exists. Use isset() because the array is keyed by product ID.
            if ( ! isset( $reserved_products[ $product_id ] ) ) {
				// No reservation found - mark for removal!
				$items_to_remove[] = $cart_item_key;
				
				if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf(
						'[AS-CAI v1.3.13] Item marked for removal - Product ID: %d, Cart Key: %s',
						$product_id,
						$cart_item_key
					) );
				}
			}
		}
		
		// Remove expired items
		if ( ! empty( $items_to_remove ) ) {
			foreach ( $items_to_remove as $cart_item_key ) {
				// Use WooCommerce's remove_cart_item method
				$cart->remove_cart_item( $cart_item_key );
				
				if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf(
						'[AS-CAI v1.3.13] Item removed using remove_cart_item() - Cart Key: %s',
						$cart_item_key
					) );
				}
			}
			
			// Show notice to user (only once)
			if ( ! wc_has_notice( __( 'Einige Produkte wurden aus deinem Warenkorb entfernt, da ihre Reservierung abgelaufen ist.', 'as-camp-availability-integration' ), 'error' ) ) {
				wc_add_notice(
					__( 'Einige Produkte wurden aus deinem Warenkorb entfernt, da ihre Reservierung abgelaufen ist.', 'as-camp-availability-integration' ),
					'error'
				);
			}
			
			if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[AS-CAI v1.3.13] Cleanup completed - %d items removed',
					count($items_to_remove)
				) );
			}
		}
	}

	/**
	 * Force cleanup expired cart items (v1.3.9)
	 * 
	 * BACKUP cleanup method that runs AFTER cart is loaded.
	 * This is a fallback for cases where pre_remove_cart_item_from_session
	 * doesn't work (e.g., Seat Planner interference).
	 * 
	 * Uses aggressive removal with WC()->cart->set_cart_contents()
	 * 
	 * @param WC_Cart $cart Cart object
	 */
	public function force_cleanup_expired_cart_items( $cart ) {
		if ( ! $cart || is_admin() ) {
			return;
		}
		
		// CRITICAL FIX v1.3.20: Don't cleanup while items are being added
		// The reservation is created AFTER woocommerce_add_to_cart, so we must wait
		if ( doing_action( 'woocommerce_add_to_cart' ) ) {
			return;
		}
		
		// Prevent infinite loops
		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		if ( ! $customer_id ) {
			return;
		}

		// Get reserved products for this customer
		$reserved_products = $this->db->get_reserved_products_by_customer( $customer_id );
		
		// Get current cart contents
		$cart_contents = $cart->get_cart();
		$items_removed = false;
		
		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			$product_id = $cart_item['product_id'];
			
			// Do not skip products without an availability counter. Products may still
			// be subject to reservation logic even if the counter is not shown.
			
            // Check if reservation exists. Use isset() because the array is keyed by product ID.
            if ( ! isset( $reserved_products[ $product_id ] ) ) {
				// No reservation found - FORCE REMOVE!
				
				if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf(
						'[AS-CAI v1.3.9 FORCE] Removing expired product - Product ID: %d, Customer: %s, Cart Key: %s',
						$product_id,
						$customer_id,
						$cart_item_key
					) );
				}
				
				// Remove from cart contents array
				unset( $cart_contents[ $cart_item_key ] );
				$items_removed = true;
				
				// Also clean up Seat Planner session data
				$seat_keys = array(
					'stachethemes_seat_selection_' . $product_id,
					'stachethemes_seat_data_' . $product_id,
					'stachethemes_reserved_seats_' . $product_id,
				);
				
				foreach ( $seat_keys as $seat_key ) {
					if ( WC()->session->get( $seat_key ) ) {
						WC()->session->set( $seat_key, null );
						
						if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( sprintf( '[AS-CAI v1.3.9 FORCE] Cleared Seat Planner session: %s', $seat_key ) );
						}
					}
				}
			}
		}

		// If we removed items, update the cart
		if ( $items_removed ) {
			// FORCE update cart contents
			$cart->set_cart_contents( $cart_contents );
			
			// Also update session
			WC()->session->set( 'cart', $cart_contents );
			
			// Update persistent cart for logged-in users
			if ( get_current_user_id() ) {
				$saved_cart = array( 'cart' => $cart_contents );
				update_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), $saved_cart );
			}
			
			// Show notice to user (only once)
			if ( ! wc_has_notice( __( 'Einige Produkte wurden aus deinem Warenkorb entfernt, da ihre Reservierung abgelaufen ist.', 'as-camp-availability-integration' ), 'error' ) ) {
				wc_add_notice(
					__( 'Einige Produkte wurden aus deinem Warenkorb entfernt, da ihre Reservierung abgelaufen ist.', 'as-camp-availability-integration' ),
					'error'
				);
			}
			
			if ( 'yes' === get_option( 'as_cai_debug_log', 'no' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[AS-CAI v1.3.9 FORCE] Cart forcefully updated with set_cart_contents()' );
			}
		}
	}

	public function transfer_on_login( $user_login, $user ) {
		if ( ! WC()->session ) { return; }

		$guest_id = 'guest_' . WC()->session->get_customer_id();
		$user_id = (string) $user->ID;

		$this->db->transfer_reservations( $guest_id, $user_id );
	}
}
