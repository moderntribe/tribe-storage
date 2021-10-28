<?php declare(strict_types=1);

namespace Tribe\Storage\Tests\Unit;

use League\Flysystem\Adapter\Local;
use League\Flysystem\Adapter\NullAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Cached\CachedAdapter;
use phpmock\mockery\PHPMockery;
use Tribe\Storage\Tests\TestCase;

use Brain\Monkey\Filters;

/**
 * @runTestsInSeparateProcesses
 *
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

		PHPMockery::mock( 'Tribe\Storage', 'is_multisite' )
		          ->once()
		          ->andReturnFalse();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'get_transient' )
		          ->once()
		          ->andReturnFalse();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'wp_using_ext_object_cache' )
		          ->twice()
		          ->andReturnTrue();
	}

	protected function tearDown(): void {
		parent::tearDown();

		@rmdir( '/tmp/www' );
	}

	/**
	 * Test the user can override the adapter with a filter.
	 *
	 * @throws \Exception
	 */
	public function test_developers_can_set_a_custom_adapter_with_filters(): void {
		$null_adapter = new NullAdapter();

		Filters\expectApplied( 'tribe/storage/flysystem_adapter' )
			->once()
			->andReturn( $null_adapter );

		$container = tribe_storage()->container();
		$adapter   = $container->get( AdapterInterface::class );

		$this->assertInstanceOf( CachedAdapter::class, $adapter );
		$this->assertInstanceOf( NullAdapter::class, $adapter->getAdapter() );
	}

	/**
	 * If a user didn't define the required defines, this should fall back to the default, local adapter.
	 *
	 * @throws \Exception
	 */
	public function test_it_gets_a_local_adapter_with_no_defined_adapter(): void {
		$container = tribe_storage()->container();
		$adapter   = $container->get( AdapterInterface::class );

		$this->assertInstanceOf( CachedAdapter::class, $adapter );
		$this->assertInstanceOf( Local::class, $adapter->getAdapter() );
		$this->assertSame( 1, Filters\applied( 'tribe/storage/undefined_adapter_message' ) );
		$this->assertSame( '1', getenv( 'MAGICK_THREAD_LIMIT' ) );
	}

	/**
	 * Test it falls back to the local adapter if the user supplied an invalid adapter.
	 *
	 * @throws \Exception
	 */
	public function test_it_falls_back_to_local_adapter_with_invalid_custom_adapter(): void {
		Filters\expectApplied( 'tribe/storage/flysystem_adapter' )
			->once()
			->andReturn( 'invalid' );

		$container = tribe_storage()->container();
		$adapter   = $container->get( AdapterInterface::class );

		$this->assertInstanceOf( CachedAdapter::class, $adapter );
		$this->assertInstanceOf( Local::class, $adapter->getAdapter() );
		$this->assertSame( 1, Filters\applied( 'tribe/storage/undefined_adapter_message' ) );
	}

}
