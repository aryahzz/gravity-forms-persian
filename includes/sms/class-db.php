<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create tables, make relations, helper methods
 */
class GFPersian_SMS_DB {

	/**
	 * @var object WordPress Database
	 */
	private static object $wpdb;

	/**
	 * @var string
	 */
	private static string $prefix;

	/**
	 * @var string sms table name
	 */
	public static string $sms_table;

	/**
	 * @var string sms verification table
	 */
	public static string $verification_table;


	/**
	 * @var string Gravity Forms table
	 */
	private static string $form_table;

	public static function init() {
		global $wpdb;

		self::$sms_table          = $wpdb->prefix . 'gf_sms_sent';
		self::$verification_table = $wpdb->prefix . 'gf_sms_verification';
		self::$form_table         = RGFormsModel::get_form_table_name();
	}

	public static function save_sms_sent( $form_id, $entry_id, $sender, $receiver, $message, $verify_code = '' ) {
		global $wpdb;

		if ( empty( $entry_id ) ) {
			$entry_id = ! empty( $verify_code ) ? '_' . $verify_code . '_' : '';
		} else {
			$entry_id = is_array( $entry_id ) ? implode( ',', $entry_id ) : $entry_id;
		}

		$form_id = ! empty( $form_id ) ? $form_id : 0;

		$receiver = is_array( $receiver ) ? implode( ',', $receiver ) : $receiver;

		$wpdb->insert(
			self::$sms_table,
			[
				'date'     => date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ),
				'form_id'  => $form_id,
				'entry_id' => $entry_id,
				'sender'   => $sender,
				'reciever' => $receiver, // TODO: Rename the column with rollback support
				'message'  => $message
			],
			[
				'%s',  // date
				'%d',  // form_id
				'%s',  // entry_id
				'%s',  // sender
				'%s',  // receiver
				'%s'   // message
			]
		);

		if ( $wpdb->last_error ) {
			error_log( 'Database Error: ' . $wpdb->last_error );
		}
	}

	public static function update_entry_verify_sent( $form_id, $entry_id, $verify_code ) {
		global $wpdb;

		$form_id = ! empty( $form_id ) ? $form_id : 0;

		// Handle entry_id: set to empty string if it's not provided
		if ( empty( $entry_id ) ) {
			$entry_id = '';
		} else {
			// Convert entry_id to a string if it's an array
			$entry_id = is_array( $entry_id ) ? implode( ',', $entry_id ) : $entry_id;
		}

		// Format verify_code
		$verify_code = '_' . $verify_code . '_';

		$wpdb->update(
			self::$sms_table,
			[
				'entry_id' => $entry_id,
			],
			[
				'form_id'  => $form_id,
				'entry_id' => $verify_code,
			],
			[ '%s' ], // Format for entry_id
			[ '%d', '%s' ] // Formats for form_id and verify_code
		);
	}

	public static function insert_verify( $form_id, $entry_id, $mobile, $code, $status, $try_num, $sent_num ) {
		global $wpdb;

		$sent_verify_table = self::$verification_table;
		$entry_id          = ! empty( $entry_id ) ? $entry_id : '';
		$form_id           = ! empty( $form_id ) ? $form_id : 0;

		$wpdb->insert(
			$sent_verify_table,
			[
				'form_id'  => $form_id,
				'entry_id' => $entry_id,
				'mobile'   => $mobile,
				'code'     => $code,
				'try_num'  => $try_num,
				'sent_num' => $sent_num,
				'status'   => $status
			],
			[
				'%d', // form_id
				'%d', // entry_id
				'%s', // mobile
				'%s', // code
				'%d', // try_num
				'%d', // sent_num
				'%d'  // status
			]
		);
	}

	public static function update_verify( $id, $try_num, $sent_num, $entry_id, $status ) {
		global $wpdb;

		$entry_id = ! empty( $entry_id ) ? $entry_id : '';
		$wpdb->update(
			self::$verification_table,
			[
				'entry_id' => $entry_id,
				'try_num'  => $try_num,
				'sent_num' => $sent_num,
				'status'   => $status
			],
			[ 'id' => $id ],
			[
				'%s', // entry_id
				'%d', // try_num
				'%d', // sent_num
				'%d'  // status
			],
			[ '%d' ] // id
		);
	}

	/**
	 * Check if a sms is already sent and stored in the gf_sms_sent
	 *
	 * @param int $entry_id
	 * @param int $form_id
	 * @param string $message
	 *
	 * @return bool
	 * */
	public static function check_sms_sent( int $entry_id, int $form_id, string $receiver = '', string $message = '' ): bool {
		global $wpdb;

		$sql = $wpdb->prepare( "SELECT entry_id FROM " . self::$sms_table . " WHERE entry_id = %d AND form_id = %d AND reciever = %s AND message = %s ", $entry_id, $form_id, $receiver, $message );

		$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $results ) ) {
			return false;
		}

		return true;
	}

}

GFPersian_SMS_DB::init();