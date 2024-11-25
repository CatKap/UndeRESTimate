<?php

namespace Aksa;

use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Request;
use Amp\Http\HttpStatus;
use Amp\Http\Server\Router;

class RestResponseHandler implements RequestHandler
{
    private $modelHandler;
    private $logger;

    public function __construct($logger, $modelHandler)
    {
        $this->logger = $logger;
        $this->modelHandler = $modelHandler;
    }

    // Removes trailing whitespaces
    static function json(array $data)
    {

        return json_encode(array_map('Aksa\RestResponseHandler::prettifyValue', $data));
    }

    static function prettifyValue($value)
    {
        var_dump($value);
        if (is_string($value)) {
            return trim($value);
        }
        // Just a little recursion for trimming all sub-strings in return data tree
        if (is_array($value)) {
            return array_map('Aksa\RestResponseHandler::prettifyValue', $value);
        }

        return $value;
    }

    public function handleRequest(Request $request): Response
    {
        $status = HttpStatus::OK;

        $args = $request->getAttribute(Router::class);
        $response = call_user_func_array([$this, (string)$request->getMethod()], [$request, isset($args['id']) ? $args['id'] : null]);
        return new Response(
            status: $response[0],
            headers: ['Content-Type' => 'application/json'],
            body:  $response[1],
        );
    }


    public function setRoutes($router, $uri)
    {
        $router->addRoute('GET', $uri, $this);
        $router->addRoute('GET', $uri . "{id}/", $this);
        $router->addRoute('POST', $uri, $this);

        $router->addRoute('PUT', $uri . "{id}/", $this);
        $router->addRoute('DELETE', $uri . "{id}/", $this);
    }

    // Array [$status, $body]
    public function GET(Request $request, $id = null): array
    {
        if ($id) {
            $got = $this->modelHandler->getById($id);
            if (!$got) {
                $name = $this->modelHandler->tableName;
                return [HttpStatus::NOT_FOUND, "$name with this id $id is not exsisting!"];
            }
            return [HttpStatus::OK, self::json($got)];
        }

        return [HttpStatus::OK, self::json(iterator_to_array($this->modelHandler->get()))];
    }

    public function POST(Request $request): array
    {
        $valid = $this->modelHandler->serialize((string)$request->getBody(), true, true);
        if ($valid) {
            if ($valid->save()) {
                return [HttpStatus::OK, self::json($this->modelHandler->data())];
            }
        }

        return [HttpStatus::INTERNAL_SERVER_ERROR, self::json(["Error" => "Something went wrong!"])];
    }

    public function PATCH(Request $request, $id): array
    {
        $valid = $this->modelHandler->serialize((string)$request->getBody(), true, true);
        if ($valid ? $valid->save($id) : false) {
            return [HttpStatus::OK, self::json($valid->data())];
        }
    }

    public function PUT(Request $request, $id = null): array
    {
        return [HttpStatus::Ok, self::json(["Ok" => ">k^k<"])];
    }

    public function DELETE(Request $request, $id): array
    {
        if ($ret = $this->modelHandler->deleteBy(new FilterStatement("id", FilterStatement::EQ, $id))) {
            if ($ret->getRowCount() == 0) {
                return [HttpStatus::NOT_FOUND, self::json(["Error" => "No such record with id $id. Cannot delete."])];
            }

            return [HttpStatus::OK, self::json(iterator_to_array($ret))];
        }
    }
}
