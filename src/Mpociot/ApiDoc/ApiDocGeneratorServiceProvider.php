<?php

namespace Mpociot\ApiDoc;

use Illuminate\Support\ServiceProvider;
use Mpociot\ApiDoc\Commands\GenerateDocumentation;

class ApiDocGeneratorServiceProvider extends ServiceProvider
{

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->loadViewsFrom(__DIR__ . '/../../resources/views/', 'apidoc');

		$this->publishes([
			__DIR__ . '/../../resources/views' => $this->resource_path('views/vendor/apidoc'),
		]);
	}

	/**
	 * Register the API doc commands.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->singleton('apidoc.generate', function () {
			return new GenerateDocumentation();
		});

		$this->commands([
			'apidoc.generate',
		]);
	}

	/**
	 * Return a fully qualified path to a given file.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	public function resource_path($path = '')
	{
		return app()->basePath() . '/resources' . ($path ? '/' . $path : $path);
	}
}
