<?php

namespace Tribe\Storage\Tests\StreamWrapper;

class RenameTest extends StreamWrapperTestCase {

	private $file;
	private $renamed;

	protected function setUp(): void {
		parent::setUp();

		$this->file    = 'fly://original.txt';
		$this->renamed = 'fly://renamed.txt';

		file_put_contents( $this->file, 'filedata' );
	}

	protected function tearDown(): void {
		@unlink( $this->file );
		@unlink( $this->renamed );
		@unlink( 'fly://testrenamed/test.txt' );
		@rmdir( 'fly://testrenamed' );
		parent::tearDown();
	}

	public function test_it_renames_a_file() {
		$this->assertFileDoesNotExist( $this->renamed );
		$this->assertTrue( rename( $this->file, $this->renamed ) );
		$this->assertFileExists( $this->renamed );
		$content = file_get_contents( $this->renamed );

		$this->assertStringContainsString( 'filedata', $content );
	}

	public function test_it_renames_a_directory() {
		$this->assertDirectoryDoesNotExist( 'fly://test' );
		$this->assertDirectoryDoesNotExist( 'fly://testrenamed' );
		mkdir( 'fly://test' );
		$this->assertDirectoryExists( 'fly://test' );
		file_put_contents( 'fly://test/test.txt', 'filedata' );
		$this->assertTrue( rename( 'fly://test', 'fly://testrenamed' ) );
		$this->assertFileExists( 'fly://testrenamed/test.txt' );
	}

}
