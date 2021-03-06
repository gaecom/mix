<?php

namespace Mix\Etcd\Client;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use Mix\Micro\Service\Exception\NotFoundException;

/**
 * Class Client
 * @package Mix\Etcd\Client
 */
class Client extends \Etcd\Client
{

    /**
     * User
     * @var string
     */
    protected $user = '';

    /**
     * Password
     * @var string
     */
    protected $password = '';

    /**
     * 重写修改 handler
     * Client constructor.
     * @param string $server
     * @param string $version
     */
    public function __construct($server = '127.0.0.1:2379', $version = 'v3alpha')
    {
        $this->server = rtrim($server);
        if (strpos($this->server, 'http') !== 0) {
            $this->server = 'http://' . $this->server;
        }
        $this->version    = trim($version);
        $baseUri          = sprintf('%s/%s/', $this->server, $this->version);
        $handler          = new \GuzzleHttp\Handler\StreamHandler();
        $stack            = \GuzzleHttp\HandlerStack::create($handler);
        $this->httpClient = new HttpClient(
            [
                'handler'  => $stack,
                'base_uri' => $baseUri,
                'timeout'  => 30,
            ]
        );
        $this->setPretty(true);
    }

    /**
     * Auth
     * @param $user
     * @param $password
     */
    public function auth($user, $password)
    {
        $this->token    = null;
        $this->user     = $user;
        $this->password = $password;
        $token          = $this->authenticate($user, $password); // token default ttl 10m
        if (is_string($token)) {
            $this->setToken($token);
        }
    }

    /**
     * Refresh auth
     */
    public function refreshAuth()
    {
        $this->auth($this->user, $this->password);
    }

    /**
     * Get Token
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Watch prefix
     * @param string $prefix
     * @param \Closure $func
     * @return Watcher
     */
    public function watchKeysWithPrefix(string $prefix, \Closure $func)
    {
        return new Watcher($this->server, $this, $prefix, $func);
    }

    /**
     * 重写该方法，统一登录与非登录的返回数据格式
     * Gets the key or a range of keys
     *
     * @param string $key
     * @param array $options
     *         string range_end
     *         int    limit
     *         int    revision
     *         int    sort_order
     *         int    sort_target
     *         bool   serializable
     *         bool   keys_only
     *         bool   count_only
     *         int64  min_mod_revision
     *         int64  max_mod_revision
     *         int64  min_create_revision
     *         int64  max_create_revision
     * @return array|\GuzzleHttp\Exception\BadResponseException
     */
    public function get($key, array $options = [])
    {
        $params  = [
            'key' => $key,
        ];
        $params  = $this->encode($params);
        $options = $this->encode($options);
        $body    = $this->request(self::URI_RANGE, $params, $options);
        $body    = $this->decodeBodyForFields(
            $body,
            'kvs',
            ['key', 'value',]
        );

        if (isset($body['kvs'])) {
            return $this->convertFields($body['kvs']);
        }

        return [];
    }

    /**
     * 重写该方法，让 lease 失效时修改为抛出异常
     *
     * keeps the lease alive by streaming keep alive requests
     * from the client\nto the server and streaming keep alive responses
     * from the server to the client.
     *
     * @param int64 $id ID is the lease ID for the lease to keep alive.
     * @return array|\GuzzleHttp\Exception\BadResponseException
     */
    public function keepAlive($id)
    {
        $params = [
            'ID' => $id,
        ];

        $body = $this->request(self::URI_KEEPALIVE, $params);

        if (!isset($body['result'])) {
            return $body;
        }

        if (!isset($body['result']['ID']) || !isset($body['result']['TTL'])) {
            throw new NotFoundException('Invalid lease id');
        }

        // response "result" field, etcd bug?
        return [
            'ID'  => $body['result']['ID'],
            'TTL' => $body['result']['TTL'],
        ];
    }

    /**
     * 重写该方法，处理 token 过期重试
     *
     * 发送HTTP请求
     *
     * @param string $uri
     * @param array $params
     * @param array $options
     * @return array|\Etcd\BadResponseException
     */
    protected function request($uri, array $params = [], array $options = [])
    {
        try {
            $result = parent::request($uri, $params, $options); // TODO: Change the autogenerated stub
        } catch (ClientException $ex) {
            if (strpos($ex->getMessage(), 'invalid auth token') !== false) {
                $this->refreshAuth();
                return $this->request($uri, $params, $options);
            }
            throw $ex;
        }
        return $result;
    }

}
