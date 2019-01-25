<?php

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
        'application-xml' => 'application/xml',
    ];

    /**
     * Creates a Response object.
     *
     * @param ?string $bodyEncoding
     * @param ?string $encodingInHeader
     * @param ?string $encodingInMeta
     * @param string $type
     *
     * @return Response
     */
    public function getResponse($bodyEncoding, $encodingInHeader, $encodingInMeta, $type) {
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

    public function convertData() {
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
     * @param Response $input
     * @param Response[] $expected
     */
    public function testConvert(Response $input, array $expected) {
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

    public function testConvertResponse() {
        $enc = strtolower(mb_internal_encoding());

        $tests = ['html4', 'html5', 'text-xml', 'application-xml'];
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
        foreach ($tests as $name) {
            $input = __DIR__ . "/resources/iso-8859-1-{$name}";
            $c = file_get_contents($input);
            $arr = $this->splitHeadersAndContentFromHttpResponseString($c);
            $headers = $arr['headers'];
            $body = $arr['body'];
            /* @var GuzzleTranscoder */
            foreach ($converters as $file => $converter) {
                $expected = __DIR__ . "/resources/utf8-{$name}-{$file}";
                $c = file_get_contents($expected);
                $arr = $this->splitHeadersAndContentFromHttpResponseString($c);
                $expectedHeaders = $arr['headers'];
                $expectedBody = $arr['body'];
                $res = $converter->convertResponse($headers, $body);
                $msg = [
                    "Error in test $name - $file:",
                    'Input headers    : ' . json_encode($headers),
                    'Expected headers: ' . json_encode($expectedHeaders),
                    'Actual headers   : ' . json_encode($res['headers']),
                ];
                $msg = implode("\n", $msg);
                $this->assertEquals($res['headers'], $expectedHeaders, $msg);
                $msg = [
                    "Error in test $name - $file:",
                    "Input body       :\n" . $body . "\n",
                    "Expected body   :\n" . $expectedBody . "\n",
                    "Actual body      :\n" . $res['content'] . "\n",
                ];
                $msg = implode("\n", $msg);
                $this->assertEquals($expectedBody, $res['content'], $msg);
            }
        }
    }

    /**
     * Gets the headers from a HTTP response as one dimensional associative array
     * with header names as keys. The header values will not be parsed but saved as-is!
     *
     * @param $responseString
     *
     * @return array
     */
    private function splitHeadersAndContentFromHttpResponseString($responseString) {
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
