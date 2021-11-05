<?php

namespace Tribe\Storage\Tests\Unit;

use Brain\Monkey\Filters;
use Intervention\Image\Exception\NotSupportedException;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use Jhofm\FlysystemIterator\FilesystemIterator;
use Jhofm\FlysystemIterator\Filter\FilterFactory;
use League\Flysystem\Filesystem;
use Mockery;
use phpmock\mockery\PHPMockery;
use Tribe\Storage\Tests\TestCase;
use Tribe\Storage\Uploads\Upload_Manager;
use Tribe\Storage\Uploads\Wp_Upload_Dir;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class UploadManagerTest extends TestCase {

	/**
	 * @var \Mockery\MockInterface|\Mockery\LegacyMockInterface
	 */
	protected $filesystem;

	/**
	 * @var \Mockery\MockInterface|\Mockery\LegacyMockInterface
	 */
	protected $upload_dir;

	/**
	 * @var \Intervention\Image\ImageManager|\Mockery\LegacyMockInterface|\Mockery\MockInterface
	 */
	protected $image_manager;

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', '/tmp/www/wp-content' );
		}

		if ( ! defined( 'TRIBE_STORAGE_IMAGE_ORIENTATION' ) ) {
			define( 'TRIBE_STORAGE_IMAGE_ORIENTATION', true );
		}

		$this->filesystem                      = Mockery::mock( Filesystem::class );
		$this->upload_dir                      = Mockery::mock( Wp_Upload_Dir::class );
		$this->image_manager                   = Mockery::mock( ImageManager::class );
		$this->image_manager->config['driver'] = 'imagick';

		PHPMockery::mock( 'Tribe\Storage\Uploads', 'get_current_blog_id' )->andReturn( 1 );
	}

	protected function tearDown(): void {
		parent::tearDown();

		@rmdir( '/tmp/www' );
	}

	private function get_original_wp_upload_dir(): array {
		return [
			'path'    => '/tmp/www/wp-content/uploads/2020/09',
			'url'     => 'https://example.com/wp-content/uploads/2020/09',
			'subdir'  => '/2020/09',
			'basedir' => '/tmp/www/wp-content/uploads',
			'baseurl' => 'https://example.com/wp-content/uploads',
			'error'   => false,
		];
	}

	private function get_original_wp_upload_dir_subsite(): array {
		return [
			'path'    => '/tmp/www/wp-content/uploads/sites/3/2020/09',
			'url'     => 'https://example.com/wp-content/uploads/sites/3/2020/09',
			'subdir'  => '/2020/09',
			'basedir' => '/tmp/www/wp-content/uploads/sites/3',
			'baseurl' => 'https://example.com/wp-content/uploads/sites/3',
			'error'   => false,
		];
	}

	private function get_original_wp_upload_dir_subsite_no_subdir(): array {
		return [
			'path' => '/tmp/www/wp-content/uploads/sites/2',
			'url' => 'https://example.com/en-us/wp-content/uploads/sites/2',
			'subdir' => '',
			'basedir' => '/tmp/www/wp-content/uploads/sites/2',
			'baseurl' => 'https://example.com/en-us/wp-content/uploads/sites/2',
			'error'   => false,
		];
	}

	public function test_it_fixes_image_orientation() {
		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		$file['tmp_name'] = '/tmp/somefile.jpg';

		$image = Mockery::mock( Image::class );

		$this->image_manager->shouldReceive( 'make' )->with( '/tmp/somefile.jpg' )->once()->andReturn( $image );

		$image->shouldReceive( 'orientate' )->once()->andReturnSelf();
		$image->shouldReceive( 'save' )->with( '/tmp/somefile.jpg' )->once()->andReturnSelf();

		Filters\expectApplied( 'tribe/storage/bypass_image_orientation' )
			->once()
			->with( false, null, $file, '', '' );

		$result = $upload_manager->fix_image_orientation( null, $file, '', '' );

		$this->assertNull( $result );
	}

	public function test_intervention_image_falls_back_to_alternative_gd_driver() {
		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		$file['tmp_name'] = '/tmp/somefile.jpg';

		$image = Mockery::mock( Image::class );

		$this->image_manager->shouldReceive( 'make' )->with( '/tmp/somefile.jpg' )->once()->andThrow( NotSupportedException::class );
		$this->image_manager->shouldReceive( 'configure' )->with( [ 'driver' => 'gd' ] )->once()->andReturnSelf();
		$this->image_manager->shouldReceive( 'make' )->with( '/tmp/somefile.jpg' )->once()->andReturn( $image );

		$image->shouldReceive( 'orientate' )->once()->andReturnSelf();
		$image->shouldReceive( 'save' )->with( '/tmp/somefile.jpg' )->once()->andReturnSelf();

		Filters\expectApplied( 'tribe/storage/bypass_image_orientation' )
			->once()
			->with( false, null, $file, '', '' );

		$result = $upload_manager->fix_image_orientation( null, $file, '', '' );

		$this->assertNull( $result );
	}

	public function test_intervention_image_falls_back_to_alternative_imagick_driver() {
		$this->image_manager->config['driver'] = 'gd';

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		$file['tmp_name'] = '/tmp/somefile.jpg';

		$image = Mockery::mock( Image::class );

		$this->image_manager->shouldReceive( 'make' )->with( '/tmp/somefile.jpg' )->once()->andThrow( NotSupportedException::class );
		$this->image_manager->shouldReceive( 'configure' )->with( [ 'driver' => 'imagick' ] )->once()->andReturnSelf();
		$this->image_manager->shouldReceive( 'make' )->with( '/tmp/somefile.jpg' )->once()->andReturn( $image );

		$image->shouldReceive( 'orientate' )->once()->andReturnSelf();
		$image->shouldReceive( 'save' )->with( '/tmp/somefile.jpg' )->once()->andReturnSelf();

		Filters\expectApplied( 'tribe/storage/bypass_image_orientation' )
			->once()
			->with( false, null, $file, '', '' );

		$result = $upload_manager->fix_image_orientation( null, $file, '', '' );

		$this->assertNull( $result );
	}

	public function test_image_orientation_can_be_bypassed() {
		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		$file['tmp_name'] = '/tmp/somefile.jpg';

		// Mock the filter returned true
		Filters\expectApplied( 'tribe/storage/bypass_image_orientation' )
			->once()
			->andReturn( true );

		$this->image_manager->shouldNotReceive( 'make' );
		$this->image_manager->shouldNotReceive( 'orientate' );
		$this->image_manager->shouldNotReceive( 'save' );

		$result = $upload_manager->fix_image_orientation( null, $file, '', '' );

		$this->assertNull( $result );
	}

	public function test_orientation_throws_exception_on_invalid_driver() {
		// Will only throw when WP_DEBUG is enabled.
		define( 'WP_DEBUG', true );

		$this->expectException( NotSupportedException::class );

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		$file['tmp_name'] = '/tmp/somefile.jpg';

		$this->image_manager->shouldReceive( 'make' )->with( '/tmp/somefile.jpg' )->twice()->andThrow( NotSupportedException::class );
		$this->image_manager->shouldReceive( 'configure' )->with( [ 'driver' => 'gd' ] )->once()->andReturnSelf();

		Filters\expectApplied( 'tribe/storage/bypass_image_orientation' )
			->once()
			->andReturn( false );

		$upload_manager->fix_image_orientation( null, $file, '', '' );
	}

	public function test_it_returns_all_image_editors_by_default() {
		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		$editors = [
			'WP_Image_Editor_Imagick',
			'WP_Image_Editor_GD',
			'Random_3rd_Party_Editor',
		];

		$filtered_editors = $upload_manager->image_editors( $editors );

		$this->assertCount( 3, $filtered_editors );
		$this->assertContains( 'WP_Image_Editor_GD', $filtered_editors );
		$this->assertContains( 'WP_Image_Editor_Imagick', $filtered_editors );
		$this->assertContains( 'Random_3rd_Party_Editor', $filtered_editors );
	}

	public function test_it_forces_custom_gd_image_editor() {
		define( 'TRIBE_STORAGE_IMAGE_EDITOR', 'gd' );

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		$editors = [
			'WP_Image_Editor_Imagick',
			'WP_Image_Editor_GD',
		];

		$filtered_editors = $upload_manager->image_editors( $editors );

		$this->assertCount( 1, $filtered_editors );
		$this->assertContains( 'WP_Image_Editor_GD', $filtered_editors );
		$this->assertNotContains( 'WP_Image_Editor_Imagick', $filtered_editors );
	}

	public function test_it_forces_custom_imagick_image_editor() {
		define( 'TRIBE_STORAGE_IMAGE_EDITOR', 'imagick' );

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		$editors = [
			'WP_Image_Editor_Imagick',
			'WP_Image_Editor_GD',
		];

		$filtered_editors = $upload_manager->image_editors( $editors );

		$this->assertCount( 1, $filtered_editors );
		$this->assertContains( 'WP_Image_Editor_Imagick', $filtered_editors );
		$this->assertNotContains( 'WP_Image_Editor_GD', $filtered_editors );
	}

	public function test_it_filters_wp_upload_dir_with_stream() {
		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		$this->upload_dir->shouldReceive( 'original_dir' )->once()->andReturn( $this->get_original_wp_upload_dir() );
		$this->upload_dir->shouldReceive( 'add_dir' )->with( $this->get_original_wp_upload_dir() );

		Filters\expectApplied( 'tribe/storage/upload/url' )
			->once()
			->with( 'https://example.com/wp-content/uploads', $this->get_original_wp_upload_dir(), $this->upload_dir );

		Filters\expectApplied( 'tribe/storage/upload/base_path' )
			->once()
			->with( 'fly://' );

		Filters\expectApplied( 'tribe/storage/stream_name' )
			->once()
			->with( 'fly' );

		$wp_upload_dir = $upload_manager->upload_dir( $this->get_original_wp_upload_dir() );

		$this->assertSame( [
			'path'    => 'fly://2020/09',
			'url'     => 'https://example.com/wp-content/uploads/2020/09',
			'subdir'  => '/2020/09',
			'basedir' => 'fly:/',
			'baseurl' => 'https://example.com/wp-content/uploads',
			'error'   => false,
		], $wp_upload_dir );
	}

	public function test_it_filters_wp_upload_dir_custom_url_and_stream() {
		define( 'TRIBE_STORAGE_URL', 'https://example.com/wp-content/uploads/prod' );

		$this->upload_dir->shouldReceive( 'add_dir' )->with( $this->get_original_wp_upload_dir() );

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		Filters\expectApplied( 'tribe/storage/upload/url' )
			->once()
			->with( 'https://example.com/wp-content/uploads/prod', $this->get_original_wp_upload_dir(), $this->upload_dir );

		Filters\expectApplied( 'tribe/storage/upload/base_path' )
			->once()
			->with( 'fly://' );

		Filters\expectApplied( 'tribe/storage/stream_name' )
			->once()
			->with( 'fly' );

		$wp_upload_dir = $upload_manager->upload_dir( $this->get_original_wp_upload_dir() );

		$this->assertSame( [
			'path'    => 'fly://2020/09',
			'url'     => 'https://example.com/wp-content/uploads/prod/2020/09',
			'subdir'  => '/2020/09',
			'basedir' => 'fly:/',
			'baseurl' => 'https://example.com/wp-content/uploads/prod',
			'error'   => false,
		], $wp_upload_dir );
	}

	public function test_it_filters_wp_upload_dir_with_stream_subsite() {
		$this->upload_dir->shouldReceive( 'original_dir' )->once()->andReturn( $this->get_original_wp_upload_dir_subsite() );
		$this->upload_dir->shouldReceive( 'add_dir' )->with( $this->get_original_wp_upload_dir_subsite() );

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		Filters\expectApplied( 'tribe/storage/upload/url' )
			->once()
			->with( 'https://example.com/wp-content/uploads/sites/3', $this->get_original_wp_upload_dir_subsite(), $this->upload_dir );

		Filters\expectApplied( 'tribe/storage/upload/base_path' )
			->once()
			->with( 'fly://' );

		Filters\expectApplied( 'tribe/storage/stream_name' )
			->once()
			->with( 'fly' );

		$wp_upload_dir = $upload_manager->upload_dir( $this->get_original_wp_upload_dir_subsite() );

		$this->assertSame( [
			'path'    => 'fly://sites/3/2020/09',
			'url'     => 'https://example.com/wp-content/uploads/sites/3/2020/09',
			'subdir'  => '/2020/09',
			'basedir' => 'fly://sites/3',
			'baseurl' => 'https://example.com/wp-content/uploads/sites/3',
			'error'   => false,
		], $wp_upload_dir );
	}

	public function test_it_filters_wp_upload_dir_with_stream_subsite_and_no_subdir() {
		$this->upload_dir->shouldReceive( 'original_dir' )->once()->andReturn( $this->get_original_wp_upload_dir_subsite_no_subdir() );
		$this->upload_dir->shouldReceive( 'add_dir' )->with( $this->get_original_wp_upload_dir_subsite_no_subdir() );

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		Filters\expectApplied( 'tribe/storage/upload/url' )
			->once()
			->with( 'https://example.com/en-us/wp-content/uploads/sites/2', $this->get_original_wp_upload_dir_subsite_no_subdir(), $this->upload_dir );

		Filters\expectApplied( 'tribe/storage/upload/base_path' )
			->once()
			->with( 'fly://' );

		Filters\expectApplied( 'tribe/storage/stream_name' )
			->once()
			->with( 'fly' );

		$wp_upload_dir = $upload_manager->upload_dir( $this->get_original_wp_upload_dir_subsite_no_subdir() );

		$this->assertSame( [
			'path'    => 'fly://sites/2',
			'url'     => 'https://example.com/en-us/wp-content/uploads/sites/2',
			'subdir'  => '',
			'basedir' => 'fly://sites/2',
			'baseurl' => 'https://example.com/en-us/wp-content/uploads/sites/2',
			'error'   => false,
		], $wp_upload_dir );
	}

	public function test_it_filters_wp_upload_dir_with_custom_url_and_stream_subsite() {
		define( 'TRIBE_STORAGE_URL', 'https://example.com/wp-content/uploads/prod' );

		$this->upload_dir->shouldReceive( 'add_dir' )->with( $this->get_original_wp_upload_dir_subsite() );

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );

		Filters\expectApplied( 'tribe/storage/upload/url' )
			->once()
			->with( 'https://example.com/wp-content/uploads/prod', $this->get_original_wp_upload_dir_subsite(), $this->upload_dir );

		Filters\expectApplied( 'tribe/storage/upload/base_path' )
			->once()
			->with( 'fly://' );

		Filters\expectApplied( 'tribe/storage/stream_name' )
			->once()
			->with( 'fly' );

		$wp_upload_dir = $upload_manager->upload_dir( $this->get_original_wp_upload_dir_subsite() );

		$this->assertSame( [
			'path'    => 'fly://sites/3/2020/09',
			'url'     => 'https://example.com/wp-content/uploads/prod/sites/3/2020/09',
			'subdir'  => '/2020/09',
			'basedir' => 'fly://sites/3',
			'baseurl' => 'https://example.com/wp-content/uploads/prod/sites/3',
			'error'   => false,
		], $wp_upload_dir );
	}

	public function test_it_finds_no_duplicate_files() {
		$this->filesystem->shouldReceive( 'listContents' )->once()->andReturn( [] );
		$this->filesystem->shouldReceive( 'createIterator' )
		                 ->once()
		                 ->andReturn( new FilesystemIterator( $this->filesystem, 'sites/4/2020/09/test-1.jpg' ), [
			                 'recursive' => false,
			                 'filter'    => FilterFactory::and(
				                 FilterFactory::isFile(),
				                 FilterFactory::pathContainsString( 'test-1' )
			                 ),
		                 ] );

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );
		$files          = $upload_manager->filter_unique_file_list( [], 'fly://sites/4/2020/09', 'test-1.jpg' );

		$this->assertEmpty( $files );
	}

	public function test_it_finds_a_duplicate_file_of_the_same_name() {
		$this->filesystem->shouldReceive( 'listContents' )->once()->andReturn( [
			[
				'path'     => 'sites/4/2020/09/test-1.jpg',
				'basename' => 'test-1.jpg',
			],
		] );
		$this->filesystem->shouldReceive( 'createIterator' )
		                 ->once()
		                 ->andReturn( new FilesystemIterator( $this->filesystem, 'sites/4/2020/09/test-1.jpg' ), [
			                 'recursive' => false,
			                 'filter'    => FilterFactory::and(
				                 FilterFactory::isFile(),
				                 FilterFactory::pathContainsString( 'test-1' )
			                 ),
		                 ] );

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );
		$files          = $upload_manager->filter_unique_file_list( [], 'fly://sites/4/2020/09', 'test-1.jpg' );

		$this->assertCount( 1, $files );
		$this->assertContains(
			'test-1.jpg',
			$files
		);
	}

	public function test_it_finds_a_duplicate_wordpress_thumbnail_files() {
		$this->filesystem->shouldReceive( 'listContents' )->once()->andReturn( [
			[
				'path'     => 'sites/4/2020/09/test-1-150x150.jpg',
				'basename' => 'test-1-150x150.jpg',
			],
			[
				'path'     => 'sites/4/2020/09/test-1-300x300.jpg',
				'basename' => 'test-1-300x300.jpg',
			],
		] );
		$this->filesystem->shouldReceive( 'createIterator' )
		                 ->once()
		                 ->andReturn( new FilesystemIterator( $this->filesystem, 'sites/4/2020/09/test-1.jpg' ), [
			                 'recursive' => false,
			                 'filter'    => FilterFactory::and(
				                 FilterFactory::isFile(),
				                 FilterFactory::pathContainsString( 'test-1' )
			                 ),
		                 ] );

		$upload_manager = new Upload_Manager( $this->filesystem, $this->upload_dir, $this->image_manager );
		$files          = $upload_manager->filter_unique_file_list( [], 'fly://sites/4/2020/09', 'test-1.jpg' );

		$this->assertCount( 2, $files );
		$this->assertContains(
			'test-1-150x150.jpg',
			$files
		);
		$this->assertContains(
			'test-1-300x300.jpg',
			$files
		);
	}

}
