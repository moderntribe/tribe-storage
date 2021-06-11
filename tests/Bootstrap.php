<?php

function tribe_data_dir( string $append_path = '' ): string {
	return __DIR__ . '/data/' . $append_path;
}
