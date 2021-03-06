<?php

namespace Mpociot\ApiDoc\Generators;

use ReflectionClass;
use Illuminate\Support\Str;
use League\Fractal\Manager;
use Illuminate\Routing\Route;
use Mpociot\Reflection\DocBlock;
use League\Fractal\Resource\Item;
use Mpociot\Reflection\DocBlock\Tag;
use League\Fractal\Resource\Collection;

abstract class AbstractGenerator
{
    /**
     * @param Route $route
     *
     * @return mixed
     */
    public function getDomain(Route $route)
    {
        return $route->domain() == null ? '*' : $route->domain();
    }

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
     * @param  \Illuminate\Routing\Route $route
     * @param array $apply Rules to apply when generating documentation for this route
     *
     * @return array
     */
    public function processRoute($route, $apply = [])
    {
        $routeAction = $route->getAction();
        $routeGroup = $this->getRouteGroup($routeAction['uses']);
        $routeDescription = $this->getRouteDescription($routeAction['uses']);
        $showresponse = null;

        $response = null;
        $docblockResponse = $this->getDocblockResponse($routeDescription['tags']);
        if ($docblockResponse) {
            // we have a response from the docblock ( @response )
            $response = $docblockResponse;
            $showresponse = true;
        }
        if (! $response) {
            $transformerResponse = $this->getTransformerResponse($routeDescription['tags']);
            if ($transformerResponse) {
                // we have a transformer response from the docblock ( @transformer || @transformercollection )
                $response = $transformerResponse;
                $showresponse = true;
            }
        }

        $content = $this->getResponseContent($response);

        return [
            'id' => md5($this->getUri($route).':'.implode($this->getMethods($route))),
            'resource' => $routeGroup,
            'title' => $routeDescription['short'],
            'description' => $routeDescription['long'],
            'methods' => $this->getMethods($route),
            'uri' => $this->getUri($route),
            'parameters' => $this->getParametersFromDocBlock($routeAction['uses']),
            'response' => $content,
            'showresponse' => $showresponse,
        ];
    }

    /**
     * Prepares / Disables route middlewares.
     *
     * @param  bool $disable
     *
     * @return  void
     */
    abstract public function prepareMiddleware($enable = false);

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
            if (! ($tag instanceof Tag)) {
                return false;
            }

