<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFPersian_SMS_Feeds {

	public static function show_feeds_table() {
		?>
        <h3>
            <span><?php echo esc_html( 'اطلاع رسانی پیامکی فرم های گرویتی' ) ?></span>
        </h3>
        <form method="post">
			<?php
			$list_table = new GFPersian_SMS_Feeds_List();
			$list_table->prepare_items();
			$list_table->display();
			?>
        </form>

		<?php
	}
}

new GFPersian_SMS_Feeds();