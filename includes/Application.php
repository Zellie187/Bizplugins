<?php

declare(strict_types=1);

/**
 * BizHub Application Bootstrap
 *
 * @package BizHub
 */

namespace BizHub;

use BizHub\Helpers\Autoloader;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Main application container.
 */
final class Application
{
	/**
	 * Plugin version.
	 */
	private const VERSION = '1.1.0';

	/**
	 * Application instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get application instance.
	 *
	 * @return self
	 */
	public static function instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Bootstrap application.
	 *
	 * @return void
	 */
	public function boot(): void
	{
		$this->register_autoloader();
		$this->load_hooks();
	}

	/**
	 * Register plugin autoloader.
	 *
	 * @return void
	 */
	private function register_autoloader(): void
	{
		$autoloader = new Autoloader();
		$autoloader->register();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function load_hooks(): void
	{
		add_action(
			'plugins_loaded',
			[$this, 'loaded']
		);
	}

	/**
	 * Plugin loaded callback.
	 *
	 * @return void
	 */
	public function loaded(): void
	{
		/**
		 * Fires after BizHub has loaded.
		 *
		 * @since 1.1.0
		 */
		do_action('bizhub_loaded');
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	public function version(): string
	{
		return self::VERSION;
	}
}