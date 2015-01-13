<?php

namespace WidgetFavorites;

class ClassPluginTest extends \WP_UnitTestCase {

	/**
	 * @var Plugin
	 */
	public $plugin;

	function setUp() {
		parent::setUp();
		$user = wp_set_current_user( 1 ); // default admin user
		$this->assertTrue( $user->ID !== 0 );
		$this->plugin = $GLOBALS['widget_favorites_plugin'];
	}

	function test_constructed() {
		$this->assertInstanceOf( $this->get_namespaced_class_name( 'Plugin' ), $this->plugin );
	}

	function test_init() {
		$this->assertTrue( did_action( 'init' ) > 0 );
		// @todo test widget_favorites_plugin_config filter
		$this->assertTrue( current_user_can( $this->plugin->config['capability'] ) );
		$this->assertEquals( 10, has_action( 'sidebar_admin_setup', array( $this->plugin, 'enqueue_scripts' ) ) );
		$this->assertInstanceOf( $this->get_namespaced_class_name( 'Ajax_API' ), $this->plugin->ajax_api );
	}

	function test_get_customize_manager() {
		$this->assertInstanceOf( 'WP_Customize_Manager', $this->plugin->get_customize_manager() );
		$this->assertInstanceOf( 'WP_Customize_Widgets', $this->plugin->get_customize_manager()->widgets );
	}

	function test_register_post_type() {

		$post_type_obj = get_post_type_object( Plugin::POST_TYPE );
		$this->assertNotEmpty( $post_type_obj );
		foreach ( $post_type_obj->cap as $name => $value ) {
			$this->assertEquals( $this->plugin->config['capability'], $value );
		}
	}

	function test_get_post_type_object() {
		$post_type_obj = get_post_type_object( Plugin::POST_TYPE );
		$this->assertNotEmpty( $post_type_obj );
		$this->assertEquals( $post_type_obj, $this->plugin->get_post_type_object() );
	}

	function test_enqueue_scripts() {
		do_action( 'sidebar_admin_setup' );

		/**
		 * @var \WP_Scripts $wp_scripts
		 */
		global $wp_scripts;
		$handle = 'widget-favorites';
		$this->assertArrayHasKey( $handle, $wp_scripts->registered );
		$this->assertContains( $handle, $wp_scripts->queue );
		$this->assertStringEndsWith( 'js/widget-favorites.js', $wp_scripts->registered[ $handle ]->src );
		$this->assertTrue( has_action( 'customize_controls_print_footer_scripts', array( $this->plugin, 'boot_scripts' ) ) > 0 );
		$this->assertContains( 'var _widgetFavorites_exports =', $wp_scripts->get_data( $handle, 'data' ) );
	}

	function test_get_script_exports() {
		$exports = $this->plugin->get_script_exports();
		$this->assertArrayHasKey( 'l10n', $exports );
		$this->assertArrayHasKey( 'nonce', $exports );
		$this->assertArrayHasKey( 'ajaxAction', $exports );
		$this->assertInternalType( 'array', $exports['l10n'] );
	}

	function test_print_templates() {
		$this->plugin->printed_templates = false;
		ob_start();
		$this->assertTrue( $this->plugin->print_templates() );
		$output = ob_get_clean();

		$this->assertContains( '<script type="text/html" id="tmpl-widget-favorites-star">', $output );
		$this->assertContains( '<script type="text/html" id="tmpl-widget-favorites-ui">', $output );

		$this->assertFalse( $this->plugin->print_templates() ); // since it was already output, and $this->plugin->printed_templates is now true
	}

	function test_boot_scripts() {
		$this->plugin->printed_templates = false;
		$this->plugin->booted_scripts = false;

		$wp_customize = $this->plugin->get_customize_manager();
		add_action( 'customize_register', array( $wp_customize, 'register_controls' ), 9 ); // re-add
		do_action( 'customize_register', $wp_customize );

		ob_start();
		$this->assertTrue( $this->plugin->boot_scripts() );
		$output = ob_get_clean();

		$this->assertContains( 'widgetFavorites.init();', $output );
		$this->assertFalse( $this->plugin->boot_scripts() ); // since it was already output, and $this->plugin->printed_templates is now true
	}

	function test_get_exported_data_from_post() {
		$wp_customize = $this->plugin->get_customize_manager();
		$result = $this->plugin->ajax_api->create( array(
			'src_widget_id' => 'calendar-1',
			'sanitized_widget_setting' => $wp_customize->widgets->sanitize_widget_js_instance( array( 'title' => 'Hello World' ) ),
		) );

		$post_id = $result['post_id'];
		$data = $this->plugin->get_exported_data_from_post( $post_id );
		$this->assertInternalType( 'int', $data['post_id'] );
		$this->assertInternalType( 'string', $data['name'] );
		$this->assertInternalType( 'string', $data['src_widget_id'] );
		$this->assertArrayHasKey( 'sanitized_widget_setting', $data );
		$this->assertInternalType( 'array', $data['sanitized_widget_setting'] );
		$this->assertInternalType( 'int', $data['author_id'] );
		$this->assertArrayHasKey( 'author_display_name', $data );
		$this->assertInternalType( 'string', $data['datetime_created'] );
		$this->assertInternalType( 'string', $data['datetime_modified'] );
		$this->assertInternalType( 'int', strtotime( $data['datetime_created'] ) );
		$this->assertInternalType( 'int', strtotime( $data['datetime_modified'] ) );
	}

	function get_namespaced_class_name( $name ) {
		return __NAMESPACE__ . '\\' . $name;
	}
}
