<?php

// @codingStandardsIgnoreLine

namespace InterNations\Component\HttpMock;

use Error;
use Exception;
use Fig\Http\Message\StatusCodeInterface;
use League\Container\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Factory\AppFactory;
use Slim\Factory\Psr17\NyholmPsr17Factory;

$autoloadFiles = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
];

$autoloaderFound = false;

foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require_once $autoloadFile;
        $autoloaderFound = true;
        break;
    }
}

if (!$autoloaderFound) {
    throw new RuntimeException(sprintf('Could not locate autoloader file. Tried "%s"', implode('", "', $autoloadFiles)));
}

$container = new Container();

$container->add('storage', RequestStorage::class)->addArguments([
    getmypid(),
    __DIR__ . '/../state/',
]);

AppFactory::setContainer($container);
$responseFactory = new NyholmPsr17Factory();

$app = AppFactory::create();

AppFactory::setResponseFactory($responseFactory->getResponseFactory());
$app->addErrorMiddleware(false, false, false);

$app->delete(
    '/_expectation',
    function (Request $request, Response $response) use ($container) {
        $container->get('storage')->clear($request, 'expectations');

        return $response->withStatus(StatusCodeInterface::STATUS_OK);
    }
);

$app->post(
    '/_expectation',
    function (Request $request, Response $response) use ($container) {
        $data = json_decode($request->getBody()->getContents(), true);
        $matcher = [];

        if (!empty($data['matcher']) && is_string($data['matcher'])) {
            $matcher = Util::silentDeserialize($data['matcher']);
            $validator = function ($closure) {
                return is_callable($closure);
            };

            if (!is_array($matcher) || count(array_filter($matcher, $validator)) !== count($matcher)) {
                $response = $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);

                return $response->getBody()->write('POST data key "matcher" must be a serialized list of closures');
            }
        }

        if (empty($data['response'])) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);

            return $response->getBody()->write('POST data key "response" not found in POST data');
        }

        try {
            $responseToSave = Util::responseDeserialize($data['response']);
        } catch (Exception $e) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);

            return $response->getBody()->write('POST data key "response" must be an http response message in text form');
        }

        $limiter = null;

        if (!empty($data['limiter'])) {
            $limiter = Util::silentDeserialize($data['limiter']);

            if (!is_callable($limiter)) {
                $response = $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);

                return $response->getBody()->write('POST data key "limiter" must be a serialized closure');
            }
        }

        // Fix issue with silex default error handling
        // not sure if this is need anymore
        $response = $response->withHeader('X-Status-Code', $response->getStatusCode());

        $responseCallback = null;
        if (!empty($data['responseCallback'])) {
            $responseCallback = Util::silentDeserialize($data['responseCallback']);

            if ($responseCallback !== null && !is_callable($responseCallback)) {
                $response = $response->withStatus(StatusCodeInterface::STATUS_EXPECTATION_FAILED);

                return $response->getBody()->write('POST data key "responseCallback" must be a serialized closure: '
                    . print_r($data['responseCallback'], true));
            }
        }

        $container->get('storage')->prepend(
            $request,
            'expectations',
            [
                'matcher' => $matcher,
                'response' => $data['response'],
                'limiter' => $limiter,
                'responseCallback' => $responseCallback,
                'runs' => 0,
            ]
        );

        return $response->withStatus(StatusCodeInterface::STATUS_CREATED);
    }
);

$container->add('phpErrorHandler', function ($container) {
    return function (Request $request, Response $response, Error $e) {
        $response = $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR)
            ->withHeader('Content-Type', 'text/plain');

        return $response->getBody()->write($e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    };
});

