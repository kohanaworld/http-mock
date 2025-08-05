<?php

namespace InterNations\Component\HttpMock\Matcher;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;

interface MatcherInterface
{
    public function getMatcher(): SerializableClosure;

    public function setExtractor(Closure $extractor) : void;
}
