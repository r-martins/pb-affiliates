<?php
/**
 * Admin: repasse manual (saque agregado) + pedidos de saque. Split PagBank não passa por esta fila.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Admin_Payments
 */
class PB_Affiliates_Admin_Payments {

	/**
	 * Marcar saque como pago (com comprovante).
	 */
	public static function handle_post() {
		if ( ! isset( $_GET['page'] ) || 'pb-affiliates-payments' !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! isset( $_POST['pb_aff_payment_action'], $_POST['_wpnonce'], $_POST['pb_aff_withdrawal_id'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'pb_aff_payment_action' ) ) {
			return;
		}
		if ( 'mark_paid' !== sanitize_key( wp_unslash( $_POST['pb_aff_payment_action'] ) ) ) {
			return;
		}
		$id    = absint( $_POST['pb_aff_withdrawal_id'] );
		$proof = isset( $_POST['pb_aff_payment_proof'] ) ? wp_unslash( $_POST['pb_aff_payment_proof'] ) : '';
		$res   = PB_Affiliates_Withdrawal::mark_paid( $id, $proof );
		$tab   = isset( $_POST['pb_aff_tab'] ) ? sanitize_key( wp_unslash( $_POST['pb_aff_tab'] ) ) : 'pending';
		if ( ! in_array( $tab, array( 'pending', 'paid' ), true ) ) {
			$tab = 'pending';
		}
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=pb-affiliates-payments&tab=' . $tab . '&pb_aff_err=' . rawurlencode( $res->get_error_code() ) ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=pb-affiliates-payments&tab=paid&pb_aff_notice=paid' ) );
		}
		exit;
	}

	/**
	 * Mensagem para código de erro da query string.
	 *
	 * @param string $code Code.
	 * @return string
	 */
	private static function error_message_for_code( $code ) {
		switch ( $code ) {
			case 'not_found':
				return __( 'Registro não encontrado.', 'pb-affiliates' );
			case 'invalid':
				return __( 'Operação inválida para este registro.', 'pb-affiliates' );
			case 'proof_required':
				return __( 'Preencha o comprovante ou referência do pagamento.', 'pb-affiliates' );
			case 'legacy':
				return __( 'Este pedido de saque é antigo e não associa comissões. Peça ao afiliado para solicitar um novo saque ou liquide em Afiliados → Pedidos.', 'pb-affiliates' );
			case 'split':
				return __( 'Comissão em modo split não pode ser liquidada por este fluxo.', 'pb-affiliates' );
			case 'commission':
				return __( 'Uma das comissões deste saque já não está pendente. Verifique Afiliados → Pedidos.', 'pb-affiliates' );
			case 'amount':
				return __( 'A soma das comissões não coincide com o valor do saque.', 'pb-affiliates' );
			default:
				return __( 'Não foi possível concluir a operação.', 'pb-affiliates' );
		}
	}

	/**
	 * Comprovante em linha (aba Pagos): pré-visualização curta com expansão.
	 *
	 * @param string $notes_raw Texto do comprovante.
	 */
	private static function render_paid_proof_cell( $notes_raw ) {
		$notes = (string) $notes_raw;
		if ( '' === $notes ) {
			echo '—';
			return;
		}
		$cut   = 120;
		$len   = function_exists( 'mb_strlen' ) ? mb_strlen( $notes ) : strlen( $notes );
		$esc   = function ( $s ) {
			return esc_html( (string) $s );
		};
		if ( $len <= $cut ) {
			echo '<div class="pb-aff-proof-cell" style="max-width:24rem;white-space:pre-wrap;">' . $esc( $notes ) . '</div>';
			return;
		}
		$preview = function_exists( 'mb_substr' ) ? mb_substr( $notes, 0, $cut ) : substr( $notes, 0, $cut );
		echo '<details class="pb-aff-proof-expand" style="max-width:24rem;">';
		echo '<summary style="cursor:pointer;list-style-position:outside;">' . $esc( $preview ) . '…</summary>';
		echo '<div style="margin-top:0.5em;white-space:pre-wrap;border-left:3px solid #c3c4c7;padding-left:8px;">' . $esc( $notes ) . '</div>';
		echo '</details>';
	}

	/**
	 * Render da página.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		global $wpdb;
		$table   = $wpdb->prefix . 'pagbank_affiliate_withdrawals';
		$mode    = PB_Affiliates_Settings::get()['payment_mode'] ?? 'manual';
		$now_gmt = gmdate( 'Y-m-d H:i:s' );

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'pending';
		if ( ! in_array( $tab, array( 'pending', 'paid' ), true ) ) {
			$tab = 'pending';
		}

		$status = 'pending' === $tab ? 'pending' : 'paid';
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY id DESC LIMIT 200",
				$status
			),
			ARRAY_A
		);

		if ( ! empty( $_GET['pb_aff_notice'] ) && 'paid' === sanitize_key( wp_unslash( $_GET['pb_aff_notice'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Pagamento registrado como concluído.', 'pb-affiliates' ) . '</p></div>';
		}
		if ( ! empty( $_GET['pb_aff_err'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['pb_aff_err'] ) );
			$msg  = self::error_message_for_code( $code );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}

		$aggregated = 'pending' === $tab
			? PB_Affiliates_Reports::get_admin_manual_pending_by_affiliate_display( $now_gmt )
			: array();
		$orders_url = admin_url( 'admin.php?page=pb-affiliates' );
		?>
		<div class="wrap woocommerce">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Pagamentos', 'pb-affiliates' ); ?></h1>
			<hr class="wp-header-end" />

			<?php if ( 'split' === $mode ) : ?>
				<div class="notice notice-info">
					<p>
						<?php esc_html_e( 'A loja está em modo split (PagBank): o repasse das comissões ocorre automaticamente conforme custódia e regras do conector. Esta tela só trata de pagamentos manuais e pedidos de saque; comissões split não entram na fila “pendente de transferência” abaixo.', 'pb-affiliates' ); ?>
					</p>
				</div>
			<?php else : ?>
				<div class="notice notice-info" style="margin-top:1em;">
					<p>
						<?php esc_html_e( 'Modo manual: o afiliado solicita saque na área “Afiliado”; você transfere o valor e marca o pedido como pago informando um comprovante.', 'pb-affiliates' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pb-affiliates-payments&tab=pending' ) ); ?>" class="nav-tab <?php echo 'pending' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Pendentes', 'pb-affiliates' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pb-affiliates-payments&tab=paid' ) ); ?>" class="nav-tab <?php echo 'paid' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Pagos', 'pb-affiliates' ); ?></a>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Pedidos de saque consolidam várias comissões manuais em uma única linha para repasse. Saldo ainda não solicitado aparece agrupado por afiliado.', 'pb-affiliates' ); ?>
				<?php
				echo ' ';
				echo wp_kses_post(
					sprintf(
						/* translators: %s: URL Afiliados > Pedidos */
						__( 'O detalhe por venda continua em %s.', 'pb-affiliates' ),
						'<a href="' . esc_url( $orders_url ) . '">' . esc_html__( 'Afiliados → Pedidos', 'pb-affiliates' ) . '</a>'
					)
				);
				?>
			</p>

			<?php if ( 'pending' === $tab && 'manual' === $mode ) : ?>
				<h2 style="margin-top:1.5em"><?php esc_html_e( 'Saldo manual ainda não pedido em saque', 'pb-affiliates' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Valores já liberados (após retenção) que o afiliado ainda não incluiu em um pedido de saque. Quando ele solicitar o saque, as linhas somem daqui e passam para a tabela de pedidos abaixo.', 'pb-affiliates' ); ?></p>
				<table class="wp-list-table widefat fixed striped table-view-list" style="margin-top:0.5em;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Afiliado', 'pb-affiliates' ); ?></th>
							<th><?php esc_html_e( 'Comissões', 'pb-affiliates' ); ?></th>
							<th><?php esc_html_e( 'Total', 'pb-affiliates' ); ?></th>
							<th><?php esc_html_e( 'Reservado em saque', 'pb-affiliates' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $aggregated ) ) : ?>
							<tr><td colspan="4"><?php esc_html_e( 'Nenhum saldo manual livre aguardando solicitação de saque.', 'pb-affiliates' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $aggregated as $arow ) : ?>
								<?php
								$aff_aid   = (int) $arow['affiliate_id'];
								$u_a       = $aff_aid ? get_userdata( $aff_aid ) : false;
								$curr      = get_woocommerce_currency();
								$lock_t    = isset( $arow['locked_in_withdrawal_total'] ) ? (float) $arow['locked_in_withdrawal_total'] : 0.0;
								$lock_c    = isset( $arow['locked_in_withdrawal_count'] ) ? (int) $arow['locked_in_withdrawal_count'] : 0;
								$lock_cell = '—';
								if ( $lock_t > 0.009 || $lock_c > 0 ) {
									$lock_cell = wp_kses_post(
										wc_price( $lock_t, array( 'currency' => $curr ) )
										. ' · ' .
										sprintf(
											/* translators: %d: commission count */
											_n( '%d comissão', '%d comissões', $lock_c, 'pb-affiliates' ),
											$lock_c
										)
									);
								}
								?>
								<tr>
									<td>
										<?php if ( $u_a && $aff_aid ) : ?>
											<a href="<?php echo esc_url( PB_Affiliates_Admin_User_Detail::url( $aff_aid ) ); ?>"><?php echo esc_html( $u_a->display_name ); ?></a>
											<br /><span class="description"><?php echo esc_html( $u_a->user_email ); ?></span>
										<?php else : ?>
											<?php echo esc_html( $aff_aid ? '#' . $aff_aid : '—' ); ?>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( (string) (int) $arow['pending_count'] ); ?></td>
									<td><?php echo wp_kses_post( wc_price( (float) $arow['pending_total'], array( 'currency' => $curr ) ) ); ?></td>
									<td><?php echo wp_kses_post( $lock_cell ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php elseif ( 'pending' === $tab && 'split' === $mode ) : ?>
				<p class="description" style="margin-top:1em;">
					<?php esc_html_e( 'Não há saldo manual neste modo; use a tabela de pedidos de saque apenas se existir fluxo misto ou registros antigos.', 'pb-affiliates' ); ?>
				</p>
			<?php endif; ?>

			<h2 style="margin-top:2em"><?php esc_html_e( 'Pedidos de saque', 'pb-affiliates' ); ?></h2>
			<p class="description">
				<?php if ( 'paid' === $tab ) : ?>
					<?php esc_html_e( 'Histórico de saques marcados como pagos (comprovante na última coluna, quando informado).', 'pb-affiliates' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Transfira o valor ao afiliado, preencha o comprovante e marque como pago — todas as comissões do pedido serão liquidadas de uma vez.', 'pb-affiliates' ); ?>
				<?php endif; ?>
			</p>

			<table class="wp-list-table widefat fixed striped table-view-list" style="margin-top:1em;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'pb-affiliates' ); ?></th>
						<th><?php esc_html_e( 'Afiliado', 'pb-affiliates' ); ?></th>
						<th><?php esc_html_e( 'Dados para repasse', 'pb-affiliates' ); ?></th>
						<th><?php esc_html_e( 'Valor', 'pb-affiliates' ); ?></th>
						<th><?php esc_html_e( 'Comissões', 'pb-affiliates' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'pb-affiliates' ); ?></th>
						<th><?php esc_html_e( 'Pedido em', 'pb-affiliates' ); ?></th>
						<th><?php esc_html_e( 'Pago em', 'pb-affiliates' ); ?></th>
						<th style="min-width:14rem;"><?php esc_html_e( 'Comprovante / ação', 'pb-affiliates' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'Nenhum registro.', 'pb-affiliates' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$aff_id   = (int) $row['affiliate_id'];
							$u        = get_userdata( $aff_id );
							$label    = $u ? $u->display_name . ' (' . $u->user_email . ')' : '#' . $aff_id;
							$cc       = PB_Affiliates_Withdrawal::count_commissions_in_withdrawal_row( $row );
							$bank_lines = PB_Affiliates_Withdrawal::get_bank_detail_lines( $aff_id );
							$bank_block = '';
							if ( ! empty( $bank_lines ) ) {
								$bank_block = '<ul style="margin:0.35em 0 0 1.25em;"><li>' . implode( '</li><li>', array_map( 'esc_html', $bank_lines ) ) . '</li></ul>';
							} else {
								$bank_block = '<span class="description">' . esc_html__( 'Sem dados bancários no perfil. Modo split: ver Account ID em Detalhes da conta.', 'pb-affiliates' ) . '</span>';
							}
							$acc_id_pb = (string) get_user_meta( $aff_id, 'pb_affiliate_pagbank_account_id', true );
							if ( 'split' === $mode && $acc_id_pb !== '' ) {
								$bank_block .= '<p style="margin:0.5em 0 0;"><strong>PagBank Account ID:</strong> <code>' . esc_html( $acc_id_pb ) . '</code></p>';
							}
							?>
							<tr>
								<td><?php echo (int) $row['id']; ?></td>
								<td>
									<?php if ( $u ) : ?>
										<a href="<?php echo esc_url( get_edit_user_link( $aff_id ) ); ?>"><?php echo esc_html( $label ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $label ); ?>
									<?php endif; ?>
								</td>
								<td>
									<details>
										<summary style="cursor:pointer;"><?php esc_html_e( 'Ver dados', 'pb-affiliates' ); ?></summary>
										<div style="margin-top:0.5em;"><?php echo wp_kses_post( $bank_block ); ?></div>
									</details>
								</td>
								<td><?php echo wp_kses_post( wc_price( (float) $row['amount'], array( 'currency' => $row['currency'] ? $row['currency'] : get_woocommerce_currency() ) ) ); ?></td>
								<td><?php echo esc_html( (string) (int) $cc ); ?></td>
								<td><?php echo esc_html( $row['status'] ); ?></td>
								<td><?php echo esc_html( $row['created_at'] ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ) : '—' ); ?></td>
								<td><?php echo ! empty( $row['processed_at'] ) ? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['processed_at'] ) ) : '—'; ?></td>
								<td>
									<?php if ( 'pending' === $row['status'] ) : ?>
										<form method="post" class="pb-aff-withdrawal-pay-form" onsubmit="return confirm(<?php echo wp_json_encode( __( 'Confirmar que o valor foi transferido ao afiliado?', 'pb-affiliates' ) ); ?>);">
											<?php wp_nonce_field( 'pb_aff_payment_action' ); ?>
											<input type="hidden" name="pb_aff_tab" value="<?php echo esc_attr( $tab ); ?>" />
											<input type="hidden" name="pb_aff_withdrawal_id" value="<?php echo (int) $row['id']; ?>" />
											<input type="hidden" name="pb_aff_payment_action" value="mark_paid" />
											<p style="margin:0 0 0.35em;">
												<label>
													<span class="screen-reader-text"><?php esc_html_e( 'Comprovante', 'pb-affiliates' ); ?></span>
													<textarea name="pb_aff_payment_proof" rows="2" class="large-text" required placeholder="<?php echo esc_attr__( 'Ex.: PIX, últimos dígitos, ID da transação, arquivo…', 'pb-affiliates' ); ?>"></textarea>
												</label>
											</p>
											<button type="submit" class="button button-primary"><?php esc_html_e( 'Marcar como pago', 'pb-affiliates' ); ?></button>
										</form>
									<?php else : ?>
										<?php self::render_paid_proof_cell( isset( $row['notes'] ) ? (string) $row['notes'] : '' ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
