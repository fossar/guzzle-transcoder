<?php

namespace Fossar\GuzzleTranscoder;

use Ddeboer\Transcoder\Transcoder;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7;
use Psr\Http\Message\ResponseInterface;

class GuzzleTranscoder {
    /** @var Transcoder */
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
        $this->transcoder = Transcoder::create();
        $this->targetEncoding = isset($options['targetEncoding']) ? $options['targetEncoding'] : 'utf-8';
        $this->replaceHeaders = isset($options['replaceHeaders']) ? $options['replaceHeaders'] : true;
        $this->replaceContent = isset($options['replaceContent']) ? $options['replaceContent'] : false;
    }

    public function convert(ResponseInterface $response) {
        if ($response === null) {
            return $response;
        }

        $stream = $response->getBody();
        if ($stream === null) { // no body - nothing to convert
            return $response;
        }

        $headers = $response->getHeaders();
        $result = $this->convertResponse($headers, (string) $stream);
        if ($result !== null) {
            $body = Psr7\stream_for($result['content']);
            $response = $response->withBody($body);
            foreach ($result['headers'] as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * @param array $options
     */
    public static function create_middleware(array $options = []) {
        $transcoder = new self($options);

        return Middleware::mapResponse(function(ResponseInterface $response) use ($transcoder) {
            return $transcoder->convert($response);
        });
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
     * Otherwise an object of type EncodingResult is returned. Please see the description of the properties of said class.
     *
     * @param array $headers
     * @param string $content
     *
     * @return EncodingResult|null
     */
    public function convertResponse(array $headers, $content) {
        $headerDeclaredEncoding = null;
        $bodyDeclaredEncoding = null;
        $replacements = [
            'headers' => [],
            'content' => null,
        ];

        $contentType = self::getByCaseInsensitiveKey($headers, 'content-type');
        if ($contentType === null) {
            $contentType = '';
        } elseif (\is_array($contentType)) {
            $contentType = $contentType[0];
        }

        $parsed = Utils::splitHttpHeaderWords($contentType);
        if (\count($parsed) > 0) {
            $parsed = reset($parsed);
        }
        //check the header
        $encoding = self::getByCaseInsensitiveKey($parsed, 'charset');
        if ($encoding !== null) {
            $headerDeclaredEncoding = $encoding;
        }
        $newParsed = self::setByCaseInsensitiveKey($parsed, 'charset', $this->targetEncoding);
        $replacements['headers']['content-type'] = Utils::joinHttpHeaderWords($newParsed);
        // else, check the body
        if (preg_match('#^text/html#i', $contentType)) {
            // find http-equiv
            $patternHtml4 = "#<meta[^>]+http-equiv=[\"']?content-type[\"']?[^>]*?>#i"; // html 4 - e.g. <meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
            $patternHtml5 = "#(?P<before><meta[^>]+?)charset=(?P<quote>[\"'])(?P<charset>[^\"' ]+?)\\2(?P<after>[^>]*?>)#i"; // e.g. <meta charset=iso-8859-1> - for html 5 http://webdesign.about.com/od/metatags/qt/meta-charset.htm
            if (preg_match($patternHtml4, $content, $match)) {
                $pattern = "#(?P<before>.*)content=(?P<quote>[\"'])(?P<content>.*?)\\2(?P<after>.*)#";
                if (preg_match($pattern, $match[0], $innerMatch)) {
                    $parsed = Utils::splitHttpHeaderWords($innerMatch['content']);
                    if (\count($parsed) > 0) {
                        $parsed = reset($parsed);
                    }
                    $bodyDeclaredEncoding = self::getByCaseInsensitiveKey($parsed, 'charset');
                    $newParsed = self::setByCaseInsensitiveKey($parsed, 'charset', $this->targetEncoding);
                    $newContent = Utils::joinHttpHeaderWords($newParsed);
                    $newMeta = $innerMatch['before'] . "content={$innerMatch['quote']}" . $newContent . "{$innerMatch['quote']}" . $innerMatch['after'];
                    $replacements['content'][$match[0]] = $newMeta;
                }
            } elseif (preg_match($patternHtml5, $content, $match)) {
                $bodyDeclaredEncoding = $match['charset'];
                $newMeta = $match['before'] . "charset={$match['quote']}" . $this->targetEncoding . "{$match['quote']}" . $match['after'];
                $replacements['content'][$match[0]] = $newMeta;
            }
        } elseif (preg_match('#^(text|application)/xml#i', $contentType)) { // see http://stackoverflow.com/a/3272572/413531
            $patternXml = "#(?P<before><\\?xml[^>]+?)encoding=(?P<quote>[\"'])(?P<charset>[^\"']+?)\\2(?P<after>[^>]*?>)#i";
            if (preg_match($patternXml, $content, $match)) {
                $bodyDeclaredEncoding = $match['charset'];
                $newMeta = $match['before'] . "encoding={$match['quote']}" . $this->targetEncoding . "{$match['quote']}" . $match['after'];
                $replacements['content'][$match[0]] = $newMeta;
            }
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
            foreach ($replacements['headers'] as $headerKey => $value) {
                $headers_new = self::setByCaseInsensitiveKey($headers_new, $headerKey, $value);
            }
        }

        $converted = $this->transcoder->transcode($content, $finalEncoding, $this->targetEncoding);
        $converted_new = $converted;
        if ($this->replaceContent) {
            if ($replacements['content'] !== null) {
                foreach ($replacements['content'] as $oldContent => $newContent) {
                    $converted_new = str_replace($oldContent, $newContent, $converted_new);
                }
            }
        }

        return [
            'headers' => $headers_new,
            'content' => $converted_new,
        ];
    }

    /**
     * Gets array item by case-insensitive key.
     *
     * @param array $words
     * @param $key
     *
     * @return ?mixed
     */
    private static function getByCaseInsensitiveKey(array $words, $key) {
        foreach ($words as $headerWord => $value) {
            if (strcasecmp($headerWord, $key) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Sets array item by case-insensitive key.
     *
     * @param array $words
     * @param string $key
     * @param $newValue
     *
     * @return array
     */
    private static function setByCaseInsensitiveKey(array $words, $key, $newValue) {
        foreach ($words as $headerWord => $value) {
            if (strcasecmp($headerWord, $key) === 0) {
                $key = $headerWord;

                break;
            }
        }

        $words[$key] = $newValue;

        return $words;
    }
}
