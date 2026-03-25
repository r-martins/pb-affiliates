<?php
/**
 * Affiliate dashboard (Minha conta).
 *
 * @package PB_Affiliates
 * @var string $link Referral link.
 * @var string $code Affiliate public code.
 * @var object|null $summary Dashboard totals (DB rows + estimated_*, total_with_estimates, pending_with_estimates).
 * @var string $commission_rate_description Comissão efetiva (texto).
 * @var array  $affiliate_domains           Domínios de referência (meta pb_affiliate_verified_domains).
 * @var string $pb_aff_payment_mode          manual|split.
 * @var float  $pb_aff_withdraw_balance     Saldo manual disponível para novo saque (após retenção, fora saque pendente).
 * @var bool   $pb_aff_withdraw_pending      Há pedido de saque pendente.
 * @var float  $pb_aff_min_withdrawal        Valor mínimo configurado.
 * @var array  $pb_aff_paid_withdrawals      Saques já pagos (histórico; modo manual).
 * @var bool   $pb_aff_has_promo_materials   Exibe atalho para materiais promocionais.
 * @var string $pb_aff_materials_url         URL do endpoint affiliate-materials.
 */

defined( 'ABSPATH' ) || exit;

// Algumas themes/caches não exibem `wc_print_notices()` nos endpoints customizados do WooCommerce.
// Mantemos WooCommerce notices quando disponíveis e fazemos fallback por query-string quando estiverem vazias.
$pb_aff_area_notice_key  = isset( $_GET['pb_aff_area_notice'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_area_notice'] ) ) : '';
$pb_aff_area_notice_type = isset( $_GET['pb_aff_area_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_area_notice_type'] ) ) : '';

$pb_aff_area_notice_type = in_array( $pb_aff_area_notice_type, array( 'error', 'success' ), true ) ? $pb_aff_area_notice_type : '';

$pb_has_wc_notices = false;
if ( function_exists( 'wc_get_notices' ) ) {
	$pb_notices = wc_get_notices();
	if ( is_array( $pb_notices ) && ! empty( $pb_notices ) ) {
		$pb_has_wc_notices = true;
	}
}

if ( $pb_has_wc_notices && function_exists( 'wc_print_notices' ) ) {
	wc_print_notices();
} else {
	$pb_msg = '';
	$type   = $pb_aff_area_notice_type ? $pb_aff_area_notice_type : 'success';
	switch ( (string) $pb_aff_area_notice_key ) {
		case 'join_terms_required':
			$pb_msg = __( 'Aceite os termos para continuar.', 'pb-affiliates' );
			break;
		case 'join_not_allowed':
			$pb_msg = __( 'Não é possível concluir o pedido.', 'pb-affiliates' );
			break;
		case 'join_pending':
			$pb_msg = __( 'Pedido enviado. Aguarde a aprovação da loja.', 'pb-affiliates' );
			break;
		case 'join_active':
			$pb_msg = __( 'Bem-vindo ao programa de afiliados! Seu link está disponível abaixo.', 'pb-affiliates' );
			break;
		case 'code_not_active':
			$pb_msg = __( 'Apenas afiliados ativos podem alterar o identificador.', 'pb-affiliates' );
			$type   = 'error';
			break;
		case 'code_too_short':
			$pb_msg = __( 'O identificador deve ter pelo menos 3 caracteres (letras minúsculas, números, _ ou -).', 'pb-affiliates' );
			$type   = 'error';
			break;
		case 'code_in_use':
			$pb_msg = __( 'Este identificador já está em uso. Escolha outro.', 'pb-affiliates' );
			$type   = 'error';
			break;
		case 'code_updated':
			$pb_msg = __( 'Identificador atualizado.', 'pb-affiliates' );
			break;
		case 'withdraw_not_active':
			$pb_msg = __( 'Apenas afiliados ativos podem solicitar saque.', 'pb-affiliates' );
			$type   = 'error';
			break;
		case 'withdraw_error':
			$pb_msg = __( 'Não foi possível concluir o pedido de saque.', 'pb-affiliates' );
			$type   = 'error';
			break;
		case 'withdraw_requested':
			$pb_msg = __( 'Pedido de saque enviado. A loja irá processar e registrar o pagamento.', 'pb-affiliates' );
			break;
		default:
			break;
	}
	if ( '' !== $pb_msg ) {
		// WooCommerce usa `woocommerce-message` para mensagens de sucesso.
		$ul_class = 'error' === $type ? 'woocommerce-error' : 'woocommerce-message';
		echo '<div class="woocommerce-notices-wrapper" aria-label="' . esc_attr__( 'Notices', 'pb-affiliates' ) . '">';
		echo '<ul class="' . esc_attr( $ul_class ) . '" role="alert"><li>' . wp_kses_post( $pb_msg ) . '</li></ul>';
		echo '</div>';
	}
}

$affiliate_domains = isset( $affiliate_domains ) && is_array( $affiliate_domains ) ? $affiliate_domains : array();

if ( ! isset( $summary ) || ! is_object( $summary ) ) {
	$summary = (object) array();
}

$pb_aff_orders        = isset( $summary->orders ) ? (int) $summary->orders : 0;
$pb_aff_total         = isset( $summary->total ) ? (float) $summary->total : 0.0;
$pb_aff_pending_total = isset( $summary->pending_total ) ? (float) $summary->pending_total : 0.0;
$pb_aff_paid_total    = isset( $summary->paid_total ) ? (float) $summary->paid_total : 0.0;
$pb_aff_pending_count = isset( $summary->pending_count ) ? (int) $summary->pending_count : 0;
$pb_aff_paid_count    = isset( $summary->paid_count ) ? (int) $summary->paid_count : 0;
$pb_aff_est_total     = isset( $summary->estimated_total ) ? (float) $summary->estimated_total : 0.0;
$pb_aff_est_count     = isset( $summary->estimated_count ) ? (int) $summary->estimated_count : 0;
$pb_aff_total_incl    = isset( $summary->total_with_estimates ) ? (float) $summary->total_with_estimates : $pb_aff_total + $pb_aff_est_total;
$pb_aff_pending_incl  = isset( $summary->pending_with_estimates ) ? (float) $summary->pending_with_estimates : $pb_aff_pending_total + $pb_aff_est_total;

$pb_aff_tip_topline_total   = __( 'Soma de tudo que já foi registrado na loja como comissão (pendente de repasse ou já pago) mais estimativas para pedidos seus pendentes de pagamento.', 'pb-affiliates' );
$pb_aff_tip_topline_pending = __( 'O que ainda não foi pago a você: comissões já registradas aguardando repasse, mais estimativas enquanto o pedido segue pendente de pagamento (sem comissão registrada ainda). Não inclui comissões já marcadas como pagas ao afiliado.', 'pb-affiliates' );
$pb_aff_tip_hero_title      = __( 'Registrado: comissão já reconhecida. Estimativa: valor previsto para pedidos pendentes de pagamento, antes de existir registro de comissão. Em Pendentes entram comissões registradas aguardando repasse ao afiliado e essas estimativas. Pagas são só as repassadas a você.', 'pb-affiliates' );
$pb_aff_tip_card_total      = __( 'Total = soma do registrado na loja mais estimativas para pedidos pendentes de pagamento.', 'pb-affiliates' );
$pb_aff_tip_card_pending    = __( 'Pendentes = comissões registradas com repasse ao afiliado ainda por fazer, mais estimativas para pedidos pendentes de pagamento. Exclui o que já foi pago a você.', 'pb-affiliates' );
?>
<div class="pb-aff-dashboard-topline" role="region" aria-label="<?php esc_attr_e( 'Totais de comissão', 'pb-affiliates' ); ?>">
	<div class="pb-aff-dashboard-topline__inner">
		<div class="pb-aff-dashboard-topline__stat">
			<span class="pb-aff-dashboard-topline__label-row">
				<span class="pb-aff-dashboard-topline__label"><?php esc_html_e( 'Total de comissões', 'pb-affiliates' ); ?></span>
				<span class="pb-aff-dashboard-info" tabindex="0" title="<?php echo esc_attr( $pb_aff_tip_topline_total ); ?>" aria-label="<?php echo esc_attr( $pb_aff_tip_topline_total ); ?>"><?php esc_html_e( '(i)', 'pb-affiliates' ); ?></span>
			</span>
			<strong class="pb-aff-dashboard-topline__value"><?php echo wp_kses_post( wc_price( $pb_aff_total_incl ) ); ?></strong>
		</div>
		<div class="pb-aff-dashboard-topline__stat pb-aff-dashboard-topline__stat--pending">
			<span class="pb-aff-dashboard-topline__label-row">
				<span class="pb-aff-dashboard-topline__label"><?php esc_html_e( 'Pendentes', 'pb-affiliates' ); ?></span>
				<span class="pb-aff-dashboard-info" tabindex="0" title="<?php echo esc_attr( $pb_aff_tip_topline_pending ); ?>" aria-label="<?php echo esc_attr( $pb_aff_tip_topline_pending ); ?>"><?php esc_html_e( '(i)', 'pb-affiliates' ); ?></span>
			</span>
			<strong class="pb-aff-dashboard-topline__value"><?php echo wp_kses_post( wc_price( $pb_aff_pending_incl ) ); ?></strong>
		</div>
	</div>
	<?php if ( $pb_aff_est_count > 0 ) : ?>
		<p class="pb-aff-dashboard-topline__hint">
			<?php esc_html_e( 'Inclui estimativas para pedidos seus pendentes de pagamento (por exemplo, aguardando pagamento ou confirmação).', 'pb-affiliates' ); ?>
		</p>
	<?php endif; ?>
</div>

<section class="pb-aff-dashboard-hero" aria-labelledby="pb-aff-dashboard-hero-title">
	<h2 id="pb-aff-dashboard-hero-title" class="pb-aff-dashboard-hero__title pb-aff-dashboard-hero__title--with-info">
		<?php esc_html_e( 'Resumo das comissões', 'pb-affiliates' ); ?>
		<span class="pb-aff-dashboard-info" tabindex="0" title="<?php echo esc_attr( $pb_aff_tip_hero_title ); ?>" aria-label="<?php echo esc_attr( $pb_aff_tip_hero_title ); ?>"><?php esc_html_e( '(i)', 'pb-affiliates' ); ?></span>
	</h2>
	<p class="pb-aff-dashboard-hero__intro">
		<?php esc_html_e( 'O painel abaixo separa o que já está registrado na loja, estimativas para pedidos pendentes de pagamento e comissões já pagas a você.', 'pb-affiliates' ); ?>
	</p>
	<p class="pb-aff-dashboard-hero__legend">
		<?php esc_html_e( 'Passe o cursor (ou foco com o teclado) no (i) para ver o significado de cada total.', 'pb-affiliates' ); ?>
	</p>
	<div class="pb-aff-dashboard-hero__grid">
		<div class="pb-aff-dashboard-hero__card pb-aff-dashboard-hero__card--total">
			<span class="pb-aff-dashboard-hero__label">
				<?php esc_html_e( 'Total (registrado + estimativa)', 'pb-affiliates' ); ?>
				<span class="pb-aff-dashboard-info" tabindex="0" title="<?php echo esc_attr( $pb_aff_tip_card_total ); ?>" aria-label="<?php echo esc_attr( $pb_aff_tip_card_total ); ?>"><?php esc_html_e( '(i)', 'pb-affiliates' ); ?></span>
			</span>
			<strong class="pb-aff-dashboard-hero__amount"><?php echo wp_kses_post( wc_price( $pb_aff_total_incl ) ); ?></strong>
			<span class="pb-aff-dashboard-hero__meta">
				<?php
				$pb_aff_meta_bits = array(
					sprintf(
						/* translators: %d: commission rows in DB */
						_n( '%d registro na loja', '%d registros na loja', $pb_aff_orders, 'pb-affiliates' ),
						$pb_aff_orders
					),
				);
				if ( $pb_aff_est_count > 0 ) {
					$pb_aff_meta_bits[] = sprintf(
						/* translators: %d: attributed orders still pending payment (commission shown as estimate) */
						_n( '%d pedido pendente de pagamento', '%d pedidos pendentes de pagamento', $pb_aff_est_count, 'pb-affiliates' ),
						$pb_aff_est_count
					);
				}
				echo esc_html( implode( ' · ', $pb_aff_meta_bits ) );
				?>
			</span>
		</div>
		<div class="pb-aff-dashboard-hero__card pb-aff-dashboard-hero__card--pending">
			<span class="pb-aff-dashboard-hero__label">
				<?php esc_html_e( 'Pendentes (registrado + estimativa)', 'pb-affiliates' ); ?>
				<span class="pb-aff-dashboard-info" tabindex="0" title="<?php echo esc_attr( $pb_aff_tip_card_pending ); ?>" aria-label="<?php echo esc_attr( $pb_aff_tip_card_pending ); ?>"><?php esc_html_e( '(i)', 'pb-affiliates' ); ?></span>
				<span class="pb-aff-badge pb-aff-badge--pending"><?php esc_html_e( 'Pendente', 'pb-affiliates' ); ?></span>
			</span>
			<strong class="pb-aff-dashboard-hero__amount"><?php echo wp_kses_post( wc_price( $pb_aff_pending_incl ) ); ?></strong>
			<span class="pb-aff-dashboard-hero__meta">
				<?php
				$pb_aff_pend_bits = array();
				if ( $pb_aff_pending_count > 0 ) {
					$pb_aff_pend_bits[] = sprintf(
						/* translators: %d: pending commission rows in DB */
						_n( '%d registrada pendente de repasse', '%d registradas pendentes de repasse', $pb_aff_pending_count, 'pb-affiliates' ),
						$pb_aff_pending_count
					);
				}
				if ( $pb_aff_est_count > 0 ) {
					$pb_aff_pend_bits[] = sprintf(
						/* translators: %d: attributed orders still pending payment (commission shown as estimate) */
						_n( '%d pedido pendente de pagamento', '%d pedidos pendentes de pagamento', $pb_aff_est_count, 'pb-affiliates' ),
						$pb_aff_est_count
					);
				}
				if ( empty( $pb_aff_pend_bits ) ) {
					esc_html_e( 'Nada pendente no momento.', 'pb-affiliates' );
				} else {
					echo esc_html( implode( ' · ', $pb_aff_pend_bits ) );
				}
				?>
			</span>
		</div>
		<div class="pb-aff-dashboard-hero__card pb-aff-dashboard-hero__card--paid">
			<span class="pb-aff-dashboard-hero__label">
				<?php esc_html_e( 'Pagas', 'pb-affiliates' ); ?>
				<span class="pb-aff-badge pb-aff-badge--paid"><?php esc_html_e( 'Paga', 'pb-affiliates' ); ?></span>
			</span>
			<strong class="pb-aff-dashboard-hero__amount"><?php echo wp_kses_post( wc_price( $pb_aff_paid_total ) ); ?></strong>
			<span class="pb-aff-dashboard-hero__meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of paid commission rows */
						_n( '%d comissão paga', '%d comissões pagas', $pb_aff_paid_count, 'pb-affiliates' ),
						$pb_aff_paid_count
					)
				);
				?>
			</span>
		</div>
	</div>
	<p class="pb-aff-dashboard-hero__actions">
		<a class="button woocommerce-Button pb-aff-btn--secondary" href="<?php echo esc_url( wc_get_account_endpoint_url( 'affiliate-reports' ) ); ?>">
			<?php esc_html_e( 'Ver relatórios', 'pb-affiliates' ); ?>
		</a>
		<?php
		$pb_aff_has_promo_materials = ! empty( $pb_aff_has_promo_materials );
		$pb_aff_materials_url       = isset( $pb_aff_materials_url ) ? (string) $pb_aff_materials_url : '';
		if ( $pb_aff_has_promo_materials && $pb_aff_materials_url ) :
			?>
		<a class="button woocommerce-Button pb-aff-btn--secondary" href="<?php echo esc_url( $pb_aff_materials_url ); ?>">
			<?php esc_html_e( 'Ver materiais promocionais', 'pb-affiliates' ); ?>
		</a>
		<?php endif; ?>
	</p>
