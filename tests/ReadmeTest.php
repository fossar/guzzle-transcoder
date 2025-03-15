<?php

declare(strict_types=1);

namespace Fossar\GuzzleTranscoder\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

class ReadmeTest extends \PHPUnit\Framework\TestCase {
    /**
     * @param array<string, string> $mockedHeaders
     *
     * @dataProvider readmeExamples
     */
    public function testReadme(string $script, string $mockedSourceBody, array $mockedHeaders, string $expectedBody): void {
        $mock = new MockHandler([
            new Response(200, $mockedHeaders, $mockedSourceBody),
        ]);

        $script = str_replace('HandlerStack::create();', 'HandlerStack::create($mock);', $script);

        $this->expectOutputString($expectedBody);
        eval($script);
    }

    /**
     * @return iterable<array{string, string, array<string, string>, string}>
     */
    public function readmeExamples(): iterable {
        $contents = file_get_contents(__DIR__ . '/../README.md');
        \assert($contents !== false); // For PHPStan.

        preg_match_all('/<!-- Headers:\s*(?P<headers>.*?)\s*-->\n<!-- Mock response:\s*(?P<response>.*?)\s*-->\n<!-- Expected:\s*(?P<expected>.*?)\s*-->\n```php\n(?P<code>(?s).*?)\n```/', $contents, $matches, \PREG_SET_ORDER);

        foreach ($matches as $match) {
            $mockedSourceBody = file_get_contents(__DIR__ . '/resources/' . $match['response']);
            \assert($mockedSourceBody !== false); // For PHPStan.
            /** @var array<string, string> $mockedHeaders */
            $mockedHeaders = json_decode($match['headers'], true);
            $expectedBody = file_get_contents(__DIR__ . '/resources/' . $match['expected']);
            \assert($expectedBody !== false); // For PHPStan.

            yield [
                $match['code'],
                $mockedSourceBody,
                $mockedHeaders,
                $expectedBody,
            ];
        }
    }
}
