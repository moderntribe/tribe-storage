<?php declare(strict_types=1);

namespace Tribe\Storage;

use Exception;
use Intervention\Image\ImageManager;
use Jhofm\FlysystemIterator\Plugin\IteratorPlugin;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Plugin\ForcedRename;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Throwable;
use Tribe\Storage\Adapters\Azure_Adapter;
use Tribe\Storage\Adapters\Cached_Adapters\Transient;
use Tribe\Storage\Adapters\Local_Adapter;
use Tribe\Storage\Cache\Cache;
use Tribe\Storage\Cache\Lru;
use Tribe\Storage\Stream_Wrappers\Identity\Fallback_Identifier;
use Tribe\Storage\Stream_Wrappers\Identity\Identifier;
use Tribe\Storage\Stream_Wrappers\Identity\Posix_Identifier;
use Tribe\Storage\Stream_Wrappers\StreamWrapper;

/**
 * PHP-DI container configuration.
 */
$tribe_storage_config = [

	/**
	 * The path to this file, useful for including assets.
	 */
	'plugin_file'           => static function () {
		return __FILE__;
	},

	/**
	 * Implement a custom WordPress adapter cache.
	 *
	 * @see https://flysystem.thephpleague.com/v1/docs/advanced/caching/
	 */
	'AdapterCache'          => static function () {
		$key        = (string) apply_filters( 'tribe/storage/config/cache/key', 'flysystem_cache_' . get_current_blog_id() );
		$expiration = (int) apply_filters( 'tribe/storage/config/cache/expiration', 14400 );
		$force_db   = (bool) apply_filters( 'tribe/storage/config/cache/force_db', true );

		return new Transient( $key, $expiration, $force_db );
	},

	/**
	 * The local disk adapter.
	 *
	 * For local file storage, we always want the server path to wp-content/uploads so
	 * sub sites with site/<id> media are uploaded to the proper path.
	 */
	Local_Adapter::class    => static function () {

		if ( is_multisite() ) {
			$current_blog_id = defined( 'BLOG_ID_CURRENT_SITE' ) ? BLOG_ID_CURRENT_SITE : 1;
			switch_to_blog( $current_blog_id );
			$dir = wp_get_upload_dir()['basedir'];
			restore_current_blog();
		} else {
			$dir = wp_get_upload_dir()['basedir'];
		}

		/**
		 * Get the default WordPress upload directory
		 *
		 * @param  string  $upload_dir  The path to the WordPress upload directory.
		 */
		$upload_dir = apply_filters( 'tribe/storage/local_adapter_uploads_dir', $dir );

		return new Local_Adapter( $upload_dir );
	},

	/**
	 * The Azure blob storage adapter.
	 */
	Azure_Adapter::class    => static function () {
		$defines = [
			defined( 'MICROSOFT_AZURE_ACCOUNT_NAME' ),
			defined( 'MICROSOFT_AZURE_ACCOUNT_KEY' ),
			defined( 'MICROSOFT_AZURE_CONTAINER' ),
		];

		if ( in_array( false, $defines, true ) ) {
			throw new RuntimeException(
				sprintf(
					'Warning: Missing required defines for the Azure Adapter: %s. Falling back to the Local Storage Adapter.',
					Azure_Adapter::class
				)
			);
		}

		return new Azure_Adapter( MICROSOFT_AZURE_ACCOUNT_NAME, MICROSOFT_AZURE_ACCOUNT_KEY, MICROSOFT_AZURE_CONTAINER, [
			'http' => [
				'stream' => true,
			],
		] );
	},

	/**
	 * Use the Local Filesystem Adapter unless it's overridden.
	 */
	AdapterInterface::class => static function ( ContainerInterface $c ) {

		// Try to load a user defined adapter, with a fallback to the local adapter.
		try {
			$adapter = $c->get( defined( 'TRIBE_STORAGE_ADAPTER' ) ? TRIBE_STORAGE_ADAPTER : '' )->get();
		} catch ( Throwable $e ) {
			$exception = new Exception( 'TRIBE_STORAGE_ADAPTER not defined or contains an invalid adapter path. Falling back to the Local Adapter.' );

			// Throw the exception when WP_DEBUG is enabled.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( $exception->getMessage() );
			}

			// Capture the exception to display in the admin if on a production system.
			add_action( 'admin_init', static function () use ( $exception ): void {
				do_action( 'tribe/storage/adapter_error', $exception );
			} );

			$adapter = $c->get( Local_Adapter::class )->get();
		}

		/**
		 * Allow an additional override of the Flysystem adapter.
		 *
		 * @param \League\Flysystem\AdapterInterface $adapter The Flysystem Adapter instance.
		 */
		$adapter = apply_filters( 'tribe/storage/flysystem_adapter', $adapter );

		// Load the local adapter if the bridge failed.
		if ( empty( $adapter ) ) {
			$adapter = $c->get( Local_Adapter::class )->get();
		}

		// Allow users to disable the adapter cache.
		if ( defined( 'TRIBE_STORAGE_NO_CACHE' ) && TRIBE_STORAGE_NO_CACHE ) {
			return $adapter;
		}

		/**
		 * Filter the Flysystem cache.
		 *
		 * @param  \League\Flysystem\Cached\Storage\AbstractCache The current cache strategy.
		 */
		$cache = apply_filters( 'tribe/storage/cache', $c->get( 'AdapterCache' ) );

		return new CachedAdapter( $adapter, $cache );
	},

	/**
	 * Stream Wrapper Cache
	 */
	Cache::class            => static function () {
		return new Lru();
	},

	Identifier::class       => static function () {
		return extension_loaded( 'posix' ) ? new Posix_Identifier() : new Fallback_Identifier();
	},

	/**
	 * Register Flysystem's filesystem, with a PHP stream wrapper.
	 *
	 * This must happen here before any wp_upload_dir() calls.
	 */
	Filesystem::class       => static function ( ContainerInterface $c ) {
		/**
		 * The name of the stream, e.g. will be fly://
		 *
		 * @param  string  $protocol  The name of the stream without a scheme.
		 */
		$protocol = (string) apply_filters( 'tribe/storage/stream_name', 'fly' );

		$filesystem = new Filesystem( $c->get( AdapterInterface::class ), ['visibility' => 'public'] );

		// Add filesystem plugins
		$filesystem->addPlugin( new IteratorPlugin() );
		$filesystem->addPlugin( new ForcedRename() );

		/**
		 * Customize the Stream Wrapper cache
		 *
		 * @param \Tribe\Storage\Cache\Cache $cache
		 */
		$cache = apply_filters( 'tribe/storage/stream/cache', $c->get( Cache::class ) );

		StreamWrapper::register( $filesystem, $c->get( Identifier::class ), $cache, $protocol );

		return $filesystem;
	},

	/**
	 * Create an ImageManager instance using an optional user defined driver or fallback to the Imagick driver.
	 *
	 * @throws \Intervention\Image\Exception\NotSupportedException
	 */
	ImageManager::class     => static function () {
		$driver = defined( 'TRIBE_STORAGE_IMAGE_EDITOR' ) ? TRIBE_STORAGE_IMAGE_EDITOR : 'imagick';

		return new ImageManager( [ 'driver' => $driver ] );
	},
];

/**
 * Filter the entire container configuration to provide custom adapters etc.
 *
 * @param  array  $tribe_storage_config  The PHP-DI configuration.
 */
return apply_filters( 'tribe/storage/config', $tribe_storage_config );
