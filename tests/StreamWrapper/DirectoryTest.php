<?php

namespace Tribe\Storage\Tests\StreamWrapper;

class DirectoryTest extends StreamWrapperTestCase {

	private const FILES = [
		'fly://test/1.txt',
		'fly://test/2.txt',
		'fly://test/3.txt',
		'fly://test/4.txt',
	];

	private $dir;

	protected function setUp(): void {
		parent::setUp();

		$this->dir = 'fly://test';
	}

	protected function tearDown(): void {
		foreach( self::FILES as $path ) {
			@unlink( $path );
		}

		@rmdir( $this->dir . '/nested' );
		@rmdir( $this->dir );

		parent::tearDown();
	}

	public function test_mkdir() {
		$this->assertTrue( mkdir( $this->dir ) );
		$this->assertFileExists( $this->dir );
		$this->assertTrue( is_dir( $this->dir ) );
	}

	public function test_list_directory() {
		mkdir( $this->dir );

		foreach ( self::FILES as $path ) {
			file_put_contents( $path, 'filedata' );
		}

		$resource = opendir( $this->dir );
		$this->assertEquals( '1.txt', readdir( $resource ) );
		$this->assertEquals( '2.txt', readdir( $resource ) );
		$this->assertEquals( '3.txt', readdir( $resource ) );
		$this->assertEquals( '4.txt', readdir( $resource ) );
		rewinddir( $resource );
		$this->assertEquals( '1.txt', readdir( $resource ) );
		closedir( $resource );
	}

	public function test_scan_directory() {
		mkdir( $this->dir );
		mkdir( $this->dir . '/nested' );

		$this->assertDirectoryExists( $this->dir . '/nested' );

		foreach ( self::FILES as $path ) {
			file_put_contents( $path, 'filedata' );
		}

		file_put_contents( $this->dir . '/nested/1.txt', '' );

		$expected = [
			'1.txt',
			'2.txt',
			'3.txt',
			'4.txt',
			'nested',
		];

		$this->assertEquals( $expected, scandir( $this->dir ) );
		$this->assertEquals( array_reverse( $expected ), scandir( $this->dir, SCANDIR_SORT_DESCENDING ) );
	}
}
