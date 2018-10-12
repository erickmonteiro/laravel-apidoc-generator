# Introduction

Welcome to the generated API reference.
@if($showPostmanCollectionButton)
<br><br>Do you use Postman? Great.<br>
[Get Postman Collection]({{url($outputPath.'/collection.json')}})<br>
[Get Postman Environment]({{url($outputPath.'/environment.json')}})
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
You must replace `{language}` with the desired language.<br>
When not sent the Accept-Language language is used.<br>
Available languages are pt, en.
</aside>

## Errors

API uses the following error codes:

Error Code | Meaning
---------- | -------
400 | Bad Request -- Your request sucks
401 | Unauthorized -- Your API key is wrong
403 | Forbidden -- The kitten requested is hidden for administrators only
404 | Not Found -- The specified kitten could not be found
405 | Method Not Allowed -- You tried to access a kitten with an invalid method
406 | Not Acceptable -- You requested a format that isn't json
410 | Gone -- The kitten requested has been removed from our servers
429 | Too Many Requests -- You're requesting too many kittens! Slow down!
500 | Internal Server Error -- We had a problem with our server. Try again later.
503 | Service Unavailable -- We're temporarially offline for maintanance. Please try again later.
