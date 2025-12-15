<?php

defined( 'ABSPATH' ) || exit;

class GFPersian_SMS_Verification {

	public function __construct() {

		if ( is_admin() ) {
			add_filter( 'gform_add_field_buttons', [ $this, 'gravity_sms_fields' ], 9998 );
			add_filter( 'gform_field_type_title', [ $this, 'title' ], 10, 2 );
			add_action( 'gform_editor_js_set_default_values', [ $this, 'default_label' ] );
			add_action( 'gform_editor_js', [ $this, 'js' ] );
			add_action( 'gform_field_standard_settings', [ $this, 'standard_settings' ], 10, 2 );
			add_filter( 'gform_tooltips', [ $this, 'tooltips' ] );
		}

		add_filter( 'gform_field_validation', [ $this, 'validation' ], 10, 4 );
		add_filter( 'gform_entry_post_save', [ $this, 'process' ], 10, 2 );
		add_action( 'gform_field_input', [ $this, 'input' ], 10, 5 );
		add_action( 'gform_field_css_class', [ $this, 'classes' ], 10, 3 );
		add_filter( 'gform_field_content', [ $this, 'content' ], 10, 5 );
		add_filter( 'gform_merge_tag_filter', [ $this, 'all_fields' ], 10, 4 );
	}

	public static function gravity_sms_fields( $field_groups ) {

		foreach ( $field_groups as $key => $group ) {

			if ( $group["name"] == "gf_persian_fields" ) {
				$group["fields"][] = [
					"class"     => "button",
					"value"     => 'تایید تلفن',
					"data-type" => "sms_verification",
				];
			}

			$field_groups[ $key ] = $group;
		}

		return $field_groups;
	}

	public static function title( $title, $field_type ) {
		if ( $field_type == 'sms_verification' ) {
			return $title = 'تایید تلفن';
		}

		return $title;
	}

	public static function default_label() { ?>
		case "sms_verification" :
		field.label = 'تایید تلفن';
		break;
		<?php
	}

	public static function classes( $classes, $field, $form ) {
		if ( ! empty( $field["type"] ) && $field["type"] == "sms_verification" ) {
			$classes .= " gfield_contains_required gform_sms_verification";
		}

		return $classes;
	}

	public static function input( $input, $field, $value, $entry_id, $form_id ) {

		if ( $field["type"] == "sms_verification" ) {

			$form = GFAPI::get_form( $form_id );

			$is_entry_detail = GFCommon::is_entry_detail();
			$is_form_editor  = GFCommon::is_form_editor();

			$field_id = $field["id"];
			$form_id  = empty( $form_id ) ? rgget( "id" ) : $form_id;

			$disabled_text = $is_form_editor ? "disabled='disabled'" : '';

			$input_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$field_id" : 'input_' . $form_id . "_$field_id";

			$size         = rgar( $field, "size" );
			$class_suffix = $is_entry_detail ? '_admin' : '';
			$class        = $size . $class_suffix;

			$max_length = '';

			$placeholder_attribute = $field->get_field_placeholder_attribute();
			$required_attribute    = $field->isRequired ? 'aria-required="true"' : '';
			$invalid_attribute     = $field->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
			$html5_attributes      = " {$placeholder_attribute} {$required_attribute} {$invalid_attribute} {$max_length} ";

			$tabindex = GFCommon::get_tabindex();

			if ( ! is_admin() && ( RGFormsModel::get_input_type( $field ) == 'adminonly_hidden' ) ) {
				return '';
			}

			$text_input = '<div class="ginput_container ginput_container_text ginput_container_verfication">';
			$text_input .= '<input name="input_' . $field_id . '" id="' . $input_id . '" type="text" value="' . esc_attr( $value ) . '" class="verify_code ' . esc_attr( $class ) . '" ' . $tabindex . ' ' . $html5_attributes . ' ' . $disabled_text . '/>';

			if ( $is_form_editor ) {
				$input = $text_input;
				$input .= '</div><br/>';
				$input .= '<div class="gf-html-container ginput_container_verfication" id="ginput_container_verfication_' . $field_id . '">';
				$input .= '<span style="line-height: 25px;">';
				$input .= 'با اضافه کردن این فیلد، کاربر ابتدا باید شماره تلفن خود را از طریق پیامک تایید کند تا بتواند وارد مراحل بعدی پر کردن فرم شود. لطفاً توجه داشته باشید که معمولاً هیچ فیلدی به فرم اضافه نخواهد شد. با این حال، این فیلد هر زمان که بخواهید فرم را تکمیل یا ثبت کنید، ظاهر خواهد شد.';
				$input .= '</span>';
				$input .= '</div>';

			} elseif ( $is_entry_detail ) {
				$input = $text_input . '</div>';
			} else {

				$mobile_field_id = rgar( $field, "field_sms_verify_mobile" );
				$mobile_field    = RGFormsModel::get_field( $form, $mobile_field_id );

				$diff_page = ! empty( $mobile_field['pageNumber'] ) && ! empty( $field['pageNumber'] ) && $mobile_field['pageNumber'] != $field['pageNumber'] ? true : false;

				if ( $diff_page && apply_filters( 'sms_verify_self_validation', true ) ) {
					$result = self::validation( [ 'action' => 'self' ], $value, $form, $field );
				}

				if ( ! $diff_page && apply_filters( 'gform_button_verify', true ) && empty( $field['conditionalLogic'] ) ) {
					$max_page_num = GFFormDisplay::get_max_page_number( $form );
					if ( ! empty( $field['pageNumber'] ) && $field['pageNumber'] == $max_page_num || ! empty( $field['pageNumber'] ) ) {
						add_filter( 'gform_submit_button', [ __CLASS__, 'submit_button' ], 10, 2 );
					} elseif ( $max_page_num > 1 ) {
						add_filter( 'gform_next_button', [ __CLASS__, 'next_button' ], 10, 2 );
					}
				}


				if ( apply_filters( 'sms_verify_display_none', true ) ) {
					return '<style type="text/css">#field_' . $form_id . '_' . $field_id . '{display:none !important;}</style>';
				} else {

					$input = '';

					if ( apply_filters( 'sms_verify_field', false ) || ( $diff_page && apply_filters( 'sms_verify_field', false ) ) ) {
						$input .= $text_input;
						if ( apply_filters( 'sms_verify_resend', false ) ) {
							$input .= '<input id="gform_resend_button" class="gform_button button" name="resend_verify_sms" type="submit" value="ارسال مجدد">';
						}
						$input .= '</div>';
					}

					if ( ! empty( $result["message_"] ) ) {
						$input .= '<div class="ginput_container ginput_container_text ginput_container_verfication ginput_container_verfication_"><p>';
						$input .= $result["message_"];
						$input .= '</p></div>';
					}
				}
			}
		}

		return $input;
	}


