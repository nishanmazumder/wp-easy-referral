<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPref_User_Dashboard {

	public function __construct() {
		add_shortcode( 'wpref_user_dashboard', array( $this, 'render_user_dashboard' ) );
	}

	public function render_user_dashboard() {
		if ( ! is_user_logged_in() ) {
			return '<div class="wpref-notice wpref-notice-warning">' . esc_html__( 'Please log in to view your dashboard.', 'wp-easy-referral' ) . '</div>';
		}

		$user = wp_get_current_user();
		$children = get_users(
			array(
				'role'       => WPref_Plugin::ROLE_KEY,
				'meta_key'   => WPref_Plugin::META_REFERRED_BY_USER_ID,
				'meta_value' => (string) absint( $user->ID ),
				'fields'     => array( 'ID', 'display_name', 'user_email' ),
				'number'     => 9999,
			)
		);

		wp_enqueue_style( 'wpref-public' );

		ob_start();
		?>
		<div class="wpref-card">
			<div class="wpref-dashboard">
				<h3 class="wpref-title"><?php esc_html_e( 'Referral Dashboard', 'wp-easy-referral' ); ?></h3>
				<div class="wpref-stats">
					<div class="wpref-stat-box"><div class="wpref-stat-label"><?php esc_html_e( 'Your Referral ID', 'wp-easy-referral' ); ?></div><div class="wpref-stat-value"><?php echo esc_html( (string) get_user_meta( $user->ID, WPref_Plugin::META_REFERRAL_ID, true ) ); ?></div></div>
					<div class="wpref-stat-box"><div class="wpref-stat-label"><?php esc_html_e( 'Credits', 'wp-easy-referral' ); ?></div><div class="wpref-stat-value"><?php echo esc_html( (string) (int) get_user_meta( $user->ID, WPref_Plugin::META_DISCOUNT_CREDITS, true ) ); ?></div></div>
					<div class="wpref-stat-box"><div class="wpref-stat-label"><?php esc_html_e( 'Referrals', 'wp-easy-referral' ); ?></div><div class="wpref-stat-value"><?php echo esc_html( (string) (int) get_user_meta( $user->ID, WPref_Plugin::META_REFERRALS_COUNT, true ) ); ?></div></div>
				</div>
				<h4 class="wpref-subtitle"><?php esc_html_e( 'Users You Referred', 'wp-easy-referral' ); ?></h4>
				<?php if ( empty( $children ) ) : ?>
					<p><?php esc_html_e( 'No referrals yet.', 'wp-easy-referral' ); ?></p>
				<?php else : ?>
					<table class="wpref-table">
						<thead><tr><th><?php esc_html_e( 'Name', 'wp-easy-referral' ); ?></th><th><?php esc_html_e( 'Email', 'wp-easy-referral' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $children as $child ) : ?>
							<tr><td><?php echo esc_html( $child->display_name ); ?></td><td><?php echo esc_html( $child->user_email ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}