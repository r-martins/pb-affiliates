<?php
/**
 * Saques manuais: solicitação pelo afiliado, liquidação no admin (comissões congeladas no pedido).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Withdrawal
 */
class PB_Affiliates_Withdrawal {

	/**
	 * Compara valores monetários com tolerância.
	 *
	 * @param float $a A.
	 * @param float $b B.
	 * @return bool
	 */
	public static function amounts_match( $a, $b ) {
		return abs( (float) $a - (float) $b ) < 0.009;
	}

	/**
	 * IDs de comissão já reservadas em pedidos de saque ainda pendentes (snapshot no JSON).
	 *
	 * @param int $user_id Affiliate user ID.
	 * @return array<int, int>
	 */
	public static function get_pending_withdrawal_locked_commission_ids( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return array();
		}
		$table = $wpdb->prefix . 'pagbank_affiliate_withdrawals';
		$jsons = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT commission_ids_json FROM {$table} WHERE affiliate_id = %d AND status = %s",
				$user_id,
				'pending'
			)
		);
		$ids = array();
		foreach ( (array) $jsons as $raw ) {
			if ( empty( $raw ) || ! is_string( $raw ) ) {
				continue;
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			foreach ( $decoded as $cid ) {
				$cid = absint( $cid );
				if ( $cid > 0 ) {
					$ids[ $cid ] = $cid;
				}
			}
		}
		return array_values( $ids );
	}

	/**
	 * Cláusula SQL e argumentos para excluir IDs (seguro só com inteiros).
	 *
	 * @param array<int, int> $ids IDs.
	 * @return array{0:string, 1:array<int,int>} fragmento após WHERE base, args vazios se sem exclusão.
	 */
	private static function sql_exclude_commission_ids( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( empty( $ids ) ) {
			return array( '', array() );
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return array( " AND id NOT IN ({$placeholders}) ", $ids );
	}

	/**
	 * Saldo disponível para saque (comissões manuais pendentes, fora retenção e fora saques pendentes).
	 *
	 * @param int $user_id Affiliate user ID.
	 * @return float
	 */
	public static function get_available_balance( $user_id ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$now_gmt = gmdate( 'Y-m-d H:i:s' );
		$locked  = self::get_pending_withdrawal_locked_commission_ids( $user_id );
		list( $excl_sql, $excl_args ) = self::sql_exclude_commission_ids( $locked );

		$sql = "SELECT COALESCE(SUM(commission_amount), 0) FROM {$table}
				WHERE affiliate_id = %d AND status = %s
				AND ( payment_method IS NULL OR payment_method = '' OR payment_method = %s )
				AND ( available_at IS NULL OR available_at <= %s )
				{$excl_sql}";

		$args = array_merge( array( $user_id, 'pending', 'manual', $now_gmt ), $excl_args );
		$sum  = $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
		return (float) $sum;
	}

	/**
	 * Afiliado tem pedido de saque aguardando processamento.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function has_pending_request( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_withdrawals';
		$n     = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE affiliate_id = %d AND status = %s",
				$user_id,
				'pending'
			)
		);
		return $n > 0;
	}

	/**
	 * Comissões manuais a liquidar (FIFO), já liberadas por available_at.
	 *
	 * @param int $affiliate_id Affiliate.
	 * @return array<int, array{id:int, commission_amount:float}>
	 */
	public static function get_allocatable_commission_rows( $affiliate_id ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$now_gmt = gmdate( 'Y-m-d H:i:s' );
		$locked  = self::get_pending_withdrawal_locked_commission_ids( $affiliate_id );
		list( $excl_sql, $excl_args ) = self::sql_exclude_commission_ids( $locked );

		$sql  = "SELECT id, commission_amount FROM {$table}
				WHERE affiliate_id = %d AND status = %s
				AND ( payment_method IS NULL OR payment_method = '' OR payment_method = %s )
				AND ( available_at IS NULL OR available_at <= %s )
				{$excl_sql}
				ORDER BY id ASC";
		$args = array_merge( array( $affiliate_id, 'pending', 'manual', $now_gmt ), $excl_args );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Solicitar saque: valor = saldo total disponível; congela IDs de comissão no pedido.
	 *
	 * @param int   $user_id User ID.
	 * @param float $amount  Deve coincidir com o saldo (total).
	 * @return int|\WP_Error Withdrawal ID.
	 */
	public static function request( $user_id, $amount ) {
		if ( 'manual' !== ( PB_Affiliates_Settings::get()['payment_mode'] ?? 'manual' ) ) {
			return new \WP_Error( 'mode', __( 'Saques só estão disponíveis no modo de pagamento manual.', 'pb-affiliates' ) );
		}
		if ( self::has_pending_request( $user_id ) ) {
			return new \WP_Error( 'pending', __( 'Já existe um pedido de saque pendente. Aguarde o processamento.', 'pb-affiliates' ) );
		}

		$settings = PB_Affiliates_Settings::get();
		$min      = (float) ( $settings['manual_min_withdrawal'] ?? 0 );
		$avail    = self::get_available_balance( $user_id );
		$amount   = (float) wc_format_decimal( $amount );

		if ( $avail <= 0 ) {
			return new \WP_Error( 'empty', __( 'Não há saldo disponível para saque.', 'pb-affiliates' ) );
		}
		if ( $amount < $min ) {
			return new \WP_Error( 'min', __( 'Valor abaixo do mínimo para saque.', 'pb-affiliates' ) );
		}
		if ( ! self::amounts_match( $amount, $avail ) ) {
			return new \WP_Error(
				'full_balance',
				__( 'No momento só é possível solicitar saque pelo saldo total disponível.', 'pb-affiliates' )
			);
		}

		$rows = self::get_allocatable_commission_rows( $user_id );
		$ids  = array();
		$sum  = 0.0;
		foreach ( $rows as $r ) {
			$ids[] = (int) $r['id'];
			$sum  += (float) $r['commission_amount'];
		}
		if ( ! self::amounts_match( $sum, $amount ) ) {
			return new \WP_Error( 'sync', __( 'Saldo alterado. Atualize a página e tente novamente.', 'pb-affiliates' ) );
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'pagbank_affiliate_withdrawals',
			array(
				'affiliate_id'         => $user_id,
				'amount'               => $amount,
				'status'               => 'pending',
				'currency'             => get_woocommerce_currency(),
				'created_at'           => current_time( 'mysql' ),
				'commission_ids_json' => wp_json_encode( $ids ),
			),
			array( '%d', '%f', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Marcar pedido de saque como pago e liquidar as comissões congeladas no pedido.
	 *
	 * @param int    $withdrawal_id Row id.
	 * @param string $proof_notes    Comprovante / referência (admin).
	 * @return bool|\WP_Error
	 */
	public static function mark_paid( $withdrawal_id, $proof_notes = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_withdrawals';
		$id    = (int) $withdrawal_id;
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'Registro não encontrado.', 'pb-affiliates' ) );
		}
		if ( 'pending' !== $row['status'] ) {
			return new \WP_Error( 'invalid', __( 'Apenas pedidos pendentes podem ser marcados como pagos.', 'pb-affiliates' ) );
		}

		$aff_id   = (int) $row['affiliate_id'];
		$target   = (float) $row['amount'];
		$proof_notes = sanitize_textarea_field( (string) $proof_notes );
		$proof_notes = mb_substr( $proof_notes, 0, 4000 );
		if ( '' === trim( $proof_notes ) ) {
			return new \WP_Error( 'proof_required', __( 'Informe o comprovante ou referência do pagamento antes de marcar como pago.', 'pb-affiliates' ) );
		}

		$ids = array();
		if ( ! empty( $row['commission_ids_json'] ) ) {
			$decoded = json_decode( (string) $row['commission_ids_json'], true );
			if ( is_array( $decoded ) ) {
				$ids = array_map( 'absint', $decoded );
				$ids = array_values( array_filter( $ids ) );
			}
		}

		if ( empty( $ids ) ) {
			return new \WP_Error(
				'legacy',
				__( 'Este pedido de saque não associa comissões. Cancele-o e peça ao afiliado para solicitar novamente, ou liquide em Afiliados → Pedidos.', 'pb-affiliates' )
			);
		}

		$check_sum = 0.0;
		foreach ( $ids as $cid ) {
			$amt = $wpdb->get_row( $wpdb->prepare( "SELECT affiliate_id, commission_amount, status, payment_method FROM {$wpdb->prefix}pagbank_affiliate_commissions WHERE id = %d", $cid ), ARRAY_A );
			if ( ! $amt || (int) $amt['affiliate_id'] !== $aff_id || 'pending' !== $amt['status'] ) {
				return new \WP_Error( 'commission', __( 'Uma das comissões deste saque já não está pendente. Verifique Afiliados → Pedidos.', 'pb-affiliates' ) );
			}
			$pm = isset( $amt['payment_method'] ) ? (string) $amt['payment_method'] : '';
			if ( 'split' === $pm ) {
				return new \WP_Error( 'split', __( 'Comissão em modo split não pode ser liquidada por este fluxo.', 'pb-affiliates' ) );
			}
			$check_sum += (float) $amt['commission_amount'];
		}
		if ( ! self::amounts_match( $check_sum, $target ) ) {
			return new \WP_Error( 'amount', __( 'A soma das comissões não coincide com o valor do saque.', 'pb-affiliates' ) );
		}

		$wpdb->query( 'START TRANSACTION' );

		foreach ( $ids as $cid ) {
			$res = PB_Affiliates_Commission::mark_row_paid( $cid, array( 'send_email' => false ) );
			if ( is_wp_error( $res ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $res;
			}
		}
		$paid_sum = $check_sum;

		$ok = $wpdb->update(
			$table,
			array(
				'status'       => 'paid',
				'processed_at' => current_time( 'mysql' ),
				'notes'        => $proof_notes !== '' ? $proof_notes : null,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'db', __( 'Não foi possível atualizar o pedido de saque.', 'pb-affiliates' ) );
		}

		$wpdb->query( 'COMMIT' );

		if ( $paid_sum > 0 && apply_filters( 'pb_affiliates_send_commission_paid_email', true, $aff_id, $paid_sum, $id, $proof_notes ) ) {
			PB_Affiliates_Emails::send_commission_paid( $aff_id, $paid_sum, $proof_notes );
		}

		return true;
	}

	/**
	 * Histórico de saques já pagos (ex.: área do afiliado).
	 *
	 * @param int $affiliate_id User ID.
	 * @param int $limit      Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_paid_withdrawals_for_affiliate( $affiliate_id, $limit = 50 ) {
		global $wpdb;
		$affiliate_id = (int) $affiliate_id;
		$limit        = max( 1, min( 100, (int) $limit ) );
		if ( $affiliate_id <= 0 ) {
			return array();
		}
		$table = $wpdb->prefix . 'pagbank_affiliate_withdrawals';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE affiliate_id = %d AND status = %s ORDER BY processed_at DESC, id DESC LIMIT %d",
				$affiliate_id,
				'paid',
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Quantidade de comissões no snapshot JSON do saque.
	 *
	 * @param array<string, mixed>|null $row Linha da tabela de saques ou pelo menos commission_ids_json.
	 * @return int
	 */
	public static function count_commissions_in_withdrawal_row( $row ) {
		if ( ! is_array( $row ) || empty( $row['commission_ids_json'] ) ) {
			return 0;
		}
		$decoded = json_decode( (string) $row['commission_ids_json'], true );
		if ( ! is_array( $decoded ) ) {
			return 0;
		}
		return count( array_filter( array_map( 'absint', $decoded ) ) );
	}

	/**
	 * Texto formatado dos dados bancários do afiliado (modo manual).
	 *
	 * @param int $user_id User ID.
	 * @return array<int, string> Linhas para exibição.
	 */
	public static function get_bank_detail_lines( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return array();
		}
		$code = (string) get_user_meta( $user_id, 'pb_affiliate_bank_code', true );
		$ag   = (string) get_user_meta( $user_id, 'pb_affiliate_bank_agency', true );
		$acc  = (string) get_user_meta( $user_id, 'pb_affiliate_bank_account', true );
		$doc  = (string) get_user_meta( $user_id, 'pb_affiliate_bank_document', true );
		$lines = array();
		if ( $code !== '' ) {
			$lines[] = sprintf(
				/* translators: 1: bank code */
				__( 'Banco (código): %s', 'pb-affiliates' ),
				$code
			);
		}
		if ( $ag !== '' ) {
			$lines[] = sprintf(
				/* translators: %s: agency */
				__( 'Agência: %s', 'pb-affiliates' ),
				$ag
			);
		}
		if ( $acc !== '' ) {
			$lines[] = sprintf(
				/* translators: %s: account */
				__( 'Conta: %s', 'pb-affiliates' ),
				$acc
			);
		}
		if ( $doc !== '' ) {
			$lines[] = sprintf(
				/* translators: %s: tax id */
				__( 'CPF/CNPJ: %s', 'pb-affiliates' ),
				$doc
			);
		}
		return $lines;
	}
}
