<?php declare(strict_types=1);

namespace Tribe\Storage\Cache;

use Countable;

/**
 * Last Recently Used Cache
 *
 * @package Tribe\Storage\Cache
 */
class Lru implements Cache, Countable {

	/**
	 * The maximum number of items in the cache
	 * before clearing begins to happen.
	 *
	 * @var int
	 */
	private $max;

	/**
	 * The items in the cache.
	 *
	 * @var \Tribe\Storage\Cache\Item[]
	 */
	private $items = [];

	public function __construct( int $max = 1000 ) {
		$this->max = $max;
	}

	/**
	 * @param  string  $key
	 *
	 * @return mixed|null
	 */
	public function get( string $key ) {
		if ( ! isset( $this->items[ $key ] ) ) {
			return null;
		}

		$item = $this->items[ $key ];

		unset( $this->items[ $key ] );

		// Fetch item if not expired.
		if ( ! $item->ttl || time() < $item->ttl ) {
			// Assign again at the end of the array.
			$this->items[ $key ] = $item;

			return $item->value;
		}

		return null;
	}

	/**
	 * @param  string  $key
	 * @param  mixed   $value
	 * @param  int     $ttl
	 */
	public function set( string $key, $value, int $ttl = 0 ): void {
		$ttl                 = $ttl ? time() + $ttl : 0;
		$this->items[ $key ] = $this->make_item( [ $value, $ttl ] );

		$diff = count( $this->items ) - $this->max;

		if ( $diff <= 0 ) {
			return;
		}

		reset( $this->items );

		for ( $i = 0; $i < $diff; $i ++ ) {
			unset( $this->items[ key( $this->items ) ] );
			next( $this->items );
		}
	}

	public function remove( string $key ): void {
		unset( $this->items[ $key ] );
	}

	public function count(): int {
		return count( $this->items );
	}

	private function make_item( array $data ): Item {
		return new Item( ...$data );
	}

}
