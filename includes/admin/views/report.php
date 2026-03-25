<?php
/**
 * Admin report view.
 *
 * @package PB_Affiliates
 * @var object|null $summary Summary.
 * @var array       $rows Rows.
 * @var int         $orders_report_paged Página atual (1-based).
 * @var int         $orders_report_per_page Linhas por página.
 * @var int         $orders_report_total Total de registros na tabela.
 * @var int         $orders_report_total_pages Total de páginas.
 * @var string      $orders_report_orderby Coluna de ordenação.
 * @var string      $orders_report_order asc|desc.
 * @var array       $orders_report_filters Filtros normalizados (affiliate_id, order_id, status, via, date_from, date_to).
 * @var array       $orders_report_via_choices Slug => rótulo para filtro Via.
 * @var array       $orders_report_affiliate_options { id, label }[].
 */

defined( 'ABSPATH' ) || exit;

$orders_report_orderby = isset( $orders_report_orderby ) ? (string) $orders_report_orderby : 'id';
$orders_report_order   = isset( $orders_report_order ) ? (string) $orders_report_order : 'desc';
$orders_report_filters = isset( $orders_report_filters ) && is_array( $orders_report_filters )
	? PB_Affiliates_Reports::normalize_admin_commissions_filters( $orders_report_filters )
	: PB_Affiliates_Reports::normalize_admin_commissions_filters( array() );
$orders_report_via_choices       = isset( $orders_report_via_choices ) && is_array( $orders_report_via_choices ) ? $orders_report_via_choices : array();
$orders_report_affiliate_options = isset( $orders_report_affiliate_options ) && is_array( $orders_report_affiliate_options ) ? $orders_report_affiliate_options : array();

$pb_filter_args = PB_Affiliates_Reports::admin_orders_report_filter_query_args( $orders_report_filters );

$pb_orders_pag_url = remove_query_arg(
	'paged',
	add_query_arg(
		array_merge(
			array(
				'page'    => PB_Affiliates_Admin::PARENT_SLUG,
				'orderby' => $orders_report_orderby,
				'order'   => $orders_report_order,
			),
			$pb_filter_args
		),
		admin_url( 'admin.php' )
	)
);

$pb_orders_report_clear_url = admin_url( 'admin.php?page=' . rawurlencode( PB_Affiliates_Admin::PARENT_SLUG ) );

$pb_orders_status_choices = array(
	''        => __( 'Todos', 'pb-affiliates' ),
	'pending' => __( 'Pendente', 'pb-affiliates' ),
	'paid'    => __( 'Pago', 'pb-affiliates' ),
);

$pb_orders_sort_mark = static function ( $col ) use ( $orders_report_orderby, $orders_report_order ) {
	if ( $col !== $orders_report_orderby ) {
		return '';
	}
	return 'desc' === $orders_report_order
		? ' <span class="sort-ind" aria-hidden="true">↓</span>'
		: ' <span class="sort-ind" aria-hidden="true">↑</span>';
};

