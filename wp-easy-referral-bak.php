<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Admin list table for referral registration entries.
 */
final class WPERF_Referral_Entries_List_Table extends WP_List_Table {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table_name = '';

	/**
	 * Constructor.
	 *
	 * @param string $table_name Entries table name.
	 */
	public function __construct( $table_name ) {
		$this->table_name = $table_name;

		parent::__construct(
			array(
				'singular' => 'wperf_referral_entry',
				'plural'   => 'wperf_referral_entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Return table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'id'               => __( 'ID', 'wp-easy-referral' ),
			'registered_at'    => __( 'Date', 'wp-easy-referral' ),
			'name'             => __( 'Name', 'wp-easy-referral' ),
			'email'            => __( 'Email', 'wp-easy-referral' ),
			'phone'            => __( 'Phone', 'wp-easy-referral' ),
			'referral_code'    => __( 'Referral Code', 'wp-easy-referral' ),
			'referred_by_code' => __( 'Referred By', 'wp-easy-referral' ),
			'share_clicks'     => __( 'Share Clicks', 'wp-easy-referral' ),
			'source'           => __( 'Source', 'wp-easy-referral' ),
			'user_source'      => __( 'User Source', 'wp-easy-referral' ),
			'status'           => __( 'Status', 'wp-easy-referral' ),
			'remarks'          => __( 'Remarks', 'wp-easy-referral' ),
		);
	}

	/**
	 * Return sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'id'            => array( 'id', true ),
			'registered_at' => array( 'registered_at', true ),
			'name'          => array( 'name', false ),
			'email'         => array( 'email', false ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="entry_ids[]" value="' . absint( $item->id ) . '" />';
	}

	/**
	 * Date column with readable format.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_registered_at( $item ) {
		$raw_date = isset( $item->registered_at ) ? (string) $item->registered_at : '';
		if ( '' === $raw_date ) {
			return '';
		}

		$timestamp = strtotime( $raw_date );
		if ( false === $timestamp ) {
			return esc_html( $raw_date );
		}

		return esc_html( wp_date( 'j F, y \\a\\t g.ia', $timestamp ) );
	}

	/**
	 * Default column renderer.
	 *
	 * @param object $item        Row item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		if ( 'source' === $column_name ) {
			$source           = isset( $item->source ) ? sanitize_key( (string) $item->source ) : '';
			$referred_by_code = isset( $item->referred_by_code ) ? sanitize_text_field( (string) $item->referred_by_code ) : '';

			if ( 'referred' === $source || ( 'manual' === $source && '' !== $referred_by_code ) ) {
				return esc_html__( 'Referred', 'wp-easy-referral' );
			}

			return esc_html__( 'Direct', 'wp-easy-referral' );
		}

		if ( isset( $item->$column_name ) ) {
			return esc_html( (string) $item->$column_name );
		}

		return '';
	}

	/**
	 * Name column with row action.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_name( $item ) {
		$view_url = add_query_arg(
			array(
				'page'     => 'wperf-easy-referral',
				'wperf_id' => absint( $item->id ),
			),
			admin_url( 'admin.php' )
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'            => 'wperf-easy-referral',
					'wperf_action'    => 'delete_entry',
					'wperf_entry_ids' => absint( $item->id ),
				),
				admin_url( 'admin.php' )
			),
			'wperf_delete_entries',
			'wperf_delete_nonce'
		);

		$actions = array(
			'view'   => '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'wp-easy-referral' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to delete this entry?', 'wp-easy-referral' ) ) . '\');">' . esc_html__( 'Delete', 'wp-easy-referral' ) . '</a>',
		);

		return '<strong>' . esc_html( $item->name ) . '</strong> ' . $this->row_actions( $actions );
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'wp-easy-referral' ),
		);
	}

	/**
	 * Prepare table items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;
		$orderby      = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'registered_at';
		$order        = isset( $_GET['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		$order        = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
		$allowed      = array( 'id', 'registered_at', 'name', 'email' );
		$orderby      = in_array( $orderby, $allowed, true ) ? $orderby : 'registered_at';
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$start_date   = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date     = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		$where_sql    = 'WHERE 1=1';
		$where_values = array();

		if ( '' !== $search ) {
			$like          = '%' . $wpdb->esc_like( $search ) . '%';
			$where_sql    .= ' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s OR referral_code LIKE %s OR referred_by_code LIKE %s)';
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
			$where_sql    .= ' AND DATE(registered_at) >= %s';
			$where_values[] = $start_date;
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			$where_sql    .= ' AND DATE(registered_at) <= %s';
			$where_values[] = $end_date;
		}

		$count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_sql}";
		$data_query  = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		if ( ! empty( $where_values ) ) {
			$total_items = (int) $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$where_values[] = $per_page;
			$where_values[] = $offset;
			$this->items = $wpdb->get_results( $wpdb->prepare( $data_query, $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$total_items = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->items = $wpdb->get_results( $wpdb->prepare( $data_query, $per_page, $offset ) );
		}

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}
}

/**
 * Main plugin class.
 */
final class WPERF_Referral_Auth_System {
	const VERSION                  = '1.6.0';
	const ROLE_KEY                 = 'referral_user';
	const ROLE_NAME                = 'Referral User';
	const DASHBOARD_SLUG           = 'referral-dashboard';
	const SHARE_SLUG               = 'referral-share';
	const QUERY_VAR_DASHBOARD      = 'wperf_referral_dashboard';
	const QUERY_VAR_SHARE_CODE     = 'wperf_referral_share_code';
	const REFERRAL_COOKIE          = 'wperf_referral_code';
	const OPTION_SETTINGS          = 'wperf_settings';
	const OPTION_REG_PAGE          = 'wperf_registration_page_url';
	const TABLE_REGISTRATIONS      = 'wperf_referral_registrations';
	const NONCE_EXPORT             = 'wperf_export_entries';

	const META_REFERRAL_ID         = 'wperf_referral_id';
	const META_PHONE               = 'wperf_phone';
	const META_REFERRED_BY_USER_ID = 'wperf_referred_by_user_id';
	const META_REFERRED_BY_CODE    = 'wperf_referred_by_code';
	const META_REFERRALS_COUNT     = 'wperf_referrals_count';
	const META_DISCOUNT_CREDITS    = 'wperf_discount_credits';
	const META_REFERRAL_PROCESSED  = 'wperf_referral_processed';
	const META_GOOGLE_LINKED       = 'wperf_google_linked';
	const META_GOOGLE_SUB          = 'wperf_google_sub';
	const META_REFERRAL_USER_NAME  = 'wperf_referral_user_name';
	const META_REFERRAL_USER_PHONE = 'wperf_referral_user_phone';
	const META_SHARE_CLICKS        = 'wperf_share_clicks';

	/**
	 * DB table name.
	 *
	 * @var string
	 */
	private $table_name = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . self::TABLE_REGISTRATIONS;

		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

		add_action( 'init', array( $this, 'register_rewrite_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'render_virtual_pages' ) );
		add_action( 'wp_head', array( $this, 'output_share_meta_tags' ) );
		add_action( 'init', array( $this, 'capture_referral_code' ) );
		add_action( 'init', array( $this, 'maybe_handle_front_actions' ) );
		add_action( 'init', array( $this, 'maybe_start_google_login' ) );
		add_action( 'init', array( $this, 'maybe_handle_google_callback' ) );
		add_action( 'init', array( $this, 'maybe_handle_share_click_redirect' ) );
		add_action( 'init', array( $this, 'maybe_upgrade_schema' ) );

		add_shortcode( 'wperf_auth_tabs', array( $this, 'render_auth_tabs' ) );
		add_shortcode( 'wperf_user_dashboard', array( $this, 'render_user_dashboard' ) );
		add_shortcode( 'wperf_referred_register', array( $this, 'render_referred_register_page' ) );
		add_shortcode( 'wperf_lead_desk', array( $this, 'render_lead_desk_page' ) );

		add_action( 'wp_ajax_wperf_update_lead', array( $this, 'ajax_update_lead' ) );
		add_action( 'wp_ajax_wperf_check_phone', array( $this, 'ajax_check_phone' ) );
		add_action( 'wp_ajax_nopriv_wperf_check_phone', array( $this, 'ajax_check_phone' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_block_referral_user_admin' ) );
		add_action( 'admin_init', array( $this, 'maybe_export_entries_csv' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_delete_entries' ) );

		add_action( 'user_register', array( $this, 'handle_user_register' ), 20, 1 );
		add_filter( 'login_redirect', array( $this, 'filter_login_redirect' ), 10, 3 );
		add_filter( 'show_admin_bar', array( $this, 'maybe_hide_admin_bar' ) );
		add_filter( 'manage_users_columns', array( $this, 'add_users_columns' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_users_custom_column' ), 10, 3 );
	}

	/**
	 * Activation routine.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		add_role(
			self::ROLE_KEY,
			self::ROLE_NAME,
			array(
				'read' => true,
			)
		);

		add_role(
			'referral_help_agent',
			__( 'Referral Help Agent', 'wp-easy-referral' ),
			array(
				'read' => true,
			)
		);

		$settings = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		update_option( self::OPTION_SETTINGS, wp_parse_args( $settings, self::get_default_settings() ), false );

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table_name      = $wpdb->prefix . self::TABLE_REGISTRATIONS;
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			referral_user_name varchar(190) NOT NULL DEFAULT '',
			referral_user_phone varchar(50) NOT NULL DEFAULT '',
			referral_code varchar(100) NOT NULL DEFAULT '',
			referred_by_code varchar(100) NOT NULL DEFAULT '',
			share_clicks bigint(20) unsigned NOT NULL DEFAULT 0,
			source varchar(20) NOT NULL DEFAULT 'manual',
			user_source varchar(50) NOT NULL DEFAULT '',
			status varchar(50) NOT NULL DEFAULT 'Unverified',
			remarks text,
			registered_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY email (email),
			KEY phone (phone),
			KEY referral_code (referral_code),
			KEY referred_by_code (referred_by_code),
			KEY registered_at (registered_at)
		) {$charset_collate};";
		dbDelta( $sql );

		$self = new self();
		$self->register_rewrite_endpoints();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Ensure schema is up to date.
	 *
	 * @return void
	 */
	public function maybe_upgrade_schema() {
		global $wpdb;
		$table_name = $this->table_name;

		$share_clicks_column = $wpdb->get_var( "SHOW COLUMNS FROM {$table_name} LIKE 'share_clicks'" );
		if ( null === $share_clicks_column ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD share_clicks bigint(20) unsigned NOT NULL DEFAULT 0 AFTER referred_by_code" );
		}

		$user_source_column = $wpdb->get_var( "SHOW COLUMNS FROM {$table_name} LIKE 'user_source'" );
		if ( null === $user_source_column ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD user_source varchar(50) NOT NULL DEFAULT '' AFTER source" );
		}

		$status_column = $wpdb->get_var( "SHOW COLUMNS FROM {$table_name} LIKE 'status'" );
		if ( null === $status_column ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD status varchar(50) NOT NULL DEFAULT 'Unverified' AFTER user_source" );
		}

		$remarks_column = $wpdb->get_var( "SHOW COLUMNS FROM {$table_name} LIKE 'remarks'" );
		if ( null === $remarks_column ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD remarks text AFTER status" );
		}

		if ( ! get_role( 'referral_help_agent' ) ) {
			add_role( 'referral_help_agent', __( 'Referral Help Agent', 'wp-easy-referral' ), array( 'read' => true ) );
		}
	}

	/**
	 * Register rewrite endpoints.
	 *
	 * @return void
	 */
	public function register_rewrite_endpoints() {
		add_rewrite_rule(
			'^' . self::DASHBOARD_SLUG . '/?$',
			'index.php?' . self::QUERY_VAR_DASHBOARD . '=1',
			'top'
		);

		add_rewrite_rule(
			'^' . self::SHARE_SLUG . '/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR_SHARE_CODE . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR_DASHBOARD;
		$vars[] = self::QUERY_VAR_SHARE_CODE;

		return $vars;
	}

	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style( 'wperf-auth-system', false, array(), self::VERSION );
		wp_register_script( 'wperf-auth-system', false, array(), self::VERSION, true );
		wp_add_inline_style( 'wperf-auth-system', $this->get_css() );
		wp_add_inline_script( 'wperf-auth-system', $this->get_js() );
	}

	/**
	 * Register admin menus.
	 *
	 * @return void
	 */
	public function register_admin_menus() {
		add_menu_page(
			__( 'WP Easy Referral', 'wp-easy-referral' ),
			__( 'WP Easy Referral', 'wp-easy-referral' ),
			'manage_options',
			'wperf-easy-referral',
			array( $this, 'render_entries_page' ),
			'dashicons-networking',
			58
		);

		add_submenu_page(
			'wperf-easy-referral',
			__( 'Entries', 'wp-easy-referral' ),
			__( 'Entries', 'wp-easy-referral' ),
			'manage_options',
			'wperf-easy-referral',
			array( $this, 'render_entries_page' )
		);

		add_submenu_page(
			'wperf-easy-referral',
			__( 'Settings', 'wp-easy-referral' ),
			__( 'Settings', 'wp-easy-referral' ),
			'manage_options',
			'wperf-easy-referral-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'wperf_settings_group',
			self::OPTION_SETTINGS,
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'wp-easy-referral_page_wperf-easy-referral-settings' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_add_inline_script(
			'jquery-core',
			"jQuery(function($){
				function wperfBindPicker(buttonSelector,inputSelector,title){
					$(document).on('click',buttonSelector,function(e){
						e.preventDefault();
						var target=$(this).data('target') || inputSelector;
						var frame=wp.media({title:title,multiple:false});
						frame.on('select',function(){
							var attachment=frame.state().get('selection').first().toJSON();
							$(target).val(attachment.url).trigger('change');
						});
						frame.open();
					});
				}
				wperfBindPicker('.wperf-select-brochure','#wperf_brochure_url','Select Brochure PDF');
				wperfBindPicker('.wperf-select-bg','#wperf_share_bg_url','Select Share Background');
			});"
		);
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = self::get_default_settings();
		$input    = is_array( $input ) ? $input : array();

		return array(
			'brochure_url'                 => isset( $input['brochure_url'] ) ? esc_url_raw( trim( (string) $input['brochure_url'] ) ) : $defaults['brochure_url'],
			'share_bg_url'                 => isset( $input['share_bg_url'] ) ? esc_url_raw( trim( (string) $input['share_bg_url'] ) ) : $defaults['share_bg_url'],
			'share_message'                => isset( $input['share_message'] ) ? sanitize_text_field( (string) $input['share_message'] ) : $defaults['share_message'],
			'registration_page_url'        => isset( $input['registration_page_url'] ) ? esc_url_raw( trim( (string) $input['registration_page_url'] ) ) : $defaults['registration_page_url'],
			'shared_registration_page_url' => isset( $input['shared_registration_page_url'] ) ? esc_url_raw( trim( (string) $input['shared_registration_page_url'] ) ) : $defaults['shared_registration_page_url'],
			'referred_banner_title'        => isset( $input['referred_banner_title'] ) ? sanitize_text_field( (string) $input['referred_banner_title'] ) : $defaults['referred_banner_title'],
			'referred_banner_text'         => isset( $input['referred_banner_text'] ) ? sanitize_textarea_field( (string) $input['referred_banner_text'] ) : $defaults['referred_banner_text'],
			'shared_brochure_url'          => isset( $input['shared_brochure_url'] ) ? esc_url_raw( trim( (string) $input['shared_brochure_url'] ) ) : $defaults['shared_brochure_url'],
			'shared_banner_bg_url'         => isset( $input['shared_banner_bg_url'] ) ? esc_url_raw( trim( (string) $input['shared_banner_bg_url'] ) ) : $defaults['shared_banner_bg_url'],
			'shared_banner_bg_mobile_url'  => isset( $input['shared_banner_bg_mobile_url'] ) ? esc_url_raw( trim( (string) $input['shared_banner_bg_mobile_url'] ) ) : $defaults['shared_banner_bg_mobile_url'],
			'facebook_share_title'         => isset( $input['facebook_share_title'] ) ? sanitize_text_field( (string) $input['facebook_share_title'] ) : $defaults['facebook_share_title'],
			'project_links'                => isset( $input['project_links'] ) ? sanitize_textarea_field( (string) $input['project_links'] ) : $defaults['project_links'],
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	private static function get_default_settings() {
		return array(
			'brochure_url'                 => '',
			'share_bg_url'                 => '',
			'share_message'                => __( 'Unlock special offer on selected bti residences.', 'wp-easy-referral' ),
			'registration_page_url'        => home_url( '/' ),
			'shared_registration_page_url' => home_url( '/' ),
			'referred_banner_title'        => __( 'Welcome to the Referral Program', 'wp-easy-referral' ),
			'referred_banner_text'         => __( 'Register now to continue and explore the brochure and projects.', 'wp-easy-referral' ),
			'shared_brochure_url'          => '',
			'shared_banner_bg_url'         => '',
			'shared_banner_bg_mobile_url'  => '',
			'facebook_share_title'         => __( 'Unlock Special Offers on bti homes', 'wp-easy-referral' ),
			'project_links'                => '',
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array
	 */
	private function get_settings() {
		$settings = get_option( self::OPTION_SETTINGS, array() );

		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::get_default_settings() );
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-easy-referral' ) );
		}

		$settings = $this->get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Easy Referral Settings', 'wp-easy-referral' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wperf_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wperf_brochure_url"><?php esc_html_e( 'Brochure PDF URL', 'wp-easy-referral' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="wperf_brochure_url" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[brochure_url]" value="<?php echo esc_attr( $settings['brochure_url'] ); ?>" />
							<button type="button" class="button wperf-select-brochure"><?php esc_html_e( 'Select PDF', 'wp-easy-referral' ); ?></button>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wperf_share_bg_url"><?php esc_html_e( 'Share Card Background Image', 'wp-easy-referral' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="wperf_share_bg_url" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[share_bg_url]" value="<?php echo esc_attr( $settings['share_bg_url'] ); ?>" />
							<button type="button" class="button wperf-select-bg"><?php esc_html_e( 'Select Image', 'wp-easy-referral' ); ?></button>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wperf_share_message"><?php esc_html_e( 'Share Card Message', 'wp-easy-referral' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wperf_share_message" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[share_message]" value="<?php echo esc_attr( $settings['share_message'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wperf_facebook_share_title"><?php esc_html_e( 'Facebook Share Title', 'wp-easy-referral' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wperf_facebook_share_title" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[facebook_share_title]" value="<?php echo esc_attr( $settings['facebook_share_title'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wperf_registration_page_url"><?php esc_html_e( 'Registration Page URL', 'wp-easy-referral' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="wperf_registration_page_url" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[registration_page_url]" value="<?php echo esc_attr( $settings['registration_page_url'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Set the page where the [wperf_auth_tabs] shortcode exists.', 'wp-easy-referral' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wperf_shared_registration_page_url"><?php esc_html_e( 'Shared Registration Page URL', 'wp-easy-referral' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="wperf_shared_registration_page_url" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[shared_registration_page_url]" value="<?php echo esc_attr( $settings['shared_registration_page_url'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Set the page where the [wperf_referred_register] shortcode exists.', 'wp-easy-referral' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wperf_shared_banner_bg_url"><?php esc_html_e( 'Shared Page Banner Background', 'wp-easy-referral' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="wperf_shared_banner_bg_url" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[shared_banner_bg_url]" value="<?php echo esc_attr( $settings['shared_banner_bg_url'] ); ?>" />
							<button type="button" class="button wperf-select-bg" data-target="#wperf_shared_banner_bg_url"><?php esc_html_e( 'Select Image', 'wp-easy-referral' ); ?></button>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wperf_shared_banner_bg_mobile_url"><?php esc_html_e( 'Shared Page Mobile Banner Background', 'wp-easy-referral' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="wperf_shared_banner_bg_mobile_url" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[shared_banner_bg_mobile_url]" value="<?php echo esc_attr( $settings['shared_banner_bg_mobile_url'] ); ?>" />
							<button type="button" class="button wperf-select-bg" data-target="#wperf_shared_banner_bg_mobile_url"><?php esc_html_e( 'Select Image', 'wp-easy-referral' ); ?></button>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wperf_shared_brochure_url"><?php esc_html_e( 'Shared Page Brochure PDF', 'wp-easy-referral' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="wperf_shared_brochure_url" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[shared_brochure_url]" value="<?php echo esc_attr( $settings['shared_brochure_url'] ); ?>" />
							<button type="button" class="button wperf-select-brochure" data-target="#wperf_shared_brochure_url"><?php esc_html_e( 'Select PDF', 'wp-easy-referral' ); ?></button>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wperf_project_links"><?php esc_html_e( 'Project List', 'wp-easy-referral' ); ?></label></th>
						<td>
							<textarea class="large-text code" rows="6" id="wperf_project_links" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[project_links]"><?php echo esc_textarea( $settings['project_links'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One project per line in this format: Project Name | https://example.com', 'wp-easy-referral' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<p><strong><?php esc_html_e( 'Google Redirect URI', 'wp-easy-referral' ); ?>:</strong> <code><?php echo esc_html( $this->get_google_redirect_uri() ); ?></code></p>
		</div>
		<?php
	}

	/**
	 * Handle entry deletion actions.
	 *
	 * @return void
	 */
	public function maybe_handle_delete_entries() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['page'] ) || 'wperf-easy-referral' !== sanitize_key( wp_unslash( $_REQUEST['page'] ) ) ) {
			return;
		}

		$action = '';
		if ( isset( $_REQUEST['wperf_action'] ) ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['wperf_action'] ) );
		} elseif ( isset( $_REQUEST['action'] ) && '-1' !== (string) wp_unslash( $_REQUEST['action'] ) ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
		} elseif ( isset( $_REQUEST['action2'] ) && '-1' !== (string) wp_unslash( $_REQUEST['action2'] ) ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
		}

		if ( 'delete_entry' !== $action && 'delete' !== $action ) {
			return;
		}

		$delete_nonce = '';
		if ( isset( $_REQUEST['wperf_delete_nonce'] ) ) {
			$delete_nonce = sanitize_text_field( wp_unslash( $_REQUEST['wperf_delete_nonce'] ) );
		} elseif ( isset( $_REQUEST['_wpnonce'] ) ) {
			$delete_nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
		}

		if ( '' === $delete_nonce || ! wp_verify_nonce( $delete_nonce, 'wperf_delete_entries' ) ) {
			return;
		}

		$entry_ids = array();
		if ( isset( $_REQUEST['wperf_entry_ids'] ) ) {
			$raw_ids = wp_unslash( $_REQUEST['wperf_entry_ids'] );
			$raw_ids = is_array( $raw_ids ) ? $raw_ids : array( $raw_ids );
			$entry_ids = array_filter( array_map( 'absint', $raw_ids ) );
		}

		if ( empty( $entry_ids ) && isset( $_REQUEST['entry_ids'] ) ) {
			$raw_ids = wp_unslash( $_REQUEST['entry_ids'] );
			$raw_ids = is_array( $raw_ids ) ? $raw_ids : array( $raw_ids );
			$entry_ids = array_filter( array_map( 'absint', $raw_ids ) );
		}

		if ( empty( $entry_ids ) ) {
			return;
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)", $entry_ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$redirect_url = add_query_arg(
			array(
				'page'          => 'wperf-easy-referral',
				'wperf_deleted' => count( $entry_ids ),
			),
			admin_url( 'admin.php' )
		);
		wp_redirect( esc_url_raw( $redirect_url ), 302 );
		exit;
	}

	/**
	 * Render entries page.
	 *
	 * @return void
	 */
	public function render_entries_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-easy-referral' ) );
		}

		$entry_id = isset( $_GET['wperf_id'] ) ? absint( wp_unslash( $_GET['wperf_id'] ) ) : 0;
		$deleted  = isset( $_GET['wperf_deleted'] ) ? absint( wp_unslash( $_GET['wperf_deleted'] ) ) : 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Easy Referral Entries', 'wp-easy-referral' ); ?></h1>
			<?php if ( $deleted > 0 ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( _n( '%d entry deleted.', '%d entries deleted.', $deleted, 'wp-easy-referral' ), $deleted ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( $entry_id > 0 ) : ?>
				<?php $this->render_single_entry_view( $entry_id ); ?>
			<?php else : ?>
				<?php $this->render_entries_list_view(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render entries list view.
	 *
	 * @return void
	 */
	private function render_entries_list_view() {
		$list_table = new WPERF_Referral_Entries_List_Table( $this->table_name );
		$list_table->prepare_items();
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		$search     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'       => 'wperf-easy-referral',
					'wperf_export' => 1,
					'start_date' => $start_date,
					'end_date'   => $end_date,
					's'          => $search,
				),
				admin_url( 'admin.php' )
			),
			self::NONCE_EXPORT
		);
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wperf-easy-referral' ) ); ?>">
			<input type="hidden" name="page" value="wperf-easy-referral" />
			<?php wp_nonce_field( 'wperf_delete_entries', 'wperf_delete_nonce' ); ?>
			<p class="search-box">
				<label class="screen-reader-text" for="entry-search-input"><?php esc_html_e( 'Search entries:', 'wp-easy-referral' ); ?></label>
				<input type="search" id="entry-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" />
				<?php submit_button( __( 'Search Entries', 'wp-easy-referral' ), '', '', false ); ?>
			</p>
			<p>
				<label for="wperf_start_date"><?php esc_html_e( 'From', 'wp-easy-referral' ); ?></label>
				<input type="date" id="wperf_start_date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
				<label for="wperf_end_date"><?php esc_html_e( 'To', 'wp-easy-referral' ); ?></label>
				<input type="date" id="wperf_end_date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
				<a class="button button-primary" id="wperf_export_csv_by_date" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'wp-easy-referral' ); ?></a>
			</p>
			<?php $list_table->display(); ?>
		</form>
		<script>
		(function(){
			var exportLink = document.getElementById('wperf_export_csv_by_date');
			var startInput = document.getElementById('wperf_start_date');
			var endInput = document.getElementById('wperf_end_date');
			if (!exportLink || !startInput || !endInput) {
				return;
			}
			exportLink.addEventListener('click', function(event){
				event.preventDefault();
				var url = new URL(exportLink.getAttribute('href'), window.location.origin);
				if (startInput.value) {
					url.searchParams.set('start_date', startInput.value);
				} else {
					url.searchParams.delete('start_date');
				}
				if (endInput.value) {
					url.searchParams.set('end_date', endInput.value);
				} else {
					url.searchParams.delete('end_date');
				}
				window.location.href = url.toString();
			});
		}());
		</script>
		<?php
	}

	/**
	 * Render single entry view.
	 *
	 * @param int $entry_id Entry ID.
	 * @return void
	 */
	private function render_single_entry_view( $entry_id ) {
		global $wpdb;

		$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $entry_id ) );
		$back  = add_query_arg( array( 'page' => 'wperf-easy-referral' ), admin_url( 'admin.php' ) );

		if ( ! $entry ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Entry not found.', 'wp-easy-referral' ) . '</p></div>';
			echo '<p><a class="button" href="' . esc_url( $back ) . '">' . esc_html__( 'Back to Entries', 'wp-easy-referral' ) . '</a></p>';
			return;
		}
		?>
		<p><a class="button" href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Back to Entries', 'wp-easy-referral' ); ?></a></p>
		<table class="widefat striped" style="max-width:900px;">
			<tbody>
				<tr><th><?php esc_html_e( 'ID', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->id ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Date', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->registered_at ); ?></td></tr>
				<tr><th><?php esc_html_e( 'User ID', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->user_id ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Name', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->name ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Email', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->email ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Phone', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->phone ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Referral User Name', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->referral_user_name ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Referral\'s Phone', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->referral_user_phone ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Referral Code', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->referral_code ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Referred By Code', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->referred_by_code ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Source', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( $this->get_source_label( (string) $entry->source, (string) $entry->referred_by_code ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'User Source', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->user_source ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Status', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->status ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Remarks', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->remarks ); ?></td></tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Export entries as CSV.
	 *
	 * @return void
	 */
	public function maybe_export_entries_csv() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) || ! isset( $_GET['wperf_export'] ) ) {
			return;
		}

		check_admin_referer( self::NONCE_EXPORT );

		global $wpdb;
		$start_date   = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date     = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$where_sql    = 'WHERE 1=1';
		$where_values = array();

		if ( '' !== $search ) {
			$like          = '%' . $wpdb->esc_like( $search ) . '%';
			$where_sql    .= ' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s OR referral_code LIKE %s OR referred_by_code LIKE %s)';
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
			$where_sql    .= ' AND DATE(registered_at) >= %s';
			$where_values[] = $start_date;
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
			$where_sql    .= ' AND DATE(registered_at) <= %s';
			$where_values[] = $end_date;
		}

		$query = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY registered_at ASC, id ASC";
		$rows  = empty( $where_values ) ? $wpdb->get_results( $query, ARRAY_A ) : $wpdb->get_results( $wpdb->prepare( $query, $where_values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=wperf-referral-entries-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		fputcsv(
			$output,
			array(
				'ID',
				'User ID',
				'Name',
				'Email',
				'Phone',
				'Referral User Name',
				'Referral\'s Phone Number',
				'Referral Code',
				'Referred By Code',
				'Share Clicks',
				'Source',
				'User Source',
				'Status',
				'Remarks',
				'Registered At',
			),
			',',
			'"',
			''
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					isset( $row['id'] ) ? $row['id'] : '',
					isset( $row['user_id'] ) ? $row['user_id'] : '',
					isset( $row['name'] ) ? $row['name'] : '',
					isset( $row['email'] ) ? $row['email'] : '',
					isset( $row['phone'] ) ? $row['phone'] : '',
					isset( $row['referral_user_name'] ) ? $row['referral_user_name'] : '',
					isset( $row['referral_user_phone'] ) ? $row['referral_user_phone'] : '',
					isset( $row['referral_code'] ) ? $row['referral_code'] : '',
					isset( $row['referred_by_code'] ) ? $row['referred_by_code'] : '',
					isset( $row['share_clicks'] ) ? $row['share_clicks'] : '0',
					$this->get_source_label( isset( $row['source'] ) ? (string) $row['source'] : '', isset( $row['referred_by_code'] ) ? (string) $row['referred_by_code'] : '' ),
					isset( $row['user_source'] ) ? $row['user_source'] : '',
					isset( $row['status'] ) ? $row['status'] : '',
					isset( $row['remarks'] ) ? $row['remarks'] : '',
					isset( $row['registered_at'] ) ? $row['registered_at'] : '',
				),
				',',
				'"',
				''
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Capture referral code from URL.
	 *
	 * @return void
	 */
	public function capture_referral_code() {
		if ( ! isset( $_GET['ref'] ) ) {
			return;
		}

		$referral_code = strtoupper( sanitize_text_field( wp_unslash( $_GET['ref'] ) ) );
		if ( '' === $referral_code ) {
			return;
		}

		$this->set_cookie( self::REFERRAL_COOKIE, $referral_code, time() + MONTH_IN_SECONDS );
	}

	/**
	 * Handle frontend POST actions.
	 *
	 * @return void
	 */
	public function maybe_handle_front_actions() {
		if ( 'POST' !== strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) || empty( $_POST['wperf_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['wperf_action'] ) );

		if ( 'login' === $action ) {
			$this->handle_front_login();
		}

		if ( 'register' === $action ) {
			$this->handle_front_register();
		}
	}

	/**
	 * Handle phone login.
	 *
	 * @return void
	 */
	private function handle_front_login() {
		if ( ! isset( $_POST['wperf_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wperf_login_nonce'] ) ), 'wperf_front_login' ) ) {
			$this->safe_redirect_with_notice( 'login', 'invalid_request' );
		}

		$phone    = isset( $_POST['wperf_phone'] ) ? $this->normalize_phone( wp_unslash( $_POST['wperf_phone'] ) ) : '';
		$password = isset( $_POST['wperf_password'] ) ? (string) wp_unslash( $_POST['wperf_password'] ) : '';

		if ( '' === $phone || '' === $password ) {
			$this->safe_redirect_with_notice( 'login', 'missing_fields' );
		}

		$user = $this->get_user_by_phone( $phone );
		if ( ! $user instanceof WP_User ) {
			$this->safe_redirect_with_notice( 'login', 'invalid_login' );
		}

		$login_user = wp_signon(
			array(
				'user_login'    => $user->user_login,
				'user_password' => $password,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $login_user ) ) {
			$this->safe_redirect_with_notice( 'login', 'invalid_login' );
		}

		wp_safe_redirect( $this->get_dashboard_url() );
		exit;
	}

	/**
	 * Handle frontend registration.
	 *
	 * @return void
	 */
	private function handle_front_register() {
		if ( ! isset( $_POST['wperf_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wperf_register_nonce'] ) ), 'wperf_front_register' ) ) {
			$this->safe_redirect_with_notice( 'register', 'invalid_request' );
		}

		$referral_user_name  = isset( $_POST['wperf_referral_user_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wperf_referral_user_name'] ) ) : '';
		$referral_user_phone = isset( $_POST['wperf_referral_user_phone'] ) ? $this->normalize_phone( wp_unslash( $_POST['wperf_referral_user_phone'] ) ) : '';
		$display_name        = isset( $_POST['wperf_display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wperf_display_name'] ) ) : '';
		$email               = isset( $_POST['wperf_email'] ) ? sanitize_email( wp_unslash( $_POST['wperf_email'] ) ) : '';
		$phone               = isset( $_POST['wperf_phone'] ) ? $this->normalize_phone( wp_unslash( $_POST['wperf_phone'] ) ) : '';
		$password            = isset( $_POST['wperf_password'] ) ? (string) wp_unslash( $_POST['wperf_password'] ) : '';
		$referred_by_code    = isset( $_POST['wperf_referred_by_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['wperf_referred_by_code'] ) ) ) : '';
		$form_context        = isset( $_POST['wperf_form_context'] ) ? sanitize_key( wp_unslash( $_POST['wperf_form_context'] ) ) : 'default';
		$user_source         = isset( $_POST['wperf_user_source'] ) ? sanitize_text_field( wp_unslash( $_POST['wperf_user_source'] ) ) : '';
		$terms_consent       = isset( $_POST['wperf_terms_consent'] ) ? absint( wp_unslash( $_POST['wperf_terms_consent'] ) ) : 0;
		$wants_to_refer      = isset( $_POST['wperf_wants_to_refer'] ) && 'yes' === sanitize_key( wp_unslash( $_POST['wperf_wants_to_refer'] ) );

		if ( '' === $display_name || '' === $phone ) {
			$this->safe_redirect_with_notice( 'register', 'missing_fields' );
		}

		if ( '' === $phone ) {
			$this->safe_redirect_with_notice( 'register', 'phone_required' );
		}

		if ( 'default' === $form_context ) {
			if ( ! $wants_to_refer ) {
				$referral_user_name  = '';
				$referral_user_phone = '';
			} elseif ( '' === $referral_user_phone ) {
				$this->safe_redirect_with_notice( 'register', 'referral_phone_required' );
			} elseif ( $referral_user_phone === $phone ) {
				$this->safe_redirect_with_notice( 'register', 'referral_phone_matches' );
			}
		}

		if ( '' === $referred_by_code && 'default' !== $form_context ) {
			$referred_by_code = $this->maybe_get_referral_code_from_manual_fields( $referral_user_name, $referral_user_phone );
		}

		if ( '' !== $referred_by_code && $this->get_user_id_by_referral_code( $referred_by_code ) <= 0 ) {
			$this->safe_redirect_with_notice( 'register', 'invalid_referral' );
		}

		if ( 'referred' === $form_context || 'dashboard_referral' === $form_context ) {
			if ( 'dashboard_referral' === $form_context ) {
				$current_user       = wp_get_current_user();
				$current_user_phone = $current_user instanceof WP_User ? $this->normalize_phone( (string) get_user_meta( $current_user->ID, self::META_PHONE, true ) ) : '';

				if ( '' !== $current_user_phone && $current_user_phone === $phone ) {
					$this->safe_redirect_with_notice( 'register', 'referral_phone_matches' );
				}
				$username = $this->generate_unique_username( $display_name, '', $phone );
				$user_id  = wp_insert_user(
					array(
						'user_login'   => $username,
						'user_pass'    => wp_generate_password( 20, true, true ),
						'user_email'   => '',
						'display_name' => $display_name,
						'first_name'   => $display_name,
						'role'         => self::ROLE_KEY,
					)
				);

				if ( is_wp_error( $user_id ) ) {
					$this->safe_redirect_with_notice( 'register', 'registration_failed' );
				}

				update_user_meta( $user_id, self::META_PHONE, $phone );
				update_user_meta( $user_id, self::META_REFERRAL_USER_NAME, $display_name );
				update_user_meta( $user_id, self::META_REFERRAL_USER_PHONE, $phone );
				$this->handle_user_register( $user_id );
				$this->apply_referral_relationship( $user_id, $referred_by_code );

				$this->insert_registration_entry(
					array(
						'user_id'              => $user_id,
						'name'                 => $display_name,
						'email'                => '',
						'phone'                => $phone,
						'referral_user_name'   => $display_name,
						'referral_user_phone'  => $phone,
						'referral_code'        => (string) get_user_meta( $user_id, self::META_REFERRAL_ID, true ),
						'referred_by_code'     => (string) get_user_meta( $user_id, self::META_REFERRED_BY_CODE, true ),
						'source'               => 'referred',
						'user_source'          => $user_source,
					)
				);

				wp_safe_redirect( add_query_arg( array( 'wperf_notice' => 'referral_added' ), $this->get_dashboard_url() ) );
				exit;
			}

			$phone_exists = $this->phone_exists_anywhere( $phone );

			if ( ! $phone_exists ) {
				$phone_exists = $this->referral_entry_exists_by_phone( $phone, $referred_by_code );
			}

			if ( $phone_exists ) {
				$this->safe_redirect_with_notice( 'register', 'phone_exists' );
			}

			$this->insert_registration_entry(
				array(
					'user_id'             => 0,
					'name'                => $display_name,
					'email'               => '',
					'phone'               => $phone,
					'referral_user_name'  => $display_name,
					'referral_user_phone' => $phone,
					'referral_code'       => '',
					'referred_by_code'    => $referred_by_code,
					'source'              => 'referred',
					'user_source'         => $user_source,
				)
			);

			wp_safe_redirect( add_query_arg( array( 'ref' => rawurlencode( $referred_by_code ), 'wperf_thanks' => '1' ), $this->get_shared_registration_page_url() ) );
			exit;
		}


		if ( 1 !== $terms_consent ) {
			$this->safe_redirect_with_notice( 'register', 'terms_required' );
		}

		if ( '' === $email || '' === $password ) {
			$this->safe_redirect_with_notice( 'register', 'missing_fields' );
		}

		if ( ! is_email( $email ) ) {
			$this->safe_redirect_with_notice( 'register', 'invalid_email' );
		}

		if ( strlen( $password ) < 6 ) {
			$this->safe_redirect_with_notice( 'register', 'weak_password' );
		}

		if ( email_exists( $email ) ) {
			$this->safe_redirect_with_notice( 'register', 'email_exists' );
		}

		if ( $this->phone_exists_anywhere( $phone ) ) {
			$this->safe_redirect_with_notice( 'register', 'phone_exists' );
		}

		$username = $this->generate_unique_username( $display_name, $email, $phone );
		$user_id  = wp_insert_user(
			array(
				'user_login'   => $username,
				'user_pass'    => $password,
				'user_email'   => $email,
				'display_name' => $display_name,
				'first_name'   => $display_name,
				'role'         => self::ROLE_KEY,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$this->safe_redirect_with_notice( 'register', 'registration_failed' );
		}

		update_user_meta( $user_id, self::META_PHONE, $phone );
		update_user_meta( $user_id, self::META_REFERRAL_USER_NAME, $referral_user_name );
		update_user_meta( $user_id, self::META_REFERRAL_USER_PHONE, $referral_user_phone );
		$this->handle_user_register( $user_id );
		$this->apply_referral_relationship( $user_id, $referred_by_code );
		$this->insert_registration_entry(
			array(
				'user_id'              => $user_id,
				'name'                 => $display_name,
				'email'                => $email,
				'phone'                => $phone,
				'referral_user_name'   => '',
				'referral_user_phone'  => '',
				'referral_code'        => (string) get_user_meta( $user_id, self::META_REFERRAL_ID, true ),
				'referred_by_code'     => (string) get_user_meta( $user_id, self::META_REFERRED_BY_CODE, true ),
				'source'               => '' !== (string) get_user_meta( $user_id, self::META_REFERRED_BY_CODE, true ) ? 'referred' : 'direct',
				'user_source'          => $user_source,
			)
		);

		if ( $wants_to_refer && '' !== $referral_user_phone ) {
			$referral_owner_code = (string) get_user_meta( $user_id, self::META_REFERRAL_ID, true );
			$referral_lead_phone = $referral_user_phone;

			$this->insert_registration_entry(
				array(
					'user_id'             => 0,
					'name'                => $referral_user_name,
					'email'               => '',
					'phone'               => $referral_lead_phone,
					'referral_user_name'  => $referral_user_name,
					'referral_user_phone' => $referral_lead_phone,
					'referral_code'       => '',
					'referred_by_code'    => $referral_owner_code,
					'source'              => 'referred',
					'user_source'         => $user_source,
				)
			);
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
		wp_safe_redirect( $this->get_dashboard_url() );
		exit;
	}

	/**
	 * Start Google login by redirecting to Google.
	 *
	 * @return void
	 */
	public function maybe_start_google_login() {
		if ( ! isset( $_GET['wperf_google_action'] ) || 'start' !== sanitize_key( wp_unslash( $_GET['wperf_google_action'] ) ) ) {
			return;
		}

		if ( ! $this->is_google_login_configured() ) {
			$this->safe_redirect_with_notice( 'login', 'google_not_configured' );
		}

		$state = wp_generate_password( 32, false, false );
		set_transient(
			'wperf_google_state_' . $state,
			array(
				'referral_code' => $this->get_current_referral_code(),
			),
			10 * MINUTE_IN_SECONDS
		);

		$auth_url = add_query_arg(
			array(
				'client_id'     => $this->get_google_client_id(),
				'redirect_uri'  => $this->get_google_redirect_uri(),
				'response_type' => 'code',
				'scope'         => 'openid email profile',
				'state'         => $state,
				'prompt'        => 'select_account',
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);

		wp_safe_redirect( $auth_url );
		exit;
	}

	/**
	 * Handle Google callback.
	 *
	 * @return void
	 */
	public function maybe_handle_google_callback() {
		if ( ! isset( $_GET['wperf_google_login'] ) ) {
			return;
		}

		if ( ! $this->is_google_login_configured() ) {
			$this->safe_redirect_with_notice( 'login', 'google_not_configured' );
		}

		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( '' === $state || '' === $code ) {
			$this->safe_redirect_with_notice( 'login', 'google_state_invalid' );
		}

		$state_data = get_transient( 'wperf_google_state_' . $state );
		if ( ! is_array( $state_data ) ) {
			$this->safe_redirect_with_notice( 'login', 'google_state_invalid' );
		}
		delete_transient( 'wperf_google_state_' . $state );

		$token_response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => $this->get_google_client_id(),
					'client_secret' => $this->get_google_client_secret(),
					'redirect_uri'  => $this->get_google_redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $token_response ) ) {
			$this->safe_redirect_with_notice( 'login', 'google_token_failed' );
		}

		$token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
		if ( empty( $token_body['access_token'] ) ) {
			$this->safe_redirect_with_notice( 'login', 'google_token_missing' );
		}

		$userinfo_response = wp_remote_get(
			'https://www.googleapis.com/oauth2/v3/userinfo',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . sanitize_text_field( $token_body['access_token'] ),
				),
			)
		);

		if ( is_wp_error( $userinfo_response ) ) {
			$this->safe_redirect_with_notice( 'login', 'google_userinfo_failed' );
		}

		$userinfo     = json_decode( wp_remote_retrieve_body( $userinfo_response ), true );
		$email        = isset( $userinfo['email'] ) ? sanitize_email( $userinfo['email'] ) : '';
		$display_name = isset( $userinfo['name'] ) ? sanitize_text_field( $userinfo['name'] ) : '';
		$google_sub   = isset( $userinfo['sub'] ) ? sanitize_text_field( $userinfo['sub'] ) : '';

		if ( '' === $email ) {
			$this->safe_redirect_with_notice( 'login', 'google_email_missing' );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user instanceof WP_User && '' !== $google_sub ) {
			$user = $this->get_user_by_google_sub( $google_sub );
		}

		$is_new_user = false;
		if ( ! $user instanceof WP_User ) {
			$username = $this->generate_unique_username( $display_name, $email, '' );
			$user_id  = wp_insert_user(
				array(
					'user_login'   => $username,
					'user_pass'    => wp_generate_password( 20, true, true ),
					'user_email'   => $email,
					'display_name' => '' !== $display_name ? $display_name : $email,
					'first_name'   => $display_name,
					'role'         => self::ROLE_KEY,
				)
			);

			if ( is_wp_error( $user_id ) ) {
				$this->safe_redirect_with_notice( 'login', 'registration_failed' );
			}

			$this->handle_user_register( $user_id );
			$this->apply_referral_relationship( $user_id, isset( $state_data['referral_code'] ) ? (string) $state_data['referral_code'] : '' );
			$user        = get_userdata( $user_id );
			$is_new_user = true;
		}

		if ( ! $user instanceof WP_User ) {
			$this->safe_redirect_with_notice( 'login', 'invalid_login' );
		}

		if ( ! in_array( self::ROLE_KEY, (array) $user->roles, true ) ) {
			$user->add_role( self::ROLE_KEY );
		}

		if ( '' !== $google_sub ) {
			update_user_meta( $user->ID, self::META_GOOGLE_SUB, $google_sub );
		}
		update_user_meta( $user->ID, self::META_GOOGLE_LINKED, 1 );

		if ( $is_new_user ) {
			$this->insert_registration_entry(
				array(
					'user_id'              => $user->ID,
					'name'                 => $user->display_name,
					'email'                => $user->user_email,
					'phone'                => (string) get_user_meta( $user->ID, self::META_PHONE, true ),
					'referral_user_name'   => '',
					'referral_user_phone'  => '',
					'referral_code'        => (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true ),
					'referred_by_code'     => (string) get_user_meta( $user->ID, self::META_REFERRED_BY_CODE, true ),
					'source'               => '' !== (string) get_user_meta( $user->ID, self::META_REFERRED_BY_CODE, true ) ? 'referred' : 'direct',
				)
			);
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
		wp_safe_redirect( $this->get_dashboard_url() );
		exit;
	}

	/**
	 * Initialize user meta after registration.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function handle_user_register( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User || ! in_array( self::ROLE_KEY, (array) $user->roles, true ) ) {
			return;
		}

		if ( '' === (string) get_user_meta( $user_id, self::META_REFERRAL_ID, true ) ) {
			update_user_meta( $user_id, self::META_REFERRAL_ID, $this->generate_unique_referral_id( $user_id ) );
		}

		if ( '' === (string) get_user_meta( $user_id, self::META_SHARE_CLICKS, true ) ) {
			update_user_meta( $user_id, self::META_SHARE_CLICKS, 0 );
		}

		if ( '' === (string) get_user_meta( $user_id, self::META_REFERRALS_COUNT, true ) ) {
			update_user_meta( $user_id, self::META_REFERRALS_COUNT, 0 );
		}

		if ( '' === (string) get_user_meta( $user_id, self::META_DISCOUNT_CREDITS, true ) ) {
			update_user_meta( $user_id, self::META_DISCOUNT_CREDITS, 0 );
		}
	}

	/**
	 * Apply referral relationship.
	 *
	 * @param int    $user_id       User ID.
	 * @param string $referral_code Referral code.
	 * @return void
	 */
	private function apply_referral_relationship( $user_id, $referral_code ) {
		$referral_code = strtoupper( sanitize_text_field( (string) $referral_code ) );
		if ( '' === $referral_code ) {
			$referral_code = $this->get_current_referral_code();
		}

		if ( '' === $referral_code ) {
			update_user_meta( $user_id, self::META_REFERRAL_PROCESSED, 1 );
			return;
		}

		if ( '' !== (string) get_user_meta( $user_id, self::META_REFERRED_BY_CODE, true ) ) {
			return;
		}

		$referrer_id = $this->get_user_id_by_referral_code( $referral_code );
		if ( $referrer_id <= 0 || (int) $referrer_id === (int) $user_id ) {
			update_user_meta( $user_id, self::META_REFERRAL_PROCESSED, 1 );
			return;
		}

		update_user_meta( $user_id, self::META_REFERRED_BY_USER_ID, $referrer_id );
		update_user_meta( $user_id, self::META_REFERRED_BY_CODE, $referral_code );
		$this->increment_user_meta_int( $referrer_id, self::META_REFERRALS_COUNT, 1 );
		$this->increment_user_meta_int( $referrer_id, self::META_DISCOUNT_CREDITS, 1 );
		$this->increment_user_meta_int( $user_id, self::META_DISCOUNT_CREDITS, 1 );
		update_user_meta( $user_id, self::META_REFERRAL_PROCESSED, 1 );
	}

	/**
	 * Resolve referral code from manually entered referral user data.
	 *
	 * @param string $referral_user_name  Referral user name.
	 * @param string $referral_user_phone Referral user phone.
	 * @return string
	 */
	private function maybe_get_referral_code_from_manual_fields( $referral_user_name, $referral_user_phone ) {
		if ( '' === $referral_user_phone ) {
			return '';
		}

		$user = $this->get_user_by_phone( $referral_user_phone );
		if ( ! $user instanceof WP_User ) {
			return '';
		}

		if ( '' !== $referral_user_name ) {
			$left  = strtolower( preg_replace( '/\s+/', ' ', trim( $referral_user_name ) ) );
			$right = strtolower( preg_replace( '/\s+/', ' ', trim( (string) $user->display_name ) ) );
			if ( $left !== $right ) {
				return '';
			}
		}

		return (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true );
	}

	/**
	 * Check whether a referral user already has the phone number.
	 *
	 * @param string $phone Phone number.
	 * @return bool
	 */
	private function referral_user_exists_by_phone( $phone ) {
		$candidates = $this->get_phone_lookup_candidates( $phone );
		if ( empty( $candidates ) ) {
			return false;
		}

		foreach ( $candidates as $candidate ) {
			$users = get_users(
				array(
					'role'        => self::ROLE_KEY,
					'number'      => 1,
					'count_total' => false,
					'fields'      => 'ids',
					'meta_key'    => self::META_PHONE,
					'meta_value'  => $candidate,
				)
			);

			if ( ! empty( $users ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if phone exists in users or referral entries.
	 *
	 * @param string $phone Phone number.
	 * @return bool
	 */
	private function phone_exists_anywhere( $phone ) {
		global $wpdb;

		$phone = $this->normalize_phone( (string) $phone );
		if ( '' === $phone ) {
			return false;
		}

		if ( $this->referral_user_exists_by_phone( $phone ) ) {
			return true;
		}

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE phone = %s OR referral_user_phone = %s",
				$phone,
				$phone
			)
		);

		return $count > 0;
	}

	/**
	 * Check duplicate referral lead by phone and referral code.
	 *
	 * @param string $phone Phone number.
	 * @param string $referred_by_code Referral code.
	 * @return bool
	 */
	private function referral_entry_exists_by_phone( $phone, $referred_by_code = '' ) {
		global $wpdb;

		$phone = $this->normalize_phone( (string) $phone );
		if ( '' === $phone ) {
			return false;
		}

		if ( '' !== $referred_by_code ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} WHERE phone = %s AND referred_by_code = %s", $phone, sanitize_text_field( (string) $referred_by_code ) ) );
			return $count > 0;
		}

		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} WHERE phone = %s", $phone ) );
		return $count > 0;
	}


	/**
	 * Insert entry into custom registrations table.
	 *
	 * @param array $data Registration data.
	 * @return void
	 */
	private function insert_registration_entry( $data ) {
		global $wpdb;

		$wpdb->insert(
			$this->table_name,
			array(
				'user_id'             => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0,
				'name'                => isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '',
				'email'               => isset( $data['email'] ) ? sanitize_email( (string) $data['email'] ) : '',
				'phone'               => isset( $data['phone'] ) ? $this->normalize_phone( (string) $data['phone'] ) : '',
				'referral_user_name'  => isset( $data['referral_user_name'] ) ? sanitize_text_field( (string) $data['referral_user_name'] ) : '',
				'referral_user_phone' => isset( $data['referral_user_phone'] ) ? $this->normalize_phone( (string) $data['referral_user_phone'] ) : '',
				'referral_code'       => isset( $data['referral_code'] ) ? sanitize_text_field( (string) $data['referral_code'] ) : '',
				'referred_by_code'    => isset( $data['referred_by_code'] ) ? sanitize_text_field( (string) $data['referred_by_code'] ) : '',
				'share_clicks'        => isset( $data['share_clicks'] ) ? absint( $data['share_clicks'] ) : 0,
				'source'              => isset( $data['source'] ) ? sanitize_key( (string) $data['source'] ) : 'manual',
				'user_source'         => isset( $data['user_source'] ) ? sanitize_text_field( (string) $data['user_source'] ) : '',
				'registered_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Block referral users from wp-admin.
	 *
	 * @return void
	 */
	public function maybe_block_referral_user_admin() {
		if ( ! is_user_logged_in() || ! $this->current_user_is_referral_user() ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			return;
		}

		global $pagenow;
		if ( 'admin-ajax.php' === $pagenow ) {
			return;
		}

		wp_safe_redirect( $this->get_dashboard_url() );
		exit;
	}

	/**
	 * Hide admin bar for referral users.
	 *
	 * @param bool $show Current value.
	 * @return bool
	 */
	public function maybe_hide_admin_bar( $show ) {
		if ( $this->current_user_is_referral_user() ) {
			return false;
		}

		return $show;
	}

	/**
	 * Login redirect filter.
	 *
	 * @param string           $redirect_to           Redirect URL.
	 * @param string           $requested_redirect_to Requested redirect URL.
	 * @param WP_User|WP_Error $user                  User object.
	 * @return string
	 */
	public function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		if ( $user instanceof WP_User && in_array( self::ROLE_KEY, (array) $user->roles, true ) ) {
			return $this->get_dashboard_url();
		}

		return $redirect_to;
	}

	/**
	 * Add user list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_users_columns( $columns ) {
		$columns['wperf_phone']       = __( 'Phone', 'wp-easy-referral' );
		$columns['wperf_referral_id'] = __( 'Referral Code', 'wp-easy-referral' );
		$columns['wperf_share_clicks'] = __( 'Share Clicks', 'wp-easy-referral' );

		return $columns;
	}

	/**
	 * Render custom user columns.
	 *
	 * @param string $output      Existing output.
	 * @param string $column_name Column name.
	 * @param int    $user_id     User ID.
	 * @return string
	 */
	public function render_users_custom_column( $output, $column_name, $user_id ) {
		if ( 'wperf_phone' === $column_name ) {
			return esc_html( (string) get_user_meta( $user_id, self::META_PHONE, true ) );
		}

		if ( 'wperf_referral_id' === $column_name ) {
			return esc_html( (string) get_user_meta( $user_id, self::META_REFERRAL_ID, true ) );
		}

		if ( 'wperf_share_clicks' === $column_name ) {
			return esc_html( (string) (int) get_user_meta( $user_id, self::META_SHARE_CLICKS, true ) );
		}

		return $output;
	}

	/**
	 * Render frontend auth tabs.
	 *
	 * Shortcode usage:
	 * [wperf_auth_tabs]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_auth_tabs( $atts ) {
		$atts = shortcode_atts(
			array(
				'login_title'    => __( 'Login', 'wp-easy-referral' ),
				'register_title' => __( 'Register', 'wp-easy-referral' ),
				'class'          => '',
			),
			$atts,
			'wperf_auth_tabs'
		);

		if ( $this->is_elementor_editor_request() ) {
			return '<div class="wperf-card ' . esc_attr( $atts['class'] ) . '"><div class="wperf-logged-in"><h3 class="wperf-title">' . esc_html__( 'WP Easy Referral Form', 'wp-easy-referral' ) . '</h3><p class="wperf-copy">' . esc_html__( 'The form is disabled in Elementor editor preview to avoid builder script conflicts. View the page on the frontend to use the live login and registration form.', 'wp-easy-referral' ) . '</p></div></div>';
		}

		wp_enqueue_style( 'wperf-auth-system' );
		wp_enqueue_script( 'wperf-auth-system' );

		if ( is_user_logged_in() && $this->current_user_is_referral_user() ) {
			ob_start();
			?>
			<div class="wperf-card <?php echo esc_attr( $atts['class'] ); ?>">
				<div class="wperf-logged-in">
					<h3 class="wperf-title"><?php esc_html_e( 'Welcome back', 'wp-easy-referral' ); ?></h3>
					<p class="wperf-copy"><?php esc_html_e( 'You are already logged in.', 'wp-easy-referral' ); ?></p>
					<div class="wperf-actions">
						<a class="wperf-btn" href="<?php echo esc_url( $this->get_dashboard_url() ); ?>"><?php esc_html_e( 'Go to Dashboard', 'wp-easy-referral' ); ?></a>
						<a class="wperf-btn wperf-btn-secondary" href="<?php echo esc_url( wp_logout_url( $this->get_registration_page_url() ) ); ?>"><?php esc_html_e( 'Logout', 'wp-easy-referral' ); ?></a>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$notice_code = isset( $_GET['wperf_notice'] ) ? sanitize_key( wp_unslash( $_GET['wperf_notice'] ) ) : '';
		$active_tab  = isset( $_GET['wperf_tab'] ) ? sanitize_key( wp_unslash( $_GET['wperf_tab'] ) ) : 'register';
		$active_tab  = in_array( $active_tab, array( 'login', 'register' ), true ) ? $active_tab : 'register';
		$ref_data    = $this->get_current_referrer_data();
		$ref_code    = isset( $ref_data['code'] ) ? (string) $ref_data['code'] : '';
		
		ob_start();
		?>
		<div class="wperf-card wperf-tabs-wrap <?php echo esc_attr( $atts['class'] ); ?>" data-wperf-tabs>
			<div class="wperf-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Authentication tabs', 'wp-easy-referral' ); ?>">
				<button type="button" class="wperf-tab-btn <?php echo ( 'register' === $active_tab ) ? 'is-active' : ''; ?>" data-tab-target="register"><?php echo esc_html( $atts['register_title'] ); ?></button>
				<button type="button" class="wperf-tab-btn <?php echo ( 'login' === $active_tab ) ? 'is-active' : ''; ?>" data-tab-target="login"><?php echo esc_html( $atts['login_title'] ); ?></button>
			</div>

			<div class="wperf-tab-panel <?php echo ( 'login' === $active_tab ) ? 'is-active' : ''; ?>" id="wperf-panel-login">
				<h3 class="wperf-title"><?php echo esc_html( $atts['login_title'] ); ?></h3>
				<p class="wperf-copy"><?php esc_html_e( 'Log in with your mobile number and password.', 'wp-easy-referral' ); ?></p>
				<?php if ( '' !== $notice_code && 'login' === $active_tab ) : ?>
					<div class="wperf-notice wperf-notice-error"><?php echo esc_html( $this->get_notice_message( $notice_code ) ); ?></div>
				<?php endif; ?>
				<?php echo $this->render_phone_login_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->render_google_login_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<div class="wperf-tab-panel <?php echo ( 'register' === $active_tab ) ? 'is-active' : ''; ?>" id="wperf-panel-register">
				<h3 class="wperf-title"><?php echo esc_html( $atts['register_title'] ); ?></h3>
				<p class="wperf-copy"><?php esc_html_e( 'Create your account. Your referral code will be generated automatically.', 'wp-easy-referral' ); ?></p>
				<?php if ( '' !== $notice_code && 'register' === $active_tab ) : ?>
					<div class="wperf-notice wperf-notice-error"><?php echo esc_html( $this->get_notice_message( $notice_code ) ); ?></div>
				<?php endif; ?>
				<form class="wperf-register-form" method="post" action="" style="margin-top:24px;" data-wperf-phone-check-form data-wperf-phone-check-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-wperf-account-phone="#wperf_register_phone" data-wperf-skip-duplicate-check>
<p>
						<label for="wperf_display_name"><?php esc_html_e( 'Name', 'wp-easy-referral' ); ?></label>
						<input type="text" id="wperf_display_name" name="wperf_display_name" required />
					</p>
					<p>
						<label for="wperf_email"><?php esc_html_e( 'Email Address', 'wp-easy-referral' ); ?></label>
						<input type="email" id="wperf_email" name="wperf_email" required />
					</p>
					<p>
						<label for="wperf_register_phone"><?php esc_html_e( 'Mobile Number', 'wp-easy-referral' ); ?></label>
						<input type="text" id="wperf_register_phone" name="wperf_phone" required />
					</p>
					<p>
						<label for="wperf_register_password"><?php esc_html_e( 'Password', 'wp-easy-referral' ); ?></label>
						<input type="password" id="wperf_register_password" name="wperf_password" minlength="6" required /><small class="wperf-field-hint"><?php esc_html_e( 'Minimum 6 characters', 'wp-easy-referral' ); ?></small>
					</p>
							<p class="wperf-referral-toggle">
								<label for="wperf_wants_to_refer"><?php esc_html_e( 'Do you want to refer someone?', 'wp-easy-referral' ); ?></label>
								<span class="wperf-checkbox-option">
									<input type="checkbox" id="wperf_wants_to_refer" name="wperf_wants_to_refer" value="yes" data-wperf-referral-toggle />
									<span><?php esc_html_e( 'Yes', 'wp-easy-referral' ); ?></span>
								</span>
							</p>
							<div class="wperf-referral-highlight" data-wperf-referral-fields hidden>
								<div class="wperf-referral-grid">
									<p>
										<label for="wperf_referral_user_name"><?php esc_html_e( 'Referral User Name (optional)', 'wp-easy-referral' ); ?></label>
										<input type="text" id="wperf_referral_user_name" name="wperf_referral_user_name" value="" />
									</p>
									<p>
										<label for="wperf_referral_user_phone"><?php esc_html_e( "Referral's Phone Number", 'wp-easy-referral' ); ?></label>
										<input type="text" id="wperf_referral_user_phone" name="wperf_referral_user_phone" value="" data-wperf-phone-check />
										<small class="wperf-field-error" data-wperf-phone-error aria-live="polite"></small>
									</p>
								</div>
							</div>
					<input type="hidden" name="wperf_referred_by_code" value="<?php echo esc_attr( $ref_code ); ?>" />
					<input type="hidden" name="wperf_action" value="register" />
					<input type="hidden" name="wperf_user_source" value="<?php echo esc_attr( $this->get_current_user_source() ); ?>" />
					<?php wp_nonce_field( 'wperf_front_register', 'wperf_register_nonce' ); ?>
					<?php wp_nonce_field( 'wperf_phone_check', 'wperf_phone_check_nonce', false ); ?>
					<div class="wperf-consent-field">
								<input type="checkbox" id="wperf_terms_consent" class="wperf-terms-checkbox" name="wperf_terms_consent" value="1" required />
								<label class="wperf-consent-label" for="wperf_terms_consent">
									<?php esc_html_e( 'I agree to the ', 'wp-easy-referral' ); ?><a href="https://campaign.btibd.com/bti-referral-program-terms-and-conditions/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'terms and conditions', 'wp-easy-referral' ); ?></a>
								</label>
							</div>
							<p><button type="submit" class="wperf-btn"><?php esc_html_e( 'Register', 'wp-easy-referral' ); ?></button></p>
				</form>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render referred registration landing page.
	 *
	 * Shortcode usage:
	 * [wperf_referred_register]
	 *
	 * @return string
	 */
	public function render_referred_register_page() {
		$settings    = $this->get_settings();
		$ref_data    = $this->get_current_referrer_data();
		$ref_code    = isset( $ref_data['code'] ) ? (string) $ref_data['code'] : '';
		$notice_code = isset( $_GET['wperf_notice'] ) ? sanitize_key( wp_unslash( $_GET['wperf_notice'] ) ) : '';
		$show_thanks = isset( $_GET['wperf_thanks'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['wperf_thanks'] ) );
		$projects    = $this->get_project_rows();

		wp_enqueue_style( 'wperf-auth-system' );
		wp_enqueue_script( 'wperf-auth-system' );

		ob_start();
		?>
		<div class="wperf-card">
			<div class="wperf-dashboard">
				<?php if ( '' !== (string) $settings['shared_banner_bg_url'] ) : ?>
					<img class="wperf-shared-banner-image wperf-shared-banner-image-desktop" src="<?php echo esc_url( $settings['shared_banner_bg_url'] ); ?>" alt="" />
				<?php endif; ?>
				<?php if ( '' !== (string) $settings['shared_banner_bg_mobile_url'] ) : ?>
					<img class="wperf-shared-banner-image wperf-shared-banner-image-mobile" src="<?php echo esc_url( $settings['shared_banner_bg_mobile_url'] ); ?>" alt="" />
				<?php endif; ?>

				<?php if ( $show_thanks ) : ?>
					<div class="wperf-notice wperf-notice-success" style="font-size:32px;line-height:1.25;"><?php esc_html_e( 'Thank you for sharing your valuable information. Our customer representative will call you shortly.', 'wp-easy-referral' ); ?></div>
				<?php elseif ( '' !== $notice_code ) : ?>
					<div class="wperf-notice wperf-notice-warning"><?php echo esc_html( $this->get_notice_message( $notice_code ) ); ?></div>
				<?php endif; ?>

				<?php if ( ! $show_thanks ) : ?>
				<form class="wperf-register-form" method="post" action="" style="margin-top:25px;">
					<p><label for="wperf_display_name_shared"><?php esc_html_e( 'Name', 'wp-easy-referral' ); ?></label><input type="text" id="wperf_display_name_shared" name="wperf_display_name" required /></p>
					<p><label for="wperf_register_phone_shared"><?php esc_html_e( 'Mobile Number', 'wp-easy-referral' ); ?></label><input type="text" id="wperf_register_phone_shared" name="wperf_phone" required /></p>
					<input type="hidden" name="wperf_referral_user_name" value="<?php echo isset( $ref_data['name'] ) ? esc_attr( $ref_data['name'] ) : ''; ?>" />
					<input type="hidden" name="wperf_referral_user_phone" value="<?php echo isset( $ref_data['phone'] ) ? esc_attr( $ref_data['phone'] ) : ''; ?>" />
					<input type="hidden" name="wperf_referred_by_code" value="<?php echo esc_attr( $ref_code ); ?>" />
					<input type="hidden" name="wperf_form_context" value="referred" />
					<input type="hidden" name="wperf_action" value="register" />
					<input type="hidden" name="wperf_user_source" value="<?php echo esc_attr( $this->get_current_user_source() ); ?>" />
					<?php wp_nonce_field( 'wperf_front_register', 'wperf_register_nonce' ); ?>
					<p><button type="submit" class="wperf-btn"><?php esc_html_e( 'Register', 'wp-easy-referral' ); ?></button></p>
				</form>
				<?php endif; ?>

				<?php if ( '' !== (string) $settings['shared_brochure_url'] ) : ?>
					<div class="wperf-actions wperf-brochure-row"><button type="button" class="wperf-btn" data-wperf-brochure-open="<?php echo esc_url( $settings['shared_brochure_url'] ); ?>"><?php esc_html_e( 'View Brouchure', 'wp-easy-referral' ); ?></button></div>
					<div class="wperf-brochure-modal" hidden>
						<div class="wperf-brochure-dialog">
							<button type="button" class="wperf-brochure-close" data-wperf-brochure-close aria-label="<?php esc_attr_e( 'Close brochure', 'wp-easy-referral' ); ?>">×</button>
							<div class="wperf-brochure-frame-wrap">
								<iframe class="wperf-brochure-frame" src="" title="<?php esc_attr_e( 'Brochure Preview', 'wp-easy-referral' ); ?>"></iframe>
							</div>
							<div class="wperf-brochure-footer">
								<a class="wperf-btn" href="" target="_blank" rel="noopener noreferrer" data-wperf-brochure-download><?php esc_html_e( 'Download Brochure', 'wp-easy-referral' ); ?></a>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $projects ) ) : ?>
					<h4 class="wperf-subtitle"><?php esc_html_e( 'Project with special offer', 'wp-easy-referral' ); ?></h4>
					<table class="wperf-table"><thead><tr><th><?php esc_html_e( 'Project', 'wp-easy-referral' ); ?></th><th><?php esc_html_e( 'Link', 'wp-easy-referral' ); ?></th></tr></thead><tbody>
					<?php foreach ( $projects as $project ) : ?>
						<tr><td><?php echo esc_html( $project['name'] ); ?></td><td><a href="<?php echo esc_url( $project['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'wp-easy-referral' ); ?></a></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php endif; ?>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render phone login form.
	 *
	 * @return string
	 */
	private function render_phone_login_form() {
		ob_start();
		?>
		<form class="wperf-phone-login-form" method="post" action="">
			<p>
				<label for="wperf_user_phone"><?php esc_html_e( 'Mobile Number', 'wp-easy-referral' ); ?></label>
				<input type="text" id="wperf_user_phone" name="wperf_phone" required />
			</p>
			<p>
				<label for="wperf_user_password"><?php esc_html_e( 'Password', 'wp-easy-referral' ); ?></label>
				<input type="password" id="wperf_user_password" name="wperf_password" required />
			</p>
			<input type="hidden" name="wperf_action" value="login" />
			<?php wp_nonce_field( 'wperf_front_login', 'wperf_login_nonce' ); ?>
			<p class="wperf-form-help"><a href="<?php echo esc_url( wp_lostpassword_url( $this->get_registration_page_url() ) ); ?>"><?php esc_html_e( 'Forgot password?', 'wp-easy-referral' ); ?></a></p>
			<p><button type="submit" class="wperf-btn"><?php esc_html_e( 'Login', 'wp-easy-referral' ); ?></button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render Google login button.
	 *
	 * @return string
	 */
	private function render_google_login_button() {
		if ( ! $this->is_google_login_configured() ) {
			return '';
		}

		$start_url = add_query_arg( 'wperf_google_action', 'start', $this->get_registration_page_url() );
		$icon      = '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.6 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.7 1.1 7.8 2.9l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.3-.4-3.5z"></path><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15 18.9 12 24 12c3 0 5.7 1.1 7.8 2.9l5.7-5.7C34 6.1 29.3 4 24 4c-7.7 0-14.4 4.3-17.7 10.7z"></path><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.5-5.2l-6.2-5.2C29.3 35.1 26.8 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.4 39.5 16.1 44 24 44z"></path><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1 2.9-3 5.2-5.9 6.6l.1-.1 6.2 5.2C35.3 39 44 32.5 44 24c0-1.3-.1-2.3-.4-3.5z"></path></svg>';

		return '<div class="wperf-google-login"><a class="wperf-btn wperf-btn-google" href="' . esc_url( $start_url ) . '"><span class="wperf-google-icon">' . $icon . '</span><span>' . esc_html__( 'Login with Google', 'wp-easy-referral' ) . '</span></a></div>';
	}

	/**
	 * Render user dashboard.
	 *
	 * Shortcode usage:
	 * [wperf_user_dashboard]
	 *
	 * @return string
	 */
	public function render_user_dashboard() {
		if ( ! is_user_logged_in() || ! $this->current_user_is_referral_user() ) {
			return '<div class="wperf-notice wperf-notice-warning">' . esc_html__( 'Please log in to view your dashboard.', 'wp-easy-referral' ) . '</div>';
		}

		wp_enqueue_style( 'wperf-auth-system' );
		wp_enqueue_script( 'wperf-auth-system' );

		$user             = wp_get_current_user();
		$settings         = $this->get_settings();
		$referral_id      = (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true );
		$credits          = (int) get_user_meta( $user->ID, self::META_DISCOUNT_CREDITS, true );
		$referrals        = (int) get_user_meta( $user->ID, self::META_REFERRALS_COUNT, true );
		$phone            = (string) get_user_meta( $user->ID, self::META_PHONE, true );
		$brochure_url     = (string) $settings['brochure_url'];
		$share_page_url   = $this->get_share_page_url( $referral_id );
		$landing_reg_link = $this->get_registration_page_url_with_ref( $referral_id );
		$children         = $this->get_direct_referrals( $user->ID );
		$referral_user_name  = (string) get_user_meta( $user->ID, self::META_REFERRAL_USER_NAME, true );
		$referral_user_phone = (string) get_user_meta( $user->ID, self::META_REFERRAL_USER_PHONE, true );
		$share_clicks        = (int) get_user_meta( $user->ID, self::META_SHARE_CLICKS, true );
		$whatsapp_share_url  = $this->get_whatsapp_share_url( $user );
		$facebook_share_url  = $this->get_facebook_share_url( $share_page_url );

		ob_start();
		?>
		<div class="wperf-card">
			<div class="wperf-dashboard">
				<div class="wperf-dashboard-header">
					<div>
						<h3 class="wperf-title"><?php esc_html_e( 'My Referral Dashboard', 'wp-easy-referral' ); ?></h3>
						<p class="wperf-copy"><?php esc_html_e( 'View your profile, brochure, referral code, and referral progress.', 'wp-easy-referral' ); ?></p>
					</div>
					<div class="wperf-actions">
						<a class="wperf-btn wperf-btn-secondary" href="<?php echo esc_url( wp_logout_url( $this->get_registration_page_url() ) ); ?>"><?php esc_html_e( 'Logout', 'wp-easy-referral' ); ?></a>
					</div>
				</div>

				<div class="wperf-stats">
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'Name', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value"><?php echo esc_html( $user->display_name ); ?></div></div>
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'Email', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value wperf-small"><?php echo esc_html( $user->user_email ); ?></div></div>
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'Mobile Number', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value"><?php echo esc_html( '' !== $phone ? $phone : __( 'Not set', 'wp-easy-referral' ) ); ?></div></div>
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'My Referral Code', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value"><?php echo esc_html( $referral_id ); ?></div></div>
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'Successful Referrals', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value"><?php echo esc_html( (string) $referrals ); ?></div></div>
					
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'Share Clicks', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value"><?php echo esc_html( (string) $share_clicks ); ?></div></div>
				</div>

				<div class="wperf-share-section">
					<h4 class="wperf-subtitle"><?php esc_html_e( 'My Referral Card', 'wp-easy-referral' ); ?></h4>
					<a class="wperf-share-card-link" href="<?php echo esc_url( $landing_reg_link ); ?>">
						<div class="wperf-share-card" style="<?php echo esc_attr( $this->get_share_card_style() ); ?>">
							<div class="wperf-share-overlay"></div>
							<div class="wperf-share-content">
								<div class="wperf-share-kicker"><?php esc_html_e( 'Referral Program', 'wp-easy-referral' ); ?></div>
								<h4 class="wperf-share-name"><?php echo esc_html( $user->display_name ); ?></h4>
								<div class="wperf-share-code"><?php echo esc_html( $referral_id ); ?></div>
								<p class="wperf-share-message"><?php echo esc_html( $settings['share_message'] ); ?></p>
							</div>
						</div>
					</a>
					<div class="wperf-share-actions">
						<a class="wperf-btn" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $whatsapp_share_url ); ?>"><?php esc_html_e( 'Share on WhatsApp', 'wp-easy-referral' ); ?></a>
						<a class="wperf-btn wperf-btn-secondary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $facebook_share_url ); ?>"><?php esc_html_e( 'Share on Facebook', 'wp-easy-referral' ); ?></a>
					</div>
					<div class="wperf-share-link-box">
					<strong><?php esc_html_e( 'My Referral Link', 'wp-easy-referral' ); ?></strong>
					<div class="wperf-copy-link-row">
						<input type="text" class="wperf-copy-link-input" value="<?php echo esc_attr( $share_page_url ); ?>" readonly />
						<button type="button" class="wperf-copy-link-btn"><?php esc_html_e( 'Copy', 'wp-easy-referral' ); ?></button>
					</div>
					<em class="wperf-copy-feedback" aria-live="polite"></em>
				</div>
				</div>

				<?php $dashboard_notice = isset( $_GET['wperf_notice'] ) ? sanitize_key( wp_unslash( $_GET['wperf_notice'] ) ) : ''; ?>
				<?php if ( '' !== $dashboard_notice ) : ?>
					<div class="wperf-notice <?php echo ( 'referral_added' === $dashboard_notice ) ? 'wperf-notice-warning' : 'wperf-notice-error'; ?>"><?php echo esc_html( $this->get_notice_message( $dashboard_notice ) ); ?></div>
				<?php endif; ?>

				<?php if ( '' !== $brochure_url ) : ?>
					<div class="wperf-actions wperf-brochure-row">
						<button type="button" class="wperf-btn" data-wperf-brochure-open="<?php echo esc_url( $brochure_url ); ?>"><?php esc_html_e( 'View Brouchure', 'wp-easy-referral' ); ?></button>
					</div>
					<div class="wperf-brochure-modal" hidden>
						<div class="wperf-brochure-dialog">
							<button type="button" class="wperf-brochure-close" data-wperf-brochure-close aria-label="<?php esc_attr_e( 'Close brochure', 'wp-easy-referral' ); ?>">×</button>
							<div class="wperf-brochure-frame-wrap">
								<iframe class="wperf-brochure-frame" src="" title="<?php esc_attr_e( 'Brochure Preview', 'wp-easy-referral' ); ?>"></iframe>
							</div>
							<div class="wperf-brochure-footer">
								<a class="wperf-btn" href="" target="_blank" rel="noopener noreferrer" data-wperf-brochure-download><?php esc_html_e( 'Download Brochure', 'wp-easy-referral' ); ?></a>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<div class="wperf-actions" style="justify-content:flex-end;">
					<button type="button" class="wperf-btn" data-wperf-open-add-referral><?php esc_html_e( 'Add Referral User', 'wp-easy-referral' ); ?></button>
				</div>

				<div class="wperf-add-referral-modal" hidden>
					<div class="wperf-brochure-dialog">
						<button type="button" class="wperf-brochure-close" data-wperf-close-add-referral aria-label="<?php esc_attr_e( 'Close add referral form', 'wp-easy-referral' ); ?>">×</button>
						<form class="wperf-register-form" method="post" action="" data-wperf-phone-check-form data-wperf-phone-check-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-wperf-account-phone="#wperf_dashboard_owner_phone" data-wperf-skip-duplicate-check>
							<p>
								<label for="wperf_add_referral_name"><?php esc_html_e( 'Name', 'wp-easy-referral' ); ?></label>
								<input type="text" id="wperf_add_referral_name" name="wperf_display_name" required placeholder="<?php esc_attr_e( 'Name', 'wp-easy-referral' ); ?>" style="display:block;width:100%;height:50px;padding:0 16px;border:1px solid #d1d5db;border-radius:12px;font-size:15px;box-sizing:border-box;background:#fff;color:#111827;" />
							</p>
							<p>
								<label for="wperf_add_referral_phone"><?php esc_html_e( 'Mobile Number', 'wp-easy-referral' ); ?></label>
								<input type="text" id="wperf_add_referral_phone" name="wperf_phone" required placeholder="<?php esc_attr_e( 'Phone', 'wp-easy-referral' ); ?>" data-wperf-phone-check style="display:block;width:100%;height:50px;padding:0 16px;border:1px solid #d1d5db;border-radius:12px;font-size:15px;box-sizing:border-box;background:#fff;color:#111827;" />
								<small class="wperf-field-error" data-wperf-phone-error aria-live="polite"></small>
							</p>
							<input type="hidden" id="wperf_dashboard_owner_phone" value="<?php echo esc_attr( $phone ); ?>" />
							<input type="hidden" name="wperf_referral_user_name" value="" />
							<input type="hidden" name="wperf_referral_user_phone" value="" />
							<input type="hidden" name="wperf_referred_by_code" value="<?php echo esc_attr( $referral_id ); ?>" />
							<input type="hidden" name="wperf_form_context" value="dashboard_referral" />
							<input type="hidden" name="wperf_action" value="register" />
							<input type="hidden" name="wperf_user_source" value="<?php echo esc_attr( $this->get_current_user_source() ); ?>" />
							<?php wp_nonce_field( 'wperf_front_register', 'wperf_register_nonce' ); ?>
							<?php wp_nonce_field( 'wperf_phone_check', 'wperf_phone_check_nonce', false ); ?>
							<p><button type="submit" class="wperf-btn"><?php esc_html_e( 'Save Referral User', 'wp-easy-referral' ); ?></button></p>
						</form>
					</div>
				</div>

				<h4 class="wperf-subtitle"><?php esc_html_e( 'Your Referral List', 'wp-easy-referral' ); ?></h4>
				<?php if ( empty( $children ) ) : ?>
					<p class="wperf-copy"><?php esc_html_e( 'No referrals yet.', 'wp-easy-referral' ); ?></p>
				<?php else : ?>
					<table class="wperf-table">
						<thead><tr><th><?php esc_html_e( 'Your Referral’s Name', 'wp-easy-referral' ); ?></th><th><?php esc_html_e( 'Referral\'s Phone Number', 'wp-easy-referral' ); ?></th><th><?php esc_html_e( 'Status', 'wp-easy-referral' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $children as $child ) : ?>
								<tr><td><?php echo esc_html( $child['referral_user_name'] ); ?></td><td><?php echo esc_html( $this->mask_lead_phone( $child['referral_user_phone'] ) ); ?></td><td><?php echo esc_html( $child['status'] ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Format entry date for Lead Desk.
	 *
	 * @param string $date Date value.
	 * @return string
	 */
	private function format_lead_desk_date( $date ) {
		$timestamp = strtotime( (string) $date );
		if ( false === $timestamp ) {
			return '';
		}

		return date_i18n( 'j F, Y', $timestamp );
	}

	/**
	 * Mask email address for Lead Desk display.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private function mask_lead_email( $email ) {
		$email = sanitize_email( (string) $email );
		if ( '' === $email || false === strpos( $email, '@' ) ) {
			return '';
		}

		list( $local, $domain ) = explode( '@', $email, 2 );
		$visible = substr( $local, 0, min( 3, strlen( $local ) ) );

		return $visible . str_repeat( '*', max( 3, strlen( $local ) - strlen( $visible ) ) ) . '@' . $domain;
	}

	/**
	 * Mask phone number for Lead Desk display.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	private function mask_lead_phone( $phone ) {
		$phone = preg_replace( '/\D+/', '', (string) $phone );
		if ( '' === $phone ) {
			return '';
		}

		$length = strlen( $phone );
		if ( $length <= 4 ) {
			return str_repeat( '*', $length );
		}

		return substr( $phone, 0, 3 ) . str_repeat( '*', max( 3, $length - 6 ) ) . substr( $phone, -3 );
	}

	/**
	 * Get child referral entries for a referral code.
	 *
	 * @param string $referral_code Referral code.
	 * @return array
	 */
	private function get_lead_desk_child_entries( $referral_code ) {
		global $wpdb;

		$referral_code = sanitize_text_field( (string) $referral_code );
		if ( '' === $referral_code ) {
			return array();
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, registered_at, name, email, phone, status, remarks FROM {$this->table_name} WHERE referred_by_code = %s ORDER BY registered_at DESC",
				$referral_code
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Render Lead Desk page for agents.
	 *
	 * Shortcode usage:
	 * [wperf_lead_desk]
	 *
	 * @return string
	 */
	public function render_lead_desk_page() {
		if ( ! is_user_logged_in() || ! in_array( 'referral_help_agent', (array) wp_get_current_user()->roles, true ) ) {
			ob_start();
			?>
			<div class="wperf-card wperf-agent-login-wrap" style="max-width:500px;margin:50px auto;">
				<div style="padding:40px;">
					<h3 class="wperf-title"><?php esc_html_e( 'Agent Login', 'wp-easy-referral' ); ?></h3>
					<p class="wperf-copy"><?php esc_html_e( 'Please log in to manage referral leads.', 'wp-easy-referral' ); ?></p>
					<?php wp_login_form( array( 'redirect' => $this->get_current_request_url(), 'remember' => false ) ); ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		wp_enqueue_style( 'wperf-auth-system' );
		wp_enqueue_script( 'wperf-auth-system' );

		global $wpdb;
		$lead_search   = isset( $_GET['wperf_lead_search'] ) ? sanitize_text_field( wp_unslash( $_GET['wperf_lead_search'] ) ) : '';
		$per_page      = 50;
		$current_page  = isset( $_GET['wperf_lead_page'] ) ? max( 1, absint( wp_unslash( $_GET['wperf_lead_page'] ) ) ) : 1;
		$where_sql     = "WHERE 1=1";
		$where_values  = array();

		if ( '' === $lead_search ) {
			$where_sql .= " AND referred_by_code = '' AND source = 'direct'";
		}

		if ( '' !== $lead_search ) {
			$like          = '%' . $wpdb->esc_like( $lead_search ) . '%';
			$where_sql    .= ' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s OR referral_code LIKE %s OR referred_by_code LIKE %s OR referral_user_name LIKE %s OR referral_user_phone LIKE %s)';
			$where_values = array( $like, $like, $like, $like, $like, $like, $like );
		}

		$count_query = "SELECT COUNT(*) FROM {$this->table_name} {$where_sql}";
		$total_items = empty( $where_values )
			? (int) $wpdb->get_var( $count_query ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $wpdb->prepare( $count_query, $where_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total_pages  = max( 1, (int) ceil( $total_items / $per_page ) );
		$current_page = min( $current_page, $total_pages );
		$offset       = ( $current_page - 1 ) * $per_page;

		$query_values   = $where_values;
		$query_values[] = $per_page;
		$query_values[] = $offset;
		$query          = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY registered_at DESC LIMIT %d OFFSET %d";
		$entries        = $wpdb->get_results( $wpdb->prepare( $query, $query_values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$statuses       = array( 'Unverified', 'Verified', 'Contacted', 'Pending', 'Ongoing', 'Converted', 'Rejected' );

		ob_start();
		?>
		<div class="wperf-card wperf-lead-desk-card" style="max-width:100%;margin:20px;">
			<div class="wperf-dashboard" style="padding:30px;">
				<div class="wperf-dashboard-header" style="justify-content: space-between; align-items:flex-start; margin-bottom: 20px; display:flex;">
					<div>
						<h3 class="wperf-title"><?php esc_html_e( 'Lead Desk', 'wp-easy-referral' ); ?></h3>
						<p class="wperf-copy"><?php esc_html_e( 'Manage direct leads and view their referred users.', 'wp-easy-referral' ); ?></p>
					</div>
					<div class="wperf-actions" style="margin-top:0;">
						<a class="wperf-btn wperf-btn-secondary" href="<?php echo esc_url( wp_logout_url( $this->get_current_request_url() ) ); ?>"><?php esc_html_e( 'Logout', 'wp-easy-referral' ); ?></a>
					</div>
				</div>

				<form class="wperf-lead-search-form" method="get" action="">
					<p>
						<input type="search" name="wperf_lead_search" value="<?php echo esc_attr( $lead_search ); ?>" placeholder="<?php esc_attr_e( 'Search leads...', 'wp-easy-referral' ); ?>" />
						<button type="submit" class="wperf-btn"><?php esc_html_e( 'Search', 'wp-easy-referral' ); ?></button>
					</p>
				</form>

				<div style="overflow-x:auto;">
					<table class="wperf-table wperf-lead-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'wp-easy-referral' ); ?></th>
								<th><?php esc_html_e( 'Name', 'wp-easy-referral' ); ?></th>
								<th><?php esc_html_e( 'Email', 'wp-easy-referral' ); ?></th>
								<th><?php esc_html_e( 'Phone', 'wp-easy-referral' ); ?></th>
								<th><?php esc_html_e( 'Referral Code', 'wp-easy-referral' ); ?></th>
								<th><?php esc_html_e( 'Status', 'wp-easy-referral' ); ?></th>
								<th><?php esc_html_e( 'Remarks', 'wp-easy-referral' ); ?></th>
								<th><?php esc_html_e( 'Update', 'wp-easy-referral' ); ?></th>
								<th><?php esc_html_e( 'Referred Users', 'wp-easy-referral' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							if ( empty( $entries ) ) {
								echo '<tr><td colspan="9">' . esc_html__( 'No direct entries found.', 'wp-easy-referral' ) . '</td></tr>';
							}
							$row_index = 0;
							foreach ( $entries as $entry ) {
								$row_bg = 0 === ( $row_index % 2 ) ? '#ffffff' : '#f8fafc';
								$children = $this->get_lead_desk_child_entries( (string) $entry->referral_code );
								$modal_id = 'wperf-lead-modal-' . absint( $entry->id );
								$row_index++;
								?>
								<tr style="background:<?php echo esc_attr( $row_bg ); ?>;" data-id="<?php echo esc_attr( $entry->id ); ?>">
									<td style="white-space:nowrap;"><?php echo esc_html( $this->format_lead_desk_date( $entry->registered_at ) ); ?></td>
									<td><?php echo esc_html( $entry->name ); ?></td>
									<td><?php echo esc_html( $this->mask_lead_email( $entry->email ) ); ?></td>
									<td><?php echo esc_html( $this->mask_lead_phone( $entry->phone ) ); ?></td>
									<td><strong><?php echo esc_html( $entry->referral_code ); ?></strong></td>
									<td>
										<select class="wperf-lead-status" style="width:130px;padding:6px;border-radius:6px;border:1px solid #d1d5db;font-family:inherit;font-size:14px;">
											<?php foreach ( $statuses as $status_opt ) : ?>
												<option value="<?php echo esc_attr( $status_opt ); ?>" <?php selected( $entry->status, $status_opt ); ?>><?php echo esc_html( $status_opt ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<textarea class="wperf-lead-remarks" rows="2" style="width:220px;padding:6px;border-radius:6px;border:1px solid #d1d5db;font-family:inherit;font-size:13px;resize:vertical;"><?php echo esc_textarea( (string) $entry->remarks ); ?></textarea>
									</td>
									<td>
										<button type="button" class="wperf-btn wperf-btn-save-lead" style="min-height:32px;padding:0 12px;font-size:13px;"><?php esc_html_e( 'Update', 'wp-easy-referral' ); ?></button>
										<span class="wperf-lead-msg" style="display:block;font-size:12px;color:#059669;margin-top:4px;"></span>
									</td>
									<td>
										<button type="button" class="wperf-btn wperf-btn-secondary wperf-btn-view-referrals" data-target="<?php echo esc_attr( $modal_id ); ?>" style="min-height:32px;padding:0 12px;font-size:13px;"><?php esc_html_e( 'View', 'wp-easy-referral' ); ?> (<?php echo esc_html( (string) count( $children ) ); ?>)</button>
									</td>
								</tr>
								<tr class="wperf-lead-modal-row" id="<?php echo esc_attr( $modal_id ); ?>" hidden>
									<td colspan="9">
										<div class="wperf-lead-popup-panel" style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 10px 25px rgba(15,23,42,.12);">
											<div style="display:flex;justify-content:space-between;align-items:center;gap:15px;margin-bottom:15px;">
												<h4 style="margin:0;"><?php esc_html_e( 'Referred User List', 'wp-easy-referral' ); ?></h4>
												<button type="button" class="wperf-btn wperf-btn-secondary wperf-btn-close-referrals" data-target="<?php echo esc_attr( $modal_id ); ?>" style="min-height:32px;padding:0 12px;font-size:13px;"><?php esc_html_e( 'Close', 'wp-easy-referral' ); ?></button>
											</div>
											<div style="overflow-x:auto;">
												<table class="wperf-table wperf-lead-child-table">
													<thead>
														<tr>
															<th><?php esc_html_e( 'Date', 'wp-easy-referral' ); ?></th>
															<th><?php esc_html_e( 'Name', 'wp-easy-referral' ); ?></th>
															<th><?php esc_html_e( 'Mail', 'wp-easy-referral' ); ?></th>
															<th><?php esc_html_e( 'Phone', 'wp-easy-referral' ); ?></th>
															<th><?php esc_html_e( 'Status', 'wp-easy-referral' ); ?></th>
															<th><?php esc_html_e( 'Remarks', 'wp-easy-referral' ); ?></th>
															<th><?php esc_html_e( 'Update', 'wp-easy-referral' ); ?></th>
														</tr>
													</thead>
													<tbody>
														<?php if ( empty( $children ) ) : ?>
															<tr><td colspan="7"><?php esc_html_e( 'No referred users found.', 'wp-easy-referral' ); ?></td></tr>
														<?php endif; ?>
														<?php foreach ( $children as $child ) : ?>
															<tr data-id="<?php echo esc_attr( $child->id ); ?>">
																<td style="white-space:nowrap;"><?php echo esc_html( $this->format_lead_desk_date( $child->registered_at ) ); ?></td>
																<td><?php echo esc_html( $child->name ); ?></td>
																<td><?php echo esc_html( $this->mask_lead_email( $child->email ) ); ?></td>
																<td><?php echo esc_html( $this->mask_lead_phone( $child->phone ) ); ?></td>
																<td>
																	<select class="wperf-lead-status" style="width:130px;padding:6px;border-radius:6px;border:1px solid #d1d5db;font-family:inherit;font-size:14px;">
																		<?php foreach ( $statuses as $status_opt ) : ?>
																			<option value="<?php echo esc_attr( $status_opt ); ?>" <?php selected( $child->status, $status_opt ); ?>><?php echo esc_html( $status_opt ); ?></option>
																		<?php endforeach; ?>
																	</select>
																</td>
																<td>
																	<textarea class="wperf-lead-remarks" rows="2" style="width:220px;padding:6px;border-radius:6px;border:1px solid #d1d5db;font-family:inherit;font-size:13px;resize:vertical;"><?php echo esc_textarea( (string) $child->remarks ); ?></textarea>
																</td>
																<td>
																	<button type="button" class="wperf-btn wperf-btn-save-lead" style="min-height:32px;padding:0 12px;font-size:13px;"><?php esc_html_e( 'Update', 'wp-easy-referral' ); ?></button>
																	<span class="wperf-lead-msg" style="display:block;font-size:12px;color:#059669;margin-top:4px;"></span>
																</td>
															</tr>
														<?php endforeach; ?>
													</tbody>
												</table>
											</div>
										</div>
									</td>
								</tr>
								<?php
							}
							?>
						</tbody>
					</table>
				</div>

				<?php if ( $total_pages > 1 ) : ?>
					<?php
					$pagination_placeholder = 999999999;
					$pagination_base_url    = remove_query_arg( 'wperf_lead_page', $this->get_current_request_url() );
					$pagination_base        = str_replace(
						(string) $pagination_placeholder,
						'%#%',
						add_query_arg( 'wperf_lead_page', $pagination_placeholder, $pagination_base_url )
					);
					$pagination_links       = paginate_links(
						array(
							'base'      => $pagination_base,
							'format'    => '',
							'current'   => $current_page,
							'total'     => $total_pages,
							'type'      => 'list',
							'prev_text' => __( 'Previous', 'wp-easy-referral' ),
							'next_text' => __( 'Next', 'wp-easy-referral' ),
						)
					);
					?>
					<?php if ( $pagination_links ) : ?>
						<nav class="wperf-lead-pagination" aria-label="<?php esc_attr_e( 'Lead Desk pagination', 'wp-easy-referral' ); ?>">
							<?php echo wp_kses_post( $pagination_links ); ?>
						</nav>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var viewBtns = document.querySelectorAll('.wperf-btn-view-referrals, .wperf-btn-close-referrals');
			viewBtns.forEach(function(btn) {
				btn.addEventListener('click', function() {
					var targetId = btn.getAttribute('data-target');
					var target = document.getElementById(targetId);
					if (!target) {
						return;
					}
					target.hidden = !target.hidden;
				});
			});

			var saveBtns = document.querySelectorAll('.wperf-btn-save-lead');
			saveBtns.forEach(function(btn) {
				btn.addEventListener('click', function() {
					var row = btn.closest('tr');
					var id = row.getAttribute('data-id');
					var status = row.querySelector('.wperf-lead-status').value;
					var remarks = row.querySelector('.wperf-lead-remarks').value;
					var msg = row.querySelector('.wperf-lead-msg');

					btn.disabled = true;
					btn.innerText = 'Saving...';
					msg.innerText = '';

					var formData = new FormData();
					formData.append('action', 'wperf_update_lead');
					formData.append('id', id);
					formData.append('status', status);
					formData.append('remarks', remarks);
					formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'wperf_update_lead' ) ); ?>');

					fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						body: formData
					})
					.then(function(res) { return res.json(); })
					.then(function(data) {
						btn.disabled = false;
						btn.innerText = 'Update';
						if (data.success) {
							msg.innerText = 'Saved!';
							msg.style.color = '#059669';
							setTimeout(function() { msg.innerText = ''; }, 2000);
						} else {
							msg.innerText = 'Error!';
							msg.style.color = '#dc2626';
						}
					})
					.catch(function(err) {
						btn.disabled = false;
						btn.innerText = 'Update';
						msg.innerText = 'Error!';
						msg.style.color = '#dc2626';
					});
				});
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check a referral phone number through AJAX.
	 *
	 * @return void
	 */
	public function ajax_check_phone() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wperf_phone_check' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request. Please refresh and try again.', 'wp-easy-referral' ) ) );
		}

		$phone = isset( $_POST['phone'] ) ? $this->normalize_phone( wp_unslash( $_POST['phone'] ) ) : '';
		if ( '' === $phone ) {
			wp_send_json_success(
				array(
					'valid'   => false,
					'exists'  => false,
					'message' => __( 'Please enter a valid Bangladeshi mobile number.', 'wp-easy-referral' ),
				)
			);
		}

		$exists = $this->phone_exists_anywhere( $phone );
		wp_send_json_success(
			array(
				'valid'   => true,
				'exists'  => $exists,
				'message' => $exists ? __( 'This mobile number is already registered.', 'wp-easy-referral' ) : '',
			)
		);
	}

	/**
	 * AJAX logic to update lead status and remarks.
	 */
	public function ajax_update_lead() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wperf_update_lead' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}
		if ( ! is_user_logged_in() || ! in_array( 'referral_help_agent', (array) wp_get_current_user()->roles, true ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			}
		}

		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$remarks = isset( $_POST['remarks'] ) ? sanitize_textarea_field( wp_unslash( $_POST['remarks'] ) ) : '';

		if ( $id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid ID' ) );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$this->table_name,
			array(
				'status'  => $status,
				'remarks' => $remarks,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false !== $updated ) {
			wp_send_json_success( array( 'message' => 'Updated' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Database error' ) );
		}
	}

	/**
	 * Render virtual dashboard or share page.
	 *
	 * @return void
	 */
	public function render_virtual_pages() {
		if ( get_query_var( self::QUERY_VAR_DASHBOARD ) ) {
			status_header( 200 );
			nocache_headers();
			wp_enqueue_style( 'wperf-auth-system' );
			wp_enqueue_script( 'wperf-auth-system' );
			get_header();
			echo '<main class="wperf-virtual-page">';
			echo $this->render_user_dashboard(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</main>';
			get_footer();
			exit;
		}

		$share_code = get_query_var( self::QUERY_VAR_SHARE_CODE );
		if ( $share_code ) {
			$this->render_share_page( (string) $share_code );
			exit;
		}
	}

	/**
	 * Render share landing page.
	 *
	 * @param string $share_code Referral code.
	 * @return void
	 */
	private function render_share_page( $share_code ) {
		$user_id = $this->get_user_id_by_referral_code( $share_code );
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! $user instanceof WP_User ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			include get_query_template( '404' );
			return;
		}

		wp_enqueue_style( 'wperf-auth-system' );
		$settings = $this->get_settings();
		$cta_url  = $this->get_shared_registration_page_url_with_ref( (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true ) );

		status_header( 200 );
		nocache_headers();
		get_header();
		?>
		<main class="wperf-virtual-page">
			<div class="wperf-card">
				<div class="wperf-dashboard">
					<a class="wperf-share-card-link" href="<?php echo esc_url( $cta_url ); ?>">
						<div class="wperf-share-card" style="<?php echo esc_attr( $this->get_share_card_style() ); ?>">
							<div class="wperf-share-overlay"></div>
							<div class="wperf-share-content">
								<div class="wperf-share-kicker"><?php esc_html_e( 'Referral Program', 'wp-easy-referral' ); ?></div>
								<h1 class="wperf-share-name"><?php echo esc_html( $user->display_name ); ?></h1>
								<div class="wperf-share-code"><?php echo esc_html( (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true ) ); ?></div>
								<p class="wperf-share-message"><?php echo esc_html( $settings['share_message'] ); ?></p>
							</div>
						</div>
					</a>
					<div class="wperf-actions">
						<a class="wperf-btn" href="<?php echo esc_url( $cta_url ); ?>"><?php esc_html_e( 'Register Now', 'wp-easy-referral' ); ?></a>
					</div>
				</div>
			</div>
		</main>
		<?php
		get_footer();
	}

	/**
	 * Output Open Graph meta tags on share page.
	 *
	 * @return void
	 */
	public function output_share_meta_tags() {
		$share_code = get_query_var( self::QUERY_VAR_SHARE_CODE );
		if ( ! $share_code ) {
			return;
		}

		$user_id = $this->get_user_id_by_referral_code( (string) $share_code );
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! $user instanceof WP_User ) {
			return;
		}

		$settings  = $this->get_settings();
		$share_url = $this->get_share_page_url( (string) $share_code );
		$image_url = ! empty( $settings['share_bg_url'] ) ? (string) $settings['share_bg_url'] : '';
		if ( '' === $image_url && ! empty( $settings['shared_banner_bg_url'] ) ) {
			$image_url = (string) $settings['shared_banner_bg_url'];
		}
		if ( '' !== $image_url && 0 === strpos( $image_url, '//' ) ) {
			$image_url = ( is_ssl() ? 'https:' : 'http:' ) . $image_url;
		}
		if ( '' !== $image_url && ! preg_match( '#^https?://#i', $image_url ) ) {
			$image_url = home_url( '/' . ltrim( $image_url, '/' ) );
		}
		if ( '' !== $image_url ) {
			$image_url = set_url_scheme( esc_url_raw( $image_url ), 'https' );
		}
		$title = ! empty( $settings['facebook_share_title'] ) ? (string) $settings['facebook_share_title'] : __( 'Unlock Special Offers on bti homes', 'wp-easy-referral' );
		$desc  = (string) $settings['share_message'];

		echo "\n";
		echo '<meta property="og:type" content="website" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $share_url ) . '" />' . "\n";
		if ( '' !== $image_url ) {
			echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
			echo '<meta property="og:image:secure_url" content="' . esc_url( $image_url ) . '" />' . "\n";
			echo '<meta property="og:image:type" content="' . esc_attr( $this->get_og_image_type( $image_url ) ) . '" />' . "\n";
			echo '<meta property="og:image:width" content="1200" />' . "\n";
			echo '<meta property="og:image:height" content="630" />' . "\n";
		}
	}

	/**
	 * Get Open Graph image type from URL extension.
	 *
	 * @param string $image_url Image URL.
	 * @return string
	 */
	private function get_og_image_type( $image_url ) {
		$path = wp_parse_url( (string) $image_url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );

		if ( 'png' === $ext ) {
			return 'image/png';
		}

		if ( 'webp' === $ext ) {
			return 'image/webp';
		}

		return 'image/jpeg';
	}

	/**
	 * Get current user source from URL parameters.
	 *
	 * @return string
	 */
	private function get_current_user_source() {
		$source = '';

		if ( isset( $_GET['source'] ) ) {
			$source = sanitize_text_field( wp_unslash( $_GET['source'] ) );
		} elseif ( isset( $_GET['utm_source'] ) ) {
			$source = sanitize_text_field( wp_unslash( $_GET['utm_source'] ) );
		}

		return $source;
	}

	/**
	 * Get current referral code from URL or cookie.
	 *
	 * @return string
	 */
	private function get_current_referral_code() {
		if ( isset( $_GET['ref'] ) ) {
			return strtoupper( sanitize_text_field( wp_unslash( $_GET['ref'] ) ) );
		}

		if ( isset( $_COOKIE[ self::REFERRAL_COOKIE ] ) ) {
			return strtoupper( sanitize_text_field( wp_unslash( $_COOKIE[ self::REFERRAL_COOKIE ] ) ) );
		}

		return '';
	}

	/**
	 * Get current referrer data.
	 *
	 * @return array
	 */
	private function get_current_referrer_data() {
		$referral_code = $this->get_current_referral_code();
		if ( '' === $referral_code ) {
			return array();
		}

		$referrer_id = $this->get_user_id_by_referral_code( $referral_code );
		if ( $referrer_id <= 0 ) {
			return array();
		}

		$referrer = get_userdata( $referrer_id );
		if ( ! $referrer instanceof WP_User ) {
			return array();
		}

		return array(
			'code'  => $referral_code,
			'name'  => $referrer->display_name,
			'phone' => (string) get_user_meta( $referrer_id, self::META_PHONE, true ),
		);
	}

	/**
	 * Get direct referrals.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_direct_referrals( $user_id ) {
		global $wpdb;

		$output       = array();
		$seen_phones  = array();
		$seen_user_ids = array();
		$users        = get_users(
			array(
				'role'       => self::ROLE_KEY,
				'meta_key'   => self::META_REFERRED_BY_USER_ID,
				'meta_value' => (string) absint( $user_id ),
				'fields'     => array( 'ID', 'display_name', 'user_email' ),
				'number'     => 9999,
			)
		);

		foreach ( $users as $user ) {
			$child_user_id = absint( $user->ID );
			$child_phone   = $this->normalize_phone( (string) get_user_meta( $child_user_id, self::META_PHONE, true ) );

			if ( in_array( $child_user_id, $seen_user_ids, true ) || ( '' !== $child_phone && in_array( $child_phone, $seen_phones, true ) ) ) {
				continue;
			}

			$status   = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$this->table_name} WHERE user_id = %d ORDER BY id ASC LIMIT 1", $child_user_id ) );
			$output[] = array(
				'display_name'        => $user->display_name,
				'user_email'          => $user->user_email,
				'phone'               => $child_phone,
				'referral_code'       => (string) get_user_meta( $child_user_id, self::META_REFERRAL_ID, true ),
				'referral_user_name'  => $user->display_name,
				'referral_user_phone' => $child_phone,
				'status'              => $status ? $status : 'Unverified',
			);

			$seen_user_ids[] = $child_user_id;
			if ( '' !== $child_phone ) {
				$seen_phones[] = $child_phone;
			}
		}

		$referral_code = (string) get_user_meta( $user_id, self::META_REFERRAL_ID, true );
		if ( '' !== $referral_code ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT name, email, phone, referral_code, referral_user_name, referral_user_phone, status FROM {$this->table_name} WHERE user_id = 0 AND referred_by_code = %s ORDER BY registered_at DESC, id DESC",
					$referral_code
				),
				ARRAY_A
			);

			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$row_phone = $this->normalize_phone(
						! empty( $row['phone'] ) ? (string) $row['phone'] : ( isset( $row['referral_user_phone'] ) ? (string) $row['referral_user_phone'] : '' )
					);

					if ( '' !== $row_phone && in_array( $row_phone, $seen_phones, true ) ) {
						continue;
					}

					$row_name = ! empty( $row['name'] ) ? (string) $row['name'] : ( isset( $row['referral_user_name'] ) ? (string) $row['referral_user_name'] : '' );
					$output[] = array(
						'display_name'        => $row_name,
						'user_email'          => isset( $row['email'] ) ? (string) $row['email'] : '',
						'phone'               => $row_phone,
						'referral_code'       => isset( $row['referral_code'] ) ? (string) $row['referral_code'] : '',
						'referral_user_name'  => $row_name,
						'referral_user_phone' => $row_phone,
						'status'              => isset( $row['status'] ) ? (string) $row['status'] : 'Unverified',
					);

					if ( '' !== $row_phone ) {
						$seen_phones[] = $row_phone;
					}
				}
			}
		}

		return $output;
	}

	/**
	 * Generate unique referral code.
	 *
	 * @return string
	 */
	private function generate_unique_referral_id( $user_id = 0 ) {
		$user       = $user_id > 0 ? get_userdata( $user_id ) : false;
		$name_part  = $user instanceof WP_User ? sanitize_title( $user->display_name ) : 'user';
		$name_part  = '' !== $name_part ? $name_part : 'user';
		$name_part  = substr( $name_part, 0, 20 );

		for ( $attempts = 0; $attempts < 50; $attempts++ ) {
			$code = $name_part . '-' . wp_rand( 1000, 9999 );
			if ( ! $this->referral_code_exists( $code ) ) {
				return $code;
			}
		}

		return $name_part . '-' . time();
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
				'meta_value'  => sanitize_text_field( (string) $code ),
			)
		);

		return ! empty( $users[0] ) ? absint( $users[0] ) : 0;
	}

	/**
	 * Get user by phone meta.
	 *
	 * Supports Bangladeshi mobile numbers stored with or without +88 / 88 / leading 0.
	 *
	 * @param string $phone Phone.
	 * @return WP_User|null
	 */
	private function get_user_by_phone( $phone ) {
		$candidates = $this->get_phone_lookup_candidates( $phone );

		if ( empty( $candidates ) ) {
			return null;
		}

		foreach ( $candidates as $candidate ) {
			$users = get_users(
				array(
					'number'      => 1,
					'count_total' => false,
					'fields'      => 'all',
					'meta_key'    => self::META_PHONE,
					'meta_value'  => $candidate,
				)
			);

			if ( ! empty( $users[0] ) && $users[0] instanceof WP_User ) {
				return $users[0];
			}
		}

		return null;
	}

	/**
	 * Get user by Google subject.
	 *
	 * @param string $sub Google subject.
	 * @return WP_User|null
	 */
	private function get_user_by_google_sub( $sub ) {
		$users = get_users(
			array(
				'number'      => 1,
				'count_total' => false,
				'fields'      => 'all',
				'meta_key'    => self::META_GOOGLE_SUB,
				'meta_value'  => sanitize_text_field( (string) $sub ),
			)
		);

		return ! empty( $users[0] ) && $users[0] instanceof WP_User ? $users[0] : null;
	}

	/**
	 * Increment numeric user meta.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Meta key.
	 * @param int    $amount   Amount.
	 * @return void
	 */
	private function increment_user_meta_int( $user_id, $meta_key, $amount ) {
		$current = (int) get_user_meta( $user_id, $meta_key, true );
		update_user_meta( $user_id, $meta_key, $current + (int) $amount );
	}

	/**
	 * Get dashboard URL.
	 *
	 * @return string
	 */
	private function get_dashboard_url() {
		return home_url( '/' . self::DASHBOARD_SLUG . '/' );
	}

	/**
	 * Get share page URL.
	 *
	 * @param string $referral_code Referral code.
	 * @return string
	 */
	private function get_share_page_url( $referral_code ) {
		return home_url( '/' . self::SHARE_SLUG . '/' . rawurlencode( $referral_code ) . '/' );
	}

	/**
	 * Get registration page URL.
	 *
	 * @return string
	 */
	private function get_registration_page_url() {
		$settings = $this->get_settings();
		$url      = isset( $settings['registration_page_url'] ) ? (string) $settings['registration_page_url'] : '';

		return '' !== $url ? $url : home_url( '/' );
	}

	/**
	 * Get registration page URL with referral code.
	 *
	 * @param string $referral_code Referral code.
	 * @return string
	 */
	private function get_registration_page_url_with_ref( $referral_code ) {
		return add_query_arg( 'ref', rawurlencode( $referral_code ), $this->get_registration_page_url() );
	}

	/**
	 * Get shared registration page URL.
	 *
	 * @return string
	 */
	private function get_shared_registration_page_url() {
		$settings = $this->get_settings();
		$url      = isset( $settings['shared_registration_page_url'] ) ? (string) $settings['shared_registration_page_url'] : '';

		return '' !== $url ? $url : $this->get_registration_page_url();
	}

	/**
	 * Get shared registration page URL with referral code.
	 *
	 * @param string $referral_code Referral code.
	 * @return string
	 */
	private function get_shared_registration_page_url_with_ref( $referral_code ) {
		return add_query_arg( 'ref', rawurlencode( $referral_code ), $this->get_shared_registration_page_url() );
	}

	/**
	 * Generate unique username.
	 *
	 * @param string $display_name Display name.
	 * @param string $email        Email.
	 * @param string $phone        Phone.
	 * @return string
	 */
	private function generate_unique_username( $display_name, $email, $phone ) {
		$base = sanitize_user( $display_name, true );
		if ( '' === $base && '' !== $email ) {
			$parts = explode( '@', $email );
			$base  = sanitize_user( $parts[0], true );
		}
		if ( '' === $base && '' !== $phone ) {
			$base = 'user' . preg_replace( '/\D+/', '', $phone );
		}
		if ( '' === $base ) {
			$base = 'referraluser';
		}

		$username = $base;
		$counter  = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $counter;
			++$counter;
		}

		return $username;
	}

	/**
	 * Normalize phone value.
	 *
	 * Stores Bangladeshi mobile numbers in local 11-digit format like 01910035835.
	 * Accepts input with +880, 880, 0, or 10-digit local format.
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	private function normalize_phone( $phone ) {
		$phone = preg_replace( '/[^0-9+]/', '', trim( (string) $phone ) );

		if ( null === $phone || '' === $phone ) {
			return '';
		}

		$phone = ltrim( $phone, '+' );

		if ( 0 === strpos( $phone, '880' ) ) {
			$phone = substr( $phone, 3 );
		}

		if ( 10 === strlen( $phone ) && '1' === substr( $phone, 0, 1 ) ) {
			$phone = '0' . $phone;
		}

		if ( 11 === strlen( $phone ) && '0' === substr( $phone, 0, 1 ) ) {
			return sanitize_text_field( $phone );
		}

		return '';
	}

	/**
	 * Get phone lookup candidates.
	 *
	 * @param string $phone Phone.
	 * @return array
	 */
	private function get_phone_lookup_candidates( $phone ) {
		$normalized = $this->normalize_phone( $phone );

		if ( '' === $normalized ) {
			return array();
		}

		$ten_digit = substr( $normalized, 1 );

		return array_values(
			array_unique(
				array(
					$normalized,
					'88' . $normalized,
					'+88' . $normalized,
					'880' . $ten_digit,
					'+880' . $ten_digit,
					$ten_digit,
				)
			)
		);
	}

	/**
	 * Check whether the current request is inside Elementor editor or preview.
	 *
	 * @return bool
	 */
	private function is_elementor_editor_request() {
		if ( is_admin() ) {
			return false;
		}

		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$plugin = \Elementor\Plugin::$instance;

		if ( isset( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode() ) {
			return true;
		}

		if ( isset( $plugin->preview ) && method_exists( $plugin->preview, 'is_preview_mode' ) && $plugin->preview->is_preview_mode() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check current user role.
	 *
	 * @return bool
	 */
	private function current_user_is_referral_user() {
		$user = wp_get_current_user();

		return $user instanceof WP_User && in_array( self::ROLE_KEY, (array) $user->roles, true ) && ! current_user_can( 'manage_options' );
	}

	/**
	 * Get WhatsApp share URL.
	 *
	 * @param WP_User $user User object.
	 * @return string
	 */
	private function get_tracked_share_url( $user, $network ) {
		return add_query_arg(
			array(
				'wperf_share_click' => sanitize_key( (string) $network ),
				'wperf_user_id'     => $user->ID,
			),
			home_url( '/' )
		);
	}

	/**
	 * Maybe handle share click redirect.
	 *
	 * @return void
	 */
	public function maybe_handle_share_click_redirect() {
		if ( ! isset( $_GET['wperf_share_click'], $_GET['wperf_user_id'] ) ) {
			return;
		}

		$network = sanitize_key( wp_unslash( $_GET['wperf_share_click'] ) );
		$user_id = absint( wp_unslash( $_GET['wperf_user_id'] ) );
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

		if ( ! $user instanceof WP_User ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$this->increment_share_clicks( $user_id );

		$redirect_url = 'facebook' === $network ? $this->get_facebook_share_url( $this->get_share_page_url( (string) get_user_meta( $user_id, self::META_REFERRAL_ID, true ) ) ) : $this->get_whatsapp_share_url( $user );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Increment share clicks for a user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function increment_share_clicks( $user_id ) {
		global $wpdb;
		$current = (int) get_user_meta( $user_id, self::META_SHARE_CLICKS, true );
		update_user_meta( $user_id, self::META_SHARE_CLICKS, $current + 1 );
		$wpdb->update( $this->table_name, array( 'share_clicks' => $current + 1 ), array( 'user_id' => $user_id ), array( '%d' ), array( '%d' ) );
	}

	/**
	 * Get WhatsApp share URL.
	 *
	 * @param WP_User $user User object.
	 * @return string
	 */
	private function get_whatsapp_share_url( $user ) {
		$referral_id = (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true );
		$share_link  = $this->get_share_page_url( $referral_id );
		$message     = sprintf(
			__( 'Hello, I’m %1$s. Become a proud bti homeowner and enjoy a special offer by using my referral link: %2$s', 'wp-easy-referral' ),
			$user->display_name,
			$share_link
		);

		return 'https://wa.me/?text=' . rawurlencode( $message );
	}

	/**
	 * Get Facebook share URL.
	 *
	 * @param string $share_link Share link.
	 * @return string
	 */
	private function get_facebook_share_url( $share_link ) {
		return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $share_link );
	}

	/**
	 * Get share card inline style.
	 *
	 * @return string
	 */
	private function get_share_card_style() {
		$settings = $this->get_settings();
		$style    = 'background:#111827;';

		if ( '' !== $settings['share_bg_url'] ) {
			$style .= 'background-image:url(' . esc_url_raw( $settings['share_bg_url'] ) . ');background-size:cover;background-position:center;';
		}

		return $style;
	}

	/**
	 * Get shared-page banner inline style.
	 *
	 * @return string
	 */
	private function get_shared_banner_style() {
		$settings = $this->get_settings();
		$style    = 'background:#111827;';

		if ( '' !== $settings['shared_banner_bg_url'] ) {
			$style .= 'background-image:url(' . esc_url_raw( $settings['shared_banner_bg_url'] ) . ');background-size:cover;background-position:center;';
		}

		return $style;
	}

	/**
	 * Get project rows from settings.
	 *
	 * @return array
	 */
	private function get_project_rows() {
		$settings = $this->get_settings();
		$lines    = preg_split( '/
|
|
/', (string) $settings['project_links'] );
		$rows     = array();

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$rows[] = array(
				'name' => isset( $parts[0] ) ? sanitize_text_field( $parts[0] ) : '',
				'url'  => isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '',
			);
		}

		return $rows;
	}

	/**
	 * Return UI notice messages.
	 *
	 * @param string $code Notice code.
	 * @return string
	 */
	private function get_notice_message( $code ) {
		$messages = array(
			'invalid_request'       => __( 'Invalid request. Please try again.', 'wp-easy-referral' ),
			'missing_fields'        => __( 'Please complete all required fields.', 'wp-easy-referral' ),
			'phone_required'        => __( 'Mobile number is required.', 'wp-easy-referral' ),
			'referral_phone_required' => __( 'Referral phone number is required when you choose to refer someone.', 'wp-easy-referral' ),
			'referral_phone_matches'  => __( 'The referral phone number cannot match your own mobile number.', 'wp-easy-referral' ),
			'invalid_login'         => __( 'Invalid mobile number or password.', 'wp-easy-referral' ),
			'invalid_email'         => __( 'Please provide a valid email address.', 'wp-easy-referral' ),
			'weak_password'         => __( 'Password must be at least 6 characters.', 'wp-easy-referral' ),
			'email_exists'          => __( 'This email is already registered.', 'wp-easy-referral' ),
			'phone_exists'          => __( 'This mobile number is already registered.', 'wp-easy-referral' ),
			'invalid_referral'      => __( 'Referral information is invalid.', 'wp-easy-referral' ),
			'registration_failed'   => __( 'Registration failed. Please try again.', 'wp-easy-referral' ),
			'referral_added'        => __( 'Referral user added successfully.', 'wp-easy-referral' ),
			'google_not_configured' => __( 'Google login is not configured yet.', 'wp-easy-referral' ),
			'google_state_invalid'  => __( 'Google login session expired. Please try again.', 'wp-easy-referral' ),
			'google_token_failed'   => __( 'Google token request failed.', 'wp-easy-referral' ),
			'google_token_missing'  => __( 'Google access token is missing.', 'wp-easy-referral' ),
			'google_userinfo_failed'=> __( 'Could not retrieve your Google profile.', 'wp-easy-referral' ),
			'google_email_missing'  => __( 'Your Google account did not return an email address.', 'wp-easy-referral' ),
		);

		return isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'Something went wrong.', 'wp-easy-referral' );
	}

	/**
	 * Get admin source label.
	 *
	 * @param string $source           Stored source value.
	 * @param string $referred_by_code Referred-by code.
	 * @return string
	 */
	private function get_source_label( $source, $referred_by_code ) {
		$source           = sanitize_key( (string) $source );
		$referred_by_code = sanitize_text_field( (string) $referred_by_code );

		if ( 'referred' === $source ) {
			return __( 'Referred', 'wp-easy-referral' );
		}

		if ( 'direct' === $source ) {
			return __( 'Direct', 'wp-easy-referral' );
		}

		if ( '' !== $referred_by_code ) {
			return __( 'Referred', 'wp-easy-referral' );
		}

		return __( 'Direct', 'wp-easy-referral' );
	}

	/**
	 * Get referrer display name for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_referred_by_display_name( $user_id ) {
		$referrer_id = (int) get_user_meta( $user_id, self::META_REFERRED_BY_USER_ID, true );

		if ( $referrer_id > 0 ) {
			$referrer = get_userdata( $referrer_id );
			if ( $referrer instanceof WP_User && '' !== (string) $referrer->display_name ) {
				return (string) $referrer->display_name;
			}
		}

		return '';
	}

	/**
	 * Redirect back with UI notice.
	 *
	 * @param string $tab    Active tab.
	 * @param string $notice Notice code.
	 * @return void
	 */
	private function safe_redirect_with_notice( $tab, $notice ) {
		$redirect_base = $this->get_registration_page_url();
		$current_url   = $this->get_current_request_url();

		if ( '' !== $current_url ) {
			$redirect_base = $current_url;
		}

		$redirect_url = add_query_arg(
			array(
				'wperf_tab'    => sanitize_key( $tab ),
				'wperf_notice' => sanitize_key( $notice ),
			),
			$redirect_base
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Get current request URL.
	 *
	 * @return string
	 */
	private function get_current_request_url() {
		if ( empty( $_SERVER['HTTP_HOST'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		$uri    = wp_unslash( $_SERVER['REQUEST_URI'] );
		$uri    = preg_replace( '/[
].*/', '', (string) $uri );

		if ( null === $uri || '' === $uri ) {
			return '';
		}

		return esc_url_raw( $scheme . $host . $uri );
	}

	/**
	 * Safe cookie setter.
	 *
	 * @param string $name    Cookie name.
	 * @param string $value   Cookie value.
	 * @param int    $expires Expiry.
	 * @return void
	 */
	private function set_cookie( $name, $value, $expires ) {
		if ( headers_sent() ) {
			return;
		}

		setcookie( $name, $value, $expires, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		if ( COOKIEPATH !== SITECOOKIEPATH ) {
			setcookie( $name, $value, $expires, SITECOOKIEPATH ? SITECOOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		}
	}

	/**
	 * Check Google credentials.
	 *
	 * @return bool
	 */
	private function is_google_login_configured() {
		return '' !== $this->get_google_client_id() && '' !== $this->get_google_client_secret();
	}

	/**
	 * Get Google client ID.
	 *
	 * @return string
	 */
	private function get_google_client_id() {
		return defined( 'WPERF_GOOGLE_CLIENT_ID' ) ? (string) WPERF_GOOGLE_CLIENT_ID : '';
	}

	/**
	 * Get Google client secret.
	 *
	 * @return string
	 */
	private function get_google_client_secret() {
		return defined( 'WPERF_GOOGLE_CLIENT_SECRET' ) ? (string) WPERF_GOOGLE_CLIENT_SECRET : '';
	}

	/**
	 * Get Google callback URL.
	 *
	 * @return string
	 */
	private function get_google_redirect_uri() {
		return add_query_arg( 'wperf_google_login', '1', home_url( '/' ) );
	}

	/**
	 * Plugin CSS.
	 *
	 * @return string
	 */
	private function get_css() {
		return '.wperf-card{--wperf-bg:#ffffff;--wperf-text:#111827;--wperf-muted:#667085;--wperf-border:#e5e7eb;--wperf-primary:#111827;--wperf-shadow:0 20px 50px rgba(2,6,23,.08);max-width:920px;margin:0 auto;background:var(--wperf-bg);border:1px solid var(--wperf-border);border-radius:18px;box-shadow:var(--wperf-shadow);overflow:hidden}.wperf-tab-nav{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:8px;background:#f3f4f6;border-bottom:1px solid var(--wperf-border)}.wperf-tab-btn{border:0;border-radius:12px;background:transparent;color:#374151;font-size:15px;font-weight:600;padding:14px 18px;cursor:pointer}.wperf-tab-btn.is-active{background:#fff;color:#111827;box-shadow:0 4px 14px rgba(0,0,0,.06)}.wperf-tab-panel{display:none;padding:28px}.wperf-tab-panel.is-active{display:block}.wperf-title{margin:0 0 8px;font-size:28px;line-height:1.15;font-weight:700;color:var(--wperf-text)}.wperf-subtitle{margin:28px 0 14px;font-size:18px;font-weight:700;color:var(--wperf-text)}.wperf-copy{margin:0 0 24px;color:var(--wperf-muted);font-size:15px;line-height:1.65}.wperf-phone-login-form p,.wperf-register-form p{margin:0 0 18px}.wperf-phone-login-form label,.wperf-register-form label{display:block;margin:0 0 8px;font-size:14px;font-weight:600;color:var(--wperf-text)}.wperf-phone-login-form input[type=text],.wperf-phone-login-form input[type=email],.wperf-phone-login-form input[type=password],.wperf-register-form input[type=text],.wperf-register-form input[type=email],.wperf-register-form input[type=password]{width:100%;height:50px;padding:0 16px;border:1px solid #d1d5db;border-radius:12px;font-size:15px;box-sizing:border-box}.wperf-referral-highlight{background:#f8fafc;border:1px solid #dbe4ee;border-radius:14px;padding:16px 16px 0;margin:0 0 20px}.wperf-referral-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.wperf-btn{display:inline-flex!important;align-items:center;justify-content:center;gap:10px;min-height:50px;padding:0 20px;border:0;border-radius:12px;font-size:15px;font-weight:700;text-decoration:none;cursor:pointer;background:var(--wperf-primary)!important;color:#fff!important}.wperf-btn-google{background:#fff!important;color:#111827!important;border:1px solid #d1d5db!important;width:100%;margin-top:12px}.wperf-google-icon{display:inline-flex;align-items:center}.wperf-btn-secondary{background:#fff!important;color:#111827!important;border:1px solid var(--wperf-border)!important}.wperf-actions,.wperf-share-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:18px}.wperf-logged-in,.wperf-dashboard{padding:30px}.wperf-notice{padding:14px 16px;border-radius:12px;font-size:14px;margin-bottom:18px}.wperf-notice-warning{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412}.wperf-notice-error{padding:0;margin:-6px 0 14px;border:0;background:transparent;color:#dc2626;font-size:12px;line-height:1.5}.wperf-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:20px}.wperf-stat-box{padding:18px;border:1px solid #e5e7eb;border-radius:14px;background:#f9fafb}.wperf-stat-label{font-size:13px;color:#667085;margin-bottom:8px}.wperf-stat-value{font-size:22px;font-weight:700;color:#111827;word-break:break-word}.wperf-small{font-size:16px}.wperf-share-card-link{text-decoration:none}.wperf-share-card{position:relative;overflow:hidden;min-height:370px;border-radius:0;padding:26px;display:flex;align-items:flex-end;color:#fff;margin-top:10px}.wperf-share-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(17,24,39,.08),rgba(17,24,39,.58))}.wperf-share-content{position:relative;z-index:2}.wperf-share-kicker{font-size:13px;letter-spacing:.08em;text-transform:uppercase;opacity:.9}.wperf-share-name{margin:10px 0 8px;font-size:30px;color:#fff}.wperf-share-code{display:inline-block;padding:10px 14px;border-radius:999px;background:rgba(255,255,255,.12);backdrop-filter:blur(6px);font-weight:700}.wperf-share-message{margin:16px 0 0;font-size:18px;max-width:420px;color:#fff}.wperf-consent-field{display:flex!important;align-items:flex-start;gap:10px;margin:14px 0}.wperf-terms-checkbox{display:inline-block!important;appearance:auto!important;-webkit-appearance:checkbox!important;width:18px!important;height:18px!important;min-width:18px!important;margin:3px 0 0!important;opacity:1!important;visibility:visible!important;position:static!important;clip:auto!important;pointer-events:auto!important}.wperf-consent-label{display:inline!important;font-weight:400;line-height:1.5;margin:0}.wperf-consent-label a{text-decoration:underline}.wperf-share-link-box,.wperf-profile-extra{margin-top:16px;padding:14px 16px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}.wperf-table{width:100%;border-collapse:collapse}.wperf-table th,.wperf-table td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}.wperf-brochure-modal[hidden]{display:none!important}.wperf-brochure-modal{position:fixed;inset:0;z-index:99999;background:rgba(17,24,39,.72);padding:24px;display:flex;align-items:center;justify-content:center}.wperf-brochure-dialog{position:relative;width:min(960px,100%);max-height:90vh;background:#fff;border-radius:16px;padding:20px 20px 16px;box-sizing:border-box;display:flex;flex-direction:column;gap:14px}.wperf-brochure-close{position:absolute;top:10px;right:12px;border:0;background:transparent;font-size:28px;line-height:1;cursor:pointer;color:#111827}.wperf-brochure-frame-wrap{margin-top:22px}.wperf-brochure-frame{width:100%;height:min(70vh,720px);border:1px solid #e5e7eb;border-radius:10px;background:#fff}.wperf-brochure-footer{display:flex;justify-content:flex-end}.wperf-shared-banner-image{display:block;width:100%;height:auto;max-height:none;border-radius:0}.wperf-shared-banner-image-mobile{display:none}.wperf-add-referral-modal[hidden]{display:none!important}.wperf-add-referral-modal{position:fixed;inset:0;z-index:99999;background:rgba(17,24,39,.72);padding:24px;display:flex;align-items:center;justify-content:center}.wperf-lead-search-form{margin:0 0 18px}.wperf-lead-search-form p{display:flex;gap:10px;align-items:center}.wperf-lead-search-form input[type=search]{width:min(360px,100%);height:42px;padding:0 12px;border:1px solid #d1d5db;border-radius:10px}.wperf-field-hint{display:block;margin-top:6px;color:#6b7280;font-size:13px}.wperf-field-error{display:block;min-height:18px;margin-top:5px;color:#dc2626;font-size:12px;line-height:1.4}.wperf-checkbox-option{display:inline-flex!important;align-items:center;gap:8px;margin-top:8px;font-weight:400}.wperf-checkbox-option span{color:var(--wperf-text)}.wperf-checkbox-option input[type=checkbox]{display:inline-block!important;appearance:auto!important;-webkit-appearance:checkbox!important;width:16px!important;height:16px!important;margin:0!important;opacity:1!important;position:static!important;visibility:visible!important}.wperf-share-link-box{display:block}.wperf-copy-link-row{display:flex;gap:8px;align-items:center;margin-top:8px}.wperf-copy-link-input{flex:1;width:100%;height:42px;border:1px solid #d1d5db;border-radius:8px;padding:0 12px;background:#fff;color:#111827;box-sizing:border-box}.wperf-copy-link-btn{border:0!important;border-radius:8px!important;padding:10px 16px!important;background:#000!important;color:#fff!important;cursor:pointer!important;font-weight:700!important;line-height:1!important}.wperf-copy-link-btn:hover,.wperf-copy-link-btn:focus{background:#111!important;color:#fff!important}.wperf-copy-feedback{display:inline-block;margin-top:6px;font-style:normal;font-size:12px;font-weight:700;color:#047857}.wperf-lead-pagination{display:flex;justify-content:center;width:100%;margin-top:24px}.wperf-lead-pagination>ul.page-numbers{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:8px;margin:0;padding:0;list-style:none}.wperf-lead-pagination>ul.page-numbers>li{margin:0;padding:0}.wperf-lead-pagination .page-numbers a.page-numbers,.wperf-lead-pagination .page-numbers span.page-numbers{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 12px;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#111827;font-size:14px;font-weight:700;line-height:1;text-decoration:none;transition:border-color .2s ease,background-color .2s ease,color .2s ease}.wperf-lead-pagination .page-numbers a.page-numbers:hover,.wperf-lead-pagination .page-numbers a.page-numbers:focus{border-color:#111827;background:#f3f4f6;color:#111827}.wperf-lead-pagination .page-numbers span.page-numbers.current{border-color:#111827;background:#111827;color:#fff}.wperf-lead-pagination .page-numbers span.page-numbers.dots{min-width:auto;padding:0 4px;border-color:transparent;background:transparent}.wperf-virtual-page{padding:30px 16px; width: 100%}@media (max-width:767px){.wperf-tab-panel,.wperf-logged-in,.wperf-dashboard{padding:20px}.wperf-title{font-size:24px}.wperf-stats,.wperf-referral-grid{grid-template-columns:1fr}.wperf-shared-banner-image-desktop{display:none!important}.wperf-shared-banner-image-mobile{display:block!important}}';
	}

	/**
	 * Plugin JS.
	 *
	 * @return string
	 */
	private function get_js() {
		return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
	var wrappers = document.querySelectorAll('[data-wperf-tabs]');
	wrappers.forEach(function (wrapper) {
		var buttons = wrapper.querySelectorAll('.wperf-tab-btn');
		var panels = wrapper.querySelectorAll('.wperf-tab-panel');

		function activateTab(target) {
			buttons.forEach(function (button) {
				button.classList.toggle('is-active', button.getAttribute('data-tab-target') === target);
			});
			panels.forEach(function (panel) {
				panel.classList.toggle('is-active', panel.id === 'wperf-panel-' + target);
			});
		}

		buttons.forEach(function (button) {
			button.addEventListener('click', function () {
				activateTab(button.getAttribute('data-tab-target'));
			});
		});
	});

	function normalizePhone(value) {
		var phone = String(value || '').replace(/[^0-9+]/g, '').replace(/^\+/, '');
		if (phone.indexOf('880') === 0) {
			phone = phone.substring(3);
		}
		if (phone.length === 10 && phone.charAt(0) === '1') {
			phone = '0' + phone;
		}
		return phone.length === 11 && phone.charAt(0) === '0' ? phone : '';
	}

	function setPhoneError(input, message) {
		var form = input.closest('form');
		var error = form ? form.querySelector('[data-wperf-phone-error]') : null;
		if (error) {
			error.textContent = message || '';
		}
		input.setAttribute('aria-invalid', message ? 'true' : 'false');
		input.dataset.wperfPhoneInvalid = message ? '1' : '0';
	}

	function checkPhone(form, input) {
		var toggle = form.querySelector('[data-wperf-referral-toggle]');
		if (toggle && !toggle.checked) {
			setPhoneError(input, '');
			return Promise.resolve(true);
		}

		var phone = normalizePhone(input.value);
		if (!phone) {
			setPhoneError(input, 'Please enter a valid Bangladeshi mobile number.');
			return Promise.resolve(false);
		}

		var accountSelector = form.getAttribute('data-wperf-account-phone');
		var accountInput = accountSelector ? form.querySelector(accountSelector) : null;
		if (accountInput && normalizePhone(accountInput.value) === phone) {
			setPhoneError(input, 'The referral phone number cannot be your own mobile number.');
			return Promise.resolve(false);
		}

		if (form.hasAttribute('data-wperf-skip-duplicate-check')) {
			setPhoneError(input, '');
			return Promise.resolve(true);
		}

		var endpoint = form.getAttribute('data-wperf-phone-check-url');
		var nonceInput = form.querySelector('[name="wperf_phone_check_nonce"]');
		if (!endpoint || !nonceInput) {
			setPhoneError(input, 'Unable to verify this phone number. Please refresh the page.');
			return Promise.resolve(false);
		}

		var data = new FormData();
		data.append('action', 'wperf_check_phone');
		data.append('phone', phone);
		data.append('nonce', nonceInput.value);
		input.dataset.wperfPhoneChecking = '1';

		return fetch(endpoint, { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function (response) { return response.json(); })
			.then(function (result) {
				input.dataset.wperfPhoneChecking = '0';
				if (!result.success || !result.data) {
					setPhoneError(input, result.data && result.data.message ? result.data.message : 'Unable to verify this phone number.');
					return false;
				}
				if (!result.data.valid || result.data.exists) {
					setPhoneError(input, result.data.message || 'This mobile number is already registered.');
					return false;
				}
				setPhoneError(input, '');
				return true;
			})
			.catch(function () {
				input.dataset.wperfPhoneChecking = '0';
				setPhoneError(input, 'Unable to verify this phone number. Please try again.');
				return false;
			});
	}

	document.querySelectorAll('[data-wperf-referral-toggle]').forEach(function (input) {
		var form = input.closest('form');
		var fields = form ? form.querySelector('[data-wperf-referral-fields]') : null;
		var referralPhone = fields ? fields.querySelector('[data-wperf-phone-check]') : null;

		function updateReferralFields() {
			if (!fields) {
				return;
			}
			fields.hidden = !input.checked;
			if (referralPhone) {
				referralPhone.required = input.checked;
				if (!input.checked) {
					referralPhone.value = '';
					setPhoneError(referralPhone, '');
				}
			}
		}

		input.addEventListener('change', updateReferralFields);
		updateReferralFields();
	});

	document.querySelectorAll('[data-wperf-phone-check-form]').forEach(function (form) {
		var input = form.querySelector('[data-wperf-phone-check]');
		if (!input) {
			return;
		}

		var timer = null;
		input.addEventListener('input', function () {
			setPhoneError(input, '');
			window.clearTimeout(timer);
			var toggle = form.querySelector('[data-wperf-referral-toggle]');
			if (toggle && !toggle.checked) {
				return;
			}
			if (normalizePhone(input.value)) {
				timer = window.setTimeout(function () {
					checkPhone(form, input);
				}, 350);
			}
		});

		input.addEventListener('blur', function () {
			var toggle = form.querySelector('[data-wperf-referral-toggle]');
			if ((!toggle || toggle.checked) && input.value.trim()) {
				checkPhone(form, input);
			}
		});

		form.addEventListener('submit', function (event) {
			if (form.dataset.wperfSubmitting === '1') {
				return;
			}
			var toggle = form.querySelector('[data-wperf-referral-toggle]');
			if (toggle && !toggle.checked) {
				return;
			}

			event.preventDefault();
			checkPhone(form, input).then(function (valid) {
				if (!valid) {
					input.focus();
					return;
				}
				form.dataset.wperfSubmitting = '1';
				HTMLFormElement.prototype.submit.call(form);
			});
		});
	});

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.wperf-copy-link-btn');
		if (!button) {
			return;
		}

		var box = button.closest('.wperf-share-link-box');
		var input = box ? box.querySelector('.wperf-copy-link-input') : null;
		var feedback = box ? box.querySelector('.wperf-copy-feedback') : null;
		if (!input) {
			return;
		}

		input.focus();
		input.select();
		input.setSelectionRange(0, input.value.length);

		var done = function () {
			if (feedback) {
				feedback.textContent = 'Copied';
				window.setTimeout(function () { feedback.textContent = ''; }, 1600);
			}
		};

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(input.value).then(done).catch(function () {
				document.execCommand('copy');
				done();
			});
		} else {
			document.execCommand('copy');
			done();
		}
	});

	var brochureModal = document.querySelector('.wperf-brochure-modal[data-wperf-global-modal]');
	if (!brochureModal) {
		brochureModal = document.createElement('div');
		brochureModal.className = 'wperf-brochure-modal';
		brochureModal.setAttribute('data-wperf-global-modal', '1');
		brochureModal.hidden = true;
		brochureModal.innerHTML = '<div class="wperf-brochure-dialog"><button type="button" class="wperf-brochure-close" data-wperf-brochure-close aria-label="Close brochure">×</button><div class="wperf-brochure-frame-wrap"><iframe class="wperf-brochure-frame" src="" title="Brochure Preview"></iframe></div><div class="wperf-brochure-footer"><a class="wperf-btn" href="" target="_blank" rel="noopener noreferrer" data-wperf-brochure-download>Download Brochure</a></div></div>';
		document.body.appendChild(brochureModal);
	}

	document.querySelectorAll('[data-wperf-brochure-open]').forEach(function (button) {
		button.addEventListener('click', function () {
			var url = button.getAttribute('data-wperf-brochure-open') || '';
			var frame = brochureModal.querySelector('.wperf-brochure-frame');
			var downloadLink = brochureModal.querySelector('[data-wperf-brochure-download]');
			if (frame) {
				frame.setAttribute('src', url);
			}
			if (downloadLink) {
				downloadLink.setAttribute('href', url);
			}
			brochureModal.hidden = false;
			document.body.style.overflow = 'hidden';
		});
	});

	var closeBrochureModal = function () {
		var frame = brochureModal.querySelector('.wperf-brochure-frame');
		if (frame) {
			frame.setAttribute('src', '');
		}
		brochureModal.hidden = true;
		document.body.style.overflow = '';
	};

	brochureModal.addEventListener('click', function (event) {
		if (event.target === brochureModal || event.target.closest('[data-wperf-brochure-close]')) {
			closeBrochureModal();
		}
	});

	var addReferralModal = document.querySelector('.wperf-add-referral-modal');
	document.querySelectorAll('[data-wperf-open-add-referral]').forEach(function (button) {
		button.addEventListener('click', function () {
			if (addReferralModal) {
				addReferralModal.hidden = false;
				document.body.style.overflow = 'hidden';
			}
		});
	});

	document.querySelectorAll('[data-wperf-close-add-referral]').forEach(function (button) {
		button.addEventListener('click', function () {
			if (addReferralModal) {
				addReferralModal.hidden = true;
				document.body.style.overflow = '';
			}
		});
	});

	if (addReferralModal) {
		addReferralModal.addEventListener('click', function (event) {
			if (event.target === addReferralModal) {
				addReferralModal.hidden = true;
				document.body.style.overflow = '';
			}
		});
	}
});
JS;
	}
}

new WPERF_Referral_Auth_System();
