<?php
/**
 * Affiliate dashboard (Minha conta).
 *
 * @package PB_Affiliates
 * @var object|null $summary Dashboard totals (DB rows + prévia pedidos pendentes, total_with_estimates, pending_with_estimates).
 * @var string $commission_rate_description Comissão efetiva (texto).
 * @var string $pb_aff_payment_mode          manual|split.
 * @var float  $pb_aff_withdraw_balance     Saldo manual disponível para novo saque (após retenção, fora saque pendente).
 * @var bool   $pb_aff_withdraw_pending      Há pedido de saque pendente.
 * @var float  $pb_aff_min_withdrawal        Valor mínimo configurado.
 * @var array  $pb_aff_paid_withdrawals      Saques já pagos (histórico; modo manual).
 * @var bool   $pb_aff_show_payments_received Exibe bloco «Pagamentos recebidos» (em split só se houver dados relevantes).
 * @var bool   $pb_aff_has_promo_materials   Exibe atalho para materiais promocionais.
 * @var string $pb_aff_materials_url         URL do endpoint affiliate-materials.
 * @var string $pb_aff_pagbank_account_id    Account ID PagBank (modo split).
 * @var bool   $pb_aff_split_receipt_ready   true se ACCO_… válido para split.
 * @var array  $pb_aff_bank_dashboard_lines  Linhas de dados bancários (documento mascarado).
 * @var array  $pb_aff_period_bundle       Métricas do período + comparação com período anterior.
 * @var array  $pb_aff_dash_chart          Série do gráfico (labels, values, order_values).
 * @var int    $pb_dash_days                7|14|30|90 (query pb_dash).
 * @var int    $pb_aff_clicks_alltime      Total de cliques no histórico.
 * @var string $pb_aff_nav_active          dashboard (para o partial de navegação).
 */

defined( 'ABSPATH' ) || exit;

