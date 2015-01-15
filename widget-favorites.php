<?php
/**
 * Plugin Name: Widget Favorites
 * Plugin URI: https://github.com/xwp/wp-widget-favorites
 * Description: Store revisions of widget instances for re-use.
 * Version: 0.1.1
 * Author:  XWP
 * Author URI: https://xwp.co/
 * License: GPLv2+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: widget-favorites
 * Domain Path: /languages
 *
 * Copyright (c) 2014 XWP (https://xwp.co/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

if ( version_compare( phpversion(), '5.3', '>=' ) ) {
	require __DIR__ . '/php/class-plugin.php';
	$class_name = '\WidgetFavorites\Plugin';
	$GLOBALS['widget_favorites_plugin'] = new $class_name();
} else {
	function widget_favorites_php_version_error() {
		printf( '<div class="error"><p>%s</p></div>', esc_html__( 'Widget Favorites plugin error: Your version of PHP is too old to run this plugin. You must be running PHP 5.3 or higher.', 'widget-favorites' ) );
	}
	if ( defined( 'WP_CLI' ) ) {
		WP_CLI::warning( __( 'Widget Favorites plugin error: Your PHP version is too old. You must have 5.3 or higher.', 'widget-favorites' ) );
	} else {
		add_action( 'admin_notices', 'widget_favorites_php_version_error' );
	}
}
