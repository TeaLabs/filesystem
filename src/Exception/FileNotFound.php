<?php
namespace Tea\Filesystem\Exception;

use Illuminate\Contracts\Filesystem\FileNotFoundException;

class FileNotFound extends FileNotFoundException
{
	public static function create($path, $message = "File does not exist.")
	{
		return new static("{$message}. Path `{$path}`.");
	}
}
