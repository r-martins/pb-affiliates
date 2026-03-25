<?php
/**
 * Relatório admin: afiliados — cliques e pedidos atribuídos (gráficos estilo WC).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Admin_Click_Report
 */
class PB_Affiliates_Admin_Click_Report extends WC_Admin_Report {

	/**
	 * Rótulo legível para a chave técnica `via` (relatórios admin).
	 *
	 * @param string $raw_via Valor em base (cookie_param, referrer, …).
	 * @return string
	 */
	public static function human_label_for_via( $raw_via ) {
		$raw_via = (string) $raw_via;
		$map     = array(
			'cookie_param' => __( 'Parâmetro na URL (ex.: ?pid=)', 'pb-affiliates' ),
			'referrer'     => __( 'Domínio verificado (HTTP Referer)', 'pb-affiliates' ),
			'coupon'       => __( 'Cupom no checkout', 'pb-affiliates' ),
			'renewal'      => __( 'Renovação / assinatura', 'pb-affiliates' ),
			'unknown'      => __( 'Origem não registada', 'pb-affiliates' ),
		);
		if ( isset( $map[ $raw_via ] ) ) {
			return $map[ $raw_via ];
		}
		if ( '' === $raw_via ) {
			return __( '(sem origem)', 'pb-affiliates' );
		}
		return $raw_via;
	}

	/**
	 * Rótulo para valor guardado em `referer_host` (relatório admin).
	 *
	 * @param string $stored Valor em `referer_host` / meta do pedido.
	 * @return string
	 */
	public static function human_label_for_referer_domain( $stored ) {
		$stored = (string) $stored;
		if ( PB_Affiliates_Click_Log::REFERER_HOST_LOCAL === $stored ) {
			return __( 'Mesmo domínio da loja', 'pb-affiliates' );
		}
		if ( '' === $stored ) {
			return __( 'Sem cabeçalho Referer', 'pb-affiliates' );
		}
		return $stored;
	}