</section>

<?php
$pb_aff_payment_mode     = isset( $pb_aff_payment_mode ) ? (string) $pb_aff_payment_mode : 'manual';
$pb_aff_withdraw_balance = isset( $pb_aff_withdraw_balance ) ? (float) $pb_aff_withdraw_balance : 0.0;
$pb_aff_withdraw_pending = ! empty( $pb_aff_withdraw_pending );
$pb_aff_min_withdrawal   = isset( $pb_aff_min_withdrawal ) ? (float) $pb_aff_min_withdrawal : 0.0;
$pb_aff_can_request      = ( 'manual' === $pb_aff_payment_mode && ! $pb_aff_withdraw_pending && $pb_aff_withdraw_balance >= $pb_aff_min_withdrawal && $pb_aff_withdraw_balance > 0 );
?>
<?php if ( 'manual' === $pb_aff_payment_mode ) : ?>
<section class="pb-aff-dashboard-withdrawal" aria-labelledby="pb-aff-withdraw-title">
	<h2 id="pb-aff-withdraw-title"><?php esc_html_e( 'Solicitar pagamento', 'pb-affiliates' ); ?></h2>
	<?php if ( $pb_aff_withdraw_pending ) : ?>
		<p class="woocommerce-info">
			<?php esc_html_e( 'Você já tem um pedido de saque em análise. Aguarde o processamento antes de solicitar outro.', 'pb-affiliates' ); ?>
		</p>
	<?php elseif ( $pb_aff_withdraw_balance <= 0 ) : ?>
		<p class="woocommerce-message">
			<?php esc_html_e( 'Não há saldo disponível para saque no momento (após retenção ou ainda reservado em pedido anterior).', 'pb-affiliates' ); ?>
		</p>
	<?php elseif ( $pb_aff_withdraw_balance < $pb_aff_min_withdrawal ) : ?>
		<p class="woocommerce-message">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: 1: current balance, 2: minimum to withdraw */
					__( 'Saldo disponível: %1$s. Mínimo para saque: %2$s.', 'pb-affiliates' ),
					wp_strip_all_tags( wc_price( $pb_aff_withdraw_balance ) ),
					wp_strip_all_tags( wc_price( $pb_aff_min_withdrawal ) )
				)
			);
			?>
		</p>
	<?php endif; ?>
	<p>
		<strong><?php esc_html_e( 'Saldo disponível para novo saque:', 'pb-affiliates' ); ?></strong>
		<?php echo wp_kses_post( wc_price( $pb_aff_withdraw_balance ) ); ?>
	</p>
	<?php if ( $pb_aff_can_request ) : ?>
		<form method="post" class="pb-aff-withdrawal-request-form" onsubmit="return window.confirm(<?php echo wp_json_encode( __( 'Enviar pedido de saque pelo valor total exibido? Você só poderá solicitar outro após este ser processado.', 'pb-affiliates' ) ); ?>);">
			<?php wp_nonce_field( 'pb_aff_area', 'pb_aff_area_nonce' ); ?>
			<input type="hidden" name="pb_aff_area_action" value="request_withdrawal" />
			<input type="hidden" name="pb_aff_withdraw_amount" value="<?php echo esc_attr( wc_format_decimal( $pb_aff_withdraw_balance ) ); ?>" />
			<p>
				<button type="submit" class="button woocommerce-Button pb-aff-btn--primary"><?php esc_html_e( 'Solicitar saque do saldo total', 'pb-affiliates' ); ?></button>
			</p>
		</form>
	<?php endif; ?>
