<?php

namespace WidgetFavorites;

/**
 * Main plugin bootstrap file.
 */
class Plugin {

	/**
	 * @const string
	 */
	const POST_TYPE = 'favorite_widget';

	/**
	 * @var array
	 */
	public $config = array();

	/**
	 * @var string
	 */
	public $slug;

	/**
	 * @var string
	 */
	public $dir_path;

	/**
	 * @var string
	 */
	public $dir_url;

	/**
	 * @var string
	 */
	protected $autoload_class_dir = 'php';

	/**
	 * @var Ajax_API
	 */
	public $ajax_api;

	/**
	 * @param array $config
	 */
	public function __construct( $config = array() ) {

		$location = $this->locate_plugin();
		$this->slug = $location['dir_basename'];
		$this->dir_path = $location['dir_path'];
		$this->dir_url = $location['dir_url'];

		$default_config = array(
			'capability' => 'edit_theme_options',
		);

		$this->config = array_merge( $default_config, $config );

		add_action( 'init', array( $this, 'init' ), 100 );
	}

	/**
	 * @action init
	 */
	public function init() {
		spl_autoload_register( array( $this, 'autoload' ) );
		$this->config = apply_filters( 'widget_favorites_plugin_config', $this->config, $this );
		$this->register_post_type();

		add_action( 'sidebar_admin_setup', array( $this, 'enqueue_scripts' ) );

		$this->ajax_api = new Ajax_API( $this );
		// @todo Class for managing scripts
		// @todo Class for managing the post type
	}

	/**
	 * @return \WP_Customize_Manager
	 */
	public function get_customize_manager() {
		if ( empty( $GLOBALS['wp_customize'] ) ) {
			require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
			$GLOBALS['wp_customize'] = new \WP_Customize_Manager(); // wpcs: global override ok
		}
		return $GLOBALS['wp_customize'];
	}

