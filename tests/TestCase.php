<?php

namespace Tribe\Storage\Tests;

use Brain\Monkey;
use phpmock\mockery\PHPMockery;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock the current blog ID for all tests so caching strategies can work.
		PHPMockery::mock( 'Tribe\Storage', 'get_current_blog_id' )->andReturn( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