            return \strtolower($tag->getName()) == 'response';
        });
        if (empty($responseTags)) {
            return;
        }
        $responseTag = \array_first($responseTags);

        return \response(json_encode($responseTag->getContent()), 200, ['Content-Type' => 'application/json']);
    }

    /**
     * @param array $routeAction
     *
     * @return array
     */
    protected function getParametersFromDocBlock($routeAction)
    {
        return [];
    }

    /**
     * @param  $route
     * @param  $bindings
     * @param  $headers
     *
     * @return \Illuminate\Http\Response
     */
    protected function getRouteResponse($route, $bindings, $headers = [])
    {
        $uri = $this->addRouteModelBindings($route, $bindings);

        $methods = $this->getMethods($route);

        // Split headers into key - value pairs
        $headers = collect($headers)->map(function ($value) {
            $split = explode(':', $value); // explode to get key + values
            $key = array_shift($split); // extract the key and keep the values in the array
            $value = implode(':', $split); // implode values into string again

            return [trim($key) => trim($value)];
        })->collapse()->toArray();

        //Changes url with parameters like /users/{user} to /users/1
        $uri = preg_replace('/{(.*?)}/', 1, $uri); // 1 is the default value for route parameters

        return $this->callRoute(array_shift($methods), $uri, [], [], [], $headers);
    }

    /**
     * @param $route
     * @param array $bindings
     *
     * @return mixed
     */
    protected function addRouteModelBindings($route, $bindings)
    {
        $uri = $this->getUri($route);
        foreach ($bindings as $model => $id) {
            $uri = str_replace('{'.$model.'}', $id, $uri);
            $uri = str_replace('{'.$model.'?}', $id, $uri);
        }

        return $uri;
    }

    /**
     * @param  \Illuminate\Routing\Route  $route
     *
     * @return array
     */
    protected function getRouteDescription($route)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);
        $reflectionMethod = $reflection->getMethod($method);

        $comment = $reflectionMethod->getDocComment();
        $phpdoc = new DocBlock($comment);

        return [
            'short' => $phpdoc->getShortDescription(),
            'long' => $phpdoc->getLongDescription()->getContents(),
            'tags' => $phpdoc->getTags(),
        ];
    }

    /**
     * @param  string  $route
     *
     * @return string
     */
    protected function getRouteGroup($route)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);
        $comment = $reflection->getDocComment();
        if ($comment) {
            $phpdoc = new DocBlock($comment);
            foreach ($phpdoc->getTags() as $tag) {
                if ($tag->getName() === 'resource') {
                    return $tag->getContent();
                }
            }
        }

        return 'general';
    }

    /**
     * Call the given URI and return the Response.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $parameters
     * @param  array  $cookies
     * @param  array  $files
     * @param  array  $server
     * @param  string  $content
     *
     * @return \Illuminate\Http\Response
     */
    abstract public function callRoute($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null);

    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     *
     * @param  array  $headers
     *
     * @return array
     */
    protected function transformHeadersToServerVars(array $headers)
    {
        $server = [];
        $prefix = 'HTTP_';

        foreach ($headers as $name => $value) {
            $name = strtr(strtoupper($name), '-', '_');

            if (! Str::startsWith($name, $prefix) && $name !== 'CONTENT_TYPE') {
                $name = $prefix.$name;
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
        if (empty($response)) {
            return '';
        }
        if ($response->headers->get('Content-Type') === 'application/json') {
            $content = json_decode($response->getContent(), JSON_PRETTY_PRINT);
        } else {
            $content = $response->getContent();
        }

        return $content;
    }

    /**
     * Get a response from the transformer tags.
     *
     * @param array $tags
     *
     * @return mixed
     */
    protected function getTransformerResponse($tags)
    {
        try {
            $transFormerTags = array_filter($tags, function ($tag) {
                if (! ($tag instanceof Tag)) {
                    return false;
                }

                return \in_array(\strtolower($tag->getName()), ['transformer', 'transformercollection']);
            });
            if (empty($transFormerTags)) {
                // we didn't have any of the tags so goodbye
                return false;
            }

            $modelTag = array_first(array_filter($tags, function ($tag) {
                if (! ($tag instanceof Tag)) {
                    return false;
                }

                return \in_array(\strtolower($tag->getName()), ['transformermodel']);
            }));
            $tag = \array_first($transFormerTags);
            $transformer = $tag->getContent();
            if (! \class_exists($transformer)) {
                // if we can't find the transformer we can't generate a response
                return;
            }
            $demoData = [];

            $reflection = new ReflectionClass($transformer);
            $method = $reflection->getMethod('transform');
            $parameter = \array_first($method->getParameters());
            $type = null;
            if ($modelTag) {
                $type = $modelTag->getContent();
            }
            if (version_compare(PHP_VERSION, '7.0.0') >= 0 && \is_null($type)) {
                // we can only get the type with reflection for PHP 7
                if ($parameter->hasType() &&
                    ! $parameter->getType()->isBuiltin() &&
                    \class_exists((string) $parameter->getType())) {
                    //we have a type
                    $type = (string) $parameter->getType();
                }
            }
            if ($type) {
                // we have a class so we try to create an instance
                $demoData = new $type;
                try {
                    // try a factory
                    $demoData = \factory($type)->make();
                } catch (\Exception $e) {
                    if ($demoData instanceof \Illuminate\Database\Eloquent\Model) {
                        // we can't use a factory but can try to get one from the database
                        try {
                            // check if we can find one
                            $newDemoData = $type::first();
                            if ($newDemoData) {
                                $demoData = $newDemoData;
                            }
                        } catch (\Exception $e) {
                            // do nothing
                        }
                    }
                }
            }

            $fractal = new Manager();
            $resource = [];
            if ($tag->getName() == 'transformer') {
                // just one
                $resource = new Item($demoData, new $transformer);
            }
            if ($tag->getName() == 'transformercollection') {
                // a collection
                $resource = new Collection([$demoData, $demoData], new $transformer);
            }

            return \response($fractal->createData($resource)->toJson());
        } catch (\Exception $e) {
            // it isn't possible to parse the transformer
            return;
        }
    }
}
