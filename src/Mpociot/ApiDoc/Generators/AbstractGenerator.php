<?php

namespace Mpociot\ApiDoc\Generators;

use Exception;
use Faker\Factory;
use ReflectionClass;
use Illuminate\Support\Str;
use Illuminate\Routing\Route;
use Mpociot\Reflection\DocBlock;
use Mpociot\Reflection\DocBlock\Tag;

abstract class AbstractGenerator
{

	/**
	 * @param Route $route
	 *
	 * @return mixed
	 */
	public function getUri(Route $route)
	{
		return $route->uri();
	}

	/**
	 * @param Route $route
	 *
	 * @return mixed
	 */
	public function getMethods(Route $route)
	{
		return array_diff($route->methods(), ['HEAD']);
	}

	/**
	 * @param \Illuminate\Routing\Route $route
	 *
	 * @return array
	 * @throws \ReflectionException
	 */
	public function processRoute(Route $route)
	{
		$routeAction = $route->getAction();
		$routeGroup  = $this->getRouteGroup($routeAction['uses']);
		$docBlock    = $this->parseDocBlock($routeAction['uses']);
		$content     = $this->getResponse($docBlock['tags']);
		$parameters  = $this->getParametersFromDocBlock($docBlock['tags']);

		return [
			'id'                 => md5($this->getUri($route) . ':' . implode($this->getMethods($route))),
			'resource'           => $routeGroup,
			'title'              => $docBlock['short'],
			'description'        => $docBlock['long'],
			'methods'            => $this->getMethods($route),
			'uri'                => $this->getUri($route),
			'unauthenticated'    => $this->getDocUnauthenticated($docBlock['tags']),
			'permission'         => $this->getDocPermission($docBlock['tags']),
			'parameters'         => $parameters,
			'has_file_parameter' => $this->hasFileParameter($parameters),
			'response'           => $content,
			'showresponse'       => !empty($content),
		];
	}

	/**
	 * Get Unauthenticated
	 *
	 * @param array $tags
	 *
	 * @return mixed
	 */
	protected function getDocUnauthenticated($tags)
	{
		$responseTags = array_filter($tags, function ($tag) {
			if( !($tag instanceof Tag) )
			{
				return false;
			}

			return strtolower($tag->getName()) == 'unauthenticated';
		});

		return !empty($responseTags);
	}

	/**
	 * Get Permission
	 *
	 * @param array $tags
	 *
	 * @return mixed
	 */
	protected function getDocPermission($tags)
	{
		$responseTags = array_filter($tags, function ($tag) {
			return $tag instanceof Tag && strtolower($tag->getName()) == 'permission';
		});

		if( empty($responseTags) )
		{
			return null;
		}

		$responseTag = \array_first($responseTags);

		return $responseTag->getContent();
	}

	/**
	 * Get the response from the docblock if available.
	 *
	 * @param array $tags
	 *
	 * @return mixed
	 */
	protected function getDocblockResponse($tags)
	{
		$responseTags = array_filter($tags, function ($tag) {
			return $tag instanceof Tag && strtolower($tag->getName()) == 'response';
		});

		if( empty($responseTags) )
		{
			return null;
		}

		$responseTag = \array_first($responseTags);

		return \response(json_encode($responseTag->getContent()), 200, ['Content-Type' => 'application/json']);
	}

	/**
	 * @param array $tags
	 *
	 * @return mixed
	 */
	protected function getParametersFromDocBlock($tags)
	{
		$parameters = collect($tags)->filter(function ($tag) {
			return $tag instanceof Tag && $tag->getName() === 'bodyParam';
		})->mapWithKeys(function ($tag) {
			preg_match('/(.+?)\s+(.+?)\s+(.+?)\s+(.*)/', $tag->getContent(), $content);

			if( empty($content) )
			{
				list($name, $type, $populateType) = preg_split('/\s+/', $tag->getContent());

				$description = '';
			}
			else
			{
				list($_, $name, $type, $populateType, $description) = $content;

				$description = trim($description);
			}

			$required = !(starts_with($name, '[') AND ends_with($name, ']'));
			$name     = str_replace(['[', ']'], '', $name);

			$type  = $this->normalizeParameterType($type);
			$value = $this->generateDummyValue($populateType);

			return [$name => compact('type', 'description', 'required', 'value')];
		})->toArray();

		return $parameters;
	}

