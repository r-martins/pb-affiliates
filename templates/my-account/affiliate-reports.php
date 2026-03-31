<?php
/**
 * Relatórios do afiliado: cliques e pedidos atribuídos.
 *
 * @package PB_Affiliates
 * @var int               $user_id           Current user (affiliate).
 * @var int               $clicks_7d         Clicks last 7 days.
 * @var int               $clicks_30d        Clicks last 30 days.
 * @var array             $chart_series      labels, values, days.
 * @var int               $chart_days        Selected period (7|14|30|90).
 * @var array<int, array> $recent_hits       Rows hit_at, via.
 * @var WC_Order[]        $orders            Orders with _pb_affiliate_id.
 * @var int               $clicks_page       Current clicks list page.
 * @var int               $clicks_max_pages  Max pages for clicks.
 * @var int               $clicks_total      Total click events.
 * @var int               $orders_page       Current orders page.
 * @var int               $orders_max_pages  Max pages for orders.
 * @var int               $orders_total      Total attributed orders.
 * @var int               $per_page          Rows per page.
 */

defined( 'ABSPATH' ) || exit;

$pb_aff_nav_active = isset( $pb_aff_nav_active ) ? (string) $pb_aff_nav_active : 'reports';
$pb_aff_materials_url = isset( $pb_aff_materials_url ) ? (string) $pb_aff_materials_url : '';
$pb_aff_has_promo_materials = ! empty( $pb_aff_has_promo_materials );

include PB_AFFILIATES_PATH . 'templates/my-account/parts/affiliate-hub-nav.php';

$recent_hits = isset( $recent_hits ) && is_array( $recent_hits ) ? $recent_hits : array();
$orders      = isset( $orders ) && is_array( $orders ) ? $orders : array();
$user_id     = isset( $user_id ) ? (int) $user_id : get_current_user_id();

$chart_series = isset( $chart_series ) && is_array( $chart_series ) ? $chart_series : array( 'labels' => array(), 'values' => array(), 'order_values' => array(), 'days' => 30 );
$chart_days   = isset( $chart_days ) ? (int) $chart_days : 30;

$clicks_page      = isset( $clicks_page ) ? (int) $clicks_page : 1;
$clicks_max_pages = isset( $clicks_max_pages ) ? (int) $clicks_max_pages : 1;
$clicks_total     = isset( $clicks_total ) ? (int) $clicks_total : 0;

$orders_page      = isset( $orders_page ) ? (int) $orders_page : 1;
$orders_max_pages = isset( $orders_max_pages ) ? (int) $orders_max_pages : 1;
$orders_total     = isset( $orders_total ) ? (int) $orders_total : 0;

$per_page = isset( $per_page ) ? (int) $per_page : 15;

$pb_rep_base = wc_get_account_endpoint_url( 'affiliate-reports' );
/**
 * URL dos relatórios preservando filtros.
 *
 * @param array $args Query args (merge).
 * @return string
 */
$pb_rep_url = function ( array $args ) use ( $pb_rep_base, $chart_days, $clicks_page, $orders_page ) {
	return esc_url(
		add_query_arg(
			array_merge(
				array(
					'pb_ch' => $chart_days,
					'pb_c'  => $clicks_page,
					'pb_o'  => $orders_page,
				),
				$args
			),
			$pb_rep_base
		)
	);
};

$chart_order_values = isset( $chart_series['order_values'] ) ? array_values( array_map( 'intval', (array) $chart_series['order_values'] ) ) : array();
$chart_config       = wp_json_encode(
	array(
		'labels'       => isset( $chart_series['labels'] ) ? array_values( (array) $chart_series['labels'] ) : array(),
		'values'       => isset( $chart_series['values'] ) ? array_values( array_map( 'intval', (array) $chart_series['values'] ) ) : array(),
		'orderValues'  => $chart_order_values,
		'dslabel'      => __( 'Visitas / cliques', 'pb-affiliates' ),
		'orderLabel'   => __( 'Pedidos', 'pb-affiliates' ),
	),
	JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
);
?>
<h2><?php esc_html_e( 'Relatórios', 'pb-affiliates' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Cliques no seu link e pedidos atribuídos ao seu código, em qualquer estado (incluindo aguardando pagamento). A comissão só é lançada de forma definitiva após a confirmação do pagamento.', 'pb-affiliates' ); ?>
</p>

<h3><?php esc_html_e( 'Visitas e cliques', 'pb-affiliates' ); ?></h3>
<ul class="pb-aff-report-summary">
	<li>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: click count */
				__( 'Últimos 7 dias: %d eventos', 'pb-affiliates' ),
				isset( $clicks_7d ) ? (int) $clicks_7d : 0
			)
		);
		?>
	</li>
	<li>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: click count */
				__( 'Últimos 30 dias: %d eventos', 'pb-affiliates' ),
				isset( $clicks_30d ) ? (int) $clicks_30d : 0
			)
		);
		?>
	</li>
