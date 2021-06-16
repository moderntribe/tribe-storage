<?php declare(strict_types=1);

namespace Tribe\Storage\Adapters;

use League\Flysystem\AdapterInterface;

/**
 * Extend to create Flysystem > Tribe Storage Adapter Bridges.
 *
 * @package Tribe\Storage\Adapters
 */
abstract class Adapter implements Bridge {

	/**
	 * Get the Adapter.
	 */
	abstract public function get(): AdapterInterface;

}
