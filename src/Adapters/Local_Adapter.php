<?php declare(strict_types=1);

namespace Tribe\Storage\Adapters;

use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;

/**
 * The Local Filesystem Flysystem Adapter.
 *
 * @package Tribe\Storage\Adapters
 */
class Local_Adapter extends Adapter {

	/**
	 * The server path to the WordPress upload directory.
	 *
	 * @var string
	 */
	protected $upload_dir;

	/**
	 * Local_Adapter constructor.
	 *
	 * @param  string  $upload_dir  The server path to the WordPress upload directory.
	 */
	public function __construct( string $upload_dir ) {
		$this->upload_dir = $upload_dir;
	}

	/**
	 * Get the Adapter.
	 *
	 * @return \League\Flysystem\AdapterInterface
	 */
	public function get(): AdapterInterface {
		return new Local( $this->upload_dir );
	}

}
