<?php

namespace OfferSyncher\Communication\Request;

use OfferSyncher\Communication\Request;
use OfferSyncher\Communication\Response\Glispa\Campaign;
use OfferSyncher\Communication\Response\Glispa\CampaignList;
use OfferSyncher\Communication\Response\Glispa\Creative;
use OfferSyncher\Communication\Response\Glispa\CreativeList;
use OfferSyncher\Communication\Response\StatusFlag;
use OfferSyncher\Helper\Constants;
use OfferSyncher\Helper\LogDispatcher;

/**
 * Class Glispa
 * NOTE: this API has XML response
 *
 * @package OfferSyncher\Communication\Request
 */
class Glispa extends Request
{
    /**
     * @var Glispa
     */
    private static $instance;

    /**
     * Affiliate API service url
     *
     * @var string $serviceUrl
     */
    protected $serviceUrl = "https://www.glispainteractive.com/API/campaigns.php";

    /**
     * Get campaigns
     * @see https://trello.com/c/1zZRzFhr
     *
     * @param string $apiKey
     * @param string $affiliateId
     *
     * @return \Guzzlehttp\Promise\Promise | null
     */
    public function getCampaigns($apiKey, $affiliateId)
    {
        $url = $this->serviceUrl;
        $options = array(
            'token'    => $apiKey,
            'cid'      => $affiliateId
        );

        /**
         * @param \Psr\Http\Message\ResponseInterface $response
         * @return bool
         */
        $callback = function ($response) use ($url, $options, &$promise)
        {
            $body = $response->getBody()->getContents();
            $content = @simplexml_load_string($body);

            $statusFlag = new StatusFlag();
            $statusFlag
                ->setHttpStatusCode($response->getStatusCode())
                ->setHttpMethod('GET')
                ->setUrl($url)
                ->setGroup($url)
                ->setOptions($options);

            if ($content !== false) {
                $campaigns = $content->xpath('campaign');

                if (count($campaigns) > 0) {
                    // 200 OK
                    $campaignList = new CampaignList();

                    foreach ($campaigns as $campaignKey=>$campaignData) {
                        $campaign = new Campaign();

                        $campaign
                            ->setId((int)$campaignData['glispaID'])
                            ->setName((string)$campaignData['name'])
                            ->setCategory((string)$campaignData->category)
                            ->setCountries(explode(' ', (string)$campaignData->countries))
                            ->setTarget((string)$campaignData->target)
                            ->setPayout((string)$campaignData->payout);

                        // map creatives - important for identifying offers (we are using creative id inside Tracking link)
                        $creativeList = new CreativeList();
                        foreach ($campaignData->creatives->xpath('creative') as $creativeKey=>$creativeData) {
                            $creative = new Creative();
                            $creative
                                ->setId((int)$creativeData['id'])
                                ->setDescription((string)$creativeData->description)
                                ->setLink((string)$creativeData->link);
                            $creativeList->offsetSet($creativeKey, $creative);
                        }
                        $campaign->setCreativeList($creativeList);

                        $campaignList->offsetSet($campaignKey, $campaign);
                    }
                    /**
                     * NOTE: stackResult property is part of the
                     *       OfferSyncher\Communication\Concurrency::resolveStack() implementation
                     */
                    $promise->stackResult = $campaignList;
                    return true;

                } else {
                    // 200 OK - empty data
                    $statusFlag
                        ->setReason(Constants::FLAG_REASON_EMPTY)
                        ->setErrorMessage($body);
                    LogDispatcher::jsonEmptyData(__CLASS__, $statusFlag);

                    $promise->stackResult = $statusFlag;
                    return true;
                }

            } else {
                if (strpos($body, 'AUTH_TOKEN') !== false) {
                    // 200 OK - error auth token
                    $statusFlag
                        ->setReason(Constants::FLAG_REASON_AUTH_FAIL)
                        ->setErrorMessage($body);
                    LogDispatcher::jsonInvalidToken(__CLASS__, $statusFlag);

                } else {
                    // 200 OK - error
                    $statusFlag
                        ->setReason(Constants::FLAG_REASON_ERROR)
                        ->setErrorMessage($body);
                    LogDispatcher::jsonErrors(__CLASS__, $statusFlag);
                }
                $promise->stackResult = $statusFlag;
                return true;
            }
        };

        $promise = $this->sendAsync('GET', $url, array('query' => $options), $callback);

        return $promise;
    }

    /**
     * @return Glispa
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}