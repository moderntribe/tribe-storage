<?php declare(strict_types=1);

namespace Tribe\Storage\Providers;

/**
 * The Service Provider Interface.
 *
 * @package Tribe\Storage\Uploads\Providers
 */
interface Providable {

	/**
	 * Register WordPress hooks with a DI injected instance.
	 */
	public function register(): void;

}
