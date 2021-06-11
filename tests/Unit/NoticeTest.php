<?php

namespace Tribe\Storage\Tests\Unit;

use Tribe\Storage\Notice;
use phpmock\mockery\PHPMockery;
use Tribe\Storage\Tests\TestCase;

class NoticeTest extends TestCase {

	public function test_it_displays_admin_notice_for_admins() {
		PHPMockery::mock( 'Tribe\Storage', 'current_user_can' )
		          ->with( 'manage_options' )
		          ->once()
		          ->andReturnTrue();
		PHPMockery::mock( 'Tribe\Storage', 'esc_attr' )->once()->andReturn( 'notice notice-error' );
		PHPMockery::mock( 'Tribe\Storage', 'esc_html' )
		          ->with( 'This would be displayed in the dashboard' )
		          ->once()
		          ->andReturn( 'This would be displayed in the dashboard' );

		$notice = new Notice();

		$exception = new \RuntimeException( 'This would be displayed in the dashboard' );

		$message = $notice->get_adapter_error_notice( $exception );

		$this->assertStringContainsString( 'This would be displayed in the dashboard', $message );
	}

	public function test_it_does_not_display_a_notice_for_non_admins() {
		PHPMockery::mock( 'Tribe\Storage', 'current_user_can' )
		          ->with( 'manage_options' )
		          ->once()
		          ->andReturnFalse();

		$notice = new Notice();

		$exception = new \RuntimeException( 'This would be displayed in the dashboard' );

		$message = $notice->get_adapter_error_notice( $exception );

		$this->assertStringNotContainsString( 'This would be displayed in the dashboard', $message );
	}

}
