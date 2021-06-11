<?php declare(strict_types=1);

namespace Tribe\Storage\Cache;

/**
 * Simple Cache Interface.
 *
 * @package Tribe\Storage\Cache
 */
interface Cache {

	/**
	 * Get a cache item by key.
	 *
	 * @param  string  $key  Key to retrieve.
	 *
	 * @return mixed|null Returns the value or null if not found.
	 */
	public function get( string $key );

	/**
	 * Set a cache key value.
	 *
	 * @param  string  $key    Key to set
	 * @param  mixed   $value  Value to set.
	 * @param  int     $ttl    Number of seconds the item is allowed to live. Set
	 *                         to 0 to allow an unlimited lifetime.
	 */
	public function set( string $key, $value, int $ttl = 0 ): void;

	/**
	 * Remove a cache key.
	 *
	 * @param  string  $key  Key to remove.
	 */
	public function remove( string $key ): void;

}
