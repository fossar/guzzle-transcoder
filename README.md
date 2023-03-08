# guzzle-transcoder

[![Packagist Version](https://img.shields.io/packagist/v/fossar/guzzle-transcoder)](https://packagist.org/packages/fossar/guzzle-transcoder)

This package provides a [Guzzle] 6/7 middleware that transparently converts documents obtained by Guzzle from its native encoding to UTF-8 (or any other specified encoding). It supports the following features:

- Detection of charset from [`Content-Type`] HTTP header.
- Detection of charset from [`meta` element] in HTML document.
- Detection of charset from [XML declaration] in RSS and other XML documents.
- Updating the `Content-Type` header in the `Response` object according to target encoding.
- Updating the metadata in the `Response` body according to target encoding (not enabled by default).

## Installation

It is recommended to install the library using [Composer]:

```ShellSession
$ composer require fossar/guzzle-transcoder
```

## Usage
### Basic example

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

### Full example

<!-- Headers: {"content-type": "text/html; charset=iso-8859-1; someOtherRandom=\"header in here\""} -->
<!-- Mock response: iso-8859-1.html -->
<!-- Expected: readme-full -->
```php
use Fossar\GuzzleTranscoder\GuzzleTranscoder;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

$stack = HandlerStack::create();
$stack->push(new GuzzleTranscoder([
	'targetEncoding' => 'windows-1252',
	// Swap the default settings:
	'replaceHeaders' => false,
	'replaceContent' => true,
]));
$client = new Client(['handler' => $stack]);

$url = 'https://www.myseosolution.de/scripts/encoding-test.php?enc=iso'; // request website with iso-8859-1 encoding
$req = $client->get($url);
echo $req->getHeaderLine('Content-Type') . "\n"; // HTTP header will remain unchanged
echo $req->getBody();
```

## Credits

It is largely based on Pascal Landau’s [guzzle-auto-charset-encoding-subscriber] and [web-utility] libraries.

We are using [Transcoder] library. This allows us to fall back to `iconv` when `mbstring` is not available or an encoding is not supported by it.

The source code is available under the terms of [MIT license](LICENSE.md)

[`Content-Type`]: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Type
[`meta` element]: https://developer.mozilla.org/en-US/docs/Web/HTML/Element/meta#charset
[XML declaration]: https://developer.mozilla.org/en-US/docs/Web/XML/XML_introduction#xml_declaration
[Composer]: https://getcomposer.org/
[Guzzle]: https://github.com/guzzle/guzzle
[Transcoder]: https://github.com/fossar/transcoder
[guzzle-auto-charset-encoding-subscriber]: https://github.com/paslandau/guzzle-auto-charset-encoding-subscriber
[web-utility]: https://github.com/paslandau/web-utility
