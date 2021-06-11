<?php

namespace Tribe\Storage\Tests\Unit;

use Mockery;
use RuntimeException;
use Brain\Monkey\Filters;
use Tribe\Storage\Attachment;
use phpmock\mockery\PHPMockery;
use League\Flysystem\Filesystem;
use Tribe\Storage\Tests\TestCase;
use League\Flysystem\Adapter\Local;
use Tribe\Storage\Uploads\Wp_Upload_Dir;

class AttachmentTest extends TestCase {

	/**
	 * @var \Mockery\MockInterface|\Mockery\LegacyMockInterface
	 */
	protected $filesystem;

	/**
	 * @var \Mockery\MockInterface|\Mockery\LegacyMockInterface
	 */
	protected $upload_dir;

	/**
	 * @var \League\Flysystem\Adapter\Local|\Mockery\LegacyMockInterface|\Mockery\MockInterface
	 */
	protected $local_adapter;

	protected function setUp(): void {
		parent::setUp();
		$this->filesystem    = Mockery::mock( Filesystem::class );
		$this->upload_dir    = Mockery::mock( Wp_Upload_Dir::class );
		$this->local_adapter = Mockery::mock( Local::class );

		// Mock all core/WordPress functions in the image_metadata URL
		PHPMockery::mock( 'Tribe\Storage', 'function_exists' )->andReturnTrue();
		PHPMockery::mock( 'Tribe\Storage', 'wp_tempnam' )->andReturn( 'flysystem_tempfile' );
		PHPMockery::mock( 'Tribe\Storage', 'copy' )->andReturnTrue();
		PHPMockery::mock( 'Tribe\Storage', 'wp_read_image_metadata' )->andReturn( [
			'title' => 'Some EXIF title',
		] );
		PHPMockery::mock( 'Tribe\Storage', 'unlink' )->andReturnTrue();
	}

	public function test_it_modifies_the_attachment_url() {
		// Mock the already filtered wp_upload_dir() function
		PHPMockery::mock( 'Tribe\Storage', 'wp_upload_dir' )->andReturn( [
			'baseurl' => 'https://somecndurl.com/wp-content/uploads/sites/3',
		] );

		// Mock the original wp_upload_dir() output, before we changed anything.
		$this->upload_dir->shouldReceive( 'original_dir' )->once()->andReturn( [
			'baseurl' => 'https://originalwordpressurl.com/wp-content/uploads/sites/3',
		] );

		$this->filesystem->shouldReceive( 'getAdapter' )->once()->andReturn( $this->local_adapter );

		Filters\expectApplied( 'tribe/storage/attachment_url' )
			->once()
			->with( \Mockery::type( 'string' ), \Mockery::type( 'int' ), Mockery::type( Local::class ) );

		$attachment = new Attachment( $this->filesystem, $this->upload_dir );

		$url = $attachment->attachment_url( 'https://originalwordpressurl.com/wp-content/uploads/sites/3/2016/12/cropped-Windows-logo2.png', 1 );

		$this->assertSame( 'https://somecndurl.com/wp-content/uploads/sites/3/2016/12/cropped-Windows-logo2.png', $url );
	}

	public function test_it_does_not_modify_an_already_replaced_url() {
		// Mock the already filtered wp_upload_dir() function
		PHPMockery::mock( 'Tribe\Storage', 'wp_upload_dir' )->andReturn( [
			'baseurl' => 'https://somecndurl.com/wp-content/uploads/prod/sites/3',
		] );

		// Mock the original wp_upload_dir() output, before we changed anything.
		$this->upload_dir->shouldNotReceive( 'original_dir' );

		$this->filesystem->shouldReceive( 'getAdapter' )->once()->andReturn( $this->local_adapter );

		Filters\expectApplied( 'tribe/storage/attachment_url' )
			->once()
			->with( \Mockery::type( 'string' ), \Mockery::type( 'int' ), Mockery::type( Local::class ) );

		$attachment = new Attachment( $this->filesystem, $this->upload_dir );

		$url = $attachment->attachment_url( 'https://somecndurl.com/wp-content/uploads/prod/sites/3/2016/12/cropped-Windows-logo2.png', 1 );

		$this->assertSame( 'https://somecndurl.com/wp-content/uploads/prod/sites/3/2016/12/cropped-Windows-logo2.png', $url );
	}