</ul>

<div class="pb-aff-chart-toolbar">
	<form method="get" action="<?php echo esc_url( $pb_rep_base ); ?>" class="pb-aff-chart-period">
		<input type="hidden" name="pb_c" value="<?php echo esc_attr( (string) $clicks_page ); ?>" />
		<input type="hidden" name="pb_o" value="<?php echo esc_attr( (string) $orders_page ); ?>" />
		<label for="pb-aff-chart-days"><?php esc_html_e( 'Período do gráfico', 'pb-affiliates' ); ?></label>
		<select name="pb_ch" id="pb-aff-chart-days" onchange="this.form.submit()">
			<option value="7" <?php selected( $chart_days, 7 ); ?>><?php esc_html_e( 'Últimos 7 dias', 'pb-affiliates' ); ?></option>
			<option value="14" <?php selected( $chart_days, 14 ); ?>><?php esc_html_e( 'Últimos 14 dias', 'pb-affiliates' ); ?></option>
			<option value="30" <?php selected( $chart_days, 30 ); ?>><?php esc_html_e( 'Últimos 30 dias', 'pb-affiliates' ); ?></option>
			<option value="90" <?php selected( $chart_days, 90 ); ?>><?php esc_html_e( 'Últimos 90 dias', 'pb-affiliates' ); ?></option>
		</select>
		<noscript><button type="submit" class="button"><?php esc_html_e( 'Aplicar', 'pb-affiliates' ); ?></button></noscript>
	</form>
</div>

<div class="pb-aff-chart-wrap">
	<canvas id="pb-aff-clicks-chart" width="400" height="220" aria-label="<?php esc_attr_e( 'Gráfico de visitas, cliques e pedidos por dia', 'pb-affiliates' ); ?>"></canvas>
