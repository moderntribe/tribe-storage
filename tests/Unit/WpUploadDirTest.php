<?php

namespace Tribe\Storage\Tests\Unit;

use phpmock\mockery\PHPMockery;
use Tribe\Storage\Tests\TestCase;
use Tribe\Storage\Uploads\Wp_Upload_Dir;

class WpUploadDirTest extends TestCase {

	public function test_it_gets_an_upload_dir() {
		PHPMockery::mock( 'Tribe\Storage\Uploads', 'get_current_blog_id' )
		          ->once()
		          ->andReturn( 1 );

		$wp_upload_dir = new Wp_Upload_Dir();

		$wp_upload_dir->add_dir( [
			'path'    => '/application/www/wp-content/uploads',
			'baseurl' => 'https://example.com/wp-content/uploads/sites/3',
		] );

		$this->assertSame( '/application/www/wp-content/uploads', $wp_upload_dir->original_dir()['path'] );
		$this->assertSame( 'https://example.com/wp-content/uploads/sites/3', $wp_upload_dir->original_dir()['baseurl'] );
	}

	public function test_it_gets_a_subsite_upload_dir() {
		PHPMockery::mock( 'Tribe\Storage\Uploads', 'get_current_blog_id' )
		          ->once()
		          ->andReturn( 5 );

		$wp_upload_dir = new Wp_Upload_Dir();

		$wp_upload_dir->add_dir( [
			'path'    => '/application/www/wp-content/uploads',
			'baseurl' => 'https://example.com/wp-content/uploads/sites/5',
		] );

		$this->assertSame( '/application/www/wp-content/uploads', $wp_upload_dir->original_dir( 5 )['path'] );
		$this->assertSame( 'https://example.com/wp-content/uploads/sites/5', $wp_upload_dir->original_dir( 5 )['baseurl'] );
	}

	public function test_it_can_add_an_original_dir_from_another_blog() {
		$current_blog_id = PHPMockery::mock( 'Tribe\Storage\Uploads', 'get_current_blog_id' );

		$current_blog_id->once()->andReturn( 5 );

		$wp_upload_dir = new Wp_Upload_Dir();

		$wp_upload_dir->add_dir( [
			'path'    => '/application/www/wp-content/uploads',
			'baseurl' => 'https://example.com/wp-content/uploads/sites/5',
		] );

		$this->assertSame( '/application/www/wp-content/uploads', $wp_upload_dir->original_dir( 5 )['path'] );
		$this->assertSame( 'https://example.com/wp-content/uploads/sites/5', $wp_upload_dir->original_dir( 5 )['baseurl'] );

		$current_blog_id->once()->andReturn( 10 );

		$wp_upload_dir->add_dir( [
			'path'    => '/application/www/wp-content/uploads',
			'baseurl' => 'https://example.com/wp-content/uploads/sites/10',
		] );

		$this->assertSame( '/application/www/wp-content/uploads', $wp_upload_dir->original_dir( 10 )['path'] );
		$this->assertSame( 'https://example.com/wp-content/uploads/sites/10', $wp_upload_dir->original_dir( 10 )['baseurl'] );
	}

}