</section>
<?php endif; ?>

<?php
$pb_aff_paid_withdrawals = isset( $pb_aff_paid_withdrawals ) && is_array( $pb_aff_paid_withdrawals ) ? $pb_aff_paid_withdrawals : array();
?>
<section class="pb-aff-dashboard-payments-history" aria-labelledby="pb-aff-payments-history-title">
	<h2 id="pb-aff-payments-history-title"><?php esc_html_e( 'Pagamentos recebidos', 'pb-affiliates' ); ?></h2>
	<?php if ( 'split' === $pb_aff_payment_mode ) : ?>
		<p class="description"><?php esc_html_e( 'Abaixo aparecem repasses registrados pela loja (ex.: saques manuais). Comissões pagas via split PagBank costumam não constar nesta lista.', 'pb-affiliates' ); ?></p>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'Valores pagos a você.', 'pb-affiliates' ); ?></p>
	<?php endif; ?>
	<?php if ( empty( $pb_aff_paid_withdrawals ) ) : ?>
		<p class="woocommerce-message"><?php esc_html_e( 'Nenhum pagamento registrado nesta lista ainda.', 'pb-affiliates' ); ?></p>
	<?php else : ?>
		<table class="shop_table shop_table_responsive pb-aff-payments-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Pago em', 'pb-affiliates' ); ?></th>
					<th><?php esc_html_e( 'Valor', 'pb-affiliates' ); ?></th>
					<th><?php esc_html_e( 'Comissões', 'pb-affiliates' ); ?></th>
					<th><?php esc_html_e( 'Comprovante / referência', 'pb-affiliates' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pb_aff_paid_withdrawals as $pb_aff_pw ) : ?>
					<?php
					$pb_aff_pw_curr = ! empty( $pb_aff_pw['currency'] ) ? (string) $pb_aff_pw['currency'] : get_woocommerce_currency();
					$pb_aff_pw_when = ! empty( $pb_aff_pw['processed_at'] )
						? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $pb_aff_pw['processed_at'] )
						: '—';
					$pb_aff_pw_notes = isset( $pb_aff_pw['notes'] ) ? (string) $pb_aff_pw['notes'] : '';
					$pb_aff_pw_n     = class_exists( 'PB_Affiliates_Withdrawal', false )
						? PB_Affiliates_Withdrawal::count_commissions_in_withdrawal_row( $pb_aff_pw )
						: 0;
					$pb_aff_cut      = 120;
					$pb_aff_notes_len = function_exists( 'mb_strlen' ) ? mb_strlen( $pb_aff_pw_notes ) : strlen( $pb_aff_pw_notes );
					?>
					<tr>
						<td data-title="<?php esc_attr_e( 'Pago em', 'pb-affiliates' ); ?>"><?php echo esc_html( $pb_aff_pw_when ); ?></td>
						<td data-title="<?php esc_attr_e( 'Valor', 'pb-affiliates' ); ?>"><?php echo wp_kses_post( wc_price( (float) $pb_aff_pw['amount'], array( 'currency' => $pb_aff_pw_curr ) ) ); ?></td>
						<td data-title="<?php esc_attr_e( 'Comissões', 'pb-affiliates' ); ?>"><?php echo esc_html( (string) (int) $pb_aff_pw_n ); ?></td>
						<td data-title="<?php esc_attr_e( 'Comprovante / referência', 'pb-affiliates' ); ?>">
							<?php if ( '' === $pb_aff_pw_notes ) : ?>
								—
							<?php elseif ( $pb_aff_notes_len <= $pb_aff_cut ) : ?>
								<span class="pb-aff-proof-inline" style="white-space:pre-wrap;"><?php echo esc_html( $pb_aff_pw_notes ); ?></span>
							<?php else : ?>
								<?php
								$pb_aff_preview = function_exists( 'mb_substr' )
									? mb_substr( $pb_aff_pw_notes, 0, $pb_aff_cut )
									: substr( $pb_aff_pw_notes, 0, $pb_aff_cut );
								?>
								<details class="pb-aff-proof-expand">
									<summary style="cursor:pointer;"><?php echo esc_html( $pb_aff_preview . '…' ); ?></summary>
									<div style="margin-top:0.35em;white-space:pre-wrap;"><?php echo esc_html( $pb_aff_pw_notes ); ?></div>
								</details>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>

