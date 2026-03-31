<?php
/**
 * Subnavegação da área do afiliado (links entre endpoints).
 *
 * @package PB_Affiliates
 * @var string $pb_aff_nav_active       dashboard|links|reports|materials|account
 * @var bool   $pb_aff_has_promo_materials Exibir link materiais.
 * @var string $pb_aff_materials_url    URL endpoint materiais.
 */

defined( 'ABSPATH' ) || exit;

$pb_aff_nav_active = isset( $pb_aff_nav_active ) ? (string) $pb_aff_nav_active : 'dashboard';
$pb_aff_mat_show   = ! empty( $pb_aff_has_promo_materials ) && ! empty( $pb_aff_materials_url );
$pb_aff_mat_url    = $pb_aff_mat_show ? (string) $pb_aff_materials_url : '';
$pb_aff_dash_url   = wc_get_account_endpoint_url( PB_Affiliates_Account::ENDPOINT );
$pb_aff_links_url  = wc_get_account_endpoint_url( PB_Affiliates_Account::ENDPOINT_LINKS );
$pb_aff_rep_url    = wc_get_account_endpoint_url( PB_Affiliates_Account::ENDPOINT_REPORTS );
$pb_aff_acct_url   = wc_get_account_endpoint_url( 'edit-account' );
?>
<nav class="pb-aff-hub-nav" aria-label="<?php esc_attr_e( 'Secções do programa de afiliados', 'pb-affiliates' ); ?>">
	<ul class="pb-aff-hub-nav__list" role="list">
		<li class="pb-aff-hub-nav__item">
			<a class="pb-aff-hub-nav__link<?php echo 'dashboard' === $pb_aff_nav_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $pb_aff_dash_url ); ?>"><?php esc_html_e( 'Painel', 'pb-affiliates' ); ?></a>
		</li>
		<li class="pb-aff-hub-nav__item">
			<a class="pb-aff-hub-nav__link<?php echo 'links' === $pb_aff_nav_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $pb_aff_links_url ); ?>"><?php esc_html_e( 'Links de afiliados', 'pb-affiliates' ); ?></a>
		</li>
		<li class="pb-aff-hub-nav__item">
			<a class="pb-aff-hub-nav__link<?php echo 'reports' === $pb_aff_nav_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $pb_aff_rep_url ); ?>"><?php esc_html_e( 'Relatórios', 'pb-affiliates' ); ?></a>
		</li>
		<?php if ( $pb_aff_mat_show ) : ?>
		<li class="pb-aff-hub-nav__item">
			<a class="pb-aff-hub-nav__link<?php echo 'materials' === $pb_aff_nav_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $pb_aff_mat_url ); ?>"><?php esc_html_e( 'Materiais', 'pb-affiliates' ); ?></a>
		</li>
		<?php endif; ?>
		<li class="pb-aff-hub-nav__item">
			<a class="pb-aff-hub-nav__link<?php echo 'account' === $pb_aff_nav_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $pb_aff_acct_url ); ?>"><?php esc_html_e( 'Minha conta', 'pb-affiliates' ); ?></a>
		</li>
	</ul>
</nav>
