<?php

namespace Mpociot\ApiDoc\Commands;

use ReflectionClass;
use Illuminate\Routing\Route;
use Illuminate\Console\Command;
use Mpociot\Reflection\DocBlock;
use Illuminate\Support\Collection;
use Mpociot\Documentarian\Documentarian;
use Mpociot\ApiDoc\Postman\CollectionWriter;
use Mpociot\ApiDoc\Generators\LaravelGenerator;
use Mpociot\ApiDoc\Generators\AbstractGenerator;
use Illuminate\Support\Facades\Route as RouteFacade;

class GenerateDocumentation extends Command
{

	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'api:generate
                            {--output=public/docs : The output path for the generated documentation}
                            {--routePrefix= : The route prefix (or prefixes) to use for generation}
                            {--routes=* : The route names to use for generation}
                            {--postmanName= : Name Postman Collection}
                            {--authProvider=users : The authentication provider to use for API response calls}
                            {--authGuard=web : The authentication guard to use for API response calls}
                            {--actAsUserId= : The user ID to use for API response calls}
    ';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate your API documentation from existing Laravel routes.';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return false|null
	 */
	public function handle()
	{
		$generator     = new LaravelGenerator();
		$allowedRoutes = $this->option('routes');
		$routePrefix   = $this->option('routePrefix');

		$this->setUserToBeImpersonated($this->option('actAsUserId'));

		if( $routePrefix === null && !count($allowedRoutes) )
		{
			$this->error('You must provide either a route prefix or a route to generate the documentation.');

			return false;
		}

		$routePrefixes = explode(',', $routePrefix ? : '*');

		$parsedRoutes = [];

		foreach( $routePrefixes as $routePrefix )
		{
			$parsedRoutes += $this->processRoutes($generator, $allowedRoutes, $routePrefix);
		}

		$parsedRoutes = collect($parsedRoutes)->groupBy('resource')->sort(function ($a, $b) {
			return strcmp($a->first()['resource'], $b->first()['resource']);
		});

		$this->writeMarkdown($parsedRoutes);
	}

	/**
	 * @param  Collection $parsedRoutes
	 *
	 * @return void
	 */
	private function writeMarkdown($parsedRoutes)
	{
		$outputPath  = $this->option('output');
		$targetFile  = $outputPath . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'index.md';
		$prependFile = $outputPath . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'prepend.md';
		$appendFile  = $outputPath . DIRECTORY_SEPARATOR . 'source' . DIRECTORY_SEPARATOR . 'append.md';

		$infoText = view('apidoc::partials.info')->with('outputPath', ltrim($outputPath, 'public/'));

		$parsedRouteOutput = $parsedRoutes->map(function ($routeGroup) {
			return $routeGroup->map(function ($route) {
				$route['output'] = (string) view('apidoc::partials.route')->with('parsedRoute', $route)->render();

				return $route;
			});
		});

		$frontmatter = view('apidoc::partials.frontmatter');

		$prependFileContents = file_exists($prependFile) ? file_get_contents($prependFile) . "\n" : '';
		$appendFileContents  = file_exists($appendFile) ? "\n" . file_get_contents($appendFile) : '';

		$documentarian = new Documentarian();

		$markdown = view('apidoc::documentarian')
			->with('writeCompareFile', false)
			->with('frontmatter', $frontmatter)
			->with('infoText', $infoText)
			->with('prependMd', $prependFileContents)
			->with('appendMd', $appendFileContents)
			->with('outputPath', $this->option('output'))
			->with('parsedRoutes', $parsedRouteOutput);

		if( !is_dir($outputPath) )
		{
			$documentarian->create($outputPath);
		}

		// Write output file
		file_put_contents($targetFile, $markdown);

		$this->info('Wrote index.md to: ' . $outputPath);

		$this->info('Generating API HTML code');

		// Copy logo to source
		$this->copyLogoToSource($outputPath);

		$documentarian->generate($outputPath);

		$this->info('Wrote HTML documentation to: ' . $outputPath . '/index.html');

		$this->info('Generating Postman collection');

		$postman = $this->generatePostmanCollection($parsedRoutes);

		file_put_contents($outputPath . DIRECTORY_SEPARATOR . 'collection.json', $postman['collection']);

		file_put_contents($outputPath . DIRECTORY_SEPARATOR . 'environment.json', $postman['environment']);
	}

	/**
	 * @param $actAs
	 */
	private function setUserToBeImpersonated($actAs)
	{
		if( !empty($actAs) )
		{
			if( version_compare($this->laravel->version(), '5.2.0', '<') )
			{
				$userModel = config('auth.model');
				$user      = $userModel::find($actAs);
				$this->laravel['auth']->setUser($user);
			}
			else
			{
				$provider  = $this->option('authProvider');
				$userModel = config("auth.providers.$provider.model");
				$user      = $userModel::find($actAs);
				$this->laravel['auth']->guard($this->option('authGuard'))->setUser($user);
			}
		}
	}

	/**
	 * @return mixed
	 */
	private function getRoutes()
	{
		return RouteFacade::getRoutes();
	}

	/**
	 * @param AbstractGenerator $generator
	 * @param array             $allowedRoutes
	 * @param                   $routePrefix
	 *
	 * @return array
	 */
	private function processRoutes(AbstractGenerator $generator, array $allowedRoutes, $routePrefix)
	{
		$routes       = $this->getRoutes();
		$parsedRoutes = [];
		foreach( $routes as $route )
		{
			/** @var Route $route */
			if( in_array($route->getName(), $allowedRoutes) || str_is($routePrefix, $generator->getUri($route)) )
			{
				if( $this->isValidRoute($route) && $this->isRouteVisibleForDocumentation($route->getAction()['uses']) )
				{
					$parsedRoutes[] = $generator->processRoute($route);
					$this->info('Processed route: [' . implode(',', $generator->getMethods($route)) . '] ' . $generator->getUri($route));
				}
				else
				{
					$this->warn('Skipping route: [' . implode(',', $generator->getMethods($route)) . '] ' . $generator->getUri($route));
				}
			}
		}

		return $parsedRoutes;
	}

	/**
	 * @param $route
	 *
	 * @return bool
	 */
	private function isValidRoute($route)
	{
		return !is_callable($route->getAction()['uses']) && !is_null($route->getAction()['uses']);
	}

	/**
	 * @param $route
	 *
	 * @return bool
	 */
	private function isRouteVisibleForDocumentation($route)
	{
		list($class, $method) = explode('@', $route);
		$reflection = new ReflectionClass($class);
		$comment    = $reflection->getMethod($method)->getDocComment();
		if( $comment )
		{
			$phpdoc = new DocBlock($comment);

			return collect($phpdoc->getTags())->filter(function ($tag) use ($route) {
				return $tag->getName() === 'hideFromAPIDocumentation';
			})->isEmpty();
		}

		return true;
	}

	/**
	 * Generate Postman collection JSON file.
	 *
	 * @param Collection $routes
	 *
	 * @return array
	 */
	private function generatePostmanCollection(Collection $routes)
	{
		$writer = new CollectionWriter($routes, $this->options());

		return $writer->getCollection();
	}

	/**
	 * Copy logo to source
	 *
	 * @param $folder
	 *
	 * @return string
	 */
	private function copyLogoToSource($folder)
	{
		$source_dir = $folder . '/source';

		if( !is_dir($source_dir) )
		{
			return false;
		}

		$logo_path = public_path('images/logo.png');

		if( is_file($logo_path) )
		{
			copy(public_path('images/logo.png'), $source_dir . '/assets/images/logo.png');
		}
	}
}
