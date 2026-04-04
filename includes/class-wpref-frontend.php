<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPref_Frontend {

	public function __construct() {
		add_shortcode( 'wpref_auth_tabs', array( $this, 'render_auth_tabs' ) );
		add_filter( 'login_redirect', array( $this, 'filter_login_redirect' ), 10, 3 );
	}

	public function render_auth_tabs( $atts ) {
		$atts = shortcode_atts(
			array(
				'register_form_id'  => 0,
				'login_title'       => __( 'Login', 'wp-easy-referral' ),
				'register_title'    => __( 'Register', 'wp-easy-referral' ),
				'login_redirect'    => get_option( WPref_Plugin::OPTION_LOGIN_REDIRECT, '/user-referral-dashboard/' ),
				'register_redirect' => get_option( WPref_Plugin::OPTION_REGISTER_REDIRECT, '/thank-you/' ),
				'class'             => '',
			),
			$atts,
			'wpref_auth_tabs'
		);

		wp_enqueue_style( 'wpref-public' );
		wp_enqueue_script( 'wpref-public' );

		$form_id = absint( $atts['register_form_id'] );
		if ( $form_id > 0 ) {
			$this->store_form_id( $form_id );
		}

		$login_form = wp_login_form(
			array(
				'echo'           => false,
				'redirect'       => esc_url_raw( home_url( $atts['login_redirect'] ) ),
				'remember'       => true,
				'form_id'        => 'wpref-loginform',
				'id_username'    => 'wpref-user-login',
				'id_password'    => 'wpref-user-pass',
				'id_remember'    => 'wpref-rememberme',
				'id_submit'      => 'wpref-submit',
				'label_username' => __( 'Username or Email', 'wp-easy-referral' ),
				'label_password' => __( 'Password', 'wp-easy-referral' ),
				'label_remember' => __( 'Remember Me', 'wp-easy-referral' ),
				'label_log_in'   => __( 'Login', 'wp-easy-referral' ),
			)
		);

		$register_form = '';
		if ( $form_id > 0 ) {
			$register_form = do_shortcode( '[wpforms id="' . $form_id . '"]' );
		} else {
			$register_form = '<div class="wpref-notice wpref-notice-warning">' . esc_html__( 'Please set a valid WPForms form ID.', 'wp-easy-referral' ) . '</div>';
		}

		ob_start();
		?>
		<div class="wpref-card wpref-tabs-wrap <?php echo esc_attr( $atts['class'] ); ?>" data-wpref-tabs>
			<div class="wpref-tab-nav" role="tablist" aria-label="<?php echo esc_attr__( 'Authentication tabs', 'wp-easy-referral' ); ?>">
				<button type="button" class="wpref-tab-btn is-active" data-tab-target="login"><?php echo esc_html( $atts['login_title'] ); ?></button>
				<button type="button" class="wpref-tab-btn" data-tab-target="register"><?php echo esc_html( $atts['register_title'] ); ?></button>
			</div>
			<div class="wpref-tab-panel is-active" id="wpref-panel-login">
				<h3 class="wpref-title"><?php echo esc_html( $atts['login_title'] ); ?></h3>
				<div class="wpref-login-wrap"><?php echo $login_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</div>
			<div class="wpref-tab-panel" id="wpref-panel-register">
				<h3 class="wpref-title"><?php echo esc_html( $atts['register_title'] ); ?></h3>
				<div class="wpref-register-wrap wpref-wpforms-skin"><?php echo $register_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $redirect_to;
		}

		if ( in_array( WPref_Plugin::ROLE_KEY, (array) $user->roles, true ) ) {
			if ( ! empty( $requested_redirect_to ) ) {
				return $requested_redirect_to;
			}

			return home_url( get_option( WPref_Plugin::OPTION_LOGIN_REDIRECT, '/user-referral-dashboard/' ) );
		}

		return $redirect_to;
	}

	private function store_form_id( $form_id ) {
		$form_ids = get_option( WPref_Plugin::OPTION_FORM_IDS, array() );
		$form_ids = is_array( $form_ids ) ? array_map( 'absint', $form_ids ) : array();

		if ( ! in_array( $form_id, $form_ids, true ) ) {
			$form_ids[] = $form_id;
			update_option( WPref_Plugin::OPTION_FORM_IDS, array_values( array_unique( $form_ids ) ), false );
		}
	}
}