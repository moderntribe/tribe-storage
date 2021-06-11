<?php

namespace Tribe\Storage\Tests\StreamWrapper;

use Tribe\Storage\Stream_Wrappers\StreamWrapper;

class WrapperTest extends StreamWrapperTestCase {

	public function test_it_finds_registered_adapter() {
		$wrappers = stream_get_wrappers();

		$this->assertContains( StreamWrapper::DEFAULT_PROTOCOL, $wrappers );
	}
}
