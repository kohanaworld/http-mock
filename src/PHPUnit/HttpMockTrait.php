<?php

namespace InterNations\Component\HttpMock\PHPUnit;

trait HttpMockTrait
{
    protected static HttpMockFacade|HttpMockFacadeMap $staticHttp;

    protected HttpMockFacade|HttpMockFacadeMap|null $http;

    protected function setUpHttpMock() : void
    {
        static::assertHttpMockSetup();

        $this->http = clone static::$staticHttp;
    }

    protected function tearDownHttpMock() : void
    {
        if (!$this->http) {
            return;
        }

        $http = $this->http;
        $this->http = null;
        $http->each(
            function (HttpMockFacade $facade) {
                static::assertSame(
                    '',
                    (string) $facade->server->getIncrementalErrorOutput(),
                    'HTTP mock server standard error output should be empty'
                );
            }
        );
    }

    public static function getHttpMockDefaultPort() : int
    {
        return 28080;
    }

    public static function getHttpMockDefaultHost() : string
    {
        return 'localhost';
    }

    protected static function setUpHttpMockBeforeClass($port = null, $host = null, $basePath = '', $name = null) : void
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

    protected static function assertHttpMockSetup() : void
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

    protected static function tearDownHttpMockAfterClass() : void
    {
        static::$staticHttp->each(
            static function (HttpMockFacade $facade) {
                $facade->server->stop();
                ServerManager::getInstance()->remove($facade->server);
            }
        );
    }
}
