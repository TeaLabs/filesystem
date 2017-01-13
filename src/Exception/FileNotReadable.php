<?php
namespace Tea\Filesystem\Exception;

use Exception;

class FileNotReadable extends Exception
{
	public static function create($path, $message = "Unable to read file.")
	{
		return new static("{$message}. Path `{$path}`.");
	}
}
