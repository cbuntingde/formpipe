<?php
namespace FormPipe;

defined( 'ABSPATH' ) || exit;

/**
 * List table for the All Forms admin screen.
 */
final class FormListTable extends \WP_List_Table {

	public const PER_PAGE = 'formpipe_forms_per_page';

	public function __construct() {
		parent::__construct( [
			'singular' => 'form',
			'plural'   => 'forms',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'cb'           => '<input type="checkbox" />',
			'title'        => __( 'Title', 'formpipe' ),
			'shortcode'    => __( 'Shortcode', 'formpipe' ),
			'count'        => __( 'Submissions', 'formpipe' ),
			'mail_active'  => __( 'Mail', 'formpipe' ),
			'date'         => __( 'Date', 'formpipe' ),
		];
	}

	protected function get_bulk_actions(): array {
		return [
			'delete' => __( 'Delete', 'formpipe' ),
		];
	}

	public function prepare_items(): void {
		$this->_column_headers = [ $this->get_columns(), [], [] ];

		$per_page = (int) ( get_user_option( self::PER_PAGE ) ?: 20 );
		$paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$search  = (string) ( $_GET['s'] ?? '' );

		$q = new \WP_Query( [
			'post_type'      => Form::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			's'              => $search,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => false,
		] );

		$this->items = array_map( static function ( $post ) {
			return Form::from_post( $post );
		}, $q->posts );

		$this->set_pagination_args( [
			'total_items' => $q->found_posts,
			'per_page'    => $per_page,
			'total_pages' => (int) $q->max_num_pages,
		] );
	}

	public function column_cb( $item ): string {
		if ( $item instanceof Form ) {
			return sprintf( '<input type="checkbox" name="post_ID[]" value="%d" />', $item->id() );
		}
		return '';
	}

	public function column_title( $item ): string {
		if ( ! $item instanceof Form ) {
			return '';
		}
		$edit_link = admin_url( 'admin.php?page=formpipe-new&post=' . $item->id() );
		$actions   = [
			'edit'   => '<a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Edit', 'formpipe' ) . '</a>',
			'copy'   => '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'
				. wp_nonce_field( 'formpipe_copy_' . $item->id(), '_wpnonce', true, false )
				. '<input type="hidden" name="action" value="formpipe_copy" />'
				. '<input type="hidden" name="post_ID" value="' . esc_attr( (string) $item->id() ) . '" />'
				. '<button class="button-link" type="submit">' . esc_html__( 'Duplicate', 'formpipe' ) . '</button>'
				. '</form>',
			'delete' => '<a class="submitdelete" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=formpipe_delete&post_ID=' . $item->id() ), 'bulk-formpipe-forms' ) ) . '">' . esc_html__( 'Delete', 'formpipe' ) . '</a>',
		];

		return sprintf(
			'<strong><a href="%1$s">%2$s</a></strong>%3$s',
			esc_url( $edit_link ),
			esc_html( $item->title() ?: __( '(no title)', 'formpipe' ) ),
			$this->row_actions( $actions )
		);
	}

	public function column_shortcode( $item ): string {
		if ( ! $item instanceof Form ) {
			return '';
		}
		return '<code>' . esc_html( $item->shortcode() ) . '</code>';
	}

	public function column_count( $item ): string {
		if ( ! $item instanceof Form ) {
			return '';
		}
		return '—';
	}

	public function column_mail_active( $item ): string {
		if ( ! $item instanceof Form ) {
			return '';
		}
		return ! empty( $item->mail['active'] )
			? '<span class="dashicons dashicons-yes" aria-label="' . esc_attr__( 'Active', 'formpipe' ) . '"></span>'
			: '<span class="dashicons dashicons-minus" aria-label="' . esc_attr__( 'Inactive', 'formpipe' ) . '"></span>';
	}

	public function column_date( $item ): string {
		if ( ! $item instanceof Form ) {
			return '';
		}
		$post = get_post( $item->id() );
		return $post ? esc_html( mysql2date( __( 'Y/m/d', 'formpipe' ), $post->post_date_gmt ) ) : '';
	}

	public function no_items(): void {
		esc_html_e( 'No forms found.', 'formpipe' );
	}
}
