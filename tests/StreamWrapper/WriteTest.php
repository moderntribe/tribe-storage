<?php

namespace Tribe\Storage\Tests\StreamWrapper;

class WriteTest extends StreamWrapperTestCase {

	private $file;

	protected function setUp(): void {
		parent::setUp();

		$this->file = 'fly://output.txt';
	}

	protected function tearDown(): void {
		unlink( $this->file );
		parent::tearDown();
	}

	public function test_it_can_put_contents() {
		$this->assertFileDoesNotExist( $this->file );
		$output = 'filedata';

		$this->assertEquals( strlen( $output ), file_put_contents( $this->file, $output ) );
		$this->assertFileExists( $this->file );
	}

	public function test_it_can_write_with_fwrite() {
		$this->assertFileDoesNotExist( $this->file );
		$output   = 'filedata';
		$resource = fopen( $this->file, 'w' );

		$this->assertEquals( strlen( $output ), fwrite( $resource, $output ) );
		$this->assertTrue( fclose( $resource ) );
		$this->assertFileExists( $this->file );
	}

	public function test_it_can_write_via_streaming() {
		$this->assertFileDoesNotExist( $this->file );

		$resource = fopen( $this->file, 'w' );
		for ( $i = 0; $i < 50; $i++ ) {
			fwrite( $resource, "Line: $i\n" );
		}

		$this->assertTrue( fclose( $resource ) );
		$this->assertFileExists( $this->file );

		$contents = file_get_contents( $this->file );
		$this->assertStringContainsString( 'Line: 1', $contents );
		$this->assertStringContainsString( 'Line: 49', $contents );
	}

}
