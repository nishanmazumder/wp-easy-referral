<?php
/**
 * WP Easy Referral
 *
 * @package           wp-easy-referral
 * @author            Nishan
 * @copyright         2026 Nishan
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WP Easy Referral
 * Plugin URI:        https://mmw.diviaccessories.com/
 * Description:       This database add-on for WPForms ensures all your entries are securely stored and easily accessible
 * Version:           1.1.2
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Nishan
 * Author URI:        https://github.com/nishanmazumder
 * Text Domain:       wp-easy-referral
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Referral_Auth_System {

	const VERSION = '1.1.0';

	const ROLE_KEY  = 'referral_user';
	const ROLE_NAME = 'Referral User';

	const OPTION_REGISTER_FORM_IDS = 'ras_register_form_ids';

	const META_REFERRAL_ID         = 'ras_referral_id';
	const META_PHONE               = 'ras_phone';
	const META_REFERRED_BY_USER_ID = 'ras_referred_by_user_id';
	const META_REFERRED_BY_CODE    = 'ras_referred_by_code';
	const META_REFERRALS_COUNT     = 'ras_referrals_count';
	const META_DISCOUNT_CREDITS    = 'ras_discount_credits';
	const META_REFERRAL_PROCESSED  = 'ras_referral_processed';

	const DEFAULT_LOGIN_REDIRECT    = '/user-referral-dashboard/';
	const DEFAULT_REGISTER_REDIRECT = '/thank-you/';

	/**
	 * Constructor.
	 */
	public function __construct() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );

		add_shortcode( 'ras_auth_tabs', array( $this, 'render_auth_tabs' ) );
		add_shortcode( 'ras_user_dashboard', array( $this, 'render_user_dashboard' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		add_filter( 'login_redirect', array( $this, 'filter_login_redirect' ), 10, 3 );
		add_action( 'user_register', array( $this, 'handle_user_register' ), 20, 1 );

		add_action( 'wpforms_process', array( $this, 'validate_referral_code_field' ), 20, 3 );
		add_action( 'wpforms_process_complete', array( $this, 'handle_wpforms_registration_complete' ), 20, 4 );

		add_action( 'show_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );

		add_filter( 'manage_users_columns', array( $this, 'add_users_columns' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_users_custom_column' ), 10, 3 );

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		add_role(
			self::ROLE_KEY,
			self::ROLE_NAME,
			array(
				'read' => true,
			)
		);

		if ( false === get_option( self::OPTION_REGISTER_FORM_IDS, false ) ) {
			add_option( self::OPTION_REGISTER_FORM_IDS, array(), '', false );
		}
	}

	/**
	 * Register assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'ras-auth-system', false, array(), self::VERSION );
		wp_register_script( 'ras-auth-system', false, array(), self::VERSION, true );

		wp_add_inline_style( 'ras-auth-system', $this->get_css() );
		wp_add_inline_script( 'ras-auth-system', $this->get_js() );
	}

	/**
	 * Render auth tabs.
	 *
	 * Usage:
	 * [ras_auth_tabs register_form_id="123" login_redirect="/user-referral-dashboard/" register_redirect="/thank-you/"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_auth_tabs( $atts ) {
		$atts = shortcode_atts(
			array(
				'register_form_id'  => 0,
				'login_title'       => __( 'Login', 'referral-auth-system' ),
				'register_title'    => __( 'Register', 'referral-auth-system' ),
				'login_redirect'    => self::DEFAULT_LOGIN_REDIRECT,
				'register_redirect' => self::DEFAULT_REGISTER_REDIRECT,
				'class'             => '',
				'show_logged_in'    => 'yes',
			),
			$atts,
			'ras_auth_tabs'
		);

		wp_enqueue_style( 'ras-auth-system' );
		wp_enqueue_script( 'ras-auth-system' );

		$register_form_id = absint( $atts['register_form_id'] );

		if ( $register_form_id > 0 ) {
			$this->store_registration_form_id( $register_form_id );
		}

		$active_tab = 'login';

		if ( isset( $_GET['ras_tab'] ) ) {
			$requested_tab = sanitize_key( wp_unslash( $_GET['ras_tab'] ) );

			if ( in_array( $requested_tab, array( 'login', 'register' ), true ) ) {
				$active_tab = $requested_tab;
			}
		}

		if ( is_user_logged_in() ) {
			if ( 'yes' !== strtolower( (string) $atts['show_logged_in'] ) ) {
				return '';
			}

			$current_user = wp_get_current_user();

			ob_start();
			?>
			<div class="ras-card <?php echo esc_attr( $atts['class'] ); ?>">
				<div class="ras-logged-in">
					<h3 class="ras-title"><?php esc_html_e( 'Welcome back', 'referral-auth-system' ); ?></h3>
					<p class="ras-copy">
						<?php
						printf(
							/* translators: %s: user display name. */
							esc_html__( 'You are logged in as %s.', 'referral-auth-system' ),
							'<strong>' . esc_html( $current_user->display_name ? $current_user->display_name : $current_user->user_login ) . '</strong>'
						);
						?>
					</p>
					<div class="ras-actions">
						<a class="ras-btn ras-btn-primary" href="<?php echo esc_url( home_url( $atts['login_redirect'] ) ); ?>">
							<?php esc_html_e( 'Go to dashboard', 'referral-auth-system' ); ?>
						</a>
						<a class="ras-btn ras-btn-secondary" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
							<?php esc_html_e( 'Logout', 'referral-auth-system' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$login_form = wp_login_form(
			array(
				'echo'           => false,
				'redirect'       => home_url( $atts['login_redirect'] ),
				'remember'       => true,
				'form_id'        => 'ras-loginform',
				'id_username'    => 'ras-user-login',
				'id_password'    => 'ras-user-pass',
				'id_remember'    => 'ras-rememberme',
				'id_submit'      => 'ras-submit',
				'label_username' => __( 'Username or Email', 'referral-auth-system' ),
				'label_password' => __( 'Password', 'referral-auth-system' ),
				'label_remember' => __( 'Remember Me', 'referral-auth-system' ),
				'label_log_in'   => __( 'Login', 'referral-auth-system' ),
				'value_remember' => true,
			)
		);

		$register_form = '';

		if ( $register_form_id > 0 ) {
			$register_form = do_shortcode( '[wpforms id="' . $register_form_id . '"]' );
		} else {
			$register_form = '<div class="ras-notice ras-notice-warning">' .
				esc_html__( 'Please set a WPForms form ID in the shortcode, for example: [ras_auth_tabs register_form_id="123"]', 'referral-auth-system' ) .
			'</div>';
		}

		ob_start();
		?>
		<div class="ras-card ras-tabs-wrap <?php echo esc_attr( $atts['class'] ); ?>" data-ras-tabs>
			<div class="ras-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Authentication tabs', 'referral-auth-system' ); ?>">
				<button
					type="button"
					class="ras-tab-btn <?php echo ( 'login' === $active_tab ) ? 'is-active' : ''; ?>"
					data-tab-target="login"
					role="tab"
					aria-selected="<?php echo ( 'login' === $active_tab ) ? 'true' : 'false'; ?>"
					aria-controls="ras-panel-login"
					id="ras-tab-login">
					<?php echo esc_html( $atts['login_title'] ); ?>
				</button>

				<button
					type="button"
					class="ras-tab-btn <?php echo ( 'register' === $active_tab ) ? 'is-active' : ''; ?>"
					data-tab-target="register"
					role="tab"
					aria-selected="<?php echo ( 'register' === $active_tab ) ? 'true' : 'false'; ?>"
					aria-controls="ras-panel-register"
					id="ras-tab-register">
					<?php echo esc_html( $atts['register_title'] ); ?>
				</button>
			</div>

			<div
				class="ras-tab-panel <?php echo ( 'login' === $active_tab ) ? 'is-active' : ''; ?>"
				id="ras-panel-login"
				role="tabpanel"
				aria-labelledby="ras-tab-login">
				<h3 class="ras-title"><?php echo esc_html( $atts['login_title'] ); ?></h3>
				<p class="ras-copy"><?php esc_html_e( 'Access your account below.', 'referral-auth-system' ); ?></p>
				<div class="ras-login-wrap">
					<?php echo $login_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>

			<div
				class="ras-tab-panel <?php echo ( 'register' === $active_tab ) ? 'is-active' : ''; ?>"
				id="ras-panel-register"
				role="tabpanel"
				aria-labelledby="ras-tab-register">
				<h3 class="ras-title"><?php echo esc_html( $atts['register_title'] ); ?></h3>
				<p class="ras-copy"><?php esc_html_e( 'Create your account below. Referral code is optional.', 'referral-auth-system' ); ?></p>
				<div class="ras-register-wrap ras-wpforms-skin">
					<?php echo $register_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render a user dashboard for referral users.
	 *
	 * Usage:
	 * [ras_user_dashboard]
	 *
	 * @return string
	 */
	public function render_user_dashboard() {
		if ( ! is_user_logged_in() ) {
			return '<div class="ras-notice ras-notice-warning">' .
				esc_html__( 'Please log in to view your dashboard.', 'referral-auth-system' ) .
			'</div>';
		}

		$user = wp_get_current_user();

		if ( ! $user instanceof WP_User ) {
			return '';
		}

		$referral_id = (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true );
		$credits     = (int) get_user_meta( $user->ID, self::META_DISCOUNT_CREDITS, true );
		$referrals   = (int) get_user_meta( $user->ID, self::META_REFERRALS_COUNT, true );
		$children    = $this->get_direct_referrals( $user->ID );

		wp_enqueue_style( 'ras-auth-system' );

		ob_start();
		?>
		<div class="ras-card">
			<div class="ras-dashboard">
				<h3 class="ras-title"><?php esc_html_e( 'Referral Dashboard', 'referral-auth-system' ); ?></h3>
				<div class="ras-stats">
					<div class="ras-stat-box">
						<div class="ras-stat-label"><?php esc_html_e( 'Your Referral ID', 'referral-auth-system' ); ?></div>
						<div class="ras-stat-value"><?php echo esc_html( $referral_id ); ?></div>
					</div>
					<div class="ras-stat-box">
						<div class="ras-stat-label"><?php esc_html_e( 'Referral Credits', 'referral-auth-system' ); ?></div>
						<div class="ras-stat-value"><?php echo esc_html( (string) $credits ); ?></div>
					</div>
					<div class="ras-stat-box">
						<div class="ras-stat-label"><?php esc_html_e( 'Successful Referrals', 'referral-auth-system' ); ?></div>
						<div class="ras-stat-value"><?php echo esc_html( (string) $referrals ); ?></div>
					</div>
				</div>

				<h4 class="ras-subtitle"><?php esc_html_e( 'Users You Referred', 'referral-auth-system' ); ?></h4>

				<?php if ( empty( $children ) ) : ?>
					<p class="ras-copy"><?php esc_html_e( 'No referrals yet.', 'referral-auth-system' ); ?></p>
				<?php else : ?>
					<table class="ras-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'referral-auth-system' ); ?></th>
								<th><?php esc_html_e( 'Email', 'referral-auth-system' ); ?></th>
								<th><?php esc_html_e( 'Credits', 'referral-auth-system' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $children as $child ) : ?>
								<tr>
									<td><?php echo esc_html( $child->display_name ); ?></td>
									<td><?php echo esc_html( $child->user_email ); ?></td>
									<td><?php echo esc_html( (string) (int) get_user_meta( $child->ID, self::META_DISCOUNT_CREDITS, true ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Store a registration form ID automatically.
	 *
	 * @param int $form_id Form ID.
	 * @return void
	 */
	private function store_registration_form_id( $form_id ) {
		$form_id = absint( $form_id );

		if ( $form_id <= 0 ) {
			return;
		}

		$form_ids = $this->get_registration_form_ids();

		if ( ! in_array( $form_id, $form_ids, true ) ) {
			$form_ids[] = $form_id;
			update_option( self::OPTION_REGISTER_FORM_IDS, array_values( array_unique( array_map( 'absint', $form_ids ) ) ), false );
		}
	}

	/**
	 * Get stored registration form IDs.
	 *
	 * @return array
	 */
	private function get_registration_form_ids() {
		$form_ids = get_option( self::OPTION_REGISTER_FORM_IDS, array() );

		if ( ! is_array( $form_ids ) ) {
			return array();
		}

		$form_ids = array_map( 'absint', $form_ids );
		$form_ids = array_filter( $form_ids );

		return array_values( array_unique( $form_ids ) );
	}

	/**
	 * Redirect referral users after login.
	 *
	 * @param string           $redirect_to Redirect destination.
	 * @param string           $requested_redirect_to Requested destination.
	 * @param WP_User|WP_Error $user User object or error.
	 * @return string
	 */
	public function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $redirect_to;
		}

		if ( in_array( self::ROLE_KEY, (array) $user->roles, true ) ) {
			if ( ! empty( $requested_redirect_to ) ) {
				return $requested_redirect_to;
			}

			return home_url( self::DEFAULT_LOGIN_REDIRECT );
		}

		return $redirect_to;
	}

	/**
	 * Handle user registration.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function handle_user_register( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return;
		}

		if ( ! in_array( self::ROLE_KEY, (array) $user->roles, true ) ) {
			return;
		}

		$referral_id = get_user_meta( $user_id, self::META_REFERRAL_ID, true );

		if ( empty( $referral_id ) ) {
			update_user_meta( $user_id, self::META_REFERRAL_ID, $this->generate_unique_referral_id() );
		}

		if ( '' === get_user_meta( $user_id, self::META_REFERRALS_COUNT, true ) ) {
			update_user_meta( $user_id, self::META_REFERRALS_COUNT, 0 );
		}

		if ( '' === get_user_meta( $user_id, self::META_DISCOUNT_CREDITS, true ) ) {
			update_user_meta( $user_id, self::META_DISCOUNT_CREDITS, 0 );
		}
	}

	/**
	 * Validate referral code in WPForms before completion.
	 *
	 * @param array $fields Sanitized fields.
	 * @param array $entry Raw entry values.
	 * @param array $form_data Form data.
	 * @return void
	 */
	public function validate_referral_code_field( $fields, $entry, $form_data ) {
		$form_id  = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$form_ids = $this->get_registration_form_ids();

		if ( ! in_array( $form_id, $form_ids, true ) ) {
			return;
		}

		$referral_field = $this->find_field_by_labels(
			$fields,
			array(
				'referral code',
				'referrer code',
				'referral id',
				'referred by',
			)
		);

		if ( empty( $referral_field ) ) {
			return;
		}

		$referral_code = isset( $referral_field['value'] ) ? sanitize_text_field( (string) $referral_field['value'] ) : '';

		if ( '' === $referral_code ) {
			return;
		}

		$referrer_id = $this->get_user_id_by_referral_code( $referral_code );

		if ( $referrer_id > 0 ) {
			return;
		}

		$field_id = isset( $referral_field['id'] ) ? absint( $referral_field['id'] ) : 0;

		if ( $field_id > 0 && function_exists( 'wpforms' ) && isset( wpforms()->process ) ) {
			wpforms()->process->errors[ $form_id ][ $field_id ] = esc_html__( 'Invalid referral code.', 'referral-auth-system' );
		}
	}

	/**
	 * Handle successful WPForms registration.
	 *
	 * @param array $fields Sanitized fields.
	 * @param array $entry Raw entry data.
	 * @param array $form_data Form data.
	 * @param int   $entry_id Entry ID.
	 * @return void
	 */
	public function handle_wpforms_registration_complete( $fields, $entry, $form_data, $entry_id ) {
		$form_id  = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$form_ids = $this->get_registration_form_ids();

		if ( ! in_array( $form_id, $form_ids, true ) ) {
			return;
		}

		$email_field = $this->find_field_by_labels(
			$fields,
			array(
				'email',
				'e-mail',
			)
		);

		if ( empty( $email_field['value'] ) ) {
			return;
		}

		$user = get_user_by( 'email', sanitize_email( (string) $email_field['value'] ) );

		if ( ! $user instanceof WP_User ) {
			return;
		}

		if ( ! in_array( self::ROLE_KEY, (array) $user->roles, true ) ) {
			return;
		}

		$phone_field = $this->find_field_by_labels(
			$fields,
			array(
				'phone',
				'phone number',
				'mobile',
			)
		);

		if ( ! empty( $phone_field['value'] ) ) {
			update_user_meta(
				$user->ID,
				self::META_PHONE,
				sanitize_text_field( (string) $phone_field['value'] )
			);
		}

		$already_processed = (int) get_user_meta( $user->ID, self::META_REFERRAL_PROCESSED, true );

		if ( 1 === $already_processed ) {
			return;
		}

		$referral_field = $this->find_field_by_labels(
			$fields,
			array(
				'referral code',
				'referrer code',
				'referral id',
				'referred by',
			)
		);

		$referral_code = '';

		if ( ! empty( $referral_field['value'] ) ) {
			$referral_code = sanitize_text_field( (string) $referral_field['value'] );
		}

		if ( '' === $referral_code ) {
			update_user_meta( $user->ID, self::META_REFERRAL_PROCESSED, 1 );
			return;
		}

		$referrer_id = $this->get_user_id_by_referral_code( $referral_code );

		if ( $referrer_id <= 0 || (int) $referrer_id === (int) $user->ID ) {
			update_user_meta( $user->ID, self::META_REFERRAL_PROCESSED, 1 );
			return;
		}

		update_user_meta( $user->ID, self::META_REFERRED_BY_USER_ID, $referrer_id );
		update_user_meta( $user->ID, self::META_REFERRED_BY_CODE, $referral_code );

		$this->increment_user_meta_int( $referrer_id, self::META_REFERRALS_COUNT, 1 );
		$this->increment_user_meta_int( $referrer_id, self::META_DISCOUNT_CREDITS, 1 );
		$this->increment_user_meta_int( $user->ID, self::META_DISCOUNT_CREDITS, 1 );

		update_user_meta( $user->ID, self::META_REFERRAL_PROCESSED, 1 );
	}

	/**
	 * Generate a unique referral ID.
	 *
	 * @return string
	 */
	private function generate_unique_referral_id() {
		$attempts  = 0;
		$max_tries = 20;

		while ( $attempts < $max_tries ) {
			++$attempts;
			$code = 'REF-' . strtoupper( wp_generate_password( 8, false, false ) );

			if ( ! $this->referral_code_exists( $code ) ) {
				return $code;
			}
		}

		return 'REF-' . strtoupper( wp_generate_password( 12, false, false ) );
	}

	/**
	 * Check if referral code exists.
	 *
	 * @param string $code Referral code.
	 * @return bool
	 */
	private function referral_code_exists( $code ) {
		return $this->get_user_id_by_referral_code( $code ) > 0;
	}

	/**
	 * Get user ID by referral code.
	 *
	 * @param string $code Referral code.
	 * @return int
	 */
	private function get_user_id_by_referral_code( $code ) {
		$users = get_users(
			array(
				'number'      => 1,
				'count_total' => false,
				'fields'      => 'ids',
				'meta_key'    => self::META_REFERRAL_ID,
				'meta_value'  => sanitize_text_field( $code ),
			)
		);

		if ( empty( $users ) || empty( $users[0] ) ) {
			return 0;
		}

		return absint( $users[0] );
	}

	/**
	 * Increment integer user meta.
	 *
	 * @param int    $user_id User ID.
	 * @param string $meta_key Meta key.
	 * @param int    $amount Amount.
	 * @return void
	 */
	private function increment_user_meta_int( $user_id, $meta_key, $amount ) {
		$current = (int) get_user_meta( $user_id, $meta_key, true );
		update_user_meta( $user_id, $meta_key, $current + (int) $amount );
	}

	/**
	 * Find WPForms field by known labels.
	 *
	 * @param array $fields Fields.
	 * @param array $labels Allowed labels.
	 * @return array
	 */
	private function find_field_by_labels( $fields, $labels ) {
		$labels = array_map( 'strtolower', $labels );

		foreach ( $fields as $field ) {
			$name = '';

			if ( isset( $field['name'] ) ) {
				$name = strtolower( sanitize_text_field( (string) $field['name'] ) );
			}

			if ( in_array( $name, $labels, true ) ) {
				return $field;
			}
		}

		return array();
	}

	/**
	 * Get direct referrals for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_direct_referrals( $user_id ) {
		return get_users(
			array(
				'role'       => self::ROLE_KEY,
				'meta_key'   => self::META_REFERRED_BY_USER_ID,
				'meta_value' => (string) absint( $user_id ),
				'fields'     => array( 'ID', 'display_name', 'user_email' ),
				'number'     => 9999,
			)
		);
	}

	/**
	 * Render custom user profile fields.
	 *
	 * @param WP_User $user User object.
	 * @return void
	 */
	public function render_user_profile_fields( $user ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}

		if ( ! in_array( self::ROLE_KEY, (array) $user->roles, true ) ) {
			return;
		}

		$referral_id      = (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true );
		$phone            = (string) get_user_meta( $user->ID, self::META_PHONE, true );
		$referred_by_code = (string) get_user_meta( $user->ID, self::META_REFERRED_BY_CODE, true );
		$credits          = (int) get_user_meta( $user->ID, self::META_DISCOUNT_CREDITS, true );
		$referrals_count  = (int) get_user_meta( $user->ID, self::META_REFERRALS_COUNT, true );

		wp_nonce_field( 'ras_save_user_profile', 'ras_user_profile_nonce' );
		?>
		<h2><?php esc_html_e( 'Referral Details', 'referral-auth-system' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ras_referral_id"><?php esc_html_e( 'Referral ID', 'referral-auth-system' ); ?></label></th>
				<td>
					<input type="text" id="ras_referral_id" class="regular-text" value="<?php echo esc_attr( $referral_id ); ?>" readonly="readonly" />
					<p class="description"><?php esc_html_e( 'Generated automatically. Not editable.', 'referral-auth-system' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ras_phone"><?php esc_html_e( 'Phone Number', 'referral-auth-system' ); ?></label></th>
				<td>
					<input type="text" name="ras_phone" id="ras_phone" class="regular-text" value="<?php echo esc_attr( $phone ); ?>" />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Referred By Code', 'referral-auth-system' ); ?></th>
				<td>
					<input type="text" class="regular-text" value="<?php echo esc_attr( $referred_by_code ); ?>" readonly="readonly" />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Referral Credits', 'referral-auth-system' ); ?></th>
				<td>
					<input type="number" class="small-text" value="<?php echo esc_attr( (string) $credits ); ?>" readonly="readonly" />
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Successful Referrals', 'referral-auth-system' ); ?></th>
				<td>
					<input type="number" class="small-text" value="<?php echo esc_attr( (string) $referrals_count ); ?>" readonly="readonly" />
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save user profile fields.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function save_user_profile_fields( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! in_array( self::ROLE_KEY, (array) $user->roles, true ) ) {
			return;
		}

		if (
			! isset( $_POST['ras_user_profile_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['ras_user_profile_nonce'] ) ),
				'ras_save_user_profile'
			)
		) {
			return;
		}

		if ( isset( $_POST['ras_phone'] ) ) {
			update_user_meta(
				$user_id,
				self::META_PHONE,
				sanitize_text_field( wp_unslash( $_POST['ras_phone'] ) )
			);
		}
	}

	/**
	 * Add users columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_users_columns( $columns ) {
		$columns['ras_referral_id'] = __( 'Referral ID', 'referral-auth-system' );
		$columns['ras_phone']       = __( 'Phone', 'referral-auth-system' );
		$columns['ras_credits']     = __( 'Credits', 'referral-auth-system' );

		return $columns;
	}

	/**
	 * Render users custom columns.
	 *
	 * @param string $output Existing output.
	 * @param string $column_name Column name.
	 * @param int    $user_id User ID.
	 * @return string
	 */
	public function render_users_custom_column( $output, $column_name, $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User || ! in_array( self::ROLE_KEY, (array) $user->roles, true ) ) {
			return $output;
		}

		switch ( $column_name ) {
			case 'ras_referral_id':
				return esc_html( (string) get_user_meta( $user_id, self::META_REFERRAL_ID, true ) );

			case 'ras_phone':
				return esc_html( (string) get_user_meta( $user_id, self::META_PHONE, true ) );

			case 'ras_credits':
				return esc_html( (string) (int) get_user_meta( $user_id, self::META_DISCOUNT_CREDITS, true ) );
		}

		return $output;
	}

	/**
	 * Register admin page.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Referral Dashboard', 'referral-auth-system' ),
			__( 'Referral Dashboard', 'referral-auth-system' ),
			'list_users',
			'ras-referral-dashboard',
			array( $this, 'render_admin_dashboard_page' ),
			'dashicons-networking',
			58
		);
	}

	/**
	 * Render admin dashboard page.
	 *
	 * @return void
	 */
	public function render_admin_dashboard_page() {
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'referral-auth-system' ) );
		}

		$users = get_users(
			array(
				'role'   => self::ROLE_KEY,
				'fields' => array( 'ID', 'display_name', 'user_email' ),
				'number' => 9999,
			)
		);

		$children_map = array();
		$user_map     = array();
		$root_ids     = array();

		foreach ( $users as $user ) {
			$user_map[ $user->ID ] = $user;

			$parent_id = (int) get_user_meta( $user->ID, self::META_REFERRED_BY_USER_ID, true );

			if ( $parent_id > 0 ) {
				if ( ! isset( $children_map[ $parent_id ] ) ) {
					$children_map[ $parent_id ] = array();
				}

				$children_map[ $parent_id ][] = $user->ID;
			} else {
				$root_ids[] = $user->ID;
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Referral Dashboard', 'referral-auth-system' ); ?></h1>
			<p><?php esc_html_e( 'Tracks referral relationships and earned credits.', 'referral-auth-system' ); ?></p>

			<h2><?php esc_html_e( 'Referral Summary', 'referral-auth-system' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'referral-auth-system' ); ?></th>
						<th><?php esc_html_e( 'Email', 'referral-auth-system' ); ?></th>
						<th><?php esc_html_e( 'Referral ID', 'referral-auth-system' ); ?></th>
						<th><?php esc_html_e( 'Referred By', 'referral-auth-system' ); ?></th>
						<th><?php esc_html_e( 'Referrals', 'referral-auth-system' ); ?></th>
						<th><?php esc_html_e( 'Credits', 'referral-auth-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $users as $user ) : ?>
						<?php
						$referral_id = (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true );
						$parent_id   = (int) get_user_meta( $user->ID, self::META_REFERRED_BY_USER_ID, true );
						$referrals   = (int) get_user_meta( $user->ID, self::META_REFERRALS_COUNT, true );
						$credits     = (int) get_user_meta( $user->ID, self::META_DISCOUNT_CREDITS, true );

						$parent_name = '-';

						if ( $parent_id > 0 ) {
							$parent_user = get_userdata( $parent_id );

							if ( $parent_user instanceof WP_User ) {
								$parent_name = $parent_user->display_name;
							}
						}
						?>
						<tr>
							<td><?php echo esc_html( $user->display_name ); ?></td>
							<td><?php echo esc_html( $user->user_email ); ?></td>
							<td><?php echo esc_html( $referral_id ); ?></td>
							<td><?php echo esc_html( $parent_name ); ?></td>
							<td><?php echo esc_html( (string) $referrals ); ?></td>
							<td><?php echo esc_html( (string) $credits ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:30px;"><?php esc_html_e( 'Referral Tree', 'referral-auth-system' ); ?></h2>

			<div style="background:#fff;border:1px solid #dcdcde;padding:20px;margin-top:10px;">
				<?php if ( empty( $root_ids ) ) : ?>
					<p><?php esc_html_e( 'No referral users found yet.', 'referral-auth-system' ); ?></p>
				<?php else : ?>
					<ul>
						<?php
						foreach ( $root_ids as $root_id ) {
							$this->render_tree_node( $root_id, $children_map, $user_map );
						}
						?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render tree node recursively.
	 *
	 * @param int   $user_id User ID.
	 * @param array $children_map Children map.
	 * @param array $user_map User map.
	 * @return void
	 */
	private function render_tree_node( $user_id, $children_map, $user_map ) {
		if ( ! isset( $user_map[ $user_id ] ) ) {
			return;
		}

		$user        = $user_map[ $user_id ];
		$referral_id = (string) get_user_meta( $user_id, self::META_REFERRAL_ID, true );
		$credits     = (int) get_user_meta( $user_id, self::META_DISCOUNT_CREDITS, true );
		$referrals   = (int) get_user_meta( $user_id, self::META_REFERRALS_COUNT, true );

		echo '<li>';
		echo '<strong>' . esc_html( $user->display_name ) . '</strong> ';
		echo '(' . esc_html( $user->user_email ) . ')';
		echo ' — ';
		echo esc_html__( 'Referral ID:', 'referral-auth-system' ) . ' ' . esc_html( $referral_id );
		echo ' — ';
		echo esc_html__( 'Credits:', 'referral-auth-system' ) . ' ' . esc_html( (string) $credits );
		echo ' — ';
		echo esc_html__( 'Referrals:', 'referral-auth-system' ) . ' ' . esc_html( (string) $referrals );

		if ( ! empty( $children_map[ $user_id ] ) ) {
			echo '<ul>';

			foreach ( $children_map[ $user_id ] as $child_id ) {
				$this->render_tree_node( $child_id, $children_map, $user_map );
			}

			echo '</ul>';
		}

		echo '</li>';
	}

	/**
	 * Return plugin CSS.
	 *
	 * @return string
	 */
	private function get_css() {
		return '
		.ras-card {
			--ras-bg: #ffffff;
			--ras-text: #111827;
			--ras-muted: #667085;
			--ras-border: #e5e7eb;
			--ras-primary: #111827;
			--ras-shadow: 0 20px 50px rgba(2, 6, 23, 0.08);
			max-width: 760px;
			margin: 0 auto;
			background: var(--ras-bg);
			border: 1px solid var(--ras-border);
			border-radius: 18px;
			box-shadow: var(--ras-shadow);
			overflow: hidden;
		}

		.ras-tab-nav {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 8px;
			padding: 8px;
			background: #f3f4f6;
			border-bottom: 1px solid var(--ras-border);
		}

		.ras-tab-btn {
			border: 0;
			border-radius: 12px;
			background: transparent;
			color: #374151;
			font-size: 15px;
			font-weight: 600;
			padding: 14px 18px;
			cursor: pointer;
			transition: all 0.2s ease;
		}

		.ras-tab-btn:hover {
			background: rgba(17, 24, 39, 0.06);
		}

		.ras-tab-btn.is-active {
			background: #ffffff;
			color: #111827;
			box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
		}

		.ras-tab-panel {
			display: none;
			padding: 28px;
		}

		.ras-tab-panel.is-active {
			display: block;
		}

		.ras-title {
			margin: 0 0 8px;
			font-size: 28px;
			line-height: 1.15;
			font-weight: 700;
			color: var(--ras-text);
		}

		.ras-subtitle {
			margin: 28px 0 14px;
			font-size: 18px;
			font-weight: 700;
			color: var(--ras-text);
		}

		.ras-copy {
			margin: 0 0 24px;
			color: var(--ras-muted);
			font-size: 15px;
			line-height: 1.65;
		}

		.ras-login-wrap p {
			margin: 0 0 18px;
		}

		.ras-login-wrap label {
			display: block;
			margin: 0 0 8px;
			font-size: 14px;
			font-weight: 600;
			color: var(--ras-text);
		}

		.ras-login-wrap input[type="text"],
		.ras-login-wrap input[type="email"],
		.ras-login-wrap input[type="password"] {
			width: 100%;
			height: 50px;
			padding: 0 16px;
			border: 1px solid #d1d5db;
			border-radius: 12px;
			font-size: 15px;
			box-sizing: border-box;
		}

		.ras-login-wrap input[type="text"]:focus,
		.ras-login-wrap input[type="email"]:focus,
		.ras-login-wrap input[type="password"]:focus {
			outline: none;
			border-color: #9ca3af;
			box-shadow: 0 0 0 4px rgba(17, 24, 39, 0.08);
		}

		.ras-login-wrap .login-remember {
			display: flex;
			align-items: center;
			gap: 8px;
		}

		.ras-login-wrap .login-submit {
			margin-top: 22px;
		}

		.ras-login-wrap input[type="submit"],
		.ras-btn,
		.ras-wpforms-skin .wpforms-submit {
			display: inline-flex !important;
			align-items: center;
			justify-content: center;
			min-height: 50px;
			padding: 0 20px;
			border: 0;
			border-radius: 12px;
			font-size: 15px;
			font-weight: 700;
			text-decoration: none;
			cursor: pointer;
			background: var(--ras-primary) !important;
			color: #fff !important;
		}

		.ras-btn-secondary {
			background: #fff !important;
			color: #111827 !important;
			border: 1px solid var(--ras-border) !important;
		}

		.ras-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 12px;
			margin-top: 18px;
		}

		.ras-logged-in,
		.ras-dashboard {
			padding: 30px;
		}

		.ras-notice {
			padding: 14px 16px;
			border-radius: 12px;
			font-size: 14px;
		}

		.ras-notice-warning {
			background: #fff7ed;
			border: 1px solid #fed7aa;
			color: #9a3412;
		}

		.ras-wpforms-skin .wpforms-container {
			margin: 0 !important;
		}

		.ras-wpforms-skin .wpforms-field {
			padding: 0 0 18px !important;
		}

		.ras-wpforms-skin .wpforms-field-label {
			margin-bottom: 8px !important;
			font-size: 14px !important;
			font-weight: 600 !important;
			color: #111827 !important;
		}

		.ras-wpforms-skin input[type="text"],
		.ras-wpforms-skin input[type="email"],
		.ras-wpforms-skin input[type="password"],
		.ras-wpforms-skin input[type="number"],
		.ras-wpforms-skin input[type="tel"],
		.ras-wpforms-skin input[type="url"],
		.ras-wpforms-skin select,
		.ras-wpforms-skin textarea {
			width: 100% !important;
			min-height: 50px !important;
			padding: 12px 16px !important;
			border: 1px solid #d1d5db !important;
			border-radius: 12px !important;
			font-size: 15px !important;
			box-sizing: border-box !important;
			background: #fff !important;
		}

		.ras-wpforms-skin textarea {
			min-height: 120px !important;
		}

		.ras-wpforms-skin input:focus,
		.ras-wpforms-skin select:focus,
		.ras-wpforms-skin textarea:focus {
			outline: none !important;
			border-color: #9ca3af !important;
			box-shadow: 0 0 0 4px rgba(17, 24, 39, 0.08) !important;
		}

		.ras-wpforms-skin .wpforms-confirmation-container-full {
			background: #ecfdf3 !important;
			border: 1px solid #abefc6 !important;
			border-radius: 12px !important;
			padding: 14px 16px !important;
			color: #067647 !important;
		}

		.ras-stats {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 16px;
			margin-top: 20px;
		}

		.ras-stat-box {
			padding: 18px;
			border: 1px solid #e5e7eb;
			border-radius: 14px;
			background: #f9fafb;
		}

		.ras-stat-label {
			font-size: 13px;
			color: #667085;
			margin-bottom: 8px;
		}

		.ras-stat-value {
			font-size: 22px;
			font-weight: 700;
			color: #111827;
			word-break: break-word;
		}

		.ras-table {
			width: 100%;
			border-collapse: collapse;
		}

		.ras-table th,
		.ras-table td {
			padding: 12px 10px;
			border-bottom: 1px solid #e5e7eb;
			text-align: left;
			font-size: 14px;
		}

		@media (max-width: 767px) {
			.ras-tab-panel,
			.ras-logged-in,
			.ras-dashboard {
				padding: 20px;
			}

			.ras-title {
				font-size: 24px;
			}

			.ras-stats {
				grid-template-columns: 1fr;
			}
		}';
	}

	/**
	 * Return plugin JS.
	 *
	 * @return string
	 */
	private function get_js() {
		return "
		document.addEventListener('DOMContentLoaded', function () {
			var wrappers = document.querySelectorAll('[data-ras-tabs]');

			if (!wrappers.length) {
				return;
			}

			wrappers.forEach(function (wrapper) {
				var buttons = wrapper.querySelectorAll('.ras-tab-btn');
				var panels = wrapper.querySelectorAll('.ras-tab-panel');

				function activateTab(target) {
					buttons.forEach(function (button) {
						var isActive = button.getAttribute('data-tab-target') === target;
						button.classList.toggle('is-active', isActive);
						button.setAttribute('aria-selected', isActive ? 'true' : 'false');
					});

					panels.forEach(function (panel) {
						var isActive = panel.id === 'ras-panel-' + target;
						panel.classList.toggle('is-active', isActive);
					});
				}

				buttons.forEach(function (button) {
					button.addEventListener('click', function () {
						activateTab(button.getAttribute('data-tab-target'));
					});
				});
			});
		});
		";
	}
}

new Referral_Auth_System();
