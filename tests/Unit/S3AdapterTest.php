<?php declare(strict_types=1);

namespace Tribe\Storage\Tests\Unit;

use League\Flysystem\AdapterInterface;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Cached\CachedAdapter;
use phpmock\mockery\PHPMockery;
use Tribe\Storage\Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class S3AdapterTest extends TestCase {

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

	public function test_it_finds_an_s3_adapter(): void {
		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'wp_using_ext_object_cache' )
				  ->twice()
				  ->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'get_transient' )
				  ->once()
				  ->andReturnFalse();

		// Mock what a user would have in their wp-config.php
		define( 'TRIBE_STORAGE_ADAPTER', 'Tribe\Storage\Adapters\S3_Adapter' );
		define( 'TRIBE_STORAGE_S3_BUCKET', 'mybucketname' );
		define( 'TRIBE_STORAGE_S3_OPTIONS', [
			'credentials' => [
				'key'    => 'mykey',
				'secret' => 'mysecretkey',
			],
			'region'      => 'us-east-1',
			'version'     => 'latest',
		] );

		$container = tribe_storage()->container();

		$adapter = $container->get( AdapterInterface::class );

		$this->assertInstanceOf( CachedAdapter::class, $adapter );
		$this->assertInstanceOf( AwsS3Adapter::class, $adapter->getAdapter() );
		$this->assertSame( 'mybucketname', $adapter->getAdapter()->getBucket() );

		$promise = $adapter->getAdapter()->getClient()->getCredentials();
		$promise->then( function ( $value ): void {
			$this->assertContains( 'mykey', $value );
			$this->assertContains( 'mysecretkey', $value );
		} );
	}

}
