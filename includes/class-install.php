<?php

defined( 'ABSPATH' ) || exit;

use Illuminate\Database\Schema\Blueprint;

class GFPersian_Install extends \Nabik_Net_Install {

	public function tasks() {
		self::create_tables();
	}

	public static function create_tables() {

		if ( ! Nabik_Net_Database::Schema()->hasTable( 'gf_sms_sent' ) ) {

			Nabik_Net_Database::Schema()->create( 'gf_sms_sent', function ( Blueprint $table ) {
				$table->id();
				$table->integer( 'form_id' )->unsigned();
				$table->string( 'entry_id', 20 );
				$table->dateTime( 'date' )->nullable();
				$table->string( 'sender', 20 );
				$table->string( 'reciever', 255 );
				$table->text( 'message' );

			} );

		}

		if ( ! Nabik_Net_Database::Schema()->hasTable( 'gf_sms_verification' ) ) {

			Nabik_Net_Database::Schema()->create( 'gf_sms_verification', function ( Blueprint $table ) {
				$table->id();
				$table->integer( 'form_id' )->unsigned();
				$table->mediumInteger( 'entry_id' )->unsigned();
				$table->mediumInteger( 'try_num' )->unsigned();
				$table->mediumInteger( 'sent_num' )->unsigned();
				$table->string( 'mobile', 20 );
				$table->string( 'code', 250 )->nullable();
				$table->boolean( 'status' )->nullable();

				$table->index( 'form_id' );
			} );

		}

	}
}

new GFPersian_Install();
