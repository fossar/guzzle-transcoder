# guzzle-transcoder

This [Guzzle] 6/7 middleware converts documents obtained by Guzzle to UTF-8 using [Transcoder] library. It is largely based on Pascal Landau’s [guzzle-auto-charset-encoding-subscriber] and [web-utility] libraries. Thanks to Transcoder, when mbstring is not available, iconv will be used.

## Installation
It is recommended to install the library using [Composer]:

```ShellSession
$ composer require fossar/guzzle-transcoder
```

## Basic usage

<!-- Headers: {"content-type": "text/html; charset=iso-8859-1; someOtherRandom=\"header in here\""} -->
<!-- Mock response: iso-8859-1.html -->
<!-- Expected: utf-8.html -->
```php
use Fossar\GuzzleTranscoder\GuzzleTranscoder;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

$stack = HandlerStack::create();
$stack->push(new GuzzleTranscoder);
$client = new Client(['handler' => $stack]);

$url = 'https://www.myseosolution.de/scripts/encoding-test.php?enc=iso'; // request website with iso-8859-1 encoding
$req = $client->get($url);
echo $req->getBody();
```

[Composer]: https://getcomposer.org/
[Guzzle]: https://github.com/guzzle/guzzle
[Transcoder]: https://github.com/fossar/transcoder
[guzzle-auto-charset-encoding-subscriber]: https://github.com/paslandau/guzzle-auto-charset-encoding-subscriber
[web-utility]: https://github.com/paslandau/web-utility
