<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class GFPersian_SMS_Feeds_List extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'singular' => 'feed',
			'plural'   => 'feeds',
			'ajax'     => true,
			'screen'   => null,
		] );
	}

	public function no_items() {
		echo "پیامکی یافت نشد.";
	}

	public function get_columns() {
		$columns = [
			'cb'         => '<input type="checkbox" />',
			'form_title' => 'عنوان فرم',
			'entries'    => 'ورودی ها',
		];

		return $columns;
	}


	function get_bulk_actions() {
		$actions = [
			'delete' => 'حذف'
		];

		return $actions;
	}

	public function get_row_actions( $actions, $item ) {
		$actions['edit']         = '<a title="فیدهای فرم" href="' . admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=settings&subview=sms_notification&id=' . $item['id'] ) . '">تنظیمات</a>';
		$actions['edit_form']    = '<a title="ویرایش فرم" href="' . admin_url( 'admin.php?page=gf_edit_forms&id=' . $item['id'] ) . '">ویرایش فرم</a>';
		$actions['view_entries'] = '<a title="ورودی‌ها" href="' . admin_url( 'admin.php?page=gf_entries&view=entries&id=' . $item['id'] ) . '">ورودی‌ها</a>';
		$actions['form_outbox']  = '<a title="صندوق خروجی" href="' . admin_url( 'admin.php?page=gfpersian_sent_table&view=sent&id=' . $item['id'] ) . '">صندوق خروجی فرم</a>';

		return implode( ' | ', $actions );
	}

	public function prepare_items() {
		$forms = GFPersian_SMS::get_active_forms();


		if ( empty( $forms ) ) {
			$forms = [];
		}

		$columns               = $this->get_columns();
		$hidden                = [];
		$sortable              = [];
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$per_page     = $this->get_items_per_page( 'feeds_per_page', 10 );
		$current_page = $this->get_pagenum();
		$total_items  = count( $forms );

		$this->items = array_slice( $forms, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		] );
	}


	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="bluk_action_ids[]" value="%s" />', $item['id'] );
	}

	public function column_entries( $item ) {
		$link  = admin_url( 'admin.php?page=gf_entries&view=entries&id=' . $item['id'] );
		$count = class_exists( 'GFAPI' ) && method_exists( 'GFAPI', 'count_entries' )
			? GFAPI::count_entries( $item['id'], [] )
			: RGFormsModel::get_lead_count( $item['id'], '', null, null, null, null, null );

		return '<a href="' . esc_url( $link ) . '">' . esc_html( $count ) . '</a>';

	}

	public function column_id( $item ) {
		return $item['id'] ?? '-';
	}

	public function column_form_title( $item ) {
		return  '<b>'.$item['title'] . '</b><br>' . $this->get_row_actions( [], $item );
	}

}


