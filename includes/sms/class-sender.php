<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFPersian_SMS_Sender {
	/**
	 * Contains SMS gateway general settings
	 *
	 * @var array
	 */
	public static array $settings;
	private static $submission;

	public function __construct() {
		self::$settings = GFPersian_SMS::get_options();

		add_filter( 'gform_confirmation', [ $this, 'after_submit' ], 100, 4 );

		add_action( 'gform_paypal_fulfillment', [ $this, 'paypal_fulfillment' ], 100, 4 );
		add_filter( 'gform_replace_merge_tags', [ $this, 'tags' ], 100, 7 );


		add_action( 'gform_post_payment_action', [ $this, 'post_payment_action' ], 10, 2 );
		add_action( 'gform_post_payment_status', [ $this, 'payment_sms_rollback_support' ], 10, 4 );
	}

	/**
	 * Rollback support for payment gateways, It'll send sms based on payment status
	 */
	public static function payment_sms_rollback_support( $config, $entry, $status, $transaction_id ) {
		$form   = RGFormsModel::get_form_meta( $entry['form_id'] );
		$status = strtolower( $status );

		if ( in_array( $status, [ 'complete', 'complete_payment', 'success', 'completed' ] ) ) {
			$status = 'complete_payment';
		} else {
			$status = 'fail_payment';
		}

		self::send_sms_form( $entry, $form, $status, 'immediately' );

	}

	/**
	 * Payment statuses in gravity forms :
	 *
	 * complete_payment
	 * refund_payment
	 * fail_payment
	 * void_authorization
	 * create_subscription
	 * cancel_subscription
	 * expire_subscription
	 * add_subscription_payment
	 * fail_subscription_payment
	 * fail_create_subscription
	 *
	 */
	public function post_payment_action( $entry, $action = 'fail_payment' ) {
		$form = GFAPI::get_form( $entry['form_id'] );
		self::send_sms_form( $entry, $form, $action, 'immediately' );
	}


	/**
	 * Send verification sms
	 */
	public static function send( $receiver, $message, $from = '', $form_id = '', $entry_id = '', $verify_code = '' ) {
		$receiver = self::change_mobile( $receiver, '' );
		$from     = ( ! empty( $from ) && $from != '' ) ? $from : self::$settings['from_default'];
		$result   = GFPersian_SMS_Gateway::action( self::$settings, "send", $from, $receiver, $message );
		if ( $result == 'OK' ) {
			GFPersian_SMS_DB::save_sms_sent( $form_id, $entry_id, $from, $receiver, $message, $verify_code );
		}

		return $result;
	}

	public static function change_mobile( string $mobile = '', string $code = '' ): string {

		if ( empty( $mobile ) ) {
			return '';
		}

		if ( empty( $code ) ) {
			$code = self::$settings["code"] ?? '';
		}

		$mobiles = array_map( 'trim', explode( ',', $mobile ) );
		$changed = array_map( fn( $m ) => self::change_mobile_separately( $m, $code ), $mobiles );

		return implode( ',', array_filter( $changed ) );
	}


	public static function change_mobile_separately( string $mobile = '', string $code = '' ): string {
		if ( empty( $mobile ) ) {
			return '';
		}

		if ( empty( $code ) ) {
			$code = self::$settings["code"] ?? '';
		}

		preg_match_all( '/\d+/', $mobile, $matches );
		$phone = ! empty( $matches[0] ) ? implode( '', $matches[0] ) : '';

		if ( str_contains( $mobile, '+' ) || str_contains( $mobile, '%2B' ) ) {
			return '+' . $phone;
		} elseif ( str_starts_with( $phone, '00' ) ) {
			return '+' . substr( $phone, 2 );
		} elseif ( str_starts_with( $phone, '0' ) ) {
			$phone = substr( $phone, 1 );
		}

		$code = str_starts_with( $code, '+' ) ? $code : '+' . $code;

		return $code . $phone;
	}

	/**
	 * Save client number as client_mobile_numbers meta in entry
	 *
	 * @param array $entry
	 * @param array $form
	 *
	 * @return void
	 */
	public static function save_number_to_meta( array $entry, array $form ): void {

		if ( ! is_numeric( $form["id"] ) ) {
			return;
		}

		$numbers = [];

		foreach ( $form['fields'] as $field ) {
			// Check the phone type
			if ( $field->type === 'phone' || stripos( $field->label, 'موبایل' ) !== false ) {
				$field_id = (string) $field->id;

				if ( ! empty( $entry[ $field_id ] ) ) {
					$mobile = self::change_mobile( sanitize_text_field( $entry[ $field_id ] ) );
					if ( ! empty( $mobile ) ) {
						$numbers[] = $mobile;
					}
				}
			}
		}

		$numbers = implode( ',', array_unique( array_filter( $numbers ) ) );

		if ( ! empty( $numbers ) ) {
			gform_update_meta( $entry['id'], 'client_mobile_numbers', $numbers );
		}


	}


	public static function has_credit_card( array $form ): bool {
		return isset( $form["fields"] ) && array_search( "creditcard", array_column( $form["fields"], "type" ) ) !== false;
	}

	/**
	 * @filter gform_confirmation
	 */
	public static function after_submit( $confirmation, array $form, array $entry, bool $ajax ) {
		self::save_number_to_meta( $entry, $form );
		self::send_sms_form( $entry, $form, 'form_submission', 'immediately' );

		return $confirmation;
	}


	/**
	 * @action gform_paypal_fulfillment
	 */
	public static function paypal_fulfillment( $entry, $config, $transaction_id, $amount ) {
		$form = RGFormsModel::get_form_meta( $entry['form_id'] );
		self::send_sms_form( $entry, $form, 'complete_payment', 'fulfillment' );
	}

	public static function send_sms_form( $entry, $form, $status, $function_time ) {

		if ( ! is_numeric( $form["id"] ) ) {
			return;
		}

		$form_id = $form['id'];

		if ( empty( self::$settings["ws"] ) || self::$settings["ws"] == 'no' ) {
			self::add_sms_log( $entry["id"], 'No Gateway found.' );

			return;
		}

		$field_values = GFForms::post( 'gform_field_values' );
		$page_number  = GFForms::post( "gform_source_page_number_{$form_id}" );
		$page_number  = ! is_numeric( $page_number ) ? 1 : $page_number;

		$files = GFFormsModel::set_uploaded_files( $form_id );

		$form_unique_id = GFFormsModel::get_form_unique_id( $form_id );
		$ip             = rgars( $form, 'personalData/preventIP' ) ? '' : GFFormsModel::get_ip();
		$source_url     = GFFormsModel::get_current_page_url();
		$source_url     = esc_url_raw( $source_url );
		$resume_token   = rgpost( 'gform_resume_token' );
		$resume_token   = sanitize_key( $resume_token );
		$resume_token   = GFFormsModel::save_draft_submission( $form, $entry, $field_values, $page_number, $files, $form_unique_id, $ip, $source_url, $resume_token );

		$notifications_to_send  = GFPersian_SMS::get_notifications_to_send( $status, $form, $entry );
		$log_notification_event = empty( $notifications_to_send ) ? 'No SMS notifications to process' : 'Processing SMS notifications';
		GFCommon::log_debug( "GFFormDisplay::process_form(): {$log_notification_event} for form_saved event." );

		foreach ( $notifications_to_send as $notification ) {

			if ( isset( $notification['isActive'] ) && ! $notification['isActive'] ) {
				GFCommon::log_debug( "GFFormDisplay::process_form(): Notification is inactive, not processing notification (#{$notification['id']} - {$notification['name']})." );
				continue;
			}

			$notification['message'] = self::replace_save_variables( $notification['message'], $form, $resume_token );

			if ( empty( $notification['message'] ) ) {
				continue;
			}

			self::send_notification( $notification, $form, $entry );
		}

		self::set_submission_if_null( $form_id, 'saved_for_later', true );
		self::set_submission_if_null( $form_id, 'resume_token', $resume_token );
		GFCommon::log_debug( 'GFFormDisplay::process_form(): Saved incomplete submission.' );
	}


	private static function set_submission_if_null( $form_id, $key, $val ) {
		if ( ! isset( self::$submission[ $form_id ][ $key ] ) ) {
			self::$submission[ $form_id ][ $key ] = $val;
		}
	}

	public static function replace_save_variables( $text, $form, $resume_token, $phone = null ) {
		$resume_token = sanitize_key( $resume_token );
		$form_id      = intval( $form['id'] );
		$page_url     = rgpost( 'current_page_url' ) ? sanitize_url( rawurldecode( rgpost( 'current_page_url' ) ) ) : GFFormsModel::get_current_page_url();

		$resume_url  = apply_filters( 'gform_save_and_continue_resume_url', add_query_arg( [ 'gf_token' => $resume_token ], $page_url ), $form, $resume_token, $phone );
		$resume_url  = esc_url( $resume_url );
		$resume_link = "<a href=\"{$resume_url}\" class='resume_form_link'>{$resume_url}</a>";

		$text = str_replace( '{save_link}', $resume_link, $text );
		$text = str_replace( '{save_token}', $resume_token, $text );
		$text = str_replace( '{save_url}', $resume_url, $text );
		$text = str_replace( '{save_phone}', esc_attr( $phone ), $text );

		$submit_button_text         = esc_html__( 'Send Link', 'gravityforms' );
		$phone_validation_message   = esc_html__( 'Please enter a valid phone number.', 'gravityforms' );
		$phone_input_label          = esc_html__( 'Phone Number', 'gravityforms' );
		$phone_input_label_required = GFFormsModel::get_required_indicator( $form_id );

		preg_match_all( '/\{save_phone_input:(.*?)\}/', $text, $matches, PREG_SET_ORDER );

		if ( is_array( $matches ) && isset( $matches[0] ) && isset( $matches[0][1] ) ) {
			$options_string = $matches[0][1];
			$options        = shortcode_parse_atts( $options_string );
			if ( isset( $options['button_text'] ) ) {
				$submit_button_text = $options['button_text'];
			}
			if ( isset( $options['validation_message'] ) ) {
				$phone_validation_message = $options['validation_message'];
			}
			if ( ! empty( $options['placeholder'] ) ) {
				$phone_input_placeholder = esc_attr( $options['placeholder'] );
			}
			$full_tag = $matches[0][0];
			$text     = str_replace( $full_tag, '{save_phone_input}', $text );
		}

		$action = esc_url( remove_query_arg( 'gf_token' ) );

		$submission_method = rgpost( 'gform_submission_method' );
		$is_iframe_ajax    = $submission_method === GFFormDisplay::SUBMISSION_METHOD_IFRAME;
		$anchor            = GFFormDisplay::get_anchor( $form, $is_iframe_ajax );
		$action            .= $anchor['id'];

		$resume_token = esc_attr( $resume_token );

		// Basic phone validation
		$form_is_invalid = ! is_null( $phone ) && ! preg_match( '/^\+?[0-9\s\-]{7,15}$/', $phone );

		$validation_output = $form_is_invalid
			? sprintf( '<div class="gfield_description gfield_validation_message" id="phone-validation-error" aria-live="assertive">%s</div>', $phone_validation_message )
			: '';

		$nonce_input = '';
		if ( GFCommon::form_requires_login( $form ) ) {
			$nonce_input = wp_nonce_field( 'gform_send_resume_link', '_gform_send_resume_link_nonce', true, false );
		}

		$target = $is_iframe_ajax ? "target='gform_ajax_frame_{$form_id}'" : '';

		$iframe_ajax_fields = '';
		if ( $is_iframe_ajax ) {
			$iframe_ajax_fields = "<input type='hidden' name='gform_ajax' value='" . esc_attr( "form_id={$form_id}&amp;title=1&amp;description=1&amp;tabindex=1" ) . "' />";
			$iframe_ajax_fields .= "<input type='hidden' name='gform_field_values' value='' />";
		}

		$form_submission_inputs = "<input type='hidden' class='gform_hidden' name='gform_submission_method' data-js='gform_submission_method_{$form_id}' value='{$submission_method}' />
							   <input type='hidden' class='gform_hidden' name='is_submit_{$form_id}' value='1' />
							   <input type='hidden' class='gform_hidden' name='gform_submit' value='{$form_id}' />";

		$ajax_submit = $is_iframe_ajax ? "onclick='jQuery(\"#gform_{$form_id}\").trigger(\"submit\",[true]);'" : '';

		$resume_form = "
	<div class='form_saved_message_phoneform'>
		<form action='{$action}' method='POST' id='gform_{$form_id}' data-formid='{$form_id}' {$target}>
			<div class='gform-body gform_body'>
				<div id='gform_fields_{$form_id}' class='gform_fields top_label form_sublabel_below description_below'>
					{$iframe_ajax_fields}
					<div class='gfield gfield--type-tel gfield--width-full field_sublabel_below field_description_below gfield_visibility_visible'>
						<label for='gform_resume_phone' class='gform_resume_phone_label gfield_label gform-field-label'>{$phone_input_label}{$phone_input_label_required}</label>
						<div class='ginput_container ginput_container_text'>
							<input type='tel' name='gform_resume_phone' class='large' id='gform_resume_phone' value='" . esc_attr( $phone ) . "' aria-describedby='phone-validation-error' />
							{$validation_output}
						</div>
					</div>
				</div>
			</div>
			<div class='gform-footer gform_footer top_label'>
				<input type='hidden' name='gform_resume_token' value='{$resume_token}' />
				<input type='hidden' name='gform_send_resume_link' value='{$form_id}' />
				{$form_submission_inputs}
				<input type='submit' name='gform_send_resume_link_button' id='gform_send_resume_link_button_{$form_id}' onclick='gform.submission.handleButtonClick(this);' value='{$submit_button_text}' {$ajax_submit}/>
				{$nonce_input}
			</div>
		</form>
	</div>";

		$always_show_spinner = gf_apply_filters( [ 'gform_always_show_spinner', $form_id ], true );
		if ( ! $is_iframe_ajax && $always_show_spinner ) {
			$default_spinner = GFCommon::get_base_url() . '/images/spinner.svg';
			$spinner_url     = gf_apply_filters( [ 'gform_ajax_spinner_url', $form_id ], $default_spinner, $form );
			$theme_slug      = GFFormDisplay::get_form_theme_slug( $form );
			$is_legacy       = $default_spinner !== $spinner_url || in_array( $theme_slug, [ 'gravity-theme', 'legacy' ] );

			$resume_form .= '<script>gform.initializeOnLoaded( function() {' .
			                "gformInitSpinner( {$form_id}, '{$spinner_url}', " . ( $is_legacy ? 'true' : 'false' ) . " );" .
			                " });</script>";
		}

		return str_replace( '{save_phone_input}', $resume_form, $text );
	}


	public static function send_notification( $notification, $form, $lead, $data = [] ) {

		GFCommon::log_debug( "GFCommon::send_notification(): Starting to process SMS notification (#{$notification['id']} - {$notification['name']})." );

		$notification = gf_apply_filters( [ 'gform_sms_notification', $form['id'] ], $notification, $form, $lead );

		$to_field = '';
		if ( rgar( $notification, 'toType' ) == 'field' ) {
			$to_field = rgar( $notification, 'toField' );
			if ( rgempty( 'toField', $notification ) ) {
				$to_field = rgar( $notification, 'to' );
			}
		}

		$sms_to = rgar( $notification, 'to' );
		//do routing logic if "to" field doesn't have a value (to support legacy notifications that will run routing prior to this method)
		if ( empty( $sms_to ) && rgar( $notification, 'toType' ) == 'routing' && ! empty( $notification['routing'] ) ) {
			$sms_to = [];
			foreach ( $notification['routing'] as $routing ) {
				if ( rgempty( 'phone', $routing ) ) {
					continue;
				}

				GFCommon::log_debug( __METHOD__ . '(): Evaluating Routing - rule => ' . print_r( $routing, 1 ) );

				$source_field   = RGFormsModel::get_field( $form, rgar( $routing, 'fieldId' ) );
				$field_value    = RGFormsModel::get_lead_field_value( $lead, $source_field );
				$is_value_match = RGFormsModel::is_value_match( $field_value, rgar( $routing, 'value', '' ), rgar( $routing, 'operator', 'is' ), $source_field, $routing, $form ) && ! RGFormsModel::is_field_hidden( $form, $source_field, [], $lead );

				if ( $is_value_match ) {
					$sms_to[] = $routing['phone'];
				}

				GFCommon::log_debug( __METHOD__ . '(): Evaluating Routing - field value => ' . print_r( $field_value, 1 ) );
				$is_value_match = $is_value_match ? 'Yes' : 'No';
				GFCommon::log_debug( __METHOD__ . '(): Evaluating Routing - is value match? ' . $is_value_match );
			}

			$sms_to = join( ',', $sms_to );
		} elseif ( ! empty( $to_field ) ) {
			$source_field = RGFormsModel::get_field( $form, $to_field );
			$sms_to       = RGFormsModel::get_lead_field_value( $lead, $source_field );
		}

		// Running through variable replacement
		$to             = GFCommon::remove_extra_commas( GFCommon::replace_variables( $sms_to, $form, $lead, false, false, false, 'text', $data ) );
		$from           = GFCommon::replace_variables( rgar( $notification, 'from' ), $form, $lead, false, false, false, 'text', $data );
		$message_format = rgempty( 'message_format', $notification ) ? 'html' : rgar( $notification, 'message_format' );

		$merge_tag_format = $message_format === 'multipart' ? 'html' : $message_format;

		$message = GFCommon::replace_variables( rgar( $notification, 'message' ), $form, $lead, false, false, ! rgar( $notification, 'disableAutoformat' ), $merge_tag_format, $data );

		if ( apply_filters( 'gform_enable_shortcode_notification_message', true, $form, $lead ) ) {
			$message = do_shortcode( $message );
		}


		if ( $message_format === 'multipart' ) {

			// Creating alternate text message.
			$text_message = GFCommon::replace_variables( rgar( $notification, 'message' ), $form, $lead, false, false, ! rgar( $notification, 'disableAutoformat' ), 'text', $data );

			if ( apply_filters( 'gform_enable_shortcode_notification_message', true, $form, $lead ) ) {
				$text_message = do_shortcode( $text_message );
			}

			// Formatting text message. Removes all tags.
			$text_message = self::format_text_message( $text_message );

			// Sends text and html messages to send_email()
			$message = [
				'html' => $message,
				'text' => $text_message,
			];
		}

		if ( empty( $to ) ) {
			GFPersian_SMS_Entry::add_note( $lead, sprintf( "ارسال پیامک با خطا مواجه شد. شماره: %s | شماره فرستنده: %s | دلیل: عدم وجود شماره مقصد. | متن پیام: %s.", $to, $from, $message ) );

			return;
		}

		if ( GFPersian_SMS_DB::check_sms_sent( intval( $lead['id'] ), intval( $form['id'] ), $to, $message ) ) {
			return;
		}

		$result = GFPersian_SMS_Gateway::action( self::$settings, 'send', $from, $to, $message );

		if ( $result == 'OK' ) {
			GFPersian_SMS_DB::save_sms_sent( $form['id'], $lead['id'], $from, $to, $message );
			GFPersian_SMS_Entry::add_note( $lead, sprintf( "پیامک با موفقیت ارسال شد. شماره: %s | شماره فرستنده: %s | متن پیام: %s .", $to, $from, $message ) );
			//echo '<div class="updated fade" style="padding:6px;">' . sprintf( "پیامک با موفقیت ارسال شد. شماره: %s . جزئیات را در یادداشت‌ها مشاهده کنید.", $to ) . '</div>';
		} else {
			GFPersian_SMS_Entry::add_note( $lead, sprintf( "ارسال پیامک با خطا مواجه شد. شماره: %s | شماره فرستنده: %s | دلیل: %s | متن پیام: %s.", $to, $from, $result, $message ) );
			//echo '<div class="error fade" style="padding:6px;">' . sprintf( "ارسال پیامک با خطا مواجه شد. شماره: %s - دلیل: %s . جزئیات را در یادداشت‌ها مشاهده کنید.", $to, $result ) . '</div>';
		}
	}

	private static function format_text_message( $message ) {

		// Replacing <h> tags with asterisk.
		$text_message = preg_replace( '|<h(\d)|', '* <h$1', $message );

		// Replacing <br> tags with new line character.
		$text_message = preg_replace( '|<br\s*?/?>|', "\n<br />", $text_message );

		// Removing all HTML tags.
		$text_message = wp_strip_all_tags( $text_message );

		// Removing &nbsp; characters
		$text_message = str_replace( '&nbsp;', ' ', $text_message );

		// Removing multiple white spaces
		$text_message = preg_replace( '|[ \t]+|', ' ', $text_message );

		// Removing multiple line feeds
		$text_message = preg_replace( "|[\r\n]+\s*|", "\n", $text_message );

		return $text_message;
	}

	private static function add_sms_log( $entry_id, $message ) {
		RGFormsModel::add_note( $entry_id, 0, 'پیامک حرفه ای', $message );
	}

	/**
	 * @filter gform_replace_merge_tags
	 */
	public static function tags( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
		$placeholders = [ '{payment_gateway}', '{payment_status}', '{transaction_id}' ];

		$entry_id = rgar( $entry, 'id' );
		$entry    = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			return $text;
		}

		$values = [
			ucfirst( gform_get_meta( $entry['id'], 'payment_gateway' ) ?? $entry['payment_method'] ?? '' ),
			ucfirst( $entry['payment_status'] ?? '' ),
			$entry['transaction_id'] ?? ''
		];

		return str_replace( $placeholders, $values, $text );
	}


}

new GFPersian_SMS_Sender();