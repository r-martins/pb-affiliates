<?php
/**
 * Admin / affiliate reports (aggregates).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Reports
 */
class PB_Affiliates_Reports {

	/**
	 * Cláusula meta_query para `_pb_affiliate_id` = usuário (comparação numérica, alinhada a HPOS).
	 *
	 * @param int $user_id ID do afiliado.
	 * @return array
	 */
	private static function wc_meta_affiliate_is_user( $user_id ) {
		return array(
			'key'     => '_pb_affiliate_id',
			'value'   => absint( $user_id ),
			'compare' => '=',
			'type'    => 'NUMERIC',
		);
	}

	/**
	 * Converte o par start/end do WC_Admin_Report para datas no fuso de WordPress (wp_timezone).
	 *
	 * `hit_at` no log usa `current_time( 'mysql' )` (hora local do site). Em HPOS, `wc_get_orders`
	 * com timestamps numéricos trata-os como UTC — usar always `Y-m-d...Y-m-d` em hora local.
	 *
	 * @param int $start_ts Início (instante Unix, geralmente meia-noite local do 1.º dia).
	 * @param int $end_ts   Meia-noite local do último dia **incluído** no intervalo WC.
	 * @return array{start_date:string,end_date:string,start_mysql_inclusive:string,end_mysql_exclusive:string} start/end_date = Y-m-d.
	 */
	private static function wc_report_range_site_tz( $start_ts, $end_ts ) {
		$tz = wp_timezone();
		$start_local = ( new DateTimeImmutable( '@' . (int) $start_ts ) )->setTimezone( $tz )->setTime( 0, 0, 0 );
		$end_day     = ( new DateTimeImmutable( '@' . (int) $end_ts ) )->setTimezone( $tz )->setTime( 0, 0, 0 );
		$end_excl    = $end_day->modify( '+1 day' );
		return array(
			'start_date'            => $start_local->format( 'Y-m-d' ),
			'end_date'              => $end_day->format( 'Y-m-d' ),
			'start_mysql_inclusive' => $start_local->format( 'Y-m-d H:i:s' ),
			'end_mysql_exclusive'   => $end_excl->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Summary for affiliate.
	 *
	 * @param int $user_id User ID.
	 * @return object|null
	 */
	public static function get_affiliate_summary( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_commissions';
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS orders,
				COALESCE(SUM(commission_amount),0) AS total,
				COALESCE(SUM(CASE WHEN status = 'pending' AND ( payment_method IS NULL OR payment_method = '' OR payment_method = 'manual' ) THEN commission_amount ELSE 0 END),0) AS pending_total,
				COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END),0) AS paid_total,
				COALESCE(SUM(CASE WHEN status = 'pending' AND ( payment_method IS NULL OR payment_method = '' OR payment_method = 'manual' ) THEN 1 ELSE 0 END),0) AS pending_count,
				COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END),0) AS paid_count
				FROM %i WHERE affiliate_id = %d",
				$table,
				$user_id
			)
		);
	}

	/**
	 * Agregados de comissão por afiliado (contagem de pedidos com registro + soma dos valores).
	 *
	 * @param array<int,int> $user_ids IDs de usuários.
	 * @return array<int, array{orders:int, commission_total:float}>
	 */
	public static function get_affiliate_commission_aggregates_map( array $user_ids ) {
		$user_ids = array_values( array_unique( array_filter( array_map( 'absint', $user_ids ) ) ) );
		$defaults = array();
		foreach ( $user_ids as $uid ) {
			$defaults[ $uid ] = array(
				'orders'             => 0,
				'commission_total'   => 0.0,
			);
		}
		if ( empty( $user_ids ) ) {
			return $defaults;
		}
		global $wpdb;
		$table        = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- IN list: only %d placeholders built from absint IDs.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT affiliate_id, COUNT(*) AS order_count, COALESCE(SUM(commission_amount), 0) AS commission_total FROM %i WHERE affiliate_id IN (' . $placeholders . ') GROUP BY affiliate_id',
				...array_merge( array( $table ), $user_ids )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		foreach ( (array) $rows as $row ) {
			$aid = (int) $row['affiliate_id'];
			if ( isset( $defaults[ $aid ] ) ) {
				$defaults[ $aid ]['orders']           = (int) $row['order_count'];
				$defaults[ $aid ]['commission_total'] = (float) $row['commission_total'];
			}
		}
		return $defaults;
	}

	/**
	 * Indica se o afiliado tem comissões marcadas como pagas com paid_at no ano civil (horário do site).
	 *
	 * @param int      $user_id Affiliate user ID.
	 * @param int|null $year    Ex.: 2026. null usa o ano atual em current_time.
	 * @return bool
	 */
	public static function affiliate_has_paid_commissions_in_year( $user_id, $year = null ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}
		if ( null === $year ) {
			$year = (int) current_time( 'Y' );
		} else {
			$year = (int) $year;
		}
		if ( $year < 1970 || $year > 2100 ) {
			return false;
		}

		$table = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$start = sprintf( '%04d-01-01 00:00:00', $year );
		$end   = sprintf( '%04d-12-31 23:59:59', $year );

		$n = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE affiliate_id = %d AND status = %s AND paid_at IS NOT NULL AND paid_at >= %s AND paid_at <= %s',
				$table,
				$user_id,
				'paid',
				$start,
				$end
			)
		);

		return $n > 0;
	}

	/**
	 * Resumo do dashboard do afiliado: totais na tabela de comissões + prévia para pedidos
	 * pendentes (atribuídos ainda sem registro de comissão na loja; ver
	 * {@see PB_Affiliates_Commission::create_commission_for_order}).
	 *
	 * @param int $user_id Affiliate user ID.
	 * @return object orders, total, pending_total, paid_total, pending_count, paid_count,
	 *                estimated_total, estimated_count, total_with_estimates, pending_with_estimates.
	 */
	public static function get_affiliate_dashboard_totals( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		$base    = self::get_affiliate_summary( $user_id );
		if ( ! is_object( $base ) ) {
			$base = (object) array(
				'orders'         => 0,
				'total'          => 0,
				'pending_total'  => 0,
				'paid_total'     => 0,
				'pending_count'  => 0,
				'paid_count'     => 0,
			);
		}

		$base->estimated_total  = 0.0;
		$base->estimated_count  = 0;
		$base->total_with_estimates   = (float) $base->total;
		$base->pending_with_estimates = (float) $base->pending_total;

		if ( ! function_exists( 'wc_get_orders' ) || ! class_exists( 'PB_Affiliates_Commission' ) ) {
			return $base;
		}

		$table     = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$order_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT order_id FROM %i WHERE affiliate_id = %d', $table, $user_id ) );
		$have_row  = array_fill_keys( array_map( 'absint', (array) $order_ids ), true );

		/**
		 * Máximo de pedidos atribuídos a analisar ao somar prévia de pedidos pendentes (evita sobrecarga).
		 *
		 * @param int $max Max orders (default 2000, capped 50–20000).
		 */
		$max_scan = (int) apply_filters( 'pb_affiliates_dashboard_estimate_max_orders_scan', 2000 );
		$max_scan = max( 50, min( 20000, $max_scan ) );
		$batch    = 80;
		$page     = 1;
		$scanned  = 0;

		while ( true ) {
			$orders = wc_get_orders(
				array(
					'limit'      => $batch,
					'page'       => $page,
					'orderby'    => 'date',
					'order'      => 'DESC',
					'meta_query' => array( self::wc_meta_affiliate_is_user( $user_id ) ),
					'return'     => 'objects',
				)
			);
			if ( empty( $orders ) ) {
				break;
			}
			foreach ( $orders as $order ) {
				if ( $scanned >= $max_scan ) {
					break 2;
				}
				if ( ! $order instanceof WC_Order ) {
					continue;
				}
				++$scanned;
				$oid = $order->get_id();
				if ( isset( $have_row[ $oid ] ) ) {
					continue;
				}
				$preview = PB_Affiliates_Commission::calculate_commission_preview_for_order( $order, $user_id );
				if ( is_array( $preview ) && isset( $preview['amount'] ) && (float) $preview['amount'] > 0 ) {
					$base->estimated_total += (float) $preview['amount'];
					++$base->estimated_count;
				}
			}
			if ( count( $orders ) < $batch ) {
				break;
			}
			++$page;
		}

		$base->estimated_total        = (float) $base->estimated_total;
		$base->estimated_count        = (int) $base->estimated_count;
		$base->total_with_estimates   = (float) $base->total + $base->estimated_total;
		$base->pending_with_estimates = (float) $base->pending_total + $base->estimated_total;

		return $base;
	}

	/**
	 * Global summary (admin).
	 *
	 * @param array $filters Mesmos filtros de {@see get_admin_commissions_list_paginated()}.
	 * @return object|null
	 */
	public static function get_global_summary( $filters = array() ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$clause = self::admin_commissions_where_clause( $filters );
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE from admin_commissions_where_clause; fragments are placeholders only.
		if ( ! empty( $clause['values'] ) ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT COUNT(*) AS cnt, COALESCE(SUM(c.commission_amount),0) AS total FROM %i c WHERE ' . $clause['sql'],
					...array_merge( array( $table ), $clause['values'] )
				)
			);
		} else {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT COUNT(*) AS cnt, COALESCE(SUM(c.commission_amount),0) AS total FROM %i c WHERE ' . $clause['sql'],
					$table
				)
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $row;
	}

	/**
	 * Valores de `attributed_via` usados no filtro da lista admin Pedidos.
	 *
	 * @return string[]
	 */
	public static function admin_commission_attribution_via_whitelist() {
		// link/qr: valores legados em alguns ambientes; o rótulo cai no fallback em human_label_for_via.
		return array( 'cookie_param', 'referrer', 'coupon', 'renewal', 'unknown', 'link', 'qr' );
	}

	/**
	 * Opções de filtro "Via" com rótulos traduzidos.
	 *
	 * @return array<string, string> slug => label
	 */
	public static function get_admin_commission_via_filter_choices() {
		$out = array();
		foreach ( self::admin_commission_attribution_via_whitelist() as $k ) {
			$out[ $k ] = class_exists( 'PB_Affiliates_Admin_Click_Report', false )
				? PB_Affiliates_Admin_Click_Report::human_label_for_via( $k )
				: $k;
		}
		return $out;
	}

	/**
	 * Monta WHERE para listagem/resumo admin (apenas alias `c`).
	 *
	 * @param array $filters affiliate_id, order_id, status, via, date_from, date_to (Y-m-d).
	 * @return array{sql: string, values: array<int, mixed>}
	 */
	private static function admin_commissions_where_clause( $filters ) {
		$f      = self::normalize_admin_commissions_filters( $filters );
		$aff_id = (int) $f['affiliate_id'];
		$oid    = (int) $f['order_id'];
		$status = (string) $f['status'];
		$via    = (string) $f['via'];
		$df     = (string) $f['date_from'];
		$dt     = (string) $f['date_to'];

		$clauses = array();
		$values  = array();
		if ( $aff_id > 0 ) {
			$clauses[] = 'c.affiliate_id = %d';
			$values[]  = $aff_id;
		}
		if ( $oid > 0 ) {
			$clauses[] = 'c.order_id = %d';
			$values[]  = $oid;
		}
		if ( '' !== $status && in_array( $status, array( 'pending', 'paid' ), true ) ) {
			$clauses[] = 'c.status = %s';
			$values[]  = $status;
		}
		$allowed_via = array( 'cookie_param', 'referrer', 'coupon', 'link', 'qr' );
		if ( '' !== $via && in_array( $via, $allowed_via, true ) ) {
			$clauses[] = 'c.attributed_via = %s';
			$values[]  = $via;
		}
		if ( '' !== $df ) {
			$clauses[] = 'c.created_at >= %s';
			$values[]  = $df . ' 00:00:00';
		}
		if ( '' !== $dt ) {
			$clauses[] = 'c.created_at <= %s';
			$values[]  = $dt . ' 23:59:59';
		}
		return array(
			'sql'    => $clauses ? implode( ' AND ', $clauses ) : '1=1',
			'values' => $values,
		);
	}

	/**
	 * @param mixed $date Raw.
	 * @return string Y-m-d ou ''.
	 */
	private static function sanitize_admin_date_ymd( $date ) {
		$d = trim( (string) $date );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
			return '';
		}
		$ts = strtotime( $d . ' 12:00:00' );
		if ( false === $ts ) {
			return '';
		}
		return $d;
	}

	/**
	 * Afiliados distintos da tabela de comissões (para filtro admin).
	 *
	 * @param int $limit Máximo de linhas.
	 * @return array<int, array{id:int, label:string}>
	 */
	public static function get_admin_commissions_distinct_affiliates( $limit = 300 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$limit = max( 10, min( 2000, (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT affiliate_id FROM %i WHERE affiliate_id > 0 ORDER BY affiliate_id ASC LIMIT %d', $table, $limit ) );
		$out = array();
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$user = get_userdata( $id );
			$out[] = array(
				'id'    => $id,
				'label' => $user
					? sprintf( '%s (#%d)', $user->display_name, $id )
					: sprintf( /* translators: %d: user ID */ __( 'Usuário #%d', 'pb-affiliates' ), $id ),
			);
		}
		return $out;
	}

	/**
	 * Lista paginada da tabela de comissões (tela admin Pedidos).
	 *
	 * @param int    $page     Página 1-based.
	 * @param int    $per_page Linhas por página.
	 * @param string $orderby  id|order_id|affiliate|commission|status|via.
	 * @param string $order    asc|desc.
	 * @param array  $filters  affiliate_id, order_id, status, via, date_from, date_to.
	 * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int, orderby: string, order: string, filters: array<string, mixed>}
	 */
	public static function get_admin_commissions_list_paginated( $page, $per_page, $orderby = 'id', $order = 'desc', $filters = array() ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$users    = $wpdb->users;
		$page     = max( 1, (int) $page );
		$per_page = max( 5, min( 100, (int) $per_page ) );

		$orderby    = sanitize_key( (string) $orderby );
		$allowed_ob = array( 'id', 'order_id', 'affiliate', 'commission', 'status', 'via' );
		$orderby    = in_array( $orderby, $allowed_ob, true ) ? $orderby : 'id';
		$order      = ( 'asc' === strtolower( (string) $order ) ) ? 'ASC' : 'DESC';

		$clause     = self::admin_commissions_where_clause( $filters );
		$where_sql  = $clause['sql'];
		$where_vals = $clause['values'];
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE from admin_commissions_where_clause; fragments are placeholders only.
		$total = ! empty( $where_vals )
			? (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i c WHERE ' . $where_sql,
					...array_merge( array( $table ), $where_vals )
				)
			)
			: (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i c WHERE ' . $where_sql,
					$table
				)
			);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = min( $page, $total_pages );
		$offset      = ( $page - 1 ) * $per_page;

		$orderby_map = array(
			'id'         => 'c.id',
			'order_id'   => 'c.order_id',
			'affiliate'  => 'u.display_name',
			'commission' => 'c.commission_amount',
			'status'     => 'c.status',
			'via'        => 'c.attributed_via',
		);
		$ob_col = $orderby_map[ $orderby ];
		if ( 'affiliate' === $orderby ) {
			// Usuários sem linha em wp_users: ordenar por nome; nulos por último na ordem “natural”.
			$order_sql = '(u.display_name IS NULL) ASC, u.display_name ' . $order . ', c.id DESC';
		} else {
			$order_sql = $ob_col . ' ' . $order . ', c.id DESC';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- ORDER BY from whitelist; WHERE uses placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT c.*, u.display_name AS affiliate_display_name
			FROM %i c
			LEFT JOIN %i u ON u.ID = c.affiliate_id
			WHERE ' . $where_sql . '
			ORDER BY ' . $order_sql . '
			LIMIT %d OFFSET %d',
				...array_merge( array( $table, $users ), $where_vals, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$norm = self::normalize_admin_commissions_filters( $filters );

		return array(
			'rows'        => is_array( $rows ) ? $rows : array(),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $total_pages,
			'orderby'     => $orderby,
			'order'       => strtolower( $order ),
			'filters'     => $norm,
		);
	}

	/**
	 * Filtros da lista admin Pedidos (normalizados para URLs e formulários).
	 *
	 * @param array $filters Raw.
	 * @return array<string, int|string>
	 */
	public static function normalize_admin_commissions_filters( $filters ) {
		$filters = wp_parse_args(
			is_array( $filters ) ? $filters : array(),
			array(
				'affiliate_id' => 0,
				'order_id'     => 0,
				'status'       => '',
				'via'          => '',
				'date_from'    => '',
				'date_to'      => '',
			)
		);
		return array(
			'affiliate_id' => absint( $filters['affiliate_id'] ),
			'order_id'     => absint( $filters['order_id'] ),
			'status'       => sanitize_key( (string) $filters['status'] ),
			'via'          => sanitize_key( (string) $filters['via'] ),
			'date_from'    => self::sanitize_admin_date_ymd( $filters['date_from'] ),
			'date_to'      => self::sanitize_admin_date_ymd( $filters['date_to'] ),
		);
	}

	/**
	 * Args de query string para ordenação/paginação (sem aninhar arrays).
	 *
	 * @param array $filters Normalizados.
	 * @return array<string, string>
	 */
	public static function admin_orders_report_filter_query_args( $filters ) {
		$f   = self::normalize_admin_commissions_filters( $filters );
		$out = array();
		if ( $f['affiliate_id'] > 0 ) {
			$out['pb_aff_ord_f_affiliate_id'] = (string) $f['affiliate_id'];
		}
		if ( $f['order_id'] > 0 ) {
			$out['pb_aff_ord_f_order_id'] = (string) $f['order_id'];
		}
		if ( '' !== $f['status'] ) {
			$out['pb_aff_ord_f_status'] = $f['status'];
		}
		if ( '' !== $f['via'] ) {
			$out['pb_aff_ord_f_via'] = $f['via'];
		}
		if ( '' !== $f['date_from'] ) {
			$out['pb_aff_ord_f_date_from'] = $f['date_from'];
		}
		if ( '' !== $f['date_to'] ) {
			$out['pb_aff_ord_f_date_to'] = $f['date_to'];
		}
		return $out;
	}

	/**
	 * Constrói array de filtros a partir dos parâmetros GET compactos (pb_aff_ord_f_*).
	 *
	 * @return array<string, int|string>
	 */
	public static function admin_orders_report_filters_from_compact_get() {
		return self::normalize_admin_commissions_filters(
			array(
				'affiliate_id' => isset( $_GET['pb_aff_ord_f_affiliate_id'] ) ? absint( wp_unslash( $_GET['pb_aff_ord_f_affiliate_id'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'order_id'     => isset( $_GET['pb_aff_ord_f_order_id'] ) ? absint( wp_unslash( $_GET['pb_aff_ord_f_order_id'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'status'       => isset( $_GET['pb_aff_ord_f_status'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_ord_f_status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'via'          => isset( $_GET['pb_aff_ord_f_via'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_ord_f_via'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'date_from'    => isset( $_GET['pb_aff_ord_f_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['pb_aff_ord_f_date_from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'date_to'      => isset( $_GET['pb_aff_ord_f_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['pb_aff_ord_f_date_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}

	/**
	 * Resumo admin: saldo manual pendente por afiliado (exclui modo split — repasse via PagBank).
	 *
	 * @param string $now_gmt Datetime UTC (Y-m-d H:i:s) para respeitar available_at.
	 * @return array<int, array{affiliate_id:int,pending_count:int,pending_total:float,display_name:string,user_email:string}>
	 */
	public static function get_admin_manual_pending_by_affiliate( $now_gmt ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$users = $wpdb->users;
		$now_gmt = sanitize_text_field( (string) $now_gmt );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $now_gmt ) ) {
			$now_gmt = gmdate( 'Y-m-d H:i:s' );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT c.affiliate_id,
				COUNT(*) AS pending_count,
				COALESCE(SUM(c.commission_amount), 0) AS pending_total,
				MAX(u.display_name) AS display_name,
				MAX(u.user_email) AS user_email
			FROM %i c
			LEFT JOIN %i u ON u.ID = c.affiliate_id
			WHERE c.status = \'pending\'
			AND ( c.payment_method IS NULL OR c.payment_method = \'\' OR c.payment_method = \'manual\' )
			AND ( c.available_at IS NULL OR c.available_at <= %s )
			GROUP BY c.affiliate_id
			HAVING pending_total > 0
			ORDER BY pending_total DESC',
				$table,
				$users,
				$now_gmt
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Estatística de comissões manuais já reservadas em saques pendentes (por afiliado).
	 *
	 * @param string $now_gmt Datetime UTC para available_at.
	 * @return array<int, array{total:float,count:int}>
	 */
	private static function get_locked_manual_pending_stats_by_affiliate( $now_gmt ) {
		global $wpdb;
		$now_gmt = sanitize_text_field( (string) $now_gmt );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $now_gmt ) ) {
			$now_gmt = gmdate( 'Y-m-d H:i:s' );
		}
		$wtable = $wpdb->prefix . 'pagbank_affiliate_withdrawals';
		$ctable = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$pending = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT affiliate_id, commission_ids_json FROM %i WHERE status = %s',
				$wtable,
				'pending'
			),
			ARRAY_A
		);
		$id_sets = array();
		foreach ( (array) $pending as $row ) {
			$aid = (int) $row['affiliate_id'];
			if ( $aid <= 0 ) {
				continue;
			}
			$decoded = json_decode( (string) $row['commission_ids_json'], true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			foreach ( $decoded as $cid ) {
				$cid = absint( $cid );
				if ( $cid > 0 ) {
					if ( ! isset( $id_sets[ $aid ] ) ) {
						$id_sets[ $aid ] = array();
					}
					$id_sets[ $aid ][ $cid ] = true;
				}
			}
		}
		$out = array();
		foreach ( $id_sets as $aid => $map ) {
			$ids = array_keys( $map );
			if ( empty( $ids ) ) {
				continue;
			}
			$ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- IN placeholders match absint commission IDs; one unpack only (PHP disallows args after ...).
			$stat = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(commission_amount), 0) AS s, COUNT(*) AS c FROM %i
					WHERE affiliate_id = %d AND id IN ({$ph}) AND status = %s
					AND ( payment_method IS NULL OR payment_method = '' OR payment_method = %s )
					AND ( available_at IS NULL OR available_at <= %s )",
					...array_merge( array( $ctable, $aid ), array_values( $ids ), array( 'pending', 'manual', $now_gmt ) )
				),
				ARRAY_A
			);
			if ( is_array( $stat ) ) {
				$out[ (int) $aid ] = array(
					'total' => (float) $stat['s'],
					'count' => (int) $stat['c'],
				);
			}
		}
		return $out;
	}

	/**
	 * Saldo manual “livre” por afiliado: exclui linhas já em pedido de saque pendente.
	 *
	 * @param string $now_gmt Datetime UTC.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_admin_manual_pending_by_affiliate_display( $now_gmt ) {
		$rows   = self::get_admin_manual_pending_by_affiliate( $now_gmt );
		$locked = self::get_locked_manual_pending_stats_by_affiliate( $now_gmt );
		$out    = array();
		foreach ( $rows as $r ) {
			$aid     = (int) $r['affiliate_id'];
			$lt      = isset( $locked[ $aid ] ) ? $locked[ $aid ]['total'] : 0.0;
			$lc      = isset( $locked[ $aid ] ) ? $locked[ $aid ]['count'] : 0;
			$net_t   = max( 0.0, (float) $r['pending_total'] - $lt );
			$net_c   = max( 0, (int) $r['pending_count'] - $lc );
			if ( $net_t < 0.009 && 0 === $net_c ) {
				continue;
			}
			$r['pending_total']              = $net_t;
			$r['pending_count']              = $net_c;
			$r['locked_in_withdrawal_total'] = $lt;
			$r['locked_in_withdrawal_count'] = $lc;
			$out[]                           = $r;
		}
		return $out;
	}

	/**
	 * Total de visitas atribuídas desde uma data (site timezone).
	 *
	 * @param int    $user_id Affiliate ID.
	 * @param string $since   Datetime SQL (Y-m-d H:i:s).
	 * @return int
	 */
	public static function count_clicks_since( $user_id, $since ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_click_log';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE affiliate_id = %d AND hit_at >= %s',
				$table,
				(int) $user_id,
				$since
			)
		);
	}

	/**
	 * Total de eventos de clique no histórico para o afiliado.
	 *
	 * @param int $user_id Affiliate ID.
	 * @return int
	 */
	public static function count_clicks_for_affiliate( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_click_log';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE affiliate_id = %d',
				$table,
				(int) $user_id
			)
		);
	}

	/**
	 * Eventos de clique paginados (mais recentes primeiro).
	 *
	 * @param int $user_id  Affiliate ID.
	 * @param int $per_page Itens por página.
	 * @param int $page     Página (1-based).
	 * @return array<int, array{hit_at:string, via:string, client_ip?:string, visited_url?:string}>
	 */
	public static function get_recent_clicks_paged( $user_id, $per_page, $page ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'pagbank_affiliate_click_log';
		$per_page = max( 1, min( 50, (int) $per_page ) );
		$page     = max( 1, (int) $page );
		$offset   = ( $page - 1 ) * $per_page;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT hit_at, via, client_ip, visited_url FROM %i WHERE affiliate_id = %d ORDER BY id DESC LIMIT %d OFFSET %d',
				$table,
				(int) $user_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Pedidos atribuídos ao afiliado, contados por dia da data de criação (fuso do site).
	 *
	 * @param int               $user_id Affiliate ID.
	 * @param DateTimeImmutable $start   Início inclusivo (meia-noite local).
	 * @param DateTimeImmutable $end     Fim exclusivo (meia-noite do dia seguinte ao último dia).
	 * @return array<string,int> Chave Y-m-d => quantidade.
	 */
	public static function get_affiliate_order_counts_by_day_for_range( $user_id, DateTimeImmutable $start, DateTimeImmutable $end ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$from           = $start->format( 'Y-m-d' );
		$last_inclusive = $end->modify( '-1 day' )->format( 'Y-m-d' );
		if ( $from > $last_inclusive ) {
			return array();
		}

		$ids = wc_get_orders(
			array(
				'limit'        => -1,
				'return'       => 'ids',
				'date_created' => $from . '...' . $last_inclusive,
				'meta_query'   => array(
					self::wc_meta_affiliate_is_user( $user_id ),
				),
			)
		);

		$range_first = $start->format( 'Y-m-d' );
		$range_last  = $end->modify( '-1 day' )->format( 'Y-m-d' );

		$out = array();
		foreach ( (array) $ids as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order ) {
				continue;
			}
			$dc = $order->get_date_created();
			if ( ! $dc ) {
				continue;
			}
			$key = $dc->date_i18n( 'Y-m-d' );
			if ( $key < $range_first || $key > $range_last ) {
				continue;
			}
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = 0;
			}
			++$out[ $key ];
		}

		return $out;
	}

	/**
	 * Etiquetas e contagens diárias para gráfico (dias completos no fuso do site).
	 *
	 * @param int $user_id Affiliate ID.
	 * @param int $days    7, 14, 30 ou 90.
	 * @return array{labels:array<int,string>,values:array<int,int>,order_values:array<int,int>,days:int}
	 */
	public static function get_affiliate_click_chart_series( $user_id, $days ) {
		$user_id  = (int) $user_id;
		$allowed  = array( 7, 14, 30, 90 );
		$days     = (int) $days;
		if ( ! in_array( $days, $allowed, true ) ) {
			$days = 30;
		}
		// current_datetime() alinha ao timezone do site (evita desvio vs. current_time( 'timestamp' ) / gmt_offset).
		$end   = current_datetime()->setTime( 0, 0, 0 )->modify( '+1 day' );
		$start = $end->modify( '-' . $days . ' days' );
		$counts = self::get_admin_click_counts_by_day(
			$start->format( 'Y-m-d H:i:s' ),
			$end->format( 'Y-m-d H:i:s' ),
			$user_id
		);
		$order_counts = self::get_affiliate_order_counts_by_day_for_range( $user_id, $start, $end );
		$labels        = array();
		$values        = array();
		$order_values  = array();
		$cursor        = $start;
		while ( $cursor < $end ) {
			$key              = $cursor->format( 'Y-m-d' );
			$labels[]         = wp_date( 'j M', $cursor->getTimestamp() );
			$values[]         = isset( $counts[ $key ] ) ? (int) $counts[ $key ] : 0;
			$order_values[]   = isset( $order_counts[ $key ] ) ? (int) $order_counts[ $key ] : 0;
			$cursor           = $cursor->modify( '+1 day' );
		}
		return array(
			'labels'        => $labels,
			'values'        => $values,
			'order_values'  => $order_values,
			'days'          => $days,
		);
	}

	/**
	 * Soma de cliques no intervalo [start, end) — fuso do site.
	 *
	 * @param int                 $user_id Affiliate ID.
	 * @param \DateTimeImmutable $start   Início inclusivo.
	 * @param \DateTimeImmutable $end     Fim exclusivo.
	 * @return int
	 */
	private static function count_clicks_in_datetime_range( $user_id, $start, $end ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0;
		}
		$rows = self::get_admin_click_counts_by_day(
			$start->format( 'Y-m-d H:i:s' ),
			$end->format( 'Y-m-d H:i:s' ),
			$user_id
		);
		$n = 0;
		foreach ( (array) $rows as $c ) {
			$n += (int) $c;
		}
		return $n;
	}

	/**
	 * Soma de pedidos atribuídos no intervalo [start, end).
	 *
	 * @param int                 $user_id Affiliate ID.
	 * @param \DateTimeImmutable $start   Início inclusivo.
	 * @param \DateTimeImmutable $end     Fim exclusivo.
	 * @return int
	 */
	private static function count_attributed_orders_in_datetime_range( $user_id, $start, $end ) {
		$daily = self::get_affiliate_order_counts_by_day_for_range( (int) $user_id, $start, $end );
		$n     = 0;
		foreach ( (array) $daily as $c ) {
			$n += (int) $c;
		}
		return $n;
	}

	/**
	 * Soma de comissões registradas (linhas na tabela) no intervalo [start, end).
	 *
	 * @param int                 $user_id Affiliate ID.
	 * @param \DateTimeImmutable $start   Início inclusivo.
	 * @param \DateTimeImmutable $end     Fim exclusivo.
	 * @return float
	 */
	private static function sum_registered_commissions_in_datetime_range( $user_id, $start, $end ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return 0.0;
		}
		$table = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$val = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(commission_amount), 0) FROM %i WHERE affiliate_id = %d AND created_at >= %s AND created_at < %s',
				$table,
				$user_id,
				$start->format( 'Y-m-d H:i:s' ),
				$end->format( 'Y-m-d H:i:s' )
			)
		);
		return (float) $val;
	}

	/**
	 * Variação percentual vs período anterior (null = sem base de comparação).
	 *
	 * @param float $current  Valor atual.
	 * @param float $previous Valor no período anterior.
	 * @return float|null
	 */
	private static function period_delta_percent( $current, $previous ) {
		$current  = (float) $current;
		$previous = (float) $previous;
		if ( $previous <= 0 && $current <= 0 ) {
			return null;
		}
		if ( $previous <= 0 ) {
			return null;
		}
		return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}

	/**
	 * Métricas do painel para N dias + período anterior (comparação).

	 * @param int $user_id Affiliate user ID.
	 * @param int $days    7, 14, 30 ou 90.
	 * @return array{
	 *   days: int,
	 *   range_label: string,
	 *   range_heading: string,
	 *   current: array{clicks: int, orders: int, commission: float},
	 *   previous: array{clicks: int, orders: int, commission: float},
	 *   delta_pct: array{clicks: ?float, orders: ?float, commission: ?float}
	 * }
	 */
	public static function get_affiliate_dashboard_period_bundle( $user_id, $days ) {
		$allowed = array( 7, 14, 30, 90 );
		$days    = (int) $days;
		if ( ! in_array( $days, $allowed, true ) ) {
			$days = 30;
		}
		$user_id = (int) $user_id;
		$end_excl = current_datetime()->setTime( 0, 0, 0 )->modify( '+1 day' );
		$start_incl = $end_excl->modify( '-' . $days . ' days' );
		$prev_end_excl = $start_incl;
		$prev_start_incl = $start_incl->modify( '-' . $days . ' days' );

		$cur_clicks = self::count_clicks_in_datetime_range( $user_id, $start_incl, $end_excl );
		$cur_orders = self::count_attributed_orders_in_datetime_range( $user_id, $start_incl, $end_excl );
		$cur_comm   = self::sum_registered_commissions_in_datetime_range( $user_id, $start_incl, $end_excl );

		$prev_clicks = self::count_clicks_in_datetime_range( $user_id, $prev_start_incl, $prev_end_excl );
		$prev_orders = self::count_attributed_orders_in_datetime_range( $user_id, $prev_start_incl, $prev_end_excl );
		$prev_comm   = self::sum_registered_commissions_in_datetime_range( $user_id, $prev_start_incl, $prev_end_excl );

		$last_day_ts = $end_excl->modify( '-1 day' )->getTimestamp();
		$range_label = sprintf(
			'%s – %s',
			wp_date( get_option( 'date_format' ), $start_incl->getTimestamp() ),
			wp_date( get_option( 'date_format' ), $last_day_ts )
		);
		$range_heading = sprintf(
			/* translators: 1: number of days, 2: localized date range */
			__( 'Últimos %1$d dias · %2$s', 'pb-affiliates' ),
			$days,
			$range_label
		);

		return array(
			'days'           => $days,
			'range_label'    => $range_label,
			'range_heading'  => $range_heading,
			'current'        => array(
				'clicks'     => (int) $cur_clicks,
				'orders'     => (int) $cur_orders,
				'commission' => (float) $cur_comm,
			),
			'previous'       => array(
				'clicks'     => (int) $prev_clicks,
				'orders'     => (int) $prev_orders,
				'commission' => (float) $prev_comm,
			),
			'delta_pct'      => array(
				'clicks'     => self::period_delta_percent( $cur_clicks, $prev_clicks ),
				'orders'     => self::period_delta_percent( $cur_orders, $prev_orders ),
				'commission' => self::period_delta_percent( $cur_comm, $prev_comm ),
			),
		);
	}

	/**
	 * Pedidos atribuídos com paginação (WooCommerce).
	 *
	 * @param int $user_id  Affiliate ID.
	 * @param int $per_page Itens por página.
	 * @param int $page     Página (1-based).
	 * @return array{orders:WC_Order[],total:int,max_pages:int}
	 */
	public static function get_affiliate_orders_paginated( $user_id, $per_page, $page ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'orders'    => array(),
				'total'     => 0,
				'max_pages' => 0,
			);
		}
		$per_page = max( 1, min( 50, (int) $per_page ) );
		$page     = max( 1, (int) $page );
		$result   = wc_get_orders(
			array(
				'limit'      => $per_page,
				'page'       => $page,
				'paginate'   => true,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_query' => array(
					self::wc_meta_affiliate_is_user( $user_id ),
				),
				'return'     => 'objects',
			)
		);
		if ( ! is_object( $result ) || ! isset( $result->orders ) ) {
			return array(
				'orders'    => array(),
				'total'     => 0,
				'max_pages' => 0,
			);
		}
		return array(
			'orders'    => $result->orders,
			'total'     => (int) $result->total,
			'max_pages' => (int) $result->max_num_pages,
		);
	}

	/**
	 * Início do dia local (MySQL) a partir de Y-m-d ou null.
	 *
	 * @param string $ymd Data.
	 * @return string|null Y-m-d H:i:s
	 */
	private static function date_ymd_start_mysql( $ymd ) {
		$ymd = sanitize_text_field( (string) $ymd );
		if ( '' === $ymd ) {
			return null;
		}
		$d = DateTimeImmutable::createFromFormat( 'Y-m-d', $ymd, wp_timezone() );
		if ( ! $d ) {
			return null;
		}
		return $d->setTime( 0, 0, 0 )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Meia-noite do dia seguinte (exclusivo) ao Y-m-d indicado, hora local.
	 *
	 * @param string $ymd Data.
	 * @return string|null Y-m-d H:i:s
	 */
	private static function date_ymd_end_exclusive_mysql( $ymd ) {
		$ymd = sanitize_text_field( (string) $ymd );
		if ( '' === $ymd ) {
			return null;
		}
		$d = DateTimeImmutable::createFromFormat( 'Y-m-d', $ymd, wp_timezone() );
		if ( ! $d ) {
			return null;
		}
		return $d->setTime( 0, 0, 0 )->modify( '+1 day' )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Normaliza intervalo Y-m-d para filtro de data de criação dos pedidos (ambos inclusivos em calendário local).
	 *
	 * @param string $from Y-m-d ou vazio.
	 * @param string $to   Y-m-d ou vazio.
	 * @return array{0:string,1:string} Strings vazias se intervalo inválido ou sem datas.
	 */
	private static function normalize_order_date_range_strings( $from, $to ) {
		$from = sanitize_text_field( (string) $from );
		$to   = sanitize_text_field( (string) $to );
		$tz   = wp_timezone();
		$d_from = $from !== '' ? DateTimeImmutable::createFromFormat( 'Y-m-d', $from, $tz ) : null;
		$d_to   = $to !== '' ? DateTimeImmutable::createFromFormat( 'Y-m-d', $to, $tz ) : null;
		if ( $d_from ) {
			$from = $d_from->format( 'Y-m-d' );
		} else {
			$from = '';
		}
		if ( $d_to ) {
			$to = $d_to->format( 'Y-m-d' );
		} else {
			$to = '';
		}
		if ( $from !== '' && $to === '' ) {
			$to = current_datetime()->setTime( 0, 0, 0 )->format( 'Y-m-d' );
		}
		if ( $to !== '' && $from === '' ) {
			$from = '2000-01-01';
		}
		if ( $from !== '' && $to !== '' && $from > $to ) {
			return array( '', '' );
		}
		return array( $from, $to );
	}

	/**
	 * Pedidos atribuídos com paginação, filtros e ordenação (admin).
	 *
	 * @param int   $user_id  Afiliado (user ID).
	 * @param int   $per_page Por página (1–100).
	 * @param int   $page     Página 1+.
	 * @param array $filters  status (slug WC sem prefixo, vazio = todos), via (sanitize_key, vazio), date_from, date_to (Y-m-d), orderby (date|total), order (ASC|DESC).
	 * @return array{orders:\WC_Order[],total:int,max_pages:int}
	 */
	public static function get_affiliate_orders_paginated_filtered( $user_id, $per_page, $page, $filters = array() ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'orders'    => array(),
				'total'     => 0,
				'max_pages' => 0,
			);
		}
		$defaults = array(
			'status'    => '',
			'via'       => '',
			'date_from' => '',
			'date_to'   => '',
			'orderby'   => 'date',
			'order'     => 'DESC',
		);
		$filters  = wp_parse_args( $filters, $defaults );
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$page     = max( 1, (int) $page );

		$meta_query = array(
			'relation' => 'AND',
			self::wc_meta_affiliate_is_user( $user_id ),
		);
		$via = sanitize_key( (string) $filters['via'] );
		if ( '' !== $via ) {
			$meta_query[] = array(
				'key'   => '_pb_attribution_source',
				'value' => $via,
			);
		}

		$orderby = strtolower( (string) $filters['orderby'] );
		if ( ! in_array( $orderby, array( 'date', 'total' ), true ) ) {
			$orderby = 'date';
		}
		$order = 'ASC' === strtoupper( (string) $filters['order'] ) ? 'ASC' : 'DESC';

		$query = array(
			'limit'      => $per_page,
			'page'       => $page,
			'paginate'   => true,
			'orderby'    => $orderby,
			'order'      => $order,
			'meta_query' => $meta_query,
			'return'     => 'objects',
		);

		$status = sanitize_key( (string) $filters['status'] );
		if ( '' !== $status ) {
			$query['status'] = array( $status );
		}

		list( $df, $dt ) = self::normalize_order_date_range_strings( $filters['date_from'], $filters['date_to'] );
		if ( '' !== $df && '' !== $dt ) {
			$query['date_created'] = $df . '...' . $dt;
		}

		$result = wc_get_orders( $query );
		if ( ! is_object( $result ) || ! isset( $result->orders ) ) {
			return array(
				'orders'    => array(),
				'total'     => 0,
				'max_pages' => 0,
			);
		}
		return array(
			'orders'    => $result->orders,
			'total'     => (int) $result->total,
			'max_pages' => (int) $result->max_num_pages,
		);
	}

	/**
	 * Contagem de cliques com filtros (origem / intervalo de datas).
	 *
	 * @param int   $user_id Afiliado.
	 * @param array $args    via (vazio = todos), date_from, date_to (Y-m-d).
	 * @return int
	 */
	public static function count_affiliate_clicks_filtered( $user_id, $args = array() ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'pagbank_affiliate_click_log';
		$wheres  = array( 'affiliate_id = %d' );
		$params  = array( (int) $user_id );
		$via     = isset( $args['via'] ) ? sanitize_key( (string) $args['via'] ) : '';
		if ( '' !== $via ) {
			$wheres[] = 'via = %s';
			$params[] = $via;
		}
		$from_sql = isset( $args['date_from'] ) ? self::date_ymd_start_mysql( $args['date_from'] ) : null;
		if ( $from_sql ) {
			$wheres[] = 'hit_at >= %s';
			$params[] = $from_sql;
		}
		$to_excl = isset( $args['date_to'] ) ? self::date_ymd_end_exclusive_mysql( $args['date_to'] ) : null;
		if ( $to_excl ) {
			$wheres[] = 'hit_at < %s';
			$params[] = $to_excl;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE clauses are only %s/%d placeholders; values bound below.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE ' . implode( ' AND ', $wheres ),
				...array_merge( array( $table ), $params )
			)
		);
	}

	/**
	 * Cliques paginados com filtros e ordenação (colunas da tabela).
	 *
	 * @param int   $user_id  Afiliado.
	 * @param int   $per_page 1–100.
	 * @param int   $page     1+.
	 * @param array $args     via, date_from, date_to (Y-m-d), orderby (id|hit_at|via|client_ip), order (ASC|DESC).
	 * @return array<int, array<string,mixed>>
	 */
	public static function get_affiliate_clicks_paged_filtered( $user_id, $per_page, $page, $args = array() ) {
		global $wpdb;
		$table    = $wpdb->prefix . 'pagbank_affiliate_click_log';
		$defaults = array(
			'via'       => '',
			'date_from' => '',
			'date_to'   => '',
			'orderby'   => 'id',
			'order'     => 'DESC',
		);
		$args     = wp_parse_args( $args, $defaults );
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$page     = max( 1, (int) $page );
		$offset   = ( $page - 1 ) * $per_page;

		$wheres = array( 'affiliate_id = %d' );
		$params = array( (int) $user_id );
		$via    = sanitize_key( (string) $args['via'] );
		if ( '' !== $via ) {
			$wheres[] = 'via = %s';
			$params[] = $via;
		}
		$from_sql = self::date_ymd_start_mysql( (string) $args['date_from'] );
		if ( $from_sql ) {
			$wheres[] = 'hit_at >= %s';
			$params[] = $from_sql;
		}
		$to_excl = self::date_ymd_end_exclusive_mysql( (string) $args['date_to'] );
		if ( $to_excl ) {
			$wheres[] = 'hit_at < %s';
			$params[] = $to_excl;
		}

		$ob = strtolower( (string) $args['orderby'] );
		switch ( $ob ) {
			case 'hit_at':
				$col = 'hit_at';
				break;
			case 'via':
				$col = 'via';
				break;
			case 'client_ip':
				$col = 'client_ip';
				break;
			case 'id':
			default:
				$col = 'id';
		}
		$dir = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- ORDER BY column/dir from allowlist; WHERE uses placeholders only.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, hit_at, via, client_ip, visited_url FROM %i WHERE ' . implode( ' AND ', $wheres ) . ' ORDER BY ' . $col . ' ' . $dir . ' LIMIT %d OFFSET %d',
				...array_merge( array( $table ), $params )
			),
			ARRAY_A
		);
	}

	/**
	 * Linha de comissão já registrada para um pedido (após pagamento confirmado, em geral).
	 *
	 * @param int $order_id     Order ID.
	 * @param int $affiliate_id Affiliate user ID.
	 * @return object|null commission_amount, status
	 */
	public static function get_commission_row_for_order( $order_id, $affiliate_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_commissions';
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT commission_amount, status FROM %i WHERE order_id = %d AND affiliate_id = %d LIMIT 1',
				$table,
				(int) $order_id,
				(int) $affiliate_id
			)
		);
	}

	/**
	 * Limites SQL [início inclusivo, fim exclusivo) alinhados a WC_Admin_Report (meia-noite local).
	 *
	 * @param int $start_ts Start timestamp (midnight).
	 * @param int $end_ts   End timestamp (midnight do último dia incluído).
	 * @return array{0:string,1:string} Datetime MySQL.
	 */
	public static function admin_click_hit_bounds( $start_ts, $end_ts ) {
		$r = self::wc_report_range_site_tz( $start_ts, $end_ts );
		return array(
			$r['start_mysql_inclusive'],
			$r['end_mysql_exclusive'],
		);
	}

	/**
	 * Slugs de status de pedido (sem prefixo wc-) alinhados a wc_get_order_statuses().
	 *
	 * @return array<int, string>
	 */
	public static function get_admin_report_order_status_slugs() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );
		}
		$out = array();
		foreach ( array_keys( wc_get_order_statuses() ) as $key ) {
			$slug = str_replace( 'wc-', '', (string) $key );
			$slug = sanitize_key( $slug );
			if ( '' === $slug ) {
				continue;
			}
			if ( in_array( $slug, array( 'trash', 'draft', 'auto-draft' ), true ) ) {
				continue;
			}
			$out[] = $slug;
		}
		$out = array_values( array_unique( $out ) );
		return ! empty( $out ) ? $out : array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' );
	}

	/**
	 * Opções para o select de filtro (slug => etiqueta), na ordem do WooCommerce.
	 *
	 * @return array<string, string>
	 */
	public static function get_admin_report_order_status_choices_for_ui() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}
		$out = array();
		foreach ( wc_get_order_statuses() as $key => $label ) {
			$slug = str_replace( 'wc-', '', (string) $key );
			$slug = sanitize_key( $slug );
			if ( '' === $slug || in_array( $slug, array( 'trash', 'draft', 'auto-draft' ), true ) ) {
				continue;
			}
			$out[ $slug ] = (string) $label;
		}
		return $out;
	}

	/**
	 * Lista de status para wc_get_orders (vazio = todos os status do relatório).
	 *
	 * @param string $filter_slug Slug vazio ou um status.
	 * @return array<int, string>
	 */
	public static function get_admin_report_order_statuses_for_query( $filter_slug ) {
		$all         = self::get_admin_report_order_status_slugs();
		$filter_slug = sanitize_key( (string) $filter_slug );
		if ( '' === $filter_slug ) {
			return $all;
		}
		if ( ! in_array( $filter_slug, $all, true ) ) {
			return $all;
		}
		return array( $filter_slug );
	}

	/**
	 * Pedidos com `_pb_affiliate_id` no intervalo agrupados por dia ou mês (data de criação no site).
	 *
	 * @param int    $start_ts     Timestamp início (meia-noite, inclusive).
	 * @param int    $end_ts       Meia-noite do último dia inclusivo.
	 * @param int    $affiliate_id 0 = qualquer afiliado.
	 * @param string       $groupby          `day` (Y-m-d) ou `month` (Y-m).
	 * @param array<string>|null $order_statuses Slugs para wc_get_orders; null = todos ({@see get_admin_report_order_status_slugs}).
	 * @return array<string, array{count:int, total:float}>
	 */
	public static function get_admin_affiliate_order_buckets( $start_ts, $end_ts, $affiliate_id = 0, $groupby = 'day', $order_statuses = null ) {
		$out = array();
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $out;
		}
		if ( ! is_array( $order_statuses ) || empty( $order_statuses ) ) {
			$order_statuses = self::get_admin_report_order_status_slugs();
		}
		$groupby = ( 'month' === $groupby ) ? 'month' : 'day';
		$range   = self::wc_report_range_site_tz( $start_ts, $end_ts );
		if ( $range['start_date'] > $range['end_date'] ) {
			return $out;
		}

		if ( (int) $affiliate_id > 0 ) {
			$meta_query = array(
				self::wc_meta_affiliate_is_user( $affiliate_id ),
			);
		} else {
			$meta_query = array(
				array(
					'key'     => '_pb_affiliate_id',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			);
		}

		$ids = wc_get_orders(
			array(
				'limit'        => -1,
				'return'       => 'ids',
				'paginate'     => false,
				'status'       => $order_statuses,
				'date_created' => $range['start_date'] . '...' . $range['end_date'],
				'meta_query'   => $meta_query,
			)
		);

		if ( ! is_array( $ids ) ) {
			return $out;
		}
		foreach ( $ids as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$created = $order->get_date_created();
			if ( ! $created ) {
				continue;
			}
			$key = 'month' === $groupby
				? $created->date_i18n( 'Y-m' )
				: $created->date_i18n( 'Y-m-d' );
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array(
					'count' => 0,
					'total' => 0.0,
				);
			}
			++$out[ $key ]['count'];
			$out[ $key ]['total'] += (float) $order->get_total();
		}
		return $out;
	}

	/**
	 * Valor de comissão para relatório admin: linha na tabela de comissões, meta do pedido, ou preview.
	 *
	 * @param WC_Order $order Order atribuída.
	 * @return float
	 */
	public static function get_admin_order_commission_amount_for_report( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return 0.0;
		}
		$aff_id = (int) $order->get_meta( '_pb_affiliate_id' );
		if ( $aff_id <= 0 ) {
			return 0.0;
		}
		$row = self::get_commission_row_for_order( $order->get_id(), $aff_id );
		if ( $row && isset( $row->commission_amount ) && '' !== $row->commission_amount && null !== $row->commission_amount ) {
			return (float) $row->commission_amount;
		}
		$meta_amt = $order->get_meta( '_pb_commission_amount' );
		if ( '' !== $meta_amt && null !== $meta_amt ) {
			return (float) wc_format_decimal( $meta_amt );
		}
		$preview = PB_Affiliates_Commission::calculate_commission_preview_for_order( $order, $aff_id );
		if ( is_array( $preview ) && isset( $preview['amount'] ) ) {
			return (float) $preview['amount'];
		}
		return 0.0;
	}

	/**
	 * Pedidos com afiliado no intervalo: lista ordenada e fatia paginada.
	 *
	 * @param int    $start_ts     Timestamp início (meia-noite, inclusive).
	 * @param int    $end_ts       Meia-noite do último dia inclusivo.
	 * @param int    $affiliate_id 0 = qualquer afiliado.
	 * @param int    $page         Página (1-based).
	 * @param int    $per_page     Linhas por página.
	 * @param string           $orderby          order_id|date|status|affiliate|commission.
	 * @param string           $order_dir        asc|desc.
	 * @param array<string>|null $order_statuses Slugs para wc_get_orders; null = todos.
	 * @return array{rows: array<int, array<string, mixed>>, total: int, total_pages: int, page: int, commission_sum: float, order_ids: array<int,int>}
	 */
	public static function get_admin_affiliate_orders_period_list( $start_ts, $end_ts, $affiliate_id, $page, $per_page, $orderby, $order_dir, $order_statuses = null ) {
		$empty = array(
			'rows'           => array(),
			'total'          => 0,
			'total_pages'    => 1,
			'page'           => 1,
			'commission_sum' => 0.0,
			'order_ids'      => array(),
		);
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $empty;
		}
		if ( ! is_array( $order_statuses ) || empty( $order_statuses ) ) {
			$order_statuses = self::get_admin_report_order_status_slugs();
		}
		$range = self::wc_report_range_site_tz( $start_ts, $end_ts );
		if ( $range['start_date'] > $range['end_date'] ) {
			return $empty;
		}
		if ( (int) $affiliate_id > 0 ) {
			$meta_query = array(
				self::wc_meta_affiliate_is_user( $affiliate_id ),
			);
		} else {
			$meta_query = array(
				array(
					'key'     => '_pb_affiliate_id',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			);
		}

		$ids = wc_get_orders(
			array(
				'limit'        => -1,
				'return'       => 'ids',
				'paginate'     => false,
				'status'       => $order_statuses,
				'date_created' => $range['start_date'] . '...' . $range['end_date'],
				'meta_query'   => $meta_query,
			)
		);

		if ( ! is_array( $ids ) ) {
			return $empty;
		}

		$order_ids_int = array_values( array_map( 'absint', $ids ) );

		$work           = array();
		$commission_sum = 0.0;
		foreach ( $ids as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$aid      = (int) $order->get_meta( '_pb_affiliate_id' );
			$user     = $aid > 0 ? get_userdata( $aid ) : false;
			$aff_name = $user
				? $user->display_name
				: (
					$aid > 0
						? sprintf(
							/* translators: %d: user ID */
							__( 'Utilizador #%d (removido)', 'pb-affiliates' ),
							$aid
						)
						: '—'
				);
			$created      = $order->get_date_created();
			$ts           = $created ? $created->getTimestamp() : 0;
			$status       = $order->get_status();
			$status_label = function_exists( 'wc_get_order_status_name' )
				? wc_get_order_status_name( $status )
				: $status;
			$comm         = self::get_admin_order_commission_amount_for_report( $order );
			$commission_sum += $comm;
			$order_number = method_exists( $order, 'get_order_number' ) ? (string) $order->get_order_number() : (string) $oid;
			$work[]       = array(
				'order_id'               => (int) $oid,
				'order_number'           => $order_number,
				'edit_url'               => $order->get_edit_order_url(),
				'date_ts'                => $ts,
				'date_display'           => $created
					? $created->date_i18n(
						( function_exists( 'wc_date_format' ) && function_exists( 'wc_time_format' ) )
							? wc_date_format() . ' ' . wc_time_format()
							: 'Y-m-d H:i'
					)
					: '—',
				'status'                 => $status,
				'status_label'           => $status_label,
				'affiliate_id'           => $aid,
				'affiliate_name'         => $aff_name,
				'affiliate_detail_url'   => $user ? PB_Affiliates_Admin_User_Detail::url( $aid ) : '',
				'commission'             => $comm,
				'_commission_sort'       => $comm,
			);
		}

		$orderby   = sanitize_key( (string) $orderby );
		$order_dir = ( 'asc' === $order_dir ) ? 'asc' : 'desc';
		if ( ! in_array( $orderby, array( 'order_id', 'date', 'status', 'affiliate', 'commission' ), true ) ) {
			$orderby = 'date';
		}

		usort(
			$work,
			static function ( $a, $b ) use ( $orderby, $order_dir ) {
				if ( 'order_id' === $orderby ) {
					$c = $a['order_id'] <=> $b['order_id'];
				} elseif ( 'date' === $orderby ) {
					$c = $a['date_ts'] <=> $b['date_ts'];
				} elseif ( 'status' === $orderby ) {
					$c = strcmp( (string) $a['status'], (string) $b['status'] );
				} elseif ( 'affiliate' === $orderby ) {
					$c = strcmp( (string) $a['affiliate_name'], (string) $b['affiliate_name'] );
				} else {
					$c = ( $a['_commission_sort'] <=> $b['_commission_sort'] );
				}
				if ( 0 !== $c ) {
					return 'desc' === $order_dir ? -$c : $c;
				}
				return $b['order_id'] <=> $a['order_id'];
			}
		);

		foreach ( $work as &$w ) {
			unset( $w['date_ts'], $w['_commission_sort'] );
		}
		unset( $w );

		$total       = count( $work );
		$per_page    = max( 1, (int) $per_page );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = max( 1, (int) $page );
		$page        = min( $page, $total_pages );
		$rows        = array_slice( $work, ( $page - 1 ) * $per_page, $per_page );

		return array(
			'rows'           => $rows,
			'total'          => $total,
			'total_pages'    => $total_pages,
			'page'           => $page,
			'commission_sum' => $commission_sum,
			'order_ids'      => $order_ids_int,
		);
	}

	/**
	 * Soma de comissões pendentes elegíveis para repasse manual (registo na tabela), limitada a pedidos da lista.
	 *
	 * @param array<int,int|string> $order_ids    IDs de pedidos (período / filtro já aplicados).
	 * @param int                   $affiliate_id 0 = qualquer; senão restringe a esse afiliado.
	 * @return float
	 */
	public static function get_admin_pending_manual_commission_total_for_order_ids( array $order_ids, $affiliate_id = 0 ) {
		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', $order_ids ) ) ) );
		if ( empty( $order_ids ) ) {
			return 0.0;
		}
		global $wpdb;
		$table        = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		$affiliate_id = (int) $affiliate_id;
		if ( $affiliate_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- IN list: only %d placeholders from absint order IDs.
			return (float) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COALESCE(SUM(commission_amount), 0) FROM %i WHERE order_id IN (' . $placeholders . ") AND status = 'pending' AND ( payment_method IS NULL OR payment_method = '' OR payment_method = 'manual' ) AND affiliate_id = %d",
					...array_merge( array( $table ), $order_ids, array( $affiliate_id ) )
				)
			);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- IN list: only %d placeholders from absint order IDs.
		return (float) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(commission_amount), 0) FROM %i WHERE order_id IN (' . $placeholders . ") AND status = 'pending' AND ( payment_method IS NULL OR payment_method = '' OR payment_method = 'manual' )",
				...array_merge( array( $table ), $order_ids )
			)
		);
	}

	/**
	 * Pedidos com `_pb_affiliate_id` no intervalo, agrupados por `_pb_referer_host` (mesmo bucket que o log de cliques).
	 *
	 * @param int $start_ts     Timestamp início (meia-noite, inclusive).
	 * @param int $end_ts       Meia-noite do último dia inclusivo.
	 * @param int              $affiliate_id     0 = qualquer afiliado.
	 * @param array<string>|null $order_statuses Slugs para wc_get_orders; null = todos.
	 * @return array<string, int> Chave = valor guardado em referer_host (string vazia se meta ausente).
	 */
	public static function get_admin_orders_grouped_by_referer_host( $start_ts, $end_ts, $affiliate_id = 0, $order_statuses = null ) {
		$out = array();
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $out;
		}
		if ( ! is_array( $order_statuses ) || empty( $order_statuses ) ) {
			$order_statuses = self::get_admin_report_order_status_slugs();
		}
		$range = self::wc_report_range_site_tz( $start_ts, $end_ts );
		if ( $range['start_date'] > $range['end_date'] ) {
			return $out;
		}

		if ( (int) $affiliate_id > 0 ) {
			$meta_query = array(
				self::wc_meta_affiliate_is_user( $affiliate_id ),
			);
		} else {
			$meta_query = array(
				array(
					'key'     => '_pb_affiliate_id',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			);
		}

		$ids = wc_get_orders(
			array(
				'limit'        => -1,
				'return'       => 'ids',
				'paginate'     => false,
				'status'       => $order_statuses,
				'date_created' => $range['start_date'] . '...' . $range['end_date'],
				'meta_query'   => $meta_query,
			)
		);

		if ( ! is_array( $ids ) ) {
			return $out;
		}

		foreach ( $ids as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$h = substr( (string) $order->get_meta( '_pb_referer_host' ), 0, 190 );
			if ( ! isset( $out[ $h ] ) ) {
				$out[ $h ] = 0;
			}
			++$out[ $h ];
		}

		return $out;
	}

	/**
	 * Contagens agregadas por dia (bucket => count).
	 *
	 * @param string $start_inclusive Start MySQL datetime.
	 * @param string $end_exclusive   End MySQL datetime (exclusive).
	 * @param int    $affiliate_id    0 = todos os afiliados ativos registrados no log.
	 * @return array<string,int>
	 */
	public static function get_admin_click_counts_by_day( $start_inclusive, $end_exclusive, $affiliate_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_click_log';
		if ( (int) $affiliate_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT DATE(hit_at) as bucket, COUNT(*) as c FROM %i
					WHERE hit_at >= %s AND hit_at < %s AND affiliate_id = %d
					GROUP BY DATE(hit_at)',
					$table,
					$start_inclusive,
					$end_exclusive,
					(int) $affiliate_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT DATE(hit_at) as bucket, COUNT(*) as c FROM %i
					WHERE hit_at >= %s AND hit_at < %s
					GROUP BY DATE(hit_at)',
					$table,
					$start_inclusive,
					$end_exclusive
				)
			);
		}
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( isset( $row->bucket, $row->c ) ) {
				$out[ (string) $row->bucket ] = (int) $row->c;
			}
		}
		return $out;
	}

	/**
	 * Contagens por mês (chave Y-m => total).
	 *
	 * @param string $start_inclusive Start MySQL datetime.
	 * @param string $end_exclusive   End MySQL datetime (exclusive).
	 * @param int    $affiliate_id    0 = todos.
	 * @return array<string,int>
	 */
	public static function get_admin_click_counts_by_month( $start_inclusive, $end_exclusive, $affiliate_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_click_log';
		if ( (int) $affiliate_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT YEAR(hit_at) as y, MONTH(hit_at) as m, COUNT(*) as c FROM %i
					WHERE hit_at >= %s AND hit_at < %s AND affiliate_id = %d
					GROUP BY YEAR(hit_at), MONTH(hit_at)',
					$table,
					$start_inclusive,
					$end_exclusive,
					(int) $affiliate_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT YEAR(hit_at) as y, MONTH(hit_at) as m, COUNT(*) as c FROM %i
					WHERE hit_at >= %s AND hit_at < %s
					GROUP BY YEAR(hit_at), MONTH(hit_at)',
					$table,
					$start_inclusive,
					$end_exclusive
				)
			);
		}
		$out = array();
		foreach ( (array) $rows as $row ) {
			$key = sprintf( '%04d-%02d', (int) $row->y, (int) $row->m );
			$out[ $key ] = (int) $row->c;
		}
		return $out;
	}

	/**
	 * Totais por origem (via).
	 *
	 * @param string $start_inclusive Start MySQL.
	 * @param string $end_exclusive   End MySQL (exclusive).
	 * @param int    $affiliate_id    0 = todos.
	 * @param string $orderby         count_desc|via_asc.
	 * @return array<int,object{via:string,cnt:int}>
	 */
	public static function get_admin_clicks_grouped_by_via( $start_inclusive, $end_exclusive, $affiliate_id = 0, $orderby = 'count_desc' ) {
		global $wpdb;
		$table        = $wpdb->prefix . 'pagbank_affiliate_click_log';
		$affiliate_id = (int) $affiliate_id;
		$via_asc      = 'via_asc' === $orderby;

		if ( $affiliate_id > 0 ) {
			if ( $via_asc ) {
				return $wpdb->get_results(
					$wpdb->prepare(
						'SELECT via AS via, COUNT(*) AS cnt FROM %i WHERE hit_at >= %s AND hit_at < %s AND affiliate_id = %d GROUP BY via ORDER BY via ASC',
						$table,
						$start_inclusive,
						$end_exclusive,
						$affiliate_id
					)
				);
			}
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT via AS via, COUNT(*) AS cnt FROM %i WHERE hit_at >= %s AND hit_at < %s AND affiliate_id = %d GROUP BY via ORDER BY cnt DESC',
					$table,
					$start_inclusive,
					$end_exclusive,
					$affiliate_id
				)
			);
		}
		if ( $via_asc ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT via AS via, COUNT(*) AS cnt FROM %i WHERE hit_at >= %s AND hit_at < %s GROUP BY via ORDER BY via ASC',
					$table,
					$start_inclusive,
					$end_exclusive
				)
			);
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT via AS via, COUNT(*) AS cnt FROM %i WHERE hit_at >= %s AND hit_at < %s GROUP BY via ORDER BY cnt DESC',
				$table,
				$start_inclusive,
				$end_exclusive
			)
		);
	}

	/**
	 * Cliques agrupados por `referer_host` (chave = valor SQL, incl. string vazia).
	 *
	 * @param string $start_inclusive Start MySQL.
	 * @param string $end_exclusive   End MySQL (exclusive).
	 * @param int    $affiliate_id    0 = todos.
	 * @return array<string, int>
	 */
	public static function get_admin_click_counts_by_referer_host( $start_inclusive, $end_exclusive, $affiliate_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_click_log';
		if ( (int) $affiliate_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT referer_host AS h, COUNT(*) AS cnt FROM %i
					WHERE hit_at >= %s AND hit_at < %s AND affiliate_id = %d
					GROUP BY referer_host',
					$table,
					$start_inclusive,
					$end_exclusive,
					(int) $affiliate_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT referer_host AS h, COUNT(*) AS cnt FROM %i
					WHERE hit_at >= %s AND hit_at < %s
					GROUP BY referer_host',
					$table,
					$start_inclusive,
					$end_exclusive
				)
			);
		}
		$out = array();
		foreach ( (array) $rows as $r ) {
			if ( $r && isset( $r->h, $r->cnt ) ) {
				$out[ (string) $r->h ] = (int) $r->cnt;
			}
		}
		return $out;
	}

	/**
	 * Cliques no período agrupados por `affiliate_id` (ID do utilizador WP).
	 *
	 * @param string $start_inclusive Start MySQL.
	 * @param string $end_exclusive   MySQL (exclusive).
	 * @param int    $affiliate_id    0 = todos (apenas affiliate_id > 0 no log).
	 * @return array<int, int> Mapa affiliate_id => total de cliques.
	 */
	public static function get_admin_click_counts_by_affiliate_id( $start_inclusive, $end_exclusive, $affiliate_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_click_log';
		if ( (int) $affiliate_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT affiliate_id AS aid, COUNT(*) AS cnt FROM %i
					WHERE hit_at >= %s AND hit_at < %s AND affiliate_id = %d
					GROUP BY affiliate_id',
					$table,
					$start_inclusive,
					$end_exclusive,
					(int) $affiliate_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT affiliate_id AS aid, COUNT(*) AS cnt FROM %i
					WHERE hit_at >= %s AND hit_at < %s AND affiliate_id > 0
					GROUP BY affiliate_id',
					$table,
					$start_inclusive,
					$end_exclusive
				)
			);
		}
		$out = array();
		foreach ( (array) $rows as $r ) {
			if ( $r && isset( $r->aid, $r->cnt ) ) {
				$aid = (int) $r->aid;
				if ( $aid > 0 ) {
					$out[ $aid ] = (int) $r->cnt;
				}
			}
		}
		return $out;
	}

	/**
	 * Pedidos com `_pb_affiliate_id` no intervalo, contados por afiliado.
	 *
	 * @param int $start_ts     Timestamp início.
	 * @param int $end_ts       Idem.
	 * @param int                $affiliate_id     0 = todos com meta > 0.
	 * @param array<string>|null $order_statuses   Slugs para wc_get_orders; null = todos.
	 * @return array<int, int> Mapa user_id => número de pedidos.
	 */
	public static function get_admin_orders_grouped_by_affiliate_id( $start_ts, $end_ts, $affiliate_id = 0, $order_statuses = null ) {
		$out = array();
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return $out;
		}
		if ( ! is_array( $order_statuses ) || empty( $order_statuses ) ) {
			$order_statuses = self::get_admin_report_order_status_slugs();
		}
		$range = self::wc_report_range_site_tz( $start_ts, $end_ts );
		if ( $range['start_date'] > $range['end_date'] ) {
			return $out;
		}

		if ( (int) $affiliate_id > 0 ) {
			$meta_query = array(
				self::wc_meta_affiliate_is_user( $affiliate_id ),
			);
		} else {
			$meta_query = array(
				array(
					'key'     => '_pb_affiliate_id',
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			);
		}

		$ids = wc_get_orders(
			array(
				'limit'        => -1,
				'return'       => 'ids',
				'paginate'     => false,
				'status'       => $order_statuses,
				'date_created' => $range['start_date'] . '...' . $range['end_date'],
				'meta_query'   => $meta_query,
			)
		);

		if ( ! is_array( $ids ) ) {
			return $out;
		}

		foreach ( $ids as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$aid = (int) $order->get_meta( '_pb_affiliate_id' );
			if ( $aid <= 0 ) {
				continue;
			}
			if ( ! isset( $out[ $aid ] ) ) {
				$out[ $aid ] = 0;
			}
			++$out[ $aid ];
		}

		return $out;
	}

	/**
	 * Afiliados com cliques e/ou pedidos no intervalo (merge por ID).
	 *
	 * @param int    $start_ts            Timestamps WC (pedidos).
	 * @param int    $end_ts              Idem.
	 * @param string $start_sql_inclusive hit_at >=
	 * @param string $end_sql_exclusive   hit_at <
	 * @param int                $affiliate_id     0 = todos.
	 * @param array<string>|null $order_statuses   Slugs para wc_get_orders; null = todos.
	 * @return array<int, array{affiliate_id:int, clicks:int, orders:int}>
	 */
	public static function get_admin_affiliate_performance_merged_rows( $start_ts, $end_ts, $start_sql_inclusive, $end_sql_exclusive, $affiliate_id = 0, $order_statuses = null ) {
		$click_map = self::get_admin_click_counts_by_affiliate_id( $start_sql_inclusive, $end_sql_exclusive, $affiliate_id );
		$order_map = self::get_admin_orders_grouped_by_affiliate_id( $start_ts, $end_ts, $affiliate_id, $order_statuses );
		$ids       = array_unique(
			array_merge(
				array_map( 'intval', array_keys( $click_map ) ),
				array_map( 'intval', array_keys( $order_map ) )
			)
		);
		$list = array();
		foreach ( $ids as $aid ) {
			if ( $aid <= 0 ) {
				continue;
			}
			$cl = isset( $click_map[ $aid ] ) ? (int) $click_map[ $aid ] : 0;
			$oc = isset( $order_map[ $aid ] ) ? (int) $order_map[ $aid ] : 0;
			if ( $cl <= 0 && $oc <= 0 ) {
				continue;
			}
			$list[] = array(
				'affiliate_id' => $aid,
				'clicks'       => $cl,
				'orders'       => $oc,
			);
		}
		return $list;
	}

	/**
	 * Lista completa de domínios (referer_host) com cliques e/ou pedidos no intervalo.
	 *
	 * @param int    $start_ts                 Timestamps WC (pedidos).
	 * @param int    $end_ts                   Idem.
	 * @param string $start_sql_inclusive      hit_at >=
	 * @param string $end_sql_exclusive        hit_at <
	 * @param int                $affiliate_id     0 = todos.
	 * @param bool               $exclude_empty_referer Omite host vazio.
	 * @param array<string>|null $order_statuses   Slugs para wc_get_orders; null = todos.
	 * @return array<int, array{h:string, clicks:int, orders:int}>
	 */
	public static function get_admin_referer_domain_merged_rows( $start_ts, $end_ts, $start_sql_inclusive, $end_sql_exclusive, $affiliate_id = 0, $exclude_empty_referer = false, $order_statuses = null ) {
		$click_map = self::get_admin_click_counts_by_referer_host( $start_sql_inclusive, $end_sql_exclusive, $affiliate_id );
		$order_map = self::get_admin_orders_grouped_by_referer_host( $start_ts, $end_ts, $affiliate_id, $order_statuses );

		$keys = array_unique( array_merge( array_keys( $click_map ), array_keys( $order_map ) ) );
		$list = array();
		foreach ( $keys as $h ) {
			$h = (string) $h;
			if ( $exclude_empty_referer && '' === $h ) {
				continue;
			}
			$cl = isset( $click_map[ $h ] ) ? (int) $click_map[ $h ] : 0;
			$oc = isset( $order_map[ $h ] ) ? (int) $order_map[ $h ] : 0;
			if ( $cl <= 0 && $oc <= 0 ) {
				continue;
			}
			$list[] = array(
				'h'      => $h,
				'clicks' => $cl,
				'orders' => $oc,
			);
		}

		return $list;
	}
}
