<?php

declare(strict_types=1);

namespace Fossar\GuzzleTranscoder;

class ContentTypeExtractor {
    /**
     * Regex pattern for HTML 4 meta tag – e.g. <meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">.
     */
    private const PATTERN_HTML4 = '/<meta[^>]+http-equiv\s*=\s*(?P<quote>["\']?)content-type\g{quote}[^>]*?>/i';
    /**
     * Regex pattern for HTML 5 meta tag – e.g. <meta charset=iso-8859-1>.
     */
    private const PATTERN_HTML5 = '/(?P<before><meta[^>]+?)charset\s*=\s*(?:(?P<quote>["\'])(?P<charset1>[^"\' ]+?)\g{quote}|(?P<charset2>[^"\'=<>`\s]+))(?P<after>[^>]*?>)/iJ';

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
     * @param array<string, list<string>|string> $headers
     *
     * @return ?array{string, ?string, array<string, ?string>} A triplet of MIME type, optional value of charset parameter and parameters from the header
     */
    public static function getContentTypeFromHeader(array $headers, string $targetEncoding): ?array {
        $contentType = Utils::getByCaseInsensitiveKey($headers, 'content-type');
        if ($contentType === null) {
            return null;
        }

        if (\is_array($contentType)) {
            // Multiple Content-Type headers are not permitted, as the header does not accept a comma-separated list:
            // https://www.rfc-editor.org/rfc/rfc2616#section-4.2
            // https://www.rfc-editor.org/rfc/rfc2616#section-14.17
            // We are attempting to handle it gracefully by dropping all but the first instance.
            $contentType = $contentType[0];
        }

        // content := "Content-Type" ":" type "/" subtype *(";" parameter)
        // see https://tools.ietf.org/html/rfc2045#section-5.1
        [$type, $params] = explode(';', $contentType . ';', 2);

        $parsed = Utils::splitHttpHeaderWords($params);
        if (\count($parsed) > 0) {
            $parsed = reset($parsed);
        }

        $encoding = Utils::getByCaseInsensitiveKey($parsed, 'charset');

        $newParsed = Utils::setByCaseInsensitiveKey($parsed, 'charset', $targetEncoding);

        return [$type, $encoding, $newParsed];
    }

    /**
     * Obtains MIME type from a text of HTML document.
     *
     * @return array{?string, array<string, string>} A pair of MIME type and replacements for the content
     */
    public static function getContentTypeFromHtml(string $content, string $targetEncoding): array {
        $bodyDeclaredEncoding = null;
        $replacements = [];

        // find http-equiv
        if (preg_match(self::PATTERN_HTML4, $content, $match)) {
            $pattern = '/(?P<before>.*)content\s*=\s*(?P<quote>["\'])(?P<content>.*?)\g{quote}(?P<after>.*)/i';
            if (preg_match($pattern, $match[0], $innerMatch)) {
                $parsed = Utils::splitHttpHeaderWords($innerMatch['content']);
                if (\count($parsed) > 0) {
                    $parsed = reset($parsed);
                }
                $bodyDeclaredEncoding = Utils::getByCaseInsensitiveKey($parsed, 'charset');
                $newParsed = Utils::setByCaseInsensitiveKey($parsed, 'charset', $targetEncoding);
                $newContent = Utils::joinHttpHeaderWords($newParsed);
                $newMeta = $innerMatch['before'] . "content={$innerMatch['quote']}" . $newContent . "{$innerMatch['quote']}" . $innerMatch['after'];
                $replacements[$match[0]] = $newMeta;
            }
        } elseif (preg_match(self::PATTERN_HTML5, $content, $match)) {
            $bodyDeclaredEncoding = $match['charset1'] . $match['charset2'];
            $newMeta = $match['before'] . "charset={$match['quote']}" . $targetEncoding . "{$match['quote']}" . $match['after'];
            $replacements[$match[0]] = $newMeta;
        }

        return [$bodyDeclaredEncoding, $replacements];
    }

    /**
     * Obtains MIME type from a text of XML document.
     *
     * @return array{?string, array<string, string>} A pair of MIME type and replacements for the content
     */
    public static function getContentTypeFromXml(string $content, string $targetEncoding): array {
        $bodyDeclaredEncoding = null;
        $replacements = [];

        $patternXml = "#(?P<before><\\?xml[^>]+?)encoding=(?P<quote>[\"'])(?P<charset>[^\"']+?)\\2(?P<after>[^>]*?>)#i";
        if (preg_match($patternXml, $content, $match)) {
            $bodyDeclaredEncoding = $match['charset'];
            $newMeta = $match['before'] . "encoding={$match['quote']}" . $targetEncoding . "{$match['quote']}" . $match['after'];
            $replacements[$match[0]] = $newMeta;
        }

        return [$bodyDeclaredEncoding, $replacements];
    }
}
