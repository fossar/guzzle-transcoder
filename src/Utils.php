<?php

namespace Fossar\GuzzleTranscoder;

class Utils {
    /**
     * HTTP Headers Util 0.1.
     *
     * @author: Keyvan Minoukadeh - hide@address.com - http://www.keyvan.net
     *
     * @license GNU General Public License, version 2
     *
     * @see (source) http://www.phpkode.com/source/s/http-headers-utility/http-headers-utility/HTTP_Headers_Util.php
     *
     * This method is based on:
     * <http://search.cpan.org/author/GAAS/libwww-perl-5.65/lib/HTTP/Headers/Util.pm>
     * by Gisle Aas.
     * The text here is copied from the documentation of the above, obviously
     * slightly modified as this is PHP not Perl.
     *
     * split_header_words
     *
     * This function will parse the header values given as argument into a
     * array containing key/value pairs.  The function
     * knows how to deal with ",", ";" and "=" as well as quoted values after
     * "=".  A list of space separated tokens are parsed as if they were
     * separated by ";".
     *
     * If the $headerValues passed as argument contains multiple values,
     * then they are treated as if they were a single value separated by
     * comma ",".
     *
     * This means that this function is useful for parsing header fields that
     * follow this syntax (BNF as from the HTTP/1.1 specification, but we relax
     * the requirement for tokens).
     *
     *   headers           = #header
     *   header            = (token | parameter) *( [";"] (token | parameter))
     *
     *   token             = 1*<any CHAR except CTLs or separators>
     *   separators        = "(" | ")" | "<" | ">" | "@"
     *                     | "," | ";" | ":" | "\" | <">
     *                     | "/" | "[" | "]" | "?" | "="
     *                     | "{" | "}" | SP | HT
     *
     *   quoted-string     = ( <"> *(qdtext | quoted-pair ) <"> )
     *   qdtext            = <any TEXT except <">>
     *   quoted-pair       = "\" CHAR
     *
     *   parameter         = attribute "=" value
     *   attribute         = token
     *   value             = token | quoted-string
     *
     * Each header is represented by an anonymous array of key/value
     * pairs.  The value for a simple token (not part of a parameter) is null.
     * Syntactically incorrect headers will not necessary be parsed as you
     * would want.
     *
     * This is easier to describe with some examples:
     *
     *    split_header_words('foo="bar"; port="80,81"; discard, bar=baz');
     *    split_header_words('text/html; charset="iso-8859-1");
     *    split_header_words('Basic realm="\"foo\\bar\""');
     *    split_header_words("</TheBook/chapter,2>;         rel=\"pre,vious\"; title*=UTF-8'de'letztes%20Kapitel, </TheBook/chapter4>;rel=\"next\"; title*=UTF-8'de'n%c3%a4chstes%20Kapitel");
     *
     * will return
     *
     *    [foo=>'bar', port=>'80,81', discard=>null], [bar=>'baz']
     *    ['text/html'=>null, charset=>'iso-8859-1']
     *    [Basic=>null, realm=>'"foo\bar"']
     *    ["</TheBook/chapter,2>" => null, "rel" => "pre,vious", "title*" => "UTF-8'de'letztes%20Kapitel" ], ["</TheBook/chapter4>" => null, "rel" => "next", "title*" => "UTF-8'de'n%c3%a4chstes%20Kapitel" ]
     *
     * @param string[]|string $headerValues
     *
     * @throws \Exception
     *
     * @return list<non-empty-array<string, ?string>>
     */
    public static function splitHttpHeaderWords($headerValues) {
        if (!\is_array($headerValues)) {
            $headerValues = [$headerValues];
        }

        $result = [];
        foreach ($headerValues as $header) {
            $cur = [];
            while ($header) {
                $key = '';
                $val = null;
                // Parse <link> header correctly http://tools.ietf.org/html/rfc5988#section-5
                if (preg_match('/^\s*(<[^>]*>)(.*)/', $header, $match)) {
                    $key = $match[1];
                    $header = $match[2];
                    $cur[$key] = null;
                } elseif (preg_match('/^\s*(=*[^\s=;,]+)(.*)/', $header, $match)) {
                    // 'token' or parameter 'attribute'
                    $key = $match[1];
                    $header = $match[2];
                    // a quoted value
                    if (preg_match('/^\s*=\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"(.*)/', $header, $match)) {
                        $val = $match[1];
                        $header = $match[2];
                        // remove backslash character escape
                        $val = preg_replace('/\\\\(.)/', '$1', $val);
                    // some unquoted value
                    } elseif (preg_match('/^\s*=\s*([^;,\s]*)(.*)/', $header, $match)) {
                        $val = trim($match[1]);
                        $header = $match[2];
                    }
                    // add details
                    $cur[$key] = $val;
                // reached the end, a new 'token' or 'attribute' about to start
                } elseif (preg_match('/^\s*,(.*)/', $header, $match)) {
                    $header = $match[1];
                    if (\count($cur)) {
                        $result[] = $cur;
                    }
                    $cur = [];
                // continue
                } elseif (preg_match('/^\s*;(.*)/', $header, $match)) {
                    $header = $match[1];
                } elseif (preg_match('/^\s+(.*)/', $header, $match)) {
                    $header = $match[1];
                } else {
                    throw new \Exception('This should not happen: "' . $header . '"');
                }
            }
            if (\count($cur)) {
                $result[] = $cur;
            }
        }

        return $result;
    }

