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
		$environment = [
			'id'                      => Uuid::uuid4()->toString(),
			'name'                    => isset($this->options['postmanName']) ? $this->options['postmanName'] : '',
			'values'                  => [
				[
					'key'     => 'access_token',
					'value'   => '',
					'enabled' => true,
					'type'    => 'text',
				],
				[
					'key'     => 'refresh_token',
					'value'   => '',
					'enabled' => true,
					'type'    => 'text',
				],
				[
					'key'     => 'Language',
					'value'   => 'pt',
					'enabled' => true,
					'type'    => 'text',
				],
				[
					'key'     => 'email',
					'value'   => 'email@site.com',
					'enabled' => true,
					'type'    => 'text',
				],
				[
					'key'     => 'password',
					'value'   => 'password',
					'enabled' => true,
					'type'    => 'text',
				],
			],
			'_postman_variable_scope' => 'environment',
			'_postman_exported_at'    => '2018-10-12T02:54:07.303Z',
			'_postman_exported_using' => 'Postman/6.4.2',
		];

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

						$is_login   = ends_with($route['uri'], 'auth/login');
						$is_refresh = ends_with($route['uri'], 'auth/refresh');
						$is_logout  = ends_with($route['uri'], 'auth/logout');

						$event = [];

						if( $is_login OR $is_refresh )
						{
							$event = [
								[
									'listen' => 'test',
									'script' => [
										'id'   => Uuid::uuid4()->toString(),
										'exec' => [
											'var jsonData = pm.response.json();',
											'',
											'if (jsonData.hasOwnProperty("access_token"))',
											'{',
											'    pm.environment.set("access_token", jsonData.access_token);',
											'    pm.environment.set("refresh_token", jsonData.refresh_token);',
											'}',
										],
										'type' => 'text/javascript'
									]
								]
							];
						}
						elseif( $is_logout )
						{
							$event = [
								[
									'listen' => 'test',
									'script' => [
										'id'   => Uuid::uuid4()->toString(),
										'exec' => [
											'pm.environment.set("access_token", "");',
											'pm.environment.set("refresh_token", "");',
										],
										'type' => 'text/javascript'
									]
								]
							];
						}

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

						$body_mode = 'urlencoded';

						$header = [
							[
								'key'   => 'Accept',
								'value' => 'application/json',
							],
						];

						if( !in_array($route['methods'][0], ['GET', 'HEAD']) AND count($route['parameters']) )
						{
							if( $route['has_file_parameter'] )
							{
								$header[] = [
									'key'   => 'Content-Type',
									'value' => 'multipart/form-data',
								];

								$body_mode = 'formdata';
							}
							else
							{
								$header[] = [
									'key'   => 'Content-Type',
									'value' => 'application/x-www-form-urlencoded',
								];
							}
						}

						$header[] = [
							'key'   => 'Language',
							'value' => '{{Language}}',
						];

						return [
							'name'    => $route['title'] != '' ? $route['title'] : url($route['uri']),
							'event'   => $event,
							'request' => [
								'url'         => url($route['uri']),
								'method'      => $route['methods'][0],
								'auth'        => $auth,
								'header'      => $header,
								'body'        => [
									'mode'     => $body_mode,
									$body_mode => collect($route['parameters'])->map(function ($parameter, $key) use ($is_login, $is_refresh) {

										if( $is_login )
										{
											if( $key == 'email' )
											{
												$parameter['value'] = '{{email}}';
											}
											elseif( $key == 'password' )
											{
												$parameter['value'] = '{{password}}';
											}
										}
										elseif( $is_refresh )
										{
											if( $key == 'refresh_token' )
											{
												$parameter['value'] = '{{refresh_token}}';
											}
										}

										return [
											'key'         => $key,
											'value'       => isset($parameter['value']) ? $parameter['value'] : '',
											'type'        => $parameter['type'] === 'file' ? 'file' : 'text',
											'description' => strip_tags($parameter['description']),
											'enabled'     => true,
										];
									})->values()->toArray(),
								],
								'description' => ($route['permission'] ? 'Permission Required: ' . $route['permission'] . "\n\n" : '') . $route['description'],
								'response'    => [],
							],
						];
					})->toArray(),
				];
			})->values()->toArray(),
		];

		return [
			'environment' => json_encode($environment),
			'collection'  => json_encode($collection),
		];
	}
}