	/**
	 * @param array $parameters
	 *
	 * @return mixed
	 */
	protected function hasFileParameter($parameters)
	{
		if( count($parameters) )
		{
			foreach( $parameters as $parameter )
			{
				if( $parameter['type'] === 'file' )
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param  \Illuminate\Routing\Route $route
	 *
	 * @return array
	 *
	 * @throws \ReflectionException
	 */
	protected function parseDocBlock($route)
	{
		list($class, $method) = explode('@', $route);
		$reflection       = new ReflectionClass($class);
		$reflectionMethod = $reflection->getMethod($method);
		$comment          = $reflectionMethod->getDocComment();
		$phpdoc           = new DocBlock($comment);

		return [
			'short' => $phpdoc->getShortDescription(),
			'long'  => $phpdoc->getLongDescription()->getContents(),
			'tags'  => $phpdoc->getTags(),
		];
	}

	/**
	 * @param Route $route
	 *
	 * @return string
	 * @throws \ReflectionException
	 */
	protected function getRouteGroup($route)
	{
		list($class, $method) = explode('@', $route);
		$reflection = new ReflectionClass($class);
		$comment    = $reflection->getDocComment();

		if( $comment )
		{
			$phpdoc = new DocBlock($comment);
			foreach( $phpdoc->getTags() as $tag )
			{
				if( $tag->getName() === 'resource' )
				{
					return $tag->getContent();
				}
			}
		}

		return 'general';
	}

	/**
	 * Call the given URI and return the Response.
	 *
	 * @param  string $method
	 * @param  string $uri
	 * @param  array  $parameters
	 * @param  array  $cookies
	 * @param  array  $files
	 * @param  array  $server
	 * @param  string $content
	 *
	 * @return \Illuminate\Http\Response
	 */
	abstract public function callRoute($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null);

	/**
	 * Transform headers array to array of $_SERVER vars with HTTP_* format.
	 *
	 * @param  array $headers
	 *
	 * @return array
	 */
	protected function transformHeadersToServerVars(array $headers)
	{
		$server = [];
		$prefix = 'HTTP_';

		foreach( $headers as $name => $value )
		{
			$name = strtr(strtoupper($name), '-', '_');

			if( !Str::startsWith($name, $prefix) && $name !== 'CONTENT_TYPE' )
			{
				$name = $prefix . $name;
			}

			$server[$name] = $value;
		}

		return $server;
	}

	/**
	 * @param $response
	 *
	 * @return mixed
	 */
	private function getResponseContent($response)
	{
		if( empty($response) )
		{
			return '';
		}

		if( $response->headers->get('Content-Type') === 'application/json' )
		{
			$content = json_decode($response->getContent(), JSON_PRETTY_PRINT);
		}
		else
		{
			$content = $response->getContent();
		}

		return $content;
	}

	/**
	 * Normalizes a rule so that we can accept short types.
	 *
	 * @param  string $rule
	 *
	 * @return string
	 */
	protected function normalizeRule($rule)
	{
		switch( $rule )
		{
			case 'int':
				return 'integer';
			case 'bool':
				return 'boolean';
			default:
				return $rule;
		}
	}

	/**
	 * @param array $annotationTags
	 *
	 * @return mixed|string
	 */
	private function getResponse(array $annotationTags)
	{
		$response = null;

		if( $docblockResponse = $this->getDocblockResponse($annotationTags) )
		{
			// we have a response from the docblock ( @response )
			$response = $docblockResponse;
		}

		$content = $response ? $this->getResponseContent($response) : null;

		return $content;
	}

	private function normalizeParameterType($type)
	{
		$typeMap = [
			'int'    => 'integer',
			'bool'   => 'boolean',
			'double' => 'float',
		];

		return $type ? ($typeMap[$type] ?? $type) : 'string';
	}

	private function generateDummyValue(string $type)
	{
		$faker = Factory::create(config('app.faker_locale'));

		$fakes = [
			'integer'  => function () {
				return rand(1, 11);
			},
			'number'   => function () use ($faker) {
				return $faker->randomFloat();
			},
			'float'    => function () use ($faker) {
				return $faker->randomFloat();
			},
			'boolean'  => function () use ($faker) {
				return $faker->boolean();
			},
			'string'   => function () use ($faker) {
				return $faker->title;
			},
			'text'     => function () use ($faker) {
				return $faker->text;
			},
			'html'     => function () use ($faker) {
				return '<p>' . implode('</p><p>', $faker->paragraphs(2)) . '</p>';
			},
			'array'    => function () {
				return '[]';
			},
			'object'   => function () {
				return '{}';
			},
			'email'    => function () use ($faker) {
				return $faker->unique()->safeEmail;
			},
			'password' => function () {
				return str_random(10);
			},
			'name'     => function () use ($faker) {
				return $faker->name;
			},
			'token'    => function () use ($faker) {
				return str_random(60);
			},
			'file'     => function () use ($faker) {
				return '{file}';
			},
		];

		if( isset($fakes[$type]) )
		{
			return $fakes[$type]();
		}

		$explode_type = explode('(', $type);

		try
		{
			$value = '';

			if( isset($explode_type[1]) )
			{
				$explode_type[1] = str_replace(')', '', $explode_type[1]);

				// Status value
				if( $explode_type[0] === 'static' )
				{
					$value = $explode_type[1];
				}
				else
				{
					$params = explode(',', $explode_type[1]);

					foreach( $params as $key => $param )
					{
						if( $param === 'false' )
						{
							$params[$key] = false;
						}
						elseif( $param === 'true' )
						{
							$params[$key] = true;
						}
					}

					$value = $faker->{$explode_type[0]}(...$params);
				}
			}
			else
			{
				$value = $faker->{$explode_type[0]};
			}

			return $value;
		}
		catch( Exception $exception )
		{
		}

		return isset($fakes[$type]) ? $fakes[$type]() : $fakes['string']();
	}
}
