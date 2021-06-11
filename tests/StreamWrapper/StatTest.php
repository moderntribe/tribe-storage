<?php

namespace Tribe\Storage\Tests\StreamWrapper;

use Tribe\Storage\Stream_Wrappers\StreamWrapper;

class StatTest extends StreamWrapperTestCase {

	private $file;
	private $dir;

	protected function setUp(): void {
		parent::setUp();

		$this->file = 'fly://testfile.txt';
		$this->dir  = 'fly://images';
	}

	public function test_file_has_proper_mode() {
		$stat = stat( $this->file );
		$this->assertSame( StreamWrapper::FILE_WRITABLE_MODE, $stat['mode'] );
	}

	public function test_directory_has_proper_mode() {
		$stat = stat( $this->dir );
		$this->assertSame( StreamWrapper::DIR_WRITABLE_MODE, $stat['mode'] );
	}

	public function test_file_is_writable_on_open() {
		$resource = fopen( $this->file, 'w' );
		$stat     = fstat( $resource );
		$this->assertSame( StreamWrapper::FILE_WRITABLE_MODE, $stat['mode'] );
		fclose( $resource );
	}

	public function test_file_is_readable_only_on_open() {
		$resource = fopen( $this->file, 'r' );
		$stat     = fstat( $resource );
		$this->assertSame( StreamWrapper::FILE_READABLE_MODE, $stat['mode'] );
	}

	public function test_it_is_writable() {
		$this->assertTrue( is_writable( $this->file ) );
		$this->assertTrue( is_writable( $this->dir ) );
	}

	public function test_it_is_readable() {
		$this->assertTrue( is_readable( $this->file ) );
		$this->assertTrue( is_readable( $this->dir ) );
	}

	public function test_it_exists() {
		$this->assertFileExists( $this->file );
		$this->assertFileExists( $this->dir );
	}

	public function test_it_is_not_a_symlink() {
		$this->assertFalse( is_link( $this->file ) );
		$this->assertFalse( is_link( $this->dir ) );
	}

	public function test_it_is_executable() {
		$this->assertFalse( is_executable( $this->file ) );
		$this->assertTrue( is_executable( $this->dir ) );
	}

	public function test_it_is_a_file() {
		$this->assertTrue( is_file( $this->file ) );
		$this->assertFalse( is_file( $this->dir ) );
	}

	public function test_it_is_a_directory() {
		$this->assertFalse( is_dir( $this->file ) );
		$this->assertTrue( is_dir( $this->dir ) );
	}

}
