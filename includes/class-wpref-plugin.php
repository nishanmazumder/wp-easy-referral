<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WPREF_PATH . 'includes/class-wpref-frontend.php';
require_once WPREF_PATH . 'includes/class-wpref-referrals.php';
require_once WPREF_PATH . 'includes/class-wpref-admin.php';
require_once WPREF_PATH . 'includes/class-wpref-user-dashboard.php';

final class WPref_Plugin {

	const ROLE_KEY = 'wpref_referral_user';
	const ROLE_NAME = 'Referral User';

	const OPTION_FORM_IDS = 'wpref_registration_form_ids';
	const OPTION_LOGIN_REDIRECT = 'wpref_login_redirect';
	const OPTION_REGISTER_REDIRECT = 'wpref_register_redirect';

	const META_REFERRAL_ID = 'wpref_referral_id';
	const META_PHONE = 'wpref_phone';
	const META_REFERRED_BY_USER_ID = 'wpref_referred_by_user_id';
	const META_REFERRED_BY_CODE = 'wpref_referred_by_code';
	const META_REFERRALS_COUNT = 'wpref_referrals_count';
	const META_DISCOUNT_CREDITS = 'wpref_discount_credits';
	const META_REFERRAL_PROCESSED = 'wpref_referral_processed';

	/**
	 * @var WPref_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var WPref_Frontend
	 */
	private $frontend;

	/**
	 * @var WPref_Referrals
	 */
	private $referrals;

	/**
	 * @var WPref_Admin
	 */
	private $admin;

	/**
	 * @var WPref_User_Dashboard
	 */
	private $user_dashboard;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		register_activation_hook( WPREF_FILE, array( __CLASS__, 'activate' ) );
		register_uninstall_hook( WPREF_FILE, array( __CLASS__, 'uninstall' ) );

		// add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		$this->frontend       = new WPref_Frontend();
		$this->referrals      = new WPref_Referrals();
		$this->admin          = new WPref_Admin();
		$this->user_dashboard = new WPref_User_Dashboard();
	}

}