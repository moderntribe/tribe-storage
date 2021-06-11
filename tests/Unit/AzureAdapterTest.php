<?php

namespace Tribe\Storage\Tests\Unit;

use phpmock\mockery\PHPMockery;
use Tribe\Storage\Tests\TestCase;
use League\Flysystem\Adapter\Local;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AzureAdapterTest extends TestCase {

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

	public function test_it_falls_back_local_adapter_on_missing_defines() {

		PHPMockery::mock( 'Tribe\Storage', 'is_multisite' )
		          ->once()
		          ->andReturnFalse();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'wp_using_ext_object_cache' )
		          ->twice()
		          ->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'get_transient' )
		          ->once()
		          ->andReturnFalse();

		// Mock a user forgot a define their wp-config.php
		define( 'TRIBE_STORAGE_ADAPTER', 'Tribe\Storage\Adapters\Azure_Adapter' );
		//define( 'MICROSOFT_AZURE_ACCOUNT_NAME', 'account' );
		define( 'MICROSOFT_AZURE_ACCOUNT_KEY', 'key' );
		define( 'MICROSOFT_AZURE_CONTAINER', 'container' );
		define( 'MICROSOFT_AZURE_CNAME', 'https://example.com/wp-content/uploads/' );

		$container = tribe_storage()->container();

		$adapter = $container->get( AdapterInterface::class );

		$this->assertInstanceOf( CachedAdapter::class, $adapter );
		$this->assertInstanceOf( Local::class, $adapter->getAdapter() );
	}

	public function test_it_finds_an_azure_adapter() {

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'wp_using_ext_object_cache' )
		          ->twice()
		          ->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'get_transient' )
		          ->once()
		          ->andReturnFalse();

		// Mock what a user would have in their wp-config.php
		define( 'TRIBE_STORAGE_ADAPTER', 'Tribe\Storage\Adapters\Azure_Adapter' );
		define( 'MICROSOFT_AZURE_ACCOUNT_NAME', 'account' );
		define( 'MICROSOFT_AZURE_ACCOUNT_KEY', 'key' );
		define( 'MICROSOFT_AZURE_CONTAINER', 'container' );
		define( 'MICROSOFT_AZURE_CNAME', 'https://example.com/wp-content/uploads/' );

		$container = tribe_storage()->container();

		$adapter = $container->get( AdapterInterface::class );

		$this->assertInstanceOf( CachedAdapter::class, $adapter );
		$this->assertInstanceOf( AzureBlobStorageAdapter::class, $adapter->getAdapter() );
	}

}
