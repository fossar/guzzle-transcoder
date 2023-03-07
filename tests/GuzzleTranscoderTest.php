<?php

declare(strict_types=1);

namespace Fossar\GuzzleTranscoder\Tests;

use Fossar\GuzzleTranscoder\GuzzleTranscoder;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class GuzzleTranscoderTest extends \PHPUnit\Framework\TestCase {
    private $types = [
        'html4' => 'text/html',
        'html5' => 'text/html',
        'text-xml' => 'text/xml',
        'application-rss-xml' => 'application/rss+xml',
        'application-xml' => 'application/xml',
    ];

    /**
     * Creates a Response object.
     */
    public function getResponse(?string $bodyEncoding, ?string $encodingInHeader, ?string $encodingInMeta, string $type): Response {
        mb_internal_encoding('utf-8');

        $status = 200;
        $content = 'Just a little piece of text with some german umlauts like äöüßÄÖÜ and maybe some more UTF-8 characters';
        $headers = [
            'Date' => 'Wed, 26 Nov 2014 22:26:29 GMT',
            'Server' => 'Apache',
            'Content-Language' => 'en',
            'Vary' => 'Accept-Encoding',
            'Content-Type' => $this->types[$type],
        ];
        if ($encodingInHeader !== null) {
            $headers['Content-Type'] .= "; charset={$encodingInHeader}";
        }
        switch ($type) {
            case 'html4':
                $meta = '';
                if ($encodingInMeta !== null) {
                    $meta = "<meta http-equiv='content-type' content='{$this->types[$type]}; charset={$encodingInMeta}'>";
                }
                $content = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\"><html><head>{$meta}<title>Umlauts everywhere öäüßÖÄÜ</title></head><body>$content</body></html>";
                break;

            case 'html5':
                $meta = '';
                if ($encodingInMeta !== null) {
                    $meta = "<meta charset='{$encodingInMeta}'>";
                }
                $content = "<!DOCTYPE html><html><head>{$meta}<title>Umlauts everywhere öäüßÖÄÜ</title></head><body>$content</body></html>";
                break;

            case 'text-xml':
            case 'application-rss-xml':
            case 'application-xml':
                $meta = '';
                if ($encodingInMeta !== null) {
                    $meta = " encoding='{$encodingInMeta}'";
                }
                $content = "<?xml version='1.0'{$meta}><foo><bar>$content</bar></foo>";
                break;
        }

        if ($bodyEncoding !== null) {
            $content = mb_convert_encoding($content, $bodyEncoding, mb_internal_encoding());
        }

        return new Response($status, $headers, $content);
    }

    /**
     * @return array<string, array{input: Response, expected: array<string, Response>}>
     */
    public function convertData(): array {
        $enc = mb_internal_encoding();
        $inputEnc = 'iso-8859-1';

        $tests = [];
        foreach ($this->types as $type => $mime) {
            $tests["Request-Type: $type; Settings: Charset info neither in header nor in body"] = [
                'input' => $this->getResponse($inputEnc, null, null, $type),
                'expected' => [
                    'none' => $this->getResponse($inputEnc, null, null, $type),
                    'header' => $this->getResponse($inputEnc, null, null, $type),
                    'header-meta' => $this->getResponse($inputEnc, null, null, $type),
                    'meta' => $this->getResponse($inputEnc, null, null, $type),
                ],
            ];
            $tests["Request-Type: $type; Settings: Charset info only in header but not in body"] = [
                'input' => $this->getResponse($inputEnc, $inputEnc, null, $type),
                'expected' => [
                    'none' => $this->getResponse($enc, $inputEnc, null, $type),
                    'header' => $this->getResponse($enc, $enc, null, $type),
                    'header-meta' => $this->getResponse($enc, $enc, null, $type),
                    'meta' => $this->getResponse($enc, $inputEnc, null, $type),
                ],
            ];
            $tests["Request-Type: $type; Settings: Charset info in header and in body"] = [
                'input' => $this->getResponse($inputEnc, $inputEnc, $inputEnc, $type),
                'expected' => [
                    'none' => $this->getResponse($enc, $inputEnc, $inputEnc, $type),
                    'header' => $this->getResponse($enc, $enc, $inputEnc, $type),
                    'header-meta' => $this->getResponse($enc, $enc, $enc, $type),
                    'meta' => $this->getResponse($enc, $inputEnc, $enc, $type),
                ],
            ];
            $tests["Request-Type: $type; Settings: Charset info not in header but only in body"] = [
                'input' => $this->getResponse($inputEnc, null, $inputEnc, $type),
                'expected' => [
                    'none' => $this->getResponse($enc, null, $inputEnc, $type),
                    'header' => $this->getResponse($enc, $enc, $inputEnc, $type),
                    'header-meta' => $this->getResponse($enc, $enc, $enc, $type),
                    'meta' => $this->getResponse($enc, null, $enc, $type),
                ],
            ];
        }

        return $tests;
    }

    /**
     * @dataProvider convertData
     *
     * @param Response[] $expected
     */
    public function testConvert(Response $input, array $expected): void {
        $enc = mb_internal_encoding();

        $converters = [
            'none' => new GuzzleTranscoder([
                'targetEncoding' => $enc,
                'replaceHeaders' => false,
                'replaceContent' => false,
            ]),
            'header' => new GuzzleTranscoder([
                'targetEncoding' => $enc,
                'replaceHeaders' => true,
                'replaceContent' => false,
            ]),
            'header-meta' => new GuzzleTranscoder([
                'targetEncoding' => $enc,
                'replaceHeaders' => true,
                'replaceContent' => true,
            ]),
            'meta' => new GuzzleTranscoder([
                'targetEncoding' => $enc,
                'replaceHeaders' => false,
                'replaceContent' => true,
            ]),
        ];

        foreach ($expected as $converterType => $expected) {
            $mock = new MockHandler([
                $input,
            ]);
            $stack = HandlerStack::create($mock);
            $stack->push($converters[$converterType]);
            $client = new Client(['handler' => $stack]);

            /** @var ResponseInterface $response */
            $response = $client->get('/');
            $this->assertEquals($expected->getStatusCode(), $response->getStatusCode());
            $this->assertEquals($expected->getHeaders(), $response->getHeaders());
            $this->assertEquals((string) $expected->getBody(), (string) $response->getBody());
        }
    }

    public function testConvertResponse(): void {
        $enc = strtolower(mb_internal_encoding());
        $tests = ['html4', 'html5', 'text-xml', 'application-xml', 'application-rss-xml'];
        $converters = [
            'header' => new GuzzleTranscoder([
                'targetEncoding' => $enc,
                'replaceHeaders' => true,
                'replaceContent' => false,
            ]),
            'header-meta' => new GuzzleTranscoder([
                'targetEncoding' => $enc,
                'replaceHeaders' => true,
                'replaceContent' => true,
            ]),
            'meta' => new GuzzleTranscoder([
                'targetEncoding' => $enc,
                'replaceHeaders' => false,
                'replaceContent' => true,
            ]),
        ];
        foreach ($tests as $type) {
            $input = __DIR__ . "/resources/iso-8859-1-{$type}";
            $c = file_get_contents($input);
            ['headers' => $headers, 'body' => $body] = $this->splitHeadersAndContentFromHttpResponseString($c);

            foreach ($converters as $converterName => $converter) {
                $expected = __DIR__ . "/resources/utf8-{$type}-{$converterName}";
                $c = file_get_contents($expected);
                ['headers' => $expectedHeaders, 'body' => $expectedBody] = $this->splitHeadersAndContentFromHttpResponseString($c);
                $res = $converter->convertResponse($headers, $body);

                $this->assertNotNull($res, "Unable to convert response for {$type} using {$converterName}.");
                $this->assertSame($expectedHeaders, $res['headers'], "Response headers do not match for {$type} using {$converterName}.");
                $this->assertSame($expectedBody, $res['content'], "Response body does not match for {$type} using {$converterName}.");
            }
        }
    }

    /**
     * Gets the headers from a HTTP response as one dimensional associative array
     * with header names as keys. The header values will not be parsed but saved as-is!
     *
     * @return array{headers: array<string>, body: string}
     */
    private function splitHeadersAndContentFromHttpResponseString(string $responseString): array {
        $lines = explode("\n", $responseString);
        $headers = [];
        $i = 0;
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                break;
            }
            $parts = explode(':', $line);
            $key = array_shift($parts);
            if (\count($parts) > 0) {
                $headers[$key] = trim(implode(':', $parts));
            } else {
                $headers[$key] = '';
            }
        }
        $body = implode("\n", \array_slice($lines, $i + 1));

        return ['headers' => $headers, 'body' => $body];
    }
}
