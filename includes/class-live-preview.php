<?php

defined( 'ABSPATH' ) || exit;

class GFPersian_LivePreview extends GFPersian_Core {

	/**
	 * @var string $post_type
	 */
	private string $post_type = 'gf_live_preview';

	/**
	 * @var array $_args
	 */
	private array $_args;

	public function __construct( $args = [] ) {

		if ( $this->option( 'live_preview', '1' ) != '1' ) {
			return;
		}

		$this->_args = wp_parse_args( $args, [
			'id'          => 0,
			'title'       => true,
			'description' => true,
			'ajax'        => true
		] );

		add_action( 'init', [ $this, 'register_preview_post_type' ] );
		add_action( 'wp', [ $this, 'maybe_load_preview_functionality' ] );
		add_action( 'admin_footer', [ $this, 'display_preview_link' ] );
		add_filter( 'gform_form_actions', [ $this, 'gform_form_actions' ], 10, 2 );
	}

	/**
	 * Register the preview post type for GForm
	 *
	 * @action init
	 *
	 * @return void
	 */
	public function register_preview_post_type(): void {

		$args = [
			'label'              => 'پیش نمایش زنده',
			'description'        => 'پیش نمایش فرم در فرانت اند به صورت یک پست تایپ مجازی',
			'public'             => false,
			'publicly_queryable' => true,
			'has_archive'        => true,
			'can_export'         => false,
			'supports'           => false
		];

		register_post_type( $this->post_type, $args );

		// create preview post
		$preview_post = get_posts( [ 'post_type' => $this->post_type ] );
		if ( empty( $preview_post ) ) {
			wp_insert_post( [
				'post_type'   => $this->post_type,
				'post_title'  => 'پیش نمایش زنده',
				'post_status' => 'publish'
			] );
		}

	}

	/**
	 * Inject preview content to the preview post type
	 *
	 * @action wp
	 *
	 * @retrun void
	 */
	public function maybe_load_preview_functionality(): void {

		if ( ! is_post_type_archive( $this->post_type ) ) {
			return;
		}

		add_filter( 'template_include', [ $this, 'load_preview_template' ] );
		add_filter( 'the_content', [ $this, 'modify_preview_post_content' ] );

		// Get the main query object safely
		$query = get_queried_object();

		if ( $query instanceof WP_Query && ! empty( $query->posts ) ) {

			foreach ( $query->posts as $post ) {
				$post->post_content = $this->get_shortcode();
			}

		}

	}

	/**
	 * Add preview option to single row form action in GForm archive
	 *
	 * @filter gform_form_actions
	 *
	 * @param array $form_actions
	 * @param int $form_id
	 *
	 * @return array
	 */
	public function gform_form_actions( array $form_actions, int $form_id ): array {
		if ( ! empty( $_GET['trash'] ) && $_GET['trash'] == 1 ) {
			return $form_actions;
		}

		$capabilities = [
			'gravityforms_view_entries',
			'gravityforms_edit_entries',
			'gravityforms_delete_entries'
		];

		$ajax_true_url  = get_bloginfo( "wpurl" ) . '/?post_type=' . $this->post_type . '&id=' . $form_id;
		$ajax_false_url = get_bloginfo( "wpurl" ) . '/?post_type=' . $this->post_type . '&ajax=false&id=' . $form_id;

		$sub_menu_items = [];

		$sub_menu_items[] = [
			'url'          => $ajax_true_url,
			'label'        => 'حالت ایجکس فعال',
			'capabilities' => $capabilities
		];

		$sub_menu_items[] = [
			'url'          => $ajax_false_url,
			'label'        => 'حالت ایجکس غیرفعال',
			'capabilities' => $capabilities
		];

		$form_actions['live_preview'] = [
			'label'          => 'پیش نمایش زنده',
			'icon'           => '<i class="fa fa-cogs fa-lg"></i>',
			'title'          => 'پیش نمایش زنده',
			'url'            => '',
			'menu_class'     => 'gf_form_toolbar_settings',
			'link_class'     => 'gf_toolbar_active',
			'sub_menu_items' => $sub_menu_items,
			'capabilities'   => $capabilities,
			'priority'       => 650,
		];

		return $form_actions;
	}

