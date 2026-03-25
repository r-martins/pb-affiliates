<?php
/**
 * Admin: materiais promocionais para afiliados.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Admin_Materials
 */
class PB_Affiliates_Admin_Materials {

	const PAGE_SLUG = 'pb-affiliates-materials';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_requests' ) );
	}

	/**
	 * Submenu Materiais.
	 */
	public static function register_submenu() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		add_submenu_page(
			PB_Affiliates_Admin::PARENT_SLUG,
			__( 'Materiais promocionais', 'pb-affiliates' ),
			__( 'Materiais', 'pb-affiliates' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_screen' )
		);
	}

	/**
	 * Scripts e estilos (tela de materiais + media modal).
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( empty( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style(
			'pb-aff-admin-materials',
			PB_AFFILIATES_URL . 'assets/css/admin-promotional-materials.css',
			array(),
			PB_AFFILIATES_VERSION
		);
		wp_enqueue_script(
			'pb-aff-admin-materials',
			PB_AFFILIATES_URL . 'assets/js/admin-promotional-materials.js',
			array( 'jquery', 'media-editor', 'media-grid' ),
			PB_AFFILIATES_VERSION,
			true
		);
		wp_localize_script(
			'pb-aff-admin-materials',
			'pbAffPromoMaterials',
			array(
				'frameTitle'  => __( 'Arquivo promocional', 'pb-affiliates' ),
				'frameButton' => __( 'Usar este arquivo', 'pb-affiliates' ),
			)
		);
	}

	/**
	 * POST/GET: guardar, eliminar, bulk.
	 */
	public static function handle_requests() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( empty( $_GET['page'] ) || self::PAGE_SLUG !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Eliminar individual (URL).
		if ( ! empty( $_GET['action'] ) && 'delete' === sanitize_key( wp_unslash( $_GET['action'] ) ) && ! empty( $_GET['material_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$mid = absint( $_GET['material_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'pb_aff_delete_material_' . $mid );
			if ( PB_Affiliates_Promotional_Materials::delete( $mid ) ) {
				wp_safe_redirect( add_query_arg( 'pb_aff_material_msg', 'deleted', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
				exit;
			}
			wp_safe_redirect( add_query_arg( 'pb_aff_material_msg', 'delete_fail', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
			exit;
		}

		// Exclusão em massa (formulário da lista; não usa pb_aff_material_nonce).
		if ( ! empty( $_POST['material'] ) && is_array( $_POST['material'] ) ) {
			$bulk_delete = false;
			if ( ! empty( $_POST['action'] ) && '-1' !== $_POST['action'] && 'delete' === sanitize_text_field( wp_unslash( $_POST['action'] ) ) ) {
				$bulk_delete = true;
			} elseif ( ! empty( $_POST['action2'] ) && '-1' !== $_POST['action2'] && 'delete' === sanitize_text_field( wp_unslash( $_POST['action2'] ) ) ) {
				$bulk_delete = true;
			}
			if ( $bulk_delete ) {
				check_admin_referer( 'bulk-pb_aff_materials' );
				$ids = array_map( 'absint', wp_unslash( $_POST['material'] ) );
				foreach ( $ids as $id ) {
					if ( $id > 0 ) {
						PB_Affiliates_Promotional_Materials::delete( $id );
					}
				}
				wp_safe_redirect( add_query_arg( 'pb_aff_material_msg', 'bulk_deleted', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
				exit;
			}
		}

		if ( empty( $_POST['pb_aff_material_submit'] ) ) {
			return;
		}
		if ( empty( $_POST['pb_aff_material_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pb_aff_material_nonce'] ) ), 'pb_aff_save_material' ) ) {
			return;
		}

		$data = array(
			'title'          => isset( $_POST['pb_aff_material_title'] ) ? wp_unslash( $_POST['pb_aff_material_title'] ) : '',
			'attachment_id'  => isset( $_POST['pb_aff_material_attachment_id'] ) ? absint( $_POST['pb_aff_material_attachment_id'] ) : 0,
			'menu_order'     => isset( $_POST['pb_aff_material_order'] ) ? (int) $_POST['pb_aff_material_order'] : 0,
			'material_id'    => isset( $_POST['pb_aff_material_id'] ) ? absint( $_POST['pb_aff_material_id'] ) : 0,
			'material_date'  => isset( $_POST['pb_aff_material_date'] ) ? wp_unslash( $_POST['pb_aff_material_date'] ) : '',
		);

		$result = PB_Affiliates_Promotional_Materials::save( $data );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'pb_aff_material_msg' => 'error',
						'pb_aff_err'          => rawurlencode( $result->get_error_message() ),
					),
					self::edit_url( $data['material_id'] )
				)
			);
			exit;
		}
		wp_safe_redirect( add_query_arg( 'pb_aff_material_msg', 'saved', admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	/**
	 * URL do formulário de edição.
	 *
	 * @param int $material_id ID (0 = novo).
	 * @return string
	 */
	public static function edit_url( $material_id = 0 ) {
		$args = array(
			'page'   => self::PAGE_SLUG,
			'action' => 'add',
		);
		if ( $material_id > 0 ) {
			$args['action']       = 'edit';
			$args['material_id'] = (int) $material_id;
		}
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Render principal.
	 */
	public static function render_screen() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'add' === $action || 'edit' === $action ) {
			self::render_edit_form();
			return;
		}

		require_once PB_AFFILIATES_PATH . 'includes/admin/class-pb-affiliates-admin-materials-list-table.php';

		if ( ! empty( $_GET['pb_aff_material_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$msg = sanitize_key( wp_unslash( $_GET['pb_aff_material_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'saved' === $msg ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Material salvo.', 'pb-affiliates' ) . '</p></div>';
			} elseif ( 'deleted' === $msg ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Material excluído.', 'pb-affiliates' ) . '</p></div>';
			} elseif ( 'bulk_deleted' === $msg ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Materiais excluídos.', 'pb-affiliates' ) . '</p></div>';
			} elseif ( 'delete_fail' === $msg ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Não foi possível excluir o material.', 'pb-affiliates' ) . '</p></div>';
			}
		}

		$table = new PB_Affiliates_Admin_Materials_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap pb-aff-materials-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Materiais promocionais', 'pb-affiliates' ); ?></h1>
			<a href="<?php echo esc_url( self::edit_url( 0 ) ); ?>" class="page-title-action"><?php esc_html_e( 'Adicionar material', 'pb-affiliates' ); ?></a>
			<hr class="wp-header-end" />
			<p class="description"><?php esc_html_e( 'Envie arquivos para a biblioteca de mídia; na loja eles ficam em uploads/pb-affiliates/promotional quando o envio é feito a partir desta tela (ou pelo seletor de mídia aqui).', 'pb-affiliates' ); ?></p>
			<?php
			$pb_aff_type_f = isset( $_GET['pb_aff_type'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<form method="get" class="pb-aff-materials-toolbar">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php if ( isset( $_GET['orderby'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<input type="hidden" name="orderby" value="<?php echo esc_attr( sanitize_key( wp_unslash( $_GET['orderby'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
				<?php endif; ?>
				<?php if ( isset( $_GET['order'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<input type="hidden" name="order" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
				<?php endif; ?>
				<p class="search-box">
					<label class="screen-reader-text" for="pb-aff-materials-search-input"><?php esc_html_e( 'Buscar materiais', 'pb-affiliates' ); ?></label>
					<input type="search" id="pb-aff-materials-search-input" name="s" value="<?php echo isset( $_GET['s'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
					<?php submit_button( __( 'Buscar materiais', 'pb-affiliates' ), '', '', false, array( 'id' => 'search-submit' ) ); ?>
				</p>
				<p class="pb-aff-materials-type-filter">
					<label for="pb_aff_type"><?php esc_html_e( 'Tipo', 'pb-affiliates' ); ?></label>
					<select name="pb_aff_type" id="pb_aff_type">
						<option value="" <?php selected( $pb_aff_type_f, '' ); ?>><?php esc_html_e( 'Todos', 'pb-affiliates' ); ?></option>
						<option value="image" <?php selected( $pb_aff_type_f, 'image' ); ?>><?php esc_html_e( 'Imagens', 'pb-affiliates' ); ?></option>
						<option value="pdf" <?php selected( $pb_aff_type_f, 'pdf' ); ?>><?php esc_html_e( 'PDF', 'pb-affiliates' ); ?></option>
						<option value="other" <?php selected( $pb_aff_type_f, 'other' ); ?>><?php esc_html_e( 'Outros', 'pb-affiliates' ); ?></option>
					</select>
					<?php submit_button( __( 'Filtrar', 'pb-affiliates' ), 'secondary', '', false ); ?>
				</p>
			</form>
			<form method="post">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Formulário add/edit.
	 */
	public static function render_edit_form() {
		$action      = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'add'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$material_id = isset( $_GET['material_id'] ) ? absint( $_GET['material_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post        = null;
		$att_id      = 0;
		if ( 'edit' === $action && $material_id > 0 ) {
			$post = get_post( $material_id );
			if ( ! $post || PB_Affiliates_Promotional_Materials::POST_TYPE !== $post->post_type ) {
				echo '<div class="wrap"><p>' . esc_html__( 'Material não encontrado.', 'pb-affiliates' ) . '</p></div>';
				return;
			}
			$att_id = (int) get_post_meta( $material_id, PB_Affiliates_Promotional_Materials::META_ATTACHMENT_ID, true );
		}

		$title       = $post ? get_the_title( $post ) : '';
		$menu_order  = $post ? (int) $post->menu_order : 0;
		$date_local  = '';
		if ( $post ) {
			$date_local = wp_date( 'Y-m-d\TH:i', strtotime( $post->post_date ) );
		}

		if ( ! empty( $_GET['pb_aff_material_msg'] ) && 'error' === sanitize_key( wp_unslash( $_GET['pb_aff_material_msg'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$err = isset( $_GET['pb_aff_err'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['pb_aff_err'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $err ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
			}
		}
		?>
		<div class="wrap pb-aff-materials-edit-wrap">
			<h1><?php echo $post ? esc_html__( 'Editar material', 'pb-affiliates' ) : esc_html__( 'Adicionar material', 'pb-affiliates' ); ?></h1>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ); ?>">&larr; <?php esc_html_e( 'Voltar à lista', 'pb-affiliates' ); ?></a></p>
			<form method="post" class="pb-aff-material-form">
				<?php wp_nonce_field( 'pb_aff_save_material', 'pb_aff_material_nonce' ); ?>
				<input type="hidden" name="pb_aff_material_submit" value="1" />
				<input type="hidden" name="pb_aff_material_id" value="<?php echo esc_attr( (string) ( $post ? $post->ID : 0 ) ); ?>" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pb_aff_material_title"><?php esc_html_e( 'Nome do material', 'pb-affiliates' ); ?></label></th>
						<td><input name="pb_aff_material_title" id="pb_aff_material_title" type="text" class="regular-text" required value="<?php echo esc_attr( $title ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="pb_aff_material_date"><?php esc_html_e( 'Data de cadastro', 'pb-affiliates' ); ?></label></th>
						<td>
							<input name="pb_aff_material_date" id="pb_aff_material_date" type="datetime-local" class="regular-text" value="<?php echo esc_attr( $date_local ); ?>" />
							<p class="description pb-aff-material-date-hint"><?php esc_html_e( 'Usada na listagem. Deixe em branco ao criar para usar a data e hora atuais.', 'pb-affiliates' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pb_aff_material_order"><?php esc_html_e( 'Ordem de exibição', 'pb-affiliates' ); ?></label></th>
						<td>
							<input name="pb_aff_material_order" id="pb_aff_material_order" type="number" class="small-text" value="<?php echo esc_attr( (string) $menu_order ); ?>" step="1" />
							<p class="description"><?php esc_html_e( 'Números menores aparecem primeiro na lista do afiliado.', 'pb-affiliates' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Arquivo', 'pb-affiliates' ); ?></th>
						<td>
							<input type="hidden" name="pb_aff_material_attachment_id" id="pb_aff_material_attachment_id" value="<?php echo esc_attr( (string) $att_id ); ?>" />
							<button type="button" class="button" id="pb_aff_material_select_file"><?php esc_html_e( 'Selecionar ou enviar arquivo…', 'pb-affiliates' ); ?></button>
							<button type="button" class="button" id="pb_aff_material_clear_file" <?php echo $att_id ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remover arquivo', 'pb-affiliates' ); ?></button>
							<div id="pb_aff_material_file_preview" class="pb-aff-material-file-preview">
								<?php
								if ( $att_id ) {
									$file = get_attached_file( $att_id );
									$base = $file ? basename( $file ) : '#' . $att_id;
									if ( wp_attachment_is_image( $att_id ) ) {
										echo '<div class="pb-aff-thumb-wrap">' . wp_get_attachment_image( $att_id, 'medium' ) . '</div>';
									}
									echo '<p class="pb-aff-filename"><strong>' . esc_html( $base ) . '</strong></p>';
								}
								?>
							</div>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Salvar material', 'pb-affiliates' ) ); ?>
			</form>
		</div>
		<?php
	}
}