	public static function validation( $result, $value, $form, $field ) {
		global $wpdb;

		if ( $field["type"] == "sms_verification" ) {

			$verify_table = GFPersian_SMS_DB::$verification_table;
			$form_id      = $form['id'];

			$mobile_field_id = rgar( $field, "field_sms_verify_mobile" );
			$mobile_field    = RGFormsModel::get_field( $form, $mobile_field_id );
			$mobile_value    = self::get_mobile( $field, false );

			if ( isset( $mobile_field->noDuplicates ) && $mobile_field->noDuplicates && RGFormsModel::is_duplicate( $form_id, $mobile_field, $mobile_value ) ) {
				return $result;
			}

			$show_input = true;

			$mobile = self::get_mobile( $field );

			if ( empty( $mobile ) || strlen( $mobile ) < 3 ) {
				$result["is_valid"] = false;
				$show_input         = false;
				$result["message"]  = 'لطفاً شماره موبایل خود را برای اهداف تایید در فیلد اختصاص داده شده به شماره موبایل وارد کنید.';
			} else {

				$white_list = self::white_list( $field );

				if ( ! in_array( $mobile, $white_list ) ) {

					$get_result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$verify_table} WHERE mobile = %s AND form_id = %s AND entry_id = %s ORDER BY id DESC LIMIT 1", $mobile, $form_id, 0 ) );

					if ( ! empty( $get_result ) && is_object( $get_result ) ) {
						$ID       = $get_result->id;
						$code     = $get_result->code;
						$status   = $get_result->status;
						$try_num  = $get_result->try_num;
						$sent_num = $get_result->sent_num;
					} else {
						$ID       = '';
						$code     = '';
						$status   = '';
						$try_num  = '';
						$sent_num = '';
					}
					$try_num  = ( ! empty( $try_num ) && $try_num != 0 ) ? $try_num : 0;
					$sent_num = ( ! empty( $sent_num ) && $sent_num != 0 ) ? $sent_num : 0;

					$new_try_num = ( isset( $result["action"] ) && $result["action"] == 'self' ) ? $try_num : $try_num + 1;

					if ( empty( $code ) || ! $code ) {
						$type = rgar( $field, 'sms_verify_code_type_radio' );
						if ( $type == 'manual' ) {
							$delimator   = ',';
							$manual      = explode( $delimator, rgar( $field, 'sms_verify_code_type_manual' ) );
							$random_keys = array_rand( $manual, 1 );
							$code        = isset( $manual[ $random_keys[0] ] ) ? $manual[ $random_keys[0] ] : ( isset( $manual[ $random_keys ] ) ? $manual[ $random_keys ] : rand( 10000, 99999 ) );
						} else {
							$code = self::rand_mask( rgar( $field, 'sms_verify_code_type_rand' ) );
						}
					}

					$allowed_try = rgar( $field, 'sms_verify_try_num', 3 );
					$allowed_try = $allowed_try ? ( $allowed_try - 1 ) : 10;

					if ( $try_num <= $allowed_try && ! rgempty( 'input_' . $field["id"] ) && ! empty( $code ) && rgpost( 'input_' . str_replace( '.', '_', $field["id"] ) ) == $code ) {
						if ( ! empty( $ID ) && $ID != 0 ) {
							GFPersian_SMS_DB::update_verify( $ID, $new_try_num, $sent_num, 0, 1 );
						} else {
							GFPersian_SMS_DB::insert_verify( $form_id, 0, $mobile, $code, 1, $new_try_num, $sent_num );
						}
					} elseif ( ( $status != 1 && $status != '1' ) || empty( $status ) || $status == 0 ) {

						$result["is_valid"] = false;

						if ( $try_num < $allowed_try ) {

							$message = rgar( $field, 'sms_verify_code_msg_body' );
							$message = strpos( $message, '%code%' ) === false ? $message . '%code%' : $message;
							$message = $message ? $message : $code;
							$message = str_replace( '%code%', $code, $message );
							//$message = GFCommon::replace_variables($message, $form, $entry, false, true, false);

							$result["message"] = 'کد دریافتی از طریق پیامک را در فیلد بالا وارد کنید تا شماره موبایل خود را تایید کنید.';

							$allowed_send = rgar( $field, 'sms_verify_sent_num', 3 );
							$allowed_send = $allowed_send ? $allowed_send : 0;

							if ( $sent_num < $allowed_send ) {
								add_filter( 'sms_verify_resend', '__return_true', 99 );
							}

							if ( ! empty( $ID ) && $ID != 0 ) {

								if ( ! rgempty( 'resend_verify_sms' ) ) {

									$result["message"] = 'ارسال پیام با خطا مواجه شد.';

									if ( $sent_num <= $allowed_send ) {

										if ( GFPersian_SMS_Sender::send( $mobile, $message, $from = '', $form_id, '', $code ) == 'OK' ) {
											$sent_num = $sent_num + 1;

											GFPersian_SMS_DB::update_verify( $ID, $try_num, $sent_num, 0, 0 );

											$result["message"] = 'کد فعال‌سازی دوباره از طریق پیامک ارسال شد.';
										}
									}
								} elseif ( ! rgempty( 'input_' . $field["id"] ) ) {

									GFPersian_SMS_DB::update_verify( $ID, $new_try_num, $sent_num, 0, 0 );

									$result["message"] = 'کد وارد شده اشتباه است.';
								}

							} else {

								if ( GFPersian_SMS_Sender::send( $mobile, $message, $from = '', $form_id, '', $code ) ) {
									$sent_num = $sent_num + 1;
									GFPersian_SMS_DB::insert_verify( $form_id, 0, $mobile, $code, 0, $try_num, $sent_num );
								} else {
									$result["message"] = 'ارسال پیام با خطا مواجه شد.';
								}
							}

						} else {

							if ( ! empty( $ID ) && $ID != 0 ) {
								GFPersian_SMS_DB::update_verify( $ID, $new_try_num, $sent_num, 0, 0 );
							}
							$show_input        = false;
							$result["message"] = 'شما تعداد دفعات مجاز برای تایید شماره موبایل خود در این فرم را به پایان رسانده‌اید.';
						}

					}
				}
			}

			if ( isset( $result["is_valid"] ) && $result["is_valid"] != true ) {

				add_filter( 'gform_validation_message', [ __CLASS__, 'change_message' ], 10, 2 );
				add_filter( 'sms_verify_display_none', '__return_false', 99 );


				if ( $show_input == true ) {
					add_filter( 'sms_verify_field', '__return_true', 99 );
				}

				if ( isset( $result["action"] ) && $result["action"] == 'self' ) {
					$result["message_"] = ! empty( $result["message"] ) ? $result["message"] : '';
				} else {
					add_filter( 'sms_verify_self_validation', '__return_false', 99 );
				}
			} else {
				add_filter( 'gform_button_verify', '__return_false', 99 );
			}

		}