$container->add('notFoundHandler', function ($container) {
    return function (Request $request, Response $response) use ($container) {
        /* @var Container $container */
        $container->get('storage')->append(
            $request,
            'requests',
            serialize(
                [
                    'request' => Util::serializePsrMessage($request),
                    'server' => $request->getServerParams(),
                ]
            )
        );

        $notFoundResponse = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

        $expectations = $container->get('storage')->read($request, 'expectations');

        foreach ($expectations as $pos => $expectation) {
            foreach ($expectation['matcher'] as $matcher) {
                if (!$matcher($request)) {
                    continue 2;
                }
            }

            if (isset($expectation['limiter']) && !$expectation['limiter']($expectation['runs'])) {
                if ($notFoundResponse->getStatusCode() != StatusCodeInterface::STATUS_GONE) {
                    $notFoundResponse = $response->withStatus(StatusCodeInterface::STATUS_GONE);
                    $notFoundResponse->getBody()->write('Expectation no longer applicable');
                }
                continue;
            }

            $expectations[$pos]['runs']++;
            $container->get('storage')->store($request, 'expectations', $expectations);

            $r = Util::responseDeserialize($expectation['response']);
            if (!empty($expectation['responseCallback'])) {
                $callback = $expectation['responseCallback'];

                return $callback($r);
            }

            return $r;
        }

        if ($notFoundResponse->getStatusCode() == StatusCodeInterface::STATUS_GONE) {
            $notFoundResponse = $notFoundResponse->getBody()->write('No matching expectation found');
        }

        return $notFoundResponse;
    };
});

$container->add('errorHandler', function ($container) {
    return function (Request $request, Response $response, Exception $e) {
        $response = $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);

        return $response->getBody()->write('Server error: ' . $e->getMessage());
    };
});

$app->get(
    '/_request/count',
    function (Request $request, Response $response) use ($container) {
        $count = count($container->get('storage')->read($request, 'requests'));

        $response = $response->withStatus(StatusCodeInterface::STATUS_OK)
            ->withHeader('Content-Type', 'text/plain');

        return $response->getBody()->write($count);
    }
);

$app->get(
    '/_request/{index:[0-9]+}',
    function (Request $request, Response $response, $args) use ($container) {
        $index = (int) $args['index'];
        $requestData = $container->get('storage')->read($request, 'requests');

        if (!isset($requestData[$index])) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

            return $response->getBody()->write('Index ' . $index . ' not found');
        }

        $response = $response->withStatus(StatusCodeInterface::STATUS_OK)
            ->withHeader('Content-Type', 'text/plain');

        return $response->getBody()->write($requestData[$index]);
    }
);

$app->delete(
    '/_request/{action:last|latest|first}',
    function (Request $request, Response $response, $args) use ($container) {
        $action = $args['action'];

        $requestData = $container->get('storage')->read($request, 'requests');
        $fn = 'array_' . ($action === 'last' || $action === 'latest' ? 'pop' : 'shift');
        $requestString = $fn($requestData);
        $container->get('storage')->store($request, 'requests', $requestData);

        if (!$requestString) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

            return $response->getBody()->write($action . ' not possible');
        }

        $response = $response->withStatus(StatusCodeInterface::STATUS_OK)
            ->withHeader('Content-Type', 'text/plain');

        return $response->getBody()->write($requestString);
    }
);

$app->get(
    '/_request/{action:last|latest|first}',
    function (Request $request, Response $response, $args) use ($container) {
        $action = $args['action'];
        $requestData = $container->get('storage')->read($request, 'requests');
        $fn = 'array_' . ($action === 'last' || $action === 'latest' ? 'pop' : 'shift');
        $requestString = $fn($requestData);

        if (!$requestString) {
            $response = $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);

            return $response->getBody()->write($action . ' not available');
        }

        $response = $response->withStatus(StatusCodeInterface::STATUS_OK)
            ->withHeader('Content-Type', 'text/plain');

        return $response->getBody()->write($requestString);
    }
);

$app->delete(
    '/_request',
    function (Request $request, Response $response) use ($container) {
        $container->get('storage')->store($request, 'requests', []);

        return $response->withStatus(StatusCodeInterface::STATUS_OK);
    }
);

$app->delete(
    '/_all',
    function (Request $request, Response $response) use ($container) {
        $container->get('storage')->store($request, 'requests', []);
        $container->get('storage')->store($request, 'expectations', []);

        return $response->withStatus(StatusCodeInterface::STATUS_OK);
    }
);

$app->get(
    '/_me',
    function (Request $request, Response $response) {
        $response = $response->withStatus(StatusCodeInterface::STATUS_IM_A_TEAPOT)
            ->withHeader('Content-Type', 'text/plain');

        return $response->getBody()->write('O RLY?');
    }
);

return $app;
