<?php
/**
 * Menu de administração principal e relatórios.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Admin
 */
class PB_Affiliates_Admin {

	const PARENT_SLUG = 'pb-affiliates';

	/**
	 * Init.
	 */
	public static function init() {
		if ( is_admin() ) {
			if ( ! class_exists( 'WC_Admin_Report', false ) ) {
				require_once WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php';
			}
			require_once PB_AFFILIATES_PATH . 'includes/admin/class-pb-affiliates-admin-click-report.php';
			add_filter( 'woocommerce_reports_screen_ids', array( 'PB_Affiliates_Admin_Click_Report', 'add_wc_reports_screen' ) );
			add_action( 'admin_enqueue_scripts', array( 'PB_Affiliates_Admin_Click_Report', 'enqueue_report_assets' ), 25 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_orders_report_assets' ), 20 );
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_head', array( __CLASS__, 'hide_affiliate_detail_submenu_item' ), 99 );
		add_action( 'admin_init', array( __CLASS__, 'handle_early_post' ) );
	}

	/**
	 * Estilos da lista de comissões (menu Pedidos).
	 */
	public static function enqueue_orders_report_assets() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( empty( $_GET['page'] ) || self::PARENT_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_register_style( 'pb-aff-orders-report', false, array(), PB_AFFILIATES_VERSION );
		wp_enqueue_style( 'pb-aff-orders-report' );
		wp_add_inline_style(
			'pb-aff-orders-report',
			'.pb-aff-orders-report-table th.sortable a,.pb-aff-orders-report-table th.sortable a:hover{text-decoration:none;display:inline-flex;align-items:center;gap:.25em}.pb-aff-orders-report-table th.sortable .sort-ind{font-size:10px;opacity:.75}.pb-aff-orders-report-table th.num,.pb-aff-orders-report-table td.num{text-align:right;font-variant-numeric:tabular-nums}' .
			'.pb-aff-orders-aff-code{font-size:12px;font-weight:400;color:#50575e;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;padding:2px 8px;margin-top:4px;display:inline-block}' .
			'.pb-aff-orders-report-wrap .tablenav{margin-top:12px}.pb-aff-orders-report-wrap .tablenav .tablenav-pages{float:none;text-align:left}' .
			'.pb-aff-orders-report-wrap .tablenav-pages .page-numbers,.pb-aff-orders-report-wrap .tablenav-pages ul.page-numbers{display:inline-flex!important;flex-direction:row;flex-wrap:wrap;gap:4px 8px;align-items:center;list-style:none;margin:0;padding:0}' .
			'.pb-aff-orders-report-wrap .tablenav-pages li{display:inline-flex;margin:0;list-style:none}' .
			'.pb-aff-orders-report-wrap .tablenav-pages a,.pb-aff-orders-report-wrap .tablenav-pages span:not(.screen-reader-text){display:inline-block;padding:2px 8px}' .
			'.pb-aff-orders-report-filters{margin:0 0 1em;padding:12px 0;border-bottom:1px solid #c3c4c7}' .
			'.pb-aff-orders-report-filters .pb-aff-orders-report-filters-row{display:flex;flex-wrap:wrap;gap:12px 16px;align-items:flex-end}' .
			'.pb-aff-orders-report-filters label{display:flex;flex-direction:column;gap:4px;font-weight:600}' .
			'.pb-aff-orders-report-filters label span{font-weight:400}' .
			'.pb-aff-orders-report-filters input[type=number],.pb-aff-orders-report-filters input[type=date],.pb-aff-orders-report-filters select{min-width:8em}'
		);
	}

	/**
	 * Esconde o item de submenu "Detalhe do afiliado": a página só deve abrir por URL (links internos).
	 * Nota: não usar remove_submenu_page() — remove a entrada de $submenu e o WordPress deixa de
	 * resolver o parent, calculando um hook errado em user_can_access_admin_page() e bloqueando o acesso.
	 */
	public static function hide_affiliate_detail_submenu_item() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$slug = PB_Affiliates_Admin_User_Detail::PAGE_SLUG;
		printf(
			'<style id="pb-aff-hide-affiliate-detail-submenu">#adminmenu .toplevel_page_pb-affiliates .wp-submenu a[href*="%s"]{display:none!important}</style>',
			esc_attr( $slug )
		);
	}

	/**
	 * Ações POST antes de qualquer output.
	 */
	public static function handle_early_post() {
		PB_Affiliates_Admin_Affiliates::handle_post();
		PB_Affiliates_Admin_Payments::handle_post();
	}

	/**
	 * Menu de topo "Afiliados" e páginas do submenu.
	 */
	public static function menu() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		add_menu_page(
			__( 'Afiliados', 'pb-affiliates' ),
			__( 'Afiliados', 'pb-affiliates' ),
			'manage_woocommerce',
			self::PARENT_SLUG,
			array( __CLASS__, 'render_orders_report' ),
			'dashicons-groups',
			56
		);

		// Evita duplicar o primeiro item com o mesmo slug do menu pai.
		remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Pedidos', 'pb-affiliates' ),
			__( 'Pedidos', 'pb-affiliates' ),
			'manage_woocommerce',
			self::PARENT_SLUG,
			array( __CLASS__, 'render_orders_report' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Relatórios de afiliados', 'pb-affiliates' ),
			__( 'Relatórios de afiliados', 'pb-affiliates' ),
			'manage_woocommerce',
			'pb-affiliates-report',
			array( __CLASS__, 'render_clicks_report' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Configurações', 'pb-affiliates' ),
			__( 'Configurações', 'pb-affiliates' ),
			'manage_woocommerce',
			'pb-affiliates-settings',
			array( 'PB_Affiliates_Admin_Settings', 'render' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Afiliados', 'pb-affiliates' ),
			__( 'Afiliados', 'pb-affiliates' ),
			'manage_woocommerce',
			'pb-affiliates-users',
			array( 'PB_Affiliates_Admin_Affiliates', 'render' )
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Pagamentos', 'pb-affiliates' ),
			__( 'Pagamentos', 'pb-affiliates' ),
			'manage_woocommerce',
			'pb-affiliates-payments',
			array( 'PB_Affiliates_Admin_Payments', 'render' )
		);

		PB_Affiliates_Admin_Materials::register_submenu();

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Detalhe do afiliado', 'pb-affiliates' ),
			__( 'Detalhe do afiliado', 'pb-affiliates' ),
			'manage_woocommerce',
			PB_Affiliates_Admin_User_Detail::PAGE_SLUG,
			array( 'PB_Affiliates_Admin_User_Detail', 'render' )
		);
	}

	/**
	 * Lista de comissões por pedido (submenu Pedidos).
	 */
	public static function render_orders_report() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$filters  = PB_Affiliates_Reports::admin_orders_report_filters_from_compact_get();
		$summary  = PB_Affiliates_Reports::get_global_summary( $filters );
		$per_page = (int) apply_filters( 'pb_affiliates_admin_orders_report_per_page', 25 );
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = ( isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) ) ? 'asc' : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$list = PB_Affiliates_Reports::get_admin_commissions_list_paginated( $paged, $per_page, $orderby, $order, $filters );
		$rows                          = $list['rows'];
		$orders_report_paged           = $list['page'];
		$orders_report_per_page        = $list['per_page'];
		$orders_report_total           = $list['total'];
		$orders_report_total_pages     = $list['total_pages'];
		$orders_report_orderby         = $list['orderby'];
		$orders_report_order           = $list['order'];
		$orders_report_filters         = $list['filters'];
		$orders_report_via_choices       = PB_Affiliates_Reports::get_admin_commission_via_filter_choices();
		$orders_report_affiliate_options = PB_Affiliates_Reports::get_admin_commissions_distinct_affiliates();

