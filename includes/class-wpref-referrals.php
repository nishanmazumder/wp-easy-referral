<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPref_Referrals {

	public function __construct() {
		add_action( 'user_register', array( $this, 'handle_user_register' ), 20, 1 );
		add_action( 'wpforms_process', array( $this, 'validate_referral_code_field' ), 20, 3 );
		add_action( 'wpforms_process_complete', array( $this, 'handle_wpforms_registration_complete' ), 20, 4 );
	}

	public function handle_user_register( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}

		if ( ! in_array( WPref_Plugin::ROLE_KEY, (array) $user->roles, true ) ) {
			return;
		}

		if ( '' === (string) get_user_meta( $user_id, WPref_Plugin::META_REFERRAL_ID, true ) ) {
			update_user_meta( $user_id, WPref_Plugin::META_REFERRAL_ID, $this->generate_unique_referral_id() );
		}

		if ( '' === (string) get_user_meta( $user_id, WPref_Plugin::META_REFERRALS_COUNT, true ) ) {
			update_user_meta( $user_id, WPref_Plugin::META_REFERRALS_COUNT, 0 );
		}

		if ( '' === (string) get_user_meta( $user_id, WPref_Plugin::META_DISCOUNT_CREDITS, true ) ) {
			update_user_meta( $user_id, WPref_Plugin::META_DISCOUNT_CREDITS, 0 );
		}
	}

	public function validate_referral_code_field( $fields, $entry, $form_data ) {
		$form_id  = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$form_ids = get_option( WPref_Plugin::OPTION_FORM_IDS, array() );
		$form_ids = is_array( $form_ids ) ? array_map( 'absint', $form_ids ) : array();

		if ( ! in_array( $form_id, $form_ids, true ) ) {
			return;
		}

		$referral_field = $this->find_field_by_labels( $fields, array( 'referral code', 'referrer code', 'referral id', 'referred by' ) );
		if ( empty( $referral_field ) ) {
			return;
		}

		$referral_code = isset( $referral_field['value'] ) ? sanitize_text_field( (string) $referral_field['value'] ) : '';
		if ( '' === $referral_code ) {
			return;
		}

		if ( $this->get_user_id_by_referral_code( $referral_code ) > 0 ) {
			return;
		}

		$field_id = isset( $referral_field['id'] ) ? absint( $referral_field['id'] ) : 0;
		if ( $field_id > 0 && function_exists( 'wpforms' ) && isset( wpforms()->process ) ) {
			wpforms()->process->errors[ $form_id ][ $field_id ] = esc_html__( 'Invalid referral code.', 'wp-easy-referral' );
		}
	}

	public function handle_wpforms_registration_complete( $fields, $entry, $form_data, $entry_id ) {
		$form_id  = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$form_ids = get_option( WPref_Plugin::OPTION_FORM_IDS, array() );
		$form_ids = is_array( $form_ids ) ? array_map( 'absint', $form_ids ) : array();

		if ( ! in_array( $form_id, $form_ids, true ) ) {
			return;
		}

		$email_field = $this->find_field_by_labels( $fields, array( 'email', 'e-mail' ) );
		if ( empty( $email_field['value'] ) ) {
			return;
		}

		$user = get_user_by( 'email', sanitize_email( (string) $email_field['value'] ) );
		if ( ! $user instanceof WP_User ) {
			return;
		}

		if ( ! in_array( WPref_Plugin::ROLE_KEY, (array) $user->roles, true ) ) {
			return;
		}

		$phone_field = $this->find_field_by_labels( $fields, array( 'phone', 'phone number', 'mobile' ) );
		if ( ! empty( $phone_field['value'] ) ) {
			update_user_meta( $user->ID, WPref_Plugin::META_PHONE, sanitize_text_field( (string) $phone_field['value'] ) );
		}

		if ( 1 === (int) get_user_meta( $user->ID, WPref_Plugin::META_REFERRAL_PROCESSED, true ) ) {
			return;
		}

		$referral_field = $this->find_field_by_labels( $fields, array( 'referral code', 'referrer code', 'referral id', 'referred by' ) );
		$referral_code  = ! empty( $referral_field['value'] ) ? sanitize_text_field( (string) $referral_field['value'] ) : '';

		if ( '' === $referral_code ) {
			update_user_meta( $user->ID, WPref_Plugin::META_REFERRAL_PROCESSED, 1 );
			return;
		}

		$referrer_id = $this->get_user_id_by_referral_code( $referral_code );
		if ( $referrer_id <= 0 || (int) $referrer_id === (int) $user->ID ) {
			update_user_meta( $user->ID, WPref_Plugin::META_REFERRAL_PROCESSED, 1 );
			return;
		}

		update_user_meta( $user->ID, WPref_Plugin::META_REFERRED_BY_USER_ID, $referrer_id );
		update_user_meta( $user->ID, WPref_Plugin::META_REFERRED_BY_CODE, $referral_code );

		$this->increment_user_meta_int( $referrer_id, WPref_Plugin::META_REFERRALS_COUNT, 1 );
		$this->increment_user_meta_int( $referrer_id, WPref_Plugin::META_DISCOUNT_CREDITS, 1 );
		$this->increment_user_meta_int( $user->ID, WPref_Plugin::META_DISCOUNT_CREDITS, 1 );

		update_user_meta( $user->ID, WPref_Plugin::META_REFERRAL_PROCESSED, 1 );
	}

	private function generate_unique_referral_id() {
		$attempts = 0;

		while ( $attempts < 20 ) {
			$attempts++;
			$code = 'REF-' . strtoupper( wp_generate_password( 8, false, false ) );

			if ( ! $this->referral_code_exists( $code ) ) {
				return $code;
			}
		}

		return 'REF-' . strtoupper( wp_generate_password( 12, false, false ) );
	}

	private function referral_code_exists( $code ) {
		return $this->get_user_id_by_referral_code( $code ) > 0;
	}

	private function get_user_id_by_referral_code( $code ) {
		$users = get_users(
			array(
				'number'      => 1,
				'count_total' => false,
				'fields'      => 'ids',
				'meta_key'    => WPref_Plugin::META_REFERRAL_ID,
				'meta_value'  => sanitize_text_field( $code ),
			)
		);

		if ( empty( $users[0] ) ) {
			return 0;
		}

		return absint( $users[0] );
	}

	private function increment_user_meta_int( $user_id, $meta_key, $amount ) {
		$current = (int) get_user_meta( $user_id, $meta_key, true );
		update_user_meta( $user_id, $meta_key, $current + (int) $amount );
	}

	private function find_field_by_labels( $fields, $labels ) {
		$labels = array_map( 'strtolower', $labels );

		foreach ( $fields as $field ) {
			$name = isset( $field['name'] ) ? strtolower( sanitize_text_field( (string) $field['name'] ) ) : '';
			if ( in_array( $name, $labels, true ) ) {
				return $field;
			}
		}

		return array();
	}
}