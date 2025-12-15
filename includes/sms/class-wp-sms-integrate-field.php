<?php

defined( 'ABSPATH' ) || exit;

/**
 * WP SMS <https://wordpress.org/plugins/wp-sms/>
 */
class GFPersian_SMS_WPSMS {

	public function __construct() {

		add_filter( 'gform_add_field_buttons', [ __CLASS__, 'button' ], 999 );

		if ( ! defined( 'WP_SMS_VERSION' ) ) {
			add_filter( 'gform_admin_pre_render', [ __CLASS__, 'can_add' ], 10, 4 );

			return;
		}

		if ( is_admin() ) {
			add_filter( 'gform_field_type_title', [ __CLASS__, 'title' ], 10, 2 );
			add_action( 'gform_editor_js_set_default_values', [ __CLASS__, 'default_label' ] );
			add_action( 'gform_editor_js', [ __CLASS__, 'js' ] );
			add_action( 'gform_field_standard_settings', [ __CLASS__, 'standard_settings' ], 10, 2 );
			add_filter( 'gform_field_content', [ __CLASS__, 'content' ], 10, 5 );
			add_filter( 'gform_entries_field_value', [ __CLASS__, 'entries_value' ], 10, 4 );
			add_filter( 'gform_tooltips', [ __CLASS__, 'tooltips' ] );
		}

		add_filter( 'gform_field_validation', [ __CLASS__, 'validation' ], 10, 4 );
		add_filter( 'gform_entry_post_save', [ __CLASS__, 'process' ], 10, 2 );
		add_filter( 'gform_merge_tag_filter', [ __CLASS__, 'merge_tag' ], 10, 4 );
		add_action( 'gform_field_input', [ __CLASS__, 'input' ], 10, 5 );
		add_action( 'gform_field_css_class', [ __CLASS__, 'classes' ], 10, 3 );
	}

	public static function can_add( $form ) {
		echo GFCommon::is_form_editor() ? "
		<script type='text/javascript'>
			gform.addFilter('gform_form_editor_can_field_be_added', function (canFieldBeAdded, type) {
				 if (type == 'sms_subscribtion') {
                    alert('افزونه WP SMS نصب نشده است.');
					return false;
				}
				return canFieldBeAdded;
			});
        </script>" : '';

		return $form;
	}

	public static function button( $field_groups ) {

		foreach ( $field_groups as $key => $group ) {

			if ( $group["name"] == "gf_persian_fields" ) {
				$group["fields"][] = [
					"class"     => "button",
					"value"     => 'پیامک وردپرس',
					"data-type" => "sms_subscribtion",
				];
			}

			$field_groups[ $key ] = $group;
		}

		return $field_groups;
	}


	public static function title( $title, $field_type ) {
		if ( $field_type == 'sms_subscribtion' ) {
			return $title = 'گروه مشترکین';
		} else {
			return $title;
		}
	}

	public static function default_label() { ?>
		case "sms_subscribtion" :
		field.label = 'گروه مشترکین';
		break;
		<?php
	}

	public static function classes( $classes, $field, $form ) {
		if ( ! empty( $field["type"] ) && $field["type"] == "sms_subscribtion" ) {
			$classes .= " gform_sms_subscribtion";
		}

		return $classes;
	}

	public static function tooltips( $tooltips ) {
		$tooltips["wp_sms_subs"]                = "<h6>عملیات عضویت</h6>لطفاً عملیات مورد نظر را انتخاب کنید.";
		$tooltips["field_wp_sms_name"]          = "<h6>فیلد نام</h6>لطفاً فیلدی را که معادل فیلد نام در افزونه WP SMS است انتخاب کنید.";
		$tooltips["field_wp_sms_mobile"]        = "<h6>فیلد موبایل</h6>لطفاً فیلدی را که معادل فیلد موبایل در افزونه WP SMS است انتخاب کنید.";
		$tooltips["wp_sms_group_select"]        = "شما می‌توانید تعیین کنید که کاربران به‌صورت اختیاری گروه مشترکین را انتخاب کنند یا مجبور به عضویت در گروه مشخص شده توسط شما باشند.";
		$tooltips["wp_sms_group_type"]          = "نمایش گروه‌های مشترکین می‌تواند به صورت لیست کشویی یا دکمه‌های رادیویی باشد.";
		$tooltips["wp_sms_group_forced"]        = "<h6>گروه اجباری</h6>اگر می‌خواهید کاربر حتماً در یک گروه مشخص از مشترکین پیامکی عضو شود، گروه مورد نظر را انتخاب کنید.";
		$tooltips["wp_sms_welcome_msg"]         = "<h6>پیام خوشامدگویی</h6>اگر می‌خواهید ارسال پیام خوشامدگویی پس از فعال‌سازی عضویت غیرفعال شود، تیک این گزینه را در تنظیمات WP SMS بزنید.";
		$tooltips["wp_sms_repeat_error"]        = "اگر شماره موبایل وارد شده قبلاً در خبرنامه پیامکی WP SMS ثبت شده باشد، از پر کردن فرم جلوگیری شده و پیام مشخص شده نمایش داده می‌شود.";
		$tooltips["wp_sms_country_code_select"] = "<h6>کد کشور</h6>می‌توانید کد کشور پیش‌فرض را تغییر دهید. اما اگر شماره موبایل وارد شده به فرمت بین‌المللی باشد، این کد کشور نادیده گرفته می‌شود.";

		return $tooltips;
	}


