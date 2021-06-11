<?php

namespace Tribe\Storage\Tests\StreamWrapper;

class CopyTest extends StreamWrapperTestCase {

	private $file;
	private $copied;

	protected function setUp(): void {
		parent::setUp();

		$this->file = 'fly://images/subdir/image-with-exif-data.jpg';
		$this->copied = 'fly://test/copied.jpg';

		mkdir( 'fly://test' );
	}

	protected function tearDown(): void {
		@unlink( $this->copied );
		@rmdir( 'fly://test' );
		parent::tearDown();
	}

	public function test_it_copies_a_file() {
		$result = copy( $this->file, $this->copied );

		$this->assertTrue( $result );
		$this->assertFileExists( $this->copied );
		$this->assertFileExists( $this->file );
		$this->assertGreaterThan( 0, filesize( $this->copied ) );
	}

}
