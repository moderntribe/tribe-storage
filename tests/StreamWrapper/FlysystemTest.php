<?php

namespace Tribe\Storage\Tests\StreamWrapper;

use League\Flysystem\Adapter\Local;

class FlysystemTest extends StreamWrapperTestCase {

	public function test_adapter() {
		$this->assertInstanceOf( Local::class, $this->filesystem->getAdapter() );
	}
}
