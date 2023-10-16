<?php

namespace InterNations\Component\HttpMock;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use hmmmath\Fibonacci\FibonacciFactory;
use RuntimeException;
use Symfony\Component\Process\Process;

class Server extends Process
{
    private int $port;

    private string $host;

    private ?Client $client;

    public function __construct(int $port, string $host)
    {
        $this->port = $port;
        $this->host = $host;
        $packageRoot = __DIR__ . '/../';
        $command = [
            'php',
            '-dalways_populate_raw_post_data=-1',
            '-derror_log=',
            '-S=' . $this->getConnectionString(),
            '-t=public/',
            $packageRoot . 'public/index.php',
        ];

        parent::__construct($command, $packageRoot);
        $this->setTimeout(null);
    }

    /**
     * @param Expectation[] $expectations
     *
     * @throws RuntimeException
     * @throws GuzzleException
     */
    public function setUp(array $expectations) : void
    {
        foreach ($expectations as $expectation) {
            $options = [
                'json' => [
                    'matcher' => serialize($expectation->getMatcherClosures()),
                    'limiter' => serialize($expectation->getLimiter()),
                    'response' => Util::serializePsrMessage($expectation->getResponse()),
                    'responseCallback' => serialize($expectation->getResponseCallback()),
                ],
            ];

            $response = $this->getClient()->post('/_expectation', $options);

            if ($response->getStatusCode() !== 201) {
                throw new RuntimeException('Could not set up expectations: ' . $response->getBody()->getContents());
            }
        }
    }

    public function start(callable $callback = null, array $env = [])
    {
        parent::start($callback, $env);

        $this->pollWait();
    }

    public function stop($timeout = 10, $signal = null) : ?int
    {
        return parent::stop($timeout, $signal);
    }

    public function getClient() : Client
    {
        if (empty($this->client)) {
            $this->client = $this->createClient();
        }

        return $this->client;
    }

    public function getBaseUrl() : string
    {
        return sprintf('http://%s', $this->getConnectionString());
    }

    public function getConnectionString() : string
    {
        return sprintf('%s:%d', $this->host, $this->port);
    }

    /**
     * @throws GuzzleException
     */
    public function clean()
    {
        if (!$this->isRunning()) {
            $this->start();
        }

        $this->getClient()->delete('/_all');
    }

    public function getIncrementalErrorOutput() : string
    {
        return self::cleanErrorOutput(parent::getIncrementalErrorOutput());
    }

    public function getErrorOutput() : string
    {
        return self::cleanErrorOutput(parent::getErrorOutput());
    }

    private function createClient() : Client
    {
        return new Client(['base_uri' => $this->getBaseUrl(), 'http_errors' => false]);
    }

    private function pollWait()
    {
        foreach (FibonacciFactory::sequence(50000, 10000) as $sleepTime) {
            try {
                usleep($sleepTime);
                $this->getClient()->head('/_me');
                break;
            } catch (Exception $e) {
                continue;
            }
        }
    }

    private static function cleanErrorOutput($output) : string
    {
        if (!trim($output)) {
            return '';
        }

        $errorLines = [];

        foreach (explode(PHP_EOL, $output) as $line) {
            if (!$line) {
                continue;
            }

            if (!self::stringEndsWithAny($line, ['Accepted', 'Closing', ' started'])) {
                $errorLines[] = $line;
            }
        }

        return $errorLines ? implode(PHP_EOL, $errorLines) : '';
    }

    private static function stringEndsWithAny($haystack, array $needles) : bool
    {
        foreach ($needles as $needle) {
            if (substr($haystack, -1 * strlen($needle)) === $needle) {
                return true;
            }
        }

        return false;
    }
}
