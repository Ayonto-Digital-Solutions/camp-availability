<?php
/**
 * Order Confirmation Shortcode Class
 *
 * Displays order details on confirmation page since WooCommerce can't process
 * seat planner and variation data properly.
 *
 * Usage: [as_cai_order_confirmation]
 *
 * @package AS_Camp_Availability_Integration
 * @since 1.3.42
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order Confirmation Shortcode functionality.
 *
 * @since 1.3.42
 */
class AS_CAI_Order_Confirmation {

	/**
	 * Instance of this class.
	 *
	 * @var AS_CAI_Order_Confirmation|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return AS_CAI_Order_Confirmation
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
		add_shortcode( 'as_cai_order_confirmation', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue frontend styles.
	 */
	public function enqueue_styles() {
		if ( ! is_page() && ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		wp_enqueue_style(
			'as-cai-order-confirmation',
			AS_CAI_PLUGIN_URL . 'assets/css/order-confirmation.css',
			array(),
			AS_CAI_VERSION
		);
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'order_id' => 0,
			'title'    => __( 'Buchungsübersicht', 'as-camp-availability-integration' ),
			'show_customer_details' => 'yes',
		), $atts );

		// Get order ID from URL parameter or shortcode attribute
		$order_id = $atts['order_id'];
		
		if ( ! $order_id ) {
			// Try to get from URL (order-received endpoint)
			$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		}

		if ( ! $order_id ) {
			// Try from query var
			$order_id = get_query_var( 'order-received' );
		}

		if ( ! $order_id ) {
			return '<div class="as-cai-order-error">' . esc_html__( 'Keine Buchung gefunden.', 'as-camp-availability-integration' ) . '</div>';
		}

