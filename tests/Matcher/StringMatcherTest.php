<?php

namespace InterNations\Component\HttpMock\Tests\Matcher;

use InterNations\Component\HttpMock\Matcher\StringMatcher;
use InterNations\Component\Testing\AbstractTestCase;
use Symfony\Component\HttpFoundation\Request;

class StringMatcherTest extends AbstractTestCase
{
    public function testConversionToString()
    {
        $matcher = new StringMatcher('0');
        $matcher->setExtractor(static function () {
            return 0;
        });
        self::assertTrue($matcher->getMatcher()(Request::create('/', 'GET')));
    }
}
