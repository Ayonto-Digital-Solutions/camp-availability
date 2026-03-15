<?php
/**
 * Cart Countdown Timer Display
 *
 * @package AS_Camp_Availability_Integration
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AS_CAI_Cart_Countdown {
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( get_option( 'as_cai_show_cart_timer', 'yes' ) !== 'yes' ) {
			return;
		}

		add_action( 'woocommerce_before_cart', array( $this, 'display_countdown' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts() {
		if ( ! is_cart() && ! is_checkout() ) { return; }

		wp_enqueue_style(
			'as-cai-cart',
			AS_CAI_PLUGIN_URL . 'assets/css/as-cai-cart.css',
			array(),
			AS_CAI_VERSION
		);

		wp_enqueue_script(
			'as-cai-cart-timer',
			AS_CAI_PLUGIN_URL . 'assets/js/as-cai-cart-timer.js',
			array( 'jquery' ),
			AS_CAI_VERSION,
			true
		);

		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		$time_remaining = AS_CAI_Reservation_Session::get_time_remaining( $customer_id );

		wp_localize_script(
			'as-cai-cart-timer',
			'asCaiCart',
			array(
				'timeRemaining' => $time_remaining,
				'warningThreshold' => (int) get_option( 'as_cai_warning_threshold', 1 ) * 60,
				'i18n' => array(
					'timeRemaining' => __( 'Time remaining:', 'as-camp-availability-integration' ),
					'reservationExpired' => __( 'Your reservation has expired. Please refresh the page.', 'as-camp-availability-integration' ),
				),
			)
		);
	}

	public function display_countdown() {
		$customer_id = AS_CAI_Reservation_Session::get_customer_id();
		if ( ! $customer_id ) { return; }

		$time_remaining = AS_CAI_Reservation_Session::get_time_remaining( $customer_id );
		if ( $time_remaining <= 0 ) { return; }

		$style = get_option( 'as_cai_cart_timer_style', 'full' );

		?>
		<div class="as-cai-cart-countdown as-cai-countdown-<?php echo esc_attr( $style ); ?>" data-time-remaining="<?php echo esc_attr( $time_remaining ); ?>">
			<div class="as-cai-countdown-inner">
				<i class="fas fa-clock"></i>
				<?php if ( 'full' === $style ) : ?>
					<span class="as-cai-countdown-label"><?php esc_html_e( 'Your reservation expires in:', 'as-camp-availability-integration' ); ?></span>
				<?php endif; ?>
				<span class="as-cai-countdown-time"></span>
			</div>
			<?php if ( 'progress' === $style ) : ?>
				<div class="as-cai-countdown-progress">
					<div class="as-cai-countdown-progress-bar"></div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
