<?php

namespace InterNations\Component\HttpMock\Matcher;

use SuperClosure\SerializableClosure;

interface MatcherInterface
{
    /**
     * @return SerializableClosure
     */
    public function getMatcher();

    public function setExtractor(\Closure $extractor);
}
