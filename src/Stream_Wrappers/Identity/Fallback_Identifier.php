<?php declare(strict_types=1);

namespace Tribe\Storage\Stream_Wrappers\Identity;

/**
 * Fallback to the user who owns script.
 *
 * @package Tribe\Storage\Stream_Wrappers\Identity
 */
class Fallback_Identifier implements Identifier {

	public function uid(): int {
		return (int) getmyuid();
	}

	public function gid(): int {
		return (int) getmygid();
	}

}
