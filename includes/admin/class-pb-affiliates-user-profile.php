<?php
/**
 * User profile fields (estado afiliado, comissão, PagBank, banco).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_User_Profile
 */
class PB_Affiliates_User_Profile {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'show_user_profile', array( __CLASS__, 'fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'profile_error_notice' ) );
	}

	/**
	 * Show Account ID validation error after redirect from profile save.
	 */
	public static function profile_error_notice() {
		global $pagenow;
		if ( ! in_array( (string) $pagenow, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}
		$uid = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : get_current_user_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $uid ) {
			return;
		}
		$key = 'pb_aff_profile_err_' . $uid;
		$msg = get_transient( $key );
		if ( ! $msg ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * @param WP_User $user User.
	 */
	public static function fields( $user ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$mode   = PB_Affiliates_Settings::get()['payment_mode'] ?? 'manual';
		$status = PB_Affiliates_Role::get_affiliate_status( $user->ID );
		if ( '' === $status ) {
			$status = PB_Affiliates_Role::STATUS_NONE;
		}
		$in_program = PB_Affiliates_Role::user_is_active_or_pending_affiliate( $user->ID );
		?>
		<h2><?php esc_html_e( 'PB Afiliados', 'pb-affiliates' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="pb_affiliate_status_field"><?php esc_html_e( 'Programa de afiliados', 'pb-affiliates' ); ?></label></th>
				<td>
					<select name="pb_affiliate_status_field" id="pb_affiliate_status_field">
						<option value="none" <?php selected( $status, PB_Affiliates_Role::STATUS_NONE ); ?>><?php esc_html_e( 'Não participa', 'pb-affiliates' ); ?></option>
						<option value="pending" <?php selected( $status, PB_Affiliates_Role::STATUS_PENDING ); ?>><?php esc_html_e( 'Pendente de aprovação', 'pb-affiliates' ); ?></option>
						<option value="active" <?php selected( $status, PB_Affiliates_Role::STATUS_ACTIVE ); ?>><?php esc_html_e( 'Ativo', 'pb-affiliates' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Não utiliza um papel WordPress separado: apenas esta opção e o estado (pendente/ativo).', 'pb-affiliates' ); ?></p>
				</td>
			</tr>
			<?php if ( $in_program ) : ?>
				<tr>
					<th><label for="pb_affiliate_code"><?php esc_html_e( 'Código público (AFF)', 'pb-affiliates' ); ?></label></th>
					<td>
						<input type="text" name="pb_affiliate_code" id="pb_affiliate_code" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pb_affiliate_code', true ) ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Único no site (minúsculas, números, _ e -).', 'pb-affiliates' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Comissão (sobrescreve padrão)', 'pb-affiliates' ); ?></th>
					<td>
						<select name="pb_affiliate_commission_type" id="pb_affiliate_commission_type">
							<option value=""><?php esc_html_e( '— Padrão global —', 'pb-affiliates' ); ?></option>
							<option value="percent" <?php selected( get_user_meta( $user->ID, 'pb_affiliate_commission_type', true ), 'percent' ); ?>><?php esc_html_e( 'Percentual', 'pb-affiliates' ); ?></option>
							<option value="fixed" <?php selected( get_user_meta( $user->ID, 'pb_affiliate_commission_type', true ), 'fixed' ); ?>><?php esc_html_e( 'Fixo', 'pb-affiliates' ); ?></option>
						</select>
						<input type="text" name="pb_affiliate_commission_value" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pb_affiliate_commission_value', true ) ); ?>" placeholder="10" />
					</td>
				</tr>
				<?php if ( 'split' === $mode ) : ?>
					<tr>
						<th><label for="pb_affiliate_pagbank_account_id"><?php esc_html_e( 'Account ID PagBank (split)', 'pb-affiliates' ); ?></label></th>
						<td>
							<input type="text" name="pb_affiliate_pagbank_account_id" id="pb_affiliate_pagbank_account_id" class="large-text" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pb_affiliate_pagbank_account_id', true ) ); ?>" placeholder="ACCO_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
							<p class="description">
								<a href="<?php echo esc_url( PB_Affiliates_Account::get_pagbank_account_id_help_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Qual é meu Account ID?', 'pb-affiliates' ); ?></a>
							</p>
						</td>
					</tr>
				<?php else : ?>
					<tr>
						<th><?php esc_html_e( 'Dados bancários (pagamento manual)', 'pb-affiliates' ); ?></th>
						<td>
							<?php PB_Affiliates_Bank_Combo::render_bank_code_field( (string) get_user_meta( $user->ID, 'pb_affiliate_bank_code', true ), 'admin' ); ?>
							<p><label><?php esc_html_e( 'Agência', 'pb-affiliates' ); ?> <input type="text" name="pb_affiliate_bank_agency" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pb_affiliate_bank_agency', true ) ); ?>" /></label></p>
							<p><label><?php esc_html_e( 'Conta e dígito', 'pb-affiliates' ); ?> <input type="text" name="pb_affiliate_bank_account" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pb_affiliate_bank_account', true ) ); ?>" /></label></p>
							<p><label><?php esc_html_e( 'CPF ou CNPJ', 'pb-affiliates' ); ?> <input type="text" name="pb_affiliate_bank_document" id="pb_affiliate_bank_document" class="regular-text" maxlength="18" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pb_affiliate_bank_document', true ) ); ?>" /></label></p>
						</td>
					</tr>
				<?php endif; ?>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * @param int $user_id User ID.
	 */
	public static function save( $user_id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . $user_id ) ) {
			return;
		}

		if ( isset( $_POST['pb_affiliate_status_field'] ) ) {
			$st = sanitize_key( wp_unslash( $_POST['pb_affiliate_status_field'] ) );
			if ( in_array( $st, array( 'none', 'pending', 'active' ), true ) ) {
				if ( 'none' === $st ) {
					update_user_meta( $user_id, 'pb_affiliate_status', PB_Affiliates_Role::STATUS_NONE );
				} else {
					update_user_meta( $user_id, 'pb_affiliate_status', $st );
					if ( PB_Affiliates_Role::STATUS_ACTIVE === $st ) {
						PB_Affiliates_Role::ensure_affiliate_code( $user_id );
					}
				}
			}
		}

		$in_program = PB_Affiliates_Role::user_is_active_or_pending_affiliate( $user_id );
		if ( ! $in_program ) {
			return;
		}

		if ( isset( $_POST['pb_affiliate_code'] ) ) {
			$code = PB_Affiliates_Attribution::sanitize_affiliate_code( wp_unslash( $_POST['pb_affiliate_code'] ) );
			if ( strlen( $code ) >= 3 && PB_Affiliates_Attribution::is_code_available( $code, $user_id ) ) {
				update_user_meta( $user_id, 'pb_affiliate_code', $code );
			}
		}

		$commission_fields = array(
			'pb_affiliate_commission_type',
			'pb_affiliate_commission_value',
		);
		foreach ( $commission_fields as $f ) {
			if ( isset( $_POST[ $f ] ) ) {
				update_user_meta( $user_id, $f, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
			}
		}

		$mode = PB_Affiliates_Settings::get()['payment_mode'] ?? 'manual';
		if ( 'split' === $mode ) {
			if ( isset( $_POST['pb_affiliate_pagbank_account_id'] ) ) {
				$raw = sanitize_text_field( wp_unslash( $_POST['pb_affiliate_pagbank_account_id'] ) );
				if ( '' === $raw ) {
					update_user_meta( $user_id, 'pb_affiliate_pagbank_account_id', '' );
				} elseif ( PB_Affiliates_Split::is_valid_account_id( $raw ) ) {
					update_user_meta( $user_id, 'pb_affiliate_pagbank_account_id', $raw );
				} else {
					set_transient(
						'pb_aff_profile_err_' . $user_id,
						__( 'Account ID PagBank inválido. Use o formato ACCO_ seguido do UUID.', 'pb-affiliates' ),
						45
					);
				}
			}
		} else {
			$bank_fields = array(
				'pb_affiliate_bank_code',
				'pb_affiliate_bank_agency',
				'pb_affiliate_bank_account',
				'pb_affiliate_bank_document',
			);
			foreach ( $bank_fields as $f ) {
				if ( ! isset( $_POST[ $f ] ) ) {
					continue;
				}
				$raw = sanitize_text_field( wp_unslash( $_POST[ $f ] ) );
				if ( 'pb_affiliate_bank_document' === $f ) {
					update_user_meta( $user_id, $f, PB_Affiliates_Account::sanitize_document_digits( $raw ) );
				} else {
					update_user_meta( $user_id, $f, $raw );
				}
			}
		}
	}
}
