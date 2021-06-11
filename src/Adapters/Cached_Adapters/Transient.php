<?php declare(strict_types=1);

namespace Tribe\Storage\Adapters\Cached_Adapters;

use InvalidArgumentException;
use League\Flysystem\Cached\Storage\AbstractCache;

/**
 * Cache adapter meta in WordPress.
 *
 * @package Tribe\Storage\Adapters\Cached_Adapters
 */
class Transient extends AbstractCache {

	/**
	 * @var string storage key
	 */
	protected $key;

	/**
	 * @var int seconds until cache expire, 0 for forever.
	 */
	protected $expire;

	/**
	 * @var bool whether to force transients to save to the db vs object cache
	 */
	protected $force_db;

	/**
	 * Transient constructor.
	 *
	 * @param  string  $key
	 * @param  int     $expire
	 * @param  false   $force_db
	 */
	public function __construct( string $key = 'flysystem', int $expire = 0, bool $force_db = true ) {
		if ( strlen( $key ) > 172 ) {
			throw new InvalidArgumentException( 'They key must be less than 172 characters long.' );
		}

		$this->key      = $key;
		$this->expire   = $expire;
		$this->force_db = $force_db;
	}

	/**
	 * Load data from the cache.
	 */
	public function load(): void {
		if ( $this->force_db ) {
			$using_external_cache = wp_using_ext_object_cache( false );
		}

		$contents = get_transient( $this->key );

		if ( $contents !== false ) {
			$this->setFromStorage( $contents );
		}

		if ( ! isset( $using_external_cache ) ) {
			return;
		}

		wp_using_ext_object_cache( $using_external_cache );
	}

	/**
	 * Save data into the cache.
	 */
	public function save(): void {
		$contents = $this->getForStorage();

		if ( $this->force_db ) {
			$using_external_cache = wp_using_ext_object_cache( false );
		}

		set_transient( $this->key, $contents, $this->expire );

		if ( ! isset( $using_external_cache ) ) {
			return;
		}

		wp_using_ext_object_cache( $using_external_cache );
	}

}
