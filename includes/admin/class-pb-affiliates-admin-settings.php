<?php
/**
 * Settings page (WooCommerce submenu).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Admin_Settings
 */
class PB_Affiliates_Admin_Settings {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Scripts and styles (settings screen only).
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( ! self::is_settings_admin_screen( $hook_suffix ) ) {
			return;
		}
		wp_enqueue_style(
			'pb-aff-admin-settings',
			PB_AFFILIATES_URL . 'assets/css/admin-settings.css',
			array(),
			PB_AFFILIATES_VERSION
		);
		wp_enqueue_script(
			'pb-aff-admin-settings',
			PB_AFFILIATES_URL . 'assets/js/admin-settings.js',
			array(),
			PB_AFFILIATES_VERSION,
			true
		);
	}

	/**
	 * Se estamos na página de configurações do plugin (admin).
	 *
	 * O $hook_suffix costuma ser `pb-affiliates_page_pb-affiliates-settings`, mas em alguns
	 * contextos o WordPress usa só o slug `pb-affiliates-settings` (vide admin.php / page_hook).
	 *
	 * @param string $hook_suffix Hook passado a admin_enqueue_scripts.
	 * @return bool
	 */
	private static function is_settings_admin_screen( $hook_suffix ) {
		if ( 'pb-affiliates_page_pb-affiliates-settings' === $hook_suffix
			|| 'pb-affiliates-settings' === $hook_suffix ) {
			return true;
		}
		if ( isset( $_GET['page'] ) && 'pb-affiliates-settings' === sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- apenas identificar tela.
			global $pagenow;
			return isset( $pagenow ) && 'admin.php' === $pagenow;
		}
		return false;
	}

	/**
	 * Checkbox/toggle sent as yes, 1 or absent.
	 *
	 * @param array  $input Input.
	 * @param string $key Key under pb_affiliates_settings.
	 * @return bool
	 */
	private static function input_is_yes( $input, $key ) {
		if ( ! isset( $input[ $key ] ) ) {
			return false;
		}
		$v = $input[ $key ];
		return ( 'yes' === $v || '1' === (string) $v || 1 === $v || true === $v );
	}

	/**
	 * Register settings.
	 */
	public static function register() {
		register_setting( 'pb_affiliates_settings_group', PB_Affiliates_Settings::OPTION, array( __CLASS__, 'sanitize' ) );
	}

	/**
	 * Sanitize options array.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$old   = PB_Affiliates_Settings::get();
		$input = is_array( $input ) ? $input : array();

		$out = array(
			'referral_param'           => isset( $input['referral_param'] ) ? sanitize_key( $input['referral_param'] ) : $old['referral_param'],
			'cookie_days'              => isset( $input['cookie_days'] ) ? absint( $input['cookie_days'] ) : $old['cookie_days'],
			'default_commission_type'  => isset( $input['default_commission_type'] ) && in_array( $input['default_commission_type'], array( 'percent', 'fixed' ), true ) ? $input['default_commission_type'] : $old['default_commission_type'],
			'default_commission_value' => isset( $input['default_commission_value'] ) ? (float) wc_format_decimal( $input['default_commission_value'] ) : $old['default_commission_value'],
			'attribution'              => isset( $input['attribution'] ) && in_array( $input['attribution'], array( 'first', 'last' ), true ) ? $input['attribution'] : $old['attribution'],
			'exclude_shipping'         => self::input_is_yes( $input, 'exclude_shipping' ) ? 'yes' : 'no',
			'exclude_fees'             => self::input_is_yes( $input, 'exclude_fees' ) ? 'yes' : 'no',
			'payment_mode'             => isset( $input['payment_mode'] ) && in_array( $input['payment_mode'], array( 'manual', 'split' ), true ) ? $input['payment_mode'] : $old['payment_mode'],
			'split_release_days'       => isset( $input['split_release_days'] ) ? absint( $input['split_release_days'] ) : $old['split_release_days'],
			'manual_min_withdrawal'    => isset( $input['manual_min_withdrawal'] ) ? (float) wc_format_decimal( $input['manual_min_withdrawal'] ) : $old['manual_min_withdrawal'],
			'manual_retention_days'    => isset( $input['manual_retention_days'] ) ? absint( $input['manual_retention_days'] ) : $old['manual_retention_days'],
			'terms_page_id'            => isset( $input['terms_page_id'] ) ? absint( $input['terms_page_id'] ) : 0,
			'affiliate_registration'   => isset( $input['affiliate_registration'] ) && in_array( $input['affiliate_registration'], array( 'auto', 'manual' ), true ) ? $input['affiliate_registration'] : $old['affiliate_registration'],
			'commission_recurring'     => self::input_is_yes( $input, 'commission_recurring' ) ? 'yes' : 'no',
		);

		if ( 'split' === $out['payment_mode'] && ! PB_Affiliates_Dependencies::can_use_affiliate_split() ) {
			add_settings_error(
				'pb_affiliates_settings',
				'split_conflict',
				__( 'Split de afiliados não pode ser usado enquanto a Divisão de pagamentos do PagBank ou o Split Dokan estiver ativo. Modo de pagamento redefinido para manual.', 'pb-affiliates' ),
				'error'
			);
			$out['payment_mode'] = 'manual';
		}

		return wp_parse_args( $out, PB_Affiliates_Install::default_settings() );
	}

	/**
	 * Checkbox value for toggles (yes when checked).
	 *
	 * @param string $name Field name suffix.
	 * @param string $current Current stored yes/no.
	 * @param string $id Input id.
	 * @param string $label Label text.
	 */
	private static function render_toggle( $name, $current, $id, $label ) {
		$opt   = PB_Affiliates_Settings::OPTION;
		$is_on = in_array( (string) $current, array( 'yes', '1' ), true );
		?>
		<label class="pb-aff-toggle" for="<?php echo esc_attr( $id ); ?>">
			<input
				type="checkbox"
				name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $name ); ?>]"
				id="<?php echo esc_attr( $id ); ?>"
				value="yes"
				<?php checked( $is_on ); ?>
			/>
			<span class="pb-aff-toggle__track" aria-hidden="true"></span>
			<span class="pb-aff-toggle__label"><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Render settings form.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s = PB_Affiliates_Settings::get();
		$o = PB_Affiliates_Settings::OPTION;

		$mode_manual = 'manual' === $s['payment_mode'];
		$mode_split  = 'split' === $s['payment_mode'];

		settings_errors( 'pb_affiliates_settings' );
		?>
		<div class="wrap pb-aff-wrap pb-aff-admin-settings">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Configurações — Afiliados', 'pb-affiliates' ); ?></h1>
			<hr class="wp-header-end" />

			<form method="post" action="options.php" class="pb-aff-settings-form">
				<?php settings_fields( 'pb_affiliates_settings_group' ); ?>

				<h2 class="title"><?php esc_html_e( 'Rastreamento', 'pb-affiliates' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Como o visitante é associado a um afiliado antes da compra.', 'pb-affiliates' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="referral_param"><?php esc_html_e( 'Parâmetro na URL', 'pb-affiliates' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( $o ); ?>[referral_param]" id="referral_param" type="text" value="<?php echo esc_attr( $s['referral_param'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Ex.: ?pid=seu-codigo (padrão: pid).', 'pb-affiliates' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cookie_days"><?php esc_html_e( 'Duração do cookie', 'pb-affiliates' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( $o ); ?>[cookie_days]" id="cookie_days" type="number" min="1" step="1" value="<?php echo esc_attr( (string) $s['cookie_days'] ); ?>" class="small-text" />
							<span><?php esc_html_e( 'dias', 'pb-affiliates' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Atribuição', 'pb-affiliates' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[attribution]" value="first" <?php checked( $s['attribution'], 'first' ); ?> /> <?php esc_html_e( 'Primeiro afiliado (first touch)', 'pb-affiliates' ); ?></label><br />
								<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[attribution]" value="last" <?php checked( $s['attribution'], 'last' ); ?> /> <?php esc_html_e( 'Último afiliado (last touch)', 'pb-affiliates' ); ?></label>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Comissão padrão', 'pb-affiliates' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="default_commission_type"><?php esc_html_e( 'Tipo', 'pb-affiliates' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( $o ); ?>[default_commission_type]" id="default_commission_type">
								<option value="percent" <?php selected( $s['default_commission_type'], 'percent' ); ?>><?php esc_html_e( 'Percentual', 'pb-affiliates' ); ?></option>
								<option value="fixed" <?php selected( $s['default_commission_type'], 'fixed' ); ?>><?php esc_html_e( 'Valor fixo', 'pb-affiliates' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="default_commission_value"><?php esc_html_e( 'Valor', 'pb-affiliates' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( $o ); ?>[default_commission_value]" id="default_commission_value" type="text" value="<?php echo esc_attr( (string) $s['default_commission_value'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Percentual sobre a base ou valor fixo por pedido, conforme o tipo.', 'pb-affiliates' ); ?></p>
							<p class="description"><?php esc_html_e( 'Você também pode definir comissão própria por categoria de produto: em Produtos → Categorias, edite uma categoria e use a seção “Comissão de afiliados (PB Afiliados)”. Isso substitui estes valores padrão por item quando não houver cupom nem comissão personalizada no perfil do afiliado.', 'pb-affiliates' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Base do cálculo', 'pb-affiliates' ); ?></th>
						<td>
							<?php self::render_toggle( 'exclude_shipping', $s['exclude_shipping'], 'pb_aff_exclude_shipping', __( 'Excluir frete (usar subtotal dos itens)', 'pb-affiliates' ) ); ?>
							<br /><br />
							<?php self::render_toggle( 'exclude_fees', $s['exclude_fees'], 'pb_aff_exclude_fees', __( 'Excluir taxas adicionais (fees) do pedido', 'pb-affiliates' ) ); ?>
							<br /><br />
							<?php
							self::render_toggle(
								'commission_recurring',
								$s['commission_recurring'] ?? 'no',
								'pb_aff_commission_recurring',
								__( 'Aplicar comissão em cobranças recorrentes', 'pb-affiliates' )
							);
							?>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Pagamentos aos afiliados', 'pb-affiliates' ); ?></h2>
				<?php if ( PB_Affiliates_Dependencies::is_gateway_split_enabled() || PB_Affiliates_Dependencies::is_dokan_split_enabled() ) : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Atenção: Divisão de pagamentos do PagBank ou Split Dokan está ativo. O split automático de afiliados exige que ambos estejam desativados.', 'pb-affiliates' ); ?></p></div>
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Modo de pagamento', 'pb-affiliates' ); ?></th>
						<td>
							<div class="pb-aff-payment-modes">
								<label class="pb-aff-payment-mode <?php echo $mode_manual ? 'is-selected' : ''; ?>">
									<input type="radio" name="<?php echo esc_attr( $o ); ?>[payment_mode]" value="manual" <?php checked( $s['payment_mode'], 'manual' ); ?> />
									<span class="pb-aff-payment-mode__text">
										<strong><?php esc_html_e( 'Manual', 'pb-affiliates' ); ?></strong>
										<span><?php esc_html_e( 'Transferência com dados bancários informados pelo afiliado.', 'pb-affiliates' ); ?></span>
									</span>
								</label>
								<label class="pb-aff-payment-mode <?php echo $mode_split ? 'is-selected' : ''; ?>">
									<input type="radio" name="<?php echo esc_attr( $o ); ?>[payment_mode]" value="split" <?php checked( $s['payment_mode'], 'split' ); ?> />
									<span class="pb-aff-payment-mode__text">
										<strong><?php esc_html_e( 'Split automático PagBank', 'pb-affiliates' ); ?></strong>
										<span><?php esc_html_e( 'Comissão enviada via split na cobrança (requer Account ID do afiliado).', 'pb-affiliates' ); ?></span>
									</span>
								</label>
							</div>
						</td>
					</tr>
					<tr id="pb-aff-row-split-release" class="<?php echo $mode_split ? '' : 'pb-aff-row-hidden'; ?>">
						<th scope="row"><label for="split_release_days"><?php esc_html_e( 'Dias para liberação no split', 'pb-affiliates' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( $o ); ?>[split_release_days]" id="split_release_days" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $s['split_release_days'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( 'Prazo de custódia até a liberação do valor ao afiliado (API PagBank).', 'pb-affiliates' ); ?></p>
						</td>
					</tr>
					<tr id="pb-aff-row-manual-min" class="<?php echo $mode_manual ? '' : 'pb-aff-row-hidden'; ?>">
						<th scope="row"><label for="manual_min_withdrawal"><?php esc_html_e( 'Valor mínimo para saque', 'pb-affiliates' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( $o ); ?>[manual_min_withdrawal]" id="manual_min_withdrawal" type="text" value="<?php echo esc_attr( (string) $s['manual_min_withdrawal'] ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Apenas para pagamento manual.', 'pb-affiliates' ); ?></p>
						</td>
					</tr>
					<tr id="pb-aff-row-manual-retention" class="<?php echo $mode_manual ? '' : 'pb-aff-row-hidden'; ?>">
						<th scope="row"><label for="manual_retention_days"><?php esc_html_e( 'Dias de retenção antes do saque', 'pb-affiliates' ); ?></label></th>
						<td>
							<input name="<?php echo esc_attr( $o ); ?>[manual_retention_days]" id="manual_retention_days" type="number" min="0" step="1" value="<?php echo esc_attr( (string) $s['manual_retention_days'] ); ?>" class="small-text" />
							<span><?php esc_html_e( 'dias', 'pb-affiliates' ); ?></span>
							<p class="description"><?php esc_html_e( 'Apenas para pagamento manual.', 'pb-affiliates' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Cadastro e termos', 'pb-affiliates' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Novos afiliados', 'pb-affiliates' ); ?></th>
						<td>
							<fieldset>
								<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[affiliate_registration]" value="auto" <?php checked( $s['affiliate_registration'], 'auto' ); ?> /> <?php esc_html_e( 'Aprovação automática', 'pb-affiliates' ); ?></label><br />
								<label><input type="radio" name="<?php echo esc_attr( $o ); ?>[affiliate_registration]" value="manual" <?php checked( $s['affiliate_registration'], 'manual' ); ?> /> <?php esc_html_e( 'Aprovação manual pelo administrador', 'pb-affiliates' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="terms_page_id"><?php esc_html_e( 'Página de termos e condições', 'pb-affiliates' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => $o . '[terms_page_id]',
									'id'                => 'terms_page_id',
									'selected'          => (int) $s['terms_page_id'],
									'show_option_none'  => __( '— Selecionar —', 'pb-affiliates' ),
									'option_none_value' => '0',
								)
							);
							?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Salvar alterações', 'pb-affiliates' ) ); ?>
			</form>
		</div>
		<?php
	}
}
