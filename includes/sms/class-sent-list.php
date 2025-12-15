<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class GFPersian_SMS_Sent_List_Table extends WP_List_Table {

	function __construct() {
		parent::__construct( [
			'singular' => 'sms',
			'plural'   => 'sms',
			'ajax'     => false
		] );
	}

	function no_items() {
		echo 'پیامکی یافت نشد.';
	}

	function column_default( $item, $column_name ) {
		$align = is_rtl() ? 'right' : 'left';
		switch ( $column_name ) {
			case 'entry_id':
				$entry_id = intval( $item['entry_id'] ?? 0 );

				if ( empty( $entry_id ) ) {
					return '-';
				}

				$form_id    = intval( $item['form_id'] ?? 0 );
				$entry_link = GFPersian_Core::get_form_entry_link( $form_id, $entry_id );

				return sprintf( '<a target="_blank" href="%s">%s</a>', esc_url( $entry_link ), $entry_id );
			case 'message':
				return $item[ $column_name ];
			case 'date':
				return date_i18n( 'Y-m-d H:i:s', strtotime( $item[ $column_name ] ) );
			case 'sender':
			case 'reciever':
				return '<div style="direction:ltr !important;text-align:' . $align . ';">' . $item[ $column_name ] . '</div>';
			default:
				return print_r( $item, true );
		}
	}

	public function column_form_title( $item ) {
		$edit_link = admin_url( 'admin.php?subview=sms&page=gf_edit_forms&view=settings&id=' . $item['form_id'] );

		return '<a href="' . esc_url( $edit_link ) . '" target="_blank">' . $item['form_title'] . '</a><br>' . $this->get_row_actions( [], $item );

	}

	function get_sortable_columns() {
		$sortable_columns = [
			'date'     => [ 'date', false ],
			'entry_id' => [ 'entry_id', false ],
			'sender'   => [ 'sender', false ],
			'reciever' => [ 'reciever', false ],
			'message'  => [ 'message', false ]
		];

		return $sortable_columns;
	}

	function get_columns() {
		$columns = [
			'cb'       => '<input type="checkbox" />',
			'date'     => 'تاریخ',
			'entry_id' => 'شناسه ورودی',
			'sender'   => 'از',
			'reciever' => 'به',
			'message'  => 'پیامک'
		];

		return $columns;
	}

	function column_date( $item ) {

		$delete_nonce = wp_create_nonce( 'gf_delete_sms' );

		$actions = [
			'delete' => sprintf( '<a href="?page=%s&view=sent&action=%s&item=%s&_wpnonce=%s">%s</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce, 'حذف' ),
		];

		return sprintf( '%1$s %2$s', date_i18n( 'Y-m-d H:i:s', strtotime( $item['date'] ) ), $this->row_actions( $actions ) );
	}

	function get_bulk_actions() {
		$actions = [
			'bulk_delete' => 'حذف'
		];

		return $actions;
	}

	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="item[]" value="%s" />', $item['id']
		);
	}

	function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = [];
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$this->process_bulk_action();

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$total_items  = $this->record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page
		] );
		$this->items = $this->get_items( $per_page, $current_page );
	}


	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public function record_count() {
		global $wpdb;

		$sent_table_name = GFPersian_SMS_DB::$sms_table;

		$sql = "SELECT COUNT(*) FROM {$sent_table_name}";

		if ( isset( $_REQUEST['id'] ) ) {
			$sql .= ' WHERE `form_id` LIKE "%%' . $wpdb->esc_like( $_REQUEST['id'] ) . '%%"';
		}

		return $wpdb->get_var( $sql );
	}

	public function process_bulk_action() {

		$action = $this->current_action();

		if ( 'delete' === $action ) {

			if ( ! empty( $_REQUEST ) && ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'gf_delete_sms' ) ) {
				die( 'درخواست نامعتبر.' );
			}


			$this->delete_item( absint( $_REQUEST['item'] ) );

			echo '<div class="updated notice is-dismissible below-h2"><p>پیامک حذف شد.</p></div>';
		} elseif ( $action == 'bulk_delete' ) {

			if ( ! empty( $_REQUEST ) && ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'bulk-' . $this->_args['plural'] ) ) {
				die( 'درخواست نامعتبر' );
			}


			$delete_ids = isset( $_REQUEST['item'] ) ? esc_sql( $_REQUEST['item'] ) : [];
			foreach ( (array) $delete_ids as $id ) {
				$this->delete_item( absint( $id ) );
			}

			echo '<div class="updated notice is-dismissible below-h2"><p>پیامک ها حذف شدند.</p></div>';

		}
	}


	/**
	 * Delete a item record.
	 *
	 * @param int $id item ID
	 */
	public function delete_item( $id ) {
		global $wpdb;

		$sent_table_name = GFPersian_SMS_DB::$sms_table;

		$wpdb->delete( $sent_table_name, [ 'id' => $id ] );
	}

	/**
	 * Retrieve items data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public function get_items( $per_page = 20, $page_number = 1 ) {
		global $wpdb;

		$sent_table_name = GFPersian_SMS_DB::$sms_table;

		$sql = "SELECT * FROM {$sent_table_name}";

		if ( isset( $_REQUEST['s'] ) ) {
			$sql .= ' WHERE `message` LIKE "%%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%%" OR `reciever` LIKE "%%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%%"  OR `sender` LIKE "%%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%%"';
		} elseif ( isset( $_REQUEST['id'] ) ) {
			$sql .= ' WHERE `form_id` LIKE "%%' . $wpdb->esc_like( $_REQUEST['id'] ) . '%%"';
		}

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		} else {
			$sql .= ' ORDER BY id DESC';
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}

}

