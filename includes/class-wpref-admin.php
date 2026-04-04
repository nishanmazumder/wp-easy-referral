<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPref_Admin {

	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
		add_filter( 'manage_users_columns', array( $this, 'add_users_columns' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_users_custom_column' ), 10, 3 );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	public function render_user_profile_fields( $user ) {
		if ( ! $user instanceof WP_User || ! in_array( WPref_Plugin::ROLE_KEY, (array) $user->roles, true ) ) {
			return;
		}

		wp_nonce_field( 'wpref_save_user_profile', 'wpref_user_profile_nonce' );

		$referral_id = (string) get_user_meta( $user->ID, WPref_Plugin::META_REFERRAL_ID, true );
		$phone = (string) get_user_meta( $user->ID, WPref_Plugin::META_PHONE, true );
		$credits = (int) get_user_meta( $user->ID, WPref_Plugin::META_DISCOUNT_CREDITS, true );
		?>
		<h2><?php esc_html_e( 'Referral Details', 'wp-easy-referral' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="wpref_referral_id"><?php esc_html_e( 'Referral ID', 'wp-easy-referral' ); ?></label></th>
				<td><input type="text" id="wpref_referral_id" class="regular-text" value="<?php echo esc_attr( $referral_id ); ?>" readonly="readonly" /></td>
			</tr>
			<tr>
				<th><label for="wpref_phone"><?php esc_html_e( 'Phone Number', 'wp-easy-referral' ); ?></label></th>
				<td><input type="text" name="wpref_phone" id="wpref_phone" class="regular-text" value="<?php echo esc_attr( $phone ); ?>" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Credits', 'wp-easy-referral' ); ?></th>
				<td><input type="number" class="small-text" value="<?php echo esc_attr( (string) $credits ); ?>" readonly="readonly" /></td>
			</tr>
		</table>
		<?php
	}

	public function save_user_profile_fields( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! in_array( WPref_Plugin::ROLE_KEY, (array) $user->roles, true ) ) {
			return;
		}

		if ( ! isset( $_POST['wpref_user_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpref_user_profile_nonce'] ) ), 'wpref_save_user_profile' ) ) {
			return;
		}

		if ( isset( $_POST['wpref_phone'] ) ) {
			update_user_meta( $user_id, WPref_Plugin::META_PHONE, sanitize_text_field( wp_unslash( $_POST['wpref_phone'] ) ) );
		}
	}

	public function add_users_columns( $columns ) {
		$columns['wpref_referral_id'] = __( 'Referral ID', 'wp-easy-referral' );
		$columns['wpref_phone'] = __( 'Phone', 'wp-easy-referral' );
		$columns['wpref_credits'] = __( 'Credits', 'wp-easy-referral' );
		return $columns;
	}

	public function render_users_custom_column( $output, $column_name, $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User || ! in_array( WPref_Plugin::ROLE_KEY, (array) $user->roles, true ) ) {
			return $output;
		}

		if ( 'wpref_referral_id' === $column_name ) {
			return esc_html( (string) get_user_meta( $user_id, WPref_Plugin::META_REFERRAL_ID, true ) );
		}
		if ( 'wpref_phone' === $column_name ) {
			return esc_html( (string) get_user_meta( $user_id, WPref_Plugin::META_PHONE, true ) );
		}
		if ( 'wpref_credits' === $column_name ) {
			return esc_html( (string) (int) get_user_meta( $user_id, WPref_Plugin::META_DISCOUNT_CREDITS, true ) );
		}

		return $output;
	}

	public function register_admin_menu() {
		add_menu_page(
			__( 'Referral Dashboard', 'wp-easy-referral' ),
			__( 'Referral Dashboard', 'wp-easy-referral' ),
			'list_users',
			'wpref-referral-dashboard',
			array( $this, 'render_admin_dashboard_page' ),
			'dashicons-networking',
			58
		);
	}

	public function render_admin_dashboard_page() {
		if ( ! current_user_can( 'list_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-easy-referral' ) );
		}

		$users = get_users(
			array(
				'role'   => WPref_Plugin::ROLE_KEY,
				'fields' => array( 'ID', 'display_name', 'user_email' ),
				'number' => 9999,
			)
		);

		$children_map = array();
		$user_map = array();
		$root_ids = array();

		foreach ( $users as $user ) {
			$user_map[ $user->ID ] = $user;
			$parent_id = (int) get_user_meta( $user->ID, WPref_Plugin::META_REFERRED_BY_USER_ID, true );

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
			<h1><?php esc_html_e( 'Referral Dashboard', 'wp-easy-referral' ); ?></h1>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'wp-easy-referral' ); ?></th>
						<th><?php esc_html_e( 'Email', 'wp-easy-referral' ); ?></th>
						<th><?php esc_html_e( 'Referral ID', 'wp-easy-referral' ); ?></th>
						<th><?php esc_html_e( 'Referrals', 'wp-easy-referral' ); ?></th>
						<th><?php esc_html_e( 'Credits', 'wp-easy-referral' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $users as $user ) : ?>
					<tr>
						<td><?php echo esc_html( $user->display_name ); ?></td>
						<td><?php echo esc_html( $user->user_email ); ?></td>
						<td><?php echo esc_html( (string) get_user_meta( $user->ID, WPref_Plugin::META_REFERRAL_ID, true ) ); ?></td>
						<td><?php echo esc_html( (string) (int) get_user_meta( $user->ID, WPref_Plugin::META_REFERRALS_COUNT, true ) ); ?></td>
						<td><?php echo esc_html( (string) (int) get_user_meta( $user->ID, WPref_Plugin::META_DISCOUNT_CREDITS, true ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<h2><?php esc_html_e( 'Referral Tree', 'wp-easy-referral' ); ?></h2>
			<?php if ( empty( $root_ids ) ) : ?>
				<p><?php esc_html_e( 'No referral users found yet.', 'wp-easy-referral' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $root_ids as $root_id ) : ?>
						<?php $this->render_tree_node( $root_id, $children_map, $user_map ); ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_tree_node( $user_id, $children_map, $user_map ) {
		if ( ! isset( $user_map[ $user_id ] ) ) {
			return;
		}

		$user = $user_map[ $user_id ];
		echo '<li><strong>' . esc_html( $user->display_name ) . '</strong> (' . esc_html( $user->user_email ) . ')';
		echo ' — ' . esc_html__( 'Referral ID:', 'wp-easy-referral' ) . ' ' . esc_html( (string) get_user_meta( $user_id, WPref_Plugin::META_REFERRAL_ID, true ) );
		echo ' — ' . esc_html__( 'Credits:', 'wp-easy-referral' ) . ' ' . esc_html( (string) (int) get_user_meta( $user_id, WPref_Plugin::META_DISCOUNT_CREDITS, true ) );

		if ( ! empty( $children_map[ $user_id ] ) ) {
			echo '<ul>';
			foreach ( $children_map[ $user_id ] as $child_id ) {
				$this->render_tree_node( $child_id, $children_map, $user_map );
			}
			echo '</ul>';
		}

		echo '</li>';
	}
}