	/**
	 * URL de ordenação para tabelas do relatório (alterna asc/desc na mesma coluna).
	 *
	 * @param array  $base_query    Args base (sem {prefix}_sort, _dir, _paged).
	 * @param string $column        clicks|orders|rate.
	 * @param string $current_sort  Coluna ativa.
	 * @param string $current_dir   asc|desc.
	 * @param string $prefix        Prefixo dos parâmetros GET (ex.: pb_ref, pb_aff).
	 * @return string
	 */
	public static function admin_report_sort_url( array $base_query, $column, $current_sort, $current_dir, $prefix = 'pb_ref' ) {
		$prefix = (string) $prefix;
		if ( ! preg_match( '/^[a-z][a-z0-9_]{0,15}$/', $prefix ) ) {
			$prefix = 'pb_ref';
		}
		$column = sanitize_key( (string) $column );
		if ( ! in_array( $column, array( 'clicks', 'orders', 'rate' ), true ) ) {
			$column = 'orders';
		}
		if ( $column === $current_sort && 'desc' === $current_dir ) {
			$new_dir = 'asc';
		} else {
			$new_dir = 'desc';
		}
		return add_query_arg(
			array_merge(
				$base_query,
				array(
					$prefix . '_sort'  => $column,
					$prefix . '_dir'   => $new_dir,
					$prefix . '_paged' => 1,
				)
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param array $base_query Args base da página.
	 * @return string
	 */
	public static function admin_referer_domain_sort_url( array $base_query, $column, $current_sort, $current_dir ) {
		return self::admin_report_sort_url( $base_query, $column, $current_sort, $current_dir, 'pb_ref' );
	}

	/**
	 * URL de ordenação para a tabela de pedidos no período (relatório de cliques).
	 *
	 * @param array  $base_query    Args base (sem pb_ord_sort, pb_ord_dir, pb_ord_paged).
	 * @param string $column        order_id|date|status|affiliate|commission.
	 * @param string $current_sort  Coluna ativa.
	 * @param string $current_dir   asc|desc.
	 * @return string
	 */
	public static function admin_period_orders_sort_url( array $base_query, $column, $current_sort, $current_dir ) {
		$column = sanitize_key( (string) $column );
		if ( ! in_array( $column, array( 'order_id', 'date', 'status', 'affiliate', 'commission' ), true ) ) {
			$column = 'date';
		}
		$current_sort = sanitize_key( (string) $current_sort );
		$current_dir  = ( 'asc' === $current_dir ) ? 'asc' : 'desc';
		if ( $column === $current_sort && 'desc' === $current_dir ) {
			$new_dir = 'asc';
		} else {
			$new_dir = 'desc';
		}
		return add_query_arg(
			array_merge(
				$base_query,
				array(
					'pb_ord_sort'  => $column,
					'pb_ord_dir'   => $new_dir,
					'pb_ord_paged' => 1,
				)
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Inclui a tela nos IDs que recebem scripts de gráficos WC (datepicker, flot).
	 *
	 * @param array $ids Screen ids.
	 * @return array
	 */
	public static function add_wc_reports_screen( $ids ) {
		$ids[] = 'pb-affiliates_page_pb-affiliates-report';
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && ! in_array( $screen->id, $ids, true ) && isset( $_GET['page'] ) && 'pb-affiliates-report' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$ids[] = $screen->id;
			}
		}
		return $ids;
	}

	/**
	 * Garante Flot + wc-reports + datepicker (o WC só faz enqueue se o screen_id coincidir).
	 */
	public static function enqueue_report_assets() {
		if ( ! isset( $_GET['page'] ) || 'pb-affiliates-report' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_enqueue_style( 'jquery-ui-style' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_add_inline_style(
			'woocommerce_admin_styles',
			'.pb-aff-click-report__filters{margin:12px 0 16px;clear:both}.pb-aff-click-report__filters select{margin:0 12px 0 4px;vertical-align:middle}.pb-aff-click-report__summary{margin:16px 0;padding:12px 14px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;font-size:14px}.pb-aff-bottom-tables-row{display:flex;flex-wrap:wrap;gap:24px 32px;align-items:flex-start;margin-top:2.25em;padding-top:1.25em;border-top:1px solid #dcdcde;clear:both}.pb-aff-bottom-tables-row__col{flex:1 1 380px;min-width:min(100%,300px);max-width:100%}.pb-aff-affiliate-perf-section{margin:0;padding:0;border:none;clear:none}.pb-aff-period-orders-section{margin:0;clear:none}.pb-aff-chart-heading{clear:both;margin-top:1.25em}.pb-aff-chart-heading--spaced{margin-top:2em}.pb-aff-click-report__pies{display:flex;flex-wrap:wrap;gap:28px 32px;align-items:flex-start}.pb-aff-click-report__pie-col--via{flex:1 1 300px;min-width:260px;max-width:480px}.pb-aff-click-report__pie-col--via .chart-container{max-width:520px;width:100%}.pb-aff-click-report__pie-col--domain{flex:2.25 1 420px;min-width:360px;max-width:100%}.pb-aff-click-report__pie-col--domain .chart-container{max-width:none;width:100%}.pb-aff-click-report__pie-col--domain .pb-aff-ref-domain-toolbar{max-width:100%}.pb-aff-click-report__pie-col--domain .pb-aff-pie-help-details{max-width:100%}.pb-aff-pie-help-details{max-width:720px;margin:.25em 0 1em;border:1px solid #c3c4c7;border-radius:4px;background:#fff}.pb-aff-pie-help-summary{cursor:pointer;padding:10px 14px;font-weight:600;background:#f6f7f7;list-style:none}.pb-aff-pie-help-summary:focus{outline:2px solid #2271b1;outline-offset:1px}.pb-aff-pie-help-summary::-webkit-details-marker{display:none}.pb-aff-pie-help-summary::before{content:"\25B6";display:inline-block;margin-right:.4em;font-size:.65em;opacity:.75;transform:translateY(-0.05em)}.pb-aff-pie-help-details[open] .pb-aff-pie-help-summary::before{transform:rotate(90deg) translateY(-0.05em)}.pb-aff-pie-help-details[open] .pb-aff-pie-help-summary{border-bottom:1px solid #dcdcde}.pb-aff-pie-help{margin:0;padding:12px 14px 16px;line-height:1.55}.pb-aff-pie-help-intro{margin:0 0 .65em}.pb-aff-pie-help-outro{margin:.85em 0 0}.pb-aff-pie-help-list{margin:.35em 0 0;padding:0 0 0 1.35em;list-style:disc}.pb-aff-pie-help-list li{margin:0 0 .55em;line-height:1.5}.pb-aff-pie-help-list li:last-child{margin-bottom:0}.pb-aff-pie-legend{margin-top:12px}.pb-aff-pie-legend li{display:flex;flex-wrap:wrap;align-items:baseline;gap:.35em .6em;border-left-width:4px;border-left-style:solid;padding:6px 10px;margin:0 0 6px;background:#f6f7f7;font-size:13px}.pb-aff-pie-legend__name{font-weight:600}.pb-aff-pie-legend__stats{color:#50575e}.pb-aff-pie-legend__code{font-size:11px;color:#787c82;font-weight:400}.pb-aff-pie-slice-label{font-size:11px;font-weight:600;text-align:center;line-height:1.2;padding:2px;color:#fff;text-shadow:0 0 2px rgba(0,0,0,.35)}.pb-aff-ref-domain-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px 16px;max-width:720px}.pb-aff-ref-domain-toolbar.pb-aff-chart-heading--spaced{margin-top:2em}.pb-aff-ref-domain-toolbar .pb-aff-chart-heading{margin:0;flex:1;min-width:12rem}.pb-aff-ref-hide-switch-wrap{display:flex;align-items:center;gap:10px;flex-shrink:0}.pb-aff-ref-hide-switch-label{font-size:13px;color:#50575e;line-height:1.3;max-width:14rem;text-align:right}a.pb-aff-ref-hide-switch{position:relative;display:inline-block;width:44px;height:26px;border-radius:999px;background:#c3c4c7;box-shadow:inset 0 1px 2px rgba(0,0,0,.12);flex-shrink:0;text-decoration:none;outline:none;vertical-align:middle;transition:background .15s}a.pb-aff-ref-hide-switch:hover{opacity:.92}a.pb-aff-ref-hide-switch:focus{box-shadow:0 0 0 1px #fff,0 0 0 3px #2271b1}a.pb-aff-ref-hide-switch.is-on{background:#00a32a}.pb-aff-ref-hide-switch__thumb{position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform .15s}a.pb-aff-ref-hide-switch.is-on .pb-aff-ref-hide-switch__thumb{transform:translateX(18px)}.pb-aff-ref-domain-split{display:flex;flex-wrap:wrap;gap:18px 28px;align-items:flex-start;margin-top:10px}.pb-aff-ref-domain-split__chart{flex:1 1 420px;min-width:340px;max-width:none}.pb-aff-ref-domain-split__table{flex:0 1 400px;min-width:240px;max-width:100%}.pb-aff-ref-domain-table{margin:0;font-size:12px}.pb-aff-ref-domain-table th.num,.pb-aff-ref-domain-table td.num{text-align:right;font-variant-numeric:tabular-nums}.pb-aff-ref-domain-table th.sortable a{display:inline-flex;align-items:center;gap:.25em;text-decoration:none}.pb-aff-ref-domain-table th.sortable a .sort-ind{font-size:10px;opacity:.75}.pb-aff-ref-domain-pager{margin:10px 0 0;padding:0;font-size:12px}.pb-aff-ref-domain-pager .page-numbers{display:inline-flex;flex-wrap:wrap;gap:4px 8px;align-items:center;list-style:none;margin:0;padding:0}.pb-aff-ref-domain-pager li{display:inline;margin:0}.pb-aff-ref-domain-pager a,.pb-aff-ref-domain-pager span{padding:2px 6px}.pb-aff-affiliate-perf-section .pb-aff-chart-heading--spaced{margin-top:0}.pb-aff-period-orders-section .pb-aff-chart-heading{margin-top:0}.pb-aff-aff-code{font-size:11px;color:#50575e;font-weight:400}.pb-aff-aff-perf-name-link{font-weight:600;text-decoration:none}.pb-aff-aff-perf-name-link:hover,.pb-aff-aff-perf-name-link:focus{text-decoration:underline}#pb-aff-bubble-tooltip{display:none;position:absolute;z-index:35;max-width:300px;padding:9px 11px;background:#23282d;color:#fff;border-radius:4px;font-size:12px;line-height:1.45;pointer-events:none;box-shadow:0 1px 8px rgba(0,0,0,.22)}#pb-aff-bubble-tooltip strong{display:block;margin-bottom:4px}'
		);
		$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$version = defined( 'WC_VERSION' ) ? WC_VERSION : null;
		$url     = WC()->plugin_url();
		if ( ! wp_script_is( 'wc-reports', 'registered' ) ) {
			wp_register_script(
				'wc-reports',
				$url . '/assets/js/admin/reports' . $suffix . '.js',
				array( 'jquery', 'jquery-ui-datepicker' ),
				$version,
				true
			);
		}
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'wc-reports' );
		wp_enqueue_script( 'wc-flot' );
		wp_enqueue_script( 'wc-flot-resize' );
		wp_enqueue_script( 'wc-flot-time' );
		wp_enqueue_script( 'wc-flot-pie' );
		wp_enqueue_script( 'wc-flot-stack' );
	}

	/**
	 * Render da página.
	 */
	public static function output() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$inst = new self();
		$inst->render_page();
	}

	/**
	 * WC_Admin_Report::calculate_current_range() usa current_time( 'timestamp' ), baseado em gmt_offset,
	 * que pode não coincidir com o calendário do site quando existe timezone_string — o intervalo “últimos 7 dias”
	 * terminava no dia civil errado (ex.: última barra = ontem). Recalcular com current_datetime() / wp_timezone().
	 *
	 * @param string $current_range Slug do intervalo (7day|month|last_month|custom|…).
	 */
	private function realign_report_day_bounds_to_site_calendar( $current_range ) {
		if ( 'day' !== $this->chart_groupby ) {
			return;
		}

		$tz    = wp_timezone();
		$today = current_datetime()->setTime( 0, 0, 0 );

		switch ( $current_range ) {
			case '7day':
				$this->end_date       = $today->getTimestamp();
				$this->start_date    = $today->modify( '-6 days' )->getTimestamp();
				$this->chart_interval = 6;
				break;

			case 'month':
				$this->end_date       = $today->getTimestamp();
				$month_start          = $today->modify( 'first day of this month' )->setTime( 0, 0, 0 );
				$this->start_date     = $month_start->getTimestamp();
				$this->chart_interval = (int) max( 0, floor( ( $this->end_date - $this->start_date ) / DAY_IN_SECONDS ) );
				break;

			case 'last_month':
				$first_this           = $today->modify( 'first day of this month' )->setTime( 0, 0, 0 );
				$last_prev            = $first_this->modify( '-1 day' )->setTime( 0, 0, 0 );
				$first_prev           = $last_prev->modify( 'first day of this month' )->setTime( 0, 0, 0 );
				$this->start_date     = $first_prev->getTimestamp();
				$this->end_date       = $last_prev->getTimestamp();
				$this->chart_interval = (int) max( 0, floor( ( $this->end_date - $this->start_date ) / DAY_IN_SECONDS ) );
				break;

			case 'custom':
				if ( empty( $_GET['start_date'] ) || empty( $_GET['end_date'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					break;
				}
				$start_s = sanitize_text_field( wp_unslash( $_GET['start_date'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$end_s   = sanitize_text_field( wp_unslash( $_GET['end_date'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$start_d = DateTimeImmutable::createFromFormat( 'Y-m-d', $start_s, $tz );
				$end_d   = DateTimeImmutable::createFromFormat( 'Y-m-d', $end_s, $tz );
				if ( ! $start_d || ! $end_d ) {
					break;
				}
				$this->start_date = $start_d->setTime( 0, 0, 0 )->getTimestamp();
				$this->end_date   = $end_d->setTime( 0, 0, 0 )->getTimestamp();
				if ( $this->end_date < $this->start_date ) {
					break;
				}
				$this->chart_interval = (int) max( 0, floor( ( $this->end_date - $this->start_date ) / DAY_IN_SECONDS ) );
				break;

			default:
				break;
		}
	}

	/**
	 * Conteúdo principal.
	 */
	public function render_page() {
		$ranges = array(
			'year'       => __( 'Year', 'woocommerce' ),
			'last_month' => __( 'Last month', 'woocommerce' ),
			'month'      => __( 'This month', 'woocommerce' ),
			'7day'       => __( 'Last 7 days', 'woocommerce' ),
		);

		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( wp_unslash( $_GET['range'] ) ) : '7day'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ), true ) ) {
			$current_range = '7day';
		}

		$this->check_current_range_nonce( $current_range );
		$this->calculate_current_range( $current_range );
		$this->realign_report_day_bounds_to_site_calendar( $current_range );

		$aff_id = isset( $_GET['pb_affiliate'] ) ? absint( $_GET['pb_affiliate'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$hide_empty_referer = ! empty( $_GET['pb_hide_empty_referer'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['pb_hide_empty_referer'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wc_order_status_choices = PB_Affiliates_Reports::get_admin_report_order_status_choices_for_ui();
		$pb_order_status         = isset( $_GET['pb_order_status'] ) ? sanitize_key( wp_unslash( $_GET['pb_order_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $pb_order_status && ! isset( $wc_order_status_choices[ $pb_order_status ] ) ) {
			$pb_order_status = '';
		}
		$report_order_statuses = PB_Affiliates_Reports::get_admin_report_order_statuses_for_query( $pb_order_status );
		$via_order               = 'count_desc';

		$ref_sort = isset( $_GET['pb_ref_sort'] ) ? sanitize_key( wp_unslash( $_GET['pb_ref_sort'] ) ) : 'orders'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $ref_sort, array( 'clicks', 'orders', 'rate' ), true ) ) {
			$ref_sort = 'orders';
		}
		$ref_dir = ( isset( $_GET['pb_ref_dir'] ) && 'asc' === sanitize_key( wp_unslash( $_GET['pb_ref_dir'] ) ) ) ? 'asc' : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ref_paged = max( 1, isset( $_GET['pb_ref_paged'] ) ? absint( $_GET['pb_ref_paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ref_per_page = (int) apply_filters( 'pb_affiliates_admin_referer_domain_table_per_page', 12 );
		$ref_per_page = max( 5, min( 50, $ref_per_page ) );

		$aff_sort = isset( $_GET['pb_aff_sort'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_sort'] ) ) : 'orders'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $aff_sort, array( 'clicks', 'orders', 'rate' ), true ) ) {
			$aff_sort = 'orders';
		}
		$aff_dir = ( isset( $_GET['pb_aff_dir'] ) && 'asc' === sanitize_key( wp_unslash( $_GET['pb_aff_dir'] ) ) ) ? 'asc' : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$aff_paged = max( 1, isset( $_GET['pb_aff_paged'] ) ? absint( $_GET['pb_aff_paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$aff_per_page = (int) apply_filters( 'pb_affiliates_admin_affiliate_perf_table_per_page', 10 );
		$aff_per_page = max( 5, min( 50, $aff_per_page ) );

		$ord_sort = isset( $_GET['pb_ord_sort'] ) ? sanitize_key( wp_unslash( $_GET['pb_ord_sort'] ) ) : 'date'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $ord_sort, array( 'order_id', 'date', 'status', 'affiliate', 'commission' ), true ) ) {
			$ord_sort = 'date';
		}
		$ord_dir = ( isset( $_GET['pb_ord_dir'] ) && 'asc' === sanitize_key( wp_unslash( $_GET['pb_ord_dir'] ) ) ) ? 'asc' : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ord_paged = max( 1, isset( $_GET['pb_ord_paged'] ) ? absint( $_GET['pb_ord_paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ord_per_page = (int) apply_filters( 'pb_affiliates_admin_period_orders_per_page', 10 );
		$ord_per_page = max( 5, min( 100, $ord_per_page ) );

		$period_orders_pack = PB_Affiliates_Reports::get_admin_affiliate_orders_period_list(
			$this->start_date,
			$this->end_date,
			$aff_id,
			$ord_paged,
			$ord_per_page,
			$ord_sort,
			$ord_dir,
			$report_order_statuses
		);
		$period_orders_rows        = $period_orders_pack['rows'];
		$period_orders_total       = (int) $period_orders_pack['total'];
		$period_orders_total_pages = (int) $period_orders_pack['total_pages'];
		$ord_paged                 = (int) $period_orders_pack['page'];
		$report_commission_total   = isset( $period_orders_pack['commission_sum'] ) ? (float) $period_orders_pack['commission_sum'] : 0.0;
		$report_commission_available = PB_Affiliates_Reports::get_admin_pending_manual_commission_total_for_order_ids(
			isset( $period_orders_pack['order_ids'] ) ? $period_orders_pack['order_ids'] : array(),
			$aff_id
		);

		list( $start_sql, $end_sql_excl ) = PB_Affiliates_Reports::admin_click_hit_bounds( $this->start_date, $this->end_date );

		$by_day   = PB_Affiliates_Reports::get_admin_click_counts_by_day( $start_sql, $end_sql_excl, $aff_id );
		$by_month = PB_Affiliates_Reports::get_admin_click_counts_by_month( $start_sql, $end_sql_excl, $aff_id );

		$order_bucket_group = ( 'day' === $this->chart_groupby ) ? 'day' : 'month';
		$order_by_bucket    = PB_Affiliates_Reports::get_admin_affiliate_order_buckets( $this->start_date, $this->end_date, $aff_id, $order_bucket_group, $report_order_statuses );
		$orders_in_range    = 0;
		$orders_total_in_range = 0.0;
		foreach ( $order_by_bucket as $ob ) {
			$orders_in_range       += (int) $ob['count'];
			$orders_total_in_range += (float) $ob['total'];
		}

		$line_points       = array();
		$order_count_pts   = array();
		$order_total_pts   = array();
		$tz_wp = wp_timezone();
		if ( 'day' === $this->chart_groupby ) {
			$day_cursor = ( new DateTimeImmutable( '@' . (int) $this->start_date ) )->setTimezone( $tz_wp )->setTime( 0, 0, 0 );
			for ( $i = 0; $i <= $this->chart_interval; $i++ ) {
				$d     = $day_cursor->modify( '+' . $i . ' day' );
				$key   = $d->format( 'Y-m-d' );
				$c     = isset( $by_day[ $key ] ) ? $by_day[ $key ] : 0;
				$ts_ms = (int) ( $d->getTimestamp() * 1000 );
				$line_points[]     = array( $ts_ms, $c );
				$o_row             = isset( $order_by_bucket[ $key ] ) ? $order_by_bucket[ $key ] : null;
				$oc                = $o_row ? (int) $o_row['count'] : 0;
				$ot                = $o_row ? (float) $o_row['total'] : 0.0;
				$order_count_pts[] = array( $ts_ms, $oc );
				$order_total_pts[] = array( $ts_ms, $ot );
			}
		} else {
			$start_month = ( new DateTimeImmutable( '@' . (int) $this->start_date ) )->setTimezone( $tz_wp )->setTime( 0, 0, 0 );
			$cy          = (int) $start_month->format( 'Y' );
			$cm          = (int) $start_month->format( 'n' );
			for ( $i = 0; $i <= $this->chart_interval; $i++ ) {
				$key   = sprintf( '%04d-%02d', $cy, $cm );
				$c     = isset( $by_month[ $key ] ) ? $by_month[ $key ] : 0;
				$month = DateTimeImmutable::createFromFormat( '!Y-m-d', sprintf( '%04d-%02d-01', $cy, $cm ), $tz_wp );
				$ts_ms = $month ? (int) ( $month->getTimestamp() * 1000 ) : 0;
				$line_points[]     = array( $ts_ms, $c );
				$o_row             = isset( $order_by_bucket[ $key ] ) ? $order_by_bucket[ $key ] : null;
				$oc                = $o_row ? (int) $o_row['count'] : 0;
				$ot                = $o_row ? (float) $o_row['total'] : 0.0;
				$order_count_pts[] = array( $ts_ms, $oc );
				$order_total_pts[] = array( $ts_ms, $ot );
				++$cm;
				if ( $cm > 12 ) {
					$cm = 1;
					++$cy;
				}
			}
		}

		$via_rows = PB_Affiliates_Reports::get_admin_clicks_grouped_by_via( $start_sql, $end_sql_excl, $aff_id, $via_order );

		$pie_total = 0;
		foreach ( (array) $via_rows as $row ) {
			$pie_total += (int) $row->cnt;
		}

		$pie_colors = array( '#3498db', '#1abc9c', '#9b59b6', '#f1c40f', '#e74c3c', '#34495e', '#8fdece', '#d35400' );
		$pie_series = array();
		$pci        = 0;
		$tip_suffix = ' ' . __( 'cliques', 'pb-affiliates' );
		foreach ( (array) $via_rows as $row ) {
			$raw_via = isset( $row->via ) ? (string) $row->via : '';
			$cnt     = (int) $row->cnt;
			$human   = self::human_label_for_via( $raw_via );
			$pct     = $pie_total > 0 ? round( ( 100 * $cnt ) / $pie_total, 1 ) : 0.0;
			/* translators: 1: count 2: percent */
			$stats_line = sprintf( __( '%1$s cliques · %2$s%%', 'pb-affiliates' ), number_format_i18n( $cnt ), wc_format_decimal( $pct, 1 ) );
			$pie_series[] = array(
				'label'            => $human,
				'data'             => (string) $cnt,
				'color'            => $pie_colors[ $pci % count( $pie_colors ) ],
				'enable_tooltip'   => true,
				'append_tooltip'   => $tip_suffix,
				'legend_primary'   => $human,
				'legend_stats'     => $stats_line,
				'legend_note'      => $raw_via,
				'legend_count'     => $cnt,
				'legend_percent'   => $pct,
				'flot_label'       => number_format_i18n( $cnt ) . ' · ' . wc_format_decimal( $pct, 1 ) . '%',
			);
			++$pci;
		}

		if ( empty( $pie_series ) ) {
			$pie_series[] = array(
				'label'            => __( 'Sem dados', 'pb-affiliates' ),
				'data'             => '0',
				'color'            => '#dbe1e3',
				'enable_tooltip'   => true,
				'append_tooltip'   => $tip_suffix,
				'legend_primary'   => __( 'Sem dados', 'pb-affiliates' ),
				'legend_stats'     => '0 ' . __( 'cliques', 'pb-affiliates' ),
				'legend_note'      => '',
				'legend_count'     => 0,
				'legend_percent'   => 0,
				'flot_label'       => '0%',
			);
		}

		$referer_domain_merged = PB_Affiliates_Reports::get_admin_referer_domain_merged_rows(
			$this->start_date,
			$this->end_date,
			$start_sql,
			$end_sql_excl,
			$aff_id,
			$hide_empty_referer,
			$report_order_statuses
		);

		$referer_domain_bubble_work = $referer_domain_merged;
		usort(
			$referer_domain_bubble_work,
			static function ( $a, $b ) {
				if ( $a['orders'] !== $b['orders'] ) {
					return $b['orders'] <=> $a['orders'];
				}
				return $b['clicks'] <=> $a['clicks'];
			}
		);
		$referer_domain_bubble_work = array_slice( $referer_domain_bubble_work, 0, 15 );
		$referer_domain_bubble_rows = array();
		foreach ( $referer_domain_bubble_work as $brow ) {
			$h = $brow['h'];
			$referer_domain_bubble_rows[] = array(
				'h'      => $h,
				'label'  => self::human_label_for_referer_domain( $h ),
				'clicks' => (int) $brow['clicks'],
				'orders' => (int) $brow['orders'],
			);
		}
		$referer_domain_table_all = array();
		foreach ( $referer_domain_merged as $mrow ) {
			$cl   = (int) $mrow['clicks'];
			$oc   = (int) $mrow['orders'];
			$rate = $cl > 0 ? round( ( 100.0 * $oc ) / $cl, 2 ) : null;
			$referer_domain_table_all[] = array(
				'h'         => $mrow['h'],
				'label'     => self::human_label_for_referer_domain( $mrow['h'] ),
				'clicks'    => $cl,
				'orders'    => $oc,
				'rate_pct'  => $rate,
				'rate_sort' => $cl > 0 ? ( $oc / $cl ) : null,
			);
		}
		if ( 'rate' === $ref_sort ) {
			$with_rate    = array();
			$without_rate = array();
			foreach ( $referer_domain_table_all as $r ) {
				if ( null === $r['rate_sort'] ) {
					$without_rate[] = $r;
				} else {
					$with_rate[] = $r;
				}
			}
			usort(
				$with_rate,
				static function ( $a, $b ) use ( $ref_dir ) {
					$c = $a['rate_sort'] <=> $b['rate_sort'];
					if ( 0 !== $c ) {
						return 'desc' === $ref_dir ? -$c : $c;
					}
					return $b['clicks'] <=> $a['clicks'];
				}
			);
			$referer_domain_table_all = array_merge( $with_rate, $without_rate );
		} else {
			usort(
				$referer_domain_table_all,
				static function ( $a, $b ) use ( $ref_sort, $ref_dir ) {
					if ( 'clicks' === $ref_sort ) {
						$c = $a['clicks'] <=> $b['clicks'];
					} else {
						$c = $a['orders'] <=> $b['orders'];
					}
					if ( 0 !== $c ) {
						return 'desc' === $ref_dir ? -$c : $c;
					}
					return $b['clicks'] <=> $a['clicks'];
				}
			);
		}
		$referer_domain_table_total      = count( $referer_domain_table_all );
		$referer_domain_table_total_pages = max( 1, (int) ceil( $referer_domain_table_total / $ref_per_page ) );
		$ref_paged                        = min( $ref_paged, $referer_domain_table_total_pages );
		$referer_domain_table_rows       = array_slice( $referer_domain_table_all, ( $ref_paged - 1 ) * $ref_per_page, $ref_per_page );

		$affiliate_perf_merged = PB_Affiliates_Reports::get_admin_affiliate_performance_merged_rows(
			$this->start_date,
			$this->end_date,
			$start_sql,
			$end_sql_excl,
			$aff_id,
			$report_order_statuses
		);
		$affiliate_perf_table_all = array();
		foreach ( $affiliate_perf_merged as $mrow ) {
			$aid  = (int) $mrow['affiliate_id'];
			$user = get_userdata( $aid );
			$code = (string) get_user_meta( $aid, 'pb_affiliate_code', true );
			$name = $user
				? $user->display_name
				: sprintf(
					/* translators: %d: user ID */
					__( 'Utilizador #%d (removido)', 'pb-affiliates' ),
					$aid
				);
			$cl   = (int) $mrow['clicks'];
			$oc   = (int) $mrow['orders'];
			$rate = $cl > 0 ? round( ( 100.0 * $oc ) / $cl, 2 ) : null;
			$affiliate_perf_table_all[] = array(
				'affiliate_id' => $aid,
				'name'         => $name,
				'code'         => $code,
				'clicks'       => $cl,
				'orders'       => $oc,
				'rate_pct'     => $rate,
				'rate_sort'    => $cl > 0 ? ( $oc / $cl ) : null,
				'detail_url'   => $user ? PB_Affiliates_Admin_User_Detail::url( $aid ) : '',
			);
		}
		if ( 'rate' === $aff_sort ) {
			$with_rate    = array();
			$without_rate = array();
			foreach ( $affiliate_perf_table_all as $r ) {
				if ( null === $r['rate_sort'] ) {
					$without_rate[] = $r;
				} else {
					$with_rate[] = $r;
				}
			}
			usort(
				$with_rate,
				static function ( $a, $b ) use ( $aff_dir ) {
					$c = $a['rate_sort'] <=> $b['rate_sort'];
					if ( 0 !== $c ) {
						return 'desc' === $aff_dir ? -$c : $c;
					}
					return $a['affiliate_id'] <=> $b['affiliate_id'];
				}
			);
			$affiliate_perf_table_all = array_merge( $with_rate, $without_rate );
		} else {
			usort(
				$affiliate_perf_table_all,
				static function ( $a, $b ) use ( $aff_sort, $aff_dir ) {
					if ( 'clicks' === $aff_sort ) {
						$c = $a['clicks'] <=> $b['clicks'];
					} else {
						$c = $a['orders'] <=> $b['orders'];
					}
					if ( 0 !== $c ) {
						return 'desc' === $aff_dir ? -$c : $c;
					}
					return $a['affiliate_id'] <=> $b['affiliate_id'];
				}
			);
		}
		$affiliate_perf_table_total       = count( $affiliate_perf_table_all );
		$affiliate_perf_table_total_pages = max( 1, (int) ceil( $affiliate_perf_table_total / $aff_per_page ) );
		$aff_paged                        = min( $aff_paged, $affiliate_perf_table_total_pages );
		$affiliate_perf_table_rows        = array_slice( $affiliate_perf_table_all, ( $aff_paged - 1 ) * $aff_per_page, $aff_per_page );

		$affiliates = get_users(
			array(
				'meta_key'     => 'pb_affiliate_status',
				'meta_value'   => 'active',
				'meta_compare' => '=',
				'orderby'      => 'display_name',
				'order'        => 'ASC',
				'fields'       => array( 'ID', 'display_name' ),
			)
		);

		$total_clicks = array_sum( 'day' === $this->chart_groupby ? $by_day : $by_month );

		$chart_data_json = wp_json_encode(
			array(
				'clicks'       => array_values( $line_points ),
				'order_counts' => array_values( $order_count_pts ),
				'order_totals' => array_values( $order_total_pts ),
			)
		);

		$order_chart_money = array(
			'symbol'       => get_woocommerce_currency_symbol(),
			'decimals'     => wc_get_price_decimals(),
			'decimal_sep'  => wc_get_price_decimal_separator(),
			'thousand_sep' => wc_get_price_thousand_separator(),
			'code'         => get_woocommerce_currency(),
		);
		$pie_series_json               = wp_json_encode( $pie_series );
		$referer_domain_bubble_series_json = wp_json_encode( $referer_domain_bubble_rows );

		$pb_report_nav_base = array(
			'page'  => 'pb-affiliates-report',
			'range' => $current_range,
		);
		if ( $aff_id > 0 ) {
			$pb_report_nav_base['pb_affiliate'] = $aff_id;
		}
		if ( $pb_order_status !== '' ) {
			$pb_report_nav_base['pb_order_status'] = $pb_order_status;
		}
		if ( $hide_empty_referer ) {
			$pb_report_nav_base['pb_hide_empty_referer'] = '1';
		}
		if ( 'custom' === $current_range && ! empty( $_GET['start_date'] ) && ! empty( $_GET['end_date'] ) ) {
			$pb_report_nav_base['start_date'] = wc_clean( wp_unslash( $_GET['start_date'] ) );
			$pb_report_nav_base['end_date']   = wc_clean( wp_unslash( $_GET['end_date'] ) );
			if ( isset( $_GET['wc_reports_nonce'] ) ) {
				$pb_report_nav_base['wc_reports_nonce'] = sanitize_text_field( wp_unslash( $_GET['wc_reports_nonce'] ) );
			}
		}
		$pb_report_nav_base['pb_ref_sort'] = $ref_sort;
		$pb_report_nav_base['pb_ref_dir']  = $ref_dir;
		$pb_report_nav_base['pb_aff_sort'] = $aff_sort;
		$pb_report_nav_base['pb_aff_dir']  = $aff_dir;
		$pb_report_nav_base['pb_ord_sort'] = $ord_sort;
		$pb_report_nav_base['pb_ord_dir']  = $ord_dir;

		require PB_AFFILIATES_PATH . 'includes/admin/views/report-clicks.php';
	}
}
