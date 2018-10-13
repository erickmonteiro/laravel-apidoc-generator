<!-- START_{{$parsedRoute['id']}} -->
@if($parsedRoute['title'] != '')## {{ $parsedRoute['title']}}
@else## {{$parsedRoute['uri']}}
@endif
@if($parsedRoute['description'])

{!! $parsedRoute['description'] !!}
@endif

> Example request:

```bash
curl -X {{$parsedRoute['methods'][0]}} "{{ trim(config('app.docs_url') ?: config('app.url'), '/')}}/{{ ltrim($parsedRoute['uri'], '/') }}" \
@if(!$parsedRoute['unauthenticated'])
-H "Authorization: Bearer {access_token}" \
@endif
-H "Accept: application/json" \
@if(!in_array($parsedRoute['methods'][0], ['GET','HEAD']))
-H "Content-Type: application/x-www-form-urlencoded" \
@endif
-H "Language: {language}"@if(count($parsedRoute['parameters'])) \
@foreach($parsedRoute['parameters'] as $attribute => $parameter)
    -d "{{$attribute}}"="{{$parameter['value']}}" \
@endforeach
@endif

```

```javascript
var settings = {
    "async": true,
    "crossDomain": true,
    "url": "{{ rtrim(config('app.docs_url') ?: config('app.url'), '/') }}/{{ ltrim($parsedRoute['uri'], '/') }}",
    "method": "{{$parsedRoute['methods'][0]}}",
    @if(count($parsedRoute['parameters']))
"data": {!! str_replace('    ','        ',json_encode(array_combine(array_keys($parsedRoute['parameters']), array_map(function($param){ return $param['value']; },$parsedRoute['parameters'])), JSON_PRETTY_PRINT)) !!},
    @endif
"headers": {
@if(!$parsedRoute['unauthenticated'])
        "Authorization": "Bearer {access_token}",
@endif
@if(!in_array($parsedRoute['methods'][0], ['GET','HEAD']))
        "Content-Type": "application/x-www-form-urlencoded",
@endif
        "Accept": "application/json",
        "Language": "{language}"
    }
}

$.ajax(settings).done(function (response) {
    console.log(response);
});
```

@if(in_array('GET',$parsedRoute['methods']) || (isset($parsedRoute['showresponse']) && $parsedRoute['showresponse']))
> Example response:

```json
@if(is_object($parsedRoute['response']) || is_array($parsedRoute['response']))
{!! json_encode($parsedRoute['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@else
{!! json_encode(json_decode($parsedRoute['response']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
@endif
```
@endif

### HTTP Request
@foreach($parsedRoute['methods'] as $method)
`{{$method}} {{$parsedRoute['uri']}}`

@endforeach
@if(count($parsedRoute['parameters']))
#### Parameters

Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
@foreach($parsedRoute['parameters'] as $attribute => $parameter)
    {{$attribute}} | {{$parameter['type']}} | @if($parameter['required']) required @else optional @endif | {!! $parameter['description'] !!}
@endforeach
@endif

<!-- END_{{$parsedRoute['id']}} -->
