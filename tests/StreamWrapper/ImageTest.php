<?php

namespace Tribe\Storage\Tests\StreamWrapper;

class ImageTest extends StreamWrapperTestCase {

	private $image;
	private $image_with_broken_exif;

	protected function setUp(): void {
		parent::setUp();

		$this->image                  = 'fly://images/subdir/image-with-exif-data.jpg';
		$this->image_with_broken_exif = 'fly://images/image-with-corrupt-exif-data.jpg';
	}

	public function test_getimagesize() {
		$size = getimagesize( $this->image );

		// 0 = width, 1 = height
		$this->assertSame( 2500, $size[0] );
		$this->assertSame( 2500, $size[1] );

		$size = getimagesize( $this->image_with_broken_exif );

		$this->assertSame( 1800, $size[0] );
		$this->assertSame( 1200, $size[1] );
	}

	public function test_getimagesize_with_info() {
		$info = [];
		$size = getimagesize( $this->image, $info );

		// 0 = width, 1 = height
		$this->assertSame( 2500, $size[0] );
		$this->assertSame( 2500, $size[1] );

		unset( $info );
		$info = [];

		$size = getimagesize( $this->image_with_broken_exif, $info );

		// This image is corrupt, will not return proper data
		$this->assertFalse( $size );
	}

	public function test_exif_with_proper_data() {
		$exif = exif_read_data( $this->image );

		$this->assertSame( IMAGETYPE_JPEG, $exif['FileType'] );
		$this->assertSame( 'image/jpeg', $exif['MimeType'] );
	}

	public function test_exif_with_broken_exif_data() {
		$this->expectError();
		$exif = exif_read_data( $this->image_with_broken_exif );

		$this->assertFalse( $exif );
	}

}
