<?php
/**
 * Opt-in: tornar-se afiliado (meta pb_affiliate_status; sem papel WP).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'wc_print_notices' ) ) {
	wc_print_notices();
}

$settings  = PB_Affiliates_Settings::get();
$terms_id  = absint( $settings['terms_page_id'] ?? 0 );
$terms_url = $terms_id ? get_permalink( $terms_id ) : '';
?>
<h2><?php esc_html_e( 'Programa de afiliados', 'pb-affiliates' ); ?></h2>
<p><?php esc_html_e( 'Participe do programa e receba um link exclusivo para divulgar a loja e gerar comissões.', 'pb-affiliates' ); ?></p>
<form method="post" class="pb-aff-opt-in-form" action="">
	<?php wp_nonce_field( 'pb_aff_area', 'pb_aff_area_nonce' ); ?>
	<input type="hidden" name="pb_aff_area_action" value="join" />
	<?php if ( $terms_url ) : ?>
		<p class="woocommerce-form-row">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
				<input type="checkbox" name="pb_aff_accept_terms" value="1" class="woocommerce-form__input-checkbox" required />
				<span>
					<?php
					printf(
						/* translators: %s: terms URL */
						wp_kses_post( __( 'Li e aceito os <a href="%s" target="_blank" rel="noopener noreferrer">termos do programa de afiliados</a>.', 'pb-affiliates' ) ),
						esc_url( $terms_url )
					);
					?>
				</span>
			</label>
		</p>
	<?php endif; ?>
	<p>
		<button type="submit" class="button woocommerce-Button"><?php esc_html_e( 'Quero ser afiliado', 'pb-affiliates' ); ?></button>
	</p>
</form>
