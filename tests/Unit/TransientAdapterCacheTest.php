<?php

namespace Tribe\Storage\Tests\Unit;

use phpmock\mockery\PHPMockery;
use Tribe\Storage\Tests\TestCase;
use Tribe\Storage\Adapters\Cached_Adapters\Transient;

class TransientAdapterCacheTest extends TestCase {

	public function test_it_fails_to_load_cache_data() {
		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'wp_using_ext_object_cache' )
		          ->twice()
		          ->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'get_transient' )
		          ->once()
		          ->andReturnFalse();

		$cache = new Transient();
		$cache->load();
		$this->assertFalse( $cache->isComplete( '', false ) );
	}

	public function test_it_successfully_loads_data() {
		$response = json_encode( [ [], [ '' => true ] ] );

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'wp_using_ext_object_cache' )
		          ->twice()
		          ->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'get_transient' )
		          ->once()
		          ->andReturn( $response );

		$cache = new Transient();
		$cache->load();
		$this->assertTrue( $cache->isComplete( '', false ) );
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_it_saves_data() {
		$response = json_encode( [ [], [] ] );

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'wp_using_ext_object_cache' )
		          ->twice()
		          ->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'set_transient' )
		          ->once()
		          ->with(
			          'flysystem',
			          $response,
			          0,
		          )
		          ->andReturnTrue();

		$cache = new Transient();
		$cache->save();
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test_it_saves_data_to_object_cache() {
		$response = json_encode( [ [], [] ] );

		$mock = PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'wp_using_ext_object_cache' );

		$mock->once()->with( false )->andReturnTrue();

		$mock->once()->with( true )->andReturnTrue();

		PHPMockery::mock( 'Tribe\Storage\Adapters\Cached_Adapters', 'set_transient' )
		          ->once()
		          ->with(
			          'flysystem',
			          $response,
			          0,
		          )
		          ->andReturnTrue();

		$cache = new Transient( 'flysystem', 0, true );
		$cache->save();
	}

}
