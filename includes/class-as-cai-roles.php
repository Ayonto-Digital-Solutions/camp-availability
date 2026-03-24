<?php
/**
 * Custom Roles — Camp Manager Role.
 *
 * @package AS_Camp_Availability_Integration
 * @since   1.3.78
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AS_CAI_Roles {

	/** @var AS_CAI_Roles|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Check if role needs to be installed/updated on admin_init.
		add_action( 'admin_init', array( $this, 'maybe_install_role' ) );

		// Patch Stachethemes menu caps so Camp Manager can access Seat Planner.
		add_action( 'admin_menu', array( $this, 'patch_stachethemes_menu_caps' ), 999 );
	}

	/**
	 * Install or update the Camp Manager role if needed.
	 */
	public function maybe_install_role() {
		$installed_version = get_option( 'as_cai_role_version', '' );
		if ( $installed_version !== AS_CAI_VERSION ) {
			$this->install();
			update_option( 'as_cai_role_version', AS_CAI_VERSION );
		}
	}

	/**
	 * Create or update the Camp Manager role.
	 */
	public function install() {
		$caps = self::get_camp_manager_caps();

		// Remove existing role first (to update caps).
		remove_role( 'camp_manager' );

		add_role( 'camp_manager', 'Camp Manager', $caps );
	}

	/**
	 * Get all capabilities for the Camp Manager role.
	 *
	 * @return array
	 */
	public static function get_camp_manager_caps() {
		return array(
			// WordPress Basis.
			'read'                   => true,
			'upload_files'           => true,
			'edit_posts'             => true,
			'edit_published_posts'   => true,
			'delete_posts'           => true,
			'publish_posts'          => true,

			// WooCommerce — Shop Management.
			'manage_woocommerce'          => true,
			'view_woocommerce_reports'    => true,

			// WooCommerce — Orders.
			'edit_shop_orders'            => true,
			'read_shop_orders'            => true,
			'delete_shop_orders'          => true,
			'edit_others_shop_orders'     => true,
			'publish_shop_orders'         => true,
			'edit_published_shop_orders'  => true,
			'delete_published_shop_orders' => true,

			// WooCommerce — Products.
			'edit_products'               => true,
			'read_products'               => true,
			'delete_products'             => true,
			'publish_products'            => true,
			'edit_others_products'        => true,
			'edit_published_products'     => true,
			'delete_published_products'   => true,

			// WooCommerce — Coupons.
			'edit_shop_coupons'           => true,
			'read_shop_coupons'           => true,
			'publish_shop_coupons'        => true,
			'edit_others_shop_coupons'    => true,
			'edit_published_shop_coupons' => true,
			'delete_shop_coupons'         => true,
			'delete_published_shop_coupons' => true,

			// WooCommerce — Customers.
			'list_users'                  => true,
			'edit_users'                  => false,

			// Stachethemes Seat Planner + Unser Plugin:
			// manage_woocommerce deckt beides ab.
		);
	}

	/**
	 * Patch Stachethemes Seat Planner menu capabilities.
	 *
	 * Stachethemes hardcodes 'manage_options' for its admin menu,
	 * but all AJAX handlers use 'manage_woocommerce'. We patch the
	 * menu globals so Camp Manager (with manage_woocommerce) can
	 * access the Seat Planner pages.
	 *
	 * @since 1.3.79
	 */
	public function patch_stachethemes_menu_caps() {
		global $menu, $submenu;

		// Hauptmenü: Stachethemes Seat Planner.
		foreach ( $menu as $position => $item ) {
			if ( isset( $item[2] ) && 'stachesepl' === $item[2] ) {
				$menu[ $position ][1] = 'manage_woocommerce';
				break;
			}
		}

		// Submenüs.
		if ( isset( $submenu['stachesepl'] ) ) {
			foreach ( $submenu['stachesepl'] as $index => $item ) {
				$submenu['stachesepl'][ $index ][1] = 'manage_woocommerce';
			}
		}
	}
}
