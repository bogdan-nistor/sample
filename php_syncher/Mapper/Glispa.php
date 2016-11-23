<?php

namespace OfferSyncher\Component\Mapper;

use OfferSyncher\Component\Mapper;
use OfferSyncher\Component\Synchronize\NetworkConfig;
use OfferSyncher\Communication\Concurrency;
use OfferSyncher\Communication\Request\Glispa as GlispaClient;
use OfferSyncher\Communication\Response\Glispa\CampaignList;
use OfferSyncher\Component\Mapper\Glispa\OfferGlispa;
use OfferSyncher\Communication\Response\StatusFlag;

/**
 * Class Glispa
 * @package OfferSyncher\Component\Mapper
 */
class Glispa extends Mapper
{
    /**
     * Reference used for \OfferSyncher\Communication\Request\Glispa::findAllAsync response
     *
     * @var \OfferSyncher\Communication\Response\Glispa\CampaignList
     */
    protected $campaignList;

    /**
     * @var NetworkConfig
     */
    protected $networkConfig;

    /**
     * Glispa constructor.
     *
     * @param NetworkConfig $networkConfig
     * @param Concurrency $concurrency
     */
    public function __construct(NetworkConfig $networkConfig, Concurrency $concurrency)
    {
        $this->networkConfig = $networkConfig;
        $this->addPromises($concurrency);
    }

    /**
     * Get mapped data
     *
     * @return array
     */
    public function getData()
    {
        $response = array();

        if ($this->campaignList instanceof CampaignList) {
            /**
             * @var \OfferSyncher\Communication\Response\Glispa\Campaign $campaign
             * @var \OfferSyncher\Communication\Response\Glispa\Creative $creative
             */
            foreach ($this->campaignList as $campaign) {
                foreach ($campaign->getCreativeList() as $creative) {
                    $entityOffer = new OfferGlispa();
                    $entityOffer
                        ->setAffiliateId($this->networkConfig->affiliateId)
                        ->setOfferId($creative->getId())
                        ->setOfferName($campaign->getName())
                        ->setNetworkName($this->networkConfig->name)
                        ->setPayout($campaign->getPayout());

                    // add countries for discover isActive()
                    $entityOffer->setCountries($campaign->getCountries());

                    // generate an entity for each creative - we use creative id inside Tracking url
                    $response[$this->networkConfig->affiliateId.':'.$creative->getId()] = $entityOffer;
                }
            }
        }

        return $response;
    }

    /**
     * @return array
     */
    public function getFlags()
    {
        $response = array();

        if ($this->campaignList instanceof StatusFlag) {
            $statusFlag = $this->campaignList;
            $statusFlag
                ->setApiId($this->networkConfig->affiliateId)
                ->setApiName($this->networkConfig->name);

            $response[$this->networkConfig->affiliateId] = $statusFlag;
        }

        return $response;
    }

    /**
     * Add required promises used in mapping
     *
     * @param Concurrency $concurrency
     */
    protected function addPromises(Concurrency $concurrency)
    {
        // promotions
        $concurrency->addPromise(GlispaClient::getInstance()->getCampaigns(
            $this->networkConfig->apiKey,
            $this->networkConfig->affiliateId
        ), null, $this->campaignList);
    }
}