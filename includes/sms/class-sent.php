<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFPersian_SMS_Sent {

	public static function show_sent_table() {

		$sent_table = new GFPersian_SMS_Sent_List_Table();

		echo '<div class="wrap">'; // Opening wrapper
		echo '<h2>پیامک های ارسال شده'; // Opening heading

		if ( isset( $_GET['id'] ) ) {
			$form_id = rgget( 'id' );
			$form    = RGFormsModel::get_form_meta( $form_id );
			if ( ! empty( $form ) ) {

				$form_edit_link = GFPersian_Core::get_form_edit_link( $form_id );
				echo sprintf( ' (<a target="_blank" href="%s">%s</a>) </h2>', esc_url( $form_edit_link ), esc_html( $form['title'] ) . " #" . $form_id );
			} else {
				echo '</h2>'; // Closing heading
			}
		} else {
			echo '</h2>'; // Closing
		}

		$sent_table->prepare_items();

		echo '<style type="text / css">';
		echo '.wp-list-table .column-id { width: 5%; }';
		echo '</style>';


		?>
		<form method="post">
			<input type="hidden" name="page" value="gfpersian_sent_table">
			<?php
			$sent_table->search_box( 'جستجو', 'search_id' );
			$sent_table->display();
			?>
		</form>

		</div><!--Closing wrapper-->
		<?php

	}
}

new GFPersian_SMS_Sent();