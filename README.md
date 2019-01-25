# guzzle-transcoder

This [Guzzle] plug-in converts documents obtained by Guzzle to UTF-8 using [Transcoder] library. It is largely based on Pascal Landau’s [guzzle-auto-charset-encoding-subscriber] and [web-utility] libraries. Thanks to Transcoder, when mbsting is not available iconv will be used.

## Basic usage

```php
use Fossar\GuzzleTranscoder\GuzzleTranscoder;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

$stack = HandlerStack::create();
$stack->push(GuzzleTranscoder::create_middleware());
$client = new Client(['handler' => $stack]);

$url = 'https://www.myseosolution.de/scripts/encoding-test.php?enc=iso'; // request website with iso-8859-1 encoding
$req = $client->get($url);
echo $req->getBody();
```

[Guzzle]: https://github.com/guzzle/guzzle
[Transcoder]: https://github.com/ddeboer/transcoder
[guzzle-auto-charset-encoding-subscriber]: https://github.com/paslandau/guzzle-auto-charset-encoding-subscriber
[web-utility]: https://github.com/paslandau/web-utility
