<?php
/**
 * Lista de materiais promocionais (admin).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table', false ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class PB_Affiliates_Admin_Materials_List_Table
 */
class PB_Affiliates_Admin_Materials_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'pb_aff_material',
				'plural'   => 'pb_aff_materials',
				'ajax'     => false,
			)
		);
	}

	/**
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'thumb'    => __( 'Pré-visualização', 'pb-affiliates' ),
			'title'    => __( 'Nome', 'pb-affiliates' ),
			'file'     => __( 'Arquivo', 'pb-affiliates' ),
			'date'     => __( 'Data', 'pb-affiliates' ),
			'order'    => __( 'Ordem', 'pb-affiliates' ),
			'actions'  => __( 'Ações', 'pb-affiliates' ),
		);
	}

	/**
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
			'date'  => array( 'post_date', true ),
			'order' => array( 'menu_order', false ),
		);
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Excluir', 'pb-affiliates' ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param WP_Post $item Post.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="material[]" value="' . esc_attr( (string) $item->ID ) . '" />';
	}

	/**
	 * @param WP_Post $item Post.
	 * @return string
	 */
	protected function column_thumb( $item ) {
		$att_id = (int) get_post_meta( $item->ID, PB_Affiliates_Promotional_Materials::META_ATTACHMENT_ID, true );
		if ( $att_id <= 0 ) {
			return '—';
		}
		if ( wp_attachment_is_image( $att_id ) ) {
			return wp_get_attachment_image( $att_id, array( 54, 54 ), false, array( 'style' => 'max-width:54px;height:auto;display:block;' ) );
		}
		return '<span class="dashicons dashicons-media-default" aria-hidden="true"></span>';
	}

	/**
	 * @param WP_Post $item Post.
	 * @return string
	 */
	protected function column_title( $item ) {
		$url  = add_query_arg(
			array(
				'page'         => PB_Affiliates_Admin_Materials::PAGE_SLUG,
				'action'       => 'edit',
				'material_id' => (int) $item->ID,
			),
			admin_url( 'admin.php' )
		);
		$del  = wp_nonce_url(
			add_query_arg(
				array(
					'page'         => PB_Affiliates_Admin_Materials::PAGE_SLUG,
					'action'       => 'delete',
					'material_id' => (int) $item->ID,
				),
				admin_url( 'admin.php' )
			),
			'pb_aff_delete_material_' . (int) $item->ID
		);
		$actions = array(
			'edit'   => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Editar', 'pb-affiliates' ) . '</a>',
			'delete' => '<a href="' . esc_url( $del ) . '" class="pb-aff-material-delete">' . esc_html__( 'Excluir', 'pb-affiliates' ) . '</a>',
		);
		return '<strong><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $item ) ) . '</a></strong>' . $this->row_actions( $actions );
	}

	/**
	 * @param WP_Post $item Post.
	 * @return string
	 */
	protected function column_file( $item ) {
		$att_id = (int) get_post_meta( $item->ID, PB_Affiliates_Promotional_Materials::META_ATTACHMENT_ID, true );
		if ( $att_id <= 0 ) {
			return '—';
		}
		$file = get_attached_file( $att_id );
		$base = $file ? basename( $file ) : '#' . $att_id;
		$mime = (string) get_post_meta( $item->ID, PB_Affiliates_Promotional_Materials::META_FILE_MIME, true );
		if ( '' === $mime ) {
			$mime = (string) get_post_mime_type( $att_id );
		}
		$edit = get_edit_post_link( $att_id, 'raw' );
		$link = $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $base ) . '</a>' : esc_html( $base );
		return $link . '<br /><span class="description">' . esc_html( $mime ) . '</span>';
	}

	/**
	 * @param WP_Post $item Post.
	 * @return string
	 */
	protected function column_date( $item ) {
		return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->post_date ) );
	}

	/**
	 * @param WP_Post $item Post.
	 * @return string
	 */
	protected function column_order( $item ) {
		return esc_html( (string) (int) $item->menu_order );
	}

	/**
	 * @param WP_Post $item Post.
	 * @return string
	 */
	protected function column_actions( $item ) {
		$att_id = (int) get_post_meta( $item->ID, PB_Affiliates_Promotional_Materials::META_ATTACHMENT_ID, true );
		if ( $att_id <= 0 ) {
			return '—';
		}
		$url = wp_get_attachment_url( $att_id );
		if ( ! $url ) {
			return '—';
		}
		return '<a class="button button-small" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir arquivo', 'pb-affiliates' ) . '</a>';
	}

	/**
	 * Default column.
	 *
	 * @param WP_Post $item Post.
	 * @param string  $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return '';
	}

	/**
	 * Prepare items.
	 */
	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();
		$orderby  = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'menu_order'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = isset( $_GET['order'] ) && 'desc' === strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) ? 'DESC' : 'ASC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$s        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$orderby_map = array(
			'title'      => 'title',
			'post_date'  => 'post_date',
			'menu_order' => 'menu_order',
		);
		if ( ! isset( $orderby_map[ $orderby ] ) ) {
			$orderby = 'menu_order';
		}

		$meta_query = array();
		$type       = isset( $_GET['pb_aff_type'] ) ? sanitize_key( wp_unslash( $_GET['pb_aff_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'image' === $type ) {
			$meta_query[] = array(
				'key'     => PB_Affiliates_Promotional_Materials::META_FILE_MIME,
				'value'   => 'image/',
				'compare' => 'LIKE',
			);
		} elseif ( 'pdf' === $type ) {
			$meta_query[] = array(
				'key'     => PB_Affiliates_Promotional_Materials::META_FILE_MIME,
				'value'   => 'pdf',
				'compare' => 'LIKE',
			);
		} elseif ( 'other' === $type ) {
			$meta_query[] = array(
				'relation' => 'AND',
				array(
					'key'     => PB_Affiliates_Promotional_Materials::META_FILE_MIME,
					'value'   => 'image/',
					'compare' => 'NOT LIKE',
				),
				array(
					'key'     => PB_Affiliates_Promotional_Materials::META_FILE_MIME,
					'value'   => 'pdf',
					'compare' => 'NOT LIKE',
				),
			);
		}

		$db_orderby = $orderby_map[ $orderby ];
		$args       = array(
			'post_type'      => PB_Affiliates_Promotional_Materials::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			's'              => $s,
			'meta_query'     => $meta_query,
		);
		if ( 'menu_order' === $db_orderby ) {
			$args['orderby'] = array(
				'menu_order' => $order,
				'post_date'  => 'DESC',
			);
		} else {
			$args['orderby'] = $db_orderby;
			$args['order']    = $order;
		}

		$q              = new WP_Query( $args );
		$this->items    = $q->posts;
		$total          = (int) $q->found_posts;
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}
}