	public static function js() {
		$settings = GFPersian_SMS::get_options();

		?>
		<script type='text/javascript'>
            if (typeof fieldSettings != 'undefined') {
                fieldSettings["sms_subscribtion"] = ".enable_enhanced_ui_setting , .size_setting, .label_placement_setting, .prepopulate_field_setting,.error_message_setting, .conditional_logic_field_setting, .label_setting, .admin_label_setting,.rules_setting, .visibility_setting, .description_setting, .css_class_setting, .wp_sms_subscribtion_setting";
            }

            function wp_sms_pgf_integration(type) {
                if (type == 'unsubscribe') {
                    jQuery("#wp_sms_subs_unsubs").prop("checked", true);
                    jQuery(".field_wp_sms_name").hide("slow");
                    jQuery(".wp_sms_group_select_div").hide("slow");
                    jQuery(".wp_sms_group_type_div").hide("slow");
                    jQuery(".wp_sms_group_forced_div").hide("slow");
                    jQuery(".wp_sms_welcome_msg_div").hide("slow");
                    jQuery(".wp_sms_repeat_error_div").hide("slow");
                    jQuery(".wp_sms_repeat_mgs_div").hide("slow");
                    jQuery(".sms_country_code").hide("slow");
                    jQuery("#ginput_container_unsubscribe_" + field.id).show("slow");
                    jQuery("#ginput_container_sms_radio_" + field.id).hide("slow");
                    jQuery("#ginput_container_sms_select_" + field.id).hide("slow");
                    jQuery("#ginput_container_force_" + field.id).hide("slow");
                } else {
                    jQuery("#wp_sms_subs_subs").prop("checked", true);
                    jQuery("#ginput_container_unsubscribe_" + field.id).hide("slow");
                    jQuery(".field_wp_sms_name").show("slow");
                    jQuery(".wp_sms_group_select_div").show("slow");
                    jQuery(".wp_sms_welcome_msg_div").show("slow");
                    jQuery(".sms_country_code").show("slow");
                    jQuery(".wp_sms_repeat_error_div").show("slow");

                    //#2
                    if (field.wp_sms_group_type == 'select') {
                        jQuery("#wp_sms_group_type_select").prop("checked", true);
                        jQuery("#ginput_container_sms_radio_" + field.id).hide("slow");
                        jQuery("#ginput_container_sms_select_" + field.id).show("slow");
                    } else {
                        jQuery("#wp_sms_group_type_radio").prop("checked", true);
                        jQuery("#ginput_container_sms_select_" + field.id).hide("slow");
                        jQuery("#ginput_container_sms_radio_" + field.id).show("slow");
                    }
                    jQuery('input[name="wp_sms_group_type"]').on("click", function () {
                        if (jQuery('input[name="wp_sms_group_type"]:checked').val() == 'select') {
                            jQuery("#ginput_container_sms_radio_" + field.id).hide("slow");
                            jQuery("#ginput_container_sms_select_" + field.id).show("slow");
                        } else {
                            jQuery("#ginput_container_sms_select_" + field.id).hide("slow");
                            jQuery("#ginput_container_sms_radio_" + field.id).show("slow");
                        }
                    });

                    //#3
                    jQuery('#wp_sms_group_select').val(field.wp_sms_group_select == undefined ? "user" : field.wp_sms_group_select);
                    if (field.wp_sms_group_select != 'force') {
                        jQuery(".wp_sms_group_type_div").show("slow");
                        jQuery(".wp_sms_group_forced_div").hide("slow");
                        jQuery("#ginput_container_force_" + field.id).hide("slow");
                        if (field.wp_sms_group_type == 'select')
                            jQuery("#ginput_container_sms_select_" + field.id).show("slow");
                        else
                            jQuery("#ginput_container_sms_radio_" + field.id).show("slow");
                    } else {
                        jQuery(".wp_sms_group_type_div").hide("slow");
                        jQuery(".wp_sms_group_forced_div").show("slow");
                        jQuery("#ginput_container_force_" + field.id).show("slow");
                        jQuery("#ginput_container_sms_radio_" + field.id).hide("slow");
                        jQuery("#ginput_container_sms_select_" + field.id).hide("slow");
                    }
                    jQuery('#wp_sms_group_select').change(function () {
                        if (jQuery(this).val() == 'user') {
                            jQuery(".wp_sms_group_type_div").show("slow");
                            jQuery(".wp_sms_group_forced_div").hide("slow");
                            jQuery("#ginput_container_force_" + field.id).hide("slow");
                            if (jQuery('input[name="wp_sms_group_type"]:checked').val() == 'select')
                                jQuery("#ginput_container_sms_select_" + field.id).show("slow");
                            else
                                jQuery("#ginput_container_sms_radio_" + field.id).show("slow");
                        } else {
                            jQuery(".wp_sms_group_type_div").hide("slow");
                            jQuery(".wp_sms_group_forced_div").show("slow");
                            jQuery("#ginput_container_force_" + field.id).show("slow");
                            jQuery("#ginput_container_sms_radio_" + field.id).hide("slow");
                            jQuery("#ginput_container_sms_select_" + field.id).hide("slow");
                        }
                    }).change();

                    //#4
                    jQuery('#wp_sms_group_forced').val(field.wp_sms_group_forced == undefined ? "" : field.wp_sms_group_forced);

                    //#5
					<?php if( get_option( 'wp_subscribes_send_sms' )) { ?>
                    jQuery("#wp_sms_welcome_msg").attr("checked", field["wp_sms_welcome_msg"] == true);
					<?php } ?>

                    //#6
                    jQuery("#wp_sms_repeat_error").attr("checked", field["wp_sms_repeat_error"] == true);
                    if (field.wp_sms_repeat_error == true) {
                        jQuery(".wp_sms_repeat_mgs_div").show("slow");
                    } else {
                        jQuery(".wp_sms_repeat_mgs_div").hide("slow");
                    }
                    jQuery("#wp_sms_repeat_error").change(function () {
                        if (jQuery('#wp_sms_repeat_error:checked').val())
                            jQuery(".wp_sms_repeat_mgs_div").show("slow");
                        else
                            jQuery(".wp_sms_repeat_mgs_div").hide("slow");
                    });

                    //#7
                    jQuery("#wp_sms_repeat_mgs").val(field["wp_sms_repeat_mgs"]);

                }

                //#8
                if (field.wp_sms_country_code_radio == 'dynamic') {
                    jQuery("#wp_sms_country_code_radio_dynamic").prop("checked", true);
                    jQuery("#wp_sms_country_code_static_div").hide("slow");
                    jQuery("#field_wp_sms_country_code_dynamic_div").show("slow");
                } else {
                    jQuery("#wp_sms_country_code_radio_static").prop("checked", true);
                    jQuery("#wp_sms_country_code_static_div").show("slow");
                    jQuery("#field_wp_sms_country_code_dynamic_div").hide("slow");
                }
                jQuery('input[name="wp_sms_country_code_radio"]').on("click", function () {
                    if (jQuery('input[name="wp_sms_country_code_radio"]:checked').val() == 'dynamic') {
                        jQuery("#wp_sms_country_code_static_div").hide("slow");
                        jQuery("#field_wp_sms_country_code_dynamic_div").show("slow");
                    } else {
                        jQuery("#wp_sms_country_code_static_div").show("slow");
                        jQuery("#field_wp_sms_country_code_dynamic_div").hide("slow");
                    }
                });
                jQuery('#wp_sms_country_code_static').val(field.wp_sms_country_code_static == undefined ? <?php echo ! empty( $settings ) && ! empty( $settings["code"] ) ? $settings["code"] : "''"; ?> : field.wp_sms_country_code_static);
                jQuery("#field_wp_sms_country_code_dynamic").val(field["field_wp_sms_country_code_dynamic"]);

            }

            jQuery(document).bind("gform_load_field_settings", function (event, field, form) {
                wp_sms_pgf_integration(field.wp_sms_subs);
                jQuery('input[name="wp_sms_subs"]').on("click", function () {
                    wp_sms_pgf_integration(jQuery('input[name="wp_sms_subs"]:checked').val());
                });
            });

            //get field online
            function gf_wp_sms_populate_select() {
                var options = ["<option value=''></option>"];
                jQuery.each(window.form.fields, function (i, field) {
                    if (field.inputs) {
                        jQuery.each(field.inputs, function (i, input) {
                            options.push("<option value='", input.id, "'>", field.label, " (", input.label, ") (ID: ", input.id, ")</option>");
                        });
                    } else {
                        options.push("<option value='", field.id, "'>", field.label, " (ID: ", field.id, ")</option>");
                    }
                });
                jQuery("select[id^=field_wp_sms_]").html(options.join(""));
            }

            jQuery(document).bind("gform_field_deleted", gf_wp_sms_populate_select);
            jQuery(document).bind("gform_field_added", gf_wp_sms_populate_select);
            gf_wp_sms_populate_select();
            jQuery(document).bind("gform_load_field_settings", function (event, field, form) {
                var fields = [ <?php foreach ( self::get_this_fields() as $key => $value ) {
					echo "'{$key}',";
				} ?> ];
                fields.map(function (fname) {
                    jQuery("#field_wp_sms_" + fname).attr("value", field["field_wp_sms_" + fname]);
                });
            });
		</script>
		<?php
	}


