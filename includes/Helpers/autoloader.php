<?php

declare(strict_types=1);

/**
 * BizHub Autoloader
 *
 * @package BizHub
 */

namespace BizHub\Helpers;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * PSR-4 compatible autoloader for the BizHub plugin.
 */
final class Autoloader
{
	/**
	 * Base namespace.
	 */
	private const BASE_NAMESPACE = 'BizHub\\';

	/**
	 * Base directory.
	 *
	 * @var string
	 */
	private string $base_directory;

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		$this->base_directory = trailingslashit(dirname(__DIR__));
	}

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public function register(): void
	{
		spl_autoload_register([$this, 'autoload']);
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class_name Fully qualified class name.
	 *
	 * @return void
	 */
	private function autoload(string $class_name): void
	{
		if (! str_starts_with($class_name, self::BASE_NAMESPACE)) {
			return;
		}

		$relative_class = substr($class_name, strlen(self::BASE_NAMESPACE));

		if ($relative_class === false) {
			return;
		}

		$file = $this->base_directory .
			str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) .
			'.php';

		if (is_readable($file)) {
			require_once $file;
		}
	}
}