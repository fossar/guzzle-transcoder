<?php

namespace Fossar\GuzzleTranscoder\Tests;

use GuzzleHttp\Client;
use Fossar\GuzzleTranscoder\GuzzleTranscoder;

class ReadmeTest extends \PHPUnit\Framework\TestCase {
    public function test_readme() {
        $contents = file_get_contents(__DIR__ . '/../README.md');
        str_replace('http://www.myseosolution.de/scripts/encoding-test.php?enc=iso', 'tests/resources/iso-8859-1.html', $contents);
        preg_match_all('(```php\n(.*?)\n```)s', $contents, $matches, PREG_SET_ORDER);

        $codes = array_map(function($match) {
            return $match[1];
        }, $matches);

        $expectations = array(
            file_get_contents(__DIR__ . '/resources/utf-8.html'),
        );

        $cases = array_combine($codes, $expectations);

        foreach ($cases as $code => $expectation) {
            $this->expectOutputString($expectation);
            eval($code);
        }
    }
}
