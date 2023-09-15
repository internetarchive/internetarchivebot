# Using alternate hosts to check for dead links

InternetArchiveBot comes with a testdeadlink.php file that can be installed on different machines and accessed through their web servers.

The testdeadlink.php checks all URLs concurrently.

## Installation
1. Clone the internetarchivebot repository to any desired directory on the target host machine.
2. Run composer to install the dependencies.
3. Copy, or symlink, the vendor directory to the directly above the web server's html root directory.
4. Copy, or symlink, the testdeadlink.php to the html root directory.
5. Create an accessConfig.php in the parent folder to the html root directory.
6. Set the variable **$accessCodes** as an array of authorization strings to be used for authorizing use of the endpoint.  The strings can be anything you wish it to be.

Note: The CheckIfDead library requires an environment with PHP 7.3 or newer.  Installing TOR on the host is recommended.

Note: It is recommended to set the gateway timeout and PHP execution timeout values to 5 minutes or higher while allowing multiple concurrent requests on the web server.

## Using the endpoint
InternetArchiveBot makes use of this endpoint when pointed to the endpoint.  If you would like to use the endpoint for other reasons, here's what you need to know.

All requests are only handled via POST requests.  Every request requires an '**authcode**' value to be specified containing one of the strings stored in **$accessCodes** as explained above.

The endpoint can be reached simply by calling the URL for it on the web server.  Responses are in JSON.

Simply calling the testdeadlink.php file will have it return:
```angular2html
{
    "error": "nocommand",
    "errormessage": "You must provide inputs.",
    "servetime": 0.0362
}
```
To have it check URLs, you must pass one or more URLs via the '**urls**' parameter.  Multiple URLs can be separated by newlines in the POST request.

When the endpoint finishes checking all URLs, it will produce output like below:
```angular2html
{
    "https:\/\/en.wikipedia.org\/nothing": true,
    "https:\/\/en.wikipedia.org": false,
    "errors": {
        "https:\/\/en.wikipedia.org\/nothing": "RESPONSE CODE: 404"
    }
}
```
Values with "true" are considered "dead".

You can add '**returncodes=1**' to the POST request to replace the true/false return values with actual HTTP codes.
Example response:
```angular2html
{
    "https:\/\/en.wikipedia.org\/nothing": 404,
    "https:\/\/en.wikipedia.org": 200,
    "errors": {
        "https:\/\/en.wikipedia.org\/nothing": "RESPONSE CODE: 404"
    }
}
```
