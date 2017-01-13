<?php
namespace Tea\Filesystem;

use Closure;
use Tea\Uzi\Uzi;
use ErrorException;
use FilesystemIterator;
use Tea\Filesystem\Exception\FileNotFound;
use Tea\Filesystem\Exception\FileNotReadable;
use Tea\Contracts\Filesystem\Filesystem as Contract;
use Illuminate\Filesystem\Filesystem as IlluminateFilesystem;
use TeaPress\Contracts\Filesystem\Compiler as CompilerContract;

class Filesystem extends IlluminateFilesystem implements Contract
{
	/**
	 * Get the contents of a file.
	 *
	 * @param  string  $path
	 * @return string
	 *
	 * @throws \TeaPress\Filesystem\Exception\FileNotFound
	 */
	public function get($path)
	{
		if ($this->isFile($path))
			return file_get_contents($path);

		throw FileNotFound::create($path);
	}

	/**
	 * Get the returned value of a file.
	 *
	 * @param  string  $path
	 * @return mixed
	 *
	 * @throws \TeaPress\Filesystem\Exception\FileNotFound
	 */
	public function getRequire($path)
	{
		if ($this->isFile($path))
			return require $path;

		throw FileNotFound::create($path);
	}

	/**
	 * Require the given file once.
	 *
	 * @param  string  $file
	 * @return mixed
	 *
	 * @throws \TeaPress\Filesystem\Exception\FileNotFound
	 */
	public function requireOnce($file)
	{
		if($this->isFile($file))
			return require_once $file;

		throw FileNotFound::create($file);
	}

	/**
	 * Determine if the given path is readable.
	 *
	 * @param  string  $path
	 *
	 * @return bool
	 */
	public function isReadable($path)
	{
		return is_readable($path);
	}

	/**
	 * Determine if the given path is a readable file.
	 *
	 * @param  string  $path
	 *
	 * @return bool
	 */
	public function isReadableFile($path)
	{
		return is_readable($path) && is_file($path);
	}

	/**
	 * Determine if the given path is writable.
	 *
	 * @param  string  $path
	 * @return bool
	 */
	public function isWritable($path)
	{
		return is_writable($path);
	}

	/**
	 * Determine if the given path is a writable file.
	 *
	 * @param  string  $path
	 * @return bool
	 */
	public function isWritableFile($path)
	{
		return is_writable($path) && is_file($path);
	}

	/**
	 * Determine if the given path is a symbolic.
	 *
	 * @param  string  $path
	 * @return bool
	 */
	public function isLink($file)
	{
		return is_link($file);
	}

	/**
	 * Get an array of all files in a directory.
	 *
	 * Provide a boolean value (true/false) for recursive to turn it on/off,
	 * an integer or string expression to specify the search depth.
	 *
	 * You can pass filters to customize the search results.
	 *
	 * For example :
	 * 		$filters = ['name' => '*.php', 'path' => ['src', 'tests' ] ]
	 * 	Searches for files with a .php extension whose path consists of a
	 * 	directory/sub-directory named 'src' or 'tests'.
	 *
	 * You can provide an array of filters with string keys as the finder's method to be
	 * called with the value passed as a parameter.
	 *
	 * If a string or integer indexed array is provided, the finder's 'name' method is used.
	 * So all the following will return the same results
	 * 		$filters = '*.txt'
	 * 		$filters = ['*.txt']
	 * 		$filters = ['name' => '*.txt']
	 *
	 * Valid filter methods methods are basically all methods in
	 * Symfony\Component\Finder\Finder and \TeaPress\Filesystem\Finder
	 * classes that return a Finder instance.
	 *
	 * You can specify a string (for one) or an array of snake_cased properties to retrieve
	 * from from the file info (SplFileInfo) object. Though SplFileInfo has no public properties,
	 * the properties will be retrieved by calling the respective getter method(s).
	 * If properties is false (bool) the entire SplFileInfo objects are returned.
	 *
	 * 		How it works:
	 * 			Property			Method Called
	 * 			basename			: SplFileInfo::getBasename();
	 * 			real_path			: SplFileInfo::getRealPath();
	 * 			is_dir				: SplFileInfo::isDir();
	 *
	 * 		You can refer to \TeaPress\Filesystem\Finder for more info
	 *
	 *
	 * @param  string  				$directory
	 * @param  bool|int|string		$recursive 		true/false = on/off. Int/string for a search depth.
	 * @param  string|array|null	$filters 		Check above.
	 * @param  string|array|bool	$properties		The properties to retrieve.
	 * @param  int 					$limit 			Limit the number of files retrieved.
	 * @return array
	 */
	public function files($directory, $recursive = false, $filters = null, $properties = null, $limit = null)
	{
		$depth = !is_bool($recursive) ? $recursive : ($recursive ? null : 0);

		$finder = $this->findFiles($directory, $depth);

		if(is_string($filters)){
			$filters = ['name' => $filters];
		}

		foreach ( (array) $filters as $method => $patterns) {

			$method = Uzi::camel( is_string($method) ? $method : 'name');

			foreach ((array) $patterns as $pattern) {
				$finder->{$method}($pattern);
			}
		}

		$properties = $properties === false ? null : (is_null($properties) ? 'pathname' : $properties);

		return $finder->get($properties, $limit);
	}