<h3><?php esc_html_e( 'Identificador de afiliado', 'pb-affiliates' ); ?></h3>
<form method="post" class="pb-aff-change-code">
	<?php wp_nonce_field( 'pb_aff_area', 'pb_aff_area_nonce' ); ?>
	<input type="hidden" name="pb_aff_area_action" value="change_code" />
	<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
		<label for="pb_aff_new_code"><?php esc_html_e( 'Identificador público (usado na URL de indicação)', 'pb-affiliates' ); ?></label>
		<input type="text" name="pb_aff_new_code" id="pb_aff_new_code" class="woocommerce-Input input-text" value="<?php echo esc_attr( (string) $code ); ?>" autocomplete="off" maxlength="40" />
	</p>
	<p>
		<button type="submit" class="button woocommerce-Button"><?php esc_html_e( 'Salvar identificador', 'pb-affiliates' ); ?></button>
	</p>
	<p class="description"><?php esc_html_e( 'Apenas letras minúsculas, números, hífen e sublinhado; mínimo 3 caracteres. Deve ser único na loja.', 'pb-affiliates' ); ?></p>
</form>

<h2><?php esc_html_e( 'Seu link de afiliado', 'pb-affiliates' ); ?></h2>
<div class="pb-aff-link-row">
	<input type="text" id="pb-aff-referral-link" readonly="readonly" class="pb-aff-link-row__input large-text woocommerce-Input" value="<?php echo esc_attr( $link ); ?>" onclick="this.select();" aria-label="<?php esc_attr_e( 'Link de afiliado', 'pb-affiliates' ); ?>" />
	<button type="button" class="button" id="pb-aff-copy-link-btn" data-label-done="<?php echo esc_attr( __( 'Copiado!', 'pb-affiliates' ) ); ?>">
		<?php esc_html_e( 'Copiar link', 'pb-affiliates' ); ?>
	</button>
