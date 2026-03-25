<?php
/**
 * Admin: página de detalhe de um afiliado (pedidos + cliques).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Admin_User_Detail
 */
class PB_Affiliates_Admin_User_Detail {

	const PAGE_SLUG = 'pb-affiliates-user-detail';

	/** @var string Prefixo query string — pedidos atribuídos */
	const Q_ORD_P = 'pba_ord_p';

	const Q_ORD_PP = 'pba_ord_pp';

	const Q_ORD_ST = 'pba_ord_st';

	const Q_ORD_VIA = 'pba_ord_via';

	const Q_ORD_DF = 'pba_ord_df';

	const Q_ORD_DT = 'pba_ord_dt';

	const Q_ORD_OB = 'pba_ord_ob';

	const Q_ORD_DIR = 'pba_ord_dir';

	/** @var string Prefixo — cliques */
	const Q_CLK_P = 'pba_clk_p';

	const Q_CLK_PP = 'pba_clk_pp';

	const Q_CLK_VIA = 'pba_clk_via';

	const Q_CLK_DF = 'pba_clk_df';

	const Q_CLK_DT = 'pba_clk_dt';

	const Q_CLK_OB = 'pba_clk_ob';

	const Q_CLK_DIR = 'pba_clk_dir';

	/**
	 * URL da página de detalhe (admin).
	 *
	 * @param int $user_id ID WP do usuário.
	 * @return string
	 */
	public static function url( $user_id ) {
		return add_query_arg(
			array(
				'page'    => self::PAGE_SLUG,
				'user_id' => (int) $user_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * URL da tela de detalhe com parâmetros de pedidos + cliques (valores vazios omitem).
	 *
	 * @param int   $user_id User ID.
	 * @param array $ord     Parâmetros pba_ord_*.
	 * @param array $clk     Parâmetros pba_clk_*.
	 * @return string
	 */
	private static function aud_url( $user_id, array $ord, array $clk ) {
		$args = array_merge(
			array(
				'page'    => self::PAGE_SLUG,
				'user_id' => (int) $user_id,
			),
			$ord,
			$clk
		);
		foreach ( $args as $k => $v ) {
			if ( '' === $v || null === $v ) {
				unset( $args[ $k ] );
			}
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Lê estado de filtros / paginação a partir de $_GET.
	 *
	 * @return array{ord:array, clk:array}
	 */
	private static function parse_list_states() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- lista só leitura, sem acção destructiva.
		$ord = array(
			self::Q_ORD_P   => max( 1, isset( $_GET[ self::Q_ORD_P ] ) ? absint( wp_unslash( $_GET[ self::Q_ORD_P ] ) ) : 1 ),
			self::Q_ORD_PP  => max( 5, min( 100, isset( $_GET[ self::Q_ORD_PP ] ) ? absint( wp_unslash( $_GET[ self::Q_ORD_PP ] ) ) : 15 ) ),
			self::Q_ORD_ST  => isset( $_GET[ self::Q_ORD_ST ] ) ? sanitize_key( wp_unslash( $_GET[ self::Q_ORD_ST ] ) ) : '',
			self::Q_ORD_VIA => isset( $_GET[ self::Q_ORD_VIA ] ) ? sanitize_key( wp_unslash( $_GET[ self::Q_ORD_VIA ] ) ) : '',
			self::Q_ORD_DF  => isset( $_GET[ self::Q_ORD_DF ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::Q_ORD_DF ] ) ) : '',
			self::Q_ORD_DT  => isset( $_GET[ self::Q_ORD_DT ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::Q_ORD_DT ] ) ) : '',
			self::Q_ORD_OB  => isset( $_GET[ self::Q_ORD_OB ] ) ? sanitize_key( wp_unslash( $_GET[ self::Q_ORD_OB ] ) ) : 'date',
			self::Q_ORD_DIR => isset( $_GET[ self::Q_ORD_DIR ] ) && 'asc' === strtolower( (string) wp_unslash( $_GET[ self::Q_ORD_DIR ] ) ) ? 'ASC' : 'DESC',
		);
		if ( ! in_array( $ord[ self::Q_ORD_OB ], array( 'date', 'total' ), true ) ) {
			$ord[ self::Q_ORD_OB ] = 'date';
		}

		$clk = array(
			self::Q_CLK_P   => max( 1, isset( $_GET[ self::Q_CLK_P ] ) ? absint( wp_unslash( $_GET[ self::Q_CLK_P ] ) ) : 1 ),
			self::Q_CLK_PP  => max( 5, min( 100, isset( $_GET[ self::Q_CLK_PP ] ) ? absint( wp_unslash( $_GET[ self::Q_CLK_PP ] ) ) : 15 ) ),
			self::Q_CLK_VIA => isset( $_GET[ self::Q_CLK_VIA ] ) ? sanitize_key( wp_unslash( $_GET[ self::Q_CLK_VIA ] ) ) : '',
			self::Q_CLK_DF  => isset( $_GET[ self::Q_CLK_DF ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::Q_CLK_DF ] ) ) : '',
			self::Q_CLK_DT  => isset( $_GET[ self::Q_CLK_DT ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::Q_CLK_DT ] ) ) : '',
			self::Q_CLK_OB  => isset( $_GET[ self::Q_CLK_OB ] ) ? sanitize_key( wp_unslash( $_GET[ self::Q_CLK_OB ] ) ) : 'id',
			self::Q_CLK_DIR => isset( $_GET[ self::Q_CLK_DIR ] ) && 'asc' === strtolower( (string) wp_unslash( $_GET[ self::Q_CLK_DIR ] ) ) ? 'ASC' : 'DESC',
		);
		if ( ! in_array( $clk[ self::Q_CLK_OB ], array( 'id', 'hit_at', 'via', 'client_ip' ), true ) ) {
			$clk[ self::Q_CLK_OB ] = 'id';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return array( 'ord' => $ord, 'clk' => $clk );
	}

	/**
	 * Próxima direcção de ordenação ao clicar na mesma coluna.
	 *
	 * @param string $column   Coluna.
	 * @param string $cur_col  Coluna atual.
	 * @param string $cur_dir  ASC|DESC.
	 * @return string ASC|DESC
	 */
	private static function next_sort_dir( $column, $cur_col, $cur_dir ) {
		if ( $cur_col === $column ) {
			return 'DESC' === $cur_dir ? 'ASC' : 'DESC';
		}
		return 'DESC';
	}

	/**
	 * Indicador de ordenação para cabeçalho.
	 *
	 * @param string $column  Coluna.
	 * @param string $cur_col Coluna ativa.
	 * @param string $cur_dir ASC|DESC.
	 * @return string
	 */
	private static function sort_indicator( $column, $cur_col, $cur_dir ) {
		if ( $cur_col !== $column ) {
			return '';
		}
		return 'DESC' === $cur_dir ? ' ▼' : ' ▲';
	}

	/**
	 * Paginação estilo WordPress.
	 *
	 * @param int    $current  Página atual.
	 * @param int    $total    Total de páginas.
	 * @param string $base_url URL base com %_% ou usar add_args.
	 */
	private static function render_pagination( $current, $total, $url_factory ) {
		if ( $total <= 1 ) {
			return;
		}
		echo '<div class="tablenav"><div class="tablenav-pages" style="display:flex;flex-wrap:wrap;align-items:center;gap:6px">';
		if ( $current > 1 ) {
			echo '<a class="button" href="' . esc_url( call_user_func( $url_factory, $current - 1 ) ) . '">' . esc_html__( '← Anterior', 'pb-affiliates' ) . '</a>';
		}
		$window = 2;
		$start  = max( 1, $current - $window );
		$end    = min( $total, $current + $window );
		if ( $start > 1 ) {
			echo '<a class="button button-small" href="' . esc_url( call_user_func( $url_factory, 1 ) ) . '">1</a>';
			if ( $start > 2 ) {
				echo '<span class="tablenav-paging-text">…</span>';
			}
		}
		for ( $p = $start; $p <= $end; $p++ ) {
			if ( (int) $p === (int) $current ) {
				echo '<strong style="padding:0 4px">' . (int) $p . '</strong>';
			} else {
				echo '<a class="button button-small" href="' . esc_url( call_user_func( $url_factory, $p ) ) . '">' . (int) $p . '</a>';
			}
		}
		if ( $end < $total ) {
			if ( $end < $total - 1 ) {
				echo '<span class="tablenav-paging-text">…</span>';
			}
			echo '<a class="button button-small" href="' . esc_url( call_user_func( $url_factory, $total ) ) . '">' . (int) $total . '</a>';
		}
		if ( $current < $total ) {
			echo '<a class="button" href="' . esc_url( call_user_func( $url_factory, $current + 1 ) ) . '">' . esc_html__( 'Próximo →', 'pb-affiliates' ) . '</a>';
		}
		echo '</div></div>';
	}

	/**
	 * Conteúdo da página.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão para ver esta página.', 'pb-affiliates' ) );
		}

		$uid = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $uid <= 0 ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Afiliado não encontrado', 'pb-affiliates' ) . '</h1>';
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=pb-affiliates-users' ) ) . '">' . esc_html__( '← Voltar à lista', 'pb-affiliates' ) . '</a></p></div>';
			return;
		}

		$user = get_user_by( 'id', $uid );
		if ( ! $user ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Usuário não encontrado', 'pb-affiliates' ) . '</h1>';
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=pb-affiliates-users' ) ) . '">' . esc_html__( '← Voltar à lista', 'pb-affiliates' ) . '</a></p></div>';
			return;
		}

		$st          = PB_Affiliates_Role::get_affiliate_status( $uid );
		$code        = (string) get_user_meta( $uid, 'pb_affiliate_code', true );
		$list_states = self::parse_list_states();
		$ord         = $list_states['ord'];
		$clk         = $list_states['clk'];

		$orders_data = PB_Affiliates_Reports::get_affiliate_orders_paginated_filtered(
			$uid,
			$ord[ self::Q_ORD_PP ],
			$ord[ self::Q_ORD_P ],
			array(
				'status'    => $ord[ self::Q_ORD_ST ],
				'via'       => $ord[ self::Q_ORD_VIA ],
				'date_from' => $ord[ self::Q_ORD_DF ],
				'date_to'   => $ord[ self::Q_ORD_DT ],
				'orderby'   => $ord[ self::Q_ORD_OB ],
				'order'     => $ord[ self::Q_ORD_DIR ],
			)
		);
		$orders      = $orders_data['orders'];
		$ord_pages   = max( 1, (int) $orders_data['max_pages'] );
		if ( $ord[ self::Q_ORD_P ] > $ord_pages ) {
			$ord[ self::Q_ORD_P ] = $ord_pages;
			$orders_data          = PB_Affiliates_Reports::get_affiliate_orders_paginated_filtered(
				$uid,
				$ord[ self::Q_ORD_PP ],
				$ord[ self::Q_ORD_P ],
				array(
					'status'    => $ord[ self::Q_ORD_ST ],
					'via'       => $ord[ self::Q_ORD_VIA ],
					'date_from' => $ord[ self::Q_ORD_DF ],
					'date_to'   => $ord[ self::Q_ORD_DT ],
					'orderby'   => $ord[ self::Q_ORD_OB ],
					'order'     => $ord[ self::Q_ORD_DIR ],
				)
			);
			$orders = $orders_data['orders'];
		}

		$clk_total = PB_Affiliates_Reports::count_affiliate_clicks_filtered(
			$uid,
			array(
				'via'       => $clk[ self::Q_CLK_VIA ],
				'date_from' => $clk[ self::Q_CLK_DF ],
				'date_to'   => $clk[ self::Q_CLK_DT ],
			)
		);
		$clk_pp    = $clk[ self::Q_CLK_PP ];
		$clk_pages = max( 1, (int) ceil( $clk_total / $clk_pp ) );
		if ( $clk[ self::Q_CLK_P ] > $clk_pages ) {
			$clk[ self::Q_CLK_P ] = $clk_pages;
		}
		$clicks = PB_Affiliates_Reports::get_affiliate_clicks_paged_filtered(
			$uid,
			$clk_pp,
			$clk[ self::Q_CLK_P ],
			array(
				'via'       => $clk[ self::Q_CLK_VIA ],
				'date_from' => $clk[ self::Q_CLK_DF ],
				'date_to'   => $clk[ self::Q_CLK_DT ],
				'orderby'   => $clk[ self::Q_CLK_OB ],
				'order'     => $clk[ self::Q_CLK_DIR ],
			)
		);

		$list_url  = admin_url( 'admin.php?page=pb-affiliates-users' );
		$edit_link = get_edit_user_link( $uid );

		$ord_state = $ord;
		$clk_state = $clk;

		$statuses      = wc_get_order_statuses();
		$via_options = array(
			''             => __( 'Todas as origens', 'pb-affiliates' ),
			'cookie_param' => __( 'Parâmetro na URL', 'pb-affiliates' ),
			'referrer'     => __( 'Domínio verificado (referer)', 'pb-affiliates' ),
			'coupon'       => __( 'Cupom no checkout', 'pb-affiliates' ),
		);

		?>
		<div class="wrap woocommerce pb-aff-user-detail">
			<p class="pb-aff-user-detail__back">
				<a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( '← Lista de afiliados', 'pb-affiliates' ); ?></a>
			</p>
			<h1 class="wp-heading-inline">
				<?php
				echo esc_html( sprintf(
					/* translators: %s: display name */
					__( 'Afiliado: %s', 'pb-affiliates' ),
					$user->display_name
				) );
				?>
				<span class="description">(ID <?php echo (int) $uid; ?>)</span>
			</h1>
			<a href="<?php echo esc_url( $edit_link ); ?>" class="page-title-action"><?php esc_html_e( 'Editar usuário', 'pb-affiliates' ); ?></a>
			<hr class="wp-header-end" />

			<div class="pb-aff-user-detail__summary">
				<p>
					<strong><?php esc_html_e( 'E-mail:', 'pb-affiliates' ); ?></strong>
					<?php echo esc_html( $user->user_email ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Estado no programa:', 'pb-affiliates' ); ?></strong>
					<?php echo esc_html( self::status_label( $st ) ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Código público:', 'pb-affiliates' ); ?></strong>
					<code><?php echo esc_html( $code !== '' ? $code : '—' ); ?></code>
				</p>
			</div>

			<h2><?php esc_html_e( 'Pedidos com atribuição a este afiliado', 'pb-affiliates' ); ?></h2>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="pb-aff-user-detail__filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<input type="hidden" name="user_id" value="<?php echo (int) $uid; ?>" />
				<?php
				foreach ( $clk_state as $qk => $qv ) {
					printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $qk ), esc_attr( (string) $qv ) );
				}
				?>
				<input type="hidden" name="<?php echo esc_attr( self::Q_ORD_P ); ?>" value="1" />
				<input type="hidden" name="<?php echo esc_attr( self::Q_ORD_OB ); ?>" value="<?php echo esc_attr( $ord[ self::Q_ORD_OB ] ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( self::Q_ORD_DIR ); ?>" value="<?php echo esc_attr( $ord[ self::Q_ORD_DIR ] ); ?>" />

				<label class="pb-aff-filter-field">
					<span><?php esc_html_e( 'Estado do pedido', 'pb-affiliates' ); ?></span>
					<select name="<?php echo esc_attr( self::Q_ORD_ST ); ?>">
						<option value=""><?php esc_html_e( 'Todos', 'pb-affiliates' ); ?></option>
						<?php foreach ( $statuses as $st_key => $st_label ) : ?>
							<?php
							$slug = 0 === strpos( $st_key, 'wc-' ) ? substr( $st_key, 3 ) : $st_key;
							?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $ord[ self::Q_ORD_ST ], $slug ); ?>><?php echo esc_html( $st_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="pb-aff-filter-field">
					<span><?php esc_html_e( 'Origem (atribuição)', 'pb-affiliates' ); ?></span>
					<select name="<?php echo esc_attr( self::Q_ORD_VIA ); ?>">
						<?php foreach ( $via_options as $vk => $vl ) : ?>
							<option value="<?php echo esc_attr( $vk ); ?>" <?php selected( $ord[ self::Q_ORD_VIA ], $vk ); ?>><?php echo esc_html( $vl ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="pb-aff-filter-field">
					<span><?php esc_html_e( 'Data criação (de)', 'pb-affiliates' ); ?></span>
					<input type="date" name="<?php echo esc_attr( self::Q_ORD_DF ); ?>" value="<?php echo esc_attr( $ord[ self::Q_ORD_DF ] ); ?>" />
				</label>
				<label class="pb-aff-filter-field">
					<span><?php esc_html_e( 'Data criação (até)', 'pb-affiliates' ); ?></span>
					<input type="date" name="<?php echo esc_attr( self::Q_ORD_DT ); ?>" value="<?php echo esc_attr( $ord[ self::Q_ORD_DT ] ); ?>" />
				</label>
				<label class="pb-aff-filter-field">
					<span><?php esc_html_e( 'Por página', 'pb-affiliates' ); ?></span>
					<select name="<?php echo esc_attr( self::Q_ORD_PP ); ?>">
						<?php foreach ( array( 10, 15, 25, 50, 100 ) as $n ) : ?>
							<option value="<?php echo (int) $n; ?>" <?php selected( (int) $ord[ self::Q_ORD_PP ], $n ); ?>><?php echo (int) $n; ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Aplicar filtros', 'pb-affiliates' ); ?></button>
			</form>

			<?php if ( empty( $orders ) ) : ?>
				<p><?php esc_html_e( 'Nenhum pedido encontrado.', 'pb-affiliates' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Pedido', 'pb-affiliates' ); ?></th>
							<th>
								<?php
								$ord_sort_date = $ord_state;
								$ord_sort_date[ self::Q_ORD_OB ]  = 'date';
								$ord_sort_date[ self::Q_ORD_DIR ] = self::next_sort_dir( 'date', $ord[ self::Q_ORD_OB ], $ord[ self::Q_ORD_DIR ] );
								$ord_sort_date[ self::Q_ORD_P ]   = 1;
								?>
								<a href="<?php echo esc_url( self::aud_url( $uid, $ord_sort_date, $clk_state ) ); ?>">
									<?php echo esc_html__( 'Data', 'pb-affiliates' ) . esc_html( self::sort_indicator( 'date', $ord[ self::Q_ORD_OB ], $ord[ self::Q_ORD_DIR ] ) ); ?>
								</a>
							</th>
							<th><?php esc_html_e( 'Estado', 'pb-affiliates' ); ?></th>
							<th>
								<?php
								$ord_sort_tot = $ord_state;
								$ord_sort_tot[ self::Q_ORD_OB ]  = 'total';
								$ord_sort_tot[ self::Q_ORD_DIR ] = self::next_sort_dir( 'total', $ord[ self::Q_ORD_OB ], $ord[ self::Q_ORD_DIR ] );
								$ord_sort_tot[ self::Q_ORD_P ]   = 1;
								?>
								<a href="<?php echo esc_url( self::aud_url( $uid, $ord_sort_tot, $clk_state ) ); ?>">
									<?php echo esc_html__( 'Total', 'pb-affiliates' ) . esc_html( self::sort_indicator( 'total', $ord[ self::Q_ORD_OB ], $ord[ self::Q_ORD_DIR ] ) ); ?>
								</a>
							</th>
							<th><?php esc_html_e( 'Comissão', 'pb-affiliates' ); ?></th>
							<th><?php esc_html_e( 'Origem', 'pb-affiliates' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $orders as $order ) {
							if ( ! $order instanceof WC_Order ) {
								continue;
							}
							$via      = (string) $order->get_meta( '_pb_attribution_source' );
							$comm_raw = $order->get_meta( '_pb_commission_amount' );
							$via_show = class_exists( 'PB_Affiliates_Admin_Click_Report' )
								? PB_Affiliates_Admin_Click_Report::human_label_for_via( $via )
								: $via;
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
										#<?php echo esc_html( (string) $order->get_order_number() ); ?>
									</a>
								</td>
								<td>
									<?php
									echo esc_html(
										$order->get_date_created()
											? $order->get_date_created()->date_i18n( wc_date_format() . ' ' . wc_time_format() )
											: '—'
									);
									?>
								</td>
								<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
								<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
								<td>
									<?php
									if ( $comm_raw !== '' && $comm_raw !== null ) {
										echo wp_kses_post( wc_price( (float) $comm_raw, array( 'currency' => $order->get_currency() ) ) );
									} else {
										echo '—';
									}
									?>
								</td>
								<td><?php echo esc_html( $via_show ); ?><?php echo $via && $via !== $via_show ? ' <code>' . esc_html( $via ) . '</code>' : ''; ?></td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
				<p class="pb-aff-list-meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: current page 2: total pages 3: result count */
							__( 'Página %1$d de %2$d — %3$d pedido(s) no filtro', 'pb-affiliates' ),
							(int) $ord[ self::Q_ORD_P ],
							$ord_pages,
							(int) $orders_data['total']
						)
					);
					?>
				</p>
				<?php
				self::render_pagination(
					$ord[ self::Q_ORD_P ],
					$ord_pages,
					function ( $p ) use ( $uid, $clk_state, $ord_state ) {
						$o = $ord_state;
						$o[ self::Q_ORD_P ] = (int) $p;
						return self::aud_url( $uid, $o, $clk_state );
					}
				);
				?>
			<?php endif; ?>

			<h2 style="margin-top:2em"><?php esc_html_e( 'Cliques registrados', 'pb-affiliates' ); ?></h2>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="pb-aff-user-detail__filters">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<input type="hidden" name="user_id" value="<?php echo (int) $uid; ?>" />
				<?php
				foreach ( $ord_state as $qk => $qv ) {
					printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $qk ), esc_attr( (string) $qv ) );
				}
				?>
				<input type="hidden" name="<?php echo esc_attr( self::Q_CLK_P ); ?>" value="1" />
				<input type="hidden" name="<?php echo esc_attr( self::Q_CLK_OB ); ?>" value="<?php echo esc_attr( $clk[ self::Q_CLK_OB ] ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( self::Q_CLK_DIR ); ?>" value="<?php echo esc_attr( $clk[ self::Q_CLK_DIR ] ); ?>" />

				<label class="pb-aff-filter-field">
					<span><?php esc_html_e( 'Origem', 'pb-affiliates' ); ?></span>
					<select name="<?php echo esc_attr( self::Q_CLK_VIA ); ?>">
						<?php foreach ( $via_options as $vk => $vl ) : ?>
							<option value="<?php echo esc_attr( $vk ); ?>" <?php selected( $clk[ self::Q_CLK_VIA ], $vk ); ?>><?php echo esc_html( $vl ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="pb-aff-filter-field">
					<span><?php esc_html_e( 'Data (de)', 'pb-affiliates' ); ?></span>
					<input type="date" name="<?php echo esc_attr( self::Q_CLK_DF ); ?>" value="<?php echo esc_attr( $clk[ self::Q_CLK_DF ] ); ?>" />
				</label>
				<label class="pb-aff-filter-field">
					<span><?php esc_html_e( 'Data (até)', 'pb-affiliates' ); ?></span>
					<input type="date" name="<?php echo esc_attr( self::Q_CLK_DT ); ?>" value="<?php echo esc_attr( $clk[ self::Q_CLK_DT ] ); ?>" />
				</label>
				<label class="pb-aff-filter-field">
					<span><?php esc_html_e( 'Por página', 'pb-affiliates' ); ?></span>
					<select name="<?php echo esc_attr( self::Q_CLK_PP ); ?>">
						<?php foreach ( array( 10, 15, 25, 50, 100 ) as $n ) : ?>
							<option value="<?php echo (int) $n; ?>" <?php selected( (int) $clk[ self::Q_CLK_PP ], $n ); ?>><?php echo (int) $n; ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Aplicar filtros', 'pb-affiliates' ); ?></button>
			</form>

			<?php if ( empty( $clicks ) ) : ?>
				<p><?php esc_html_e( 'Nenhum clique registrado no banco de dados para este filtro.', 'pb-affiliates' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>
								<?php
								$c1 = $clk_state;
								$c1[ self::Q_CLK_OB ]  = 'hit_at';
								$c1[ self::Q_CLK_DIR ] = self::next_sort_dir( 'hit_at', $clk[ self::Q_CLK_OB ], $clk[ self::Q_CLK_DIR ] );
								$c1[ self::Q_CLK_P ]   = 1;
								?>
								<a href="<?php echo esc_url( self::aud_url( $uid, $ord_state, $c1 ) ); ?>">
									<?php echo esc_html__( 'Data e hora', 'pb-affiliates' ) . esc_html( self::sort_indicator( 'hit_at', $clk[ self::Q_CLK_OB ], $clk[ self::Q_CLK_DIR ] ) ); ?>
								</a>
							</th>
							<th>
								<?php
								$c2 = $clk_state;
								$c2[ self::Q_CLK_OB ]  = 'via';
								$c2[ self::Q_CLK_DIR ] = self::next_sort_dir( 'via', $clk[ self::Q_CLK_OB ], $clk[ self::Q_CLK_DIR ] );
								$c2[ self::Q_CLK_P ]   = 1;
								?>
								<a href="<?php echo esc_url( self::aud_url( $uid, $ord_state, $c2 ) ); ?>">
									<?php echo esc_html__( 'Origem', 'pb-affiliates' ) . esc_html( self::sort_indicator( 'via', $clk[ self::Q_CLK_OB ], $clk[ self::Q_CLK_DIR ] ) ); ?>
								</a>
							</th>
							<th>
								<?php
								$c3 = $clk_state;
								$c3[ self::Q_CLK_OB ]  = 'client_ip';
								$c3[ self::Q_CLK_DIR ] = self::next_sort_dir( 'client_ip', $clk[ self::Q_CLK_OB ], $clk[ self::Q_CLK_DIR ] );
								$c3[ self::Q_CLK_P ]   = 1;
								?>
								<a href="<?php echo esc_url( self::aud_url( $uid, $ord_state, $c3 ) ); ?>">
									<?php echo esc_html__( 'IP', 'pb-affiliates' ) . esc_html( self::sort_indicator( 'client_ip', $clk[ self::Q_CLK_OB ], $clk[ self::Q_CLK_DIR ] ) ); ?>
								</a>
							</th>
							<th><?php esc_html_e( 'URL', 'pb-affiliates' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $clicks as $hit ) : ?>
							<tr>
								<td>
									<?php
									$at = isset( $hit['hit_at'] ) ? (string) $hit['hit_at'] : '';
									echo esc_html( $at ? mysql2date( wc_date_format() . ' ' . wc_time_format(), $at ) : '' );
									?>
								</td>
								<td>
									<?php
									$v = isset( $hit['via'] ) ? (string) $hit['via'] : '';
									if ( $v ) {
										$human = class_exists( 'PB_Affiliates_Admin_Click_Report' )
											? PB_Affiliates_Admin_Click_Report::human_label_for_via( $v )
											: $v;
										echo esc_html( $human );
										if ( $human !== $v ) {
											echo ' <code>' . esc_html( $v ) . '</code>';
										}
									} else {
										echo '—';
									}
									?>
								</td>
								<td><code><?php echo esc_html( isset( $hit['client_ip'] ) ? (string) $hit['client_ip'] : '' ); ?></code></td>
								<td class="pb-aff-user-detail__url-cell">
									<?php
									$uu = isset( $hit['visited_url'] ) ? (string) $hit['visited_url'] : '';
									if ( $uu ) {
										$short = strlen( $uu ) > 90 ? substr( $uu, 0, 90 ) . '…' : $uu;
										echo '<a href="' . esc_url( $uu ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $uu ) . '">' . esc_html( $short ) . '</a>';
									} else {
										echo '—';
									}
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="pb-aff-list-meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: page 2: total pages 3: matched rows */
							__( 'Página %1$d de %2$d — %3$d clique(s) no filtro', 'pb-affiliates' ),
							(int) $clk[ self::Q_CLK_P ],
							$clk_pages,
							(int) $clk_total
						)
					);
					?>
				</p>
				<?php
				self::render_pagination(
					$clk[ self::Q_CLK_P ],
					$clk_pages,
					function ( $p ) use ( $uid, $clk_state, $ord_state ) {
						$c = $clk_state;
						$c[ self::Q_CLK_P ] = (int) $p;
						return self::aud_url( $uid, $ord_state, $c );
					}
				);
				?>
			<?php endif; ?>
		</div>
		<style>
			.pb-aff-user-detail__summary{max-width:640px;margin:1em 0 1.5em;padding:12px 14px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px}
			.pb-aff-user-detail__summary p{margin:.35em 0}
			.pb-aff-user-detail__back{margin:.5em 0}
			.pb-aff-user-detail__url-cell{word-break:break-all;max-width:360px;font-size:12px}
			.pb-aff-user-detail__filters{display:flex;flex-wrap:wrap;align-items:flex-end;gap:10px 16px;margin:12px 0 16px}
			.pb-aff-filter-field{display:flex;flex-direction:column;gap:4px}
			.pb-aff-filter-field span{font-size:12px;color:#50575e}
			.pb-aff-list-meta{color:#50575e;margin:.75em 0}
			.pb-aff-user-detail thead th a{text-decoration:none}
		</style>
		<?php
	}

	/**
	 * @param string $st Status.
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
