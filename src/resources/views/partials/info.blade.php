# Introduction

Welcome to the generated API reference.
@if($showPostmanCollectionButton)
[Get Postman Collection]({{url($outputPath.'/collection.json')}})
@endif

## Header

Header | Value | When should I send?
-------------- | -------------- | --------------
Authorization | Bearer {access_token} | Whenever the resource requires an authenticated user
Accept | application/json | All requests
Language | {language} | All requests

<aside class="notice">
You must replace `{access_token}` with your personal access token.
</aside>
<aside class="notice">
You must replace `{language}` with the desired language.
When not sent the Accept-Language language is used.
Available languages are pt, en.
</aside>
