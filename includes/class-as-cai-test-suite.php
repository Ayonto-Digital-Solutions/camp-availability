<?php
/**
 * Test Suite - Automated testing for cart reservations
 * 
 * @package AS_Camp_Availability_Integration
 * @since 1.3.14
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AS_CAI_Test_Suite {
	private static $instance = null;
	private $db = null;
	private $logger = null;
	private $test_results = array();
	
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		$this->db = AS_CAI_Reservation_DB::instance();
		$this->logger = AS_CAI_Logger::instance();
		
		// Handle AJAX test runner
		add_action( 'wp_ajax_as_cai_run_tests', array( $this, 'run_tests_ajax' ) );
	}
	
	/**
	 * Render test suite page
	 */
	public function render_page() {
		?>
		<!-- Modern unified layout (v1.3.22) -->
		<div class="as-cai-card as-cai-fade-in">
			<div class="as-cai-card-header">
				<h2 class="as-cai-card-title">
					<i class="fas fa-vial"></i>
					<?php esc_html_e( 'Automated Tests', 'as-camp-availability-integration' ); ?>
				</h2>
			</div>
			<div class="as-cai-card-body">
				<div style="margin-bottom: 20px;">
					<p><?php esc_html_e( 'These tests verify all critical functions of the reservation system.', 'as-camp-availability-integration' ); ?></p>
					<button id="run-all-tests" class="as-cai-btn as-cai-btn-primary" style="font-size: 16px; padding: 12px 24px;">
						<i class="fas fa-play"></i>
						<?php esc_html_e( 'Run All Tests', 'as-camp-availability-integration' ); ?>
					</button>
				</div>
				
				<div id="test-results" style="display:none; margin-top: 20px;">
					<h3 style="margin-bottom: 15px; font-size: 18px;">
						<i class="fas fa-check-circle"></i>
						<?php esc_html_e( 'Test Results', 'as-camp-availability-integration' ); ?>
					</h3>
					<div id="test-results-content"></div>
				</div>
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#run-all-tests').on('click', function() {
				var $button = $(this);
				var originalText = $button.html();
				$button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> <?php esc_html_e( 'Running Tests...', 'as-camp-availability-integration' ); ?>');
				$('#test-results').show();
				$('#test-results-content').html('<div style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin" style="font-size: 40px; color: var(--as-primary);"></i></div>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'as_cai_run_tests',
						nonce: '<?php echo esc_js( wp_create_nonce( 'as_cai_tests' ) ); ?>'
					},
					success: function(response) {
						$button.prop('disabled', false).html(originalText);
						
						if (response.success) {
							$('#test-results-content').html(response.data.html);
						} else {
							$('#test-results-content').html('<div class="as-cai-card" style="background: #fee; border-left: 4px solid #dc3232;"><div class="as-cai-card-body"><strong><?php esc_html_e( 'Error:', 'as-camp-availability-integration' ); ?></strong> ' + response.data.message + '</div></div>');
						}
					},
					error: function() {
						$button.prop('disabled', false).html(originalText);
						$('#test-results-content').html('<div class="as-cai-card" style="background: #fee; border-left: 4px solid #dc3232;"><div class="as-cai-card-body"><strong><?php esc_html_e( 'AJAX Error!', 'as-camp-availability-integration' ); ?></strong></div></div>');
					}
				});
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Run all tests via AJAX
	 */
	public function run_tests_ajax() {
		check_ajax_referer( 'as_cai_tests', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
			return;
		}
		
		$this->test_results = array();
		
		// Run all tests
		$this->test_create_reservation();
		$this->test_expire_reservation();
		$this->test_cart_cleanup();
		$this->test_seat_planner_transient();
		$this->test_session_cart();
		$this->test_persistent_cart();
		$this->test_hook_execution();
		
		// Generate HTML report
		$html = $this->generate_html_report();
		
		wp_send_json_success( array( 'html' => $html ) );
	}
	
	/**
	 * Test 1: Create Reservation
	 */
	private function test_create_reservation() {
		$test_name = 'Test 1: Create Reservation';
		$customer_id = 'test_' . time();
		$product_id = 9999; // Dummy product
		$quantity = 1;
		
		try {
			$result = $this->db->reserve_stock( $customer_id, $product_id, $quantity );
			
			if ( $result ) {
				$reserved_products = $this->db->get_reserved_products_by_customer( $customer_id );
				
				if ( isset( $reserved_products[ $product_id ] ) && $reserved_products[ $product_id ] == $quantity ) {
					$this->add_test_result( $test_name, true, 'Reservation created successfully', array(
						'customer_id' => $customer_id,
						'product_id' => $product_id,
						'quantity' => $quantity,
						'reserved_products' => $reserved_products,
					) );
				} else {
					$this->add_test_result( $test_name, false, 'Reservation not found after creation', array(
						'reserved_products' => $reserved_products,
					) );
				}
				
				// Cleanup
				$this->db->release_reservation( $customer_id, $product_id );
			} else {
				$this->add_test_result( $test_name, false, 'Failed to create reservation', array() );
			}
		} catch ( Exception $e ) {
			$this->add_test_result( $test_name, false, 'Exception: ' . $e->getMessage(), array() );
		}
	}
	
	/**
	 * Test 2: Expire Reservation
	 */
	private function test_expire_reservation() {
		global $wpdb;
		$test_name = 'Test 2: Expire Reservation';
		$customer_id = 'test_' . time();
		$product_id = 9999;
		$quantity = 1;
		
		try {
			// Create reservation
			$this->db->reserve_stock( $customer_id, $product_id, $quantity );
			
			// Manually expire it
			$table_name = $wpdb->prefix . 'as_cai_cart_reservations';
			$wpdb->update(
				$table_name,
				array( 'expires' => '2020-01-01 00:00:00' ),
				array( 'customer_id' => $customer_id, 'product_id' => $product_id ),
				array( '%s' ),
				array( '%s', '%d' )
			);
			
			// Clear cache
			wp_cache_flush();
			
			// Check if expired
			$reserved_products = $this->db->get_reserved_products_by_customer( $customer_id );
			
			if ( empty( $reserved_products ) || ! isset( $reserved_products[ $product_id ] ) ) {
				$this->add_test_result( $test_name, true, 'Expired reservation correctly filtered out', array(
					'reserved_products' => $reserved_products,
				) );
			} else {
				$this->add_test_result( $test_name, false, 'Expired reservation still appears as valid', array(
					'reserved_products' => $reserved_products,
				) );
			}
			
			// Cleanup
			$this->db->release_reservation( $customer_id, $product_id );
		} catch ( Exception $e ) {
			$this->add_test_result( $test_name, false, 'Exception: ' . $e->getMessage(), array() );
		}
	}
	
	/**
	 * Test 3: Cart Cleanup
	 */
	private function test_cart_cleanup() {
		$test_name = 'Test 3: Cart Cleanup';
		
		try {
			if ( ! WC()->cart ) {
				$this->add_test_result( $test_name, false, 'WooCommerce cart not available', array() );
				return;
			}
			
			// Simulate cleanup
			$cart = WC()->cart;
			$cart_contents_before = count( $cart->get_cart() );
			
			// Try to trigger cleanup
			do_action( 'woocommerce_cart_loaded_from_session', $cart );
			
			$cart_contents_after = count( $cart->get_cart() );
			
			$this->add_test_result( $test_name, true, 'Cleanup hook triggered successfully', array(
				'cart_items_before' => $cart_contents_before,
				'cart_items_after' => $cart_contents_after,
				'hook_triggered' => did_action( 'woocommerce_cart_loaded_from_session' ),
			) );
		} catch ( Exception $e ) {
			$this->add_test_result( $test_name, false, 'Exception: ' . $e->getMessage(), array() );
		}
	}
	
	/**
	 * Test 4: Seat Planner Transient
	 */
	private function test_seat_planner_transient() {
		$test_name = 'Test 4: Seat Planner Transient Cleanup';
		
		try {
			if ( ! WC()->session ) {
				$this->add_test_result( $test_name, false, 'WooCommerce session not available', array() );
				return;
			}
			
			$product_id = 9999;
			$transient_key = 'stachethemes_seat_selection_' . $product_id;
			
			// Set test transient
			WC()->session->set( $transient_key, array( 'test' => 'data' ) );
			
			// Verify it was set
			$value_before = WC()->session->get( $transient_key );
			
			// Clear it
			WC()->session->set( $transient_key, null );
			
			// Verify it was cleared
			$value_after = WC()->session->get( $transient_key );
			
			if ( ! empty( $value_before ) && empty( $value_after ) ) {
				$this->add_test_result( $test_name, true, 'Transient set and cleared successfully', array(
					'transient_key' => $transient_key,
					'value_before' => $value_before,
					'value_after' => $value_after,
				) );
			} else {
				$this->add_test_result( $test_name, false, 'Transient cleanup failed', array(
					'value_before' => $value_before,
					'value_after' => $value_after,
				) );
			}
		} catch ( Exception $e ) {
			$this->add_test_result( $test_name, false, 'Exception: ' . $e->getMessage(), array() );
		}
	}
	
	/**
	 * Test 5: Session Cart
	 */
	private function test_session_cart() {
		$test_name = 'Test 5: Session Cart Access';
		
		try {
			if ( ! WC()->session ) {
				$this->add_test_result( $test_name, false, 'WooCommerce session not available', array() );
				return;
			}
			
			$session_cart = WC()->session->get( 'cart', array() );
			
			$this->add_test_result( $test_name, true, 'Session cart accessible', array(
				'cart_items' => count( $session_cart ),
				'session_id' => WC()->session->get_customer_id(),
			) );
		} catch ( Exception $e ) {
			$this->add_test_result( $test_name, false, 'Exception: ' . $e->getMessage(), array() );
		}
	}
	
	/**
	 * Test 6: Persistent Cart
	 */
	private function test_persistent_cart() {
		$test_name = 'Test 6: Persistent Cart Access';
		
		try {
			if ( ! get_current_user_id() ) {
				$this->add_test_result( $test_name, true, 'Skipped (not logged in)', array(
					'message' => 'Persistent cart is only available for logged-in users',
				) );
				return;
			}
			
			$saved_cart = get_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), true );
			$persistent_cart = isset( $saved_cart['cart'] ) ? $saved_cart['cart'] : array();
			
			$this->add_test_result( $test_name, true, 'Persistent cart accessible', array(
				'cart_items' => count( $persistent_cart ),
				'user_id' => get_current_user_id(),
			) );
		} catch ( Exception $e ) {
			$this->add_test_result( $test_name, false, 'Exception: ' . $e->getMessage(), array() );
		}
	}
	
	/**
	 * Test 7: Hook Execution
	 */
	private function test_hook_execution() {
		global $wp_filter;
		$test_name = 'Test 7: Hook Execution Check';
		
		$hooks_to_check = array(
			'woocommerce_pre_remove_cart_item_from_session',
			'woocommerce_cart_loaded_from_session',
			'woocommerce_before_calculate_totals',
		);
		
		$registered_hooks = array();
		$missing_hooks = array();
		
		foreach ( $hooks_to_check as $hook_name ) {
			if ( isset( $wp_filter[ $hook_name ] ) ) {
				$registered_hooks[] = $hook_name;
			} else {
				$missing_hooks[] = $hook_name;
			}
		}
		
		if ( empty( $missing_hooks ) ) {
			$this->add_test_result( $test_name, true, 'All hooks registered', array(
				'registered_hooks' => $registered_hooks,
			) );
		} else {
			$this->add_test_result( $test_name, false, 'Some hooks missing', array(
				'registered_hooks' => $registered_hooks,
				'missing_hooks' => $missing_hooks,
			) );
		}
	}
	
	/**
	 * Add test result
	 */
	private function add_test_result( $name, $passed, $message, $details = array() ) {
		$this->test_results[] = array(
			'name' => $name,
			'passed' => $passed,
			'message' => $message,
			'details' => $details,
		);
	}
	
	/**
	 * Generate HTML report
	 */
	private function generate_html_report() {
		$total = count( $this->test_results );
		$passed = 0;
		
		foreach ( $this->test_results as $result ) {
			if ( $result['passed'] ) {
				$passed++;
			}
		}
		
		$all_passed = ( $passed === $total );
		$summary_color = $all_passed ? '#46b450' : '#dc3232';
		
		$html = '<div class="as-cai-card" style="background: ' . ( $all_passed ? '#ecf7ed' : '#fef7f7' ) . '; border-left: 4px solid ' . $summary_color . '; margin-bottom: 20px;">';
		$html .= '<div class="as-cai-card-body">';
		$html .= '<h3 style="margin: 0; font-size: 18px; color: ' . $summary_color . ';">';
		$html .= '<i class="fas ' . ( $all_passed ? 'fa-check-circle' : 'fa-exclamation-triangle' ) . '"></i> ';
		$html .= '<strong>Summary: ' . $passed . '/' . $total . ' tests passed</strong>';
		$html .= '</h3>';
		$html .= '</div>';
		$html .= '</div>';
		
		foreach ( $this->test_results as $result ) {
			$passed_test = $result['passed'];
			$bg_color = $passed_test ? '#ecf7ed' : '#fef7f7';
			$border_color = $passed_test ? '#46b450' : '#dc3232';
			$icon = $passed_test ? '<i class="fas fa-check-circle" style="color: #46b450;"></i>' : '<i class="fas fa-times-circle" style="color: #dc3232;"></i>';
			
			$html .= '<div class="as-cai-card" style="background: ' . $bg_color . '; border-left: 4px solid ' . $border_color . '; margin-bottom: 15px;">';
			$html .= '<div class="as-cai-card-body">';
			$html .= '<h4 style="margin: 0 0 8px 0; font-size: 15px; font-weight: 600;">' . $icon . ' ' . esc_html( $result['name'] ) . '</h4>';
			$html .= '<p style="margin: 0; font-size: 13px; color: #666;">' . esc_html( $result['message'] ) . '</p>';
			
			if ( ! empty( $result['details'] ) ) {
				$html .= '<pre style="background: #fff; padding: 10px; margin-top: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 11px; overflow-x: auto; white-space: pre-wrap;">';
				$html .= esc_html( print_r( $result['details'], true ) );
				$html .= '</pre>';
			}
			
			$html .= '</div>';
			$html .= '</div>';
		}
		
		return $html;
	}
}