		// Get order
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return '<div class="as-cai-order-error">' . esc_html__( 'Buchung konnte nicht geladen werden.', 'as-camp-availability-integration' ) . '</div>';
		}

		// Skip refunds (HPOS compatibility)
		if ( $order->get_type() === 'shop_order_refund' ) {
			return '<div class="as-cai-order-error">' . esc_html__( 'Rückerstattungen können nicht angezeigt werden.', 'as-camp-availability-integration' ) . '</div>';
		}

		// SECURITY FIX v1.3.55: Order key is REQUIRED (prevent IDOR vulnerability)
		$order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		
		// Order key is mandatory
		if ( empty( $order_key ) ) {
			return '<div class="as-cai-order-error">' . esc_html__( 'Buchungsschlüssel fehlt.', 'as-camp-availability-integration' ) . '</div>';
		}
		
		// Verify order key matches
		if ( ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return '<div class="as-cai-order-error">' . esc_html__( 'Ungültiger Buchungsschlüssel.', 'as-camp-availability-integration' ) . '</div>';
		}
		
		// Additional user ownership check for logged-in users
		if ( is_user_logged_in() ) {
			$current_user_id = get_current_user_id();
			$order_user_id = $order->get_user_id();
			
			// If order belongs to a user, verify ownership (unless admin)
			if ( $order_user_id && $order_user_id !== $current_user_id ) {
				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					return '<div class="as-cai-order-error">' . esc_html__( 'Sie haben keine Berechtigung, diese Bestellung anzuzeigen.', 'as-camp-availability-integration' ) . '</div>';
				}
			}
		}

		ob_start();
		?>
		<div class="as-cai-order-confirmation">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h2 class="as-cai-order-title"><?php echo esc_html( $atts['title'] ); ?></h2>
			<?php endif; ?>

			<!-- Order Info -->
			<div class="as-cai-order-header">
				<div class="as-cai-order-number">
					<strong><?php esc_html_e( 'Buchungsnummer:', 'as-camp-availability-integration' ); ?></strong>
					<span>#<?php echo esc_html( $order->get_order_number() ); ?></span>
				</div>
				<div class="as-cai-order-date">
					<strong><?php esc_html_e( 'Buchungsdatum:', 'as-camp-availability-integration' ); ?></strong>
					<span><?php echo esc_html( $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) ); ?></span>
				</div>
				<div class="as-cai-order-status">
					<strong><?php esc_html_e( 'Buchung:', 'as-camp-availability-integration' ); ?></strong>
					<span class="as-cai-status as-cai-status-<?php echo esc_attr( sanitize_title( $order->get_status() ) ); ?>">
						<?php echo esc_html( $this->get_order_status_label( $order->get_status() ) ); ?>
					</span>
				</div>
				<div class="as-cai-payment-status">
					<strong><?php esc_html_e( 'Zahlung:', 'as-camp-availability-integration' ); ?></strong>
					<span class="as-cai-status as-cai-status-<?php echo esc_attr( $order->is_paid() ? 'completed' : 'pending' ); ?>">
						<?php echo esc_html( $order->is_paid() ? __( 'Abgeschlossen', 'as-camp-availability-integration' ) : __( 'Ausstehend', 'as-camp-availability-integration' ) ); ?>
					</span>
				</div>
			</div>

			<!-- Customer Details -->
			<?php if ( 'yes' === $atts['show_customer_details'] ) : ?>
				<div class="as-cai-customer-details">
					<h3><?php esc_html_e( 'Deine Daten', 'as-camp-availability-integration' ); ?></h3>
					<div class="as-cai-customer-grid">
						<div class="as-cai-customer-item">
							<strong><?php esc_html_e( 'Name:', 'as-camp-availability-integration' ); ?></strong>
							<span>
							<?php 
							// HPOS compatible customer name
							if ( method_exists( $order, 'get_formatted_billing_full_name' ) ) {
								echo esc_html( $order->get_formatted_billing_full_name() );
							} else {
								$first_name = $order->get_billing_first_name();
								$last_name = $order->get_billing_last_name();
								$customer_name = trim( $first_name . ' ' . $last_name );
								echo esc_html( $customer_name ? $customer_name : __( 'Gast', 'as-camp-availability-integration' ) );
							}
							?>
							</span>
						</div>
						<div class="as-cai-customer-item">
							<strong><?php esc_html_e( 'E-Mail:', 'as-camp-availability-integration' ); ?></strong>
							<span>
								<a href="mailto:<?php echo esc_attr( $order->get_billing_email() ); ?>">
									<?php echo esc_html( $order->get_billing_email() ); ?>
								</a>
							</span>
						</div>
						<?php if ( $order->get_billing_phone() ) : ?>
							<div class="as-cai-customer-item">
								<strong><?php esc_html_e( 'Telefon:', 'as-camp-availability-integration' ); ?></strong>
								<span><?php echo esc_html( $order->get_billing_phone() ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Order Items -->
			<div class="as-cai-order-items">
				<h3><?php esc_html_e( 'Details', 'as-camp-availability-integration' ); ?></h3>
				
				<?php 
				$items_by_category = $this->group_items_by_category( $order );
				
				if ( empty( $items_by_category ) ) : ?>
					<p><?php esc_html_e( 'Keine Artikel gefunden.', 'as-camp-availability-integration' ); ?></p>
				<?php else : ?>
					<?php foreach ( $items_by_category as $category_name => $items ) : ?>
						<div class="as-cai-category-group">
							<h4 class="as-cai-category-name"><?php echo esc_html( $category_name ); ?></h4>
							
							<div class="as-cai-items-grid">
								<?php foreach ( $items as $item ) : ?>
									<div class="as-cai-item-card">
										<!-- Item Header -->
										<div class="as-cai-item-header">
											<div class="as-cai-item-name">
												<span class="as-cai-item-label"><?php esc_html_e( 'Typ', 'as-camp-availability-integration' ); ?></span>
												<strong><?php echo esc_html( $item['name'] ); ?></strong>
												<?php if ( ! empty( $item['sku'] ) ) : ?>
													<small class="as-cai-sku">SKU: <?php echo esc_html( $item['sku'] ); ?></small>
												<?php endif; ?>
											</div>
											<div class="as-cai-item-price">
												<?php echo wp_kses_post( $item['total'] ); ?>
											</div>
										</div>
										
										<!-- Parzelle (Variation + Seats) -->
										<?php if ( ! empty( $item['variation'] ) || ! empty( $item['seats'] ) ) : ?>
											<div class="as-cai-item-details">
												<span class="as-cai-detail-label"><?php esc_html_e( 'Parzelle', 'as-camp-availability-integration' ); ?></span>
												<div class="as-cai-detail-content">
													<?php if ( ! empty( $item['variation'] ) ) : ?>
														<div class="as-cai-variation-inline">
															<?php 
															$variations = array();
															foreach ( $item['variation'] as $key => $value ) {
																$variations[] = '<strong>' . esc_html( $key ) . ':</strong> ' . esc_html( $value );
															}
															echo implode( ' <span class="as-cai-separator">•</span> ', $variations );
															?>
														</div>
													<?php endif; ?>
													<?php if ( ! empty( $item['seats'] ) ) : ?>
														<div class="as-cai-seats">
															<?php foreach ( $item['seats'] as $seat ) : ?>
																<span class="as-cai-seat-badge"><?php echo esc_html( $seat ); ?></span>
															<?php endforeach; ?>
														</div>
													<?php endif; ?>
												</div>
											</div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<!-- Order Totals -->
			<div class="as-cai-order-totals">
				<div class="as-cai-totals-card">
					<div class="as-cai-total-row as-cai-subtotal">
						<span><?php esc_html_e( 'Zwischensumme:', 'as-camp-availability-integration' ); ?></span>
						<strong><?php echo wp_kses_post( wc_price( $order->get_subtotal() ) ); ?></strong>
					</div>
					
					<?php if ( $order->get_total_tax() > 0 ) : ?>
						<div class="as-cai-total-row as-cai-tax">
							<span><?php esc_html_e( 'MwSt.:', 'as-camp-availability-integration' ); ?></span>
							<strong><?php echo wp_kses_post( wc_price( $order->get_total_tax() ) ); ?></strong>
						</div>
					<?php endif; ?>
					
					<?php if ( $order->get_shipping_total() > 0 ) : ?>
						<div class="as-cai-total-row as-cai-shipping">
							<span><?php esc_html_e( 'Versand:', 'as-camp-availability-integration' ); ?></span>
							<strong><?php echo wp_kses_post( wc_price( $order->get_shipping_total() ) ); ?></strong>
						</div>
					<?php endif; ?>
					
					<?php if ( $order->get_total_discount() > 0 ) : ?>
						<div class="as-cai-total-row as-cai-discount">
							<span><?php esc_html_e( 'Rabatt:', 'as-camp-availability-integration' ); ?></span>
							<strong>-<?php echo wp_kses_post( wc_price( $order->get_total_discount() ) ); ?></strong>
						</div>
					<?php endif; ?>
					
					<div class="as-cai-total-row as-cai-total">
						<span><?php esc_html_e( 'Gesamtsumme:', 'as-camp-availability-integration' ); ?></span>
						<strong><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></strong>
					</div>
				</div>
			</div>

			<!-- Payment Method -->
			<div class="as-cai-payment-method">
				<strong><?php esc_html_e( 'Zahlungsmethode:', 'as-camp-availability-integration' ); ?></strong>
				<span><?php echo esc_html( $order->get_payment_method_title() ); ?></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Group order items by category.
	 *
	 * @param WC_Order $order Order object.
	 * @return array Items grouped by category.
	 */
	private function group_items_by_category( $order ) {
		$grouped = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			
			if ( ! $product ) {
				continue;
			}

			// Get product categories
			$categories = wp_get_post_terms( $product->get_id(), 'product_cat' );
			$category_name = ! empty( $categories ) ? $categories[0]->name : __( 'Allgemein', 'as-camp-availability-integration' );

			// Get variation attributes
			$variation_data = array();
			if ( $item->get_variation_id() ) {
				$variation = wc_get_product( $item->get_variation_id() );
				if ( $variation ) {
					$attributes = $variation->get_attributes();
					foreach ( $attributes as $key => $value ) {
						$taxonomy = str_replace( 'pa_', '', $key );
						$term = get_term_by( 'slug', $value, 'pa_' . $taxonomy );
						$label = wc_attribute_label( $key );
						$variation_data[ $label ] = $term ? $term->name : $value;
					}
				}
			}

			// Get seat planner data - check multiple meta keys and handle serialized data
			$seats = array();
			$meta_keys = array(
				'_stachethemes_seat_planner_data',
				'seat_data',
				'_seat_data',
			);
			
			foreach ( $meta_keys as $meta_key ) {
				$seat_meta = $item->get_meta( $meta_key, true );
				
				if ( ! empty( $seat_meta ) ) {
					// SECURITY FIX v1.3.55: WooCommerce get_meta() returns already deserialized data
					// NEVER use maybe_unserialize() on user-controllable data (RCE vulnerability)
					
					// Handle JSON string (fallback for custom implementations)
					if ( is_string( $seat_meta ) ) {
						$decoded = json_decode( $seat_meta, true );
						if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
							$seat_meta = (object) $decoded;
						}
					}
					
					// Handle stdClass object (from Stachethemes Seat Planner)
					if ( is_object( $seat_meta ) ) {
						if ( isset( $seat_meta->label ) ) {
							$seats[] = $seat_meta->label;
						} elseif ( isset( $seat_meta->seat ) ) {
							$seats[] = $seat_meta->seat;
						} elseif ( isset( $seat_meta->name ) ) {
							$seats[] = $seat_meta->name;
						} elseif ( isset( $seat_meta->seatId ) ) {
							$seats[] = $seat_meta->seatId;
						}
					}
					// Handle array of seat data
					elseif ( is_array( $seat_meta ) ) {
						foreach ( $seat_meta as $seat_data ) {
							if ( is_object( $seat_data ) ) {
								// stdClass object in array
								if ( isset( $seat_data->label ) ) {
									$seats[] = $seat_data->label;
								} elseif ( isset( $seat_data->seat ) ) {
									$seats[] = $seat_data->seat;
								} elseif ( isset( $seat_data->name ) ) {
									$seats[] = $seat_data->name;
								} elseif ( isset( $seat_data->seatId ) ) {
									$seats[] = $seat_data->seatId;
								}
							} elseif ( is_array( $seat_data ) ) {
								// Array in array
								if ( isset( $seat_data['label'] ) ) {
									$seats[] = $seat_data['label'];
								} elseif ( isset( $seat_data['seat'] ) ) {
									$seats[] = $seat_data['seat'];
								} elseif ( isset( $seat_data['name'] ) ) {
									$seats[] = $seat_data['name'];
								}
							} elseif ( is_string( $seat_data ) ) {
								// String value
								$seats[] = $seat_data;
							}
						}
					}
				}
			}
			
			// Remove duplicates
			$seats = array_unique( $seats );

			// Build item data
			$item_data = array(
				'name'      => $item->get_name(),
				'sku'       => $product->get_sku(),
				'variation' => $variation_data,
				'seats'     => $seats,
				'quantity'  => $item->get_quantity(),
				'total'     => wc_price( $item->get_total() ),
			);

			if ( ! isset( $grouped[ $category_name ] ) ) {
				$grouped[ $category_name ] = array();
			}

			$grouped[ $category_name ][] = $item_data;
		}

		return $grouped;
	}

	/**
	 * Get custom order status label.
	 *
	 * @param string $status Order status.
	 * @return string Translated status label.
	 * @since 1.3.50
	 */
	private function get_order_status_label( $status ) {
		$labels = array(
			'pending'    => __( 'Ausstehend', 'as-camp-availability-integration' ),
			'processing' => __( 'In Bearbeitung', 'as-camp-availability-integration' ),
			'completed'  => __( 'Erfolgreich', 'as-camp-availability-integration' ),
			'on-hold'    => __( 'In Wartestellung', 'as-camp-availability-integration' ),
			'cancelled'  => __( 'Storniert', 'as-camp-availability-integration' ),
			'refunded'   => __( 'Erstattet', 'as-camp-availability-integration' ),
			'failed'     => __( 'Fehlgeschlagen', 'as-camp-availability-integration' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : wc_get_order_status_name( $status );
	}
}
