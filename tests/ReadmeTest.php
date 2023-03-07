<?php

declare(strict_types=1);

namespace Fossar\GuzzleTranscoder\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class ReadmeTest extends \PHPUnit\Framework\TestCase {
    public function testReadme(): void {
        $contents = file_get_contents(__DIR__ . '/../README.md');
        \assert($contents !== false); // For PHPStan.

        $mockedSource = file_get_contents(__DIR__ . '/resources/iso-8859-1.html');
        \assert($mockedSource !== false); // For PHPStan.

        $mock = new MockHandler([
            new Response(200, ['content-type' => 'text/html; charset=iso-8859-1; someOtherRandom="header in here"'], $mockedSource),
        ]);

        $contents = str_replace('HandlerStack::create();', 'HandlerStack::create($mock);', $contents);

        preg_match_all('(```php\n(.*?)\n```)s', $contents, $matches, \PREG_SET_ORDER);

        $codes = array_map(function($match) {
            return $match[1];
        }, $matches);

        $expected1 = file_get_contents(__DIR__ . '/resources/utf-8.html');
        \assert($expected1 !== false); // For PHPStan.
        $expectations = [
            $expected1,
        ];

        $cases = array_combine($codes, $expectations);
        \assert($cases !== false); // For PHPStan.

        foreach ($cases as $code => $expectation) {
            $this->expectOutputString($expectation);
            eval($code);
        }
    }
}
