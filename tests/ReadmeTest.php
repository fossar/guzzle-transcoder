<?php

namespace Fossar\GuzzleTranscoder\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class ReadmeTest extends \PHPUnit\Framework\TestCase {
    public function testReadme() {
        $contents = file_get_contents(__DIR__ . '/../README.md');

        $mock = new MockHandler([
            new Response(200, ['content-type' => 'text/html; charset=iso-8859-1; someOtherRandom="header in here"'], file_get_contents(__DIR__ . '/resources/iso-8859-1.html')),
        ]);

        $contents = str_replace('HandlerStack::create();', 'HandlerStack::create($mock);', $contents);

        preg_match_all('(```php\n(.*?)\n```)s', $contents, $matches, PREG_SET_ORDER);

        $codes = array_map(function($match) {
            return $match[1];
        }, $matches);

        $expectations = [
            file_get_contents(__DIR__ . '/resources/utf-8.html'),
        ];

        $cases = array_combine($codes, $expectations);

        foreach ($cases as $code => $expectation) {
            $this->expectOutputString($expectation);
            eval($code);
        }
    }
}
