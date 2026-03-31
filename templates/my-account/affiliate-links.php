<?php
/**
 * Links de afiliados: identificador, URL de indicação, domínios de referência.
 *
 * @package PB_Affiliates
 * @var string $link Referral link.
 * @var string $code Affiliate public code.
 * @var array  $affiliate_domains Domínios de referência (meta pb_affiliate_verified_domains).
 * @var bool   $pb_aff_has_promo_materials Exibe atalho para materiais promocionais.
 * @var string $pb_aff_materials_url URL do endpoint affiliate-materials.
 * @var string $pb_aff_nav_active links (para o partial de navegação).
 * @var bool $pb_aff_zip1_enabled Exibe caixa zip1.io (admin).
 */

defined( 'ABSPATH' ) || exit;

// Algumas themes/caches não exibem `wc_print_notices()` nos endpoints customizados do WooCommerce.
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
		$ul_class = 'error' === $type ? 'woocommerce-error' : 'woocommerce-message';
		echo '<div class="woocommerce-notices-wrapper" aria-label="' . esc_attr__( 'Notices', 'pb-affiliates' ) . '">';
		echo '<ul class="' . esc_attr( $ul_class ) . '" role="alert"><li>' . wp_kses_post( $pb_msg ) . '</li></ul>';
		echo '</div>';
	}
}