		return $result;
	}


	public static function process( $entry, $form ) {
		global $wpdb;
		
		$sms_verification = GFCommon::get_fields_by_type( $form, [ 'sms_verification' ] );

		foreach ( (array) $sms_verification as $field ) {

			$verify_table = GFPersian_SMS_DB::$verification_table;

			$field = (array) $field;

			$mobile = self::get_mobile( $field );

			$get_result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$verify_table} WHERE mobile = %s AND form_id = %s AND entry_id = %s ORDER BY id DESC LIMIT 1", $mobile, $form['id'], 0 ) );

			if ( ! empty( $get_result ) && is_object( $get_result ) ) {

				$ID     = ! empty( $get_result->id ) ? $get_result->id : '';
				$status = ! empty( $get_result->status ) ? $get_result->status : 0;

				if ( ! empty( $ID ) && $ID != 0 && ! empty( $status ) && $status != 0 ) {

					$verify_code = $entry[ $field['id'] ] = $get_result->code;

					GFPersian_SMS_DB::update_entry_verify_sent( $form['id'], $entry['id'], $verify_code );

					$try_num  = $get_result->try_num;
					$sent_num = $get_result->sent_num;
					GFPersian_SMS_DB::update_verify( $ID, $try_num, $sent_num, $entry['id'], 1 );

					GFAPI::update_entry_field( $entry['id'], $field['id'], $verify_code );
				}
			}
		}

		return $entry;
	}

	public static function content( $content, $field, $value, $entry_id, $form_id ) {
		/*
		if ( $field["type"] == "sms_verification" ) {
			return $content;
		}
		*/
		return $content;
	}

	public static function js() {
		$settings = GFPersian_SMS::get_options();
		?>
		<script type='text/javascript'>
            jQuery(document).ready(function ($) {
                fieldSettings["sms_verification"] = ".label_setting, .placeholder_setting, .label_placement_setting, .conditional_logic_field_setting, .admin_label_setting, .size_setting, .default_value_setting, .css_class_setting, .sms_verification_setting, .field_sms_verify_mobile, .sms_country_code, .sms_verify_code_type_radio";

                function gf_sms_verify_populate_select() {
                    var options = ["<option value=''></option>"];
                    $.each(window.form.fields, function (i, field) {
                        if (field.inputs) {
                            $.each(field.inputs, function (j, input) {
                                options.push(
                                    "<option value='" + input.id + "'>" +
                                    field.label + " (" + input.label + ") (ID: " + input.id + ")</option>"
                                );
                            });
                        } else {
                            options.push(
                                "<option value='" + field.id + "'>" +
                                field.label + " (ID: " + field.id + ")</option>"
                            );
                        }
                    });
                    $("select[id^=field_sms_verify_]").html(options.join(""));
                }

                $(document)
                    .on("gform_field_deleted gform_field_added", gf_sms_verify_populate_select);

                gf_sms_verify_populate_select();

                $(document).on("gform_load_field_settings", function (event, field, form) {
                    // Code Type Radio
                    sms_verify_code_type_radio_manual_el = $("#sms_verify_code_type_radio_manual");
                    sms_verify_code_type_radio_rand_el = $("#sms_verify_code_type_radio_rand");
                    sms_verify_code_type_rand_div_el = $("#sms_verify_code_type_rand_div");
                    sms_verify_code_type_manual_div_el = $("#sms_verify_code_type_manual_div");

                    if (field.sms_verify_code_type_radio === 'manual') {
                        sms_verify_code_type_radio_manual_el.prop("checked", true);
                        sms_verify_code_type_rand_div_el.hide("slow");
                        sms_verify_code_type_manual_div_el.show("slow");
                    } else {
                        sms_verify_code_type_radio_rand_el.prop("checked", true);
                        sms_verify_code_type_rand_div_el.show("slow");
                        sms_verify_code_type_manual_div_el.hide("slow");
                    }

                    $('input[name="sms_verify_code_type_radio"]').off("click").on("click", function () {
                        if ($(this).val() === 'manual') {
                            sms_verify_code_type_rand_div_el.hide("slow");
                            sms_verify_code_type_manual_div_el.show("slow");
                        } else {
                            sms_verify_code_type_rand_div_el.show("slow");
                            sms_verify_code_type_manual_div_el.hide("slow");
                        }
                    });

                    // Country Code Radio
                    sms_verify_country_code_radio_dynamic_el = $("#sms_verify_country_code_radio_dynamic");
                    sms_verify_country_code_radio_static_el = $("#sms_verify_country_code_radio_static");
                    sms_verify_country_code_static_div_el = $("#sms_verify_country_code_static_div");
                    field_sms_verify_country_code_dynamic_div_el = $("#field_sms_verify_country_code_dynamic_div");

                    if (field.sms_verify_country_code_radio === 'dynamic') {
                        sms_verify_country_code_radio_dynamic_el.prop("checked", true);
                        sms_verify_country_code_static_div_el.hide("slow");
                        field_sms_verify_country_code_dynamic_div_el.show("slow");
                    } else {
                        sms_verify_country_code_radio_static_el.prop("checked", true);
                        sms_verify_country_code_static_div_el.show("slow");
                        field_sms_verify_country_code_dynamic_div_el.hide("slow");
                    }

                    $('input[name="sms_verify_country_code_radio"]').off("click").on("click", function () {
                        if ($(this).val() === 'dynamic') {
                            sms_verify_country_code_static_div_el.hide("slow");
                            field_sms_verify_country_code_dynamic_div_el.show("slow");
                        } else {
                            sms_verify_country_code_static_div_el.show("slow");
                            field_sms_verify_country_code_dynamic_div_el.hide("slow");
                        }
                    });

                    // Set values
                    field_sms_verify_mobile_el = $("#field_sms_verify_mobile");
                    sms_verify_try_num_el = $("#sms_verify_try_num");
                    sms_verify_sent_num_el = $("#sms_verify_sent_num");
                    sms_verify_code_type_rand_el = $("#sms_verify_code_type_rand");
                    sms_verify_code_type_manual_el = $("#sms_verify_code_type_manual");
                    sms_verify_country_code_static_el = $('#sms_verify_country_code_static');
                    field_sms_verify_country_code_dynamic_el = $("#field_sms_verify_country_code_dynamic");
                    sms_verify_code_msg_body_el = $("#sms_verify_code_msg_body");
                    sms_verify_code_white_list_el = $("#sms_verify_code_white_list");
                    sms_verify_code_all_fields_el = $("#sms_verify_code_all_fields");

                    field_sms_verify_mobile_el.val(field["field_sms_verify_mobile"]);
                    sms_verify_try_num_el.val(field["sms_verify_try_num"]);
                    sms_verify_sent_num_el.val(field["sms_verify_sent_num"]);
                    sms_verify_code_type_rand_el.val(field["sms_verify_code_type_rand"]);
                    sms_verify_code_type_manual_el.val(field["sms_verify_code_type_manual"]);
                    sms_verify_country_code_static_el.val(
                        typeof field.sms_verify_country_code_static === "undefined"
                            ? <?php echo ! empty( $settings ) && ! empty( $settings["code"] ) ? esc_js( $settings["code"] ) : "''"; ?>
                            : field.sms_verify_country_code_static
                    );
                    field_sms_verify_country_code_dynamic_el.val(field["field_sms_verify_country_code_dynamic"]);
                    sms_verify_code_msg_body_el.val(field["sms_verify_code_msg_body"]);
                    sms_verify_code_white_list_el.val(field["sms_verify_code_white_list"]);
                    sms_verify_code_all_fields_el.prop("checked", field["sms_verify_code_all_fields"] === true);

                    // Set dynamic fields
                    var fields = [<?php foreach ( self::get_this_fields() as $key ) {
						echo "'" . esc_js( $key ) . "',";
					} ?>];
                    $.each(fields, function (i, fname) {
                        $("#field_sms_verify_" + fname).val(field["field_sms_verify_" + fname]);
                    });
                });
            });
		</script>
		<?php
	}


	public static function tooltips( $tooltips ) {

		$tooltips['form_gravity_sms_fields']        = '<h6>پیامک گرویتی</h6>فیلدهای';
		$tooltips['sms_verify_code_type_select']    = 'شما می‌توانید تعیین کنید که کدهای فعال‌سازی خود را چگونه می‌خواهید در نظر گرفته شوند. توجه داشته باشید که در نوع دستی، هر کد ممکن است به چندین نفر ارسال شود.';
		$tooltips["sms_verify_mobile"]              = '<h6>فیلد موبایل</h6>فیلد شماره موبایل را برای تایید انتخاب کنید.';
		$tooltips["sms_verify_code_msg_body"]       = '<h6>متن پیامک</h6>متن پیامک حاوی کد فعال‌سازی را وارد کنید. همچنین برای کد فعال‌سازی، از کد کوتاه داده شده استفاده کنید.';
		$tooltips["sms_verify_try_num"]             = 'تعداد دفعاتی را که یک شماره مجاز است در این فرم کد اشتباه وارد کند، تعیین کنید.';
		$tooltips["sms_verify_sent_num"]            = 'تعیین کنید که یک شماره چند بار مجاز است درخواست کد فعالسازی را در این فرم بدهد.';
		$tooltips["sms_verify_all_fields"]          = 'با فعال‌سازی این بخش، محتوای این فیلد از تگ "all_fields" پنهان خواهد شد.';
		$tooltips["sms_verify_country_code_select"] = '<h6>کد کشور</h6>شما می‌توانید کد کشور پیش‌فرض را تغییر دهید، اما اگر شماره موبایل وارد شده به فرمت بین‌المللی باشد، این کد کشور تاثیری نخواهد داشت.';
		$tooltips["sms_verify_code_white_list"]     = '<h6>فهرست سفید</h6>شماره‌هایی را وارد کنید که نیازی به تأیید ندارند.';

		return $tooltips;
	}


	public static function standard_settings( $position, $form_id ) {

		if ( $position == 50 ) { ?>

			<li class="sms_verification_setting field_setting">

				<div class="field_sms_verify_mobile">
					<br/>
					<label for="field_sms_verify_mobile">
						زمینه شماره موبایل
						<?php gform_tooltip( 'sms_verify_mobile' ) ?>
					</label>
					<select id="field_sms_verify_mobile"
					        onchange="SetFieldProperty('field_sms_verify_mobile', this.value);"></select>
				</div>

				<div class="sms_country_code">
					<br/>
					<label>
						کد کشور
						<?php gform_tooltip( "sms_verify_country_code_select" ); ?>
					</label>
					<div>
						<input type="radio" name="sms_verify_country_code_radio"
						       id="sms_verify_country_code_radio_static" size="10" value="static"
						       onclick="SetFieldProperty('sms_verify_country_code_radio', this.value);"/>
						<label for="sms_verify_country_code_radio_static" class="inline">
							ایستا
						</label>

						<input type="radio" name="sms_verify_country_code_radio"
						       id="sms_verify_country_code_radio_dynamic" size="10" value="dynamic"
						       onclick="SetFieldProperty('sms_verify_country_code_radio', this.value);"/>
						<label for="sms_verify_country_code_radio_dynamic" class="inline">
							پویا
						</label>
					</div>

					<div id="sms_verify_country_code_static_div">
						<input id="sms_verify_country_code_static" name="sms_verify_country_code_static" type="text"
						       size="35" style="direction:ltr !important;text-align:left;"
						       onkeyup="SetFieldProperty('sms_verify_country_code_static', this.value);">
					</div>

					<div id="field_sms_verify_country_code_dynamic_div">
						<select id="field_sms_verify_country_code_dynamic"
						        onchange="SetFieldProperty('field_sms_verify_country_code_dynamic', this.value);"></select>
					</div>
				</div>

				<div class="sms_verify_type_div">
					<br/>
					<label>
						چطور کدهای تأیید را وارد کنیم؟
						<?php gform_tooltip( "sms_verify_code_type_select" ); ?>
					</label>
					<div>
						<input type="radio" name="sms_verify_code_type_radio" id="sms_verify_code_type_radio_rand"
						       size="10" value="rand"
						       onclick="SetFieldProperty('sms_verify_code_type_radio', this.value);"/>
						<label for="sms_verify_code_type_radio_rand" class="inline">
							تصادفی
						</label>

						<input type="radio" name="sms_verify_code_type_radio" id="sms_verify_code_type_radio_manual"
						       size="10" value="manual"
						       onclick="SetFieldProperty('sms_verify_code_type_radio', this.value);"/>
						<label for="sms_verify_code_type_radio_manual" class="inline">
							دستی
						</label>
					</div>

					<div id="sms_verify_code_type_rand_div">
						<input id="sms_verify_code_type_rand" name="sms_verify_code_type_rand" type="text" size="35"
						       style="direction:ltr !important;text-align:left;"
						       onkeyup="SetFieldProperty('sms_verify_code_type_rand', this.value);">
						<p class="mask_text_description_" style="margin: 5px 0px 0px;">
							<?php esc_html_e( 'Enter a custom mask', 'gravityforms' ) ?>.
							<a onclick="tb_show('<?php echo esc_attr__( 'Custom Mask Instructions', 'gravityforms' ) ?>', '#TB_inline?width=350&inlineId=custom_mask_instructions', '');"
							   href="javascript:void(0);"><?php esc_html_e( 'Help', 'gravityforms' ) ?></a>
						</p>
					</div>

					<div id="sms_verify_code_type_manual_div">
                        <textarea id="sms_verify_code_type_manual"
                                  style="text-align:left !important; direction:ltr !important;"
                                  class="fieldwidth-1 fieldheight-1"
                                  onkeyup="SetFieldProperty('sms_verify_code_type_manual', this.value);"></textarea>
						<span class="description">لطفاً کدها را با ویرگول جدا کنید</span>
					</div>
				</div>

				<div id="sms_verify_code_msg_body_div">
					<br/>
					<label for="sms_verify_code_msg_body">
						متن پیامک
						<?php gform_tooltip( "sms_verify_code_msg_body" ); ?>
					</label>
					<textarea id="sms_verify_code_msg_body" class="fieldwidth-1"
					          onkeyup="SetFieldProperty('sms_verify_code_msg_body', this.value);"></textarea>
					<span class="description">کد تایید = <code>%code%</code></span>
				</div>

				<div class="sms_verify_try_num_div">
					<br/>
					<label for="sms_verify_try_num">
						حداکثر تعداد تلاش‌های مجاز
						<?php gform_tooltip( "sms_verify_try_num" ); ?>
					</label>
					<input type="text" size="35" id="sms_verify_try_num" value="3"
					       onkeyup="SetFieldProperty('sms_verify_try_num', this.value || 3);"/>
				</div>

				<div class="sms_verify_sent_num_div">
					<br/>
					<label for="sms_verify_sent_num">
						حداکثر تعداد ارسال مجدد کد
						<?php gform_tooltip( "sms_verify_sent_num" ); ?>
					</label>
					<input type="text" size="35" id="sms_verify_sent_num" value="3"
					       onkeyup="SetFieldProperty('sms_verify_sent_num', this.value || 3);"/>
				</div>

				<div class="sms_verify_code_all_fields_div">
					<br/>
					<input type="checkbox" id="sms_verify_code_all_fields"
					       onclick="SetFieldProperty('sms_verify_code_all_fields', this.checked);"/>
					<label for="sms_verify_code_all_fields" class="inline">
						پنهان کردن از تگ مرج {all_fields}
						<?php gform_tooltip( "sms_verify_all_fields" ); ?>
					</label>
				</div>

				<div id="sms_verify_code_white_list_div">
					<br/>
					<label for="sms_verify_code_white_list">
						شماره های موبایل مستثنی شده
						<?php gform_tooltip( "sms_verify_code_white_list" ); ?>
					</label>
					<textarea id="sms_verify_code_white_list" style="text-align:left;direction:ltr !important;"
					          class="fieldwidth-1"
					          onkeyup="SetFieldProperty('sms_verify_code_white_list', this.value);"
					></textarea>
					<span class="description">شماره ها را با ویرگول جدا کنید</span>
				</div>

			</li>
			<?php
		}
	}

	public static function get_this_fields() {
		return [ 'mobile', 'country_code_dynamic' ];
	}

	public static function rand_str( $type = 2 ) {
		$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$numbers  = $type == 1 ? '0123456789' : '';
		$rand     = str_split( str_shuffle( $alphabet . $numbers ) );

		return $rand[ rand( 0, count( $rand ) - 1 ) ];
	}

	public static function rand_mask( $mask ) {

		if ( empty( $mask ) ) {
			return rand( 10000, 99999 );
		}
		$all_str = str_split( $mask );
		$code    = '';
		foreach ( (array) $all_str as $str ) {
			if ( $str == '*' ) {
				$code .= self::rand_str( 1 );
			} elseif ( $str == 'a' ) {
				$code .= self::rand_str( 2 );
			} elseif ( $str == '9' ) {
				$code .= rand( 0, 9 );
			} else {
				$code .= $str;
			}
		}

		return $code;
	}

	public static function country_code( $field ) {
		$field     = (array) $field;
		$code_type = rgar( $field, "sms_verify_country_code_radio" );
		if ( $code_type == 'dynamic' ) {
			$code = rgar( $field, "field_sms_verify_country_code_dynamic" );
			$code = str_replace( '.', '_', $code );
			$code = "input_{$code}";
			$code = ! rgempty( $code ) ? sanitize_text_field( rgpost( $code ) ) : '';
		} else {
			$code = rgar( $field, "sms_verify_country_code_static" );
		}

		return $code;
	}

	public static function get_mobile( $field, $change = true ) {
		$field  = (array) $field;
		$mobile = rgar( $field, "field_sms_verify_mobile" );
		$mobile = str_replace( '.', '_', $mobile );
		$mobile = "input_{$mobile}";
		$mobile = ! rgempty( $mobile ) ? sanitize_text_field( rgpost( $mobile ) ) : '';
		if ( $change && ! empty( $mobile ) ) {
			$mobile = GFPersian_SMS_Sender::change_mobile_separately( $mobile, self::country_code( $field ) );
		}

		return $mobile;
	}

	public static function white_list( $field ) {
		$field      = (array) $field;
		$numbers    = rgar( $field, "sms_verify_code_white_list" );
		$white_list = GFPersian_SMS_Sender::change_mobile( $numbers, self::country_code( $field ) );

		return ! empty( $white_list ) ? explode( ',', $white_list ) : [];
	}

	public static function submit_button( $button, $form ) {
		unset( $form['button']['text'] );
		$text = apply_filters( 'sms_verification_button', 'تایید شماره تلفن', $button, $form );
		if ( is_callable( [ 'GFFormDisplay', 'get_form_button' ] ) ) {
			return GFFormDisplay::get_form_button( $form['id'], "gform_submit_button_{$form['id']}", $form['button'], $text, 'gform_button', $text, 0 );
		} else {
			return self::get_form_button( $form['id'], "gform_submit_button_{$form['id']}", $form['button'], $text, 'gform_button', $text, 0 );
		}
	}

	public static function next_button( $button, $form ) {
		unset( $form['button']['text'] );
		$text  = apply_filters( 'sms_verification_button', 'تایید شماره تلفن', $button, $form );
		$field = GFCommon::get_fields_by_type( $form, [ 'page' ] );
		if ( is_callable( [ 'GFFormDisplay', 'get_form_button' ] ) ) {
			return GFFormDisplay::get_form_button( $form['id'], "gform_next_button_{$form['id']}_{$field->id}", $field->nextButton, $text, 'gform_next_button', $text, $field->pageNumber );
		} else {
			return self::get_form_button( $form['id'], "gform_next_button_{$form['id']}_{$field->id}", $field->nextButton, $text, 'gform_next_button', $text, $field->pageNumber );
		}
	}

	public static function change_message( $message, $form ) {
		return "<div class='validation_error'>برای ادامه، باید شماره موبایل خود را تأیید کنید.</div>";
	}

	public static function all_fields( $value, $merge_tag, $modifier, $field ) {
		if ( $merge_tag == 'all_fields' && $field->type == 'sms_verification' ) {
			if ( rgar( $field, "sms_verify_code_all_fields" ) ) {
				return false;
			}
		}

		return $value;
	}

	public static function get_form_button( $form_id, $button_input_id, $button, $default_text, $class, $alt, $target_page_number, $onclick = '' ) {

		$tabindex = GFCommon::get_tabindex();

		$input_type = 'submit';

		if ( ! empty( $target_page_number ) ) {
			$onclick    = "onclick='jQuery(\"#gform_target_page_number_{$form_id}\").val(\"{$target_page_number}\"); {$onclick} jQuery(\"#gform_{$form_id}\").trigger(\"submit\",[true]); '";
			$input_type = 'button';
		} else {
			// prevent multiple form submissions when button is pressed multiple times
			if ( GFFormsModel::is_html5_enabled() ) {
				$set_submitting = "if( !jQuery(\"#gform_{$form_id}\")[0].checkValidity || jQuery(\"#gform_{$form_id}\")[0].checkValidity()){window[\"gf_submitting_{$form_id}\"]=true;}";
			} else {
				$set_submitting = "window[\"gf_submitting_{$form_id}\"]=true;";
			}

			$onclick_submit = $button['type'] == 'link' ? "jQuery(\"#gform_{$form_id}\").trigger(\"submit\",[true]);" : '';

			$onclick = "onclick='if(window[\"gf_submitting_{$form_id}\"]){return false;}  {$set_submitting} {$onclick} {$onclick_submit}'";
		}

		if ( rgar( $button, 'type' ) == 'text' || rgar( $button, 'type' ) == 'link' || empty( $button['imageUrl'] ) ) {
			$button_text = ! empty( $button['text'] ) ? $button['text'] : $default_text;
			if ( rgar( $button, 'type' ) == 'link' ) {
				$button_input = "<a href='javascript:void(0);' id='{$button_input_id}_link' class='{$class}' {$tabindex} {$onclick}>{$button_text}</a>";
			} else {
				$class        .= ' button';
				$button_input = "<input type='{$input_type}' id='{$button_input_id}' class='{$class}' value='" . esc_attr( $button_text ) . "' {$tabindex} {$onclick} />";
			}
		} else {
			$imageUrl     = $button['imageUrl'];
			$class        .= ' gform_image_button';
			$button_input = "<input type='image' src='{$imageUrl}' id='{$button_input_id}' class='{$class}' alt='{$alt}' {$tabindex} {$onclick} />";
		}

		return $button_input;
	}

}

new GFPersian_SMS_Verification();