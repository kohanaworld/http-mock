<?php

namespace InterNations\Component\HttpMock\PHPUnit;

trait HttpMockTrait
{
    /** @var HttpMockFacade|HttpMockFacadeMap */
    protected static $staticHttp;

    /** @var HttpMockFacade|HttpMockFacadeMap */
    protected $http;

    protected function setUpHttpMock()
    {
        static::assertHttpMockSetup();

        $this->http = clone static::$staticHttp;
    }

    protected function tearDownHttpMock()
    {
        if (!$this->http) {
            return;
        }

        $http = $this->http;
        $this->http = null;
        $http->each(
            function (HttpMockFacade $facade) {
                $this->assertSame(
                    '',
                    (string) $facade->server->getIncrementalErrorOutput(),
                    'HTTP mock server standard error output should be empty'
                );
            }
        );
    }

    public static function getHttpMockDefaultPort()
    {
        return 28080;
    }

    public static function getHttpMockDefaultHost()
    {
        return 'localhost';
    }

    protected static function setUpHttpMockBeforeClass($port = null, $host = null, $basePath = null, $name = null)
    {
        $port = $port ?: static::getHttpMockDefaultPort();
        $host = $host ?: static::getHttpMockDefaultHost();

        $facade = new HttpMockFacade($port, $host, $basePath);

        if ($name === null) {
            static::$staticHttp = $facade;
        } elseif (static::$staticHttp instanceof HttpMockFacadeMap) {
            static::$staticHttp = new HttpMockFacadeMap([$name => $facade] + static::$staticHttp->all());
        } else {
            static::$staticHttp = new HttpMockFacadeMap([$name => $facade]);
        }

        ServerManager::getInstance()->add($facade->server);
    }

    protected static function assertHttpMockSetup()
    {
        if (!static::$staticHttp) {
            static::fail(
                sprintf(
                    'Static HTTP mock facade not present. Did you forget to invoke static::setUpHttpMockBeforeClass()'
                    . ' in %s::setUpBeforeClass()?',
                    get_called_class()
                )
            );
        }
    }

    protected static function tearDownHttpMockAfterClass()
    {
        static::$staticHttp->each(
            static function (HttpMockFacade $facade) {
                $facade->server->stop();
                ServerManager::getInstance()->remove($facade->server);
            }
        );
    }
}
