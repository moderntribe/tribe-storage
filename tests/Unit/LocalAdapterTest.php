<?php

namespace Tribe\Storage\Tests\Unit;

use phpmock\mockery\PHPMockery;
use Tribe\Storage\Tests\TestCase;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;
use Tribe\Storage\Adapters\Local_Adapter;
use League\Flysystem\Cached\CachedAdapter;

class LocalAdapterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', '/tmp/www/wp-content' );
		}

		PHPMockery::mock( 'Tribe\Storage', 'wp_upload_dir' )->andReturn( [
			'path'    => '/tmp/www/wp-content/uploads/sites/2/2020/09',
			'url'     => 'https://example.com/wp-content/uploads/sites/2/2020/09',
			'subdir'  => '/2020/09',
			'basedir' => '/tmp/www/wp-content/uploads/sites/2',
			'baseurl' => 'https://example.com/wp-content/uploads/sites/2',
			'error'   => false,
		] );

		// When we switch to the main blog
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

	public function test_it_gets_a_local_adapter_instance() {
		$local = new Local_Adapter( '/tmp/www/wp-content/uploads' );

		$this->assertInstanceOf( Local::class, $local->get() );
	}

	public function test_it_gets_uploads_root_path_on_multisite_when_called_on_a_subsite() {
		define( 'BLOG_ID_CURRENT_SITE', 1 );

		PHPMockery::mock( 'Tribe\Storage', 'is_multisite' )
		          ->once()
		          ->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage', 'switch_to_blog' )
		          ->with( 1 )
		          ->once()
		          ->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage', 'restore_current_blog' )
		          ->once()
		          ->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'wp_using_ext_object_cache' )
		          ->twice()
		          ->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'get_transient' )
		          ->once()
		          ->andReturnFalse();

		$container = tribe_storage()->container();

		$adapter = $container->get( AdapterInterface::class );

		$this->assertInstanceOf( CachedAdapter::class, $adapter );
		$this->assertInstanceOf( Local::class, $adapter->getAdapter() );
		$this->assertEquals( '/tmp/www/wp-content/uploads/', $adapter->getPathPrefix() );
	}

}
