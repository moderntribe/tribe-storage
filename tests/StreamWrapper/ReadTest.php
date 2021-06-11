<?php

namespace Tribe\Storage\Tests\StreamWrapper;

class ReadTest extends StreamWrapperTestCase {

	private $file;

	protected function setUp(): void {
		parent::setUp();

		$this->file = 'fly://testfile.txt';
	}

	public function test_it_reads_a_file_with_fread() {
		$resource = fopen( $this->file, 'r' );
		$this->assertSame( 'filedata', trim( fread( $resource, filesize( $this->file ) ) ) );
		$this->assertTrue( fclose( $resource ) );
	}

	public function test_it_reads_a_file_with_file_get_contents() {
		$content = file_get_contents( $this->file );

		$this->assertSame( 'filedata', trim( $content ) );
	}

	public function test_it_reads_a_file_with_fgets() {
		$resource = fopen( $this->file, 'r' );
		$this->assertSame( 'filedata', trim( fgets( $resource ) ) );
		$this->assertTrue( fclose( $resource ) );
	}

	public function test_it_detects_eof() {
		$resource = fopen( $this->file, 'r' );
		$this->assertFalse( feof( $resource ) );
		fread( $resource, 1500 );
		$this->assertTrue( feof( $resource) );
		$this->assertTrue( fclose( $resource ) );
	}

}
