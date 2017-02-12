<?php

namespace Fossar\GuzzleTranscoder;

use Ddeboer\Transcoder\Transcoder;
use GuzzleHttp\Event\AbstractTransferEvent;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Stream\Stream;

class GuzzleTranscoder implements SubscriberInterface {
    /** @var Transcoder */
    private $transcoder;

    /** @var string */
    private $targetEncoding;

    /** @var bool */
    private $replaceHeaders;

    /** @var bool */
    private $replaceContent;

    public function __construct($targetEncoding = 'UTF-8', $replaceHeaders = true, $replaceContent = false) {
        $this->transcoder = Transcoder::create();
        $this->targetEncoding = $targetEncoding;
        $this->replaceHeaders = $replaceHeaders;
        $this->replaceContent = $replaceContent;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array
     */
    public function getEvents() {
        return array(
            'complete' => array('convert'),
            'error' => array('convert')
        );
    }

    public function convert(AbstractTransferEvent $event) {
        $response = $event->getResponse();

        if ($response === null) {
            return;
        }

        $stream = $response->getBody();
        if ($stream === null) { // no body - nothing to convert
            return;
        }

        $headers = $response->getHeaders();
        $content = $stream->__toString();
        $result = $this->convertResponse($headers, $content);
        if ($result !== null) {
            $body = new Stream(fopen('php://temp', 'r+')); // see Guzzle 4.1.7 > GuzzleHttp\Adapter\Curl\RequestMediator::writeResponseBody
            $response->setBody($body);
            $body->write($result['content']);
            $response->setHeaders($result['headers']);
        }
    }

    /**
     * Converts the given $content to the $targetEncoding. The original encoding is defined by (in order):
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
        $encodings = array(
            'header' => null,
            'content' => null,
        );
        $replacements = array(
            'header' => null,
            'content' => null,
        );

        $contentType = self::getByCaseInsensitiveKey($headers, 'content-type');
        if ($contentType === null) {
            $contentType = '';
        } elseif (is_array($contentType)) {
            $contentType = $contentType[0];
        }

        $parsed = Utils::splitHttpHeaderWords($contentType);
        if (count($parsed) > 0) {
            $parsed = reset($parsed);
        }
        //check the header
        $encoding = self::getByCaseInsensitiveKey($parsed, 'charset');
        if ($encoding !== null) {
            $encodings['header'] = $encoding;
        }
        $newParsed = self::setByCaseInsensitiveKey($parsed, 'charset', $this->targetEncoding);
        $replacements['header']['content-type'] = Utils::joinHttpHeaderWords($newParsed);
        // else, check the body
        if (preg_match('#^text/html#i', $contentType)) {
            // find http-equiv
            $patternHtml4 = "#<meta[^>]+http-equiv=[\"']?content-type[\"']?[^>]*?>#i"; // html 4 - e.g. <meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
            $patternHtml5 = "#(?P<before><meta[^>]+?)charset=(?P<quote>[\"'])(?P<charset>[^\"' ]+?)\\2(?P<after>[^>]*?>)#i"; // e.g. <meta charset=iso-8859-1> - for html 5 http://webdesign.about.com/od/metatags/qt/meta-charset.htm
            if (preg_match($patternHtml4, $content, $match)) {
                $pattern = "#(?P<before>.*)content=(?P<quote>[\"'])(?P<content>.*?)\\2(?P<after>.*)#";
                if (preg_match($pattern, $match[0], $innerMatch)) {
                    $parsed = Utils::splitHttpHeaderWords($innerMatch['content']);
                    if (count($parsed) > 0) {
                        $parsed = reset($parsed);
                    }
                    $encodings['content'] = self::getByCaseInsensitiveKey($parsed, 'charset');
                    $newParsed = self::setByCaseInsensitiveKey($parsed, 'charset', $this->targetEncoding);
                    $newContent = Utils::joinHttpHeaderWords($newParsed);
                    $newMeta = $innerMatch['before'] . "content={$innerMatch['quote']}" . $newContent . "{$innerMatch['quote']}" . $innerMatch['after'];
                    $replacements['content'][$match[0]] = $newMeta;
                }
            } elseif (preg_match($patternHtml5, $content, $match)) {
                $encodings['content'] = $match['charset'];
                $newMeta = $match['before'] . "charset={$match['quote']}" . $this->targetEncoding . "{$match['quote']}" . $match['after'];
                $replacements['content'][$match[0]] = $newMeta;
            }
        } elseif (preg_match('#^(text|application)/xml#i', $contentType)) { // see http://stackoverflow.com/a/3272572/413531
            $patternXml = "#(?P<before><\\?xml[^>]+?)encoding=(?P<quote>[\"'])(?P<charset>[^\"']+?)\\2(?P<after>[^>]*?>)#i";
            if (preg_match($patternXml, $content, $match)) {
                $encodings['content'] = $match['charset'];
                $newMeta = $match['before'] . "encoding={$match['quote']}" . $this->targetEncoding . "{$match['quote']}" . $match['after'];
                $replacements['content'][$match[0]] = $newMeta;
            }
        }

        $finalEncoding = null;
        foreach ($encodings as $type => $encoding) {
            if ($encoding !== null) {
                $finalEncoding = $encoding;
                break;
            }
        }
        if ($finalEncoding === null) {
            return null;
        }

        $converted = $this->transcoder->transcode($content, $finalEncoding, $this->targetEncoding);
        $headers_new = $headers;
        if ($this->replaceHeaders) {
            foreach ($replacements['header'] as $headerKey => $value) {
                $headers_new = self::setByCaseInsensitiveKey($headers_new, $headerKey, $value);
            }
        }

        $converted_new = $converted;
        if ($this->replaceContent) {
            if ($replacements['content'] !== null) {
                foreach ($replacements['content'] as $oldContent => $newContent) {
                    $converted_new = str_replace($oldContent, $newContent, $converted_new);
                }
            }
        }

        return array(
            'headers' => $headers_new,
            'content' => $converted_new
        );
    }

    /**
     * @param array $words
     * @param $key
     *
     * @return mixed|null
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
     * @param array $words
     * @param $key
     * @param $newValue
     *
     * @return array
     */
    private static function setByCaseInsensitiveKey(array $words, $key, $newValue) {
        foreach ($words as $headerWord => $value) {
            if (strcasecmp($headerWord, $key) === 0) {
                $words[$headerWord] = $newValue;

                return $words;
            }
        }
        $words[$key] = $newValue;

        return $words;
    }
}
