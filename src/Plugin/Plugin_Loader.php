<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin;

use Exception;
use Tribe\Storage\Providers\Providable;

/**
 * Register Tribe Storage plugin definitions and service providers.
 *
 * Plugins will call this instance directly and add
 * their own definitions/providers.
 *
 * @package Tribe\Storage\Plugin
 */
class Plugin_Loader {

	/**
	 * @var \Tribe\Storage\Plugin\Plugin_Loader
	 */
	private static $instance;

	/**
	 * @var \Tribe\Storage\Plugin\Definition_Provider[]
	 */
	private static $definitions = [];

	/**
	 * An array of fully qualified class names
	 * implementing the Providable interface.
	 *
	 * @see Providable
	 *
	 * @var string[]
	 */
	private static $providers = [];

	private function __construct() {
	}

	public static function get_instance(): Plugin_Loader {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Add a plugin's container definition provider.
	 *
	 * @param  \Tribe\Storage\Plugin\Definition_Provider  $provider
	 */
	public function add_definition_provider( Definition_Provider $provider ): void {
		self::$definitions[] = $provider;
	}

	/**
	 * Get all plugin definition providers
	 *
	 * @return \Tribe\Storage\Plugin\Definition_Provider[]
	 */
	public function definition_providers(): array {
		return self::$definitions;
	}

	/**
	 * Pass a fully qualified class name
	 * implementing the Providable interface.
	 *
	 * @param  string  $provider
	 *
	 * @see Providable
	 */
	public function add_service_provider( string $provider ): void {
		self::$providers[] = $provider;
	}

	/**
	 * An array of fully qualified class names
	 * implementing the Providable interface.
	 *
	 * @see Providable
	 *
	 * @return string[]
	 */
	public function service_providers(): array {
		return self::$providers;
	}

	private function __clone() {
	}

	/**
	 * @throws \Exception
	 */
	public function __wakeup(): void {
		throw new Exception( 'Singletons do not support unserialize' );
	}

}
