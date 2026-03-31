<?php
/**
 * Admin: listagem e aprovação/rejeição de afiliados.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Admin_Affiliates
 */
class PB_Affiliates_Admin_Affiliates {

	/**
	 * Processar ações POST antes de renderizar.
	 */
	public static function handle_post() {
		if ( ! isset( $_POST['pb_aff_affiliate_action'], $_POST['_wpnonce'], $_POST['pb_aff_user_id'] ) ) {
			return;
		}
		if ( ! isset( $_GET['page'] ) || 'pb-affiliates-users' !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'pb_aff_affiliate_action' ) ) {
			return;
		}
		$uid = absint( $_POST['pb_aff_user_id'] );
		if ( $uid <= 0 ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['pb_aff_affiliate_action'] ) );
		if ( 'approve' === $action ) {
			PB_Affiliates_Role::approve_affiliate( $uid );
			$arg = 'approved';
		} elseif ( 'reject' === $action ) {
			PB_Affiliates_Role::reject_affiliate( $uid );
			$arg = 'rejected';
		} else {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=pb-affiliates-users&pb_aff_notice=' . rawurlencode( $arg ) ) );
		exit;
	}

	/**
	 * Render da página.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- List screen: GET is only for filters/pagination/notices; approve/reject uses POST with nonce in handle_post().
		$filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		if ( ! in_array( $filter, array( 'all', 'pending', 'active', 'none' ), true ) ) {
			$filter = 'all';
		}

		if ( 'all' === $filter ) {
			$meta_query = array(
				array(
					'key'     => 'pb_affiliate_status',
					'value'   => array( PB_Affiliates_Role::STATUS_PENDING, PB_Affiliates_Role::STATUS_ACTIVE, PB_Affiliates_Role::STATUS_NONE ),
					'compare' => 'IN',
				),
			);
		} else {
			$meta_query = array(
				array(
					'key'   => 'pb_affiliate_status',
					'value' => $filter,
				),
			);
		}

		$per_page = 30;
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

		$sort_ob = isset( $_GET['pb_aff_u_ob'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_u_ob'] ) ) : '';
		if ( ! in_array( $sort_ob, array( 'orders', 'commissions' ), true ) ) {
			$sort_ob = '';
		}
		$sort_dir = isset( $_GET['pb_aff_u_dir'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_u_dir'] ) ) : 'desc';
		if ( ! in_array( $sort_dir, array( 'asc', 'desc' ), true ) ) {
			$sort_dir = 'desc';
		}

		$user_query_base = array(
			'meta_query' => $meta_query,
		);

		$sort_agg = null;
		if ( '' !== $sort_ob ) {
			$ids_q   = new WP_User_Query(
				array_merge(
					$user_query_base,
					array(
						'fields' => 'ID',
						'number' => -1,
					)
				)
			);
			$all_ids = $ids_q->get_results();
			if ( ! is_array( $all_ids ) ) {
				$all_ids = array();
			}
			$sort_agg = PB_Affiliates_Reports::get_affiliate_commission_aggregates_map( $all_ids );
			usort(
				$all_ids,
				function ( $a, $b ) use ( $sort_agg, $sort_ob, $sort_dir ) {
					$a = (int) $a;
					$b = (int) $b;
					$va = 'orders' === $sort_ob ? $sort_agg[ $a ]['orders'] : $sort_agg[ $a ]['commission_total'];
					$vb = 'orders' === $sort_ob ? $sort_agg[ $b ]['orders'] : $sort_agg[ $b ]['commission_total'];
					$key_cmp = $va <=> $vb;
					if ( 0 !== $key_cmp ) {
						return 'asc' === $sort_dir ? $key_cmp : - $key_cmp;
					}
					return $a <=> $b;
				}
			);
			$total    = count( $all_ids );
			$page_ids = array_slice( $all_ids, ( $paged - 1 ) * $per_page, $per_page );
			$users    = array_values(
				array_filter(
					array_map( 'get_userdata', $page_ids )
				)
			);
		} else {
			$q = new WP_User_Query(
				array_merge(
					$user_query_base,
					array(
						'number'  => $per_page,
						'paged'   => $paged,
						'orderby' => 'registered',
						'order'   => 'DESC',
					)
				)
			);
			$users = $q->get_results();
			$total = $q->get_total();
		}

		$display_ids = array_map(
			static function ( $u ) {
				return $u instanceof WP_User ? (int) $u->ID : 0;
			},
			$users
		);
		$display_ids = array_values( array_filter( $display_ids ) );
		if ( is_array( $sort_agg ) ) {
			$row_agg = array();
			foreach ( $display_ids as $did ) {
				$row_agg[ $did ] = $sort_agg[ $did ];
			}
		} else {
			$row_agg = PB_Affiliates_Reports::get_affiliate_commission_aggregates_map( $display_ids );
		}

		$list_extra_args = array();
		if ( '' !== $sort_ob ) {
			$list_extra_args['pb_aff_u_ob']  = $sort_ob;
			$list_extra_args['pb_aff_u_dir'] = $sort_dir;
		}

		$subsub_url = function ( $status_key ) use ( $list_extra_args ) {
			$args = array_merge(
				array(
					'page'   => 'pb-affiliates-users',
					'status' => $status_key,
				),
				$list_extra_args
			);
			return add_query_arg( $args, admin_url( 'admin.php' ) );
		};

		if ( ! empty( $_GET['pb_aff_notice'] ) ) {
			$n = sanitize_key( wp_unslash( $_GET['pb_aff_notice'] ) );
			if ( 'approved' === $n ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Afiliado aprovado.', 'pb-affiliates' ) . '</p></div>';
			} elseif ( 'rejected' === $n ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Afiliado rejeitado ou removido do programa.', 'pb-affiliates' ) . '</p></div>';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap woocommerce">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Afiliados', 'pb-affiliates' ); ?></h1>
			<hr class="wp-header-end" />

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( call_user_func( $subsub_url, 'all' ) ); ?>" class="<?php echo 'all' === $filter ? 'current' : ''; ?>"><?php esc_html_e( 'Todos', 'pb-affiliates' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( call_user_func( $subsub_url, 'pending' ) ); ?>" class="<?php echo 'pending' === $filter ? 'current' : ''; ?>"><?php esc_html_e( 'Pendentes', 'pb-affiliates' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( call_user_func( $subsub_url, 'active' ) ); ?>" class="<?php echo 'active' === $filter ? 'current' : ''; ?>"><?php esc_html_e( 'Ativos', 'pb-affiliates' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( call_user_func( $subsub_url, 'none' ) ); ?>" class="<?php echo 'none' === $filter ? 'current' : ''; ?>"><?php esc_html_e( 'Não participam', 'pb-affiliates' ); ?></a></li>
			</ul>

			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th scope="col" class="column-primary"><?php esc_html_e( 'Usuário', 'pb-affiliates' ); ?></th>
						<th><?php esc_html_e( 'E-mail', 'pb-affiliates' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'pb-affiliates' ); ?></th>
						<?php self::render_sortable_metric_th( __( 'Total de pedidos aprovados', 'pb-affiliates' ), $filter, 'orders', $sort_ob, $sort_dir ); ?>
						<?php self::render_sortable_metric_th( __( 'Total de comissões', 'pb-affiliates' ), $filter, 'commissions', $sort_ob, $sort_dir ); ?>
						<th><?php esc_html_e( 'Código', 'pb-affiliates' ); ?></th>
						<th><?php esc_html_e( 'Ações', 'pb-affiliates' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $users ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'Nenhum usuário encontrado.', 'pb-affiliates' ); ?></td></tr>
					<?php else : ?>
						<?php
						foreach ( $users as $user ) {
							$st    = PB_Affiliates_Role::get_affiliate_status( $user->ID );
							$code  = (string) get_user_meta( $user->ID, 'pb_affiliate_code', true );
							$stats = isset( $row_agg[ $user->ID ] ) ? $row_agg[ $user->ID ] : array(
								'orders'             => 0,
								'commission_total'   => 0.0,
							);
							?>
						<tr>
							<td class="column-primary">
								<strong><a href="<?php echo esc_url( PB_Affiliates_Admin_User_Detail::url( $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a></strong>
							</td>
							<td><?php echo esc_html( $user->user_email ); ?></td>
							<td><?php echo esc_html( self::status_label( $st ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $stats['orders'] ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( $stats['commission_total'] ) ); ?></td>
							<td><code><?php echo esc_html( $code ? $code : '—' ); ?></code></td>
							<td>
								<?php if ( PB_Affiliates_Role::user_is_pending_affiliate( $user->ID ) ) : ?>
									<form method="post" style="display:inline;">
										<?php wp_nonce_field( 'pb_aff_affiliate_action' ); ?>
										<input type="hidden" name="pb_aff_user_id" value="<?php echo (int) $user->ID; ?>" />
										<input type="hidden" name="pb_aff_affiliate_action" value="approve" />
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Aprovar', 'pb-affiliates' ); ?></button>
									</form>
								<?php endif; ?>
								<?php if ( PB_Affiliates_Role::user_is_pending_affiliate( $user->ID ) || PB_Affiliates_Role::user_is_affiliate( $user->ID ) ) : ?>
									<form method="post" style="display:inline;margin-left:4px;" onsubmit="return confirm(<?php echo wp_json_encode( __( 'Rejeitar ou remover este afiliado do programa?', 'pb-affiliates' ) ); ?>);">
										<?php wp_nonce_field( 'pb_aff_affiliate_action' ); ?>
										<input type="hidden" name="pb_aff_user_id" value="<?php echo (int) $user->ID; ?>" />
										<input type="hidden" name="pb_aff_affiliate_action" value="reject" />
										<button type="submit" class="button"><?php echo PB_Affiliates_Role::user_is_pending_affiliate( $user->ID ) ? esc_html__( 'Rejeitar', 'pb-affiliates' ) : esc_html__( 'Remover', 'pb-affiliates' ); ?></button>
									</form>
								<?php endif; ?>
								<a class="button" href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php esc_html_e( 'Editar', 'pb-affiliates' ); ?></a>
							</td>
						</tr>
							<?php
						}
						?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			$total_pages = (int) ceil( $total / $per_page );
			if ( $total_pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				$pagination_query = array_merge(
					array(
						'paged'  => '%#%',
						'status' => $filter,
					),
					$list_extra_args
				);
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns escaped HTML.
				echo paginate_links(
					array(
						'base'      => add_query_arg( $pagination_query, admin_url( 'admin.php?page=pb-affiliates-users' ) ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => $total_pages,
						'current'   => $paged,
					)
				);
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Cabeçalho de coluna numérica ordenável (estilo list tables).
	 *
	 * @param string $label     Texto.
	 * @param string $filter    status (all|pending|active|none).
	 * @param string $column    orders|commissions.
	 * @param string $sort_ob   Coluna ativa ou vazio.
	 * @param string $sort_dir  asc|desc.
	 */
	private static function render_sortable_metric_th( $label, $filter, $column, $sort_ob, $sort_dir ) {
		if ( ! in_array( $column, array( 'orders', 'commissions' ), true ) ) {
			return;
		}
		$url = self::affiliates_metric_sort_url( $filter, $column, $sort_ob, $sort_dir );
		if ( '' === $url ) {
			return;
		}
		$is_active = ( $sort_ob === $column );
		$classes   = 'manage-column column-pb-aff-' . sanitize_html_class( $column );
		if ( $is_active ) {
			$classes .= ' sorted ' . ( 'asc' === $sort_dir ? 'asc' : 'desc' );
		} else {
			$classes .= ' sortable asc';
		}
		echo '<th scope="col" class="' . esc_attr( $classes ) . '">';
		echo '<a href="' . esc_url( $url ) . '">';
		echo '<span>' . esc_html( $label ) . '</span>';
		echo '<span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span class="sorting-indicator desc" aria-hidden="true"></span></span>';
		echo '</a></th>';
	}

	/**
	 * URL para alternar ordenação por métrica (nova ordenação começa em descendente).
	 *
	 * @param string $filter    Filtro de status.
	 * @param string $column    orders|commissions.
	 * @param string $sort_ob   Coluna ordenada atual.
	 * @param string $sort_dir  Direção atual.
	 * @return string
	 */
	private static function affiliates_metric_sort_url( $filter, $column, $sort_ob, $sort_dir ) {
		if ( ! in_array( $column, array( 'orders', 'commissions' ), true ) ) {
			return '';
		}
		$next_dir = 'desc';
		if ( $sort_ob === $column ) {
			$next_dir = ( 'asc' === $sort_dir ) ? 'desc' : 'asc';
		}
		return add_query_arg(
			array(
				'page'          => 'pb-affiliates-users',
				'status'        => $filter,
				'pb_aff_u_ob'   => $column,
				'pb_aff_u_dir'  => $next_dir,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @param string $st Status raw.
	 * @return string
	 */
	private static function status_label( $st ) {
		switch ( (string) $st ) {
			case PB_Affiliates_Role::STATUS_PENDING:
				return __( 'Pendente', 'pb-affiliates' );
			case PB_Affiliates_Role::STATUS_ACTIVE:
				return __( 'Ativo', 'pb-affiliates' );
			case PB_Affiliates_Role::STATUS_NONE:
				return __( 'Não participa', 'pb-affiliates' );
			default:
				return $st ? (string) $st : '—';
		}
	}
}