	/**
	 * Set preview link elements
	 *
	 * @action admin_footer
	 *
	 * @return void
	 */
	public function display_preview_link() {

		if ( ! in_array( rgget( 'page' ), [
				'gf_edit_forms',
				'gf_entries'
			] ) || ! rgget( 'id' ) || apply_filters( 'gf_live_preview_page', false ) ) {
			return;
		}

		$form_id        = apply_filters( 'gf_live_preview_id', rgget( 'id' ) );
		$ajax_true_url  = get_bloginfo( 'wpurl' ) . '/?post_type=' . $this->post_type . '&id=' . $form_id;
		$ajax_false_url = get_bloginfo( 'wpurl' ) . '/?post_type=' . $this->post_type . '&ajax=false&id=' . $form_id;
		?>

        <script type="text/javascript">
            (function ($) {
                $('<li class="gf_form_toolbar_preview">' +
                    '<a style="position:relative" id="gf-live-preview" target="_blank" href="<?php echo esc_url( $ajax_true_url ); ?>" class="" >' +
                    '<i class="fa fa-eye" style="position: absolute; text-shadow: 0px 0px 5px rgb(255, 255, 255); z-index: 99; line-height: 7px; left: 0px; font-size: 9px; background-color: rgb(243, 243, 243);"></i>' +
                    '<i class="fa fa-file-o" style="margin-left: 5px; line-height: 12px; font-size: 18px; position: relative;"></i>' +
                    'پیش نمایش زنده' +
                    '</a>' +
                    '<div class="gf_submenu"><ul>' +
                    '<li class=""><a target="_blank" href="<?php echo esc_url( $ajax_true_url ); ?>">حالت ایجکس فعال</a></li>' +
                    '<li class=""><a target="_blank" href="<?php echo esc_url( $ajax_false_url ); ?>">حالت ایجکس غیرفعال</a></li>' +
                    '</ul></div>' +
                    '</li>')
                    .insertAfter('li.gf_form_toolbar_preview');
            })(jQuery);
        </script>
		<?php
	}

	/**
	 * Force WordPress to use page template
	 *
	 * @filter template_include
	 *
	 * @param string $template The template to look for.
	 *
	 * @return string
	 */
	public function load_preview_template( string $template ): string {
		return get_page_template();
	}

	/**
	 * Force WordPress to set GForm shortcode in preview post type
	 *
	 * @filter the_content
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function modify_preview_post_content( string $content ): string {
		return $this->get_shortcode();
	}

	/**
	 * Get GForm shortcode to preview
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	public function get_shortcode( array $args = [] ): string {

		if ( ! is_user_logged_in() ) {
			return '<p>برای دسترسی به این قسمت باید لاگین شوید.</p>' . wp_login_form( [ 'echo' => false ] );
		}

		if ( ! GFCommon::current_user_can_any( 'gravityforms_preview_forms' ) ) {
			return 'شما مجوز دسترسی به این بخش را ندارید.';
		}

		if ( empty( $args ) ) {
			$args = $this->get_shortcode_parameters_from_query_string();
		}

		extract( wp_parse_args( $args, $this->_args ) );

		$title       = ! empty( $title ) && $title === true ? 'true' : 'false';
		$description = ! empty( $description ) && $description === true ? 'true' : 'false';
		$ajax        = ! empty( $ajax ) && $ajax === true ? 'true' : 'false';
		$id          = ! empty( $id ) && $id > 0 ? $id : 0;

		return "[gravityform id='$id' title='$title' description='$description' ajax='$ajax']";
	}

	/**
	 * Set default GForm shortcode parameters
	 *
	 * @return array
	 */
	public function get_shortcode_parameters_from_query_string(): array {
		return array_filter( [
			'id'          => rgget( 'id' ),
			'title'       => rgget( 'title' ),
			'description' => rgget( 'description' ),
			'ajax'        => rgget( 'ajax' )
		] );
	}

}

new GFPersian_LivePreview();
