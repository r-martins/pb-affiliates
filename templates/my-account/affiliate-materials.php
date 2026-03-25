<?php
/**
 * Materiais promocionais (afiliado — Minha conta).
 *
 * @package PB_Affiliates
 * @var array<int, array<string, mixed>> $pb_aff_promo_materials Linhas para exibição.
 */

defined( 'ABSPATH' ) || exit;

$pb_aff_promo_materials = isset( $pb_aff_promo_materials ) && is_array( $pb_aff_promo_materials ) ? $pb_aff_promo_materials : array();
?>
<div class="pb-aff-promo-materials" data-pb-aff-promo-materials="1">
	<h2><?php esc_html_e( 'Materiais promocionais', 'pb-affiliates' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Arquivos disponibilizados pela loja para uso em suas campanhas.', 'pb-affiliates' ); ?></p>
	<?php if ( empty( $pb_aff_promo_materials ) ) : ?>
		<p><?php esc_html_e( 'Nenhum material disponível no momento.', 'pb-affiliates' ); ?></p>
	<?php else : ?>
		<ul class="pb-aff-promo-materials-list woocommerce-MyAccount-content">
			<?php foreach ( $pb_aff_promo_materials as $row ) : ?>
				<?php
				$row        = is_array( $row ) ? $row : array();
				$title      = isset( $row['title'] ) ? (string) $row['title'] : '';
				$url        = isset( $row['url'] ) ? (string) $row['url'] : '';
				$thumb      = isset( $row['thumb_url'] ) ? (string) $row['thumb_url'] : '';
				$is_image   = ! empty( $row['is_image'] );
				$date_mysql = isset( $row['date'] ) ? (string) $row['date'] : '';
				$date_disp  = $date_mysql ? mysql2date( get_option( 'date_format' ), $date_mysql ) : '';
				?>
				<li class="pb-aff-promo-materials-list__item">
					<div class="pb-aff-promo-materials-list__body">
						<?php if ( $is_image && $thumb ) : ?>
							<a class="pb-aff-promo-materials-list__thumb" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
								<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $title ); ?>" width="120" height="120" loading="lazy" decoding="async" />
							</a>
						<?php endif; ?>
						<div class="pb-aff-promo-materials-list__meta">
							<strong class="pb-aff-promo-materials-list__title"><?php echo esc_html( $title ); ?></strong>
							<?php if ( $date_disp ) : ?>
								<span class="pb-aff-promo-materials-list__date"><?php echo esc_html( $date_disp ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<?php if ( $url ) : ?>
						<a class="button woocommerce-Button pb-aff-btn--secondary pb-aff-promo-materials-list__download" href="<?php echo esc_url( $url ); ?>" download target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Download', 'pb-affiliates' ); ?>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
