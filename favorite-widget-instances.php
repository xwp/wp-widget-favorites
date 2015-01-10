<?php
/**
 * Plugin Name: Favorite Widget Instances
 * Plugin URI: https://github.com/xwp/wp-favorite-widget-instances
 * Description: ...
 * Version: 0.1
 * Author:  XWP
 * Author URI: https://xwp.co/
 * License: GPLv2+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: favorite-widget-instances
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
	$class_name = '\FavoriteWidgetInstances\Plugin';
	$GLOBALS['favorite_widget_instances_plugin'] = new $class_name();
} else {
	function favorite_widget_instances_php_version_error() {
		printf( '<div class="error"><p>%s</p></div>', esc_html__( 'Favorite Widget Instances plugin error: Your version of PHP is too old to run this plugin. You must be running PHP 5.3 or higher.', 'favorite-widget-instances' ) );
	}
	if ( defined( 'WP_CLI' ) ) {
		WP_CLI::warning( __( 'Favorite Widget Instances plugin error: Your PHP version is too old. You must have 5.3 or higher.', 'favorite-widget-instances' ) );
	} else {
		add_action( 'admin_notices', 'favorite_widget_instances_php_version_error' );
	}
}
