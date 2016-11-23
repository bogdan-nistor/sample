<?php

namespace OfferSyncher\Communication;

use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;

/**
 * Class Concurrency
 * @package OfferSyncher\Communication
 */
class Concurrency
{
    /**
     * Container for stacking request promises
     *
     * @var array
     */
    private $stack = array();

    /**
     * Container for references
     *
     * @var array
     */
    private $references = array();

    /**
     * Add promise to stack
     *
     * @param \Guzzlehttp\Promise\Promise $promise
     * @param string | integer $promiseKey
     * @param &$referenceVar
     *
     * @return Concurrency
     */
    public function addPromise($promise, $promiseKey = null, &$referenceVar = null)
    {
        if (null !== $promise) {

            if ($promiseKey !== null) {
                $this->stack[$promiseKey] = $promise;
                $this->references[$promiseKey] = &$referenceVar;

            } else {
                $this->stack[] = $promise;
                $this->references[] = &$referenceVar;
            }
        }
        return $this;
    }

    /**
     * Make asyncronious requests using stack promises
     *
     * @return array
     */
    public function resolveStack()
    {
        $response = array();
        if (!empty($this->stack)) {

            // make async requests using stack
            try {
                Promise\settle($this->stack)->wait();
            } catch (RequestException $e) {
                // NOTE: we treat the RequestException inside \OfferSyncher\Communication\Request
            }

            foreach ($this->stack as $key=>$promise) {
                if (property_exists($promise, 'stackResult')) {
                    $response[$key] = $promise->stackResult;

                } else {
                    $response[$key] = null;
                }

                // set reference value
                $this->references[$key] = $response[$key];
            }
        }

        return $response;
    }
}