$affiliate_domains = isset( $affiliate_domains ) && is_array( $affiliate_domains ) ? $affiliate_domains : array();
$pb_aff_nav_active = isset( $pb_aff_nav_active ) ? (string) $pb_aff_nav_active : 'links';
?>
<div class="pb-aff-hub pb-aff-hub--subpage">
	<?php include PB_AFFILIATES_PATH . 'templates/my-account/parts/affiliate-hub-nav.php'; ?>

	<h2 class="pb-aff-links-page-title"><?php esc_html_e( 'Links de afiliados', 'pb-affiliates' ); ?></h2>

	<h3><?php esc_html_e( 'Identificador de afiliado', 'pb-affiliates' ); ?></h3>
	<form method="post" class="pb-aff-change-code">
		<?php wp_nonce_field( 'pb_aff_area', 'pb_aff_area_nonce' ); ?>
		<input type="hidden" name="pb_aff_area_action" value="change_code" />
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="pb_aff_new_code"><?php esc_html_e( 'Identificador público (usado na URL de indicação)', 'pb-affiliates' ); ?></label>
			<input type="text" name="pb_aff_new_code" id="pb_aff_new_code" class="woocommerce-Input input-text" value="<?php echo esc_attr( (string) $code ); ?>" autocomplete="off" maxlength="40" />
		</p>
		<p>
			<button type="submit" class="button woocommerce-Button"><?php esc_html_e( 'Salvar identificador', 'pb-affiliates' ); ?></button>
		</p>
		<p class="description"><?php esc_html_e( 'Apenas letras minúsculas, números, hífen e sublinhado; mínimo 3 caracteres. Deve ser único na loja.', 'pb-affiliates' ); ?></p>
	</form>

	<h3><?php esc_html_e( 'Seu link de afiliado', 'pb-affiliates' ); ?></h3>
	<div class="pb-aff-link-row">
		<input type="text" id="pb-aff-referral-link" readonly="readonly" class="pb-aff-link-row__input large-text woocommerce-Input" value="<?php echo esc_attr( $link ); ?>" onclick="this.select();" aria-label="<?php esc_attr_e( 'Link de afiliado', 'pb-affiliates' ); ?>" />
		<button type="button" class="button" id="pb-aff-copy-link-btn" data-label-done="<?php echo esc_attr( __( 'Copiado!', 'pb-affiliates' ) ); ?>">
			<?php esc_html_e( 'Copiar link', 'pb-affiliates' ); ?>
		</button>
	</div>

	<section class="pb-aff-link-builder pb-aff-zip1-box" aria-labelledby="pb-aff-lb-title">
		<h3 id="pb-aff-lb-title"><?php esc_html_e( 'Compartilhe um produto, página ou categoria', 'pb-affiliates' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Cole o endereço de um produto, de uma categoria ou de qualquer página desta loja. O sistema acrescenta o seu código de afiliado ao link.', 'pb-affiliates' ); ?></p>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="pb-aff-lb-paste"><?php esc_html_e( 'URL da página', 'pb-affiliates' ); ?></label>
			<input type="text" id="pb-aff-lb-paste" class="woocommerce-Input input-text large-text" autocomplete="off" placeholder="<?php esc_attr_e( 'https://… ou /caminho/relativo', 'pb-affiliates' ); ?>" />
		</p>
		<p>
			<button type="button" class="button woocommerce-Button pb-aff-btn--primary" id="pb-aff-lb-generate"><?php esc_html_e( 'Gerar link de afiliado', 'pb-affiliates' ); ?></button>
		</p>
		<div id="pb-aff-lb-notice" class="pb-aff-lb-notice" role="alert" hidden></div>
		<div id="pb-aff-lb-result" class="pb-aff-lb-result" hidden>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="pb-aff-lb-out"><?php esc_html_e( 'Link com o seu código', 'pb-affiliates' ); ?></label>
			</p>
			<div class="pb-aff-link-row">
				<input type="text" id="pb-aff-lb-out" readonly="readonly" class="pb-aff-link-row__input large-text woocommerce-Input" value="" onclick="this.select();" aria-label="<?php esc_attr_e( 'Link de afiliado gerado', 'pb-affiliates' ); ?>" />
				<button type="button" class="button" id="pb-aff-lb-copy" data-label-done="<?php echo esc_attr( __( 'Copiado!', 'pb-affiliates' ) ); ?>"><?php esc_html_e( 'Copiar', 'pb-affiliates' ); ?></button>
			</div>
		</div>
	</section>

	<?php if ( ! empty( $pb_aff_zip1_enabled ) ) : ?>
		<?php
		$pb_z1_short       = '';
		$pb_z1_stats       = '';
		$pb_z1_has         = false;
		$pb_z1_source_init = $link;
		?>
	<section class="pb-aff-zip1-box" data-pb-aff-zip1="1" aria-labelledby="pb-aff-zip1-title">
		<h3 id="pb-aff-zip1-title"><?php esc_html_e( 'Link curto', 'pb-affiliates' ); ?></h3>

		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide pb-aff-zip1-source-row">
			<label for="pb-aff-zip1-source-url"><?php esc_html_e( 'URL a encurtar', 'pb-affiliates' ); ?></label>
			<input type="text" id="pb-aff-zip1-source-url" class="woocommerce-Input input-text large-text" value="<?php echo esc_attr( $pb_z1_source_init ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'URL de destino a encurtar', 'pb-affiliates' ); ?>" />
		</p>

		<div id="pb-aff-zip1-notice" class="pb-aff-zip1-notice" role="alert" hidden></div>
		<div id="pb-aff-zip1-panel" class="pb-aff-zip1-panel"<?php echo $pb_z1_has ? '' : ' hidden'; ?>>
			<div class="pb-aff-link-row pb-aff-zip1-row">
				<input type="text" id="pb-aff-zip1-short-input" readonly="readonly" class="pb-aff-link-row__input large-text woocommerce-Input" value="<?php echo esc_attr( $pb_z1_short ); ?>" onclick="this.select();" aria-label="<?php esc_attr_e( 'Link curto gerado', 'pb-affiliates' ); ?>" />
				<button type="button" class="button" id="pb-aff-zip1-copy" data-label-done="<?php echo esc_attr( __( 'Copiado!', 'pb-affiliates' ) ); ?>"><?php esc_html_e( 'Copiar link curto', 'pb-affiliates' ); ?></button>
			</div>
			<p class="pb-aff-zip1-stats-line"<?php echo $pb_z1_stats ? '' : ' hidden'; ?>>
				<a id="pb-aff-zip1-stats-link" href="<?php echo $pb_z1_stats ? esc_url( $pb_z1_stats ) : '#'; ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Estatísticas no zip1.io', 'pb-affiliates' ); ?></a>
			</p>
			<p class="pb-aff-zip1-replace-line">
				<button type="button" class="button" id="pb-aff-zip1-replace"><?php esc_html_e( 'Gerar outro link curto', 'pb-affiliates' ); ?></button>
			</p>
		</div>
		<div id="pb-aff-zip1-form-panel" class="pb-aff-zip1-form-panel"<?php echo $pb_z1_has ? ' hidden' : ''; ?>>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label class="pb-aff-zip1-alias-label-visible" for="pb-aff-zip1-alias"><?php esc_html_e( 'Personalizar endereço (opcional)', 'pb-affiliates' ); ?></label>
				<span class="pb-aff-zip1-alias-wrap">
					<span class="pb-aff-zip1-alias-prefix" aria-hidden="true">https://zip1.io/</span>
					<input type="text" id="pb-aff-zip1-alias" class="woocommerce-Input input-text pb-aff-zip1-alias-input" maxlength="16" autocomplete="off" placeholder="<?php esc_attr_e( 'Opcional: 3–16 caracteres (a–z, A–Z, 0–9, hífen) ou um emoji.', 'pb-affiliates' ); ?>" />
				</span>
			</p>
			<p>
				<button type="button" class="button woocommerce-Button pb-aff-btn--primary" id="pb-aff-zip1-submit"><?php esc_html_e( 'Gerar link curto', 'pb-affiliates' ); ?></button>
			</p>
		</div>
	</section>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Domínio de referência (Referer)', 'pb-affiliates' ); ?></h3>
	<?php
	$pb_aff_referer_hint = __( 'Lembre-se de não colocar rel="noreferrer" em seus links.', 'pb-affiliates' );
	?>
	<p class="pb-aff-domain-intro">
		<?php esc_html_e( 'Tem um site ou blog? Informe o URL aqui.', 'pb-affiliates' ); ?><br />
		<?php esc_html_e( 'Toda vez que um link for clicado a partir do seu site, automaticamente saberemos que foi sua indicação, sem sequer precisar informar o seu código de afiliado.', 'pb-affiliates' ); ?>
		<span class="pb-aff-domain-info" tabindex="0" title="<?php echo esc_attr( $pb_aff_referer_hint ); ?>" aria-label="<?php echo esc_attr( $pb_aff_referer_hint ); ?>"><?php echo esc_html( 'ℹ' ); ?></span>
	</p>
	<form method="post" class="pb-aff-domain-form">
		<?php wp_nonce_field( 'pb_aff_domain', 'pb_aff_domain_nonce' ); ?>
		<input type="hidden" name="pb_aff_domain_action" value="add" />
		<p>
			<label for="pb_aff_domain_url"><?php esc_html_e( 'URL do seu site (domínio ou subdomínio)', 'pb-affiliates' ); ?></label>
			<input type="url" name="pb_aff_domain_url" id="pb_aff_domain_url" class="woocommerce-Input input-text" placeholder="https://exemplo.com.br" />
		</p>
		<button type="submit" class="button"><?php esc_html_e( 'Adicionar', 'pb-affiliates' ); ?></button>
	</form>

	<?php if ( ! empty( $affiliate_domains ) ) : ?>
		<h4 class="pb-aff-domain-heading"><?php esc_html_e( 'Seus sites', 'pb-affiliates' ); ?></h4>
		<table class="shop_table shop_table_responsive pb-aff-domain-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Site', 'pb-affiliates' ); ?></th>
					<th><?php esc_html_e( 'Status', 'pb-affiliates' ); ?></th>
					<th class="pb-aff-domain-table__action"><?php esc_html_e( 'Ações', 'pb-affiliates' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $affiliate_domains as $pb_aff_d ) {
					$pb_aff_host = isset( $pb_aff_d['host'] ) ? (string) $pb_aff_d['host'] : '';
					if ( '' === $pb_aff_host ) {
						continue;
					}
					$pb_aff_verified = ! empty( $pb_aff_d['verified'] );
					$pb_aff_token          = isset( $pb_aff_d['token'] ) ? (string) $pb_aff_d['token'] : '';
					$pb_aff_file_path      = $pb_aff_token ? 'https://' . $pb_aff_host . '/.well-known/pb-affiliate-' . $pb_aff_token . '.txt' : '';
					$pb_aff_download_name  = $pb_aff_token ? 'pb-affiliate-' . $pb_aff_token . '.txt' : '';
					$pb_aff_download_href  = $pb_aff_token ? 'data:text/plain;charset=utf-8,' . rawurlencode( $pb_aff_token ) : '';
					$pb_aff_remove_confirm = __( 'Remover este site da lista?', 'pb-affiliates' );
					?>
				<tr>
					<td data-title="<?php esc_attr_e( 'Site', 'pb-affiliates' ); ?>"><?php echo esc_html( $pb_aff_host ); ?></td>
					<td data-title="<?php esc_attr_e( 'Status', 'pb-affiliates' ); ?>">
						<?php
						if ( $pb_aff_verified ) {
							esc_html_e( 'Validado', 'pb-affiliates' );
						} else {
							esc_html_e( 'Pendente de validação', 'pb-affiliates' );
						}
						?>
					</td>
					<td class="pb-aff-domain-table__action" data-title="<?php esc_attr_e( 'Ações', 'pb-affiliates' ); ?>">
						<div class="pb-aff-domain-actions">
							<?php if ( ! $pb_aff_verified && $pb_aff_token && $pb_aff_download_href ) : ?>
								<form method="post" class="pb-aff-domain-validate-form">
									<?php wp_nonce_field( 'pb_aff_domain', 'pb_aff_domain_nonce' ); ?>
									<input type="hidden" name="pb_aff_domain_action" value="validate" />
									<input type="hidden" name="pb_aff_domain_host" value="<?php echo esc_attr( $pb_aff_host ); ?>" />
									<button type="submit" class="button woocommerce-Button pb-aff-btn--primary"><?php esc_html_e( 'Validar', 'pb-affiliates' ); ?></button>
								</form>
								<a class="button woocommerce-Button pb-aff-btn--secondary" href="<?php echo esc_attr( $pb_aff_download_href ); ?>" download="<?php echo esc_attr( $pb_aff_download_name ); ?>"><?php esc_html_e( 'Baixar arquivo', 'pb-affiliates' ); ?></a>
							<?php endif; ?>
							<form method="post" class="pb-aff-domain-remove-form" onsubmit="return window.confirm(<?php echo wp_json_encode( $pb_aff_remove_confirm ); ?>);">
								<?php wp_nonce_field( 'pb_aff_domain', 'pb_aff_domain_nonce' ); ?>
								<input type="hidden" name="pb_aff_domain_action" value="remove" />
								<input type="hidden" name="pb_aff_domain_host" value="<?php echo esc_attr( $pb_aff_host ); ?>" />
								<button type="submit" class="button woocommerce-Button pb-aff-btn--danger"><?php esc_html_e( 'Excluir', 'pb-affiliates' ); ?></button>
							</form>
						</div>
					</td>
				</tr>
					<?php if ( ! $pb_aff_verified && $pb_aff_file_path && $pb_aff_token ) : ?>
				<tr class="pb-aff-domain-table__instructions">
					<td colspan="3">
						<p class="description">
							<?php esc_html_e( 'Arquivo (URL exata):', 'pb-affiliates' ); ?>
							<a href="<?php echo esc_url( $pb_aff_file_path ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $pb_aff_file_path ); ?></a>
						</p>
						<p class="description">
							<?php esc_html_e( 'Conteúdo do arquivo (texto puro, uma linha):', 'pb-affiliates' ); ?>
							<code><?php echo esc_html( $pb_aff_token ); ?></code>
						</p>
					</td>
				</tr>
					<?php endif; ?>
					<?php
				}
				?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<script>
(function(){
	var btn = document.getElementById('pb-aff-copy-link-btn');
	var inp = document.getElementById('pb-aff-referral-link');
	if (!btn || !inp) return;
	btn.addEventListener('click', function() {
		inp.focus();
		inp.select();
		inp.setSelectionRange(0, 99999);
		var done = btn.getAttribute('data-label-done') || 'OK';
		var orig = btn.textContent;
		function feedback() {
			btn.textContent = done;
			btn.disabled = true;
			setTimeout(function() {
				btn.textContent = orig;
				btn.disabled = false;
			}, 2000);
		}
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(inp.value).then(feedback).catch(function() {
				try {
					document.execCommand('copy');
					feedback();
				} catch (e) {}
			});
		} else {
			try {
				document.execCommand('copy');
				feedback();
			} catch (e) {}
		}
	});
})();
</script>