</div>

<h3><?php esc_html_e( 'Comissão por venda', 'pb-affiliates' ); ?></h3>
<p class="pb-aff-commission-desc"><?php echo esc_html( isset( $commission_rate_description ) ? (string) $commission_rate_description : '' ); ?></p>
<p class="description"><?php esc_html_e( 'O valor final depende dos produtos do pedido. Cupons de afiliado com regra própria podem alterar a comissão.', 'pb-affiliates' ); ?></p>

<h3><?php esc_html_e( 'Domínio de referência (Referer)', 'pb-affiliates' ); ?></h3>
<?php
$pb_aff_referer_hint = __( 'Lembre-se de não colocar rel="noreferrer" em seus links.', 'pb-affiliates' );
?>
<p class="pb-aff-domain-intro">
	<?php esc_html_e( 'Tem um site ou blog? Informe o URL aqui.', 'pb-affiliates' ); ?><br />
	<?php esc_html_e( 'Toda vez que um link for clicado a partir do seu site, automaticamente saberemos que foi sua indicação, sem sequer precisar informar o seu código de afiliado.', 'pb-affiliates' ); ?>
	<span class="pb-aff-domain-info" tabindex="0" title="<?php echo esc_attr( $pb_aff_referer_hint ); ?>" aria-label="<?php echo esc_attr( $pb_aff_referer_hint ); ?>">(i)</span>
