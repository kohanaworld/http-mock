<?php

namespace InterNations\Component\HttpMock\Tests\Matcher;

use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\Testing\AbstractTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;

class ExtractorFactoryTest extends AbstractTestCase
{
    /** @var ExtractorFactory */
    private $extractorFactory;

    /** @var Request|MockObject */
    private $request;

    public function setUp() : void
    {
        $this->extractorFactory = new ExtractorFactory();
    }

    public function testGetMethod()
    {
        $request = Request::create('/', 'POST');

        $extractor = $this->extractorFactory->createMethodExtractor();
        $this->assertSame('POST', $extractor($request));
    }

    public function testGetPath()
    {
        $request = Request::create('/foo/bar', 'GET');

        $extractor = $this->extractorFactory->createPathExtractor();
        $this->assertSame('/foo/bar', $extractor($request));
    }

    public function testGetPathWithBasePath()
    {
        $request = Request::create('/foo/bar', 'GET');

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('/bar', $extractor($request));
    }

    public function testGetPathWithBasePathTrailingSlash()
    {
        $request = Request::create('/foo/bar', 'GET');

        $extractorFactory = new ExtractorFactory('/foo/');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('/bar', $extractor($request));
    }

    public function testGetPathWithBasePathThatDoesNotMatch()
    {
        $request = Request::create('/bar', 'GET');

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createPathExtractor();
        $this->assertSame('', $extractor($request));
    }

    public function testGetHeaderWithExistingHeader()
    {
        $request = Request::create('/', 'GET', [], [], [], [], json_encode(['key' => 'value']));
        $request->headers->set('Content-Type', 'application/json');

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExtractor('content-type');
        $this->assertSame('application/json', $extractor($request));
    }

    public function testGetHeaderWithNonExistingHeader()
    {
        $request = Request::create('/', 'GET', [], [], [], [], json_encode(['key' => 'value']));
        $request->headers->set('X-Foo', 'bar');

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExtractor('content-type');
        $this->assertSame(null, $extractor($request));
    }

    public function testHeaderExistsWithExistingHeader()
    {
        $request = Request::create('/', 'GET', [], [], [], [], json_encode(['key' => 'value']));
        $request->headers->set('Content-Type', 'application/json');

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExistsExtractor('content-type');
        $this->assertTrue($extractor($request));
    }

    public function testHeaderExistsWithNonExistingHeader()
    {
        $request = Request::create('/', 'GET', [], [], [], [], json_encode(['key' => 'value']));
        $request->headers->set('X-Foo', 'bar');

        $extractorFactory = new ExtractorFactory('/foo');

        $extractor = $extractorFactory->createHeaderExistsExtractor('content-type');
        $this->assertFalse($extractor($request));
    }
}
