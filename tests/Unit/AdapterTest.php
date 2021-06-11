<?php

namespace Tribe\Storage\Tests\Unit;

use phpmock\mockery\PHPMockery;
use Tribe\Storage\Tests\TestCase;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Cached\CachedAdapter;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AdapterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', '/tmp/www/wp-content' );
		}

		PHPMockery::mock( 'Tribe\Storage', 'wp_upload_dir' )->andReturn( [
			'path'    => '/tmp/www/wp-content/uploads/2020/09',
			'url'     => 'https://example.com/wp-content/uploads/2020/09',
			'subdir'  => '/2020/09',
			'basedir' => '/tmp/www/wp-content/uploads',
			'baseurl' => 'https://example.com/wp-content/uploads',
			'error'   => false,
		] );

		PHPMockery::mock( 'Tribe\Storage', 'wp_get_upload_dir' )->andReturn( [
			'path'    => '/tmp/www/wp-content/uploads/2020/09',
			'url'     => 'https://example.com/wp-content/uploads/2020/09',
			'subdir'  => '/2020/09',
			'basedir' => '/tmp/www/wp-content/uploads',
			'baseurl' => 'https://example.com/wp-content/uploads',
			'error'   => false,
		] );

		require_once dirname( __DIR__ ) . '/../core.php';

	}

	protected function tearDown(): void {
		parent::tearDown();

		@rmdir( '/tmp/www' );
	}

	/**
	 * If a user didn't define the required defines, this should fall back to the default, local adapter.
	 *
	 * @throws \Exception
	 */
	public function test_it_gets_a_local_adapter_with_missing_adapters() {

		PHPMockery::mock( 'Tribe\Storage', 'is_multisite' )
		          ->once()
		          ->andReturnFalse();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'get_transient' )
		          ->once()
		          ->andReturnFalse();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'wp_using_ext_object_cache' )
		          ->twice()
		          ->andReturnTrue();

		$container = tribe_storage()->container();

		$adapter = $container->get( AdapterInterface::class );

		$this->assertInstanceOf( CachedAdapter::class, $adapter );
		$this->assertInstanceOf( Local::class, $adapter->getAdapter() );
	}

}
