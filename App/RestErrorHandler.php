<?php

namespace Aksa;

use Amp\Http\Server\ErrorHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Request;

class RestErrorHandler implements ErrorHandler
{
    public function handleError(
        int $status,
        ?string $reason = null,
        ?Request $request = null,
    ): Response {
          $ret = $reason ? $reason : "Something bad happend :(";
          return new Response(
              status: $status,
              headers: ['Content-Type' => 'application/json'],
              body: '{"Error": "' . $ret . '"}'
          );
    }
}
