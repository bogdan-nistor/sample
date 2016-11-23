<?php

namespace OfferSyncher\Communication;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use OfferSyncher\Communication\Response\StatusFlag;
use OfferSyncher\Helper\Constants;
use OfferSyncher\Helper\LogDispatcher;
use OfferSyncher\Helper\SyncLogger;

/**
 * Class Request
 * @package OfferSyncher\Communication
 */
abstract class Request
{
    abstract public static function getInstance();

    /**
     * Http client used for requests
     *
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Send request
     *
     * @param string $httpMethod
     * @param string $url
     * @param array $options
     *
     * @return array | null
     */
    protected function send($httpMethod, $url, $options)
    {
        try {
            $response = $this->client->request($httpMethod, $url, $options);
            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException $e) {

            // 4xx, 5xx - error
            $statusFlag = new StatusFlag();

            if ($e->getResponse() !== null) {
                $statusFlag
                    ->setHttpStatusCode($e->getResponse()->getStatusCode())
                    ->setReason(Constants::FLAG_REASON_SERVER_ERROR);
            } else {
                $statusFlag
                    ->setHttpStatusCode(408)
                    ->setReason(Constants::FLAG_REASON_TIMEOUT);
            }

            $statusFlag
                ->setHttpMethod($httpMethod)
                ->setErrorMessage($e->getMessage())
                ->setUrl($url)
                ->setGroup($url)
                ->setOptions($options);
            LogDispatcher::serverErrors(__CLASS__, $statusFlag);

        } catch (\Exception $e) {
            SyncLogger::getInstance()->error($e->getMessage(), array(
                'url' => $url,
                'httpMethod' => $httpMethod,
                'options' => $options
            ));
        }

        return null;
    }

    /**
     * Send request async
     *
     * @param string $httpMethod
     * @param string $url
     * @param array $options
     * @param callable $callback
     *
     * @return \Guzzlehttp\Promise\Promise | null
     */
    protected function sendAsync($httpMethod, $url, $options, callable $callback)
    {
        try {
            $promise = $this->client->requestAsync($httpMethod, $url, $options);
            $promise->then(
                $callback,
                function (RequestException $e) use ($httpMethod, $url, $options, &$promise) {

                    // 4xx, 5xx - error
                    $statusFlag = new StatusFlag();

                    if ($e->getResponse() !== null) {
                        $statusFlag
                            ->setHttpStatusCode($e->getResponse()->getStatusCode())
                            ->setReason(Constants::FLAG_REASON_SERVER_ERROR);
                    } else {
                        $statusFlag
                            ->setHttpStatusCode(408)
                            ->setReason(Constants::FLAG_REASON_TIMEOUT);
                    }

                    $statusFlag
                        ->setHttpMethod($httpMethod)
                        ->setErrorMessage($e->getMessage())
                        ->setUrl($url)
                        ->setGroup($url)
                        ->setOptions($options);
                    LogDispatcher::serverErrors(__CLASS__, $statusFlag);

                    $promise->stackResult = $statusFlag;
                }
            );
            return $promise;

        } catch (\Exception $e) {
            SyncLogger::getInstance()->error('Could not create Promise object: ' . $e->getMessage(), array(
                'url' => $url,
                'httpMethod' => $httpMethod,
                'options' => $options
            ));
        }

        return null;
    }

    /**
     * Constructor
     */
    protected function __construct()
    {
        $options = array(
            'timeout' => 0.6,
            'connect_timeout' => 0.2,
            'headers'        => array(
                'Accept-Encoding' => 'gzip'
            )
        );
        $this->client = new Client($options);
    }
}