	public static function standard_settings( $position, $form_id ) {
		global $wpdb, $table_prefix;

		if ( $position == 25 ) { ?>

			<li class="wp_sms_subscribtion_setting field_setting">

				<label>
					عضویت در خبرنامه
					<?php gform_tooltip( "wp_sms_subs" ); ?>
				</label>
				<div>
					<input type="radio" name="wp_sms_subs" id="wp_sms_subs_subs" size="10" value="subscribe"
					       onclick="SetFieldProperty('wp_sms_subs', this.value);"/>
					<label for="wp_sms_subs_subs" class="inline">
						عضویت
					</label>

					<input type="radio" name="wp_sms_subs" id="wp_sms_subs_unsubs" size="10" value="unsubscribe"
					       onclick="SetFieldProperty('wp_sms_subs', this.value);"/>
					<label for="wp_sms_subs_unsubs" class="inline">
						لغو عضویت
					</label>
				</div>

				<?php foreach ( self::get_this_fields() as $key => $value ) {
					if ( $key != 'country_code_dynamic' ) { ?>
						<div class="field_wp_sms_<?php echo esc_attr( $key ) ?>">
							<br/>
							<label for="field_wp_sms_<?php echo esc_attr( $key ) ?>">
								<?php echo esc_html($value) ?>
								<?php gform_tooltip( 'field_wp_sms_' . $key ) ?>
							</label>
							<select id="field_wp_sms_<?php echo esc_attr($key) ?>"
							        onchange="SetFieldProperty('field_wp_sms_<?php echo esc_attr( $key ) ?>', this.value);"></select>
						</div>
					<?php } ?>
				<?php } ?>

				<div class="sms_country_code">
					<br/>
					<label>
						کد کشور
						<?php gform_tooltip( "wp_sms_country_code_select" ); ?>
					</label>
					<div>
						<input type="radio" name="wp_sms_country_code_radio" id="wp_sms_country_code_radio_static"
						       size="10" value="static"
						       onclick="SetFieldProperty('wp_sms_country_code_radio', this.value);"/>
						<label for="wp_sms_country_code_radio_static" class="inline">
							ثابت
						</label>

						<input type="radio" name="wp_sms_country_code_radio" id="wp_sms_country_code_radio_dynamic"
						       size="10" value="dynamic"
						       onclick="SetFieldProperty('wp_sms_country_code_radio', this.value);"/>
						<label for="wp_sms_country_code_radio_dynamic" class="inline">
							داینامیک
						</label>
					</div>

					<div id="wp_sms_country_code_static_div">
						<input id="wp_sms_country_code_static" name="wp_sms_country_code_static" type="text" size="17"
						       style="text-align:left;direction:ltr !important"
						       onkeyup="SetFieldProperty('wp_sms_country_code_static', this.value);">
					</div>

					<div id="field_wp_sms_country_code_dynamic_div">
						<select id="field_wp_sms_country_code_dynamic"
						        onchange="SetFieldProperty('field_wp_sms_country_code_dynamic', this.value);"></select>
					</div>
				</div>

				<br class="field_wp_sms_name"/>

				<div class="wp_sms_group_select_div">
					<label for="wp_sms_group_select" class="inline">
						چگونه در یک گروه مشترکین عضو شویم
						<?php gform_tooltip( "wp_sms_group_select" ); ?>
					</label><br/>

					<select id="wp_sms_group_select" onchange="SetFieldProperty('wp_sms_group_select', this.value);">
						<option value="user">
							باید توسط کاربر انتخاب شود
						</option>
						<option value="force">
							مجبور به عضویت در گروه تعیین شده
						</option>
					</select>
				</div>

				<br class="wp_sms_group_select_div"/>

				<div class="wp_sms_group_type_div">
					<label for="wp_sms_group_type">
						نوع نمایش برای گروه مشترکین
						<?php gform_tooltip( 'wp_sms_group_type' ) ?>
					</label>

					<div>
						<input type="radio" name="wp_sms_group_type" id="wp_sms_group_type_radio" size="10"
						       value="radio" onclick="SetFieldProperty('wp_sms_group_type', this.value);"/>
						<label for="wp_sms_group_type_radio" class="inline">
							دکمه‌های رادیویی
						</label>

						<input type="radio" name="wp_sms_group_type" id="wp_sms_group_type_select" size="10"
						       value="select" onclick="SetFieldProperty('wp_sms_group_type', this.value);"/>
						<label for="wp_sms_group_type_select" class="inline">
							کشویی
						</label>
					</div>
				</div>

				<br class="wp_sms_group_type_div"/>


				<div class="wp_sms_group_forced_div">
					<label for="wp_sms_group_forced">
						لطفاً گروه مورد نظر را انتخاب کنید
						<?php gform_tooltip( 'wp_sms_group_forced' ) ?>
					</label>

					<select id="wp_sms_group_forced" onchange="SetFieldProperty('wp_sms_group_forced', this.value);">
						<?php
						$get_group_result = $wpdb->get_results( "SELECT * FROM {$table_prefix}sms_subscribes_group" );
						foreach ( (array) $get_group_result as $items ) { ?>
							<option value="<?php echo esc_attr($items->ID) ?>"><?php echo esc_html($items->name) ?></option>
						<?php } ?>
					</select>
				</div>

				<br class="wp_sms_group_forced_div"/>

				<div class="wp_sms_repeat_error_div">
					<input type="checkbox" id="wp_sms_repeat_error"
					       onclick="SetFieldProperty('wp_sms_repeat_error', this.checked);"/>
					<label for="wp_sms_repeat_error" class="inline">
						پیام با همان شماره
						<?php gform_tooltip( "wp_sms_repeat_error" ); ?>
					</label>
				</div>

				<div class="wp_sms_repeat_mgs_div">
					<input type="text" id="wp_sms_repeat_mgs" class="fieldwidth-1"
					       onkeyup="SetFieldProperty('wp_sms_repeat_mgs', this.value);"/>
				</div>


				<?php if ( get_option( 'wp_subscribes_send_sms' ) ) { ?>

					<br class="wp_sms_welcome_msg_div"/>

					<div class="wp_sms_welcome_msg_div">
						<input type="checkbox" id="wp_sms_welcome_msg"
						       onclick="SetFieldProperty('wp_sms_welcome_msg', this.checked);"/>
						<label for="wp_sms_welcome_msg" class="inline">
							ارسال پیام خوش آمد گویی
							<?php gform_tooltip( "wp_sms_welcome_msg" ); ?>
						</label>
					</div>
				<?php } ?>

			</li>
			<?php
		}
	}

