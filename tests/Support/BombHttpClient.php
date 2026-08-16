<?php

declare(strict_types=1);

namespace ViewMend\Laravel\Tests\Support;

use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class BombHttpClient implements ClientInterface
{
    public int $requests = 0;

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        ++$this->requests;

        throw new LogicException('A network request was attempted.');
    }
}
