<?php

namespace Fossar\GuzzleTranscoder\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Subscriber\Mock;
use Fossar\GuzzleTranscoder\GuzzleTranscoder;

class GuzzleTranscoderTest extends \PHPUnit\Framework\TestCase {
    private $types = array(
        'html4' => 'text/html',
        'html5' => 'text/html',
        'text-xml' => 'text/xml',
        'application-xml' => 'application/xml',
    );

    public function getResponseString($bodyEncoding, $encodingInHeader, $encodingInMeta, $type) {
        mb_internal_encoding('utf-8');

        $status = 'HTTP/1.1 200 OK';
        $content = 'Just a little piece of text with some german umlauts like äöüßÄÖÜ and maybe some more UTF-8 characters';
        $headers = array(
            'Date: Wed, 26 Nov 2014 22:26:29 GMT',
            'Server: Apache',
            'Content-Language: en',
            'Vary: Accept-Encoding',
            'ctype' => "Content-Type: {$this->types[$type]};"
        );
        if ($encodingInHeader !== null) {
            $headers['ctype'] .= " charset={$encodingInHeader}";
        }
        switch ($type) {
            case 'html4': {
                $meta = '';
                if ($encodingInMeta !== null) {
                    $meta = "<meta http-equiv='content-type' content='{$this->types[$type]}; charset={$encodingInMeta}'>";
                }
                $content = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\"><html><head>{$meta}<title>Umlauts everywhere öäüßÖÄÜ</title></head><body>$content</body></html>";
                break;
            }
            case 'html5': {
                $meta = '';
                if ($encodingInMeta !== null) {
                    $meta = "<meta charset='{$encodingInMeta}'>";
                }
                $content = "<!DOCTYPE html><html><head>{$meta}<title>Umlauts everywhere öäüßÖÄÜ</title></head><body>$content</body></html>";
                break;
            }
            case 'text-xml':
            case 'application-xml': {
                $meta = '';
                if ($encodingInMeta !== null) {
                    $meta = " encoding='{$encodingInMeta}'";
                }
                $content = "<?xml version='1.0'{$meta}><foo><bar>$content</bar></foo>";
                break;
            }
        }
        $headers[] = '';

        $response = array(
            $status,
        );
        $response = array_merge($response, $headers);
        $response[] = $content;
        $response = implode("\r\n", $response);
        if ($bodyEncoding !== null) {
            $response = mb_convert_encoding($response, $bodyEncoding, mb_internal_encoding());
        }

        return $response;
    }

    public function test_convert() {
        $enc = mb_internal_encoding();

        $inputEnc = 'iso-8859-1';
        $converters = array(
            'none' => new GuzzleTranscoder("$enc", false, false),
            'header' => new GuzzleTranscoder("$enc", true, false),
            'header-meta' => new GuzzleTranscoder("$enc", true, true),
            'meta' => new GuzzleTranscoder("$enc", false, true),
        );

        $tests = array();
        foreach ($this->types as $type => $mime) {
            $tests["none-$type"] = array(
                'info' => "Request-Type: $type; Settings: Charset info neither in header nor in body",
                'input' => $this->getResponseString($inputEnc, null, null, $type),
                'expected' => array(
                    'none' => $this->getResponseString($inputEnc, null, null, $type),
                    'header' => $this->getResponseString($inputEnc, null, null, $type),
                    'header-meta' => $this->getResponseString($inputEnc, null, null, $type),
                    'meta' => $this->getResponseString($inputEnc, null, null, $type),
                )
            );
            $tests["header-$type"] = array(
                'info' => "Request-Type: $type; Settings: Charset info only in header but not in body",
                'input' => $this->getResponseString($inputEnc, $inputEnc, null, $type),
                'expected' => array(
                    'none' => $this->getResponseString($enc, $inputEnc, null, $type),
                    'header' => $this->getResponseString($enc, $enc, null, $type),
                    'header-meta' => $this->getResponseString($enc, $enc, null, $type),
                    'meta' => $this->getResponseString($enc, $inputEnc, null, $type),
                )
            );
            $tests["header-meta-$type"] = array(
                'info' => "Request-Type: $type; Settings: Charset info in header and in body",
                'input' => $this->getResponseString($inputEnc, $inputEnc, $inputEnc, $type),
                'expected' => array(
                    'none' => $this->getResponseString($enc, $inputEnc, $inputEnc, $type),
                    'header' => $this->getResponseString($enc, $enc, $inputEnc, $type),
                    'header-meta' => $this->getResponseString($enc, $enc, $enc, $type),
                    'meta' => $this->getResponseString($enc, $inputEnc, $enc, $type),
                )
            );
            $tests["meta-$type"] = array(
                'info' => "Request-Type: $type; Settings: Charset info not in header but only in body",
                'input' => $this->getResponseString($inputEnc, null, $inputEnc, $type),
                'expected' => array(
                    'none' => $this->getResponseString($enc, null, $inputEnc, $type),
                    'header' => $this->getResponseString($enc, $enc, $inputEnc, $type),
                    'header-meta' => $this->getResponseString($enc, $enc, $enc, $type),
                    'meta' => $this->getResponseString($enc, null, $enc, $type),
                )
            );
        }

        $client = new Client();
        foreach ($tests as $name => $data) {
            foreach ($data['expected'] as $converterType => $expected) {
                $request = $client->createRequest('GET', '/');
                $mock = new Mock(array($data['input']));
                $request->getEmitter()->attach($mock);
                $converter = $converters[$converterType];
                $request->getEmitter()->attach($converter);
                /** @var ResponseInterface $response */
                $response = $client->send($request);
                $actual = $response->__toString();
                $msg = array(
                    "Error at {$data['info']} for converter type {$converterType}:",
                    "Input\n" . $data['input'] . "\n",
                    "Expected\n" . $expected . "\n",
                    "Actual\n" . $actual . "\n",
                );
                $msg = implode("\n", $msg);
                $this->assertEquals($expected, $actual, $msg);
            }
        }
    }

    public function test_convertResponse() {
        $tests = array('html4', 'html5', 'text-xml', 'application-xml');
        $converters = array(
            'header' => new GuzzleTranscoder('utf-8', true, false),
            'header-meta' => new GuzzleTranscoder('utf-8', true, true),
            'meta' => new GuzzleTranscoder('utf-8', false, true),
        );
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
                $msg = array(
                    "Error in test $name - $file:",
                    'Input headers    : ' . json_encode($headers),
                    'Expected headers: ' . json_encode($expectedHeaders),
                    'Actual headers   : ' . json_encode($res['headers']),
                );
                $msg = implode("\n", $msg);
                $this->assertEquals($res['headers'], $expectedHeaders, $msg);
                $msg = array(
                    "Error in test $name - $file:",
                    "Input body       :\n" . $body . "\n",
                    "Expected body   :\n" . $expectedBody . "\n",
                    "Actual body      :\n" . $res['content'] . "\n",
                );
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
        $headers = array();
        $i = 0;
        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                break;
            }
            $parts = explode(':', $line);
            $key = array_shift($parts);
            if (count($parts) > 0) {
                $headers[$key] = trim(implode(':', $parts));
            } else {
                $headers[$key] = '';
            }
        }
        $body = implode("\n", array_slice($lines, $i + 1));

        return array('headers' => $headers, 'body' => $body);
    }
}
