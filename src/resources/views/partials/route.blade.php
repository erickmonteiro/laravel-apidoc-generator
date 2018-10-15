<!-- START_{{$parsedRoute['id']}} -->
@if($parsedRoute['title'] != '')## {{ $parsedRoute['title']}}
@else## {{$parsedRoute['uri']}}
@endif
@if($parsedRoute['description'])

{!! $parsedRoute['description'] !!}
@endif

> Example request:

```javascript
const axios = require('axios');

$.axios({
    "url": "{{ rtrim(config('app.docs_url') ?: config('app.url'), '/') }}/{{ ltrim($parsedRoute['uri'], '/') }}",
    "method": "{{ mb_strtolower($parsedRoute['methods'][0]) }}",
    "headers": {
@if(!$parsedRoute['unauthenticated'])
        "Authorization": "Bearer {access_token}",
@endif
@if(!in_array($parsedRoute['methods'][0], ['GET','HEAD']))
        "Content-Type": "{{ $parsedRoute['has_file_parameter'] ? 'multipart/form-data' : 'application/x-www-form-urlencoded' }}",
@endif
        "Accept": "application/json",
        "Language": "{language}"
    }@if(count($parsedRoute['parameters'])),
    "data": {!! str_replace('    ','        ',json_encode(array_combine(array_keys($parsedRoute['parameters']), array_map(function($param){ return $param['value']; },$parsedRoute['parameters'])), JSON_PRETTY_PRINT)) !!}
@endif
})
.then(function (response) {
    console.log(response);
})
.catch(function (error) {
    console.log(error);
})
.then(function () {
    // optional, always executed
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
@if($parsedRoute['permission'])
#### Permission Required
`{{ $parsedRoute['permission'] }}`

@endif
@if(count($parsedRoute['parameters']))
#### Parameters

Parameter | Type | Validation | Description
--------- | ------- | ------- | ------- | -----------
@foreach($parsedRoute['parameters'] as $attribute => $parameter)
    {{$attribute}} | {{$parameter['type']}} | {!! str_replace('|', '&vert;', $parameter['validation']) !!} | {!! $parameter['description'] !!}
@endforeach
@endif

<!-- END_{{$parsedRoute['id']}} -->
