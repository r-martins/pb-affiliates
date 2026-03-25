<?php
/**
 * Admin: relatório de cliques (gráficos).
 *
 * @package PB_Affiliates
 *
 * @var array                  $ranges           Intervalos (WC).
 * @var string                 $current_range    Intervalo ativo.
 * @var int                    $aff_id           Filtro afiliado (0 = todos).
 * @var string                 $pb_order_status  Filtro de estado do pedido (vazio = todos).
 * @var array                  $wc_order_status_choices slug => etiqueta (WooCommerce).
 * @var array                  $affiliates       Lista de usuários WP_User parciais.
 * @var string                 $chart_data_json  JSON série temporal.
 * @var string                 $pie_series_json  JSON fatias pie (via).
 * @var string                 $referer_domain_bubble_series_json Bolhas: h, label, clicks, orders.
 * @var array                  $referer_domain_bubble_rows         Mesmos dados (PHP + tabela).
 * @var int                    $total_clicks      Soma de cliques no período.
 * @var int                    $orders_in_range   Pedidos com afiliado no período.
 * @var float                  $orders_total_in_range Soma dos totais dos pedidos (get_total).
 * @var float                  $report_commission_total Soma das comissões (tabela/meta/preview) dos pedidos no período.
 * @var float                  $report_commission_available Soma de comissões pendentes com repasse manual na tabela, mesmos pedidos.
 * @var array                  $order_chart_money currency symbol/decimals for chart axis.
 * @var bool                   $hide_empty_referer Ocultar bucket sem host Referer (interruptor na secção de domínio).
 * @var string                 $ref_sort         Lista domínios: ordenar por clicks|orders|rate.
 * @var string                 $ref_dir          asc|desc.
 * @var int                    $ref_paged        Página atual da lista (1-based).
 * @var int                    $ref_per_page     Linhas por página.
 * @var array                  $pb_report_nav_base Query args base (page, range, filtros) para URLs das tabelas.
 * @var array                  $referer_domain_table_rows Linhas da página atual (domínio, cliques, pedidos, taxa).
 * @var int                    $referer_domain_table_total Total de linhas (todos os domínios).
 * @var int                    $referer_domain_table_total_pages Total de páginas.
 * @var string                 $aff_sort Ordenar tabela afiliados: clicks|orders|rate.
 * @var string                 $aff_dir asc|desc.
 * @var int                    $aff_paged Página da tabela afiliados.
 * @var int                    $aff_per_page Linhas por página na tabela de desempenho por afiliado (predefinição 10).
 * @var array                  $affiliate_perf_table_rows Linhas afiliado: name, code, clicks, orders, rate_pct, detail_url…
 * @var int                    $affiliate_perf_table_total Total de afiliados com dados.
 * @var int                    $affiliate_perf_table_total_pages Total de páginas.
 * @var string                 $ord_sort Ordenação pedidos no período: order_id|date|status|affiliate|commission.
 * @var string                 $ord_dir asc|desc.
 * @var int                    $ord_paged Página da tabela de pedidos.
 * @var array                  $period_orders_rows Linhas: order_id, order_number, edit_url, date_display, status_label, affiliate_*, commission.
 * @var int                    $period_orders_total Total de pedidos listados.
 * @var int                    $period_orders_total_pages Total de páginas.
 * @var PB_Affiliates_Admin_Click_Report $this Relatório (intervalo, groupby).
 */

defined( 'ABSPATH' ) || exit;

global $wp_locale;

