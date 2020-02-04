<?php

namespace TelegramApiServer\Controllers;

use Amp\Promise;

class ApiController extends AbstractApiController
{

    private ?string $session = '';

    /**
     * Получаем параметры из uri
     *
     * @param array $path
     *
     */
    protected function resolvePath(array $path): void
    {
        $this->session = $path['session'] ?? null;
        $this->api = explode('.', $path['method'] ?? '');
    }

    /**
     * @return mixed|Promise
     * @throws \Exception
     */
    protected function callApi()
    {
        $madelineProto = $this->client->getInstance($this->session);
        return $this->callApiCommon($madelineProto);
    }

}