</p>
<form method="post" class="pb-aff-domain-form">
	<?php wp_nonce_field( 'pb_aff_domain', 'pb_aff_domain_nonce' ); ?>
	<input type="hidden" name="pb_aff_domain_action" value="add" />
	<p>
		<label for="pb_aff_domain_url"><?php esc_html_e( 'URL do seu site (domínio ou subdomínio)', 'pb-affiliates' ); ?></label>
		<input type="url" name="pb_aff_domain_url" id="pb_aff_domain_url" class="woocommerce-Input input-text" placeholder="https://exemplo.com.br" />
	</p>
	<button type="submit" class="button"><?php esc_html_e( 'Adicionar', 'pb-affiliates' ); ?></button>
</form>

<?php if ( ! empty( $affiliate_domains ) ) : ?>
	<h4 class="pb-aff-domain-heading"><?php esc_html_e( 'Seus sites', 'pb-affiliates' ); ?></h4>
	<table class="shop_table shop_table_responsive pb-aff-domain-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Site', 'pb-affiliates' ); ?></th>
				<th><?php esc_html_e( 'Status', 'pb-affiliates' ); ?></th>
				<th class="pb-aff-domain-table__action"><?php esc_html_e( 'Ações', 'pb-affiliates' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $affiliate_domains as $pb_aff_d ) {
				$pb_aff_host = isset( $pb_aff_d['host'] ) ? (string) $pb_aff_d['host'] : '';
				if ( '' === $pb_aff_host ) {
					continue;
				}
				$pb_aff_verified = ! empty( $pb_aff_d['verified'] );
				$pb_aff_token          = isset( $pb_aff_d['token'] ) ? (string) $pb_aff_d['token'] : '';
				$pb_aff_file_path      = $pb_aff_token ? 'https://' . $pb_aff_host . '/.well-known/pb-affiliate-' . $pb_aff_token . '.txt' : '';
				$pb_aff_download_name  = $pb_aff_token ? 'pb-affiliate-' . $pb_aff_token . '.txt' : '';
				$pb_aff_download_href  = $pb_aff_token ? 'data:text/plain;charset=utf-8,' . rawurlencode( $pb_aff_token ) : '';
				$pb_aff_remove_confirm = __( 'Remover este site da lista?', 'pb-affiliates' );
				?>
			<tr>
				<td data-title="<?php esc_attr_e( 'Site', 'pb-affiliates' ); ?>"><?php echo esc_html( $pb_aff_host ); ?></td>
				<td data-title="<?php esc_attr_e( 'Status', 'pb-affiliates' ); ?>">
					<?php
					if ( $pb_aff_verified ) {
						esc_html_e( 'Validado', 'pb-affiliates' );
					} else {
						esc_html_e( 'Pendente de validação', 'pb-affiliates' );
					}
					?>
				</td>
				<td class="pb-aff-domain-table__action" data-title="<?php esc_attr_e( 'Ações', 'pb-affiliates' ); ?>">
					<div class="pb-aff-domain-actions">
						<?php if ( ! $pb_aff_verified && $pb_aff_token && $pb_aff_download_href ) : ?>
							<form method="post" class="pb-aff-domain-validate-form">
								<?php wp_nonce_field( 'pb_aff_domain', 'pb_aff_domain_nonce' ); ?>
								<input type="hidden" name="pb_aff_domain_action" value="validate" />
								<input type="hidden" name="pb_aff_domain_host" value="<?php echo esc_attr( $pb_aff_host ); ?>" />
								<button type="submit" class="button woocommerce-Button pb-aff-btn--primary"><?php esc_html_e( 'Validar', 'pb-affiliates' ); ?></button>
							</form>
							<a class="button woocommerce-Button pb-aff-btn--secondary" href="<?php echo esc_attr( $pb_aff_download_href ); ?>" download="<?php echo esc_attr( $pb_aff_download_name ); ?>"><?php esc_html_e( 'Baixar arquivo', 'pb-affiliates' ); ?></a>
						<?php endif; ?>
						<form method="post" class="pb-aff-domain-remove-form" onsubmit="return window.confirm(<?php echo wp_json_encode( $pb_aff_remove_confirm ); ?>);">
							<?php wp_nonce_field( 'pb_aff_domain', 'pb_aff_domain_nonce' ); ?>
							<input type="hidden" name="pb_aff_domain_action" value="remove" />
							<input type="hidden" name="pb_aff_domain_host" value="<?php echo esc_attr( $pb_aff_host ); ?>" />
							<button type="submit" class="button woocommerce-Button pb-aff-btn--danger"><?php esc_html_e( 'Excluir', 'pb-affiliates' ); ?></button>
						</form>
					</div>
				</td>
			</tr>
				<?php if ( ! $pb_aff_verified && $pb_aff_file_path && $pb_aff_token ) : ?>
			<tr class="pb-aff-domain-table__instructions">
				<td colspan="3">
					<p class="description">
						<?php esc_html_e( 'Arquivo (URL exata):', 'pb-affiliates' ); ?>
						<a href="<?php echo esc_url( $pb_aff_file_path ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $pb_aff_file_path ); ?></a>
					</p>
					<p class="description">
						<?php esc_html_e( 'Conteúdo do arquivo (texto puro, uma linha):', 'pb-affiliates' ); ?>
						<code><?php echo esc_html( $pb_aff_token ); ?></code>
					</p>
				</td>
			</tr>
				<?php endif; ?>
				<?php
			}
			?>
		</tbody>
	</table>
