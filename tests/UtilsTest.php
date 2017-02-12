<?php

namespace Fossar\GuzzleTranscoder\Tests;

class UtilsTest extends \PHPUnit\Framework\TestCase {
    public function test_splitHeaderWords_joinHeaderWords() {
        $tests = array(
            'key' => array(
                'base' => 'foo',
                'expected' => array(array('foo' => null))
            ),
            'key-value' => array(
                'base' => 'foo=bar',
                'expected' => array(array('foo' => 'bar'))
            ),
            'key-escaped-value' => array(
                'base' => 'foo="bar, baz"',
                'expected' => array(array('foo' => 'bar, baz'))
            ),
            'key-escaped-value-double-quotes' => array(
                'base' => 'foo="\"bar\""',
                'expected' => array(array('foo' => '"bar"'))
            ),
            'key-encoded-value' => array(
                'base' => "foo='bar%20baz'",
                'expected' => array(array('foo' => "'bar%20baz'")),
            ),
            'escaped-key-value' => array(
                'base' => '<foo,bar>; baz',
                'expected' => array(array('<foo,bar>' => null, 'baz' => null))
            ),
            'escaped-key-escaped-value-key-encoded-value' => array(
                'base' => '<foo,bar>,        bar=baz; foo=baz%20baz',
                'expected' => array(array('<foo,bar>' => null), array('bar' => 'baz', 'foo' => 'baz%20baz')),
                'join' => '<foo,bar>, bar=baz; foo=baz%20baz' // removed whitespaces
            ),
            'rel-canonical' => array(
                'base' => '<http://www.example.com/white-paper.html>; rel="canonical"', // @see http://googlewebmastercentral.blogspot.de/2011/06/supporting-relcanonical-http-headers.html
                'expected' => array(array('<http://www.example.com/white-paper.html>' => null, 'rel' => 'canonical')),
                'join' => '<http://www.example.com/white-paper.html>; rel=canonical', // removed unnecessary quotes
            ),
        );

        foreach ($tests as $name => $data) {
            $methods = array(
                'split' => array('Fossar\GuzzleTranscoder\Utils', 'splitHttpHeaderWords'),
                'join' => array('Fossar\GuzzleTranscoder\Utils', 'joinHttpHeaderWords'),
            );
            $expected = null;
            $base = null;

            foreach ($methods as $id => $method) {
                if ($id == 'split') {
                    $expected = $data['expected'];
                    $base = $data['base'];
                } else { // swap data for join
                    $expected = $data['base'];
                    if (array_key_exists('join', $data)) {
                        $expected = $data['join'];
                    }
                    $base = $data['expected'];
                }

                $res = call_user_func($method, $base);
                $msg = array(
                    'Error in method ' . json_encode($method) . " test $name:",
                    'Input    : ' . json_encode($base),
                    'Excpected: ' . json_encode($expected),
                    'Actual   : ' . json_encode($res),
                );
                $msg = implode("\n", $msg);
                $this->assertEquals($expected, $res, $msg);
            }
        }
    }
}
