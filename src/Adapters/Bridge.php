<?php declare(strict_types=1);

namespace Tribe\Storage\Adapters;

use League\Flysystem\AdapterInterface;

/**
 * The Flysystem Adapter > WordPress bridge
 *
 * @package Tribe\Storage\Adapters
 */
interface Bridge {

	/**
	 * Get the configured Flysystem Adapter.
	 *
	 * @return \League\Flysystem\AdapterInterface
	 */
	public function get(): AdapterInterface;

}
