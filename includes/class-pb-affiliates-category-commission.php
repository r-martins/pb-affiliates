<?php
/**
 * Comissão por categoria de produto (term meta em product_cat).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Category_Commission
 */
class PB_Affiliates_Category_Commission {

	const META_TYPE  = 'pb_aff_cat_commission_type';

	const META_VALUE = 'pb_aff_cat_commission_value';

	/**
	 * Registra campos e gravação.
	 */
	public static function init() {
		// Depois dos campos do WooCommerce (display_type, miniatura), que usam prioridade 10.
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'render_add_fields' ), 20 );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'render_edit_fields' ), 20, 2 );
		add_action( 'created_product_cat', array( __CLASS__, 'save_term' ), 10, 2 );
		add_action( 'edited_product_cat', array( __CLASS__, 'save_term' ), 10, 2 );
	}

	/**
	 * @return void
	 */
	public static function render_add_fields() {
		wp_nonce_field( 'pb_aff_save_cat_commission', 'pb_aff_cat_commission_nonce' );
		?>
		<div class="form-field term-group">
			<h2><?php esc_html_e( 'Comissão de afiliados (PB Afiliados)', 'pb-affiliates' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Opcional: defina uma comissão específica para produtos desta categoria. Ignorado quando o pedido usa regra de cupom de afiliado ou comissão personalizada no perfil do afiliado.', 'pb-affiliates' ); ?>
			</p>
			<label><?php esc_html_e( 'Modo', 'pb-affiliates' ); ?></label>
			<select name="pb_aff_cat_commission_mode" id="pb_aff_cat_commission_mode">
				<option value="default"><?php esc_html_e( 'Usar comissão padrão da loja', 'pb-affiliates' ); ?></option>
				<option value="custom"><?php esc_html_e( 'Personalizar para esta categoria', 'pb-affiliates' ); ?></option>
			</select>
		</div>
		<div class="form-field term-group">
			<label for="pb_aff_cat_commission_type"><?php esc_html_e( 'Tipo (personalizado)', 'pb-affiliates' ); ?></label>
			<select name="pb_aff_cat_commission_type" id="pb_aff_cat_commission_type">
				<option value="percent"><?php esc_html_e( 'Percentual', 'pb-affiliates' ); ?></option>
				<option value="fixed"><?php esc_html_e( 'Valor fixo (por item / fração da base)', 'pb-affiliates' ); ?></option>
			</select>
		</div>
		<div class="form-field term-group">
			<label for="pb_aff_cat_commission_value"><?php esc_html_e( 'Valor', 'pb-affiliates' ); ?></label>
			<input name="pb_aff_cat_commission_value" id="pb_aff_cat_commission_value" type="text" value="" />
			<p class="description"><?php esc_html_e( 'Percentual: ex. 10 (significa 10%). Fixo: valor na moeda da loja, aplicado proporcionalmente por linha conforme a base do pedido.', 'pb-affiliates' ); ?></p>
		</div>
		<?php
	}

	/**
	 * @param WP_Term $term Term.
	 */
	public static function render_edit_fields( $term ) {
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		wp_nonce_field( 'pb_aff_save_cat_commission', 'pb_aff_cat_commission_nonce' );
		$type  = (string) get_term_meta( $term->term_id, self::META_TYPE, true );
		$value = get_term_meta( $term->term_id, self::META_VALUE, true );
		$mode  = $type ? 'custom' : 'default';
		?>
		<tr class="form-field">
			<th scope="row" colspan="2"><strong><?php esc_html_e( 'Comissão de afiliados (PB Afiliados)', 'pb-affiliates' ); ?></strong></th>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="pb_aff_cat_commission_mode"><?php esc_html_e( 'Modo', 'pb-affiliates' ); ?></label></th>
			<td>
				<select name="pb_aff_cat_commission_mode" id="pb_aff_cat_commission_mode">
					<option value="default" <?php selected( $mode, 'default' ); ?>><?php esc_html_e( 'Usar comissão padrão da loja', 'pb-affiliates' ); ?></option>
					<option value="custom" <?php selected( $mode, 'custom' ); ?>><?php esc_html_e( 'Personalizar para esta categoria', 'pb-affiliates' ); ?></option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Se um produto estiver em várias categorias com regra própria, será aplicada a comissão que resultar no menor valor para aquela linha do pedido.', 'pb-affiliates' ); ?>
				</p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="pb_aff_cat_commission_type"><?php esc_html_e( 'Tipo (personalizado)', 'pb-affiliates' ); ?></label></th>
			<td>
				<select name="pb_aff_cat_commission_type" id="pb_aff_cat_commission_type">
					<option value="percent" <?php selected( $type, 'percent' ); ?>><?php esc_html_e( 'Percentual', 'pb-affiliates' ); ?></option>
					<option value="fixed" <?php selected( $type, 'fixed' ); ?>><?php esc_html_e( 'Valor fixo', 'pb-affiliates' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="pb_aff_cat_commission_value"><?php esc_html_e( 'Valor', 'pb-affiliates' ); ?></label></th>
			<td>
				<input name="pb_aff_cat_commission_value" id="pb_aff_cat_commission_value" type="text" value="<?php echo esc_attr( is_numeric( $value ) ? (string) wc_format_decimal( $value ) : '' ); ?>" />
				<p class="description"><?php esc_html_e( 'Percentual sobre a base alocada à linha (ex.: 10 = 10%). Valor fixo: limitado à base da linha e distribuído conforme o subtotal dos itens no pedido.', 'pb-affiliates' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * @param int $term_id Term ID.
	 */
	public static function save_term( $term_id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( empty( $_POST['pb_aff_cat_commission_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pb_aff_cat_commission_nonce'] ) ), 'pb_aff_save_cat_commission' ) ) {
			return;
		}
		$mode = isset( $_POST['pb_aff_cat_commission_mode'] ) ? sanitize_key( wp_unslash( $_POST['pb_aff_cat_commission_mode'] ) ) : 'default';
		if ( 'custom' !== $mode ) {
			delete_term_meta( $term_id, self::META_TYPE );
			delete_term_meta( $term_id, self::META_VALUE );
			return;
		}
		$type = isset( $_POST['pb_aff_cat_commission_type'] ) ? sanitize_key( wp_unslash( $_POST['pb_aff_cat_commission_type'] ) ) : '';
		$val  = isset( $_POST['pb_aff_cat_commission_value'] ) ? wp_unslash( $_POST['pb_aff_cat_commission_value'] ) : '';
		$val  = (float) wc_format_decimal( is_string( $val ) ? $val : '' );
		if ( ! in_array( $type, array( 'percent', 'fixed' ), true ) ) {
			return;
		}
		if ( $val < 0 ) {
			return;
		}
		update_term_meta( $term_id, self::META_TYPE, $type );
		update_term_meta( $term_id, self::META_VALUE, wc_format_decimal( $val ) );
	}

	/**
	 * IDS de categorias com meta válida para um produto (pai se for variação).
	 *
	 * @param int $product_id Product or variation ID.
	 * @return array<int, array{type:string,value:float}>
	 */
	public static function get_category_override_rates_for_product( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return array();
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}
		$lookup_id = $product->is_type( 'variation' ) ? (int) $product->get_parent_id() : (int) $product->get_id();
		if ( $lookup_id <= 0 ) {
			return array();
		}
		$terms = wp_get_post_terms( $lookup_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) $terms as $tid ) {
			$tid = (int) $tid;
			if ( $tid <= 0 ) {
				continue;
			}
			$t = (string) get_term_meta( $tid, self::META_TYPE, true );
			$v = get_term_meta( $tid, self::META_VALUE, true );
			$v = (float) wc_format_decimal( is_numeric( $v ) ? (string) $v : '0' );
			if ( ! in_array( $t, array( 'percent', 'fixed' ), true ) ) {
				continue;
			}
			if ( $v < 0 ) {
				continue;
			}
			$out[] = array( 'type' => $t, 'value' => $v );
		}
		return $out;
	}

	/**
	 * Escolhe a regra de comissão que produz o menor valor para a linha (entre categorias com override).
	 *
	 * @param int   $product_id Product or variation ID.
	 * @param float $line_base  Base alocada à linha.
	 * @param array $fallback   {type,value} comissão padrão da loja.
	 * @return array{type:string,value:float}
	 */
	public static function get_lowest_commission_rate_for_product_line( $product_id, $line_base, array $fallback ) {
		$candidates = self::get_category_override_rates_for_product( $product_id );
		if ( empty( $candidates ) ) {
			return $fallback;
		}
		$line_base = (float) $line_base;
		$best      = $candidates[0];
		$best_amt  = PB_Affiliates_Commission::apply_rate_to_base( $line_base, $best );
		$n         = count( $candidates );
		for ( $i = 1; $i < $n; $i++ ) {
			$amt = PB_Affiliates_Commission::apply_rate_to_base( $line_base, $candidates[ $i ] );
			if ( $amt < $best_amt ) {
				$best_amt = $amt;
				$best     = $candidates[ $i ];
			}
		}
		return $best;
	}
}