	public static function get_this_fields() {
		return [
			"name"                 => 'زمینه نام',
			"mobile"               => 'زمینه شماره تلفن',
			"country_code_dynamic" => '',
		];
	}

	public static function input( $input, $field, $value, $entry_id, $form_id ) {
		global $wpdb, $table_prefix;

		if ( $field["type"] == "sms_subscribtion" ) {

			$is_entry_detail  = GFCommon::is_entry_detail();
			$is_form_editor   = GFCommon::is_form_editor();
			$get_group_result = $wpdb->get_results( "SELECT * FROM {$table_prefix}sms_subscribes_group" );

			$field_id = $field["id"];
			$form_id  = empty( $form_id ) ? rgget( "id" ) : $form_id;

			$disabled_text = $is_form_editor ? "disabled='disabled'" : '';

			$size         = rgar( $field, "size" );
			$class_suffix = $is_entry_detail ? '_admin' : '';
			$class        = $size . $class_suffix;

			$html5_attributes = '';

			$input_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$field_id" : 'input_' . $form_id . "_$field_id";

			$tabindex = GFCommon::get_tabindex();

			if ( ! is_admin() && ( RGFormsModel::get_input_type( $field ) == 'adminonly_hidden' ) ) {
				return '';
			}

			if ( ! is_admin() && ( rgar( $field, "wp_sms_subs" ) == 'unsubscribe' || rgar( $field, "wp_sms_group_select" ) == 'force' ) ) {
				$hidden = '';
				if ( ! empty( $field['conditionalLogic'] ) ) {
					$hidden .= '<input name="input_' . $field_id . '" id="' . $input_id . '" type="hidden" value="true" />';
				}

				return apply_filters( 'wp_sms_display_none', true ) ? $hidden . '<style type="text/css">#field_' . $form_id . '_' . $field_id . '{display:none !important;}</style>' : '';
			}

			if ( $is_entry_detail && rgar( $field, "wp_sms_subs" ) == 'unsubscribe' ) {
				return '<p>امکان ویرایش فیلد WP SMS در حالت لغو عضویت وجود ندارد.</p>';
			}


			$type = rgar( $field, "wp_sms_group_type" ) ? rgar( $field, "wp_sms_group_type" ) : 'radio';

			$desired_form_id = ( $is_form_editor || ! is_admin() ) ? $form_id . '_' : '';

			$wp_sms_select = $wp_sms_radio = '';

			if ( $is_form_editor || $type == 'select' ) {

				if ( ! is_admin() && $field->enableEnhancedUI ) {

					$form          = RGFormsModel::get_form_meta( $form_id );
					$chosen_fields = [];
					foreach ( $form['fields'] as $field_val ) {
						$input_type = GFFormsModel::get_input_type( $field_val );
						if ( $field_val->enableEnhancedUI && in_array( $input_type, [
								'select',
								'multiselect',
								'sms_subscribtion'
							] ) ) {
							$chosen_fields[] = "#input_{$form['id']}_{$field_val->id}";
						}
					}
					$chosen_script = "gformInitChosenFields('" . implode( ',', $chosen_fields ) . "','" . esc_attr( gf_apply_filters( 'gform_dropdown_no_results_text', $form['id'], __( 'No results matched', 'gravityforms' ), $form['id'] ) ) . "');";
					GFFormDisplay::add_init_script( $form_id, 'chosen', GFFormDisplay::ON_PAGE_RENDER, $chosen_script );
					GFFormDisplay::add_init_script( $form_id, 'chosen', GFFormDisplay::ON_CONDITIONAL_LOGIC, $chosen_script );


					if ( ! wp_script_is( 'gform_gravityforms', 'enqueued' ) ) {
						wp_enqueue_script( 'gform_gravityforms' );
					}

					if ( ! wp_script_is( 'chosen' ) ) {
						wp_enqueue_script( 'gform_chosen' );
					}

					if ( wp_script_is( 'chosen', 'registered' ) ) {
						wp_enqueue_script( 'chosen' );
					} else {
						wp_enqueue_script( 'gform_chosen' );
					}

					$scripts = [];

					if ( ! wp_script_is( 'gform_chosen' ) && ! wp_script_is( 'chosen' ) ) {
						if ( wp_script_is( 'chosen', 'registered' ) ) {
							$scripts[] = 'chosen';
						} else {
							$scripts[] = 'gform_chosen';
						}
					}

					if ( ! empty( $scripts ) ) {
						foreach ( (array) $scripts as $script ) {
							wp_enqueue_script( $script );
						}
						wp_print_scripts( $scripts );
					}

				}

				$wp_sms_select .= '<div class="ginput_container ginput_container_select ginput_container_sms_select" id="ginput_container_sms_select_' . $field_id . '">';
				$wp_sms_select .= '<select name="input_' . $field_id . '" id="input_' . $desired_form_id . $field_id . '" class="sms_subscribtion ' . esc_attr( $class ) . '" ' . $tabindex . ' ' . $html5_attributes . ' ' . $disabled_text . '/>';
				if ( empty( $get_group_result ) && $is_form_editor ) {
					$wp_sms_select .= '<div class="gf-html-container ginput_container_unsubscribe">';
					$wp_sms_select .= '<span>';
					$wp_sms_select .= "اول باید چند گروه عضویت در افزونه WP SMS ایجاد کنید.";
					$wp_sms_select .= '</span>';
					$wp_sms_select .= '</div>';
				} else {
					foreach ( (array) $get_group_result as $items ) {
						$selected      = $items->ID == $value ? 'selected="selected"' : '';
						$wp_sms_select .= '<option value="' . $items->ID . '" ' . $selected . '>' . $items->name . '</option>';
					}
				}
				$wp_sms_select .= '</select>';
				$wp_sms_select .= '</div>';
			}

			if ( $is_form_editor || $type != 'select' ) {

				$choice_id    = 0;
				$wp_sms_radio .= '<div class="ginput_container ginput_container_radio ginput_container_sms_radio" id="ginput_container_sms_radio_' . $field_id . '">';
				$wp_sms_radio .= '<ul class="gfield_radio" id="input_' . $desired_form_id . $field_id . '">';

				if ( empty( $get_group_result ) && $is_form_editor ) {
					$wp_sms_radio .= '<div class="gf-html-container ginput_container_unsubscribe">';
					$wp_sms_radio .= '<span>';
					$wp_sms_radio .= "اول باید چند گروه عضویت در افزونه WP SMS ایجاد کنید.";
					$wp_sms_radio .= '</span>';
					$wp_sms_radio .= '</div>';
				} else {
					foreach ( (array) $get_group_result as $items ) {
						$checked      = $items->ID == $value ? 'checked="checked"' : '';
						$wp_sms_radio .= '
						<li class="gchoice_' . $desired_form_id . $field_id . '_' . $choice_id . '">
							<input name="input_' . $field_id . '" type="radio" value="' . $items->ID . '" id="choice_' . $desired_form_id . $field_id . '_' . $choice_id . '" ' . $checked . ' ' . $tabindex . ' ' . $disabled_text . ' />
							<label id="label_' . $desired_form_id . $field_id . '_' . $choice_id . '" for="choice_' . $desired_form_id . $field_id . '_' . $choice_id . '">' . $items->name . '</label>
						</li>';
						$choice_id ++;
					}
				}
				$wp_sms_radio .= '</ul>';
				$wp_sms_radio .= '</div>';
			}


			if ( $is_form_editor ) {

				$wp_sms_input_unsubscribe = '<div class="gf-html-container ginput_container_unsubscribe" id="ginput_container_unsubscribe_' . $field_id . '">';
				$wp_sms_input_unsubscribe .= '<span>';
				$wp_sms_input_unsubscribe .= 'زمانی که گزینه "لغو عضویت" فعال می‌شود، هیچ چیزی برای این فیلد در خروجی فرم نمایش داده نخواهد شد، اما لغو عضویت به هر حال انجام خواهد شد.';
				$wp_sms_input_unsubscribe .= '</span>';
				$wp_sms_input_unsubscribe .= '</div>';

				$wp_sms_input_force = '<div class="gf-html-container ginput_container_unsubscribe" id="ginput_container_force_' . $field_id . '">';
				$wp_sms_input_force .= '<span>';
				$wp_sms_input_force .= 'زمانی که گزینه "گروه اجباری" فعال می‌شود، هیچ چیزی برای این فیلد در خروجی فرم نمایش داده نخواهد شد، اما عضویت به هر حال انجام خواهد شد.';
				$wp_sms_input_force .= '</span>';
				$wp_sms_input_force .= '</div>';

				$style = '<style type="text/css">';
				$style .= '#ginput_container_sms_select_' . $field_id . ' option{display:none;}';

				$hide_radio       = '#ginput_container_sms_radio_' . $field_id . '{display:none;}';
				$hide_select      = '#ginput_container_sms_select_' . $field_id . '{display:none;}';
				$hide_unsubscribe = '#ginput_container_unsubscribe_' . $field_id . '{display:none;}';
				$hide_force       = '#ginput_container_force_' . $field_id . '{display:none;}';

				if ( rgar( $field, 'wp_sms_subs' ) == 'unsubscribe' ) {
					$style .= $hide_radio;
					$style .= $hide_select;
					$style .= $hide_force;
				} else {
					$style .= $hide_unsubscribe;
					if ( rgar( $field, 'wp_sms_group_select' ) != 'force' ) {
						$style .= $hide_force;
						if ( $type == 'select' ) {
							$style .= $hide_radio;
						} else {
							$style .= $hide_select;
						}
					} else {
						$style .= $hide_radio;
						$style .= $hide_select;
					}
				}
				$style .= '</style>';

				return $wp_sms_input_unsubscribe . $wp_sms_select . $wp_sms_radio . $wp_sms_input_force . $style;
			}

			return $wp_sms_input = ( $type == 'select' ? $wp_sms_select : $wp_sms_radio );
		}

		return $input;
	}


