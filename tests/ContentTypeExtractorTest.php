<?php

declare(strict_types=1);

namespace Fossar\GuzzleTranscoder\Tests;

use Fossar\GuzzleTranscoder\ContentTypeExtractor;
use PHPUnit\Framework\TestCase;

class ContentTypeExtractorTest extends TestCase {
    /**
     * @dataProvider contentTypes
     *
     * @param array<string, string> $expectedContentReplacements
     */
    public function testGetContentTypeFromHtml(string $html, string $expectedDeclaredEncoding, array $expectedContentReplacements): void {
        [$bodyDeclaredEncoding, $contentReplacements] = ContentTypeExtractor::getContentTypeFromHtml($html, 'placeholder-encoding');
        $this->assertSame($expectedContentReplacements, $contentReplacements);
        $this->assertSame($expectedDeclaredEncoding, $bodyDeclaredEncoding);
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public function contentTypes(): iterable {
        // https://html.spec.whatwg.org/multipage/syntax.html#attributes-2
        yield 'HTML5, double quotes' => [
            <<<HTML
                <meta charset="iso-8859-1">
                HTML,
            'iso-8859-1',
            [
                '<meta charset="iso-8859-1">' => '<meta charset="placeholder-encoding">',
            ],
        ];

        yield 'HTML5, single quotes' => [
            <<<HTML
                <meta charset='iso-8859-1'>
                HTML,
            'iso-8859-1',
            [
                "<meta charset='iso-8859-1'>" => "<meta charset='placeholder-encoding'>",
            ],
        ];

        yield 'HTML5, unquoted' => [
            <<<HTML
                <meta charset=iso-8859-1>
                HTML,
            'iso-8859-1',
            [
                '<meta charset=iso-8859-1>' => '<meta charset=placeholder-encoding>',
            ],
        ];

        yield 'HTML5, unquoted, spaces around' => [
            <<<HTML
                <meta charset = iso-8859-1>
                HTML,
            'iso-8859-1',
            [
                '<meta charset = iso-8859-1>' => '<meta charset=placeholder-encoding>',
            ],
        ];

        yield 'HTML5, unquoted, extra attributes' => [
            <<<HTML
                <meta foo charset=iso-8859-1 bar baz="2">
                HTML,
            'iso-8859-1',
            [
                '<meta foo charset=iso-8859-1 bar baz="2">' => '<meta foo charset=placeholder-encoding bar baz="2">',
            ],
        ];

        yield 'HTML5, random case' => [
            <<<HTML
                <MeTA chArSEt="ISo-8859-1">
                HTML,
            'ISo-8859-1',
            [
                '<MeTA chArSEt="ISo-8859-1">' => '<MeTA charset="placeholder-encoding">',
            ],
        ];

        yield '(X)HTML5, unquoted' => [
            <<<HTML
                <meta charset=iso-8859-1 />
                HTML,
            'iso-8859-1',
            [
                '<meta charset=iso-8859-1 />' => '<meta charset=placeholder-encoding />',
            ],
        ];

        yield '(X)HTML5, tight' => [
            <<<HTML
                <meta charset="iso-8859-1"/>
                HTML,
            'iso-8859-1',
            [
                '<meta charset="iso-8859-1"/>' => '<meta charset="placeholder-encoding"/>',
            ],
        ];

        // If [a solidus in a start tag of a void element is] directly preceded by an unquoted attribute value, it becomes part of the attribute value rather than being discarded by the parser.
        // https://html.spec.whatwg.org/multipage/syntax.html#start-tags
        yield '(X)HTML5, unquoted, misplaced solidus' => [
            <<<HTML
                <meta charset=iso-8859-1/>
                HTML,
            'iso-8859-1/',
            [
                '<meta charset=iso-8859-1/>' => '<meta charset=placeholder-encoding>',
            ],
        ];

        yield 'HTML4, double quotes' => [
            <<<HTML
                <meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
                HTML,
            'ISO-8859-1',
            [
                '<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">' => '<meta http-equiv="content-type" content="text/html; charset=placeholder-encoding">',
            ],
        ];

        yield 'HTML4, double quotes, other way around' => [
            <<<HTML
                <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
                HTML,
            'ISO-8859-1',
            [
                '<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">' => '<meta content="text/html; charset=placeholder-encoding" http-equiv="content-type">',
            ],
        ];

        yield 'HTML4, double quotes, extra attributes, other way around' => [
            <<<HTML
                <meta foo="bar" content="text/html; charset=ISO-8859-1" test middle http-equiv="content-type" after='something'>
                HTML,
            'ISO-8859-1',
            [
                '<meta foo="bar" content="text/html; charset=ISO-8859-1" test middle http-equiv="content-type" after=\'something\'>' => '<meta foo="bar" content="text/html; charset=placeholder-encoding" test middle http-equiv="content-type" after=\'something\'>',
            ],
        ];

        yield 'HTML4, single quotes' => [
            <<<HTML
                <meta http-equiv='content-type' content='text/html; charset=ISO-8859-1'>
                HTML,
            'ISO-8859-1',
            [
                "<meta http-equiv='content-type' content='text/html; charset=ISO-8859-1'>" => "<meta http-equiv='content-type' content='text/html; charset=placeholder-encoding'>",
            ],
        ];

        yield 'HTML4, unquoted+single quotes' => [
            <<<HTML
                <meta http-equiv=content-type content='text/html; charset=ISO-8859-1'>
                HTML,
            'ISO-8859-1',
            [
                "<meta http-equiv=content-type content='text/html; charset=ISO-8859-1'>" => "<meta http-equiv=content-type content='text/html; charset=placeholder-encoding'>",
            ],
        ];

        // https://httpwg.org/specs/rfc9110.html#field.content-type
        yield 'HTML4, internally quoted, extra parameters' => [
            <<<HTML
                <meta http-equiv=content-type content='text/html;foo;charset="ISO-8859-1";bar'>
                HTML,
            'ISO-8859-1',
            [
                "<meta http-equiv=content-type content='text/html;foo;charset=\"ISO-8859-1\";bar'>" => "<meta http-equiv=content-type content='text/html; foo; charset=placeholder-encoding; bar'>",
            ],
        ];

        yield 'HTML4, single quotes+double quotes+spaces around' => [
            <<<HTML
                <meta http-equiv =  "content-type" content  	=  "text/html; charset=ISO-8859-1">
                HTML,
            'ISO-8859-1',
            [
                '<meta http-equiv =  "content-type" content  	=  "text/html; charset=ISO-8859-1">' => '<meta http-equiv =  "content-type" content="text/html; charset=placeholder-encoding">',
            ],
        ];

        yield 'HTML4, random case' => [
            <<<HTML
                <meTA HTTp-EQuIv="conTeNt-TYpe" CoNTeNt="text/Html; cHArSeT=ISO-8859-1">
                HTML,
            'ISO-8859-1',
            [
                '<meTA HTTp-EQuIv="conTeNt-TYpe" CoNTeNt="text/Html; cHArSeT=ISO-8859-1">' => '<meTA HTTp-EQuIv="conTeNt-TYpe" content="text/Html; cHArSeT=placeholder-encoding">',
            ],
        ];

        yield '(X)HTML4' => [
            <<<HTML
                <meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"/>
                HTML,
            'ISO-8859-1',
            [
                '<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"/>' => '<meta http-equiv="content-type" content="text/html; charset=placeholder-encoding"/>',
            ],
        ];

        yield 'multiple declarations' => [
            <<<HTML
                <meta http-equiv="content-type" test content="text/html; charset=ISO-8859-1">
                <meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
                <meta charset=UTF-8>
                HTML,
            'ISO-8859-1',
            [
                '<meta http-equiv="content-type" test content="text/html; charset=ISO-8859-1">' => '<meta http-equiv="content-type" test content="text/html; charset=placeholder-encoding">',
            ],
        ];
    }
}
