<?php
/**
 * Coupon meta: affiliate commission override.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Coupon
 */
class PB_Affiliates_Coupon {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'woocommerce_coupon_options', array( __CLASS__, 'coupon_options' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( __CLASS__, 'coupon_save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_coupon_affiliate_search' ), 20 );
		add_action( 'wp_ajax_pb_affiliates_search_affiliates', array( __CLASS__, 'ajax_search_affiliates' ) );
	}

	/**
	 * Scripts do combobox de afiliado (tela do cupom).
	 *
	 * @param string $hook_suffix Hook (não usado — preferimos screen).
	 */
	public static function enqueue_coupon_affiliate_search( $hook_suffix ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'shop_coupon' !== $screen->id ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		wp_enqueue_style(
			'pb-aff-bank-combo',
			PB_AFFILIATES_URL . 'assets/css/bank-combobox.css',
			array(),
			PB_AFFILIATES_VERSION
		);
		wp_enqueue_script(
			'pb-aff-coupon-affiliate-search',
			PB_AFFILIATES_URL . 'assets/js/affiliate-coupon-search.js',
			array(),
			PB_AFFILIATES_VERSION,
			true
		);
		wp_localize_script(
			'pb-aff-coupon-affiliate-search',
			'pbAffCouponAffiliate',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'pb_aff_coupon_affiliate' ),
				'empty'       => __( 'Nenhum afiliado encontrado.', 'pb-affiliates' ),
				'error'       => __( 'Erro ao buscar afiliados.', 'pb-affiliates' ),
				'placeholder' => __( 'Buscar por ID, código público, e-mail ou nome…', 'pb-affiliates' ),
			)
		);
	}

	/**
	 * AJAX: resultados para o combobox (afiliados ativos).
	 */
	public static function ajax_search_affiliates() {
		check_ajax_referer( 'pb_aff_coupon_affiliate', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'no_cap' ), 403 );
		}
		$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
		$rows = self::search_affiliate_users( $term, 25 );
		wp_send_json_success( array( 'results' => $rows ) );
	}

	/**
	 * Busca usuários com estado de afiliado ativo.
	 *
	 * @param string $term  Texto (ID numérico, e-mail, login, nome, código).
	 * @param int    $limit Máximo de linhas.
	 * @return array<int,array{id:int,label:string}>
	 */
	private static function search_affiliate_users( $term, $limit = 25 ) {
		global $wpdb;
		$term = trim( (string) $term );
		if ( '' === $term ) {
			return array();
		}
		$limit = max( 1, min( 50, (int) $limit ) );
		$like  = '%' . $wpdb->esc_like( $term ) . '%';

		$id_guess = ctype_digit( $term ) ? (int) $term : 0;

		if ( $id_guess > 0 ) {
			$sql = $wpdb->prepare(
				"SELECT DISTINCT u.ID, u.user_email, u.display_name, cd.meta_value AS aff_code
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} st ON st.user_id = u.ID AND st.meta_key = %s AND st.meta_value = %s
				LEFT JOIN {$wpdb->usermeta} cd ON cd.user_id = u.ID AND cd.meta_key = %s
				WHERE (
					u.ID = %d
					OR u.user_email LIKE %s
					OR u.user_login LIKE %s
					OR u.display_name LIKE %s
					OR cd.meta_value LIKE %s
				)
				ORDER BY u.display_name ASC
				LIMIT %d",
				'pb_affiliate_status',
				PB_Affiliates_Role::STATUS_ACTIVE,
				'pb_affiliate_code',
				$id_guess,
				$like,
				$like,
				$like,
				$like,
				$limit
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT DISTINCT u.ID, u.user_email, u.display_name, cd.meta_value AS aff_code
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->usermeta} st ON st.user_id = u.ID AND st.meta_key = %s AND st.meta_value = %s
				LEFT JOIN {$wpdb->usermeta} cd ON cd.user_id = u.ID AND cd.meta_key = %s
				WHERE (
					u.user_email LIKE %s
					OR u.user_login LIKE %s
					OR u.display_name LIKE %s
					OR cd.meta_value LIKE %s
				)
				ORDER BY u.display_name ASC
				LIMIT %d",
				'pb_affiliate_status',
				PB_Affiliates_Role::STATUS_ACTIVE,
				'pb_affiliate_code',
				$like,
				$like,
				$like,
				$like,
				$limit
			);
		}

		$rows = $wpdb->get_results( $sql );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$uid = isset( $row->ID ) ? (int) $row->ID : 0;
			if ( $uid <= 0 ) {
				continue;
			}
			$code = isset( $row->aff_code ) ? (string) $row->aff_code : '';
			$out[] = array(
				'id'    => $uid,
				'label' => sprintf(
					/* translators: 1: user ID 2: affiliate public code 3: email 4: display name */
					__( '#%1$d — %2$s — %3$s — %4$s', 'pb-affiliates' ),
					$uid,
					'' !== $code ? $code : '—',
					(string) $row->user_email,
					(string) $row->display_name
				),
			);
		}
		return $out;
	}

	/**
	 * Texto inicial do campo de busca quando já há ID guardado.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function get_affiliate_display_label( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return '';
		}
		$user = get_userdata( $user_id );
		if ( ! $user || ! PB_Affiliates_Role::user_is_affiliate( $user_id ) ) {
			return sprintf(
				/* translators: %d: user ID */
				__( 'ID %d (não é afiliado ativo)', 'pb-affiliates' ),
				$user_id
			);
		}
		$code = (string) get_user_meta( $user_id, 'pb_affiliate_code', true );
		return sprintf(
			/* translators: 1: user ID 2: affiliate public code 3: email 4: display name */
			__( '#%1$d — %2$s — %3$s — %4$s', 'pb-affiliates' ),
			$user_id,
			'' !== $code ? $code : '—',
			$user->user_email,
			$user->display_name
		);
	}

	/**
	 * Extra fields on coupon edit.
	 *
	 * @param int       $coupon_id Coupon ID.
	 * @param WC_Coupon $coupon Coupon.
	 */
	public static function coupon_options( $coupon_id, $coupon ) {
		$stored_id   = (int) $coupon->get_meta( '_pb_affiliate_id' );
		$display_val = self::get_affiliate_display_label( $stored_id );

		echo '<div class="options_group">';
		echo '<div class="form-field _pb_affiliate_id_field">';
		echo '<label for="pb_aff_coupon_affiliate_display">' . esc_html__( 'Afiliado', 'pb-affiliates' ) . '</label>';
		echo wp_kses_post( wc_help_tip( __( 'Afiliado que recebe comissão quando este cupom for usado. Busque por ID numérico, código público, e-mail ou nome.', 'pb-affiliates' ) ) );
		echo '<div class="pb-aff-bank-field pb-aff-affiliate-coupon-field" data-pb-affiliate-coupon-combo>';
		echo '<input type="hidden" name="_pb_affiliate_id" id="_pb_affiliate_id" value="' . esc_attr( (string) $stored_id ) . '" />';
		echo '<input type="text" id="pb_aff_coupon_affiliate_display" class="pb-aff-bank-search pb-aff-coupon-affiliate-search short" autocomplete="off" data-pb-display="' . esc_attr( $display_val ) . '" value="" placeholder="' . esc_attr__( 'Buscar por ID, código público, e-mail ou nome…', 'pb-affiliates' ) . '" />';
		echo '<ul class="pb-aff-bank-dropdown pb-aff-coupon-affiliate-dropdown" hidden role="listbox"></ul>';
		echo '</div></div>';
		woocommerce_wp_select(
			array(
				'id'      => '_pb_affiliate_commission_type',
				'label'   => __( 'Tipo de comissão (cupom)', 'pb-affiliates' ),
				'options' => array(
					''        => __( '— Usar padrão do afiliado —', 'pb-affiliates' ),
					'percent' => __( 'Percentual', 'pb-affiliates' ),
					'fixed'   => __( 'Valor fixo', 'pb-affiliates' ),
				),
				'value'   => $coupon->get_meta( '_pb_affiliate_commission_type' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'    => '_pb_affiliate_commission_value',
				'label' => __( 'Valor da comissão', 'pb-affiliates' ),
				'type'  => 'text',
				'value' => $coupon->get_meta( '_pb_affiliate_commission_value' ),
			)
		);
		echo '</div>';
	}

	/**
	 * Save coupon meta.
	 *
	 * @param int       $post_id Post ID.
	 * @param WC_Coupon $coupon Coupon.
	 */
	public static function coupon_save( $post_id, $coupon ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['_pb_affiliate_id'] ) ) {
			$aid = absint( wp_unslash( $_POST['_pb_affiliate_id'] ) );
			if ( $aid > 0 ) {
				$coupon->update_meta_data( '_pb_affiliate_id', $aid );
			} else {
				$coupon->delete_meta_data( '_pb_affiliate_id' );
			}
		}
		if ( isset( $_POST['_pb_affiliate_commission_type'] ) ) {
			$coupon->update_meta_data( '_pb_affiliate_commission_type', sanitize_text_field( wp_unslash( $_POST['_pb_affiliate_commission_type'] ) ) );
		}
		if ( isset( $_POST['_pb_affiliate_commission_value'] ) ) {
			$coupon->update_meta_data( '_pb_affiliate_commission_value', wc_format_decimal( wp_unslash( $_POST['_pb_affiliate_commission_value'] ) ) );
		}
		$coupon->save_meta_data();
	}
}
