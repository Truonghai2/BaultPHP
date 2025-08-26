<?php

namespace Core\Exceptions;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class HttpResponseException extends RuntimeException
{
    protected ResponseInterface $response;

    public function __construct(ResponseInterface $response)
    {
        parent::__construct('The request resulted in a response object that should be sent immediately.');
        $this->response = $response;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