	/**
	 * Get all of the files from the given directory (recursive).
	 * You can specify the properties to retrieve.
	 * Returns entire SplFileInfo object if properties = null.
	 *
	 * @param  string|null 			$directory
	 * @param  string|array|null	$properties
	 *
	 * @return array
	 */
	public function allFiles($directory, $properties = null)
	{
		return $this->findFiles($directory)->get($properties);
	}

	/**
	 * Get an iterator for all of the files in the given directory.
	 *
	 * @param  string  $directory
	 * @param  int|null  $depth
	 *
	 * @return \TeaPress\Filesystem\Finder
	 */
	public function findFiles($directory, $depth = null)
	{
		if(is_null($depth))
			return $this->finder()->files()->in($directory);
		else
			return $this->finder()->files()->in($directory)->depth($depth);
	}

	/**
	 * Get all of the directories within a given directory.
	 *
	 * Provide a boolean value (true/false) for recursive to turn it on/off,
	 * an integer or string expression to specify the search depth.
	 *
	 * You can pass filters to customize the search results.
	 *
	 * For example :
	 * 		$filters = ['name' => 'prefix_*', 'path' => ['src', 'tests' ] ]
	 * 	Searches for directories whose names start with a 'prefix_' and whose path consists of a
	 * 	directory/sub-directory named 'src' or 'tests'.
	 *
	 * You can provide an array of filters with string keys as the finder's method to be
	 * called with the value passed as a parameter.
	 *
	 * If a string or integer indexed array is provided, the finder's 'name' method is used.
	 * So all the following will return the same results
	 * 		$filters = '*_suffix'
	 * 		$filters = ['*_suffix']
	 * 		$filters = ['name' => '*_suffix']
	 *
	 * Valid filter methods methods are basically all methods in
	 * Symfony\Component\Finder\Finder and \TeaPress\Filesystem\Finder
	 * classes that return a Finder instance.
	 *
	 * You can specify a string (for one) or an array of snake_cased properties to retrieve
	 * from from the file info (SplFileInfo) object. Though SplFileInfo has no public properties,
	 * the properties will be retrieved by calling the respective getter method(s).
	 * If properties is false (bool) the entire SplFileInfo objects are returned.
	 *
	 * 		How it works:
	 * 			Property			Method Called
	 * 			basename			: SplFileInfo::getBasename();
	 * 			real_path			: SplFileInfo::getRealPath();
	 * 			is_dir				: SplFileInfo::isDir();
	 *
	 * 		You can refer to \TeaPress\Filesystem\Finder for more info
	 *
	 *
	 * @param  string  				$directory
	 * @param  bool|int|string		$recursive 		true/false = on/off. Int/string for a search depth.
	 * @param  string|array|null	$filters 		Check above.
	 * @param  string|array|bool	$properties		The properties to retrieve.
	 * @param  int 					$limit 			Limit the number of directories retrieved.
	 *
	 * @return array
	 */
	public function directories($directory, $recursive = false, $filters = null, $properties = null, $limit = null)
	{
		$depth = !is_bool($recursive) ? $recursive : ($recursive ? null : 0);

		$finder = $this->findDirs($directory, $depth);

		if(is_string($filters)){
			$filters = ['name' => $filters];
		}

		foreach ( (array) $filters as $method => $patterns) {

			$method = Uzi::camel( is_string($method) ? $method : 'name');

			foreach ((array) $patterns as $pattern) {
				$finder->{$method}($pattern);
			}
		}

		$properties = $properties === false ? null : (is_null($properties) ? 'pathname' : $properties);

		return $finder->get($properties, $limit);
	}



	/**
	 * Get all (recursive) of the directories within a given directory.
	 * You can specify the properties to retrieve.
	 * Returns entire SplFileInfo object if properties = null.
	 *
	 * @param  string|null 			$directory
	 * @param  string|array|null	$properties
	 *
	 * @return array
	 */
	public function allDirs($directory, $properties = null)
	{
		return $this->allDirectories($directory, $properties);
	}

