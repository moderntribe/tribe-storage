<?php declare(strict_types=1);

/**
 * Plugin Name:        Tribe Storage
 * Plugin URI:         https://tri.be
 * Description:        Replace the WordPress filesystem with Flypress adapters. Allows the use of multiple cloud storage providers.
 * Author:             Modern Tribe
 * Author URI:         https://tri.be
 * Text Domain:        tribe-storage
 * Version:            2.3.0
 * Requires at least:  5.6
 * Requires PHP:       7.3
 */

use DI\ContainerBuilder;
use Tribe\Storage\Core;
use Tribe\Storage\Plugin\Plugin_Loader;

// Require the vendor folder via multiple locations
$autoloaders = [
	trailingslashit( WP_CONTENT_DIR ) . '../vendor/autoload.php',
	trailingslashit( WP_CONTENT_DIR ) . 'vendor/autoload.php',
	trailingslashit( __DIR__ ) . 'vendor/autoload.php',
];

$autoload = current( array_filter( $autoloaders, 'file_exists' ) );

require_once $autoload;

// Start the core plugin
add_action( 'plugins_loaded', static function (): void {
	tribe_storage()->init();
}, 1, 0 );

/**
 * Shorthand to get the instance of our main core plugin class.
 *
 * @return mixed
 *
 * @throws \Exception
 */
function tribe_storage(): Core {
	$builder       = new ContainerBuilder();
	$plugin_loader = Plugin_Loader::get_instance();

	// Load plugin container definitions
	foreach ( $plugin_loader->definition_providers() as $definition_provider ) {
		$builder->addDefinitions( $definition_provider->get_definitions() );
	}

	$builder->addDefinitions( __DIR__ . '/config.php' );
	$container = $builder->build();

	return Core::instance( $container, $plugin_loader );
}