?>
<div class="wrap pb-aff-wrap pb-aff-click-report woocommerce">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Relatórios de afiliados', 'pb-affiliates' ); ?></h1>
	<hr class="wp-header-end" />

	<div id="poststuff" class="woocommerce-reports-wide">
		<div class="postbox">
			<?php if ( 'custom' === $current_range && isset( $_GET['start_date'], $_GET['end_date'] ) ) : ?>
				<h3 class="screen-reader-text">
					<?php
					printf(
						/* translators: 1: start date 2: end date */
						esc_html__( 'From %1$s to %2$s', 'woocommerce' ),
						esc_html( wc_clean( wp_unslash( $_GET['start_date'] ) ) ),
						esc_html( wc_clean( wp_unslash( $_GET['end_date'] ) ) )
					);
					?>
				</h3>
			<?php else : ?>
				<h3 class="screen-reader-text"><?php echo esc_html( $ranges[ $current_range ] ); ?></h3>
			<?php endif; ?>

			<div class="stats_range">
				<ul>
					<?php foreach ( $ranges as $range => $name ) : ?>
						<?php
						$range_args = array(
							'page'  => 'pb-affiliates-report',
							'range' => $range,
						);
						if ( $aff_id > 0 ) {
							$range_args['pb_affiliate'] = $aff_id;
						}
						if ( '' !== $pb_order_status ) {
							$range_args['pb_order_status'] = $pb_order_status;
						}
						if ( ! empty( $hide_empty_referer ) ) {
							$range_args['pb_hide_empty_referer'] = '1';
						}
						$range_args['pb_ref_sort'] = $ref_sort;
						$range_args['pb_ref_dir']  = $ref_dir;
						$range_args['pb_aff_sort'] = $aff_sort;
						$range_args['pb_aff_dir']  = $aff_dir;
						$range_args['pb_ord_sort'] = $ord_sort;
						$range_args['pb_ord_dir']  = $ord_dir;
						$href = add_query_arg( $range_args, admin_url( 'admin.php' ) );
						?>
						<li class="<?php echo $current_range === $range ? 'active' : ''; ?>">
							<a href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $name ); ?></a>
						</li>
					<?php endforeach; ?>
					<li class="custom <?php echo ( 'custom' === $current_range ) ? 'active' : ''; ?>">
						<?php esc_html_e( 'Custom:', 'woocommerce' ); ?>
						<form method="get">
							<div>
								<?php
								// phpcs:ignore WordPress.Security.NonceVerification.Recommended
								foreach ( $_GET as $key => $value ) {
									if ( in_array( $key, array( 'range', 'start_date', 'end_date', 'wc_reports_nonce', 'pb_ref_paged', 'pb_aff_paged', 'pb_ord_paged' ), true ) ) {
										continue;
									}
									if ( is_array( $value ) ) {
										foreach ( $value as $v ) {
											echo '<input type="hidden" name="' . esc_attr( sanitize_text_field( $key ) ) . '[]" value="' . esc_attr( sanitize_text_field( $v ) ) . '" />';
										}
									} else {
										echo '<input type="hidden" name="' . esc_attr( sanitize_text_field( $key ) ) . '" value="' . esc_attr( sanitize_text_field( $value ) ) . '" />';
									}
								}
								?>
								<input type="hidden" name="page" value="pb-affiliates-report" />
								<input type="hidden" name="range" value="custom" />
								<input type="text" size="11" placeholder="yyyy-mm-dd" value="<?php echo ( ! empty( $_GET['start_date'] ) ) ? esc_attr( wp_unslash( $_GET['start_date'] ) ) : ''; ?>" name="start_date" class="range_datepicker from" autocomplete="off" />
								<span>&ndash;</span>
								<input type="text" size="11" placeholder="yyyy-mm-dd" value="<?php echo ( ! empty( $_GET['end_date'] ) ) ? esc_attr( wp_unslash( $_GET['end_date'] ) ) : ''; ?>" name="end_date" class="range_datepicker to" autocomplete="off" />
								<button type="submit" class="button" value="<?php esc_attr_e( 'Go', 'woocommerce' ); ?>"><?php esc_html_e( 'Go', 'woocommerce' ); ?></button>
								<?php wp_nonce_field( 'custom_range', 'wc_reports_nonce', false ); ?>
							</div>
						</form>
					</li>
				</ul>
			</div>

			<div class="inside" style="padding-top: 1em;">
				<form method="get" class="pb-aff-click-report__filters">
					<input type="hidden" name="page" value="pb-affiliates-report" />
					<input type="hidden" name="range" value="<?php echo esc_attr( $current_range ); ?>" />
					<?php if ( 'custom' === $current_range && ! empty( $_GET['start_date'] ) && ! empty( $_GET['end_date'] ) ) : ?>
						<input type="hidden" name="start_date" value="<?php echo esc_attr( wc_clean( wp_unslash( $_GET['start_date'] ) ) ); ?>" />
						<input type="hidden" name="end_date" value="<?php echo esc_attr( wc_clean( wp_unslash( $_GET['end_date'] ) ) ); ?>" />
						<?php wp_nonce_field( 'custom_range', 'wc_reports_nonce', false ); ?>
					<?php endif; ?>

					<label for="pb_affiliate"><?php esc_html_e( 'Afiliado', 'pb-affiliates' ); ?></label>
					<select name="pb_affiliate" id="pb_affiliate">
						<option value="0"><?php esc_html_e( 'Todos', 'pb-affiliates' ); ?></option>
						<?php foreach ( $affiliates as $u ) : ?>
							<option value="<?php echo (int) $u->ID; ?>" <?php selected( $aff_id, (int) $u->ID ); ?>>
								<?php echo esc_html( $u->display_name . ' (#' . $u->ID . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label for="pb_order_status"><?php esc_html_e( 'Estado do pedido', 'pb-affiliates' ); ?></label>
					<select name="pb_order_status" id="pb_order_status">
						<option value="" <?php selected( $pb_order_status, '' ); ?>><?php esc_html_e( 'Todos os status', 'pb-affiliates' ); ?></option>
						<?php foreach ( $wc_order_status_choices as $st_slug => $st_label ) : ?>
							<option value="<?php echo esc_attr( $st_slug ); ?>" <?php selected( $pb_order_status, $st_slug ); ?>>
								<?php echo esc_html( $st_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if ( ! empty( $hide_empty_referer ) ) : ?>
						<input type="hidden" name="pb_hide_empty_referer" value="1" />
					<?php endif; ?>
					<input type="hidden" name="pb_ref_sort" value="<?php echo esc_attr( $ref_sort ); ?>" />
					<input type="hidden" name="pb_ref_dir" value="<?php echo esc_attr( $ref_dir ); ?>" />
					<input type="hidden" name="pb_aff_sort" value="<?php echo esc_attr( $aff_sort ); ?>" />
					<input type="hidden" name="pb_aff_dir" value="<?php echo esc_attr( $aff_dir ); ?>" />
					<input type="hidden" name="pb_ord_sort" value="<?php echo esc_attr( $ord_sort ); ?>" />
					<input type="hidden" name="pb_ord_dir" value="<?php echo esc_attr( $ord_dir ); ?>" />

					<button type="submit" class="button button-primary"><?php esc_html_e( 'Aplicar filtros', 'pb-affiliates' ); ?></button>
				</form>

				<p class="pb-aff-click-report__summary">
					<strong><?php esc_html_e( 'Neste período:', 'pb-affiliates' ); ?></strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: number of clicks 2: number of orders */
							__( '%1$s cliques registrados · %2$s pedidos com código de afiliado', 'pb-affiliates' ),
							number_format_i18n( (int) $total_clicks ),
							number_format_i18n( (int) $orders_in_range )
						)
					);
					?>
					<?php echo ' · '; ?>
					<?php esc_html_e( 'Valor total dos pedidos:', 'pb-affiliates' ); ?>
					<?php echo ' '; ?>
					<?php echo wp_kses_post( wc_price( $orders_total_in_range ) ); ?>
					<?php echo ' · '; ?>
					<?php esc_html_e( 'Total de comissões:', 'pb-affiliates' ); ?>
					<?php echo ' '; ?>
					<?php echo wp_kses_post( wc_price( $report_commission_total ) ); ?>
					<?php echo ' · '; ?>
					<?php esc_html_e( 'Comissões disponíveis (pendentes, repasse manual):', 'pb-affiliates' ); ?>
					<?php echo ' '; ?>
					<?php echo wp_kses_post( wc_price( $report_commission_available ) ); ?>
				</p>

				<h3 class="pb-aff-chart-heading"><?php esc_html_e( 'Evolução: cliques no link e pedidos atribuídos', 'pb-affiliates' ); ?></h3>
				<p class="description" style="margin-top:-0.25em">
					<?php esc_html_e( 'A série "Cliques" usa apenas visitas registradas no log (URL, referer, etc.). "Pedidos" e "Valor dos pedidos" incluem qualquer atribuição ao programa (cupom com ID do afiliado ou cupom cujo código é igual ao código público do afiliado, cookie, referer). Os pedidos nas restantes secções respeitam o filtro "Estado do pedido" quando não está em "Todos os status".', 'pb-affiliates' ); ?>
				</p>
				<div class="chart-container">
					<div class="chart-placeholder main pb-aff-click-line" style="height:360px;"></div>
				</div>

				<div class="pb-aff-click-report__pies">
					<div class="pb-aff-click-report__pie-col pb-aff-click-report__pie-col--via">
						<h3 class="pb-aff-chart-heading pb-aff-chart-heading--spaced"><?php esc_html_e( 'Origem dos cliques', 'pb-affiliates' ); ?></h3>
						<details class="pb-aff-pie-help-details">
							<summary class="pb-aff-pie-help-summary"><?php esc_html_e( 'Como a origem dos cliques é definida', 'pb-affiliates' ); ?></summary>
							<div class="description pb-aff-pie-help">
								<p class="pb-aff-pie-help-intro">
									<?php esc_html_e( 'A origem corresponde ao valor salvo quando o cookie de afiliado é definido:', 'pb-affiliates' ); ?>
								</p>
								<ul class="pb-aff-pie-help-list">
									<li>
										<strong><?php esc_html_e( 'Parâmetro na URL', 'pb-affiliates' ); ?></strong>
										<?php
										echo ' ';
										esc_html_e( 'o cliente visitou a loja com o parâmetro de indicação na URL (por padrão ?pid=, configurável nas configurações do plugin).', 'pb-affiliates' );
										?>
									</li>
									<li>
										<strong><?php esc_html_e( 'Domínio verificado (referer)', 'pb-affiliates' ); ?></strong>
										<?php
										echo ' ';
										esc_html_e( 'o cliente veio de um site cujo domínio o afiliado validou na área Domínio de referência em Minha conta, e o navegador enviou o cabeçalho HTTP Referer.', 'pb-affiliates' );
										?>
									</li>
								</ul>
								<p class="pb-aff-pie-help-outro">
									<?php esc_html_e( 'O afiliado não escolhe manualmente a origem; isso depende de como o visitante chega ao site.', 'pb-affiliates' ); ?>
								</p>
							</div>
						</details>
						<div class="chart-container" style="max-width:520px;">
							<div class="chart-placeholder pb-aff-click-pie pie-chart" style="height:260px;"></div>
							<ul class="pie-chart-legend pb-aff-pie-legend" id="pb-aff-pie-legend" aria-label="<?php esc_attr_e( 'Legenda: origem dos cliques com totais', 'pb-affiliates' ); ?>"></ul>
						</div>
					</div>
					<div class="pb-aff-click-report__pie-col pb-aff-click-report__pie-col--domain">
						<?php
						$pb_ref_report_args = array(
							'page'  => 'pb-affiliates-report',
							'range' => $current_range,
						);
						if ( $aff_id > 0 ) {
							$pb_ref_report_args['pb_affiliate'] = $aff_id;
						}
						if ( '' !== $pb_order_status ) {
							$pb_ref_report_args['pb_order_status'] = $pb_order_status;
						}
						if ( 'custom' === $current_range && ! empty( $_GET['start_date'] ) && ! empty( $_GET['end_date'] ) ) {
							$pb_ref_report_args['start_date'] = wc_clean( wp_unslash( $_GET['start_date'] ) );
							$pb_ref_report_args['end_date']   = wc_clean( wp_unslash( $_GET['end_date'] ) );
							if ( isset( $_GET['wc_reports_nonce'] ) ) {
								$pb_ref_report_args['wc_reports_nonce'] = sanitize_text_field( wp_unslash( $_GET['wc_reports_nonce'] ) );
							}
						}
						$pb_ref_report_args['pb_ref_sort'] = $ref_sort;
						$pb_ref_report_args['pb_ref_dir']  = $ref_dir;
						$pb_ref_report_args['pb_aff_sort'] = $aff_sort;
						$pb_ref_report_args['pb_aff_dir']  = $aff_dir;
						$pb_ref_report_args['pb_ord_sort'] = $ord_sort;
						$pb_ref_report_args['pb_ord_dir']  = $ord_dir;
						$pb_ref_report_url_base = add_query_arg( $pb_ref_report_args, admin_url( 'admin.php' ) );
						$pb_ref_url_hide_on     = add_query_arg( 'pb_hide_empty_referer', '1', $pb_ref_report_url_base );
						$pb_ref_url_hide_off    = remove_query_arg( 'pb_hide_empty_referer', $pb_ref_report_url_base );
						$pb_ref_switch_href     = ! empty( $hide_empty_referer ) ? $pb_ref_url_hide_off : $pb_ref_url_hide_on;
						$pb_ref_switch_on       = ! empty( $hide_empty_referer );
						?>
						<div class="pb-aff-ref-domain-toolbar pb-aff-chart-heading--spaced">
							<h3 class="pb-aff-chart-heading"><?php esc_html_e( 'Domínio do visitante (cliques × pedidos)', 'pb-affiliates' ); ?></h3>
							<div class="pb-aff-ref-hide-switch-wrap">
								<span id="pb-aff-ref-hide-label" class="pb-aff-ref-hide-switch-label"><?php esc_html_e( 'Ocultar sem HTTP Referer', 'pb-affiliates' ); ?></span>
								<a
									href="<?php echo esc_url( $pb_ref_switch_href ); ?>"
									class="pb-aff-ref-hide-switch<?php echo $pb_ref_switch_on ? ' is-on' : ''; ?>"
									role="switch"
									aria-checked="<?php echo $pb_ref_switch_on ? 'true' : 'false'; ?>"
									aria-labelledby="pb-aff-ref-hide-label"
								><span class="pb-aff-ref-hide-switch__thumb" aria-hidden="true"></span></a>
							</div>
						</div>
						<details class="pb-aff-pie-help-details">
							<summary class="pb-aff-pie-help-summary"><?php esc_html_e( 'Como ler o gráfico e a tabela', 'pb-affiliates' ); ?></summary>
							<div class="description pb-aff-pie-help">
								<p class="pb-aff-pie-help-intro">
									<?php esc_html_e( 'Cada bolha é um domínio (host do HTTP Referer no log). Posição: quanto mais à direita, mais cliques no período; quanto mais acima, mais pedidos com esse host na meta do pedido. O tamanho da bolha combina os dois (maior = mais tráfego e/ou mais vendas).', 'pb-affiliates' ); ?>
								</p>
								<ul class="pb-aff-pie-help-list">
									<li>
										<?php esc_html_e( 'Até 15 domínios com atividade no período (prioridade para quem tem mais pedidos; entram também sites com muitos cliques).', 'pb-affiliates' ); ?>
									</li>
									<li>
										<?php esc_html_e( 'À direita do gráfico, a tabela lista todos os domínios no período (com paginação), com taxa de conversão (pedidos ÷ cliques) e ordenação por coluna.', 'pb-affiliates' ); ?>
									</li>
									<li>
										<?php esc_html_e( 'Passe o rato sobre uma bolha para ver o domínio e os totais.', 'pb-affiliates' ); ?>
									</li>
									<li>
										<?php esc_html_e( 'Pedidos só gravam domínio quando o cookie (mesmo afiliado) traz referer_host; cupom de outro afiliado não copia o domínio do cookie.', 'pb-affiliates' ); ?>
									</li>
								</ul>
							</div>
						</details>
						<?php if ( empty( $referer_domain_bubble_rows ) ) : ?>
							<p class="description"><?php esc_html_e( 'Não há cliques nem pedidos com domínio de referência neste período (ou com o filtro de afiliado).', 'pb-affiliates' ); ?></p>
						<?php else : ?>
							<p class="description" style="margin:0.35em 0 0">
								<?php esc_html_e( 'Eixo X: cliques · Eixo Y: pedidos · Tamanho da bolha ~ volume total.', 'pb-affiliates' ); ?>
							</p>
							<?php
							$pb_ref_sort_link = static function ( $col ) use ( $pb_report_nav_base, $ref_sort, $ref_dir ) {
								return PB_Affiliates_Admin_Click_Report::admin_referer_domain_sort_url( $pb_report_nav_base, $col, $ref_sort, $ref_dir );
							};
							$pb_ref_sort_mark = static function ( $col ) use ( $ref_sort, $ref_dir ) {
								if ( $col !== $ref_sort ) {
									return '';
								}
								return 'desc' === $ref_dir
									? ' <span class="sort-ind" aria-hidden="true">↓</span>'
									: ' <span class="sort-ind" aria-hidden="true">↑</span>';
							};
							$pb_ref_pag_url = remove_query_arg( 'pb_ref_paged', add_query_arg( $pb_report_nav_base, admin_url( 'admin.php' ) ) );
							$pb_ref_pagination = '';
							if ( $referer_domain_table_total_pages > 1 ) {
								$pb_ref_pagination = paginate_links(
									array(
										'base'      => add_query_arg( 'pb_ref_paged', '%#%', $pb_ref_pag_url ),
										'format'    => '',
										'current'   => $ref_paged,
										'total'     => $referer_domain_table_total_pages,
										'type'      => 'list',
										'prev_text' => '&laquo;',
										'next_text' => '&raquo;',
									)
								);
							}
							?>
							<div class="pb-aff-ref-domain-split">
								<div class="pb-aff-ref-domain-split__chart">
									<div class="chart-container" style="margin-top:8px;">
										<div class="chart-placeholder pb-aff-ref-domain-bubble" style="height:400px;"></div>
									</div>
								</div>
								<div class="pb-aff-ref-domain-split__table">
									<table class="widefat striped pb-aff-ref-domain-table">
										<thead>
											<tr>
												<th scope="col"><?php esc_html_e( 'Domínio', 'pb-affiliates' ); ?></th>
												<th scope="col" class="num sortable">
													<a href="<?php echo esc_url( call_user_func( $pb_ref_sort_link, 'clicks' ) ); ?>">
														<?php esc_html_e( 'Cliques', 'pb-affiliates' ); ?>
														<?php echo wp_kses_post( call_user_func( $pb_ref_sort_mark, 'clicks' ) ); ?>
													</a>
												</th>
												<th scope="col" class="num sortable">
													<a href="<?php echo esc_url( call_user_func( $pb_ref_sort_link, 'orders' ) ); ?>">
														<?php esc_html_e( 'Pedidos', 'pb-affiliates' ); ?>
														<?php echo wp_kses_post( call_user_func( $pb_ref_sort_mark, 'orders' ) ); ?>
													</a>
												</th>
												<th scope="col" class="num sortable">
													<a href="<?php echo esc_url( call_user_func( $pb_ref_sort_link, 'rate' ) ); ?>">
														<?php esc_html_e( 'Conversão', 'pb-affiliates' ); ?>
														<?php echo wp_kses_post( call_user_func( $pb_ref_sort_mark, 'rate' ) ); ?>
													</a>
												</th>
											</tr>
										</thead>
										<tbody>
											<?php if ( empty( $referer_domain_table_rows ) ) : ?>
												<tr>
													<td colspan="4"><?php esc_html_e( 'Nenhuma linha nesta página.', 'pb-affiliates' ); ?></td>
												</tr>
											<?php else : ?>
												<?php foreach ( $referer_domain_table_rows as $tr ) : ?>
													<tr>
														<td><?php echo esc_html( $tr['label'] ); ?></td>
														<td class="num"><?php echo esc_html( number_format_i18n( (int) $tr['clicks'] ) ); ?></td>
														<td class="num"><?php echo esc_html( number_format_i18n( (int) $tr['orders'] ) ); ?></td>
														<td class="num">
															<?php
															if ( null === $tr['rate_pct'] ) {
																echo esc_html( '—' );
															} else {
																echo esc_html( wc_format_decimal( $tr['rate_pct'], 2 ) . '%' );
															}
															?>
														</td>
													</tr>
												<?php endforeach; ?>
											<?php endif; ?>
										</tbody>
									</table>
									<?php if ( $pb_ref_pagination ) : ?>
										<nav class="pb-aff-ref-domain-pager" aria-label="<?php esc_attr_e( 'Paginação da lista de domínios', 'pb-affiliates' ); ?>">
											<?php
											// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML.
											echo $pb_ref_pagination;
											?>
										</nav>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="pb-aff-bottom-tables-row">
				<div class="pb-aff-affiliate-perf-section pb-aff-bottom-tables-row__col">
					<h3 class="pb-aff-chart-heading pb-aff-chart-heading--spaced"><?php esc_html_e( 'Desempenho por afiliado', 'pb-affiliates' ); ?></h3>
					<details class="pb-aff-pie-help-details">
						<summary class="pb-aff-pie-help-summary"><?php esc_html_e( 'Sobre esta tabela', 'pb-affiliates' ); ?></summary>
						<div class="description pb-aff-pie-help">
							<p class="pb-aff-pie-help-intro">
								<?php esc_html_e( 'Cada linha é um afiliado (utilizador com programa ativo). Os cliques vêm do registo de visitas atribuídas a esse afiliado; os pedidos são encomendas com meta de atribuição ao mesmo ID no período.', 'pb-affiliates' ); ?>
							</p>
							<ul class="pb-aff-pie-help-list">
								<li>
									<?php esc_html_e( 'A conversão é pedidos ÷ cliques; mostramos “—” quando não houve cliques registados no período.', 'pb-affiliates' ); ?>
								</li>
								<li>
									<?php esc_html_e( 'Respeita o mesmo intervalo de datas e o filtro “Afiliado” acima (um afiliado ou todos).', 'pb-affiliates' ); ?>
								</li>
							</ul>
						</div>
					</details>
					<?php if ( $affiliate_perf_table_total < 1 ) : ?>
						<p class="description">
							<?php esc_html_e( 'Não há cliques nem pedidos atribuídos a afiliados neste período (com os filtros atuais).', 'pb-affiliates' ); ?>
						</p>
					<?php else : ?>
						<?php
						$pb_aff_sort_link = static function ( $col ) use ( $pb_report_nav_base, $aff_sort, $aff_dir ) {
							return PB_Affiliates_Admin_Click_Report::admin_report_sort_url( $pb_report_nav_base, $col, $aff_sort, $aff_dir, 'pb_aff' );
						};
						$pb_aff_sort_mark = static function ( $col ) use ( $aff_sort, $aff_dir ) {
							if ( $col !== $aff_sort ) {
								return '';
							}
							return 'desc' === $aff_dir
								? ' <span class="sort-ind" aria-hidden="true">↓</span>'
								: ' <span class="sort-ind" aria-hidden="true">↑</span>';
						};
						$pb_aff_pag_url = remove_query_arg( 'pb_aff_paged', add_query_arg( $pb_report_nav_base, admin_url( 'admin.php' ) ) );
						$pb_aff_pagination = '';
						if ( $affiliate_perf_table_total_pages > 1 ) {
							$pb_aff_pagination = paginate_links(
								array(
									'base'      => add_query_arg( 'pb_aff_paged', '%#%', $pb_aff_pag_url ),
									'format'    => '',
									'current'   => $aff_paged,
									'total'     => $affiliate_perf_table_total_pages,
									'type'      => 'list',
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								)
							);
						}
						?>
						<table class="widefat striped pb-aff-ref-domain-table" style="width:100%;margin-top:10px">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Afiliado', 'pb-affiliates' ); ?></th>
									<th scope="col" class="num sortable">
										<a href="<?php echo esc_url( call_user_func( $pb_aff_sort_link, 'clicks' ) ); ?>">
											<?php esc_html_e( 'Cliques', 'pb-affiliates' ); ?>
											<?php echo wp_kses_post( call_user_func( $pb_aff_sort_mark, 'clicks' ) ); ?>
										</a>
									</th>
									<th scope="col" class="num sortable">
										<a href="<?php echo esc_url( call_user_func( $pb_aff_sort_link, 'orders' ) ); ?>">
											<?php esc_html_e( 'Pedidos', 'pb-affiliates' ); ?>
											<?php echo wp_kses_post( call_user_func( $pb_aff_sort_mark, 'orders' ) ); ?>
										</a>
									</th>
									<th scope="col" class="num sortable">
										<a href="<?php echo esc_url( call_user_func( $pb_aff_sort_link, 'rate' ) ); ?>">
											<?php esc_html_e( 'Conversão', 'pb-affiliates' ); ?>
											<?php echo wp_kses_post( call_user_func( $pb_aff_sort_mark, 'rate' ) ); ?>
										</a>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $affiliate_perf_table_rows as $ar ) : ?>
									<tr>
										<td>
											<?php if ( ! empty( $ar['detail_url'] ) ) : ?>
												<strong><a class="pb-aff-aff-perf-name-link" href="<?php echo esc_url( $ar['detail_url'] ); ?>"><?php echo esc_html( $ar['name'] ); ?></a></strong>
											<?php else : ?>
												<strong><?php echo esc_html( $ar['name'] ); ?></strong>
											<?php endif; ?>
											<?php if ( '' !== $ar['code'] ) : ?>
												<br /><code class="pb-aff-aff-code"><?php echo esc_html( $ar['code'] ); ?></code>
											<?php endif; ?>
										</td>
										<td class="num"><?php echo esc_html( number_format_i18n( (int) $ar['clicks'] ) ); ?></td>
										<td class="num"><?php echo esc_html( number_format_i18n( (int) $ar['orders'] ) ); ?></td>
										<td class="num">
											<?php
											if ( null === $ar['rate_pct'] ) {
												echo esc_html( '—' );
											} else {
												echo esc_html( wc_format_decimal( $ar['rate_pct'], 2 ) . '%' );
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php if ( $pb_aff_pagination ) : ?>
							<nav class="pb-aff-ref-domain-pager" aria-label="<?php esc_attr_e( 'Paginação: desempenho por afiliado', 'pb-affiliates' ); ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML.
								echo $pb_aff_pagination;
								?>
							</nav>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<div class="pb-aff-period-orders-section pb-aff-bottom-tables-row__col">
					<h3 class="pb-aff-chart-heading pb-aff-chart-heading--spaced"><?php esc_html_e( 'Pedidos de afiliados', 'pb-affiliates' ); ?></h3>
					<?php if ( $period_orders_total < 1 ) : ?>
						<p class="description"><?php esc_html_e( 'Não há pedidos atribuídos a afiliados neste período (com os filtros atuais).', 'pb-affiliates' ); ?></p>
					<?php else : ?>
						<?php
						$pb_ord_sort_link = static function ( $col ) use ( $pb_report_nav_base, $ord_sort, $ord_dir ) {
							return PB_Affiliates_Admin_Click_Report::admin_period_orders_sort_url( $pb_report_nav_base, $col, $ord_sort, $ord_dir );
						};
						$pb_ord_sort_mark = static function ( $col ) use ( $ord_sort, $ord_dir ) {
							if ( $col !== $ord_sort ) {
								return '';
							}
							return 'desc' === $ord_dir
								? ' <span class="sort-ind" aria-hidden="true">↓</span>'
								: ' <span class="sort-ind" aria-hidden="true">↑</span>';
						};
						$pb_ord_pag_url    = remove_query_arg( 'pb_ord_paged', add_query_arg( $pb_report_nav_base, admin_url( 'admin.php' ) ) );
						$pb_ord_pagination = '';
						if ( $period_orders_total_pages > 1 ) {
							$pb_ord_pagination = paginate_links(
								array(
									'base'      => add_query_arg( 'pb_ord_paged', '%#%', $pb_ord_pag_url ),
									'format'    => '',
									'current'   => $ord_paged,
									'total'     => $period_orders_total_pages,
									'type'      => 'list',
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								)
							);
						}
						?>
						<div style="overflow-x:auto;margin-top:10px">
						<table class="widefat striped pb-aff-ref-domain-table pb-aff-period-orders-table" style="width:100%;margin:0;min-width:520px">
							<thead>
								<tr>
									<th scope="col" class="sortable">
										<a href="<?php echo esc_url( call_user_func( $pb_ord_sort_link, 'order_id' ) ); ?>">
											<?php esc_html_e( 'Pedido', 'pb-affiliates' ); ?>
											<?php echo wp_kses_post( call_user_func( $pb_ord_sort_mark, 'order_id' ) ); ?>
										</a>
									</th>
									<th scope="col" class="sortable">
										<a href="<?php echo esc_url( call_user_func( $pb_ord_sort_link, 'date' ) ); ?>">
											<?php esc_html_e( 'Data e hora', 'pb-affiliates' ); ?>
											<?php echo wp_kses_post( call_user_func( $pb_ord_sort_mark, 'date' ) ); ?>
										</a>
									</th>
									<th scope="col" class="sortable">
										<a href="<?php echo esc_url( call_user_func( $pb_ord_sort_link, 'status' ) ); ?>">
											<?php esc_html_e( 'Estado', 'pb-affiliates' ); ?>
											<?php echo wp_kses_post( call_user_func( $pb_ord_sort_mark, 'status' ) ); ?>
										</a>
									</th>
									<th scope="col" class="sortable">
										<a href="<?php echo esc_url( call_user_func( $pb_ord_sort_link, 'affiliate' ) ); ?>">
											<?php esc_html_e( 'Afiliado', 'pb-affiliates' ); ?>
											<?php echo wp_kses_post( call_user_func( $pb_ord_sort_mark, 'affiliate' ) ); ?>
										</a>
									</th>
									<th scope="col" class="num sortable">
										<a href="<?php echo esc_url( call_user_func( $pb_ord_sort_link, 'commission' ) ); ?>">
											<?php esc_html_e( 'Comissão', 'pb-affiliates' ); ?>
											<?php echo wp_kses_post( call_user_func( $pb_ord_sort_mark, 'commission' ) ); ?>
										</a>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $period_orders_rows as $por ) : ?>
									<tr>
										<td>
											<a href="<?php echo esc_url( $por['edit_url'] ); ?>">#<?php echo esc_html( isset( $por['order_number'] ) ? (string) $por['order_number'] : (string) $por['order_id'] ); ?></a>
										</td>
										<td><?php echo esc_html( $por['date_display'] ); ?></td>
										<td><?php echo esc_html( $por['status_label'] ); ?></td>
										<td>
											<?php if ( ! empty( $por['affiliate_detail_url'] ) ) : ?>
												<a href="<?php echo esc_url( $por['affiliate_detail_url'] ); ?>"><?php echo esc_html( $por['affiliate_name'] ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $por['affiliate_name'] ); ?>
											<?php endif; ?>
										</td>
										<td class="num"><?php echo wp_kses_post( wc_price( $por['commission'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						</div>
						<?php if ( $pb_ord_pagination ) : ?>
							<nav class="pb-aff-ref-domain-pager" aria-label="<?php esc_attr_e( 'Paginação: pedidos de afiliados', 'pb-affiliates' ); ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML.
								echo $pb_ord_pagination;
								?>
							</nav>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				</div>

				<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON from wp_json_encode. ?>
				<script type="text/javascript">
				jQuery(function(){
					var order_data = JSON.parse( decodeURIComponent( '<?php echo rawurlencode( $chart_data_json ); ?>' ) );
					var pie_series = JSON.parse( decodeURIComponent( '<?php echo rawurlencode( $pie_series_json ); ?>' ) );
					var refererBubbleRows = JSON.parse( decodeURIComponent( '<?php echo rawurlencode( $referer_domain_bubble_series_json ); ?>' ) );
					var bubbleAxisX = <?php echo wp_json_encode( __( 'Cliques', 'pb-affiliates' ) ); ?>;
					var bubbleAxisY = <?php echo wp_json_encode( __( 'Pedidos', 'pb-affiliates' ) ); ?>;
					var orderMoney = JSON.parse( decodeURIComponent( '<?php echo rawurlencode( wp_json_encode( $order_chart_money ) ); ?>' ) );
					var main_chart;

					var pbAffFormatMoney = function (num) {
						var n = Number(num);
						if (!isFinite(n)) {
							return '';
						}
						var neg = n < 0;
						n = Math.abs(n);
						var d = parseInt(orderMoney.decimals, 10) || 0;
						var fixed = n.toFixed(d);
						var parts = fixed.split('.');
						var intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, orderMoney.thousand_sep || '');
						var decPart = parts.length > 1 ? parts[1] : '';
						var sep = orderMoney.decimal_sep || '.';
						var s = orderMoney.symbol || '';
						var body = intPart + (d > 0 ? sep + decPart : '');
						return (neg ? '-' : '') + s + body;
					};

					var drawLine = function() {
						main_chart = jQuery.plot(
							jQuery('.chart-placeholder.pb-aff-click-line'),
							[
								{
									label: <?php echo wp_json_encode( __( 'Cliques', 'pb-affiliates' ) ); ?>,
									data: order_data.clicks,
									color: '#3498db',
									points: { show: true, radius: 4, lineWidth: 2, fillColor: '#fff', fill: true },
									lines: { show: true, lineWidth: 3, fill: true },
									shadowSize: 0,
									prepend_label: true,
									enable_tooltip: true,
									yaxis: 1
								},
								{
									label: <?php echo wp_json_encode( __( 'Pedidos', 'pb-affiliates' ) ); ?>,
									data: order_data.order_counts,
									color: '#e67e22',
									points: { show: true, radius: 3, lineWidth: 2, fillColor: '#fff', fill: true },
									lines: { show: true, lineWidth: 2, fill: false },
									shadowSize: 0,
									prepend_label: true,
									enable_tooltip: true,
									yaxis: 1
								},
								{
									label: <?php echo wp_json_encode( __( 'Valor dos pedidos', 'pb-affiliates' ) ); ?>,
									data: order_data.order_totals,
									color: '#27ae60',
									points: { show: true, radius: 3, lineWidth: 2, fillColor: '#fff', fill: true },
									lines: { show: true, lineWidth: 2, fill: false },
									shadowSize: 0,
									prepend_label: true,
									enable_tooltip: true,
									yaxis: 2
								}
							],
							{
								legend: { show: true, position: 'nw', backgroundOpacity: 0.85 },
								grid: {
									color: '#aaa',
									borderColor: 'transparent',
									borderWidth: 0,
									hoverable: true
								},
								xaxes: [{
									color: '#aaa',
									position: "bottom",
									tickColor: 'transparent',
									mode: "time",
									timeformat: <?php echo wp_json_encode( 'day' === $this->chart_groupby ? '%d %b' : '%b %y' ); ?>,
									monthNames: JSON.parse( decodeURIComponent( '<?php echo rawurlencode( wp_json_encode( array_values( $wp_locale->month_abbrev ) ) ); ?>' ) ),
									tickLength: 1,
									minTickSize: [1, <?php echo wp_json_encode( $this->chart_groupby ); ?>],
									font: { color: "#aaa" }
								}],
								yaxes: [
									{
										min: 0,
										minTickSize: 1,
										tickDecimals: 0,
										color: '#ecf0f1',
										font: { color: "#aaa" }
									},
									{
										min: 0,
										position: 'right',
										color: '#ecf0f1',
										font: { color: "#aaa" },
										tickFormatter: function (v) {
											return pbAffFormatMoney(v);
										}
									}
								]
							}
						);
						jQuery('.chart-placeholder').trigger('resize');
					};

					drawLine();

					if (refererBubbleRows.length) {
						var bubbleColors = ['#3498db', '#e67e22', '#1abc9c', '#9b59b6', '#e74c3c', '#34495e', '#f1c40f', '#27ae60', '#8fdece', '#d35400', '#16a085', '#c0392b', '#2980b9', '#8e44ad', '#2c3e50'];
						var dupSlot = {};
						var bubbleSeries = [];
						var i, r, cx, cy, rad, k, slot, jitter, maxX, maxY;
						maxX = 1;
						maxY = 1;
						for (i = 0; i < refererBubbleRows.length; i++) {
							r = refererBubbleRows[i];
							maxX = Math.max(maxX, r.clicks || 0);
							maxY = Math.max(maxY, r.orders || 0);
						}
						maxX = Math.max(1, Math.ceil(maxX * 1.12) + 1);
						maxY = Math.max(1, Math.ceil(maxY * 1.12) + 1);
						for (i = 0; i < refererBubbleRows.length; i++) {
							r = refererBubbleRows[i];
							cx = r.clicks || 0;
							cy = r.orders || 0;
							k = cx + ',' + cy;
							slot = dupSlot[k] || 0;
							dupSlot[k] = slot + 1;
							jitter = slot * 0.35;
							rad = 5 + Math.sqrt((r.clicks || 0) + (r.orders || 0) * 2) * 2.1;
							rad = Math.min(26, Math.max(6, rad));
							bubbleSeries.push({
								label: r.label || '',
								data: [[cx + jitter, cy + jitter]],
								color: bubbleColors[i % bubbleColors.length],
								points: { show: true, radius: rad, lineWidth: 2, fillColor: bubbleColors[i % bubbleColors.length], fill: true },
								lines: { show: false },
								shadowSize: 0
							});
						}
						var $bubbleEl = jQuery('.chart-placeholder.pb-aff-ref-domain-bubble');
						var $bTip = jQuery('#pb-aff-bubble-tooltip');
						if (!$bTip.length) {
							$bTip = jQuery('<div id="pb-aff-bubble-tooltip" />').appendTo(document.body);
						}
						jQuery.plot(
							$bubbleEl,
							bubbleSeries,
							{
								legend: { show: false },
								grid: { hoverable: true, borderColor: 'transparent', borderWidth: 0, color: '#ddd' },
								xaxis: {
									min: 0,
									max: maxX,
									tickDecimals: 0,
									minTickSize: 1,
									font: { color: '#646970' }
								},
								yaxis: {
									min: 0,
									max: maxY,
									tickDecimals: 0,
									minTickSize: 1,
									font: { color: '#646970' }
								}
							}
						);
						$bubbleEl.on('plothover', function (event, pos, item) {
							if (item) {
								var row = refererBubbleRows[item.seriesIndex];
								var h = row.h ? String(row.h) : '';
								var html = '<strong>' + jQuery('<span/>').text(row.label || h).html() + '</strong>';
								if (h && row.label !== h) {
									html += '<div><code style="opacity:.85">' + jQuery('<span/>').text(h).html() + '</code></div>';
								}
								html += '<div>' + bubbleAxisX + ': ' + (row.clicks || 0) + '</div>';
								html += '<div>' + bubbleAxisY + ': ' + (row.orders || 0) + '</div>';
								$bTip.html(html).css({ left: pos.pageX + 12, top: pos.pageY + 12 }).show();
							} else {
								$bTip.hide();
							}
						});
						$bubbleEl.on('mouseleave', function () {
							$bTip.hide();
						});
						$bubbleEl.trigger('resize');
					}

					var $legend = jQuery('#pb-aff-pie-legend').empty();
					jQuery.each(pie_series, function(i, s) {
						var title = s.legend_primary || s.label || '';
						var stats = s.legend_stats || '';
						var $li = jQuery('<li/>').css('border-left-color', s.color || '#ccc');
						$li.append(jQuery('<span class="pb-aff-pie-legend__name"/>').text(title));
						if (stats) {
							$li.append(document.createTextNode(' '));
							$li.append(jQuery('<span class="pb-aff-pie-legend__stats"/>').text(stats));
						}
						if (s.legend_note && s.legend_note !== title) {
							$li.append(document.createTextNode(' '));
							$li.append(jQuery('<code class="pb-aff-pie-legend__code"/>').text('(' + s.legend_note + ')'));
						}
						$legend.append($li);
					});

					jQuery.plot(
						jQuery('.chart-placeholder.pb-aff-click-pie'),
						pie_series,
						{
							grid: { hoverable: true },
							series: {
								pie: {
									show: true,
									radius: 1,
									innerRadius: 0.55,
									label: {
										show: true,
										radius: 0.72,
										formatter: function (text, slice) {
											if (!slice || typeof slice.percent !== 'number') {
												return '';
											}
											var count = slice.data && slice.data[0] && typeof slice.data[0][1] !== 'undefined'
												? slice.data[0][1]
												: '';
											var pct = slice.percent.toFixed(1);
											return '<div class="pb-aff-pie-slice-label">' + count + '<br/>' + pct + '%</div>';
										}
									}
								}
							},
							legend: { show: false }
						}
					);
					jQuery('.chart-placeholder.pb-aff-click-pie').trigger('resize');
				});
				</script>
			</div>
		</div>
	</div>
</div>
