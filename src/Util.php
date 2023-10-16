<?php

namespace InterNations\Component\HttpMock;

use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface as Response;
use UnexpectedValueException;

final class Util
{
    public static function deserialize(string $string)
    {
        $result = Util::silentDeserialize($string);

        if ($result === false) {
            throw new UnexpectedValueException('Cannot deserialize string');
        }

        return $result;
    }

    public static function silentDeserialize(string $string)
    {
        // @codingStandardsIgnoreStart
        return \unserialize($string);
        // @codingStandardsIgnoreEnd
    }

    public static function responseDeserialize(string $string) : Response
    {
        return Message::parseResponse($string);
    }

    public static function serializePsrMessage(MessageInterface $message) : string
    {
        $headers = $message->getHeaders();
        foreach ($headers as $key => $list) {
            foreach ($list as $value) {
                if (substr($key, 0, 5) === 'HTTP_') {
                    $newKey = substr($key, 5);
                    $newKey = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $newKey))));
                    $message = $message->withoutHeader($key)->withHeader($newKey, $value);
                }
            }
        }

        if (!$message->hasHeader('cache-control')) {
            $message->withHeader('cache-control', 'no-cache, private');
        }

        return Message::toString($message);
    }
}