	public static function wp_sms_group_name_by_id( $value ) {
		global $wpdb, $table_prefix;

		if ( ! is_numeric( $value ) ) {
			return $value;
		}

		$result = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$table_prefix}sms_subscribes_group WHERE ID = %d", $value ) );

		if ( ! empty( $result ) ) {
			$value = $result;
		}

		return $value;
	}


	public static function content( $content, $field, $value, $entry_id, $form_id ) {

		$mode = ! rgpost( 'screen_mode' ) ? 'view' : sanitize_text_field( rgpost( 'screen_mode' ) );

		if ( GFCommon::is_entry_detail() && $mode == 'view' && $field["type"] == "sms_subscribtion" ) {
			$content = '<tr>
					<td colspan="2" class="entry-view-field-name">' . esc_html( GFCommon::get_label( $field ) ) . '</td>
				</tr>
				<tr>
					<td colspan="2" class="entry-view-field-value">' . self::wp_sms_group_name_by_id( $value ) . '</td>
                </tr>';
		}

		return $content;
	}


	public static function entries_value( $value, $form_id, $field_id, $entry ) {

		$form  = RGFormsModel::get_form_meta( $form_id );
		$field = RGFormsModel::get_field( $form, $field_id );

		$field = (array) $field;//

		if ( $field["type"] == "sms_subscribtion" ) {
			$value = self::wp_sms_group_name_by_id( $value );
		}

		return $value;
	}


	public static function merge_tag( $value, $merge_tag, $modifier, $field ) {

		if ( $field->type == 'sms_subscribtion' ) {
			$value = self::wp_sms_group_name_by_id( $value );
		}

		return $value;
	}

	public static function country_code( $field ) {

		$code_type = rgar( $field, 'wp_sms_country_code_radio' );
		if ( $code_type == 'dynamic' ) {
			$code = rgar( $field, 'field_wp_sms_country_code_dynamic' );
			$code = str_replace( '.', '_', $code );
			$code = "input_{$code}";
			$code = ! rgempty( $code ) ? sanitize_text_field( rgpost( $code ) ) : '';
		} else {
			$code = rgar( $field, 'wp_sms_country_code_static' );
		}

		return $code;
	}

	public static function validation( $result, $value, $form, $field ) {
		global $wpdb, $table_prefix;

		if ( $field["type"] == "sms_subscribtion" ) {
			$mobile_error = false;

			if ( rgar( $field, 'wp_sms_subs' ) != 'unsubscribe' && rgar( $field, 'wp_sms_repeat_error' ) ) {

				$mobile = rgar( $field, 'field_wp_sms_mobile' );
				$mobile = str_replace( '.', '_', $mobile );
				$mobile = "input_{$mobile}";
				$mobile = ! rgempty( $mobile ) ? sanitize_text_field( rgpost( $mobile ) ) : '';
				$mobile = GFPersian_SMS_Sender::change_mobile_separately( $mobile, self::country_code( $field ) );

				$mobile_exist = $wpdb->query( $wpdb->prepare( "SELECT * FROM {$table_prefix}sms_subscribes WHERE mobile = %s", $mobile ) );

				if ( $mobile_exist ) {
					$mobile_error       = true;
					$result["is_valid"] = false;
					$result["message"]  = rgar( $field, 'wp_sms_repeat_mgs' ) ? rgar( $field, 'wp_sms_repeat_mgs' ) : "شماره موبایل وارد شده قبلاً در این فرم استفاده شده است.";
					add_filter( 'wp_sms_display_none', '__return_false' );
				}

			}

			if ( RGFormsModel::get_input_type( $field ) == 'adminonly_hidden' || rgar( $field, 'wp_sms_subs' ) == 'unsubscribe' || ( rgar( $field, 'wp_sms_group_select' ) == 'force' && ! $mobile_error ) ) {
				$result['is_valid'] = true;
			}

		}

		return $result;
	}

	public static function process( $entry, $form ) {

		$wp_sms_fileds = GFCommon::get_fields_by_type( $form, [ 'sms_subscribtion' ] );

		foreach ( (array) $wp_sms_fileds as $field ) {

			$field = (array) $field;

			if ( ! empty( $field['conditionalLogic'] ) && empty( $entry[ $field['id'] ] ) ) {
				break;
			}

			$type = rgar( $field, 'wp_sms_subs' ) ? rgar( $field, 'wp_sms_subs' ) : 'subscribe';

			$name = rgar( $field, 'field_wp_sms_name' );
			$name = str_replace( '.', '_', $name );
			$name = "input_{$name}";
			$name = ! rgempty( $name ) ? sanitize_text_field( rgpost( $name ) ) : '';

			$mobile = rgar( $field, 'field_wp_sms_mobile' );
			$mobile = str_replace( '.', '_', $mobile );
			$mobile = "input_{$mobile}";
			$mobile = ! rgempty( $mobile ) ? sanitize_text_field( rgpost( $mobile ) ) : '';
			$mobile = GFPersian_SMS_Sender::change_mobile_separately( $mobile, self::country_code( $field ) );

			if ( rgar( $field, 'wp_sms_group_select' ) == 'force' ) {
				$groups = rgar( $field, 'wp_sms_group_forced' );
			} else {
				$groups = str_replace( '.', '_', $field['id'] );
				$groups = "input_{$groups}";
				$groups = ! rgempty( $groups ) ? rgpost( $groups ) : '';
			}

			if ( is_array( $groups ) ) {
				$groups = array_map( 'sanitize_text_field', $groups );
			} else {
				$groups = sanitize_text_field( $groups );
			}

			if ( $type == 'subscribe' ) {
				$value = $entry[ $field['id'] ] = is_array( $groups ) ? implode( ',', $groups ) : $groups;
			} else {
				$value = $entry[ $field['id'] ] = '';
			}
			GFAPI::update_entry_field( $entry['id'], $field['id'], $value );


			$process = self::subscribtion( $name, $mobile, $groups, $type, rgar( $field, 'wp_sms_repeat_error' ) );

			if ( ! empty( $process['message'] ) ) {
				RGFormsModel::add_note( $entry["id"], 0, 'پیامک گرویتی - پیامک وردپرس', $process['message'] );
			}

			if ( ! empty( $process['status'] ) && $process['status'] == 'success-1' ) {

				if ( get_option( 'wp_subscribes_send_sms' ) && rgar( $field, 'wp_sms_welcome_msg' ) ) {

					$string = get_option( 'wp_subscribes_text_send' );
					//$template_vars = array( 'subscribe_name' => $name, 'subscribe_mobile' => $mobile );
					$final_message = preg_replace( '/%(.*?)%/ime', "\$template_vars['$1']", $string );

					GFPersian_SMS_Sender::Send( $mobile, $final_message, $from = '', $form['id'], '', '' );

				}
			}

		}

		return $entry;
	}

	public static function subscribtion( $name, $mobile, $groups, $type, $no_repeat = false ) {
		global $wpdb, $table_prefix;

		if ( empty( $mobile ) ) {
			return [ 'status' => 'empty', 'message' => 'مقدار شماره موبایل خالی است.' ];
		}


		$mobile_exist = false;
		if ( $no_repeat || $type != 'subscribe' ) {
			$_mobile      = substr( $mobile, - 10 );
			$mobile_exist = $wpdb->get_results( $wpdb->prepare( "SELECT mobile FROM {$table_prefix}sms_subscribes WHERE mobile LIKE %s", '%' . $_mobile . '%' ), ARRAY_N );
		}

		if ( empty( $mobile_exist ) || $type != 'subscribe' ) {

			if ( $type == 'subscribe' ) {

				$groups = is_array( $groups ) ? $groups : [ $groups ];

				foreach ( (array) $groups as $group ) {

					$insert = $wpdb->insert( "{$table_prefix}sms_subscribes",
						[
							'date'     => date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ),
							'name'     => $name,
							'mobile'   => $mobile,
							'status'   => '1',
							'group_ID' => $group
						]
					);

					if ( $insert ) {
						return [
							'status'  => 'success-1',
							'message' => 'عضویت در خبرنامه پیامکی با موفقیت انجام شد.'
						];
					} else {
						return [
							'status'  => 'failed-1',
							'message' => 'عضویت در خبرنامه پیامکی ناموفق بود.'
						];
					}
				}

			} elseif ( $type == 'unsubscribe' ) {

				if ( ! empty( $mobile_exist ) ) {

					$delete = 0;
					foreach ( $mobile_exist as $mobile ) {
						$mobile = (array) $mobile;
						$delete = $delete + $wpdb->delete( "{$table_prefix}sms_subscribes", [ 'mobile' => reset( $mobile ) ] );
					}

					if ( ! empty( $delete ) ) {
						return [
							'status'  => 'success-2',
							'message' => 'لغو عضویت در خبرنامه پیامکی با موفقیت انجام شد.'
						];
					} else {
						return [
							'status'  => 'failed-2',
							'message' => 'لغو عضویت در خبرنامه پیامکی ناموفق بود.'
						];
					}

				} else {
					return [
						'status'  => 'not-sub',
						'message' => 'شماره وارد شده به خبرنامه پیامکی عضو نشده بود و لغو عضویت انجام نشد.'
					];
				}
			}

		} else {
			return [
				'status'  => 'repeat',
				'message' => 'شماره موبایل وارد شده قبلاً در خبرنامه استفاده شده است.'
			];
		}

	}
}

new GFPersian_SMS_WPSMS();