<?php endif; ?>

<?php
$edit_account_url = wc_get_account_endpoint_url( 'edit-account' );
?>
<?php if ( 'split' === ( PB_Affiliates_Settings::get()['payment_mode'] ?? 'manual' ) ) : ?>
	<h3><?php esc_html_e( 'Conta PagBank (recebimento)', 'pb-affiliates' ); ?></h3>
	<p>
		<?php esc_html_e( 'Informe seu Account ID PagBank em Detalhes da conta para receber comissões via split.', 'pb-affiliates' ); ?>
		<a href="<?php echo esc_url( $edit_account_url ); ?>"><?php esc_html_e( 'Editar dados de recebimento', 'pb-affiliates' ); ?></a>
	</p>
<?php else : ?>
	<h3><?php esc_html_e( 'Dados bancários', 'pb-affiliates' ); ?></h3>
	<p>
		<?php esc_html_e( 'Informe banco, agência, conta e CPF/CNPJ em Detalhes da conta para pagamentos manuais.', 'pb-affiliates' ); ?>
		<a href="<?php echo esc_url( $edit_account_url ); ?>"><?php esc_html_e( 'Editar dados de recebimento', 'pb-affiliates' ); ?></a>
	</p>
<?php endif; ?>

<script>
(function(){
	var btn = document.getElementById('pb-aff-copy-link-btn');
	var inp = document.getElementById('pb-aff-referral-link');
	if (!btn || !inp) return;
	btn.addEventListener('click', function() {
		inp.focus();
		inp.select();
		inp.setSelectionRange(0, 99999);
		var done = btn.getAttribute('data-label-done') || 'OK';
		var orig = btn.textContent;
		function feedback() {
			btn.textContent = done;
			btn.disabled = true;
			setTimeout(function() {
				btn.textContent = orig;
				btn.disabled = false;
			}, 2000);
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(inp.value).then(feedback).catch(function() {
				try {
					document.execCommand('copy');
					feedback();
				} catch (e) {}
			});
		} else {
			try {
				document.execCommand('copy');
				feedback();
			} catch (e) {}
		}
	});
})();
</script>
