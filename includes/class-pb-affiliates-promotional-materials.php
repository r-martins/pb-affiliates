<?php
/**
 * Materiais promocionais para afiliados (arquivos na biblioteca de mídia).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Promotional_Materials
 */
class PB_Affiliates_Promotional_Materials {

	const POST_TYPE = 'pb_aff_promo_material';

	const META_ATTACHMENT_ID = '_pb_aff_attachment_id';

	const META_FILE_MIME = '_pb_aff_file_mime';

	/** Subpasta dentro de uploads (sem slash inicial). */
	const UPLOAD_SUBDIR = 'pb-affiliates/promotional';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 5 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap_for_material' ), 10, 4 );
		add_action( 'wp_ajax_upload-attachment', array( __CLASS__, 'maybe_apply_promo_upload_dir' ), 0 );
		add_action( 'wp_ajax_upload-attachment', array( __CLASS__, 'remove_promo_upload_dir_filter_late' ), 999 );
	}

	/**
	 * Permite manage_woocommerce editar/apagar registos do CPT.
	 *
	 * @param array  $caps Caps.
	 * @param string $cap  Capability.
	 * @param int    $user_id User.
	 * @param array  $args Args.
	 * @return array
	 */
	public static function map_meta_cap_for_material( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( (string) $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) ) {
			return $caps;
		}
		if ( empty( $args[0] ) ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return $caps;
		}
		if ( user_can( (int) $user_id, 'manage_woocommerce' ) ) {
			return array( 'manage_woocommerce' );
		}
		return $caps;
	}

	/**
	 * Redireciona uploads do media modal na tela de materiais para subpasta dedicada.
	 */
	public static function maybe_apply_promo_upload_dir() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$use_subdir = false;
		if ( ! empty( $_POST['pb_aff_promo_upload'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['pb_aff_promo_upload'] ) ) ) {
			$use_subdir = true;
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$ref = wp_unslash( $_SERVER['HTTP_REFERER'] );
			if ( is_string( $ref ) && str_contains( $ref, 'page=pb-affiliates-materials' ) ) {
				$use_subdir = true;
			}
		}
		if ( $use_subdir ) {
			add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ), 20 );
		}
	}

	/**
	 * Garante que o filtro de pasta não afete outros uploads AJAX na mesma requisição.
	 */
	public static function remove_promo_upload_dir_filter_late() {
		remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ), 20 );
	}

	/**
	 * @param array $uploads Upload paths.
	 * @return array
	 */
	public static function filter_upload_dir( $uploads ) {
		if ( ! is_array( $uploads ) ) {
			return $uploads;
		}
		$subdir              = '/' . self::UPLOAD_SUBDIR;
		$uploads['subdir']   = $subdir;
		$uploads['path']     = $uploads['basedir'] . $subdir;
		$uploads['url']      = $uploads['baseurl'] . $subdir;
		$uploads['relative'] = ltrim( $subdir, '/' );
		wp_mkdir_p( $uploads['path'] );
		return $uploads;
	}

	/**
	 * Regista CPT interno (sem UI própria do core).
	 */
	public static function register_post_type() {
		$labels = array(
			'name'          => __( 'Materiais promocionais', 'pb-affiliates' ),
			'singular_name' => __( 'Material promocional', 'pb-affiliates' ),
		);
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
			)
		);
	}

	/**
	 * Há pelo menos um material com anexo válido para mostrar aos afiliados.
	 *
	 * @return bool
	 */
	public static function has_displayable_materials() {
		$items = self::get_items_for_affiliate_display();
		return ! empty( $items );
	}

	/**
	 * @return int
	 */
	public static function count_published() {
		$q = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Materiais para área do afiliado (por ordem de exibição).
	 *
	 * @return array<int, array{id:int,title:string,attachment_id:int,date:string,url:string,thumb_url:string,is_image:bool,mime:string}>
	 */
	public static function get_items_for_affiliate_display() {
		$q = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'post_date'  => 'DESC',
				),
				'no_found_rows'  => true,
			)
		);
		// WP does not sort by menu_order naturally without 'orderby' => 'menu_order'.
		$items = array();
		if ( ! $q->have_posts() ) {
			return $items;
		}
		foreach ( $q->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}
			$att_id = (int) get_post_meta( $post->ID, self::META_ATTACHMENT_ID, true );
			if ( $att_id <= 0 ) {
				continue;
			}
			$url = wp_get_attachment_url( $att_id );
			if ( ! $url ) {
				continue;
			}
			$is_image = wp_attachment_is_image( $att_id );
			$thumb    = $is_image ? wp_get_attachment_image_url( $att_id, 'thumbnail' ) : '';
			$mime     = (string) get_post_mime_type( $att_id );
			$items[]  = array(
				'id'            => (int) $post->ID,
				'title'         => get_the_title( $post ),
				'attachment_id' => $att_id,
				'date'          => $post->post_date,
				'url'           => $url,
				'thumb_url'     => $thumb ? $thumb : '',
				'is_image'      => $is_image,
				'mime'          => $mime,
			);
		}
		return $items;
	}

	/**
	 * Guarda material (criar ou atualizar).
	 *
	 * @param array $data title, attachment_id, menu_order, material_id (0 = novo), material_date (Y-m-d H:i:s opcional).
	 * @return int|WP_Error Post ID ou erro.
	 */
	public static function save( array $data ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'pb_aff_forbidden', __( 'Permissão negada.', 'pb-affiliates' ) );
		}
		$title = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '';
		if ( '' === $title ) {
			return new WP_Error( 'pb_aff_title', __( 'Informe o nome do material.', 'pb-affiliates' ) );
		}
		$att_id = isset( $data['attachment_id'] ) ? absint( $data['attachment_id'] ) : 0;
		if ( $att_id <= 0 ) {
			return new WP_Error( 'pb_aff_file', __( 'Selecione um arquivo na biblioteca de mídia.', 'pb-affiliates' ) );
		}
		$att = get_post( $att_id );
		if ( ! $att || 'attachment' !== $att->post_type ) {
			return new WP_Error( 'pb_aff_file', __( 'Anexo inválido.', 'pb-affiliates' ) );
		}
		$menu_order = isset( $data['menu_order'] ) ? (int) $data['menu_order'] : 0;
		$id         = isset( $data['material_id'] ) ? absint( $data['material_id'] ) : 0;

		$post_date = '';
		if ( ! empty( $data['material_date'] ) ) {
			$ts = pb_aff_promo_parse_admin_datetime( $data['material_date'] );
			if ( is_string( $ts ) ) {
				$post_date = $ts;
			}
		}

		$postarr = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => $title,
			'post_status' => 'publish',
			'menu_order'  => $menu_order,
			'post_author' => get_current_user_id(),
		);
		if ( '' !== $post_date ) {
			$postarr['post_date']     = $post_date;
			$postarr['post_date_gmt'] = get_gmt_from_date( $post_date );
			$postarr['edit_date']     = true;
		}

		if ( $id > 0 ) {
			$existing = get_post( $id );
			if ( ! $existing || self::POST_TYPE !== $existing->post_type ) {
				return new WP_Error( 'pb_aff_nf', __( 'Material não encontrado.', 'pb-affiliates' ) );
			}
			$postarr['ID'] = $id;
			$result        = wp_update_post( wp_slash( $postarr ), true );
		} else {
			if ( '' === $post_date ) {
				unset( $postarr['post_date'], $postarr['post_date_gmt'], $postarr['edit_date'] );
			}
			$result = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$pid = (int) $result;
		update_post_meta( $pid, self::META_ATTACHMENT_ID, $att_id );
		update_post_meta( $pid, self::META_FILE_MIME, (string) get_post_mime_type( $att_id ) );
		return $pid;
	}

	/**
	 * @param int $material_id Post ID.
	 * @return bool
	 */
	public static function delete( $material_id ) {
		$material_id = absint( $material_id );
		if ( $material_id <= 0 || ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}
		$p = get_post( $material_id );
		if ( ! $p || self::POST_TYPE !== $p->post_type ) {
			return false;
		}
		return (bool) wp_delete_post( $material_id, true );
	}
}

if ( ! function_exists( 'pb_aff_promo_parse_admin_datetime' ) ) {
	/**
	 * Converte string de data (admin) para mysql.
	 *
	 * @param mixed $raw Data enviada.
	 * @return string|false
	 */
	function pb_aff_promo_parse_admin_datetime( $raw ) {
		if ( ! is_string( $raw ) ) {
			return false;
		}
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return false;
		}
		$d = date_create_from_format( 'Y-m-d H:i', $raw, wp_timezone() );
		if ( ! $d ) {
			$d = date_create_from_format( 'Y-m-d\TH:i', $raw, wp_timezone() );
		}
		if ( ! $d ) {
			return false;
		}
		return $d->format( 'Y-m-d H:i:s' );
	}
}