	public function test_it_can_filter_attachment_url() {
		// Mock the already filtered wp_upload_dir() function
		PHPMockery::mock( 'Tribe\Storage', 'wp_upload_dir' )->andReturn( [
			'baseurl' => 'https://somecndurl.com/wp-content/uploads/sites/3',
		] );

		// Mock the original wp_upload_dir() output, before we changed anything.
		$this->upload_dir->shouldReceive( 'original_dir' )->once()->andReturn( [
			'baseurl' => 'https://originalwordpressurl.com/wp-content/uploads/sites/3',
		] );

		$this->filesystem->shouldReceive( 'getAdapter' )->once()->andReturn( $this->local_adapter );

		Filters\expectApplied( 'tribe/storage/attachment_url' )
			->once()
			->with( \Mockery::type( 'string' ), \Mockery::type( 'int' ), Mockery::type( Local::class ) )
			->andReturn( 'https://somedifferenturl.com/wp-content/uploads/sites/3/2016/12/cropped-Windows-logo2.png' );

		$attachment = new Attachment( $this->filesystem, $this->upload_dir );

		$url = $attachment->attachment_url( 'https://originalwordpressurl.com/wp-content/uploads/sites/3/2016/12/cropped-Windows-logo2.png', 1 );

		self::assertSame( 'https://somedifferenturl.com/wp-content/uploads/sites/3/2016/12/cropped-Windows-logo2.png',
			apply_filters( 'tribe/storage/attachment_url', 'https://somedifferenturl.com/wp-content/uploads/sites/3/2016/12/cropped-Windows-logo2.png' )
		);

		$this->assertSame( 'https://somedifferenturl.com/wp-content/uploads/sites/3/2016/12/cropped-Windows-logo2.png', $url );
	}

	public function test_it_reads_meta_data() {
		$attachment = new Attachment( $this->filesystem, $this->upload_dir );

		$meta = $attachment->image_metadata( [], '' );

		$this->assertSame( $meta['title'], 'Some EXIF title' );
	}

	public function test_it_adds_filesize_to_missing_image_meta() {
		$attachment = new Attachment( $this->filesystem, $this->upload_dir );

		$meta = [
			'sizes' => [
				'some_image_size',
			],
		];

		$attachment_id = 1;

		$image_meta = $attachment->get_metadata( $meta, $attachment_id );

		$this->assertSame( 350000, $image_meta['filesize'] );
	}

	public function test_it_ignores_filesize_if_meta_exists() {
		$attachment = new Attachment( $this->filesystem, $this->upload_dir );

		$meta = [
			'sizes'    => [
				'some_image_size',
			],
			'filesize' => 1000,
		];

		$attachment_id = 1;

		$image_meta = $attachment->get_metadata( $meta, $attachment_id );

		$this->assertSame( 1000, $image_meta['filesize'] );
	}

	public function test_it_returns_false_on_false_meta() {
		$attachment = new Attachment( $this->filesystem, $this->upload_dir );

		$image_meta = $attachment->get_metadata( false, 1 );

		$this->assertFalse( $image_meta );
	}

	public function test_it_saves_file_size_to_image_meta_on_main_site() {
		$this->filesystem->shouldReceive( 'getAdapter' )->once()->andReturnSelf();

		$this->filesystem->shouldReceive( 'getMetadata' )
		                 ->once()
		                 ->with( '2021/01/testimage.jpg' )
		                 ->andReturn( [
			                 'size' => 550000,
		                 ] );

		// Mock the output of wp_get_upload_dir
		PHPMockery::mock( 'Tribe\Storage', 'wp_get_upload_dir' )->andReturn( [
			'path'    => 'fly://2021/01',
			'url'     => 'https://example.com/wp-content/uploads/2021/01',
			'subdir'  => '/2021/01',
			'basedir' => 'fly:/',
			'baseurl' => 'https://example.com/wp-content/uploads',
			'error'   => false,
		] );

		$attachment = new Attachment( $this->filesystem, $this->upload_dir );

		$meta = [
			'sizes' => [
				'some_image_size',
			],
			'file'  => '2021/01/testimage.jpg',
		];

		$attachment_id = 1;

		$image_meta = $attachment->update_metadata( $meta, $attachment_id );

		$this->assertSame( 550000, $image_meta['filesize'] );
	}

	public function test_it_saves_file_size_to_image_meta_on_sub_site() {
		$this->filesystem->shouldReceive( 'getAdapter' )->once()->andReturnSelf();

		$this->filesystem->shouldReceive( 'getMetadata' )
		                 ->once()
		                 ->with( 'sites/5/2021/01/testimage.jpg' )
		                 ->andReturn( [
			                 'size' => 550000,
		                 ] );

		// Mock the output of wp_get_upload_dir
		PHPMockery::mock( 'Tribe\Storage', 'wp_get_upload_dir' )->andReturn( [
			'path'    => 'fly://sites/5/2021/01',
			'url'     => 'https://example.com/wp-content/uploads/sites/5/2021/01',
			'subdir'  => '/2021/01',
			'basedir' => 'fly://sites/5',
			'baseurl' => 'https://example.com/wp-content/uploads/sites/5',
			'error'   => false,
		] );

		$attachment = new Attachment( $this->filesystem, $this->upload_dir );

		$meta = [
			'sizes' => [
				'some_image_size',
			],
			'file'  => '2021/01/testimage.jpg',
		];

		$attachment_id = 1;

		$image_meta = $attachment->update_metadata( $meta, $attachment_id );

		$this->assertSame( 550000, $image_meta['filesize'] );
	}

	public function test_it_does_not_save_filesize_if_meta_exists() {
		$attachment = new Attachment( $this->filesystem, $this->upload_dir );

		$meta = [
			'sizes'    => [
				'some_image_size',
			],
			'file'     => '2021/01/testimage.jpg',
			'filesize' => 1000,
		];

		$attachment_id = 1;

		$image_meta = $attachment->update_metadata( $meta, $attachment_id );

		$this->assertSame( 1000, $image_meta['filesize'] );
	}

}
