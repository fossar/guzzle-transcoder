<?php

namespace Fossar\GuzzleTranscoder;

use Ddeboer\Transcoder\Transcoder;
use Ddeboer\Transcoder\TranscoderInterface;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleTranscoder {
    /** @var ?TranscoderInterface */
    private $transcoder;

    /** @var string */
    private $targetEncoding;

    /** @var bool */
    private $replaceHeaders;

    /** @var bool */
    private $replaceContent;

    /**
     * Constructs a class for transcoding Responses.
     *
     * @param array $options array supporting the following options
     *  - string targetEncoding: Encoding the response should be transcoded to (default: 'utf-8')
     *  - bool   replaceHeaders: Whether charset field in Content-Type header should be updated (default: true)
     *  - bool   replaceContent: Whether charset declarations in the body (meta tags, XML declaration) should be updated (default: false)
     */
    public function __construct(array $options = []) {
        $this->transcoder = null;
        $this->targetEncoding = isset($options['targetEncoding']) ? $options['targetEncoding'] : 'utf-8';
        $this->replaceHeaders = isset($options['replaceHeaders']) ? $options['replaceHeaders'] : true;
        $this->replaceContent = isset($options['replaceContent']) ? $options['replaceContent'] : false;
    }

    /**
     * Returns a transcoder instance.
     *
     * @return TranscoderInterface
     */
    private function createTranscoder() {
        if ($this->transcoder === null) {
            $this->transcoder = Transcoder::create();
        }

        return $this->transcoder;
    }

    /**
     * Converts a PSR response.
     *
     * @return ResponseInterface
     */
    public function convert(ResponseInterface $response) {
        $stream = $response->getBody();

        /** @var array<string, list<string>|string> */
        $headers = $response->getHeaders();
        $result = $this->convertResponse($headers, (string) $stream);
        if ($result !== null) {
            $body = Psr7Utils::streamFor($result['content']);
            $response = $response->withBody($body);
            foreach ($result['headers'] as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * Called when the middleware is handled by the client.
     *
     * @return callable
     */
    public function __invoke(callable $handler) {
        return function(RequestInterface $request, array $options) use ($handler) {
            $promise = $handler($request, $options);

            return $promise->then(function(ResponseInterface $response) {
                return $this->convert($response);
            });
        };
    }

    /**
     * Converts the given $content to the $targetEncoding.
     *
     * The original encoding is defined by (in order):
     * - the 'charset' parameter of the 'content-type' header
     * - the meta information in the body of an HTML (content-type: text/html)or XML (content-type: text/xml or application/xml) document
     *
     * If the original encoding could not be determined, null is returned.
     *
     * Otherwise an array containing the new headers and content is returned.
     *
     * @param array<string, list<string>|string> $headers
     * @param string $content
     *
     * @return ?array{headers: array<string, list<string>|string>, content: string}
     */
    public function convertResponse(array $headers, $content) {
        $headerDeclaredEncoding = null;
        $bodyDeclaredEncoding = null;
        /** @var array<string, list<string>|string> */
        $headerReplacements = [];
        $contentReplacements = [];

        // check the header
        $type = ContentTypeExtractor::getContentTypeFromHeader($headers, $this->targetEncoding);
        if ($type !== null) {
            list($contentType, $headerDeclaredEncoding, $params) = $type;

            $headerReplacements['content-type'] = $contentType . (\count($params) > 0 ? '; ' . Utils::joinHttpHeaderWords($params) : '');
        } else {
            return null;
        }

        // else, check the body
        if (preg_match('#^text/html#i', $contentType)) {
            list($bodyDeclaredEncoding, $contentReplacements) = ContentTypeExtractor::getContentTypeFromHtml($content, $this->targetEncoding);
        } elseif (preg_match('#^(text|application)/(.+\+)?xml#i', $contentType)) { // see http://stackoverflow.com/a/3272572/413531
            list($bodyDeclaredEncoding, $contentReplacements) = ContentTypeExtractor::getContentTypeFromXml($content, $this->targetEncoding);
        }

        $finalEncoding = null;
        if ($bodyDeclaredEncoding !== null) {
            $finalEncoding = $bodyDeclaredEncoding;
        } elseif ($headerDeclaredEncoding !== null) {
            $finalEncoding = $headerDeclaredEncoding;
        } else {
            return null;
        }

        $headers_new = $headers;
        if ($this->replaceHeaders) {
            foreach ($headerReplacements as $headerKey => $value) {
                $headers_new = Utils::setByCaseInsensitiveKey($headers_new, $headerKey, $value);
            }
        }

        $converted = $this->createTranscoder()->transcode($content, $finalEncoding, $this->targetEncoding);
        $converted_new = $converted;
        if ($this->replaceContent) {
            foreach ($contentReplacements as $oldContent => $newContent) {
                $converted_new = str_replace($oldContent, $newContent, $converted_new);
            }
        }

        return [
            'headers' => $headers_new,
            'content' => $converted_new,
        ];
    }
}
