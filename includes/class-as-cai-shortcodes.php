<?php
/**
 * Shortcodes — Verfügbarkeits-Anzeige für Elementor Loop und andere Kontexte.
 *
 * [as_cai_availability]                      → Badge (Standard), aktuelles Produkt
 * [as_cai_availability product_id="123"]     → Spezifisches Produkt
 * [as_cai_availability display="badge"]      → Farbiger Badge
 * [as_cai_availability display="bar"]        → Mini-Progress-Bar
 * [as_cai_availability display="text"]       → Nur Text
 * [as_cai_availability display="count"]      → Nur Zahl
 *
 * @package AS_Camp_Availability_Integration
 * @since   1.3.78
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AS_CAI_Shortcodes {

	/** @var AS_CAI_Shortcodes|null */
	private static $instance = null;

	/** @var bool Track if CSS has been enqueued */
	private static $css_enqueued = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'as_cai_availability', array( $this, 'shortcode_handler' ) );

		// AJAX for admin builder preview.
		add_action( 'wp_ajax_as_cai_shortcode_preview', array( $this, 'ajax_shortcode_preview' ) );
	}

	/**
	 * Shortcode handler: [as_cai_availability]
	 */
	public function shortcode_handler( $atts ) {
		$atts = shortcode_atts( array(
			'product_id' => 0,
			'display'    => 'badge',
		), $atts, 'as_cai_availability' );

		$product_id = absint( $atts['product_id'] );

		// Fallback: Aktuelles Produkt im Loop.
		if ( ! $product_id ) {
			global $product;
			if ( $product && is_object( $product ) && method_exists( $product, 'get_id' ) ) {
				$product_id = $product->get_id();
			} elseif ( get_the_ID() ) {
				$product_id = get_the_ID();
			}
		}

		if ( ! $product_id ) {
			return '';
		}

		$data = AS_CAI_Status_Display::get_detailed_availability_status( $product_id );
		if ( ! $data ) {
			return '';
		}

		// Enqueue CSS einmalig.
		if ( ! self::$css_enqueued ) {
			self::enqueue_shortcode_css();
			self::$css_enqueued = true;
		}

		return self::render_output( $data, $atts['display'] );
	}

	/**
	 * Render the shortcode output.
	 *
	 * @param array  $data    Status data from get_detailed_availability_status()
	 * @param string $display Display mode: badge, bar, text, count
	 * @return string HTML output
	 */
	public static function render_output( $data, $display = 'badge' ) {
		$status    = $data['status'];
		$label     = $data['label'] ?? 'Einheiten';
		$available = $data['available'];
		$total     = $data['total'];
		$pct       = $data['percent_free'];

		$status_colors = array(
			'available'     => '#22c55e',
			'limited'       => '#f59e0b',
			'critical'      => '#ef4444',
			'reserved_full' => '#8b5cf6',
			'sold_out'      => '#dc2626',
		);
		$color = isset( $status_colors[ $status ] ) ? $status_colors[ $status ] : '#666';

		$status_icons = array(
			'available'     => '🟢',
			'limited'       => '🟡',
			'critical'      => '🔴',
			'reserved_full' => '🔒',
			'sold_out'      => '🔴',
		);
		$icon = isset( $status_icons[ $status ] ) ? $status_icons[ $status ] : '⚪';

		switch ( $display ) {
			case 'count':
				return '<span class="as-cai-sc as-cai-sc-count" style="color:' . esc_attr( $color ) . ';">' . esc_html( $available ) . '</span>';

			case 'text':
				if ( 'sold_out' === $status ) {
					return '<span class="as-cai-sc as-cai-sc-text" style="color:' . esc_attr( $color ) . ';">Ausgebucht</span>';
				}
				return '<span class="as-cai-sc as-cai-sc-text">' . esc_html( $available ) . ' von ' . esc_html( $total ) . ' ' . esc_html( $label ) . ' verfügbar</span>';

			case 'bar':
				$html  = '<div class="as-cai-sc as-cai-sc-bar">';
				$html .= '<div class="as-cai-sc-bar-track">';
				$html .= '<div class="as-cai-sc-bar-fill" style="width:' . esc_attr( $pct ) . '%;background:' . esc_attr( $color ) . ';"></div>';
				$html .= '</div>';
				if ( 'sold_out' === $status ) {
					$html .= '<span class="as-cai-sc-bar-label" style="color:' . esc_attr( $color ) . ';">Ausgebucht</span>';
				} else {
					$html .= '<span class="as-cai-sc-bar-label">' . esc_html( round( $pct ) ) . '% verfügbar (' . esc_html( $available ) . '/' . esc_html( $total ) . ')</span>';
				}
				$html .= '</div>';
				return $html;

			case 'badge':
			default:
				$html = '<span class="as-cai-sc as-cai-sc-badge as-cai-sc-status-' . esc_attr( $status ) . '">';
				if ( 'sold_out' === $status ) {
					$html .= $icon . ' Ausgebucht';
				} else {
					$html .= $icon . ' ' . esc_html( $available ) . ' von ' . esc_html( $total ) . ' ' . esc_html( $label );
				}
				$html .= '</span>';
				return $html;
		}
	}

	/**
	 * Enqueue inline CSS for shortcode output.
	 */
	private static function enqueue_shortcode_css() {
		$css = '
		.as-cai-sc { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
		.as-cai-sc-badge {
			display: inline-flex; align-items: center; gap: 6px;
			padding: 4px 10px; border-radius: 20px; font-size: 13px; font-weight: 600;
			line-height: 1.4; white-space: nowrap;
		}
		.as-cai-sc-status-available { background: #f0fdf4; color: #166534; }
		.as-cai-sc-status-limited { background: #fffbeb; color: #92400e; }
		.as-cai-sc-status-critical { background: #fef2f2; color: #991b1b; }
		.as-cai-sc-status-reserved_full { background: #faf5ff; color: #6b21a8; }
		.as-cai-sc-status-sold_out { background: #fef2f2; color: #991b1b; }
		.as-cai-sc-text { font-size: 14px; font-weight: 500; }
		.as-cai-sc-count { font-size: 18px; font-weight: 700; }
		.as-cai-sc-bar { display: flex; flex-direction: column; gap: 4px; width: 100%; }
		.as-cai-sc-bar-track {
			width: 100%; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden;
		}
		.as-cai-sc-bar-fill {
			height: 100%; border-radius: 3px; transition: width 0.5s ease;
		}
		.as-cai-sc-bar-label { font-size: 12px; color: #6b7280; }
		';
		wp_register_style( 'as-cai-shortcode-inline', false );
		wp_enqueue_style( 'as-cai-shortcode-inline' );
		wp_add_inline_style( 'as-cai-shortcode-inline', $css );
	}

	/**
	 * AJAX handler: Live preview for admin builder.
	 */
	public function ajax_shortcode_preview() {
		check_ajax_referer( 'as_cai_shortcode_builder', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Nicht autorisiert' );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$display    = isset( $_POST['display'] ) ? sanitize_text_field( $_POST['display'] ) : 'badge';

		// Simulation mode.
		if ( ! empty( $_POST['simulate'] ) ) {
			$data = array(
				'status'       => sanitize_text_field( $_POST['sim_status'] ?? 'available' ),
				'total'        => absint( $_POST['sim_total'] ?? 45 ),
				'available'    => absint( $_POST['sim_available'] ?? 20 ),
				'sold'         => absint( $_POST['sim_sold'] ?? 23 ),
				'reserved'     => absint( $_POST['sim_reserved'] ?? 2 ),
				'percent_free' => 0,
				'label'        => sanitize_text_field( $_POST['sim_label'] ?? 'Parzellen' ),
			);
			$data['percent_free'] = $data['total'] > 0 ? round( ( $data['available'] / $data['total'] ) * 100, 1 ) : 0;
		} else {
			$data = $product_id ? AS_CAI_Status_Display::get_detailed_availability_status( $product_id ) : null;
		}

		if ( ! $data ) {
			wp_send_json_error( 'Keine Daten verfügbar' );
		}

		$html = self::render_output( $data, $display );

		// Include inline CSS for preview.
		$css = '<style>
		.as-cai-sc { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
		.as-cai-sc-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 13px; font-weight: 600; line-height: 1.4; white-space: nowrap; }
		.as-cai-sc-status-available { background: #f0fdf4; color: #166534; }
		.as-cai-sc-status-limited { background: #fffbeb; color: #92400e; }
		.as-cai-sc-status-critical { background: #fef2f2; color: #991b1b; }
		.as-cai-sc-status-reserved_full { background: #faf5ff; color: #6b21a8; }
		.as-cai-sc-status-sold_out { background: #fef2f2; color: #991b1b; }
		.as-cai-sc-text { font-size: 14px; font-weight: 500; }
		.as-cai-sc-count { font-size: 18px; font-weight: 700; }
		.as-cai-sc-bar { display: flex; flex-direction: column; gap: 4px; width: 100%; }
		.as-cai-sc-bar-track { width: 100%; height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; }
		.as-cai-sc-bar-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
		.as-cai-sc-bar-label { font-size: 12px; color: #6b7280; }
		</style>';

		wp_send_json_success( array(
			'html'      => $css . $html,
			'shortcode' => '[as_cai_availability' . ( $product_id ? ' product_id="' . $product_id . '"' : '' ) . ( 'badge' !== $display ? ' display="' . $display . '"' : '' ) . ']',
		) );
	}
}
