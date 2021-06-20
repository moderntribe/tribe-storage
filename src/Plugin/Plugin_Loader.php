<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin;

use Exception;

/**
 * Register Tribe Storage plugin definitions.
 *
 * Plugins will call this instance directly and add
 * their own definitions.
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

	private function __construct() {
	}

	public static function get_instance(): Plugin_Loader {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	public function add_definitions( Definition_Provider $provider ): void {
		self::$definitions[] = $provider;
	}

	/**
	 * @return \Tribe\Storage\Plugin\Definition_Provider[]
	 */
	public function get_definitions(): array {
		return self::$definitions;
	}

	private function __clone() {
	}

	/**
	 * @throws \Exception
	 */
	protected function __wakeup(): void {
		throw new Exception( 'Singletons do not support unserialize' );
	}

}