</div>
<script type="application/json" id="pb-aff-chart-json"><?php echo $chart_config; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
<script>
window.addEventListener('load', function () {
	var cfgEl = document.getElementById('pb-aff-chart-json');
	var ctx = document.getElementById('pb-aff-clicks-chart');
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
					borderColor: '#2271b1',
					backgroundColor: 'rgba(34, 113, 177, 0.12)',
					fill: true,
					tension: 0.25,
					pointRadius: 3,
					pointHoverRadius: 5
				},
				{
					label: cfg.orderLabel || '',
					data: orderSeries,
					borderColor: '#1e6b3a',
					backgroundColor: 'rgba(30, 107, 58, 0.1)',
					fill: true,
					tension: 0.25,
					pointRadius: 3,
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

<h4><?php esc_html_e( 'Últimos registros', 'pb-affiliates' ); ?></h4>

<?php if ( ! empty( $recent_hits ) ) : ?>
	<table class="shop_table shop_table_responsive pb-aff-report-table pb-aff-report-table--hits">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Data e hora', 'pb-affiliates' ); ?></th>
				<th><?php esc_html_e( 'Origem', 'pb-affiliates' ); ?></th>
				<th><?php esc_html_e( 'IP de origem', 'pb-affiliates' ); ?></th>
				<th><?php esc_html_e( 'URL visitado', 'pb-affiliates' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $recent_hits as $hit ) : ?>
				<?php
				$pb_hit_ip = isset( $hit['client_ip'] ) ? (string) $hit['client_ip'] : '';
				$pb_hit_url = isset( $hit['visited_url'] ) ? (string) $hit['visited_url'] : '';
				$pb_hit_url_short = $pb_hit_url;
				if ( function_exists( 'mb_strlen' ) && mb_strlen( $pb_hit_url_short ) > 56 ) {
					$pb_hit_url_short = mb_substr( $pb_hit_url_short, 0, 56 ) . '…';
				} elseif ( strlen( $pb_hit_url_short ) > 56 ) {
					$pb_hit_url_short = substr( $pb_hit_url_short, 0, 56 ) . '…';
				}
				?>
				<tr>
					<td data-title="<?php esc_attr_e( 'Data e hora', 'pb-affiliates' ); ?>">
						<?php echo esc_html( isset( $hit['hit_at'] ) ? (string) $hit['hit_at'] : '' ); ?>
					</td>
					<td data-title="<?php esc_attr_e( 'Origem', 'pb-affiliates' ); ?>">
						<code><?php echo esc_html( isset( $hit['via'] ) ? (string) $hit['via'] : '' ); ?></code>
					</td>
					<td data-title="<?php esc_attr_e( 'IP de origem', 'pb-affiliates' ); ?>">
						<code class="pb-aff-hit-ip"><?php echo esc_html( $pb_hit_ip ); ?></code>
					</td>
					<td data-title="<?php esc_attr_e( 'URL visitado', 'pb-affiliates' ); ?>" class="pb-aff-hit-url-cell">
						<?php if ( $pb_hit_url !== '' ) : ?>
							<a href="<?php echo esc_url( $pb_hit_url ); ?>" target="_blank" rel="noopener noreferrer" class="pb-aff-hit-url" title="<?php echo esc_attr( $pb_hit_url ); ?>"><?php echo esc_html( $pb_hit_url_short ); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $clicks_max_pages > 1 ) : ?>
		<nav class="pb-aff-pagination pb-aff-pagination--clicks" aria-label="<?php esc_attr_e( 'Paginação dos registros de clique', 'pb-affiliates' ); ?>">
			<p class="pb-aff-pagination__meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: current page, 2: total pages, 3: total items */
						__( 'Página %1$d de %2$d (%3$d registros)', 'pb-affiliates' ),
						$clicks_page,
						$clicks_max_pages,
						$clicks_total
					)
				);
				?>
			</p>
			<div class="pb-aff-pagination__links">
				<?php if ( $clicks_page > 1 ) : ?>
					<a class="button" href="<?php echo esc_url( $pb_rep_url( array( 'pb_c' => $clicks_page - 1 ) ) ); ?>"><?php esc_html_e( 'Anterior', 'pb-affiliates' ); ?></a>
				<?php endif; ?>
				<?php if ( $clicks_page < $clicks_max_pages ) : ?>
					<a class="button" href="<?php echo esc_url( $pb_rep_url( array( 'pb_c' => $clicks_page + 1 ) ) ); ?>"><?php esc_html_e( 'Próximo', 'pb-affiliates' ); ?></a>
				<?php endif; ?>
			</div>
		</nav>
	<?php endif; ?>
<?php else : ?>
	<p class="pb-aff-report-empty"><?php esc_html_e( 'Ainda não há eventos de clique registrados desde a ativação do histórico.', 'pb-affiliates' ); ?></p>
<?php endif; ?>

<h3><?php esc_html_e( 'Pedidos com o seu código', 'pb-affiliates' ); ?></h3>

