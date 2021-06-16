<?php declare(strict_types=1);

namespace Tribe\Storage;

use Psr\Container\ContainerInterface;
use Tribe\Storage\Providers\Tribe_Storage_Provider;

/**
 * The Core Plugin.
 *
 * @package Tribe\Storage\Uploads
 */
class Core {

	/**
	 * @var \Psr\Container\ContainerInterface|null
	 */
	protected $container = null;

	/**
	 * @var \Tribe\Storage\Core
	 */
	protected static $instance;

	/**
	 * @var \Tribe\Storage\Providers\Providable[]
	 */
	private $providers = [];

	/**
	 * Core constructor.
	 *
	 * @param  \Psr\Container\ContainerInterface  $container
	 */
	public function __construct( ContainerInterface $container ) {
		$this->container = $container;
	}

	/**
	 * Get an instance of Core.
	 *
	 * @param  \Psr\Container\ContainerInterface|null  $container
	 *
	 * @throws \Exception
	 *
	 * @return \Tribe\Storage\Core
	 */
	public static function instance( ?ContainerInterface $container = null ): Core {
		if ( ! isset( self::$instance ) ) {
			if ( empty( $container ) ) {
				throw new \Exception( 'You need to provide a PHP-DI container' );
			}

			$className      = self::class;
			self::$instance = new $className( $container );
		}

		return self::$instance;
	}

	/**
	 * Init the plugin.
	 */
	public function init(): void {
		$this->configure_service_providers();
		$this->load_service_providers();
	}

	public function container(): ?ContainerInterface {
		return $this->container;
	}

	/**
	 * Configure service providers.
	 */
	private function configure_service_providers(): void {
		$this->providers[] = $this->container->make( Tribe_Storage_Provider::class );
		$this->providers   = apply_filters( 'tribe/storage/providers', $this->providers );
	}

	/**
	 * Load service providers.
	 */
	private function load_service_providers(): void {
		foreach ( $this->providers as $provider ) {
			$provider->register();
		}
	}

}
