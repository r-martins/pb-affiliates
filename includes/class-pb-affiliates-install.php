<?php
/**
 * Install: DB tables, version option.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Install
 */
class PB_Affiliates_Install {

	const DB_VERSION = '1.1.6';

	/**
	 * A loja usa armazenamento de pedidos HPOS (tabelas dedicadas) como modo ativo.
	 * Usa a mesma opção que o WooCommerce; legível no hook de ativação sem o container WC.
	 *
	 * @return bool
	 */
	public static function is_hpos_order_storage_enabled() {
		return get_option( 'woocommerce_custom_orders_table_enabled' ) === 'yes';
	}

	/**
	 * Atualiza tabelas em sites já ativados.
	 */
	public static function maybe_upgrade() {
		$current = get_option( 'pb_affiliates_db_version', '0' );
		if ( version_compare( (string) $current, self::DB_VERSION, '>=' ) ) {
			return;
		}
		self::create_tables();
		update_option( 'pb_affiliates_db_version', self::DB_VERSION );
		flush_rewrite_rules( false );
	}

	/**
	 * Activation.
	 */
	public static function activate() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( PB_AFFILIATES_BASENAME );
			wp_die(
				esc_html__( 'PB Afiliados requer WooCommerce.', 'pb-affiliates' ),
				'',
				array( 'back_link' => true )
			);
		}
		if ( ! defined( 'WC_PAGSEGURO_CONNECT_VERSION' ) && ! class_exists( 'RM_PagBank\Connect' ) ) {
			deactivate_plugins( PB_AFFILIATES_BASENAME );
			wp_die(
				esc_html__( 'PB Afiliados requer PagBank Connect.', 'pb-affiliates' ),
				'',
				array( 'back_link' => true )
			);
		}
		if ( ! self::is_hpos_order_storage_enabled() ) {
			deactivate_plugins( PB_AFFILIATES_BASENAME );
			wp_die(
				wp_kses_post(
					__( 'PB Afiliados exige que o WooCommerce utilize o <strong>armazenamento de pedidos de alto desempenho (HPOS)</strong>. Em WooCommerce &gt; Configurações &gt; Avançado &gt; Recursos, ative as tabelas personalizadas de pedidos e conclua a migração antes de ativar este plugin.', 'pb-affiliates' )
				),
				'',
				array( 'back_link' => true )
			);
		}
		self::create_tables();
		update_option( 'pb_affiliates_db_version', self::DB_VERSION );
		if ( ! get_option( 'pb_affiliates_settings' ) ) {
			update_option( 'pb_affiliates_settings', self::default_settings() );
		}
		flush_rewrite_rules();
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'referral_param'           => 'pid',
			'cookie_days'              => 30,
			'default_commission_type'  => 'percent',
			'default_commission_value' => 10,
			'attribution'              => 'last',
			'exclude_shipping'         => 'yes',
			'exclude_fees'             => 'yes',
			'payment_mode'             => 'manual',
			'split_release_days'       => 7,
			'manual_min_withdrawal'    => 0,
			'manual_retention_days'    => 0,
			'terms_page_id'            => 0,
			'affiliate_registration'   => 'auto',
			'commission_recurring'      => 'no',
		);
	}

	/**
	 * Create custom tables.
	 */
	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table           = $wpdb->prefix . 'pagbank_affiliate_commissions';

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			affiliate_id bigint(20) unsigned NOT NULL,
			commission_amount decimal(19,4) NOT NULL DEFAULT 0,
			commission_base decimal(19,4) NOT NULL DEFAULT 0,
			commission_type varchar(20) NOT NULL DEFAULT 'percent',
			attributed_via varchar(32) NOT NULL DEFAULT '',
			coupon_id bigint(20) unsigned NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			currency varchar(10) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			available_at datetime NULL,
			paid_at datetime NULL,
			payment_method varchar(20) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY affiliate_id (affiliate_id),
			KEY status (status)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$table_w = $wpdb->prefix . 'pagbank_affiliate_withdrawals';
		$sql2    = "CREATE TABLE {$table_w} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			affiliate_id bigint(20) unsigned NOT NULL,
			amount decimal(19,4) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			currency varchar(10) NOT NULL DEFAULT '',
			notes text NULL,
			commission_ids_json longtext NULL,
			created_at datetime NOT NULL,
			processed_at datetime NULL,
			PRIMARY KEY  (id),
			KEY affiliate_id (affiliate_id),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql2 );

		$table_c = $wpdb->prefix . 'pagbank_affiliate_click_log';
		$sql3    = "CREATE TABLE {$table_c} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			affiliate_id bigint(20) unsigned NOT NULL,
			hit_at datetime NOT NULL,
			via varchar(32) NOT NULL DEFAULT '',
			client_ip varchar(45) NOT NULL DEFAULT '',
			visited_url text NULL,
			referer_host varchar(190) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY affiliate_id (affiliate_id),
			KEY hit_at (hit_at),
			KEY referer_host (referer_host)
		) $charset_collate;";
		dbDelta( $sql3 );
	}

}