	/**
	 * Get all (recursive) of the directories within a given directory.
	 * You can specify the properties to retrieve.
	 * Returns entire SplFileInfo object if properties = null.
	 *
	 * @param  string|null 			$directory
	 * @param  string|array|null	$properties
	 *
	 * @return array
	 */
	public function allDirectories($directory = null, $properties = null)
	{
		return $this->findDirs($directory)->get($properties);
	}

	/**
	 * Get an iterator for all of the directories in the given path.
	 *
	 * @param  string  	$path
	 * @param  int 		$depth
	 *
	 * @return \TeaPress\Filesystem\Finder
	 */
	public function findDirs($path, $depth = null)
	{
		if(is_null($depth))
			return $this->finder()->in($path)->directories();
		else
			return $this->finder()->in($path)->directories()->depth($depth);
	}

	/**
	 * Create a directory.
	 *
	 * @param  string  $path
	 * @param  int     $mode
	 * @param  bool    $recursive
	 * @param  bool    $force
	 * @return bool
	 */
	public function makeDirectory($path, $mode = 0755, $recursive = true, $force = false)
	{
		if ($force) {
			return @mkdir($path, $mode, $recursive);
		}

		return mkdir($path, $mode, $recursive);
	}

	/**
	 * Copy a directory from one location to another.
	 *
	 * @param  string  $directory
	 * @param  string  $destination
	 * @param  int     $options
	 * @return bool
	 */
	public function copyDirectory($directory, $destination, $options = null)
	{
		if (! $this->isDirectory($directory)) {
			return false;
		}

		$options = $options ?: FilesystemIterator::SKIP_DOTS;

		// If the destination directory does not actually exist, we will go ahead and
		// create it recursively, which just gets the destination prepared to copy
		// the files over. Once we make the directory we'll proceed the copying.
		if (! $this->isDirectory($destination)) {
			$this->makeDirectory($destination, 0777, true);
		}

		$items = new FilesystemIterator($directory, $options);

		foreach ($items as $item) {
			// As we spin through items, we will check to see if the current file is actually
			// a directory or a file. When it is actually a directory we will need to call
			// back into this function recursively to keep copying these nested folders.
			$target = $destination.'/'.$item->getBasename();

			if ($item->isDir()) {
				$path = $item->getPathname();

				if (! $this->copyDirectory($path, $target, $options)) {
					return false;
				}
			}

			// If the current items is just a regular file, we will just copy this to the new
			// location and keep looping. If for some reason the copy fails we'll bail out
			// and return false, so the developer is aware that the copy process failed.
			else {
				if (! $this->copy($item->getPathname(), $target)) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Recursively delete a directory.
	 *
	 * The directory itself may be optionally preserved.
	 *
	 * @param  string  $directory
	 * @param  bool    $preserve
	 * @return bool
	 */
	public function deleteDirectory($directory, $preserve = false)
	{
		if (! $this->isDirectory($directory)) {
			return false;
		}

		$items = new FilesystemIterator($directory);

		foreach ($items as $item) {
			// If the item is a directory, we can just recurse into the function and
			// delete that sub-directory otherwise we'll just delete the file and
			// keep iterating through each file until the directory is cleaned.
			if ($item->isDir() && ! $item->isLink()) {
				$this->deleteDirectory($item->getPathname());
			}

			// If the item is just a file, we can go ahead and delete it since we're
			// just looping through and waxing all of the files in this directory
			// and calling directories recursively, so we delete the real path.
			else {
				$this->delete($item->getPathname());
			}
		}

		if (! $preserve) {
			@rmdir($directory);
		}

		return true;
	}

	/**
	 * Empty the specified directory of all files and folders.
	 *
	 * @param  string  $directory
	 * @return bool
	 */
	public function cleanDirectory($directory)
	{
		return $this->deleteDirectory($directory, true);
	}

	/**
	 * Create a Finder instance
	 *
	 * @throws \TeaPress\Filesystem\Finder
	 */
	public function finder()
	{
		return Finder::create();
	}

	/**
	 * Get the script compiler instance.
	 *
	 * @return \TeaPress\Contracts\Filesystem\Compiler
	 */
	public function getCompiler()
	{
		return $this->compiler;
	}

	/**
	 * Set the script compiler instance.
	 *
	 * @param  \TeaPress\Contracts\Filesystem\Compiler  $container
	 * @return void
	 */
	public function setCompiler(CompilerContract $compiler)
	{
		$this->compiler = $compiler;
	}
}
