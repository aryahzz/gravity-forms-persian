<?php

defined( 'ABSPATH' ) || exit;

class GFPersian_Settings extends GFAddOn {

	protected $_version;
	protected $_slug;
	protected $_path;
	protected $_full_path;
	protected $_short_title = 'گرویتی فرم فارسی';
	protected $_title = 'بسته گرویتی فرم فارسی';
	protected $_min_gravityforms_version = GF_PERSIAN_REQUIRED_GF_VERSION;

	private static $_instance = null;

	public function __construct() {

		parent::__construct();

		$this->_version   = GF_PERSIAN_VERSION;
		$this->_slug      = GF_PERSIAN_SLUG;
		$this->_path      = GF_PERSIAN_DIR . 'index.php';
		$this->_full_path = __FILE__;
	}

	public static function get_instance() {

		if ( self::$_instance == null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function init() {
		parent::init();
	}

	public function plugin_settings_fields() {
		return [
			[
				'title'  => 'تنظیمات گرویتی فرم فارسی',
				'fields' => [
					[
						'name'          => 'translate',
						'label'         => 'ترجمه گرویتی فرم',
						'tooltip'       => 'با فعالسازی این گزینه در صورتی که زبان سایت به صورت فارسی باشد گرویتی فرم به فارسی ترجمه خواهد شد.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'address',
						'label'         => 'آدرس های ایران',
						'tooltip'       => 'با فعالسازی این گزینه استان ها و شهرهای ایران به فیلد "آدرس" اضافه خواهد شد. باید از تب "عمومی" فیلد آدرس گزینه "نوع آدرس" را بر روی "ایران" قرار دهید و سپس تیک "فعالسازی شهرهای ایران" را انتخاب کنید.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'national_id',
						'label'         => 'فیلد کد ملی',
						'tooltip'       => 'با فعالسازی این فیلد "کد ملی" به فیلدهای پیشرفته گرویتی فرم اضافه خواهد شد. این فیلد قابلیت اعتبار سنجی کد ملی و همچنین نمایش شهر صادر کننده کد ملی را هم دارد.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'jalali',
						'label'         => 'شمسی ساز فیلد تاریخ',
						'tooltip'       => 'با فعالسازی این فیلد گزینه تاریخ جلالی به قابلیت های فیلد تاریخ گرویتی فرم اضافه خواهد شد.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'currencies',
						'label'         => 'واحدهای پول ایران',
						'tooltip'       => 'با فعالسازی این فیلد گزینه واحدهای پول ایران (ریال - تومان - هزار ریال - هزار تومان) به واحدهای پول گرویتی فرم اضافه خواهد شد.<br><br>واحد های "هزار ریال - هزار تومان" با نسخه 2.3 درگاه های پرداخت به بالا سازگارند و برای نسخه های پایین تر درگاه های پرداخت قابل استفاده نیستند.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'rtl_admin',
						'label'         => 'راستچین سازی مدیریت',
						'tooltip'       => 'با فعالسازی این فیلد گزینه بخش هایی از محیط کار با گرویتی فرم در مدیریت که نیاز به راستچین سازی داشته باشند، راستچین خواهند شد.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'font_admin',
						'label'         => 'فونت مدیریت',
						'tooltip'       => 'با توجه به جذاب بودن محیط کار با گرویتی فرم در بخش مدیریت سایت، با اضافه کردن فرم فارسی آن را جذاب تر نمایید.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => 'vazir',
						'choices'       => [
							[ 'label' => 'بدون فونت', 'value' => '0' ],
							[ 'label' => 'یکان', 'value' => 'yekan' ],
							[ 'label' => 'وزیر', 'value' => 'vazir' ],
							[ 'label' => 'شبنم', 'value' => 'shabnam' ],
						],
					],
					[
						'name'          => 'rss_widget',
						'label'         => 'خبرخوان گرویتی فرم فارسی',
						'tooltip'       => 'با فعالسازی این فیلد گزینه ابزارک خبرخوان گرویتی فرم فارسی به داشبورد مدیریت وردپرس اضافه خواهد شد تا آخرین پست های گرویتی فرم فارسی را مشاهده کنید.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],

				]
			],
			[
				'title'       => 'پیامک حرفه ای',
				'description' => 'پس از ثبت نام در وبسرویس مد نظر، مشخصات کاربری خود را وارد نمایید.',
				'fields'      => [
					[
						'name'          => 'sms_gateway',
						'label'         => 'وبسرویس پیامک',
						'tooltip'       => '',
						'type'          => 'select',
						'choices'       => GFPersian_SMS::create_gateway_choices(),
						'default_value' => 'none',
					],
					[
						'name'          => 'sms_username',
						'label'         => 'نام کاربری وبسرویس',
						'tooltip'       => 'پس از ثبت نام در وبسرویس با توجه به مستندات، در اختیار شما قرار خواهد گرفت.',
						'type'          => 'text',
						'default_value' => '',
					],
					[
						'name'          => 'sms_password',
						'label'         => 'کلمه عبور وبسرویس',
						'tooltip'       => 'پس از ثبت نام در وبسرویس با توجه به مستندات، در اختیار شما قرار خواهد گرفت.',
						'type'          => 'text',
						'default_value' => '',
					],
					[
						'name'          => 'sms_from_numbers',
						'label'         => 'شماره ارسال کننده پیامک',
						'tooltip'       => 'ابتدا ارسال پنل پیامک را بررسی کرده و پس از تست شماره های ارسال کننده یکی را در این ورودی ثبت کنید. بدون فاصله ثبت کرده و با , جدا کنید.',
						'type'          => 'text',
						'default_value' => '',
					],
					[
						'name'          => 'sms_country_code',
						'label'         => 'کد کشور پیشفرض',
						'tooltip'       => 'کد کشور ایران : +98 که در ابتدای شماره های ارسالی قرار خواهد گرفت. ماننده +989121234567',
						'type'          => 'text',
						'default_value' => '',
					],
				]
			],
			[
				'title'  => 'کد رهگیری',
				'fields' => [
					[
						'name'          => 'enable_transaction_id',
						'label'         => 'فعالسازی کد رهگیری',
						'tooltip'       => 'به صورت پیشفرض در گرویتی فرم فقط زمانی که یک پرداخت انجام شود یک شماره تراکنش ثبت میشود<br> با فعالسازی این گزینه میتوانید حتی برای فرم هایی که به درگاه پرداخت متصل نیستند نیز کد رهگیری اختصاص دهید.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'transaction_id_title',
						'label'         => 'عنوان کد رهگیری',
						'tooltip'       => 'در صورتیکه گزینه بالا را فعال کرده اید، عنوانی که میخواهید برای کد رهگیری نمایش داده شود را وارد کنید.',
						'type'          => 'text',
						'default_value' => 'شماره تراکنش',
					],
					[
						'name'          => 'transaction_id_mask',
						'label'         => 'الگوی کد رهگیری (قاب)',
						'tooltip'       => 'با توجه به آشنایی با مفهوم قاب (الگو) در گرویتی فرم، الگوی دلخواه خود برای تولید کد رهگیری را وارد کنید.',
						'type'          => 'text',
						'default_value' => '9999999999',
						'style'         => 'text-align:left; direction:ltr;',
						'after_input'   => $this->mask_instructions(),
					]
				]
			],
			[
				'title'       => 'برچسب های ادغام (شورتکد)',
				'description' => 'برچسب های ادغام (Merge Tags) در واقع همان کدهای کوتاه گرویتی فرم هستند.',
				'fields'      => [
					[
						'name'          => 'add_merge_tags',
						'label'         => 'برچسب های ادغام جدید',
						'tooltip'       => 'با فعالسازی این گزینه، شورتکدهای جدیدی به لیست آنها اضافه خواهد شد. شورتکدهایی نظیر اطلاعات پرداخت - وضعیت پرداخت - کد رهگیری و ...',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'post_content_merge_tags',
						'label'         => 'برچسب ها در برگه ها',
						'tooltip'       => 'به صورت پیشفرض وقتی نوع تاییدیه ها را روی "برگه" ست میکنید امکان استفاده از "برچسب ها (شورتکد های گرویتی فرم)" در آن برگه ها وجود نخواهد داشت. با فعالسازی این گزینه این امکان فراهم خواهد شد.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'entry_time',
						'label'         => 'ماندگاری برچسب ها در برگه',
						'tooltip'       => 'با فعالسازی گزینه بالا، زمانی که کاربر به برگه تاییدیه هدایت میشود، آیدی پیام ورودی اش نیز در آدرس بار پاس داده میشود و این سبب میشود که افراد دیگر با دانستن شماره پیام (و یا آزمون و خطا) به اطلاعات آن تاییدیه دسترسی داشته پیدا کنند. برای جلوگیری از این قضیه یک مدت زمان بر حسب دقیقه وارد کنید تا پس از گذشت آن زمان دسترسی به تاییدیه منقضی شود.',
						'type'          => 'text',
						'class'         => 'small',
						'input_type'    => 'number',
						'default_value' => '0',
						'style'         => 'width:70px;',
						'after_input'   => '   (دقیقه) - در صورتی که این فیلد را خالی و یا برابر 0 قرار دهید، شماره پیام ورودی به صورت "انکریپت شده" پاس داده خواهد شد.',
					],
					[
						'name'          => 'pre_submission_merge_tags',
						'label'         => 'برچسب ها در فیلد HTML',
						'tooltip'       => 'توسط این امکان میتوانید برای فرم های خود یک "پیشفاکتور" بسازید.<br>' . '<a target="_blank" href="https://gravityforms.ir/?p=5690">برای مشاهده راهنما کلیک کنید.</a>',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
				]
			],
			[
				'title'  => 'تنظیمات سایر ویژگی ها',
				'fields' => [
					[
						'name'          => 'hide_lic',
						'label'         => 'حذف بنر فعال نبودن لایسنس',
						'tooltip'       => 'در برخی نسخه های گرویتی فرم، در صورتی که لایسنس گرویتی فرم شما فعال نباشد یک بنر بالای صفحات گرویتی فرم مبنی بر عدم فعال بودن لایسنس شما ظاهر میشود که با فعالسازی این گزینه میتوانید آن را مخفی نمایید.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '0',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'live_preview',
						'label'         => 'پیش نمایش زنده',
						'tooltip'       => 'با فعالسازی این گزینه، منوی پیش نمایش زنده در ویرایشگر فرم اضافه میشود تا فرم را داخل فرانت اند سایت مشاهده نمایید. البته این گزینه ممکن است با برخی قالب ها سازگاری نداشته باشد. چون یک پست تایپ مجازی ایجاد میکند.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'label_visibility',
						'label'         => 'مدیریت برچسب فیلدها',
						'tooltip'       => 'با فعالسازی این گزینه، یک آپشن جدید در تب "نمایش" فیلدها زیر "نگه دارنده متن (Placeholder)" تحت عنوان "نمایش برچسب فیلد" اضافه خواهد شد تا بتوانید "برچسب (Lable)" فیلد ها را مخفی کنید.این گزینه برای زمانی که از "نگه دارنده متن (Placeholder)" استفاده میکنید مفید است.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'newsletter',
						'label'         => 'رویداد خبرنامه در اعلان ها',
						'tooltip'       => 'توسط این امکان میتوانید گرویتی فرم خود را به یک پلاگین خبرنامه تبدیل کنید.<br>' . '<a target="_blank" href="https://gravityforms.ir/?p=3940">برای مشاهده توضیحات کلیک کنید.</a>',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'private_post',
						'label'         => 'وضعیت پست خصوصی',
						'tooltip'       => 'به صورت پیشفرض وقتی از "فیلدهای ارسال نوشته" استفاده کنید وضعیت پست "خصوصی" در لیست وضیعت های پست وجود ندارد که با فعالسازی این گزینه اضافه خواهد شد.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'multipage_nav',
						'label'         => 'پیمایش فرم های مرحله ای',
						'tooltip'       => 'با فعالسازی این گزینه در فرم های چند مرحله ای (چند برگه ای) در صورتی که از تب "عمومی" برگه "نشانگر پیشرفت" را روی حالت "مرحله ها" قرار دهید،‌ کاربران بدون استفاده از دکمه های "قبلی" و "بعدی" میتوانند از طریق خود "نشانگر پیشرفت" بین صفحات (مرحله ها) جابجا شوند.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'بله', 'value' => '1' ],
							[ 'label' => 'خیر', 'value' => '0' ],
						],
					],
					[
						'name'          => 'multipage_nav_last',
						'label'         => 'شرط پیمایش فرم های مرحله ای',
						'tooltip'       => 'در صورتی که گزینه بالا را فعال کرده اید، میتوانید مشخص کنید که پیمایش بین مرحله ها همواره از ابتدا فعال باشد یا فقط زمانی که که کاربر مرحله ها را پشت سر گذاشت و به مرحله آخر رسید فعال شود.',
						'type'          => 'radio',
						'horizontal'    => true,
						'default_value' => '1',
						'choices'       => [
							[ 'label' => 'همواره فعال باشد', 'value' => '0' ],
							[
								'label' => 'فقط زمانی که تمام مراحل طی شدند و به مرحله آخر رسید فعال شود',
								'value' => '1'
							],
						],
					],
				]
			]
		];
	}


	public function settings_br( $field, $echo = true ) {
		$br = '<br>';

		return $echo ? print( $br ) : $br;
	}

	public function settings_hr( $field, $echo = true ) {

		$output = '';

		if ( ! empty( $field['text'] ) ) {
			$output .= $field['text'];
		}

		$output .= '<hr>';

		return $echo ? print( $output ) : $output;
	}

	private function mask_instructions() {

		ob_start(); ?>

		<div id="custom_mask_instructions" style="display:none;">
			<div class="custom_mask_instructions">
				<h4><?php esc_html_e( 'Usage', 'gravityforms' ) ?></h4>
				<ul class="description-list">
					<li><?php esc_html_e( "Use a '9' to indicate a numerical character.", 'gravityforms' ) ?></li>
					<li><?php esc_html_e( "Use a lower case 'a' to indicate an alphabetical character.", 'gravityforms' ) ?></li>
					<li><?php esc_html_e( "Use an asterisk '*' to indicate any alphanumeric character.", 'gravityforms' ) ?></li>
					<li><?php esc_html_e( 'All other characters are literal values and will be displayed automatically.', 'gravityforms' ) ?></li>
				</ul>

				<h4><?php esc_html_e( 'Examples', 'gravityforms' ) ?></h4>
				<ul class="examples-list">

					<li>
						<h5><?php esc_html_e( 'Social Security Number', 'gravityforms' ) ?></h5>
						<span class="label"><?php esc_html_e( 'Mask', 'gravityforms' ) ?></span>
						<code>999-99-9999</code><br/>
						<span
							class="label">نمونه خروجی</span>
						<code>987-65-4329</code>
					</li>
					<li>
						<h5><?php esc_html_e( 'Course Code', 'gravityforms' ) ?></h5>
						<span class="label"><?php esc_html_e( 'Mask', 'gravityforms' ) ?></span>
						<code>aaa 999</code><br/>
						<span
							class="label">نمونه خروجی</span>
						<code>BIO 101</code>
					</li>
					<li>
						<h5><?php esc_html_e( 'License Key', 'gravityforms' ) ?></h5>
						<span class="label"><?php esc_html_e( 'Mask', 'gravityforms' ) ?></span>
						<code>***-***-***</code><br/>
						<span
							class="label">نمونه خروجی</span>
						<code>a9a-f0c-28Q</code>
					</li>
				</ul>

			</div>
		</div>
		(<a href="javascript:void(0);" style="text-decoration: none !important;"
		    onclick="tb_show('<?php echo esc_js( __( 'Custom Mask Instructions', 'gravityforms' ) ); ?>', '#TB_inline?width=350&amp;inlineId=custom_mask_instructions', '');"
		    onkeypress="tb_show('<?php echo esc_js( __( 'Custom Mask Instructions', 'gravityforms' ) ); ?>', '#TB_inline?width=350&amp;inlineId=custom_mask_instructions', '');">
			راهنمای ایجاد الگو
		</a>)
		<?php
		return ob_get_clean();
	}

}