    /**
     * HTTP Headers Util 0.1.
     *
     * @author: Keyvan Minoukadeh – hide@address.com – http://www.keyvan.net
     *
     * @license GNU General Public License, version 2
     *
     * @see (source)http://www.phpkode.com/source/s/http-headers-utility/http-headers-utility/HTTP_Headers_Util.php
     *
     * join_header_words
     *
     * This will do the opposite of the conversion done by split_header_words().
     * It takes a list of anonymous arrays as arguments (or a list of
     * key/value pairs) and produces a single header value.  Attribute values
     * are quoted if needed.
     *
     * Example:
     *
     *    join_header_words(array(array("text/plain" => null, "charset" => "iso-8859/1")));
     *    join_header_words(array("text/plain" => null, "charset" => "iso-8859/1"));
     *
     * will both return the string:
     *
     *    text/plain; charset="iso-8859/1"
     *
     * @return string
     *
     * @see http://tools.ietf.org/html/rfc5988#section-5
     */
    public static function joinHttpHeaderWords(array $headerValues) {
        if (\count($headerValues) === 0) {
            return '';
        }
        // evaluate if its a multidimensional array
        $first = reset($headerValues);
        if (!\is_array($first)) {
            $headerValues = [$headerValues];
        }

        $spaces = '\\s';
        $ctls = '\\x00-\\x1F\\x7F'; //@see http://stackoverflow.com/a/1497928/413531
        $tspecials = '()<>@,;:<>/[\\]?.="\\\\';
        $tokenPattern = "#^[^{$spaces}{$ctls}{$tspecials}]+$#";
        $result = [];
        foreach ($headerValues as $header) {
            $attr = [];
            foreach ($header as $key => $val) {
                if (isset($val)) {
                    if (preg_match($tokenPattern, $val)) {
                        $key .= "=$val";
                    } else {
                        $val = preg_replace('/(["\\\\])/', '\\\\$1', $val);
                        $key .= "=\"$val\"";
                    }
                }
                $attr[] = $key;
            }
            if (\count($attr)) {
                $result[] = implode('; ', $attr);
            }
        }

        return implode(', ', $result);
    }

    /**
     * Gets array item by case-insensitive key.
     *
     * @template T
     *
     * @param array<string, T> $words
     * @param string $key
     *
     * @return ?T
     */
    public static function getByCaseInsensitiveKey(array $words, $key) {
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
     * @template T
     *
     * @param array<string, T> $words
     * @param string $key
     * @param T $newValue
     *
     * @return array<string, T>
     */
    public static function setByCaseInsensitiveKey(array $words, $key, $newValue) {
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
