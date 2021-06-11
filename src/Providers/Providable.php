<?php declare(strict_types=1);

namespace Tribe\Storage\Providers;

/**
 * Service Providers should implement this interface
 *
 * @package Tribe\Storage\Uploads\Providers
 */
interface Providable {

	/**
	 * Register WordPress hooks with a DI injected instance.
	 */
	public function register(): void;

}
