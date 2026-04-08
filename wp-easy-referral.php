<?php
/**
 * Plugin Name:       WP Easy Referral
 * Plugin URI:        https://mmw.diviaccessories.com/
 * Description:       Referral registration, phone login, Google login, frontend dashboard, admin entries, sharing, and referral tracking.
 * Version:           1.5.0
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
			'source'           => __( 'Source', 'wp-easy-referral' ),
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
	 * Default column renderer.
	 *
	 * @param object $item        Row item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
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

		$actions = array(
			'view' => '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'wp-easy-referral' ) . '</a>',
		);

		return '<strong>' . esc_html( $item->name ) . '</strong> ' . $this->row_actions( $actions );
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
	const VERSION                  = '1.5.0';
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

		add_shortcode( 'wperf_auth_tabs', array( $this, 'render_auth_tabs' ) );
		add_shortcode( 'wperf_user_dashboard', array( $this, 'render_user_dashboard' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_block_referral_user_admin' ) );
		add_action( 'admin_init', array( $this, 'maybe_export_entries_csv' ) );

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
			source varchar(20) NOT NULL DEFAULT 'manual',
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
						var frame=wp.media({title:title,multiple:false});
						frame.on('select',function(){
							var attachment=frame.state().get('selection').first().toJSON();
							$(inputSelector).val(attachment.url).trigger('change');
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
			'brochure_url'          => isset( $input['brochure_url'] ) ? esc_url_raw( trim( (string) $input['brochure_url'] ) ) : $defaults['brochure_url'],
			'share_bg_url'          => isset( $input['share_bg_url'] ) ? esc_url_raw( trim( (string) $input['share_bg_url'] ) ) : $defaults['share_bg_url'],
			'share_message'         => isset( $input['share_message'] ) ? sanitize_text_field( (string) $input['share_message'] ) : $defaults['share_message'],
			'registration_page_url' => isset( $input['registration_page_url'] ) ? esc_url_raw( trim( (string) $input['registration_page_url'] ) ) : $defaults['registration_page_url'],
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	private static function get_default_settings() {
		return array(
			'brochure_url'          => '',
			'share_bg_url'          => '',
			'share_message'         => __( 'Come and get discount.', 'wp-easy-referral' ),
			'registration_page_url' => home_url( '/' ),
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
						<th scope="row"><label for="wperf_registration_page_url"><?php esc_html_e( 'Registration Page URL', 'wp-easy-referral' ); ?></label></th>
						<td>
							<input type="url" class="regular-text" id="wperf_registration_page_url" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[registration_page_url]" value="<?php echo esc_attr( $settings['registration_page_url'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Set the page where the [wperf_auth_tabs] shortcode exists.', 'wp-easy-referral' ); ?></p>
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
	 * Render entries page.
	 *
	 * @return void
	 */
	public function render_entries_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-easy-referral' ) );
		}

		$entry_id = isset( $_GET['wperf_id'] ) ? absint( wp_unslash( $_GET['wperf_id'] ) ) : 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WP Easy Referral Entries', 'wp-easy-referral' ); ?></h1>
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
		<form method="get">
			<input type="hidden" name="page" value="wperf-easy-referral" />
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
				<?php submit_button( __( 'Filter', 'wp-easy-referral' ), 'secondary', '', false ); ?>
				<a class="button button-primary" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export CSV', 'wp-easy-referral' ); ?></a>
			</p>
		</form>
		<?php
		$list_table->display();
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
				<tr><th><?php esc_html_e( 'Referral User Phone', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->referral_user_phone ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Referral Code', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->referral_code ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Referred By Code', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->referred_by_code ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Source', 'wp-easy-referral' ); ?></th><td><?php echo esc_html( (string) $entry->source ); ?></td></tr>
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

		$query = "SELECT * FROM {$this->table_name} {$where_sql} ORDER BY registered_at DESC";
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
				'Referral User Phone',
				'Referral Code',
				'Referred By Code',
				'Source',
				'Registered At',
			)
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
					isset( $row['source'] ) ? $row['source'] : '',
					isset( $row['registered_at'] ) ? $row['registered_at'] : '',
				)
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
		if ( ! $user instanceof WP_User || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			$this->safe_redirect_with_notice( 'login', 'invalid_login' );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );
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

		if ( '' === $display_name || '' === $email || '' === $phone || '' === $password ) {
			$this->safe_redirect_with_notice( 'register', 'missing_fields' );
		}

		if ( ! is_email( $email ) ) {
			$this->safe_redirect_with_notice( 'register', 'invalid_email' );
		}

		if ( strlen( $password ) < 6 ) {
			$this->safe_redirect_with_notice( 'register', 'weak_password' );
		}

		if ( '' === $phone ) {
			$this->safe_redirect_with_notice( 'register', 'phone_required' );
		}

		if ( email_exists( $email ) ) {
			$this->safe_redirect_with_notice( 'register', 'email_exists' );
		}

		if ( $this->get_user_by_phone( $phone ) instanceof WP_User ) {
			$this->safe_redirect_with_notice( 'register', 'phone_exists' );
		}

		if ( '' === $referred_by_code ) {
			$referred_by_code = $this->maybe_get_referral_code_from_manual_fields( $referral_user_name, $referral_user_phone );
		}

		if ( '' !== $referred_by_code && $this->get_user_id_by_referral_code( $referred_by_code ) <= 0 ) {
			$this->safe_redirect_with_notice( 'register', 'invalid_referral' );
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
		$this->handle_user_register( $user_id );
		$this->apply_referral_relationship( $user_id, $referred_by_code );
		$this->insert_registration_entry(
			array(
				'user_id'              => $user_id,
				'name'                 => $display_name,
				'email'                => $email,
				'phone'                => $phone,
				'referral_user_name'   => $referral_user_name,
				'referral_user_phone'  => $referral_user_phone,
				'referral_code'        => (string) get_user_meta( $user_id, self::META_REFERRAL_ID, true ),
				'referred_by_code'     => (string) get_user_meta( $user_id, self::META_REFERRED_BY_CODE, true ),
				'source'               => 'manual',
			)
		);

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
					'source'               => 'google',
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
			update_user_meta( $user_id, self::META_REFERRAL_ID, $this->generate_unique_referral_id() );
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
				'source'              => isset( $data['source'] ) ? sanitize_key( (string) $data['source'] ) : 'manual',
				'registered_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
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
						<a class="wperf-btn wperf-btn-secondary" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Logout', 'wp-easy-referral' ); ?></a>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$notice_code = isset( $_GET['wperf_notice'] ) ? sanitize_key( wp_unslash( $_GET['wperf_notice'] ) ) : '';
		$active_tab  = isset( $_GET['wperf_tab'] ) ? sanitize_key( wp_unslash( $_GET['wperf_tab'] ) ) : 'login';
		$active_tab  = in_array( $active_tab, array( 'login', 'register' ), true ) ? $active_tab : 'login';
		$ref_data    = $this->get_current_referrer_data();
		$ref_code    = isset( $ref_data['code'] ) ? (string) $ref_data['code'] : '';
		?>
		<div class="wperf-card wperf-tabs-wrap <?php echo esc_attr( $atts['class'] ); ?>" data-wperf-tabs>
			<div class="wperf-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'Authentication tabs', 'wp-easy-referral' ); ?>">
				<button type="button" class="wperf-tab-btn <?php echo ( 'login' === $active_tab ) ? 'is-active' : ''; ?>" data-tab-target="login"><?php echo esc_html( $atts['login_title'] ); ?></button>
				<button type="button" class="wperf-tab-btn <?php echo ( 'register' === $active_tab ) ? 'is-active' : ''; ?>" data-tab-target="register"><?php echo esc_html( $atts['register_title'] ); ?></button>
			</div>

			<div class="wperf-tab-panel <?php echo ( 'login' === $active_tab ) ? 'is-active' : ''; ?>" id="wperf-panel-login">
				<h3 class="wperf-title"><?php echo esc_html( $atts['login_title'] ); ?></h3>
				<p class="wperf-copy"><?php esc_html_e( 'Log in with your mobile number and password.', 'wp-easy-referral' ); ?></p>
				<?php if ( '' !== $notice_code && 'login' === $active_tab ) : ?>
					<div class="wperf-notice wperf-notice-warning"><?php echo esc_html( $this->get_notice_message( $notice_code ) ); ?></div>
				<?php endif; ?>
				<?php echo $this->render_phone_login_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->render_google_login_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<div class="wperf-tab-panel <?php echo ( 'register' === $active_tab ) ? 'is-active' : ''; ?>" id="wperf-panel-register">
				<h3 class="wperf-title"><?php echo esc_html( $atts['register_title'] ); ?></h3>
				<p class="wperf-copy"><?php esc_html_e( 'Create your account. Your referral code will be generated automatically.', 'wp-easy-referral' ); ?></p>
				<?php if ( '' !== $notice_code && 'register' === $active_tab ) : ?>
					<div class="wperf-notice wperf-notice-warning"><?php echo esc_html( $this->get_notice_message( $notice_code ) ); ?></div>
				<?php endif; ?>
				<form class="wperf-register-form" method="post" action="">
					<div class="wperf-referral-highlight">
						<div class="wperf-referral-grid">
							<p>
								<label for="wperf_referral_user_name"><?php esc_html_e( 'Referral User', 'wp-easy-referral' ); ?></label>
								<input type="text" id="wperf_referral_user_name" name="wperf_referral_user_name" value="<?php echo isset( $ref_data['name'] ) ? esc_attr( $ref_data['name'] ) : ''; ?>" />
							</p>
							<p>
								<label for="wperf_referral_user_phone"><?php esc_html_e( 'Referral User Phone', 'wp-easy-referral' ); ?></label>
								<input type="text" id="wperf_referral_user_phone" name="wperf_referral_user_phone" value="<?php echo isset( $ref_data['phone'] ) ? esc_attr( $ref_data['phone'] ) : ''; ?>" />
							</p>
						</div>
					</div>
					<p>
						<label for="wperf_display_name"><?php esc_html_e( 'Full Name', 'wp-easy-referral' ); ?></label>
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
						<input type="password" id="wperf_register_password" name="wperf_password" required />
					</p>
					<input type="hidden" name="wperf_referred_by_code" value="<?php echo esc_attr( $ref_code ); ?>" />
					<input type="hidden" name="wperf_action" value="register" />
					<?php wp_nonce_field( 'wperf_front_register', 'wperf_register_nonce' ); ?>
					<p><button type="submit" class="wperf-btn"><?php esc_html_e( 'Register', 'wp-easy-referral' ); ?></button></p>
				</form>
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
		$referred_by      = (string) get_user_meta( $user->ID, self::META_REFERRED_BY_CODE, true );
		$brochure_url     = (string) $settings['brochure_url'];
		$share_page_url   = $this->get_share_page_url( $referral_id );
		$landing_reg_link = $this->get_registration_page_url_with_ref( $referral_id );
		$children         = $this->get_direct_referrals( $user->ID );

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
						<a class="wperf-btn wperf-btn-secondary" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Logout', 'wp-easy-referral' ); ?></a>
					</div>
				</div>

				<div class="wperf-stats">
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'Name', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value"><?php echo esc_html( $user->display_name ); ?></div></div>
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'Email', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value wperf-small"><?php echo esc_html( $user->user_email ); ?></div></div>
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'Mobile Number', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value"><?php echo esc_html( '' !== $phone ? $phone : __( 'Not set', 'wp-easy-referral' ) ); ?></div></div>
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'My Referral Code', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value"><?php echo esc_html( $referral_id ); ?></div></div>
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'Successful Referrals', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value"><?php echo esc_html( (string) $referrals ); ?></div></div>
					<div class="wperf-stat-box"><div class="wperf-stat-label"><?php esc_html_e( 'Referral Credits', 'wp-easy-referral' ); ?></div><div class="wperf-stat-value"><?php echo esc_html( (string) $credits ); ?></div></div>
				</div>

				<div class="wperf-share-section">
					<h4 class="wperf-subtitle"><?php esc_html_e( 'My Share Card', 'wp-easy-referral' ); ?></h4>
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
						<a class="wperf-btn" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $this->get_whatsapp_share_url( $user ) ); ?>"><?php esc_html_e( 'Share on WhatsApp', 'wp-easy-referral' ); ?></a>
						<a class="wperf-btn wperf-btn-secondary" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $this->get_facebook_share_url( $share_page_url ) ); ?>"><?php esc_html_e( 'Share on Facebook', 'wp-easy-referral' ); ?></a>
					</div>
					<div class="wperf-share-link-box"><strong><?php esc_html_e( 'My Share Page:', 'wp-easy-referral' ); ?></strong> <span><?php echo esc_html( $share_page_url ); ?></span></div>
				</div>

				<?php if ( '' !== $brochure_url ) : ?>
					<div class="wperf-actions wperf-brochure-row">
						<a class="wperf-btn" href="<?php echo esc_url( $brochure_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download Brochure', 'wp-easy-referral' ); ?></a>
					</div>
				<?php endif; ?>

				<div class="wperf-profile-extra">
					<strong><?php esc_html_e( 'Referred By:', 'wp-easy-referral' ); ?></strong>
					<?php echo esc_html( '' !== $referred_by ? $referred_by : __( 'Direct Registration', 'wp-easy-referral' ) ); ?>
				</div>

				<h4 class="wperf-subtitle"><?php esc_html_e( 'Users You Referred', 'wp-easy-referral' ); ?></h4>
				<?php if ( empty( $children ) ) : ?>
					<p class="wperf-copy"><?php esc_html_e( 'No referrals yet.', 'wp-easy-referral' ); ?></p>
				<?php else : ?>
					<table class="wperf-table">
						<thead><tr><th><?php esc_html_e( 'Name', 'wp-easy-referral' ); ?></th><th><?php esc_html_e( 'Email', 'wp-easy-referral' ); ?></th><th><?php esc_html_e( 'Mobile', 'wp-easy-referral' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $children as $child ) : ?>
								<tr><td><?php echo esc_html( $child['display_name'] ); ?></td><td><?php echo esc_html( $child['user_email'] ); ?></td><td><?php echo esc_html( $child['phone'] ); ?></td></tr>
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
			echo '<main class="wperf-virtual-page">' . wp_kses_post( $this->render_user_dashboard() ) . '</main>';
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
		$cta_url  = $this->get_registration_page_url_with_ref( (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true ) );

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

		$settings   = $this->get_settings();
		$share_url  = $this->get_share_page_url( (string) $share_code );
		$image_url  = (string) $settings['share_bg_url'];
		$title      = sprintf( __( '%s invited you', 'wp-easy-referral' ), $user->display_name );
		$desc       = (string) $settings['share_message'];

		echo "\n";
		echo '<meta property="og:type" content="website" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $share_url ) . '" />' . "\n";
		if ( '' !== $image_url ) {
			echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
		}
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
		$users = get_users(
			array(
				'role'       => self::ROLE_KEY,
				'meta_key'   => self::META_REFERRED_BY_USER_ID,
				'meta_value' => (string) absint( $user_id ),
				'fields'     => array( 'ID', 'display_name', 'user_email' ),
				'number'     => 9999,
			)
		);

		$output = array();
		foreach ( $users as $user ) {
			$output[] = array(
				'display_name' => $user->display_name,
				'user_email'   => $user->user_email,
				'phone'        => (string) get_user_meta( $user->ID, self::META_PHONE, true ),
			);
		}

		return $output;
	}

	/**
	 * Generate unique referral code.
	 *
	 * @return string
	 */
	private function generate_unique_referral_id() {
		for ( $attempts = 0; $attempts < 20; $attempts++ ) {
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
				'meta_value'  => sanitize_text_field( (string) $code ),
			)
		);

		return ! empty( $users[0] ) ? absint( $users[0] ) : 0;
	}

	/**
	 * Get user by phone meta.
	 *
	 * @param string $phone Phone.
	 * @return WP_User|null
	 */
	private function get_user_by_phone( $phone ) {
		$phone = $this->normalize_phone( $phone );
		if ( '' === $phone ) {
			return null;
		}

		$users = get_users(
			array(
				'number'      => 1,
				'count_total' => false,
				'fields'      => 'all',
				'meta_key'    => self::META_PHONE,
				'meta_value'  => $phone,
			)
		);

		return ! empty( $users[0] ) && $users[0] instanceof WP_User ? $users[0] : null;
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
	 * @param string $phone Phone.
	 * @return string
	 */
	private function normalize_phone( $phone ) {
		$phone = preg_replace( '/[^0-9+]/', '', trim( (string) $phone ) );
		if ( null === $phone ) {
			return '';
		}

		return sanitize_text_field( $phone );
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
	private function get_whatsapp_share_url( $user ) {
		$referral_id = (string) get_user_meta( $user->ID, self::META_REFERRAL_ID, true );
		$share_link  = $this->get_share_page_url( $referral_id );
		$message     = sprintf(
			__( 'Hello, I am %1$s. Use my referral code %2$s and register here: %3$s', 'wp-easy-referral' ),
			$user->display_name,
			$referral_id,
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
			'invalid_login'         => __( 'Invalid mobile number or password.', 'wp-easy-referral' ),
			'invalid_email'         => __( 'Please provide a valid email address.', 'wp-easy-referral' ),
			'weak_password'         => __( 'Password must be at least 6 characters.', 'wp-easy-referral' ),
			'email_exists'          => __( 'This email is already registered.', 'wp-easy-referral' ),
			'phone_exists'          => __( 'This mobile number is already registered.', 'wp-easy-referral' ),
			'invalid_referral'      => __( 'Referral information is invalid.', 'wp-easy-referral' ),
			'registration_failed'   => __( 'Registration failed. Please try again.', 'wp-easy-referral' ),
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
	 * Redirect back with UI notice.
	 *
	 * @param string $tab    Active tab.
	 * @param string $notice Notice code.
	 * @return void
	 */
	private function safe_redirect_with_notice( $tab, $notice ) {
		$redirect_base = $this->get_registration_page_url();

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
		return '.wperf-card{--wperf-bg:#ffffff;--wperf-text:#111827;--wperf-muted:#667085;--wperf-border:#e5e7eb;--wperf-primary:#111827;--wperf-shadow:0 20px 50px rgba(2,6,23,.08);max-width:920px;margin:0 auto;background:var(--wperf-bg);border:1px solid var(--wperf-border);border-radius:18px;box-shadow:var(--wperf-shadow);overflow:hidden}.wperf-tab-nav{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:8px;background:#f3f4f6;border-bottom:1px solid var(--wperf-border)}.wperf-tab-btn{border:0;border-radius:12px;background:transparent;color:#374151;font-size:15px;font-weight:600;padding:14px 18px;cursor:pointer}.wperf-tab-btn.is-active{background:#fff;color:#111827;box-shadow:0 4px 14px rgba(0,0,0,.06)}.wperf-tab-panel{display:none;padding:28px}.wperf-tab-panel.is-active{display:block}.wperf-title{margin:0 0 8px;font-size:28px;line-height:1.15;font-weight:700;color:var(--wperf-text)}.wperf-subtitle{margin:28px 0 14px;font-size:18px;font-weight:700;color:var(--wperf-text)}.wperf-copy{margin:0 0 24px;color:var(--wperf-muted);font-size:15px;line-height:1.65}.wperf-phone-login-form p,.wperf-register-form p{margin:0 0 18px}.wperf-phone-login-form label,.wperf-register-form label{display:block;margin:0 0 8px;font-size:14px;font-weight:600;color:var(--wperf-text)}.wperf-phone-login-form input[type=text],.wperf-phone-login-form input[type=email],.wperf-phone-login-form input[type=password],.wperf-register-form input[type=text],.wperf-register-form input[type=email],.wperf-register-form input[type=password]{width:100%;height:50px;padding:0 16px;border:1px solid #d1d5db;border-radius:12px;font-size:15px;box-sizing:border-box}.wperf-referral-highlight{background:#f8fafc;border:1px solid #dbe4ee;border-radius:14px;padding:16px 16px 0;margin:0 0 20px}.wperf-referral-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.wperf-btn{display:inline-flex!important;align-items:center;justify-content:center;gap:10px;min-height:50px;padding:0 20px;border:0;border-radius:12px;font-size:15px;font-weight:700;text-decoration:none;cursor:pointer;background:var(--wperf-primary)!important;color:#fff!important}.wperf-btn-google{background:#fff!important;color:#111827!important;border:1px solid #d1d5db!important;width:100%;margin-top:12px}.wperf-google-icon{display:inline-flex;align-items:center}.wperf-btn-secondary{background:#fff!important;color:#111827!important;border:1px solid var(--wperf-border)!important}.wperf-actions,.wperf-share-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:18px}.wperf-logged-in,.wperf-dashboard{padding:30px}.wperf-notice{padding:14px 16px;border-radius:12px;font-size:14px;margin-bottom:18px}.wperf-notice-warning{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412}.wperf-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:20px}.wperf-stat-box{padding:18px;border:1px solid #e5e7eb;border-radius:14px;background:#f9fafb}.wperf-stat-label{font-size:13px;color:#667085;margin-bottom:8px}.wperf-stat-value{font-size:22px;font-weight:700;color:#111827;word-break:break-word}.wperf-small{font-size:16px}.wperf-share-card-link{text-decoration:none}.wperf-share-card{position:relative;overflow:hidden;min-height:260px;border-radius:18px;padding:26px;display:flex;align-items:flex-end;color:#fff;margin-top:10px}.wperf-share-overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(17,24,39,.15),rgba(17,24,39,.78))}.wperf-share-content{position:relative;z-index:2}.wperf-share-kicker{font-size:13px;letter-spacing:.08em;text-transform:uppercase;opacity:.9}.wperf-share-name{margin:10px 0 8px;font-size:30px;color:#fff}.wperf-share-code{display:inline-block;padding:10px 14px;border-radius:999px;background:rgba(255,255,255,.12);backdrop-filter:blur(6px);font-weight:700}.wperf-share-message{margin:16px 0 0;font-size:18px;max-width:420px;color:#fff}.wperf-share-link-box,.wperf-profile-extra{margin-top:16px;padding:14px 16px;border:1px solid #e5e7eb;border-radius:12px;background:#fff}.wperf-table{width:100%;border-collapse:collapse}.wperf-table th,.wperf-table td{padding:12px 10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}.wperf-virtual-page{padding:30px 16px}@media (max-width:767px){.wperf-tab-panel,.wperf-logged-in,.wperf-dashboard{padding:20px}.wperf-title{font-size:24px}.wperf-stats,.wperf-referral-grid{grid-template-columns:1fr}}';
	}

	/**
	 * Plugin JS.
	 *
	 * @return string
	 */
	private function get_js() {
		return "document.addEventListener('DOMContentLoaded',function(){var wrappers=document.querySelectorAll('[data-wperf-tabs]');if(!wrappers.length){return;}wrappers.forEach(function(wrapper){var buttons=wrapper.querySelectorAll('.wperf-tab-btn');var panels=wrapper.querySelectorAll('.wperf-tab-panel');function activateTab(target){buttons.forEach(function(button){button.classList.toggle('is-active',button.getAttribute('data-tab-target')===target);});panels.forEach(function(panel){panel.classList.toggle('is-active',panel.id==='wperf-panel-'+target);});}buttons.forEach(function(button){button.addEventListener('click',function(){activateTab(button.getAttribute('data-tab-target'));});});});});";
	}
}

new WPERF_Referral_Auth_System();
