# Introduction

Welcome to the generated API reference.
<br><br>Do you use Postman? Great.<br>
[Get Postman Collection]({{url($outputPath.'/collection.json')}})<br>
[Get Postman Environment]({{url($outputPath.'/environment.json')}})

## Header

Header | Value | When should I send?
-------------- | -------------- | --------------
Accept | application/json | All requests
Content-Type | application/x-www-form-urlencoded | Must send when passing query string in request body
Content-Type | application/json | Must send when passing json in request body
Content-Type | multipart/form-data | Must send when passing files in request body
Authorization | Bearer {access_token} | Whenever the resource requires an authenticated user
Language | {language} | All requests

<aside class="notice">
You must replace `{access_token}` with your personal access token.
</aside>
<aside class="notice">
You must replace `{language}` with the desired language.<br>
When not sent the Accept-Language language is used.<br>
Available languages are pt, en.
</aside>

## Controlling requests

All API requests are limited to prevent abuse and ensure stability.<br>
The limit is 120 requests every 1 minute.<br>
You can always check the response header to have a status of available requests:

`X-RateLimit-Limit → 120`<br>
`X-RateLimit-Remaining → 29`

## Errors

API uses the following error codes:

Error Code | Meaning
---------- | -------
400 | Bad Request -- Your request sucks
401 | Unauthorized -- Your API key is wrong
403 | Forbidden -- You are not authorized or do not have permission to access
404 | Not Found -- The specified page can not be found
405 | Method Not Allowed -- Method not allowed for this request
406 | Not Acceptable -- You have requested a format that is not valid
410 | Gone -- The target resource is no longer available
413 | Payload Too Large -- Request payload is larger than the server is willing or able to process
429 | Too Many Requests -- You have sent too many requests in a certain amount of time ("rate limiting")
500 | Internal Server Error -- We had a problem with our server. Try again later.
503 | Service Unavailable -- We're temporarially offline for maintanance. Please try again later.
