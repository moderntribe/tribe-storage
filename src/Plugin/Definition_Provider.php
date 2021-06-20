<?php declare(strict_types=1);

namespace Tribe\Storage\Plugin;

use DI\Definition\Source\DefinitionSource;

/**
 * Plugins that provide custom container actions should
 * implement this interface and autoload the instance.
 *
 * @package Tribe\Storage\Plugin
 */
interface Definition_Provider {

	/**
	 * Return a PHP-DI definition source.
	 *
	 * @return \DI\Definition\Source\DefinitionSource
	 */
	public function get_definitions(): DefinitionSource;

}
