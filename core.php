<?php declare(strict_types=1);

/*
Plugin Name: Tribe Storage
Plugin URI: https://tri.be
Description: Replace the WordPress filesystem with Flypress adapters. Allows the use of multiple cloud storage providers.
Author:      Modern Tribe
Author URI:  https://tri.be
Version:     1.0.0
*/

use DI\ContainerBuilder;
use Tribe\Storage\Core;

// Require the main vendor autoloader via the composer merge plugin
$autoloaders = [
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
 * Shorthand to get the instance of our main core plugin class
 *
 * @return mixed
 *
 * @throws \Exception
 */
function tribe_storage(): Core {
	$builder = new ContainerBuilder();
	$builder->addDefinitions( __DIR__ . '/config.php' );
	$container = $builder->build();

	return Core::instance( $container );
}
