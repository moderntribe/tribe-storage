<?php

namespace Tribe\Storage\Tests\StreamWrapper;

use Tribe\Storage\Stream_Wrappers\Identity\Posix_Identifier;
use Tribe\Storage\Stream_Wrappers\Identity\Fallback_Identifier;

class IdentifierTest extends StreamWrapperTestCase {

	private $file;

	protected function setUp(): void {
		parent::setUp();

		$this->file = 'fly://uid.txt';
	}

	protected function tearDown(): void {
		@unlink( $this->file );
		parent::tearDown();
	}

	public function test_posix_identifier() {
		$uid = new Posix_Identifier();

		$this->assertSame( 3, file_put_contents( $this->file, '123' ) );

		$info = stat( $this->file );

		$this->assertSame( $info['uid'], $uid->uid() );
		$this->assertSame( $info['gid'], $uid->gid() );
	}

	public function test_fallback_identifier() {
		$uid = new Fallback_Identifier();

		$this->assertSame( 3, file_put_contents( $this->file, '123' ) );

		$info = stat( $this->file );

		$this->assertSame( $info['uid'], $uid->uid() );
		$this->assertSame( $info['gid'], $uid->gid() );
	}

}
