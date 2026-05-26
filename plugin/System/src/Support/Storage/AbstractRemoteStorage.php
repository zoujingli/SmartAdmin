<?php

declare(strict_types=1);
/**
 * This file is part of SmartAdmin.
 *
 * @contact Anyon <zoujingli@qq.com>
 * @license https://github.com/zoujingli/SmartAdmin/blob/master/LICENSE
 * @document https://zoujingli.github.io/SmartAdmin
 */

namespace System\Support\Storage;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Library\Exception\ErrorResponseException;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractRemoteStorage extends AbstractStorage
{
    protected Client $client;

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $common
     */
    public function __construct(array $config, array $common = [])
    {
        parent::__construct($config, $common);
        $this->client = new Client([
            'http_errors' => false,
            'timeout' => 30,
        ]);
    }

    /**
     * 发起远程 HTTP 请求并统一异常。
     *
     * @param array<string, mixed> $options
     */
    protected function request(string $method, string $url, array $options = []): ResponseInterface
    {
        try {
            return $this->client->request($method, $url, $options);
        } catch (GuzzleException $exception) {
            throw new ErrorResponseException($exception->getMessage(), null, 500, $exception);
        }
    }

    /**
     * 校验响应状态码是否在允许范围内。
     *
     * @param array<int, int> $allowedStatusCodes
     */
    protected function ensureSuccessful(ResponseInterface $response, array $allowedStatusCodes = [200, 201, 204]): ResponseInterface
    {
        if (in_array($response->getStatusCode(), $allowedStatusCodes, true)) {
            return $response;
        }

        throw new ErrorResponseException(sprintf('远程存储请求失败，状态码 %d', $response->getStatusCode()));
    }

    /**
     * @return array<string, string>
     */
    protected function headersToMap(ResponseInterface $response): array
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string)$name)] = implode(',', array_map('strval', $values));
        }

        return $headers;
    }
}
