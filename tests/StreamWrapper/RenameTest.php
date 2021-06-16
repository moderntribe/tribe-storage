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

		@unlink( 'fly://testwordpress/test.txt' );
		@unlink( 'fly://testrenamedwordpress/test.txt' );
		@rmdir( 'fly://testwordpress' );
		@rmdir( 'fly://testrenamedwordpress' );
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

	/**
	 * For WordPress, we force all directories to exist to work around WordPress's
	 * wp_upload_dir() doing file_exists() checks on the uploads directory.
	 */
	public function test_it_renames_a_directory_in_wordpress() {
		// Define a constant that is always available in WordPress
		define( 'ABSPATH', '/tmp');

		// This will always return true
		$this->assertDirectoryExists( 'fly://testwordpress' );
		mkdir( 'fly://testwordpress' );
		// For good measure
		$this->assertDirectoryExists( 'fly://testwordpress' );
		file_put_contents( 'fly://testwordpress/test.txt', 'filedata' );
		$this->assertTrue( rename( 'fly://testwordpress', 'fly://testrenamedwordpress' ) );
		$this->assertFileExists( 'fly://testrenamedwordpress/test.txt' );
	}

}