// Algumas themes/caches não exibem `wc_print_notices()` nos endpoints customizados do WooCommerce.
// Mantemos WooCommerce notices quando disponíveis e fazemos fallback por query-string quando estiverem vazias.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Notice keys are read-only (redirect); values sanitized below.
$pb_aff_area_notice_key  = isset( $_GET['pb_aff_area_notice'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_area_notice'] ) ) : '';
$pb_aff_area_notice_type = isset( $_GET['pb_aff_area_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_area_notice_type'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

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

$pb_aff_tip_hero_title   = __( 'Registrado: comissão já reconhecida na loja. Pedidos pendentes: atribuídos a você sem comissão registrada ainda; mostramos o valor previsto. Na secção Pendentes entram comissões já registradas aguardando repasse e esse valor previsto. Pagas são só as repassadas a você.', 'pb-affiliates' );
$pb_aff_tip_card_total   = __( 'Total = soma do registrado na loja mais o valor previsto para pedidos pendentes.', 'pb-affiliates' );
$pb_aff_tip_card_pending = __( 'Pendentes = comissões registradas com repasse ao afiliado ainda por fazer, mais o valor previsto em pedidos pendentes. Exclui o que já foi pago a você.', 'pb-affiliates' );
$pb_aff_tip_period_comm  = __( 'Comissões já registradas na loja neste intervalo (somadas na base de dados). Não inclui apenas o valor previsto de pedidos sem linha de comissão.', 'pb-affiliates' );

$pb_aff_period_bundle = isset( $pb_aff_period_bundle ) && is_array( $pb_aff_period_bundle ) ? $pb_aff_period_bundle : array(
	'days'          => 30,
	'range_heading' => '',
	'current'       => array(
		'clicks'     => 0,
		'orders'     => 0,
		'commission' => 0.0,
	),
	'delta_pct'     => array(
		'clicks'     => null,
		'orders'     => null,
		'commission' => null,
	),
);
$pb_aff_dash_chart     = isset( $pb_aff_dash_chart ) && is_array( $pb_aff_dash_chart ) ? $pb_aff_dash_chart : array(
	'labels'       => array(),
	'values'       => array(),
	'order_values' => array(),
);
$pb_dash_days          = isset( $pb_dash_days ) ? (int) $pb_dash_days : 30;
$pb_aff_clicks_alltime = isset( $pb_aff_clicks_alltime ) ? (int) $pb_aff_clicks_alltime : 0;
$pb_dash_cur           = isset( $pb_aff_period_bundle['current'] ) && is_array( $pb_aff_period_bundle['current'] ) ? $pb_aff_period_bundle['current'] : array();
$pb_dash_dlt           = isset( $pb_aff_period_bundle['delta_pct'] ) && is_array( $pb_aff_period_bundle['delta_pct'] ) ? $pb_aff_period_bundle['delta_pct'] : array();
$pb_dash_clicks        = isset( $pb_dash_cur['clicks'] ) ? (int) $pb_dash_cur['clicks'] : 0;
$pb_dash_orders        = isset( $pb_dash_cur['orders'] ) ? (int) $pb_dash_cur['orders'] : 0;
$pb_dash_comm          = isset( $pb_dash_cur['commission'] ) ? (float) $pb_dash_cur['commission'] : 0.0;

$pb_aff_order_vals = isset( $pb_aff_dash_chart['order_values'] ) ? array_values( array_map( 'intval', (array) $pb_aff_dash_chart['order_values'] ) ) : array();
$pb_dash_chart_json  = wp_json_encode(
	array(
		'labels'      => isset( $pb_aff_dash_chart['labels'] ) ? array_values( (array) $pb_aff_dash_chart['labels'] ) : array(),
		'values'      => isset( $pb_aff_dash_chart['values'] ) ? array_values( array_map( 'intval', (array) $pb_aff_dash_chart['values'] ) ) : array(),
		'orderValues' => $pb_aff_order_vals,
		'dslabel'     => __( 'Visitas / cliques', 'pb-affiliates' ),
		'orderLabel'  => __( 'Pedidos', 'pb-affiliates' ),
	),
	JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
);
?>
<div class="pb-aff-hub">
	<?php include PB_AFFILIATES_PATH . 'templates/my-account/parts/affiliate-hub-nav.php'; ?>

	<div class="pb-aff-hub-toolbar">
		<form method="get" action="<?php echo esc_url( wc_get_account_endpoint_url( 'affiliate-area' ) ); ?>" class="pb-aff-hub-toolbar__form">
			<label class="pb-aff-hub-toolbar__label" for="pb-aff-dash-days"><?php esc_html_e( 'Período', 'pb-affiliates' ); ?></label>
			<select name="pb_dash" id="pb-aff-dash-days" class="pb-aff-hub-toolbar__select" onchange="this.form.submit()">
				<option value="7" <?php selected( $pb_dash_days, 7 ); ?>><?php esc_html_e( 'Últimos 7 dias', 'pb-affiliates' ); ?></option>
				<option value="14" <?php selected( $pb_dash_days, 14 ); ?>><?php esc_html_e( 'Últimos 14 dias', 'pb-affiliates' ); ?></option>
				<option value="30" <?php selected( $pb_dash_days, 30 ); ?>><?php esc_html_e( 'Últimos 30 dias', 'pb-affiliates' ); ?></option>
				<option value="90" <?php selected( $pb_dash_days, 90 ); ?>><?php esc_html_e( 'Últimos 90 dias', 'pb-affiliates' ); ?></option>
			</select>
			<span class="pb-aff-hub-toolbar__range"><?php echo esc_html( isset( $pb_aff_period_bundle['range_heading'] ) ? (string) $pb_aff_period_bundle['range_heading'] : '' ); ?></span>
			<noscript><button type="submit" class="button"><?php esc_html_e( 'Aplicar', 'pb-affiliates' ); ?></button></noscript>
		</form>
	</div>

	<div class="pb-aff-hub-metrics" role="region" aria-label="<?php esc_attr_e( 'Métricas do período selecionado', 'pb-affiliates' ); ?>">
		<article class="pb-aff-metric-card pb-aff-metric-card--clicks">
			<header class="pb-aff-metric-card__head">
				<span class="pb-aff-metric-card__label"><?php esc_html_e( 'Visitas e cliques', 'pb-affiliates' ); ?></span>
				<?php
				$pct = isset( $pb_dash_dlt['clicks'] ) ? $pb_dash_dlt['clicks'] : null;
				if ( null === $pct ) :
					?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--na" title="<?php esc_attr_e( 'Sem período anterior para comparar', 'pb-affiliates' ); ?>">—</span>
				<?php elseif ( (float) $pct > 0 ) : ?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--up">↑ <?php echo esc_html( number_format_i18n( (float) $pct, 1 ) ); ?>%</span>
				<?php elseif ( (float) $pct < 0 ) : ?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--down">↓ <?php echo esc_html( number_format_i18n( abs( (float) $pct ), 1 ) ); ?>%</span>
				<?php else : ?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--flat">→ 0%</span>
				<?php endif; ?>
			</header>
			<p class="pb-aff-metric-card__value"><?php echo esc_html( number_format_i18n( $pb_dash_clicks ) ); ?></p>
			<p class="pb-aff-metric-card__foot"><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'affiliate-reports' ) ); ?>"><?php esc_html_e( 'Ver registros', 'pb-affiliates' ); ?></a></p>
		</article>
		<article class="pb-aff-metric-card pb-aff-metric-card--orders">
			<header class="pb-aff-metric-card__head">
				<span class="pb-aff-metric-card__label"><?php esc_html_e( 'Pedidos atribuídos', 'pb-affiliates' ); ?></span>
				<?php
				$pct = isset( $pb_dash_dlt['orders'] ) ? $pb_dash_dlt['orders'] : null;
				if ( null === $pct ) :
					?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--na" title="<?php esc_attr_e( 'Sem período anterior para comparar', 'pb-affiliates' ); ?>">—</span>
				<?php elseif ( (float) $pct > 0 ) : ?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--up">↑ <?php echo esc_html( number_format_i18n( (float) $pct, 1 ) ); ?>%</span>
				<?php elseif ( (float) $pct < 0 ) : ?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--down">↓ <?php echo esc_html( number_format_i18n( abs( (float) $pct ), 1 ) ); ?>%</span>
				<?php else : ?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--flat">→ 0%</span>
				<?php endif; ?>
			</header>
			<p class="pb-aff-metric-card__value"><?php echo esc_html( number_format_i18n( $pb_dash_orders ) ); ?></p>
			<p class="pb-aff-metric-card__foot"><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'affiliate-reports' ) ); ?>"><?php esc_html_e( 'Ver relatórios', 'pb-affiliates' ); ?></a></p>
		</article>
		<article class="pb-aff-metric-card pb-aff-metric-card--comm">
			<header class="pb-aff-metric-card__head">
				<span class="pb-aff-metric-card__label"><?php esc_html_e( 'Comissões registradas', 'pb-affiliates' ); ?></span>
				<span class="pb-aff-dashboard-info pb-aff-metric-card__info" tabindex="0" title="<?php echo esc_attr( $pb_aff_tip_period_comm ); ?>" aria-label="<?php echo esc_attr( $pb_aff_tip_period_comm ); ?>"><?php echo esc_html( 'ℹ' ); ?></span>
				<?php
				$pct = isset( $pb_dash_dlt['commission'] ) ? $pb_dash_dlt['commission'] : null;
				if ( null === $pct ) :
					?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--na" title="<?php esc_attr_e( 'Sem período anterior para comparar', 'pb-affiliates' ); ?>">—</span>
				<?php elseif ( (float) $pct > 0 ) : ?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--up">↑ <?php echo esc_html( number_format_i18n( (float) $pct, 1 ) ); ?>%</span>
				<?php elseif ( (float) $pct < 0 ) : ?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--down">↓ <?php echo esc_html( number_format_i18n( abs( (float) $pct ), 1 ) ); ?>%</span>
				<?php else : ?>
				<span class="pb-aff-metric-trend pb-aff-metric-trend--flat">→ 0%</span>
				<?php endif; ?>
			</header>
			<p class="pb-aff-metric-card__value pb-aff-metric-card__value--money"><?php echo wp_kses_post( wc_price( $pb_dash_comm ) ); ?></p>
			<p class="pb-aff-metric-card__foot"><?php esc_html_e( 'Só valores já lançados na loja.', 'pb-affiliates' ); ?></p>
		</article>
	</div>

	<div class="pb-aff-chart-wrap pb-aff-hub-chart">
		<canvas id="pb-aff-dashboard-chart" width="400" height="220" aria-label="<?php esc_attr_e( 'Gráfico de visitas, cliques e pedidos por dia', 'pb-affiliates' ); ?>"></canvas>
	</div>
	<script type="application/json" id="pb-aff-dash-chart-json"><?php echo $pb_dash_chart_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
	<script>
	window.addEventListener('load', function () {
		var cfgEl = document.getElementById('pb-aff-dash-chart-json');
		var ctx = document.getElementById('pb-aff-dashboard-chart');
		if (!cfgEl || !ctx || typeof Chart === 'undefined') return;
		var cfg;
		try { cfg = JSON.parse(cfgEl.textContent || '{}'); } catch (e) { return; }
		if (!cfg.labels || !cfg.values) return;
		var orderSeries = cfg.orderValues && cfg.orderValues.length === cfg.values.length ? cfg.orderValues : cfg.values.map(function () { return 0; });
		new Chart(ctx, {
			type: 'line',
			data: {
				labels: cfg.labels,
				datasets: [
					{
						label: cfg.dslabel || '',
						data: cfg.values,
						borderColor: '#0e7490',
						backgroundColor: 'rgba(14, 116, 144, 0.12)',
						fill: true,
						tension: 0.3,
						pointRadius: 2,
						pointHoverRadius: 5
					},
					{
						label: cfg.orderLabel || '',
						data: orderSeries,
						borderColor: '#ea580c',
						backgroundColor: 'rgba(234, 88, 12, 0.1)',
						fill: true,
						tension: 0.3,
						pointRadius: 2,
						pointHoverRadius: 5
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				interaction: { mode: 'index', intersect: false },
				plugins: {
					legend: { display: true, position: 'bottom' }
				},
				scales: {
					y: { beginAtZero: true, ticks: { precision: 0 } }
				}
			}
		});
	});
	</script>

	<section class="pb-aff-hub-lifetime" aria-labelledby="pb-aff-lifetime-title">
		<h2 id="pb-aff-lifetime-title" class="pb-aff-hub-lifetime__title">
			<?php esc_html_e( 'Todo o período', 'pb-affiliates' ); ?>
			<span class="pb-aff-dashboard-info" tabindex="0" title="<?php echo esc_attr( $pb_aff_tip_hero_title ); ?>" aria-label="<?php echo esc_attr( $pb_aff_tip_hero_title ); ?>"><?php echo esc_html( 'ℹ' ); ?></span>
		</h2>
		<?php if ( $pb_aff_est_count > 0 ) : ?>
			<p class="pb-aff-hub-lifetime__hint"><?php esc_html_e( 'Os totais abaixo incluem valor previsto para pedidos pendentes (sem linha de comissão ainda).', 'pb-affiliates' ); ?></p>
		<?php endif; ?>
		<div class="pb-aff-hub-lifetime__grid">
			<div class="pb-aff-hub-lifetime__card pb-aff-hub-lifetime__card--total">
				<span class="pb-aff-hub-lifetime__label">
					<?php esc_html_e( 'Total', 'pb-affiliates' ); ?>
					<span class="pb-aff-dashboard-info" tabindex="0" title="<?php echo esc_attr( $pb_aff_tip_card_total ); ?>" aria-label="<?php echo esc_attr( $pb_aff_tip_card_total ); ?>"><?php echo esc_html( 'ℹ' ); ?></span>
				</span>
				<strong class="pb-aff-hub-lifetime__value"><?php echo wp_kses_post( wc_price( $pb_aff_total_incl ) ); ?></strong>
				<span class="pb-aff-hub-lifetime__meta"><?php echo esc_html( sprintf( /* translators: %d: number of shop orders counted */ _n( '%d registro na loja', '%d registros na loja', $pb_aff_orders, 'pb-affiliates' ), $pb_aff_orders ) ); ?></span>
			</div>
			<div class="pb-aff-hub-lifetime__card pb-aff-hub-lifetime__card--pending">
				<span class="pb-aff-hub-lifetime__label">
					<?php esc_html_e( 'Pendentes', 'pb-affiliates' ); ?>
					<span class="pb-aff-dashboard-info" tabindex="0" title="<?php echo esc_attr( $pb_aff_tip_card_pending ); ?>" aria-label="<?php echo esc_attr( $pb_aff_tip_card_pending ); ?>"><?php echo esc_html( 'ℹ' ); ?></span>
				</span>
				<strong class="pb-aff-hub-lifetime__value"><?php echo wp_kses_post( wc_price( $pb_aff_pending_incl ) ); ?></strong>
				<span class="pb-aff-hub-lifetime__meta">
					<?php
					$pb_aff_pend_bits = array();
					if ( $pb_aff_pending_count > 0 ) {
						$pb_aff_pend_bits[] = sprintf(
							/* translators: %d: number of pending commission rows */
							_n( '%d registrada pendente de repasse', '%d registradas pendentes de repasse', $pb_aff_pending_count, 'pb-affiliates' ),
							$pb_aff_pending_count
						);
					}
					if ( $pb_aff_est_count > 0 ) {
						$pb_aff_pend_bits[] = sprintf(
							/* translators: %d: number of orders with estimated commission */
							_n( '%d pedido pendente', '%d pedidos pendentes', $pb_aff_est_count, 'pb-affiliates' ),
							$pb_aff_est_count
						);
					}
					echo empty( $pb_aff_pend_bits ) ? esc_html__( 'Nada pendente no momento.', 'pb-affiliates' ) : esc_html( implode( ' · ', $pb_aff_pend_bits ) );
					?>
				</span>
			</div>
			<div class="pb-aff-hub-lifetime__card pb-aff-hub-lifetime__card--paid">
				<span class="pb-aff-hub-lifetime__label"><?php esc_html_e( 'Pagas', 'pb-affiliates' ); ?></span>
				<strong class="pb-aff-hub-lifetime__value"><?php echo wp_kses_post( wc_price( $pb_aff_paid_total ) ); ?></strong>
				<span class="pb-aff-hub-lifetime__meta"><?php echo esc_html( sprintf( /* translators: %d: number of paid commissions */ _n( '%d comissão paga', '%d comissões pagas', $pb_aff_paid_count, 'pb-affiliates' ), $pb_aff_paid_count ) ); ?></span>
			</div>
			<div class="pb-aff-hub-lifetime__card pb-aff-hub-lifetime__card--visits">
				<span class="pb-aff-hub-lifetime__label"><?php esc_html_e( 'Cliques no histórico', 'pb-affiliates' ); ?></span>
				<strong class="pb-aff-hub-lifetime__value"><?php echo esc_html( number_format_i18n( $pb_aff_clicks_alltime ) ); ?></strong>
				<span class="pb-aff-hub-lifetime__meta"><?php esc_html_e( 'Total desde o início do programa.', 'pb-affiliates' ); ?></span>
			</div>
		</div>
	</section>
</div>

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
$pb_aff_show_payments_received = ! isset( $pb_aff_show_payments_received ) || $pb_aff_show_payments_received;
?>
<?php if ( $pb_aff_show_payments_received ) : ?>
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
<?php endif; ?>

<h3><?php esc_html_e( 'Comissão por venda', 'pb-affiliates' ); ?></h3>
<p class="pb-aff-commission-desc"><?php echo esc_html( isset( $commission_rate_description ) ? (string) $commission_rate_description : '' ); ?></p>
<p class="description"><?php esc_html_e( 'O valor final depende dos produtos do pedido. Cupons de afiliado com regra própria podem alterar a comissão.', 'pb-affiliates' ); ?></p>
<p class="description"><a href="<?php echo esc_url( wc_get_account_endpoint_url( PB_Affiliates_Account::ENDPOINT_LINKS ) ); ?>"><?php esc_html_e( 'Identificador, link de indicação e domínios de referência', 'pb-affiliates' ); ?></a></p>

<?php
$edit_account_url              = wc_get_account_endpoint_url( 'edit-account' );
$pb_aff_bank_dashboard_lines   = isset( $pb_aff_bank_dashboard_lines ) && is_array( $pb_aff_bank_dashboard_lines ) ? $pb_aff_bank_dashboard_lines : array();
$pb_aff_pagbank_account_id     = isset( $pb_aff_pagbank_account_id ) ? (string) $pb_aff_pagbank_account_id : '';
$pb_aff_split_receipt_ready    = ! empty( $pb_aff_split_receipt_ready );
?>
<section class="pb-aff-dashboard-receipt" aria-labelledby="pb-aff-receipt-title">
<?php if ( 'split' === $pb_aff_payment_mode ) : ?>
	<h3 id="pb-aff-receipt-title"><?php esc_html_e( 'Conta PagBank (recebimento)', 'pb-affiliates' ); ?></h3>
	<?php if ( ! $pb_aff_split_receipt_ready ) : ?>
		<div class="woocommerce-error pb-aff-receipt-alert" role="alert">
			<div class="pb-aff-receipt-alert__body">
				<strong><?php esc_html_e( 'Informe seus dados de pagamento para receber suas comissões', 'pb-affiliates' ); ?></strong>
				<p><?php echo wp_kses_post( __( 'Sem informar seu account id PagBank os pagamentos <strong>não</strong> serão repassados pra você. Informe agora. É rápido e fácil.', 'pb-affiliates' ) ); ?></p>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( $pb_aff_split_receipt_ready ) : ?>
		<dl class="pb-aff-receipt-summary">
			<dt><?php esc_html_e( 'Account ID PagBank', 'pb-affiliates' ); ?></dt>
			<dd><code class="pb-aff-receipt-summary__code"><?php echo esc_html( $pb_aff_pagbank_account_id ); ?></code></dd>
		</dl>
	<?php elseif ( '' !== $pb_aff_pagbank_account_id ) : ?>
		<p class="description"><?php esc_html_e( 'O valor informado ainda não está no formato aceito (ACCO_ seguido do UUID). Ajuste em Detalhes da conta.', 'pb-affiliates' ); ?></p>
		<p><code class="pb-aff-receipt-summary__code pb-aff-receipt-summary__code--invalid"><?php echo esc_html( $pb_aff_pagbank_account_id ); ?></code></p>
	<?php endif; ?>
	<p class="pb-aff-receipt-actions">
		<a class="button woocommerce-Button pb-aff-btn--secondary" href="<?php echo esc_url( $edit_account_url ); ?>"><?php esc_html_e( 'Editar dados de recebimento', 'pb-affiliates' ); ?></a>
	</p>
<?php else : ?>
	<h3 id="pb-aff-receipt-title"><?php esc_html_e( 'Dados bancários', 'pb-affiliates' ); ?></h3>
	<?php if ( empty( $pb_aff_bank_dashboard_lines ) ) : ?>
		<p><?php esc_html_e( 'Informe banco, agência, conta e CPF/CNPJ em Detalhes da conta para pagamentos manuais.', 'pb-affiliates' ); ?></p>
	<?php else : ?>
		<ul class="pb-aff-receipt-summary-list">
			<?php foreach ( $pb_aff_bank_dashboard_lines as $pb_aff_bank_line ) : ?>
				<li><?php echo esc_html( $pb_aff_bank_line ); ?></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<p class="pb-aff-receipt-actions">
		<a class="button woocommerce-Button pb-aff-btn--secondary" href="<?php echo esc_url( $edit_account_url ); ?>"><?php esc_html_e( 'Editar dados de recebimento', 'pb-affiliates' ); ?></a>
	</p>
<?php endif; ?>
</section>
