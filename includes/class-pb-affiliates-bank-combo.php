<?php
/**
 * Assets + markup for bank code combobox (api-bancos JSON).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Bank_Combo
 */
class PB_Affiliates_Bank_Combo {

	/**
	 * Lista oficial (JSON) — api-bancos.
	 */
	const BANK_JSON_URL = 'https://cdn.jsdelivr.net/gh/r-martins/api-bancos@master/json/all.json';

	/**
	 * Init hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_front' ), 25 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ), 25 );
	}

	/**
	 * @return string
	 */
	public static function get_bank_json_url() {
		/**
		 * URL do JSON de bancos para o combobox.
		 *
		 * @param string $url Default CDN api-bancos.
		 */
		return apply_filters( 'pb_affiliates_bank_json_url', self::BANK_JSON_URL );
	}

	/**
	 * Front: Minha conta → editar conta, modo manual, afiliado ativo.
	 */
	public static function enqueue_front() {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}
		if ( 'manual' !== ( PB_Affiliates_Settings::get()['payment_mode'] ?? 'manual' ) ) {
			return;
		}
		if ( ! PB_Affiliates_Account::user_can_edit_payment_fields( get_current_user_id() ) ) {
			return;
		}
		// Não exigir is_wc_endpoint_url( 'edit-account' ): em alguns temas/permalinks falha e o JS não carrega.
		self::enqueue_assets();
	}

	/**
	 * Admin: editar usuário (dados bancários modo manual).
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_admin( $hook_suffix ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		global $pagenow;
		// $hook_suffix varia entre versões WP; $pagenow é fiável.
		if ( ! in_array( (string) $pagenow, array( 'user-edit.php', 'profile.php' ), true ) ) {
			return;
		}
		if ( 'manual' !== ( PB_Affiliates_Settings::get()['payment_mode'] ?? 'manual' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		self::enqueue_assets();
	}

	/**
	 * Register scripts/styles.
	 */
	private static function enqueue_assets() {
		wp_enqueue_style(
			'pb-aff-bank-combo',
			PB_AFFILIATES_URL . 'assets/css/bank-combobox.css',
			array(),
			PB_AFFILIATES_VERSION
		);
		wp_enqueue_script(
			'pb-aff-bank-combo',
			PB_AFFILIATES_URL . 'assets/js/bank-combobox.js',
			array(),
			PB_AFFILIATES_VERSION,
			true
		);
		wp_localize_script(
			'pb-aff-bank-combo',
			'pbAffBankCombo',
			array(
				'url'     => esc_url_raw( self::get_bank_json_url() ),
				'loading' => __( 'Carregando bancos…', 'pb-affiliates' ),
				'error'   => __( 'Não foi possível carregar a lista de bancos.', 'pb-affiliates' ),
				'empty'   => __( 'Nenhum banco encontrado.', 'pb-affiliates' ),
			)
		);
		wp_enqueue_script(
			'pb-aff-cpf-cnpj-mask',
			PB_AFFILIATES_URL . 'assets/js/cpf-cnpj-mask.js',
			array(),
			PB_AFFILIATES_VERSION,
			true
		);
	}

	/**
	 * Markup do campo código do banco (hidden + busca).
	 *
	 * @param string $stored_code Valor salvo (apenas código).
	 * @param string $context     front|admin.
	 */
	public static function render_bank_code_field( $stored_code, $context = 'front' ) {
		$stored_code = (string) $stored_code;
		$is_front    = 'front' === $context;
		$label_for   = 'pb_affiliate_bank_code_display';
		$input_class = $is_front
			? 'woocommerce-Input woocommerce-Input--text input-text pb-aff-bank-search'
			: 'regular-text pb-aff-bank-search';

		if ( $is_front ) {
			?>
			<div class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide pb-aff-bank-form-row">
				<label for="<?php echo esc_attr( $label_for ); ?>"><?php esc_html_e( 'Número do banco', 'pb-affiliates' ); ?></label>
				<div class="pb-aff-bank-field" data-pb-bank-combo>
					<input type="hidden" name="pb_affiliate_bank_code" id="pb_affiliate_bank_code" value="<?php echo esc_attr( $stored_code ); ?>" />
					<input type="text" id="<?php echo esc_attr( $label_for ); ?>" class="<?php echo esc_attr( $input_class ); ?>" value="" autocomplete="off" placeholder="<?php esc_attr_e( 'Buscar por código ou nome…', 'pb-affiliates' ); ?>" />
					<ul class="pb-aff-bank-dropdown" hidden role="listbox"></ul>
				</div>
			</div>
			<?php
			return;
		}

		?>
		<div class="pb-aff-bank-admin-row">
			<label for="<?php echo esc_attr( $label_for ); ?>"><?php esc_html_e( 'Número do banco', 'pb-affiliates' ); ?></label><br />
			<div class="pb-aff-bank-field" data-pb-bank-combo>
				<input type="hidden" name="pb_affiliate_bank_code" id="pb_affiliate_bank_code" value="<?php echo esc_attr( $stored_code ); ?>" />
				<input type="text" id="<?php echo esc_attr( $label_for ); ?>" class="<?php echo esc_attr( $input_class ); ?>" value="" autocomplete="off" placeholder="<?php esc_attr_e( 'Buscar por código ou nome…', 'pb-affiliates' ); ?>" />
				<ul class="pb-aff-bank-dropdown" hidden role="listbox"></ul>
			</div>
		</div>
		<?php
	}
}