		require PB_AFFILIATES_PATH . 'includes/admin/views/report.php';
	}

	/**
	 * URL de ordenação na lista de pedidos/comissões (admin).
	 *
	 * @param string $column     Coluna: id|order_id|affiliate|commission|status|via.
	 * @param string $current_ob Coluna ativa.
	 * @param string $current_dir asc|desc.
	 * @param array|null $filters Normalizados; null lê da query string atual.
	 * @return string
	 */
	public static function orders_report_sort_url( $column, $current_ob, $current_dir, $filters = null ) {
		$allowed = array( 'id', 'order_id', 'affiliate', 'commission', 'status', 'via' );
		$column  = sanitize_key( (string) $column );
		if ( ! in_array( $column, $allowed, true ) ) {
			$column = 'id';
		}
		if ( $column === $current_ob && 'asc' === $current_dir ) {
			$new_dir = 'desc';
		} else {
			$new_dir = 'asc';
		}
		if ( $column !== $current_ob ) {
			$new_dir = 'desc';
		}
		if ( null === $filters ) {
			$filters = PB_Affiliates_Reports::admin_orders_report_filters_from_compact_get();
		} else {
			$filters = PB_Affiliates_Reports::normalize_admin_commissions_filters( $filters );
		}
		$args = array_merge(
			array(
				'page'    => self::PARENT_SLUG,
				'orderby' => $column,
				'order'   => $new_dir,
				'paged'   => 1,
			),
			PB_Affiliates_Reports::admin_orders_report_filter_query_args( $filters )
		);
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Relatórios de afiliados (cliques + pedidos, gráficos).
	 */
	public static function render_clicks_report() {
		PB_Affiliates_Admin_Click_Report::output();
	}
}
