<?php

namespace InterNations\Component\HttpMock\Matcher;

use Closure;
use Opis\Closure\SerializableClosure;
use Psr\Http\Message\RequestInterface as Request;

abstract class AbstractMatcher implements MatcherInterface
{
    protected $extractor;

    abstract protected function createMatcher();

    public function setExtractor(Closure $extractor) : void
    {
        $this->extractor = $extractor;
    }

    public function getMatcher() : SerializableClosure
    {
        $matcher = new SerializableClosure($this->createMatcher());
        $extractor = new SerializableClosure($this->createExtractor());

        return new SerializableClosure(
            static function (Request $request) use ($matcher, $extractor) {
                return $matcher($extractor($request));
            }
        );
    }

    protected function createExtractor()
    {
        if (!$this->extractor) {
            return static function (Request $request) {
                return $request;
            };
        }

        return $this->extractor;
    }
}
