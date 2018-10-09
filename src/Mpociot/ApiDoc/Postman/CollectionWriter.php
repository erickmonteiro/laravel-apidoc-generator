<?php

namespace Mpociot\ApiDoc\Postman;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Collection;

class CollectionWriter
{

	/**
	 * @var Collection
	 */
	private $routeGroups;

	private $options = [];

	/**
	 * CollectionWriter constructor.
	 *
	 * @param Collection $routeGroups
	 */
	public function __construct(Collection $routeGroups, $options)
	{
		$this->routeGroups = $routeGroups;
		$this->options     = $options;
	}

	public function getCollection()
	{
		$collection = [
			'variables' => [],
			'info'      => [
				'name'        => isset($this->options['postmanName']) ? $this->options['postmanName'] : '',
				'_postman_id' => Uuid::uuid4()->toString(),
				'description' => '',
				'schema'      => 'https://schema.getpostman.com/json/collection/v2.0.0/collection.json',
			],
			'item'      => $this->routeGroups->map(function ($routes, $groupName) {
				return [
					'name'        => $groupName,
					'description' => '',
					'item'        => $routes->map(function ($route) {
						$auth = [
							'type' => 'noauth',
						];

						if( !$route['unauthenticated'] )
						{
							$auth = [
								'type'   => 'bearer',
								'bearer' => [
									'token' => '{{access_token}}'
								]
							];
						}

						return [
							'name'    => $route['title'] != '' ? $route['title'] : url($route['uri']),
							'request' => [
								'url'         => url($route['uri']),
								'method'      => $route['methods'][0],
								'auth'        => $auth,
								'header'      => [
									[
										'key'   => 'Accept',
										'value' => 'application/json',
									],
									[
										'key'   => 'Language',
										'value' => '{{Language}}',
									],
								],
								'body'        => [
									'mode'     => 'formdata',
									'formdata' => collect($route['parameters'])->map(function ($parameter, $key) {
										return [
											'key'     => $key,
											'value'   => isset($parameter['value']) ? $parameter['value'] : '',
											'type'    => 'text',
											'enabled' => true,
										];
									})->values()->toArray(),
								],
								'description' => $route['description'],
								'response'    => [],
							],
						];
					})->toArray(),
				];
			})->values()->toArray(),
		];

		return json_encode($collection);
	}
}
