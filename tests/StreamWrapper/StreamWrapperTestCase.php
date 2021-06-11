<?php

namespace Tribe\Storage\Tests\StreamWrapper;

use Tribe\Storage\Cache\Lru;
use League\Flysystem\Filesystem;
use Tribe\Storage\Tests\TestCase;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Plugin\ForcedRename;
use Tribe\Storage\Stream_Wrappers\StreamWrapper;
use Jhofm\FlysystemIterator\Plugin\IteratorPlugin;
use Tribe\Storage\Stream_Wrappers\Identity\Posix_Identifier;

class StreamWrapperTestCase extends TestCase {

	protected $filesystem;

	protected function setUp(): void {
		parent::setUp();

		$adapter    = new Local( tribe_data_dir( 'stream_wrapper' ) );
		$identifier = new Posix_Identifier();
		$cache      = new Lru();
		$filesystem = new Filesystem( $adapter, [ 'visibility' => 'public' ] );
		$filesystem->addPlugin( new IteratorPlugin() );
		$filesystem->addPlugin( new ForcedRename() );

		$this->filesystem = $filesystem;
		StreamWrapper::register( $filesystem, $identifier, $cache );
	}

	protected function tearDown(): void {
		parent::tearDown();

		StreamWrapper::unregister();
	}

}
