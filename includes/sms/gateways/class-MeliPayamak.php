<?php

/*
 * Class Name : GFPersian_SMS_{file-postfix}
 */

class GFPersian_SMS_MeliPayamak extends GFPersian_SMS_Gateway {


	public const ERRORS = [
		- 7 => 'خطایی در شماره فرستنده پیامک رخ داده است، لطفاً با پشتیبانی فنی تماس بگیرید.',
		- 6 => 'خطای داخلی رخ داده است، لطفاً با پشتیبانی فنی تماس بگیرید.',
		- 5 => 'تعداد متغیرهای پترن با متن ارسالی مطابقت ندارد.',
		- 4 => 'کد پترن صحیح نیست یا تایید نشده است.',
		- 3 => 'سرشماره تعریف نشده یا تعداد گیرندگان مجاز نیست.',
		- 2 => 'در هر بار ارسال، تنها یک گیرنده مجاز است.',
		- 1 => 'دسترسی به وب‌سرویس غیرفعال است.',
		0   => 'نام کاربری یا رمز عبور اشتباه است.',
		2   => 'اعتبار کافی نیست.',
		3   => 'محدودیت در ارسال روزانه.',
		4   => 'محدودیت تعداد یا حجم پیامک.',
		5   => 'شماره فرستنده معتبر نیست.',
		6   => 'سامانه در حال بروزرسانی است.',
		7   => 'متن پیامک شامل کلمات فیلتر شده است.',
		8   => 'تعداد پیامک کمتر از حداقل مجاز.',
		9   => 'ارسال از خطوط عمومی غیرمجاز است.',
		10  => 'پنل غیرفعال یا مسدود است.',
		11  => 'شماره گیرنده در لیست سیاه است.',
		12  => 'مدارک پنل ناقص است.',
		14  => 'ارسال لینک از این سرشماره مجاز نیست.',
		15  => 'ارسال به چند شماره بدون لغو11 مجاز نیست.',
		35  => 'شماره در لیست سیاه مخابرات است.'
	];


	/*
	* Gateway title	
	*/
	public static function name(): string {
		return 'ملی پیامک';
	}

	/*
	* Gateway parameters
	*/
	public static function options() {
		return [
			'username' => 'نام کاربری',
			'password' => 'پسورد',
		];
	}

	public static function get_credit( string $username, string $password ) {
		try {
			$client  = new SoapClient( "http://api.payamak-panel.com/post/Users.asmx?wsdl", [ 'encoding' => 'UTF-8' ] );
			$request = [ 'username' => $username, 'password' => $password ];
			$result  = $client->GetUserCredit2( $request )->GetUserCredit2Result;

			return is_numeric( $result ) ? (int) $result : 0;
		} catch ( SoapFault $e ) {
			return 'خطا در دریافت موجودی: ' . $e->getMessage();
		}
	}

	/*
	* Gateway action
	*/
	public static function process( $options, $action, $from, $to, $message ): string {
		if ( ! extension_loaded( 'soap' ) ) {
			return 'ماژول Soap بر روی هاست شما فعال نمی باشد .';
		}

		$username = trim( $options['username'] );
		$password = trim( $options['password'] );

		if ( empty( $username ) || empty( $password ) || empty( $message ) ) {
			return false;
		}

		$credit = self::get_credit( $username, $password );
		if ( is_numeric( $credit ) && $credit < 100000 ) {
			add_action( 'admin_notices', function () use ( $credit ) {
				echo '<div class="notice notice-warning"><p><strong>پیامک گرویتی فرمز فارسی:</strong> موجودی پنل پیامک کمتر از 100,000 ریال است (موجودی فعلی: ' . number_format( $credit ) . ' ریال).</p></div>';
			} );
		}


		$to = self::normalize_numbers( $to );

		$username = $options['username'];
		$password = $options['password'];

		ini_set( "soap.wsdl_cache_enabled", "0" );

		// Detect pattern-based message
		if ( str_contains( $message, '@' ) && str_contains( $message, '##' ) ) {
			return self::send_pattern( $username, $password, $from, $to, $message );
		}

		return self::send_simple( $username, $password, $from, $to, $message );

	}

	public static function send_simple( $username, $password, $from, $to, $message ) {
		try {
			$client = new SoapClient( "https://api.payamak-panel.com/post/Send.asmx?wsdl", [
				'encoding'     => 'UTF-8',
				'cache_wsdl'   => WSDL_CACHE_MEMORY,
				'compression'  => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
				'soap_version' => SOAP_1_2,
				'keep_alive'   => true,
				'exceptions'   => true,
				'features'     => SOAP_WAIT_ONE_WAY_CALLS,
				'trace'        => true
			] );

			$params = [
				'username' => $username,
				'password' => $password,
				'from'     => $from,
				'to'       => $to,
				'text'     => iconv( 'UTF-8', 'UTF-8//TRANSLIT', $message ),
				'isflash'  => false,
				'udh'      => '',
				'recId'    => [ 0 ],
				'status'   => 0,
			];

			$result = $client->SendSms( $params )->SendSmsResult;

			return $result == 1 ? 'OK' : ( self::ERRORS[ $result ] ?? $result );

		} catch ( SoapFault $ex ) {
			return $ex->getMessage();
		}
	}

	public static function send_pattern( $username, $password, $from, $to, $message ) {

		$parts     = explode( '@', $message );
		$text_data = array_pop( $parts );
		$body_id   = array_pop( $parts );
		$params    = explode( '##', $text_data );
		$key       = array_pop( $params );

		if ( trim( $key ) === 'shared' && count( $to ) < 5 ) {
			// Shared pattern send to each recipient
			try {
				foreach ( $to as $mobile ) {
					$client   = new SoapClient( "https://api.payamak-panel.com/post/send.asmx?wsdl", [ 'encoding' => 'UTF-8' ] );
					$response = $client->SendByBaseNumber2( [
						'username' => $username,
						'password' => $password,
						'text'     => reset( $params ),
						'to'       => $mobile,
						'bodyId'   => $body_id,
					] )->SendByBaseNumber2Result;

					if ( $response <= 20 ) {
						return self::ERRORS[ $response ] ?? $response;
					}
				}

				return 'OK';

			} catch ( SoapFault $ex ) {
				return $ex->getMessage();
			}

		} else {
			// Fallback to standard simple send
			return self::send_simple( $username, $password, $from, $to, $message );
		}
	}

}