$pb_orders_pagination = '';
if ( isset( $orders_report_total_pages ) && $orders_report_total_pages > 1 ) {
	$pb_orders_pagination = paginate_links(
		array(
			'base'      => add_query_arg( 'paged', '%#%', $pb_orders_pag_url ),
			'format'    => '',
			'current'   => isset( $orders_report_paged ) ? (int) $orders_report_paged : 1,
			'total'     => (int) $orders_report_total_pages,
			'type'      => 'list',
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
		)
	);
}
$pb_orders_from = 0;
$pb_orders_to   = 0;
if ( ! empty( $orders_report_total ) && ! empty( $rows ) ) {
	$pb_orders_from = (int) ( ( (int) $orders_report_paged - 1 ) * (int) $orders_report_per_page ) + 1;
	$pb_orders_to   = $pb_orders_from + count( (array) $rows ) - 1;
}
?>
<div class="wrap pb-aff-wrap pb-aff-orders-report-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Pedidos', 'pb-affiliates' ); ?></h1>
	<hr class="wp-header-end" />
	<form class="pb-aff-orders-report-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( PB_Affiliates_Admin::PARENT_SLUG ); ?>" />
		<input type="hidden" name="orderby" value="<?php echo esc_attr( $orders_report_orderby ); ?>" />
		<input type="hidden" name="order" value="<?php echo esc_attr( $orders_report_order ); ?>" />
		<div class="pb-aff-orders-report-filters-row">
			<label>
				<span><?php esc_html_e( 'Afiliado', 'pb-affiliates' ); ?></span>
				<select name="pb_aff_ord_f_affiliate_id" id="pb_aff_ord_f_affiliate_id">
					<option value="0"><?php esc_html_e( 'Todos', 'pb-affiliates' ); ?></option>
					<?php foreach ( $orders_report_affiliate_options as $ao ) : ?>
						<option value="<?php echo esc_attr( (string) $ao['id'] ); ?>" <?php selected( $orders_report_filters['affiliate_id'], (int) $ao['id'] ); ?>>
							<?php echo esc_html( (string) $ao['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Nº do pedido', 'pb-affiliates' ); ?></span>
				<input type="number" name="pb_aff_ord_f_order_id" id="pb_aff_ord_f_order_id" min="0" step="1" value="<?php echo $orders_report_filters['order_id'] > 0 ? esc_attr( (string) (int) $orders_report_filters['order_id'] ) : ''; ?>" placeholder="—" />
			</label>
			<label>
				<span><?php esc_html_e( 'Estado da comissão', 'pb-affiliates' ); ?></span>
				<select name="pb_aff_ord_f_status" id="pb_aff_ord_f_status">
					<?php foreach ( $pb_orders_status_choices as $sv => $sl ) : ?>
						<option value="<?php echo esc_attr( $sv ); ?>" <?php selected( $orders_report_filters['status'], $sv ); ?>><?php echo esc_html( $sl ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Via', 'pb-affiliates' ); ?></span>
				<select name="pb_aff_ord_f_via" id="pb_aff_ord_f_via">
					<option value=""><?php esc_html_e( 'Todas', 'pb-affiliates' ); ?></option>
					<?php foreach ( $orders_report_via_choices as $vk => $vl ) : ?>
						<option value="<?php echo esc_attr( $vk ); ?>" <?php selected( $orders_report_filters['via'], $vk ); ?>><?php echo esc_html( $vl ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Criado de', 'pb-affiliates' ); ?></span>
				<input type="date" name="pb_aff_ord_f_date_from" id="pb_aff_ord_f_date_from" value="<?php echo esc_attr( (string) $orders_report_filters['date_from'] ); ?>" />
			</label>
			<label>
				<span><?php esc_html_e( 'até', 'pb-affiliates' ); ?></span>
				<input type="date" name="pb_aff_ord_f_date_to" id="pb_aff_ord_f_date_to" value="<?php echo esc_attr( (string) $orders_report_filters['date_to'] ); ?>" />
			</label>
			<?php submit_button( __( 'Filtrar', 'pb-affiliates' ), 'secondary', 'submit', false ); ?>
			<p class="submit" style="margin:0;padding:0">
				<a class="button button-link" href="<?php echo esc_url( $pb_orders_report_clear_url ); ?>"><?php esc_html_e( 'Limpar filtros', 'pb-affiliates' ); ?></a>
			</p>
		</div>
	</form>
	<p>
		<?php
		if ( $summary ) {
			echo esc_html( sprintf( __( 'Total de registros: %1$d — Soma de comissões: ', 'pb-affiliates' ), (int) $summary->cnt ) );
			echo wp_kses_post( wc_price( (float) $summary->total ) );
		}
		?>
	</p>
	<?php if ( $pb_orders_from > 0 && $pb_orders_to > 0 ) : ?>
		<p class="description" style="margin:-0.5em 0 0.75em">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: first row number 2: last row number 3: total rows */
					__( 'Exibindo %1$d–%2$d de %3$d.', 'pb-affiliates' ),
					$pb_orders_from,
					$pb_orders_to,
					(int) $orders_report_total
				)
			);
			?>
		</p>
	<?php endif; ?>
	<table class="widefat striped pb-aff-orders-report-table">
		<thead>
			<tr>
				<th scope="col" class="sortable">
					<a href="<?php echo esc_url( PB_Affiliates_Admin::orders_report_sort_url( 'id', $orders_report_orderby, $orders_report_order, $orders_report_filters ) ); ?>">
						<?php esc_html_e( 'ID', 'pb-affiliates' ); ?><?php echo wp_kses_post( call_user_func( $pb_orders_sort_mark, 'id' ) ); ?>
					</a>
				</th>
				<th scope="col" class="sortable">
					<a href="<?php echo esc_url( PB_Affiliates_Admin::orders_report_sort_url( 'order_id', $orders_report_orderby, $orders_report_order, $orders_report_filters ) ); ?>">
						<?php esc_html_e( 'Pedido', 'pb-affiliates' ); ?><?php echo wp_kses_post( call_user_func( $pb_orders_sort_mark, 'order_id' ) ); ?>
					</a>
				</th>
				<th scope="col" class="sortable">
					<a href="<?php echo esc_url( PB_Affiliates_Admin::orders_report_sort_url( 'affiliate', $orders_report_orderby, $orders_report_order, $orders_report_filters ) ); ?>">
						<?php esc_html_e( 'Afiliado', 'pb-affiliates' ); ?><?php echo wp_kses_post( call_user_func( $pb_orders_sort_mark, 'affiliate' ) ); ?>
					</a>
				</th>
				<th scope="col" class="sortable num">
					<a href="<?php echo esc_url( PB_Affiliates_Admin::orders_report_sort_url( 'commission', $orders_report_orderby, $orders_report_order, $orders_report_filters ) ); ?>">
						<?php esc_html_e( 'Comissão', 'pb-affiliates' ); ?><?php echo wp_kses_post( call_user_func( $pb_orders_sort_mark, 'commission' ) ); ?>
					</a>
				</th>
				<th scope="col" class="sortable">
					<a href="<?php echo esc_url( PB_Affiliates_Admin::orders_report_sort_url( 'status', $orders_report_orderby, $orders_report_order, $orders_report_filters ) ); ?>">
						<?php esc_html_e( 'Estado', 'pb-affiliates' ); ?><?php echo wp_kses_post( call_user_func( $pb_orders_sort_mark, 'status' ) ); ?>
					</a>
				</th>
				<th scope="col" class="sortable">
					<a href="<?php echo esc_url( PB_Affiliates_Admin::orders_report_sort_url( 'via', $orders_report_orderby, $orders_report_order, $orders_report_filters ) ); ?>">
						<?php esc_html_e( 'Via', 'pb-affiliates' ); ?><?php echo wp_kses_post( call_user_func( $pb_orders_sort_mark, 'via' ) ); ?>
					</a>
				</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( (array) $rows as $row ) : ?>
				<?php
				$rid      = (int) $row['order_id'];
				$ord      = $rid ? wc_get_order( $rid ) : null;
				$edit_ord = ( $ord && is_callable( array( $ord, 'get_edit_order_url' ) ) ) ? $ord->get_edit_order_url() : admin_url( 'post.php?post=' . $rid . '&action=edit' );
				$aff_id   = (int) $row['affiliate_id'];
				$aff_code = $aff_id > 0 ? (string) get_user_meta( $aff_id, 'pb_affiliate_code', true ) : '';
				$aff_name = isset( $row['affiliate_display_name'] ) ? trim( (string) $row['affiliate_display_name'] ) : '';
				if ( '' === $aff_name && $aff_id > 0 ) {
					$aff_name = sprintf(
						/* translators: %d: user ID */
						__( 'Usuário #%d (sem nome)', 'pb-affiliates' ),
						$aff_id
					);
				}
				$via_raw = isset( $row['attributed_via'] ) ? (string) $row['attributed_via'] : '';
				$via_lbl = class_exists( 'PB_Affiliates_Admin_Click_Report', false )
					? PB_Affiliates_Admin_Click_Report::human_label_for_via( $via_raw )
					: $via_raw;
				?>
				<tr>
					<td><?php echo esc_html( (string) $row['id'] ); ?></td>
					<td>
						<a href="<?php echo esc_url( $edit_ord ); ?>">
							#<?php echo esc_html( (string) $row['order_id'] ); ?>
						</a>
					</td>
					<td>
						<?php if ( $aff_id > 0 ) : ?>
							<strong><a href="<?php echo esc_url( PB_Affiliates_Admin_User_Detail::url( $aff_id ) ); ?>"><?php echo esc_html( $aff_name ); ?></a></strong>
							<br /><code class="pb-aff-orders-aff-code"><?php echo esc_html( $aff_code ? $aff_code : '—' ); ?></code>
						<?php else : ?>
							<?php echo esc_html( '—' ); ?>
						<?php endif; ?>
					</td>
					<td class="num">
						<?php echo wp_kses_post( wc_price( (float) $row['commission_amount'], array( 'currency' => $row['currency'] ) ) ); ?>
						<?php
						$base = isset( $row['commission_base'] ) ? (float) $row['commission_base'] : 0.0;
						if ( $base > 0 ) {
							echo '<br /><span class="description">';
							echo esc_html( __( 'Base:', 'pb-affiliates' ) . ' ' );
							echo wp_kses_post( wc_price( $base, array( 'currency' => $row['currency'] ) ) );
							echo '</span>';
						}
						?>
					</td>
					<td><?php echo esc_html( (string) $row['status'] ); ?></td>
					<td><?php echo esc_html( $via_lbl ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $pb_orders_pagination ) : ?>
		<nav class="tablenav" style="margin-top:10px" aria-label="<?php esc_attr_e( 'Paginação da lista de pedidos', 'pb-affiliates' ); ?>">
			<div class="tablenav-pages">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML.
				echo $pb_orders_pagination;
				?>
			</div>
		</nav>
	<?php endif; ?>
</div>
