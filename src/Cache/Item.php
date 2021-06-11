<?php declare(strict_types=1);

namespace Tribe\Storage\Cache;

/**
 * Cache Item DTO
 *
 * @package Tribe\Storage\Cache
 */
class Item {

	/**
	 * The value of the cache item.
	 *
	 * @var null|mixed
	 */
	public $value = null;

	/**
	 * The expiration of the cache item.
	 *
	 * @var int
	 */
	public $ttl = 0;

	/**
	 * Item constructor.
	 *
	 * @param  null  $value
	 * @param  int   $ttl
	 */
	public function __construct( $value = null, int $ttl = 0 ) {
		$this->value = $value;
		$this->ttl   = $ttl;
	}

}
