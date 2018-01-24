<?php
namespace Rupay\Helper;

use \GuzzleHttp\Psr7\Response as GuzzleResponse;

class Response
{
    /**
     * Simple way to convert GuzzleHttp\Psr7\Response object to HTTP response
     *
     * @param GuzzleResponse $response
     */
    public static function render($response)
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $header => $values) {
            foreach ($values as $value) {
                header("$header: $value");
            }
        }
        echo $response->getBody()->getContents();
    }


    public static function set($body = null, $statusCode = 200, $headers = [])
    {
        return new GuzzleResponse($statusCode, $headers, $body);
    }
}