<?php if ( ! empty( $orders ) ) : ?>
	<table class="shop_table shop_table_responsive pb-aff-report-table pb-aff-report-table--orders">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Pedido', 'pb-affiliates' ); ?></th>
				<th><?php esc_html_e( 'Data', 'pb-affiliates' ); ?></th>
				<th><?php esc_html_e( 'Estado', 'pb-affiliates' ); ?></th>
				<th><?php esc_html_e( 'Total', 'pb-affiliates' ); ?></th>
				<th><?php esc_html_e( 'Comissão', 'pb-affiliates' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $orders as $pb_aff_order ) {
				if ( ! $pb_aff_order instanceof WC_Order ) {
					continue;
				}
				$pb_aff_oid     = $pb_aff_order->get_id();
				$pb_aff_row     = PB_Affiliates_Reports::get_commission_row_for_order( $pb_aff_oid, $user_id );
				$pb_aff_preview = PB_Affiliates_Commission::calculate_commission_preview_for_order( $pb_aff_order, $user_id );
				$pb_aff_comm_label = '';
				if ( $pb_aff_row && isset( $pb_aff_row->commission_amount ) ) {
					$pb_aff_comm_label = wp_kses_post( wc_price( (float) $pb_aff_row->commission_amount, array( 'currency' => $pb_aff_order->get_currency() ) ) );
					$pb_aff_st         = isset( $pb_aff_row->status ) ? (string) $pb_aff_row->status : '';
					if ( 'paid' === $pb_aff_st ) {
						$pb_aff_comm_label .= ' <span class="pb-aff-badge pb-aff-badge--paid">' . esc_html__( 'Paga', 'pb-affiliates' ) . '</span>';
					} elseif ( 'pending' === $pb_aff_st ) {
						$pb_aff_comm_label .= ' <span class="pb-aff-badge pb-aff-badge--pending">' . esc_html__( 'Pendente', 'pb-affiliates' ) . '</span>';
					}
				} elseif ( $pb_aff_preview && $pb_aff_preview['amount'] > 0 ) {
					$pb_aff_comm_label  = wp_kses_post( wc_price( $pb_aff_preview['amount'], array( 'currency' => $pb_aff_order->get_currency() ) ) );
					$pb_aff_comm_label .= ' <span class="pb-aff-badge pb-aff-badge--estimate">' . esc_html__( 'Pedido pendente', 'pb-affiliates' ) . '</span>';
				} elseif ( $pb_aff_preview && $pb_aff_preview['amount'] <= 0 ) {
					$pb_aff_comm_label = '—';
				} else {
					$pb_aff_comm_label = '—';
				}
				?>
			<tr>
				<td data-title="<?php esc_attr_e( 'Pedido', 'pb-affiliates' ); ?>">
					#<?php echo esc_html( (string) $pb_aff_oid ); ?>
				</td>
				<td data-title="<?php esc_attr_e( 'Data', 'pb-affiliates' ); ?>">
					<?php echo esc_html( $pb_aff_order->get_date_created() ? $pb_aff_order->get_date_created()->date_i18n( wc_date_format() ) : '' ); ?>
				</td>
				<td data-title="<?php esc_attr_e( 'Estado', 'pb-affiliates' ); ?>">
					<?php echo esc_html( wc_get_order_status_name( $pb_aff_order->get_status() ) ); ?>
				</td>
				<td data-title="<?php esc_attr_e( 'Total', 'pb-affiliates' ); ?>">
					<?php echo wp_kses_post( $pb_aff_order->get_formatted_order_total() ); ?>
				</td>
				<td data-title="<?php esc_attr_e( 'Comissão', 'pb-affiliates' ); ?>">
					<?php echo wp_kses_post( $pb_aff_comm_label ); ?>
				</td>
			</tr>
				<?php
			}
			?>
		</tbody>
	</table>
	<?php if ( $orders_max_pages > 1 ) : ?>
		<nav class="pb-aff-pagination pb-aff-pagination--orders" aria-label="<?php esc_attr_e( 'Paginação dos pedidos', 'pb-affiliates' ); ?>">
			<p class="pb-aff-pagination__meta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: current page, 2: total pages, 3: total orders */
						__( 'Página %1$d de %2$d (%3$d pedidos)', 'pb-affiliates' ),
						$orders_page,
						$orders_max_pages,
						$orders_total
					)
				);
				?>
			</p>
			<div class="pb-aff-pagination__links">
				<?php if ( $orders_page > 1 ) : ?>
					<a class="button" href="<?php echo esc_url( $pb_rep_url( array( 'pb_o' => $orders_page - 1 ) ) ); ?>"><?php esc_html_e( 'Anterior', 'pb-affiliates' ); ?></a>
				<?php endif; ?>
				<?php if ( $orders_page < $orders_max_pages ) : ?>
					<a class="button" href="<?php echo esc_url( $pb_rep_url( array( 'pb_o' => $orders_page + 1 ) ) ); ?>"><?php esc_html_e( 'Próximo', 'pb-affiliates' ); ?></a>
				<?php endif; ?>
			</div>
		</nav>
	<?php endif; ?>
<?php else : ?>
	<p class="pb-aff-report-empty"><?php esc_html_e( 'Ainda não há pedidos atribuídos ao seu código.', 'pb-affiliates' ); ?></p>
<?php endif; ?>

<p class="pb-aff-report-back">
	<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'affiliate-area' ) ); ?>" class="button"><?php esc_html_e( '← Área do afiliado', 'pb-affiliates' ); ?></a>
</p>