	/**
	 * @action init
	 */
	public function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name' => __( 'Favorite Widgets', 'widget-favorites' ),
				'singular_name' => __( 'Favorite Widget', 'widget-favorites' ),
			),
			'public' => false,
			'capability_type' => self::POST_TYPE,
			'supports' => array( 'title' ),
			'map_meta_cap' => true,
		) );

		// Now override the caps to all be the cap defined in the config
		$post_type_object = get_post_type_object( self::POST_TYPE );
		foreach ( array_keys( (array) $post_type_object->cap ) as $cap ) {
			$post_type_object->cap->$cap = $this->config['capability'];
		}
	}

	/**
	 * @return object
	 */
	public function get_post_type_object() {
		return get_post_type_object( self::POST_TYPE );
	}

	/**
	 * Add the script needed to drive the UI
	 *
	 * @action sidebar_admin_setup
	 */
	public function enqueue_scripts() {
		if ( ! current_user_can( $this->config['capability'] ) ) {
			return;
		}

		$handle = 'widget-favorites';
		$src = $this->dir_url . 'js/widget-favorites.js';
		$deps = array( 'jquery', 'backbone', 'customize-controls', 'wp-util' );
		wp_enqueue_script( $handle, $src, $deps );

		/**
		 * @var \WP_Scripts $wp_scripts
		 */
		global $wp_scripts;

		$wp_scripts->add_data(
			$handle,
			'data',
			sprintf( 'var _widgetFavorites_exports = %s;', wp_json_encode( $this->get_script_exports() ) )
		);

		add_action( 'customize_controls_print_footer_scripts', array( $this, 'boot_scripts' ), 100 );
	}

	/**
	 * @return array
	 */
	public function get_script_exports() {
		$exports = array(
			'l10n' => array(
				'tooltip_show_favorites' => __( 'Show favorites', 'widget-favorites' ),
				'tooltip_hide_favorites' => __( 'Hide favorites', 'widget-favorites' ),
				'heading' => __( 'Favorites:', 'widget-favorites' ),
				'tooltip_load_btn' => __( 'Load widget instance, overriding the current one.', 'widget-favorites' ),
				'tooltip_save_btn' => __( 'Save the current widget instance, overwriting any existing saved instance of the supplied name.', 'widget-favorites' ),
				'create_new_option_label' => __( '--', 'widget-favorites' ),
				'untitled' => __( '(Untitled)', 'widget-favorites' ),
				'tooltip_widget_instance_option' => __( 'Created by %1$s at %2$s. Last modified %3$s.', 'widget-favorites' ),
			),
			'nonce' => wp_create_nonce( 'widget_favorites' ),
			'ajaxAction' => Ajax_API::AJAX_ACTION,
		);
		return $exports;
	}

	/**
	 * @var bool
	 */
	public $printed_templates = false;

	/**
	 * @return bool
	 */
	public function print_templates() {
		if ( $this->printed_templates ) {
			return false;
		}
		$this->printed_templates = true;

		?>
		<style>
		.widget-favorites-ui {
			margin-top: 8px;
			background: #F9F9F9;
			border: 1px solid #DFDFDF;
			padding: 12px 10px;
			position: relative;
		}
		.widget-favorites-ui .spinner {
			position: absolute;
			top: 10px;
			right: 5px;
			display: none !important; /* needed due to customize-widgets.js */
		}
		.widget-favorites-ui .spinner.visible {
			display: inline-block !important;
		}

		.widget-favorites-ui > h4 {
			display: block;
			font-size: 14px;
			line-height: 24px;
			font-weight: 600;
			margin-top: 0;
			margin-bottom: 5px;
		}
		.widget-favorites-control-row {
			display: -webkit-flex;
			display: -ms-flexbox;
			display: -webkit-flex;
			display: flex;
			-webkit-box-direction: normal;
			-webkit-box-orient: horizontal;
			-ms-flex-direction: row;
			-webkit-flex-direction: row;
			flex-direction: row;

			flex-wrap: nowrap;
		}
		.widget-favorites-control-row .dashicons {
			vertical-align: middle;
		}
		.widget-favorites-control-row > input,
		.widget-favorites-control-row > select {
			flex: 10 0;
		}
		.widget-favorites-control-row > .button-secondary {
			flex: 1 0 20px;
			margin: 0;
			padding: 0;
		}
		</style>

		<script type="text/html" id="tmpl-widget-favorites-star">
			| <a class="widget-favorites-star" href="javascript:" title="{{ data.l10n.tooltip_show_favorites }}">&#x2605;</a>
		</script>

		<script type="text/html" id="tmpl-widget-favorites-ui">
			<div class="widget-favorites-ui">
				<h4>{{ data.l10n.heading }}</h4>
				<span class="spinner"></span>
				<div class="widget-favorites-control-row">
					<select class="widget-favorites-select"></select>
					<button type="button" class="button-secondary widget-favorites-load" title="{{ data.l10n.tooltip_load_btn }}"><span class="dashicons dashicons-download"></span></button>
				</div>
				<div class="widget-favorites-control-row">
					<input type="text" class="widget-favorites-save-name">
					<button type="button" class="button-secondary widget-favorites-save" title="{{ data.l10n.tooltip_save_btn }}"><span class="dashicons dashicons-upload"></span></button>
				</div>
			</div>
		</script>
		<?php
		return true;
	}

	/**
	 * @var bool
	 */
	public $booted_scripts = false;

	/**
	 * @return bool
	 */
	public function boot_scripts() {
		if ( $this->booted_scripts || ! did_action( 'customize_register' ) ) {
			return false;
		}
		$this->booted_scripts = true;

		wp_print_scripts( array( 'widget-favorites' ) );
		$this->print_templates();
		echo '<script> widgetFavorites.init(); </script>';
		return true;
	}

	/**
	 * @param \WP_Post|int $post
	 * @return array
	 */
	public function get_exported_data_from_post( $post ) {
		$post = get_post( $post );

		$sanitized_widget_setting = array();
		if ( $post->post_content ) {
			$sanitized_widget_setting = unserialize( $post->post_content );
		}
		$sanitized_widget_setting = $this->get_customize_manager()->widgets->sanitize_widget_js_instance( $sanitized_widget_setting );

		$data = array(
			'post_id' => $post->ID,
			'name' => $post->post_title,
			'src_widget_id' => get_post_meta( $post->ID, 'src_widget_id', true ),
			'sanitized_widget_setting' => $sanitized_widget_setting,
			'author_id' => intval( $post->post_author ),
			'author_display_name' => $post->post_author > 0 ? get_the_author_meta( 'display_name', $post->post_author ) : null,
			'datetime_created' => $post->post_date_gmt . ' +0000',
			'datetime_modified' => $post->post_modified_gmt . ' +0000',
		);
		return $data;
	}


	/**
	 * @return \ReflectionObject
	 */
	public function get_object_reflection() {
		static $reflection;
		if ( empty( $reflection ) ) {
			$reflection = new \ReflectionObject( $this );
		}
		return $reflection;
	}

	/**
	 * Autoload for classes that are in the same namespace as $this, and also for
	 * classes in the Twig library.
	 *
	 * @param  string $class
	 * @return void
	 */
	public function autoload( $class ) {
		if ( ! preg_match( '/^(?P<namespace>.+)\\\\(?P<class>[^\\\\]+)$/', $class, $matches ) ) {
			return;
		}
		if ( $this->get_object_reflection()->getNamespaceName() !== $matches['namespace'] ) {
			return;
		}
		$class_name = $matches['class'];

		$class_path = \trailingslashit( $this->dir_path );
		if ( $this->autoload_class_dir ) {
			$class_path .= \trailingslashit( $this->autoload_class_dir );
		}
		$class_path .= sprintf( 'class-%s.php', strtolower( str_replace( '_', '-', $class_name ) ) );
		if ( is_readable( $class_path ) ) {
			require_once $class_path;
		}
	}

	/**
	 * Version of plugin_dir_url() which works for plugins installed in the plugins directory,
	 * and for plugins bundled with themes.
	 *
	 * @throws \Exception
	 * @return array
	 */
	public function locate_plugin() {
		$reflection = new \ReflectionObject( $this );
		$file_name = $reflection->getFileName();
		$plugin_dir = preg_replace( '#(.*plugins[^/]*/[^/]+)(/.*)?#', '$1', $file_name, 1, $count );
		if ( 0 === $count ) {
			throw new \Exception( "Class not located within a directory tree containing 'plugins': $file_name" );
		}

		// Make sure that we can reliably get the relative path inside of the content directory
		$content_dir = trailingslashit( WP_CONTENT_DIR );
		if ( 0 !== strpos( $plugin_dir, $content_dir ) ) {
			throw new \Exception( 'Plugin dir is not inside of WP_CONTENT_DIR' );
		}
		$content_sub_path = substr( $plugin_dir, strlen( $content_dir ) );
		$dir_url = content_url( trailingslashit( $content_sub_path ) );
		$dir_path = $plugin_dir;
		$dir_basename = basename( $plugin_dir );
		return compact( 'dir_url', 'dir_path', 'dir_basename' );
	}

}
