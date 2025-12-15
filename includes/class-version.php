<?php
defined( 'ABSPATH' ) || exit;

class GFPersian_Version extends \Nabik_Net_Version {

	protected string $current_version = GF_PERSIAN_VERSION;

	public function updated() {
		flush_rewrite_rules();
	}

	public static function update_300() {
		global $wpdb;

		// Update national id
		$table  = GFFormsModel::get_meta_table_name();
		$update = $wpdb->query( "UPDATE $table SET display_meta = REPLACE(display_meta, 'mellicart', 'ir_national_id')" );

		if ( $update ) {
			$wpdb->query( "UPDATE $table SET display_meta = REPLACE(display_meta, '\"field_ir_national_id\"', '\"showLocation\"')" );
			$wpdb->query( "UPDATE $table SET display_meta = REPLACE(display_meta, '\"field_ir_national_id_sp\"', '\"showSeparator\"')" );
			$wpdb->query( "UPDATE $table SET display_meta = REPLACE(display_meta, '\"field_ir_national_id_sp1\"', '\"notDigitError\"')" );
			$wpdb->query( "UPDATE $table SET display_meta = REPLACE(display_meta, '\"field_ir_national_id_sp2\"', '\"qtyDigitError\"')" );
			$wpdb->query( "UPDATE $table SET display_meta = REPLACE(display_meta, '\"field_ir_national_id_sp3\"', '\"duplicateError\"')" );
			$wpdb->query( "UPDATE $table SET display_meta = REPLACE(display_meta, '\"field_ir_national_id_sp4\"', '\"isInvalidError\"')" );
		}

		for ( $i = 1; $i <= 5; $i ++ ) {
			delete_option( 'persian_gf_notice_v' . $i );
		}

	}

}

new GFPersian_Version();
