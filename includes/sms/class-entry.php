<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class GFPersian_SMS_Entry {

	public function __construct() {

		if ( ! is_admin() ) {
			return;
		}

		$this->init_hooks();
	}


	/**
	 * Initialize GForms SMS sidebar in form entry
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'gform_entry_info', [ $this, 'client_number_show_edit' ], 10, 2 );
		add_action( 'gform_after_update_entry', [ $this, 'save_client_number_update' ], 10, 2 );
		add_filter( 'gform_entry_detail_meta_boxes', [ $this, 'add_meta_box' ], 8, 3 );
		add_action( 'wp_ajax_nopriv_sms_merge_tage_var_ajax', [ $this, 'sms_merge_tage_var_ajax' ] );
		add_action( 'wp_ajax_sms_merge_tage_var_ajax', [ $this, 'sms_merge_tage_var_ajax' ] );
	}

	/**
	 * Extract phone numbers from entry
	 *
	 * @param int $form_id
	 * @param array $entry
	 *
	 * @return string
	 */
	public static function get_phone_numbers( int $form_id, array $entry ): string {


		$clients = self::get_existing_clients( $entry );

		$clients = array_unique( $clients );
		$clients = str_replace( ',,', ',', implode( ',', $clients ) );

		if ( ! empty( $clients ) ) {
			gform_update_meta( $entry['id'], 'client_mobile_numbers', sanitize_text_field( $clients ) );
		}

		return $clients ?? '';
	}

	/**
	 * Extract existing client phones from form meta
	 *
	 * @param array $entry
	 *
	 * @return array|null
	 */
	private static function get_existing_clients( array $entry ): ?array {
		return gform_get_meta( $entry['id'], 'client_mobile_numbers' ) ? explode( ',', gform_get_meta( $entry['id'], 'client_mobile_numbers' ) ) : null;
	}

	public static function client_number_show_edit( $form_id, $entry ) {

		$client_nums = self::get_phone_numbers( $form_id, $entry );

		if ( rgpost( "save" ) && ( GFForms::post( "screen_mode" ) == "edit" || GFForms::post( "action" ) != "update" ) ) {

			self::render_edit_form( $client_nums );
		} elseif ( $client_nums ) {
			self::render_client_numbers( $client_nums );
		} else {
			echo '<hr/>شماره تلفن: -<hr/>';
		}
		echo '<br/><br/>';
	}

	private static function render_edit_form( $client_nums ) {
		?>
		<label for="gfsms_client_edit">شماره تلفن:</label>
		<input type="text" name="gfsms_client_edit" id="gfsms_client_edit"
		       style="width:100%; text-align:left !important; direction:ltr !important;  padding:3px 5px;"
		       value="<?php echo esc_attr( $client_nums ); ?>" autocomplete="off"/>
		<?php
	}

	private static function render_client_numbers( $client_nums ) {
		echo '<hr/>' . sprintf( 'شماره تلفن: %s', '<br/><br/><div style="text-align:left !important;direction:ltr !important;word-wrap: break-word;">' . esc_html( $client_nums ) . '</div><hr/>' );
	}

	public static function save_client_number_update( $form, $entry_id ) {

		if ( ! rgpost( "gfsms_client_edit" ) ) {
			return;
		}

		check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

		$entry = GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) ) {
			return;
		}

		$client_nums = self::get_phone_numbers( $form["id"], $entry );

		if ( $client_nums != rgpost( "gfsms_client_edit" ) ) {
			self::update_client_numbers( $entry, $client_nums, rgpost( "gfsms_client_edit" ) );
		}
	}

	private static function update_client_numbers( $entry, $old_nums, $new_nums ) {
		global $current_user;

		if ( empty( $old_nums ) ) {
			$old_nums = '-';
		}

		$user_id   = $current_user ? $current_user->ID : 0;
		$user_name = $current_user ? $current_user->display_name : 'پیامک حرفه ای';

		$mobile = sanitize_text_field( $new_nums );
		RGFormsModel::add_note( $entry["id"], $user_id, $user_name, sprintf( "شماره موبایل کاربر از %s به %s تغییر یافت .", $old_nums, $mobile ) );
		gform_update_meta( $entry["id"], "client_mobile_numbers", $mobile );
	}

	public function add_meta_box( $meta_boxes, $entry, $form ) {
		$new_meta_boxes = [];
		foreach ( $meta_boxes as $key => $val ) {
			$new_meta_boxes[ $key ] = $val;
			if ( $key == 'submitdiv' ) {
				$new_meta_boxes['send_sms'] = [
					'title'    => esc_html__( 'ارسال پیامک' ),
					'callback' => [ $this, 'send_sms_sidebar' ],
					'context'  => 'side',
				];
			}
		}

		return $new_meta_boxes;
	}

	public function send_sms_sidebar( $arg_1, $arg_2 ) {
		[ $form, $entry ] = self::get_form_and_entry( $arg_1, $arg_2 );
		$settings = GFPersian_SMS::get_options();
		$is_OK    = ! empty( $settings["ws"] ) && $settings["ws"] != 'none';

		if ( rgpost( "gfsms_send" ) && rgpost( "gf_hannan_sms_sideber" ) && wp_verify_nonce( rgpost( "gf_hannan_sms_sideber" ), "send" ) ) {
			self::process_sms_sending( $form, $entry, $settings, $is_OK );
		}

		self::render_sms_sidebar( $form, $entry, $settings, $is_OK );
	}

	private static function get_form_and_entry( $arg_1, $arg_2 ): array {
		return [ $arg_1['form'], $arg_1['entry'] ];
	}

	private static function process_sms_sending( $form, $entry, $settings, $is_OK ) {
		$from = sanitize_text_field( rgpost( 'gfsms_from' ) );
		self::update_last_sender( $from );

		$to  = sanitize_text_field( rgpost( 'gfsms_client' ) );
		$msg = self::prepare_message( $form, $entry );

		if ( ! $is_OK ) {
			self::add_note( $entry, 'درگاه پیامکی یافت نشد.' );

			return;
		}

		if ( $to ) {
			self::send_sms( $form, $entry, $settings, $from, $to, $msg );
		} else {
			echo '<div class="error fade" style="padding:6px;"> ارسال پیام با خطا مواجه شد زیرا شماره خالی است. </div>';
		}
	}

	private static function update_last_sender( $from ) {
		$from_db = get_option( "gf_sms_last_sender" );
		if ( $from && $from_db != $from ) {
			update_option( "gf_sms_last_sender", $from );
		}
	}

	private static function prepare_message( $form, $entry ) {
		$msg = GFCommon::replace_variables( wp_kses( rgpost( 'gfsms_text' ), [ 'br' => [] ] ), $form, $entry );

		return str_replace( [ "<br>", "<br/>", "<br />" ], [ "", "", "" ], $msg );
	}

	private static function send_sms( $form, $entry, $settings, $from, $to, $msg ) {
		$result = GFPersian_SMS_Gateway::action( $settings, 'send', $from, $to, $msg );
		if ( $result == 'OK' ) {
			GFPersian_SMS_DB::save_sms_sent( $form['id'], $entry['id'], $from, $to, $msg, '' );
			self::add_note( $entry, sprintf( "پیامک با موفقیت ارسال شد. شماره: %s | شماره فرستنده: %s | متن پیام: %s .", $to, $from, $msg ) );
			echo '<div class="updated fade" style="padding:6px;">' . sprintf( "پیامک با موفقیت ارسال شد. شماره: %s . جزئیات را در یادداشت‌ها مشاهده کنید.", esc_html( $to ) ) . '</div>';
		} else {
			self::add_note( $entry, sprintf( "ارسال پیامک با خطا مواجه شد. شماره: %s | شماره فرستنده: %s | دلیل: %s | متن پیام: %s.", $to, $from, $result, $msg ) );
			echo '<div class="error fade" style="padding:6px;">' . sprintf( "ارسال پیامک با خطا مواجه شد. شماره: %s - دلیل: %s . جزئیات را در یادداشت‌ها مشاهده کنید.", esc_html( $to ), esc_html( $result ) ) . '</div>';
		}
	}

	public static function add_note( $entry, $message ) {
		global $current_user;

		$user_id   = $current_user ? $current_user->ID : 0;
		$user_name = $current_user ? $current_user->display_name : 'پیامک حرفه ای';
		RGFormsModel::add_note( $entry["id"], $user_id, $user_name, $message );
	}

	private static function render_sms_sidebar( $form, $entry, $settings, $is_OK ) {
		if ( $is_OK ) {
			self::render_sms_form( $form, $entry, $settings );
		} else {
			self::render_sms_settings_link();
		}
	}

	private static function render_sms_form( $form, $entry, $settings ) {
		wp_nonce_field( "send", "gf_hannan_sms_sideber" );
		?>
		<div id="minor-publishing" style="padding:10px;">
			<label for="gfsms_client">شماره‌های گیرنده:</label>
			<input type="text" name="gfsms_client"
			       style="width:100%; text-align:left; direction:ltr !important;  padding:3px 5px;"
			       id="gfsms_client"
			       value="<?php echo esc_attr( self::get_phone_numbers( $form["id"], $entry ) ); ?>"
			       autocomplete="off"/>
			<br/>
			<div id="sms_sidebar_loading" style="padding:5px;height:10px;text-align:center"></div>
			<label for="gfsms_text"> پیام</label>
			<select style="width:100%" id="gfsms_text_variable_select"
			        onchange="InsertMegeTag_SMS('gfsms_text', 'variable_select', jQuery(this).val());"
			>
				<?php echo self::get_merge_tags_options( RGFormsModel::get_form_meta( $form['id'] ) ); ?>
			</select>
			<textarea id="gfsms_text" class="input-text"
			          style="width: 100%; height: 100px; padding:5px;" name="gfsms_text"></textarea>
		</div>
		<div id="major-publishing-actions">
			<div id="delete-action" style="width:70%">
				<select id="gfsms_from" name="gfsms_from" style="width:100%">
					<option value="">انتخاب شماره ارسال کننده:</option>
					<?php self::render_sender_numbers( $settings ); ?>
				</select>
			</div>
			<div id="publishing-action" style="width:25%">
				<input class="button button-large button-primary" type="submit" name="gfsms_send"
				       value="ارسال">
			</div>
			<div class="clear"></div>
		</div>
		<?php
		self::InsertMegeTag_SMS_JS( empty( $settings["sidebar_ajax"] ) || esc_attr( $settings["sidebar_ajax"] ) != 'No', $form['id'], $entry['id'] );
	}

	private static function render_sender_numbers( $settings ) {
		$sender_num = $settings["from"] ?? '';
		if ( $sender_num == '' || strpos( $settings["from"], ',' ) === false ) {
			if ( $sender_num ) {
				$last_from = get_option( "gf_sms_last_sender" );
				$selected  = $sender_num == $last_from ? "selected='selected'" : "";
				?>
				<option value="<?php echo esc_attr( $sender_num ) ?>" <?php echo esc_attr( $selected ) ?>><?php echo esc_html( $sender_num ) ?></option>
				<?php
			}
		} else {
			foreach ( explode( ',', $settings["from"] ) as $sender_num ) {
				$last_from = get_option( "gf_sms_last_sender" );
				$selected  = $sender_num == $last_from ? "selected='selected'" : "";
				?>
				<option value="<?php echo esc_attr( $sender_num ) ?>" <?php echo esc_attr( $selected ) ?>><?php echo esc_html( $sender_num ) ?></option>
				<?php
			}
		}
	}

	private static function render_sms_settings_link() {
		?>
		<p>بررسی تنظیمات عمومی پیامک</p>
		<a class="button add-new-h2" href="admin.php?page=gf_settings&subview=gf_sms_pro"
		   style="margin:3px 9px;">تنظیمات عمومی پیامک</a>
		<?php
	}

	public static function InsertMegeTag_SMS_JS( $ajax = false, $form_id = 0, $entry_id = 0 ) {
		?>
		<script type="text/javascript">
            function InsertMegeTag_SMS(element_id, ex_id, variable) {
                ex_id = '_' + ex_id;
				<?php if ($ajax) : ?>
                jQuery("#sms_sidebar_loading").html('<img src="<?php echo esc_url( GFCommon::get_base_url() ) ?>/images/spinner.svg" />');
                jQuery.ajax({
                    url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ) ?>",
                    type: "post",
                    data: {
                        action: "sms_merge_tage_var_ajax",
                        security: "<?php echo wp_create_nonce( "sms_sidebar_entry_ajax" ); ?>",
                        form_id: "<?php echo esc_js( $form_id ) ?>",
                        entry_id: "<?php echo esc_js( $entry_id ) ?>",
                        variable: variable,
                    },
                    success: function (response) {
                        jQuery("#sms_sidebar_loading").html('');
                        variable = response;
                        InsertMegeTag_SMS_Value(element_id, variable, ex_id);
                    }
                });
				<?php else : ?>
                InsertMegeTag_SMS_Value(element_id, variable, ex_id);
				<?php endif; ?>
            }

            function InsertMegeTag_SMS_Value(element_id, variable, ex_id) {
                if (typeof (tinyMCE) != "undefined") {
                    if (tinyMCE.get(element_id) != null && tinyMCE.get(element_id).isHidden() != true) {
                        tinyMCE.get(element_id).execCommand('mceInsertContent', false, variable);
                    }
                }

                var messageElement = jQuery("#" + element_id);
                if (document.selection) {
                    messageElement[0].focus();
                    document.selection.createRange().text = variable;
                } else if (messageElement[0].selectionStart) {
                    obj = messageElement[0]
                    obj.value = obj.value.substr(0, obj.selectionStart) + variable + obj.value.substr(obj.selectionEnd, obj.value.length);
                } else {
                    messageElement.val(variable + messageElement.val());
                }
                jQuery('#' + element_id + ex_id)[0].selectedIndex = 0;
                if (callback && window[callback])
                    window[callback].call();
            }
		</script>
		<style type="text/css">
            #send_sms .inside, #send_sms h3 {
                padding: 0px !important;
                margin: 0px !important;
            }
		</style>
		<?php
	}

	public static function sms_merge_tage_var_ajax() {
		check_ajax_referer( 'sms_sidebar_entry_ajax', 'security' );

		$variable = isset( $_POST['variable'] ) ? trim( $_POST['variable'] ) : '';
		$form_id  = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		$entry_id = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;

		if ( ob_get_length() ) {
			ob_clean();
		}

		if ( $form_id && $entry_id ) {
			$form  = RGFormsModel::get_form_meta( $form_id );
			$entry = GFAPI::get_entry( $entry_id );
			if ( is_wp_error( $entry ) ) {
				$entry = false;
			}

			echo esc_html( GFCommon::replace_variables( $variable, $form, $entry, false, true, false ) );
		} else {
			echo esc_html( $variable );
		}
		die();
	}

	public static function get_field_value( $form, $entry, $field_id ) {
		$field = RGFormsModel::get_field( $form, $field_id );
		if ( ! $field instanceof GF_Field ) {
			$field = GF_Fields::create( $field );
		}

		$value = RGFormsModel::get_lead_field_value( $entry, $field );
		if ( is_array( $value ) ) {
			$value = rgar( $value, $field_id );
		}

		return GFCommon::format_variable_value( $value, false, true, 'html', true );
	}


	public static function get_merge_tags_options( $form ) {
		$output = [
			'header' => self::get_merge_tag_option( 'تگ های ادغام', '' ),
			'groups' => [],
		];

		$field_groups = self::categorize_form_fields( $form );

		foreach ( $field_groups as $group_type => $fields ) {
			if ( ! empty( $fields ) ) {
				$output['groups'][ $group_type ] = [
					'label'   => self::get_group_label( $group_type ),
					'options' => self::generate_field_options( $fields ),
				];
			}
		}

		// Add static "Other" options
		$output['groups']['other'] = [
			'label'   => __( "Other", "gravityforms" ),
			'options' => self::get_other_merge_tags( $form ),
		];

		$html = self::merge_tags_build_html_output( $output );

		return apply_filters( 'gravity_sms_pro_merge_tags_list', $html, $form );
	}

	/**
	 * Categorize form fields into groups
	 */
	private static function categorize_form_fields( $form ) {
		$groups = [
			'required' => [],
			'optional' => [],
			'pricing'  => [],
		];

		if ( empty( $form['fields'] ) ) {
			return $groups;
		}

		foreach ( $form['fields'] as $field ) {

			$input_type = RGFormsModel::get_input_type( $field );
			$is_pricing = GFCommon::is_pricing_field( $field['type'] );

			if ( $is_pricing ) {
				$groups['pricing'][] = $field;
			} elseif ( $field['isRequired'] ?? false ) {
				self::handle_required_field( $groups['required'], $field, $input_type );
			} else {
				$groups['optional'][] = $field;
			}
		}

		return $groups;
	}

	/**
	 * Handle special cases for required fields
	 */
	private static function handle_required_field( &$group, $field, $input_type ) {
		if ( $input_type === 'name' && ( $field['nameFormat'] ?? '' ) === 'extended' ) {
			$optional_inputs = [
				GFCommon::get_input( $field, $field['id'] + 0.2 ),
				GFCommon::get_input( $field, $field['id'] + 0.8 ),
			];

			$optional_field           = $field;
			$optional_field['inputs'] = $optional_inputs;
			$group[]                  = $field;
		} else {
			$group[] = $field;
		}
	}

	/**
	 * Generate field options HTML
	 */
	private static function generate_field_options( $fields ) {
		return array_reduce( $fields, function ( $carry, $field ) {
			return $carry . self::get_fields_options( $field );
		}, '' );
	}

	/**
	 * Get translated group labels
	 */
	private static function get_group_label( $group_type ) {
		$labels = [
			'required' => __( "Required form fields", "gravityforms" ),
			'optional' => __( "Optional form fields", "gravityforms" ),
			'pricing'  => __( "Pricing form fields", "gravityforms" ),
		];

		return $labels[ $group_type ] ?? '';
	}

	/**
	 * Generate other merge tags section
	 */
	private static function get_other_merge_tags( $form ) {
		$tags = [
			'{payment_gateway}'       => 'درگاه / روش پرداخت',
			'{payment_status}'        => __( "Payment Status", "gravityforms" ),
			'{transaction_id}'        => __( "Transaction Id", "gravityforms" ),
			'{ip}'                    => __( "IP", "gravityforms" ),
			'{date_mdy}'              => __( "Date (mm/dd/yyyy)", "gravityforms" ),
			'{date_dmy}'              => __( "Date (dd/mm/yyyy)", "gravityforms" ),
			'{embed_post:ID}'         => __( "Embed Post/Page Id", "gravityforms" ),
			'{embed_post:post_title}' => __( "Embed Post/Page Title", "gravityforms" ),
			'{embed_url}'             => __( "Embed URL", "gravityforms" ),
			'{entry_id}'              => __( "Entry Id", "gravityforms" ),
			'{entry_url}'             => __( "Entry URL", "gravityforms" ),
			'{form_id}'               => __( "Form Id", "gravityforms" ),
			'{form_title}'            => __( "Form Title", "gravityforms" ),
			'{user_agent}'            => __( "HTTP User Agent", "gravityforms" ),
			'{user:display_name}'     => __( "User Display Name", "gravityforms" ),
			'{user:user_email}'       => __( "User Email", "gravityforms" ),
			'{user:user_login}'       => __( "User Login", "gravityforms" ),
		];

		if ( GFCommon::has_post_field( $form['fields'] ) ) {
			$tags['{post_id}']       = __( "Post Id", "gravityforms" );
			$tags['{post_edit_url}'] = __( "Post Edit URL", "gravityforms" );
		}

		return array_reduce( array_keys( $tags ), function ( $carry, $tag ) use ( $tags ) {
			return $carry . self::get_merge_tag_option( $tags[ $tag ], $tag );
		}, '' );
	}

	/**
	 * Build final HTML output
	 */
	private static function merge_tags_build_html_output( $data ) {
		$html = $data['header'];

		foreach ( $data['groups'] as $group ) {
			$html .= sprintf(
				'<optgroup label="%s">%s</optgroup>',
				esc_attr( $group['label'] ),
				$group['options']
			);
		}

		return $html;
	}

	/**
	 * Safe option tag generator
	 */
	private static function get_merge_tag_option( $label, $value ) {
		return sprintf(
			'<option value="%s">%s</option>',
			esc_attr( $value ),
			esc_html( $label )
		);
	}

	public static function get_fields_options( $field, $max_label_size = 100 ) {
		$str = "";
		if ( is_array( $field["inputs"] ) ) {
			foreach ( (array) $field["inputs"] as $input ) {
				$str .= "<option value='{" . esc_attr( GFCommon::get_label( $field, $input["id"] ) ) . ":" . $input["id"] . "}'>" . esc_html( GFCommon::truncate_middle( GFCommon::get_label( $field, $input["id"] ), $max_label_size ) ) . "</option>";
			}
		} else {
			$str .= "<option value='{" . esc_html( GFCommon::get_label( $field ) ) . ":" . $field["id"] . "}'>" . esc_html( GFCommon::truncate_middle( GFCommon::get_label( $field ), $max_label_size ) ) . "</option>";
		}

		return $str;
	}
}

new GFPersian_SMS_Entry();