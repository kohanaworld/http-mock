<?php

declare(strict_types=1);

namespace InterNations\Component\HttpMock\Tests;
use InterNations\Component\HttpMock\PHPUnit\HttpMockTrait;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    use HttpMockTrait;

    public static function setUpBeforeClass(): void
    {
        static::setUpHttpMockBeforeClass('8082', 'localhost');
    }

    public static function tearDownAfterClass(): void
    {
        static::tearDownHttpMockAfterClass();
    }

    public function setUp(): void
    {
        $this->setUpHttpMock();
    }

    public function tearDown(): void
    {
        $this->tearDownHttpMock();
    }

    public function testAccessingRecordedRequests()
    {
        $this->http->mock
            ->when()
            ->methodIs('POST')
            ->pathIs('/foo')
            ->then()
            ->body('mocked body')
            ->end();
        $this->http->setUp();

        $this->assertSame('mocked body', $this->http->client->post('http://localhost:8082/foo')->send()->getBody(true));

        $this->assertSame('POST', $this->http->requests->latest()->getMethod());
        //$this->assertSame('/foo', $this->http->requests->latest()->getPath());
    }
}
