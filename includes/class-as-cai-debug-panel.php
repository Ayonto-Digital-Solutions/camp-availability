<?php
/**
 * Debug Panel - Admin debugging interface
 * 
 * @package AS_Camp_Availability_Integration
 * @since 1.3.14
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AS_CAI_Debug_Panel {
	private static $instance = null;
	private $db = null;
	private $logger = null;
	
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		$this->db = AS_CAI_Reservation_DB::instance();
		$this->logger = AS_CAI_Logger::instance();
		
		// Handle AJAX actions
		add_action( 'wp_ajax_as_cai_debug_action', array( $this, 'handle_ajax' ) );
		
		// NEW v1.3.19: Frontend Debug for Admins
		add_action( 'wp_footer', array( $this, 'render_frontend_debug' ), 999 );
	}
	
	/**
	 * Render debug panel page
	 */
	public function render_page() {
		?>
		<!-- Modern unified layout (v1.3.22) -->
		<?php $this->render_system_info(); ?>
		<?php $this->render_reservations_table(); ?>
		<?php $this->render_cart_status(); ?>
		<?php $this->render_hook_status(); ?>
		<?php $this->render_seat_planner_transients(); ?>
		<?php $this->render_recent_logs(); ?>
		<?php $this->render_debug_actions(); ?>
		
		<script>
		jQuery(document).ready(function($) {
			// Handle debug action buttons
			$('.as-cai-debug-btn').on('click', function() {
				var action = $(this).data('action');
				var productId = $(this).data('product-id') || '';
				
				if (action === 'clear_all' && !confirm('Wirklich alle Reservierungen löschen?')) {
					return;
				}
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'as_cai_debug_action',
						debug_action: action,
						product_id: productId,
						nonce: '<?php echo esc_js( wp_create_nonce( 'as_cai_debug' ) ); ?>'
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message);
							location.reload();
						} else {
							alert('Fehler: ' + response.data.message);
						}
					}
				});
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Render system information
	 */
	private function render_system_info() {
		global $wpdb;
		
		$wp_timezone = wp_timezone_string();
		$db_timezone = $wpdb->get_var( "SELECT @@session.time_zone" );
		$current_time_wp = current_time( 'Y-m-d H:i:s' );
		$current_time_utc = current_time( 'Y-m-d H:i:s', true );
		$current_time_db = $wpdb->get_var( "SELECT NOW()" );
		
		?>
		<div class="as-cai-card as-cai-fade-in">
			<div class="as-cai-card-header">
				<h2 class="as-cai-card-title">
					<i class="fas fa-cog"></i>
					<?php esc_html_e( 'System Information', 'as-camp-availability-integration' ); ?>
				</h2>
			</div>
			<div class="as-cai-card-body">
				<table class="as-cai-table">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Plugin Version', 'as-camp-availability-integration' ); ?></th>
							<td><strong><?php echo esc_html( AS_CAI_VERSION ); ?></strong></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'WordPress Timezone', 'as-camp-availability-integration' ); ?></th>
							<td><?php echo esc_html( $wp_timezone ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Database Timezone', 'as-camp-availability-integration' ); ?></th>
							<td><?php echo esc_html( $db_timezone ?: 'SYSTEM' ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Current Time (WP Local)', 'as-camp-availability-integration' ); ?></th>
							<td><?php echo esc_html( $current_time_wp ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Current Time (UTC)', 'as-camp-availability-integration' ); ?></th>
							<td><?php echo esc_html( $current_time_utc ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Current Time (DB NOW())', 'as-camp-availability-integration' ); ?></th>
							<td><?php echo esc_html( $current_time_db ); ?></td>
						</tr>
						<tr>
							<th>WP_DEBUG</th>
							<td>
								<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
									<span class="as-cai-badge active">
										<i class="fas fa-check-circle"></i>
										<?php esc_html_e( 'Enabled', 'as-camp-availability-integration' ); ?>
									</span>
								<?php else : ?>
									<span class="as-cai-badge expired">
										<i class="fas fa-times-circle"></i>
										<?php esc_html_e( 'Disabled', 'as-camp-availability-integration' ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Reservation Duration', 'as-camp-availability-integration' ); ?></th>
							<td><strong><?php echo esc_html( $this->db->get_reservation_minutes() ); ?></strong> <?php esc_html_e( 'minutes', 'as-camp-availability-integration' ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Cart Reservation Enabled', 'as-camp-availability-integration' ); ?></th>
							<td>
								<?php if ( get_option( 'as_cai_enable_cart_reservation', 'yes' ) === 'yes' ) : ?>
									<span class="as-cai-badge active">
										<i class="fas fa-check-circle"></i>
										<?php esc_html_e( 'Yes', 'as-camp-availability-integration' ); ?>
									</span>
								<?php else : ?>
									<span class="as-cai-badge expired">
										<i class="fas fa-times-circle"></i>
										<?php esc_html_e( 'No', 'as-camp-availability-integration' ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Render active reservations table
	 */
	private function render_reservations_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'as_cai_cart_reservations';
		
		$reservations = $wpdb->get_results( "
			SELECT 
				*,
				TIMESTAMPDIFF(SECOND, NOW(), expires) AS seconds_left,
				CASE WHEN expires < NOW() THEN 'EXPIRED' ELSE 'VALID' END AS status
			FROM {$table_name}
			ORDER BY expires DESC
		", ARRAY_A );
		
		?>
		<div class="as-cai-card as-cai-fade-in">
			<div class="as-cai-card-header">
				<h2 class="as-cai-card-title">
					<i class="fas fa-list"></i>
					<?php 
					printf( 
						esc_html__( 'Active Reservations (%d)', 'as-camp-availability-integration' ), 
						count( $reservations ) 
					); 
					?>
				</h2>
			</div>
			<div class="as-cai-card-body">
				<?php if ( empty( $reservations ) ) : ?>
					<div class="as-cai-empty-state">
						<i class="fas fa-inbox"></i>
						<p><?php esc_html_e( 'No active reservations found.', 'as-camp-availability-integration' ); ?></p>
					</div>
				<?php else : ?>
					<div style="overflow-x: auto;">
						<table class="as-cai-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Customer ID', 'as-camp-availability-integration' ); ?></th>
									<th><?php esc_html_e( 'Product ID', 'as-camp-availability-integration' ); ?></th>
									<th><?php esc_html_e( 'Quantity', 'as-camp-availability-integration' ); ?></th>
									<th><?php esc_html_e( 'Reserved At', 'as-camp-availability-integration' ); ?></th>
									<th><?php esc_html_e( 'Expires', 'as-camp-availability-integration' ); ?></th>
									<th><?php esc_html_e( 'Time Left', 'as-camp-availability-integration' ); ?></th>
									<th><?php esc_html_e( 'Status', 'as-camp-availability-integration' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'as-camp-availability-integration' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $reservations as $res ) : ?>
									<tr>
										<td>
											<code style="font-size: 12px;">
												<?php echo esc_html( substr( $res['customer_id'], 0, 20 ) . '...' ); ?>
											</code>
										</td>
										<td>
											<?php 
											$product = wc_get_product( $res['product_id'] );
											echo '<strong>' . esc_html( $res['product_id'] ) . '</strong>';
											if ( $product ) {
												echo '<br><small>' . esc_html( $product->get_name() ) . '</small>';
											}
											?>
										</td>
										<td><strong><?php echo esc_html( $res['stock_quantity'] ); ?></strong></td>
										<td style="font-size: 13px;"><?php echo esc_html( $res['timestamp'] ); ?></td>
										<td style="font-size: 13px;"><?php echo esc_html( $res['expires'] ); ?></td>
										<td>
											<?php 
											$seconds = intval( $res['seconds_left'] );
											if ( $seconds > 0 ) {
												echo '<span class="as-cai-badge active">' . esc_html( gmdate( 'i:s', $seconds ) ) . '</span>';
											} else {
												echo '<span class="as-cai-badge expired">' . esc_html( gmdate( 'i:s', abs( $seconds ) ) ) . ' ago</span>';
											}
											?>
										</td>
										<td>
											<?php 
											if ( $res['status'] === 'VALID' ) {
												echo '<span class="as-cai-badge active"><i class="fas fa-check-circle"></i> ' . esc_html__( 'Active', 'as-camp-availability-integration' ) . '</span>';
											} else {
												echo '<span class="as-cai-badge expired"><i class="fas fa-times-circle"></i> ' . esc_html__( 'Expired', 'as-camp-availability-integration' ) . '</span>';
											}
											?>
										</td>
										<td>
											<button class="as-cai-btn as-cai-btn-danger as-cai-debug-btn" data-action="expire_reservation" data-product-id="<?php echo esc_attr( $res['product_id'] ); ?>">
												<i class="fas fa-clock"></i>
												<?php esc_html_e( 'Expire Now', 'as-camp-availability-integration' ); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Render cart status
	 */
	private function render_cart_status() {
		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		$session_cart = WC()->session ? WC()->session->get( 'cart', array() ) : array();
		$persistent_cart = array();
		
		if ( get_current_user_id() ) {
			$saved_cart = get_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), true );
			$persistent_cart = isset( $saved_cart['cart'] ) ? $saved_cart['cart'] : array();
		}
		
		?>
		<div class="as-cai-card as-cai-fade-in">
			<div class="as-cai-card-header">
				<h2 class="as-cai-card-title">
					<i class="fas fa-shopping-cart"></i>
					<?php esc_html_e( 'Cart Status', 'as-camp-availability-integration' ); ?>
				</h2>
			</div>
			<div class="as-cai-card-body">
				<table class="as-cai-table">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Customer ID', 'as-camp-availability-integration' ); ?></th>
							<td><code><?php echo esc_html( $customer_id ?: 'N/A' ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'User ID', 'as-camp-availability-integration' ); ?></th>
							<td><strong><?php echo esc_html( get_current_user_id() ?: 'Guest' ); ?></strong></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Session Cart Items', 'as-camp-availability-integration' ); ?></th>
							<td><strong><?php echo count( $session_cart ); ?></strong></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Persistent Cart Items', 'as-camp-availability-integration' ); ?></th>
							<td><strong><?php echo count( $persistent_cart ); ?></strong></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Session Cart Contents', 'as-camp-availability-integration' ); ?></th>
							<td><pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px;"><?php echo esc_html( wp_json_encode( $session_cart, JSON_PRETTY_PRINT ) ); ?></pre></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Render hook status
	 */
	private function render_hook_status() {
		global $wp_filter;
		
		$hooks_to_check = array(
			'woocommerce_pre_remove_cart_item_from_session',
			'woocommerce_cart_loaded_from_session',
			'woocommerce_before_calculate_totals',
		);
		
		?>
		<div class="as-cai-card as-cai-fade-in">
			<div class="as-cai-card-header">
				<h2 class="as-cai-card-title">
					<i class="fas fa-link"></i>
					<?php esc_html_e( 'Hook Status', 'as-camp-availability-integration' ); ?>
				</h2>
			</div>
			<div class="as-cai-card-body">
				<div style="overflow-x: auto;">
					<table class="as-cai-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Hook Name', 'as-camp-availability-integration' ); ?></th>
								<th><?php esc_html_e( 'Registered', 'as-camp-availability-integration' ); ?></th>
								<th><?php esc_html_e( 'Callbacks', 'as-camp-availability-integration' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $hooks_to_check as $hook_name ) : ?>
								<?php 
								$registered = isset( $wp_filter[ $hook_name ] );
								$callbacks = array();
								
								if ( $registered && isset( $wp_filter[ $hook_name ]->callbacks ) ) {
									foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $hooks ) {
										foreach ( $hooks as $hook ) {
											if ( is_array( $hook['function'] ) ) {
												$class = is_object( $hook['function'][0] ) ? get_class( $hook['function'][0] ) : $hook['function'][0];
												$method = $hook['function'][1];
												$callbacks[] = array(
													'priority' => $priority,
													'callback' => $class . '::' . $method,
												);
											}
										}
									}
								}
								?>
								<tr>
									<td><code style="font-size: 12px;"><?php echo esc_html( $hook_name ); ?></code></td>
									<td>
										<?php if ( $registered ) : ?>
											<span class="as-cai-badge active">
												<i class="fas fa-check-circle"></i>
												<?php esc_html_e( 'Yes', 'as-camp-availability-integration' ); ?>
											</span>
										<?php else : ?>
											<span class="as-cai-badge expired">
												<i class="fas fa-times-circle"></i>
												<?php esc_html_e( 'No', 'as-camp-availability-integration' ); ?>
											</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( ! empty( $callbacks ) ) : ?>
											<ul style="margin: 0; padding-left: 20px; font-size: 12px;">
												<?php foreach ( $callbacks as $cb ) : ?>
													<li>
														<span class="as-cai-badge active" style="font-size: 11px;"><?php echo esc_html( $cb['priority'] ); ?></span>
														<code style="font-size: 11px;"><?php echo esc_html( $cb['callback'] ); ?></code>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php else : ?>
											<em style="font-size: 12px; color: #999;"><?php esc_html_e( 'No AS-CAI callbacks', 'as-camp-availability-integration' ); ?></em>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Render Seat Planner transients
	 */
	private function render_seat_planner_transients() {
		$transients = array();
		
		if ( WC()->session ) {
			$session_data = WC()->session->get_session_data();
			
			foreach ( $session_data as $key => $value ) {
				if ( strpos( $key, 'stachethemes_' ) === 0 ) {
					$transients[ $key ] = $value;
				}
			}
		}
		
		?>
		<div class="as-cai-card as-cai-fade-in">
			<div class="as-cai-card-header">
				<h2 class="as-cai-card-title">
					<i class="fas fa-theater-masks"></i>
					<?php 
					printf( 
						esc_html__( 'Seat Planner Transients (%d)', 'as-camp-availability-integration' ), 
						count( $transients ) 
					); 
					?>
				</h2>
			</div>
			<div class="as-cai-card-body">
				<?php if ( empty( $transients ) ) : ?>
					<div class="as-cai-empty-state">
						<i class="fas fa-inbox"></i>
						<p><?php esc_html_e( 'No Seat Planner transients found.', 'as-camp-availability-integration' ); ?></p>
					</div>
				<?php else : ?>
					<div style="overflow-x: auto;">
						<table class="as-cai-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Key', 'as-camp-availability-integration' ); ?></th>
									<th><?php esc_html_e( 'Value', 'as-camp-availability-integration' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $transients as $key => $value ) : ?>
									<tr>
										<td><code style="font-size: 12px;"><?php echo esc_html( $key ); ?></code></td>
										<td><pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 11px;"><?php echo esc_html( print_r( $value, true ) ); ?></pre></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Render recent logs
	 */
	private function render_recent_logs() {
		$logs = $this->logger->get_recent_logs( 50 );
		
		?>
		<div class="as-cai-card as-cai-fade-in">
			<div class="as-cai-card-header">
				<h2 class="as-cai-card-title">
					<i class="fas fa-file-alt"></i>
					<?php esc_html_e( 'Recent Logs (Last 50 Entries)', 'as-camp-availability-integration' ); ?>
				</h2>
			</div>
			<div class="as-cai-card-body">
				<div style="max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 11px;">
					<?php if ( empty( $logs ) ) : ?>
						<div class="as-cai-empty-state">
							<i class="fas fa-inbox"></i>
							<p><?php esc_html_e( 'No log entries found. Enable WP_DEBUG to see logs.', 'as-camp-availability-integration' ); ?></p>
						</div>
					<?php else : ?>
						<?php foreach ( $logs as $log ) : ?>
							<div style="padding: 8px; border-bottom: 1px solid #ddd; white-space: pre-wrap; word-break: break-all;">
								<?php echo esc_html( $log ); ?>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px; font-size: 12px;">
					<strong><?php esc_html_e( 'Log file:', 'as-camp-availability-integration' ); ?></strong> <code><?php echo esc_html( $this->logger->get_log_file_path() ); ?></code><br>
					<strong><?php esc_html_e( 'Size:', 'as-camp-availability-integration' ); ?></strong> <?php echo esc_html( size_format( $this->logger->get_log_file_size() ) ); ?>
				</div>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Render debug actions
	 */
	private function render_debug_actions() {
		?>
		<div class="as-cai-card as-cai-fade-in">
			<div class="as-cai-card-header">
				<h2 class="as-cai-card-title">
					<i class="fas fa-bolt"></i>
					<?php esc_html_e( 'Debug Actions', 'as-camp-availability-integration' ); ?>
				</h2>
			</div>
			<div class="as-cai-card-body">
				<div style="display: flex; gap: 10px; flex-wrap: wrap;">
					<button class="as-cai-btn as-cai-btn-primary as-cai-debug-btn" data-action="test_cleanup">
						<i class="fas fa-broom"></i>
						<?php esc_html_e( 'Test Cart Cleanup', 'as-camp-availability-integration' ); ?>
					</button>
					<button class="as-cai-btn as-cai-btn-danger as-cai-debug-btn" data-action="clear_all">
						<i class="fas fa-trash"></i>
						<?php esc_html_e( 'Clear All Reservations', 'as-camp-availability-integration' ); ?>
					</button>
					<button class="as-cai-btn as-cai-btn-secondary as-cai-debug-btn" data-action="clear_logs">
						<i class="fas fa-file-alt"></i>
						<?php esc_html_e( 'Clear Logs', 'as-camp-availability-integration' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Handle AJAX debug actions
	 */
	public function handle_ajax() {
		check_ajax_referer( 'as_cai_debug', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
			return;
		}
		
		$action = isset( $_POST['debug_action'] ) ? sanitize_text_field( $_POST['debug_action'] ) : '';
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		
		switch ( $action ) {
			case 'test_cleanup':
				// Force cleanup
				$cart = WC()->cart;
				AS_CAI_Cart_Reservation::instance()->cleanup_expired_items_after_session_load( $cart );
				wp_send_json_success( array( 'message' => 'Cart cleanup executed!' ) );
				break;
				
			case 'expire_reservation':
				global $wpdb;
				$table_name = $wpdb->prefix . 'as_cai_cart_reservations';
				$wpdb->update(
					$table_name,
					array( 'expires' => current_time( 'mysql', true ) ),
					array( 'product_id' => $product_id ),
					array( '%s' ),
					array( '%d' )
				);
				wp_send_json_success( array( 'message' => 'Reservation expired!' ) );
				break;
				
			case 'clear_all':
				$this->db->flush_all_reservations();
				wp_send_json_success( array( 'message' => 'All reservations cleared!' ) );
				break;
				
			case 'clear_logs':
				$this->logger->clear_logs();
				wp_send_json_success( array( 'message' => 'Logs cleared!' ) );
				break;
				
			default:
				wp_send_json_error( array( 'message' => 'Unknown action' ) );
		}
	}
	
	/**
	 * Render frontend debug info for admins
	 * 
	 * @since 1.3.19
	 */
	public function render_frontend_debug() {
		// Only for admins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Only when WP_DEBUG is active
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		
		$debug_data = array();
		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		
		// Context: Product page
		if ( is_product() ) {
			global $product;
			if ( $product ) {
				$product_id = $product->get_id();
				$stock = $product->get_stock_quantity();
				$managing_stock = $product->managing_stock();
				
				$reserved_total = $this->db->get_reserved_stock_for_product( $product_id );
				$reserved_others = $this->db->get_reserved_stock_for_product( $product_id, $customer_id );
				
				$customer_reserved = $this->db->get_reserved_products_by_customer( $customer_id );
				$customer_has = isset( $customer_reserved[ $product_id ] ) ? $customer_reserved[ $product_id ] : 0;
				
				$availability = AS_CAI_Availability_Check::get_product_availability( $product_id );
				
				$debug_data = array(
					'Kontext' => 'Produktseite',
					'Produkt ID' => $product_id,
					'Produkt Name' => $product->get_name(),
					'Stock Management' => $managing_stock ? 'Ja' : 'Nein',
					'Gesamter Stock' => $stock !== null ? $stock : 'N/A',
					'Reserviert (Gesamt)' => $reserved_total,
					'Reserviert (Andere)' => $reserved_others,
					'Eigene Reservierung' => $customer_has,
					'Verfügbar' => $stock !== null ? ( $stock - $reserved_others ) : 'N/A',
					'Has Counter' => $availability['has_counter'] ? 'Ja' : 'Nein',
					'Customer ID' => $customer_id,
				);
			}
		}
		
		// Context: Cart
		elseif ( is_cart() && WC()->cart ) {
			$cart_items = WC()->cart->get_cart();
			$debug_data['Kontext'] = 'Warenkorb';
			$debug_data['Artikel im Warenkorb'] = count( $cart_items );
			$debug_data['Customer ID'] = $customer_id;
			
			$item_index = 0;
			foreach ( $cart_items as $cart_item ) {
				$item_index++;
				$product_id = $cart_item['product_id'];
				$product = wc_get_product( $product_id );
				
				if ( $product && $product->managing_stock() ) {
					$expires_ts = $this->db->get_product_expiration_timestamp( $customer_id, $product_id );
					$remaining = $expires_ts ? AS_CAI_Timezone::seconds_until( $expires_ts ) : 0;
					
					$debug_data["Artikel #{$item_index}"] = sprintf(
						'%s (ID: %d) - Menge: %d - Timer: %s',
						$product->get_name(),
						$product_id,
						$cart_item['quantity'],
						$remaining > 0 ? gmdate( 'i:s', $remaining ) : 'Abgelaufen'
					);
				}
			}
		}
		
		// Context: Shop/Archive
		elseif ( is_shop() || is_product_category() || is_product_tag() ) {
			$debug_data['Kontext'] = 'Shop/Archiv';
			$debug_data['Customer ID'] = $customer_id;
			
			$all_reservations = $this->db->get_all_reservations();
			$debug_data['Aktive Reservierungen'] = count( $all_reservations );
			
			if ( $customer_id ) {
				$customer_reservations = $this->db->get_reserved_products_by_customer( $customer_id );
				$debug_data['Eigene Reservierungen'] = count( $customer_reservations );
			}
		}
		
		// Context: Checkout
		elseif ( is_checkout() && WC()->cart ) {
			$debug_data['Kontext'] = 'Checkout';
			$debug_data['Customer ID'] = $customer_id;
			
			$cart_items = WC()->cart->get_cart();
			$debug_data['Artikel'] = count( $cart_items );
			
			$customer_reservations = $this->db->get_reserved_products_by_customer( $customer_id );
			$debug_data['Reservierungen'] = count( $customer_reservations );
			
			$time_remaining = AS_CAI_Reservation_Session::get_time_remaining( $customer_id );
			$debug_data['Kürzeste Ablaufzeit'] = $time_remaining > 0 ? gmdate( 'i:s', $time_remaining ) : 'Abgelaufen';
		}
		
		if ( empty( $debug_data ) ) {
			return;
		}
		
		// Render Debug Box
		?>
		<div class="as-cai-frontend-debug" id="as-cai-frontend-debug">
			<div class="as-cai-debug-header">
				<strong>🐛 AS-CAI Debug (v<?php echo esc_html( AS_CAI_VERSION ); ?>)</strong>
				<button class="as-cai-debug-toggle" onclick="document.getElementById('as-cai-frontend-debug').style.display='none';">×</button>
			</div>
			<div class="as-cai-debug-content">
				<?php $this->render_debug_array( $debug_data ); ?>
			</div>
		</div>
		
		<style>
		.as-cai-frontend-debug {
			position: fixed;
			bottom: 20px;
			right: 20px;
			background: #fff;
			border: 2px solid #0073aa;
			border-radius: 5px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.3);
			z-index: 999999;
			max-width: 450px;
			font-size: 12px;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
		}
		.as-cai-debug-header {
			background: #0073aa;
			color: #fff;
			padding: 10px 15px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			border-radius: 3px 3px 0 0;
		}
		.as-cai-debug-toggle {
			background: transparent;
			border: none;
			color: #fff;
			font-size: 24px;
			cursor: pointer;
			line-height: 1;
			padding: 0;
			width: 24px;
			height: 24px;
		}
		.as-cai-debug-toggle:hover {
			background: rgba(255,255,255,0.2);
			border-radius: 3px;
		}
		.as-cai-debug-content {
			padding: 15px;
			max-height: 400px;
			overflow-y: auto;
		}
		.as-cai-debug-content table {
			width: 100%;
			border-collapse: collapse;
		}
		.as-cai-debug-content th,
		.as-cai-debug-content td {
			padding: 6px 8px;
			border: 1px solid #ddd;
			text-align: left;
			vertical-align: top;
		}
		.as-cai-debug-content th {
			background: #f5f5f5;
			font-weight: 600;
			width: 40%;
		}
		.as-cai-debug-content td {
			word-break: break-word;
		}
		.as-cai-debug-content table table {
			margin: 5px 0;
		}
		</style>
		<?php
	}
	
	/**
	 * Helper: Render debug array as table
	 * 
	 * @since 1.3.19
	 * @param array $data Debug data
	 * @param bool $nested Is nested table
	 */
	private function render_debug_array( $data, $nested = false ) {
		echo '<table>';
		foreach ( $data as $key => $value ) {
			echo '<tr>';
			echo '<th>' . esc_html( $key ) . '</th>';
			echo '<td>';
			if ( is_array( $value ) ) {
				$this->render_debug_array( $value, true );
			} else {
				echo esc_html( $value );
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
}
