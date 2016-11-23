<?php
/**
 * Class ProductConfigurationAdapter
 * @copyright   Copyright (c) 2015 Traffics Softwaresysteme für den Tourismus GmbH (http://www.traffics.de/)
 * @version     $Id: $
 * @author      Bogdan Nistor <bogdan.nistorb@yahoo.com>
 */

use Storage\StorageInterface;
use Configuration\Configuration;
/**
 * Adapter for ProductConfiguration
 *
 * @author Rolf Loges
 */
class ProductConfigurationAdapter
{
    /**
     * @var string
     */
    const LICENCE_WITH_DEFAULTS = '0080000010000000';
    
    /**
     * Configuration Storage (Database)
     *
     * @var StorageInterface
     */
    protected $storage;
    
    public function __construct()
    {
        $this->storage = Storage\StorageMongo::getInstance(Yii::app()->params['mongodb']['productConfiguration']);
    }
    
    /**
     * Get User data
     *
     * @param string $licence 
     * @return \Configuration\User
     * @throws Exception
     */
    public function getUser($licence)
    {
        $result = $this->storage->get(array(
            'cfg' => $licence
        ));
        if(is_array($result) && is_a($result[0], 'Configuration\User')) {
            $user = $result[0];
        } else {
            throw new CException('no user found for licence: ' . $licence, 2018);
        }
        return $user;
    }
    
    /**
     * Get User data
     *
     * @param string $licence 
     * @return \Configuration\User
     * @throws Exception
     */
    public function getUserByDatabaseId($id)
    {
        $result = $this->storage->get(array(
            '_id' => new Mongoid($id)
        ));
        if(is_array($result) && is_a($result[0], 'Configuration\User')) {
            $user = $result[0];
        } else {
            throw new CException('no user found for id: ' . $id, 2018);
        }
        return $user;
    }
    
    /**
     * Get all Users data
     *
     * @return \Configuration\User[]
     * @throws Exception
     */
    public function getAllUsers()
    {
        return $this->storage->get();
    }
    
    /**
     * Save User
     *
     * @param \Configuration\User $user 
     */
    public function saveUser($user)
    {
        $this->storage->put($user);
    }
    
    /**
     * Delete User
     *
     * @param \Configuration\User $user 
     */
    public function deleteUser($user)
    {
        $this->storage->deleteDocument($user);
    }
    
    /**
     * Delete User by Database ID
     *
     * @param \Configuration\User $user 
     */
    public function deleteUserByDatabaseId($id)
    {
        $this->storage->delete(array(
            '_id' => new Mongoid($id)
        ));
    }
    
    /**
     * Create new user
     *
     * @param string $licence 
     * @param string $terminal 
     * @param string $name 
     * @return \Configuration\User
     */
    public function createUser($licence, $terminal, $name)
    {
        $user = new \Configuration\User();
        $user->cfg = $licence;
        $user->terminal = $terminal;
        $user->name = $name;
        return $user;
    }
    
    /**
     * Auto create user
     *
     * @param string $licence
     * (cfg)
     */
    public function autoCreateUser($licence, $terminal, $name)
    {
        $userDefault = $this->getUser(self::LICENCE_WITH_DEFAULTS);
        $nameProductDefault = $this->getDefaultProductName($licence);
        $userNew = $this->createUser($licence, $terminal, $name);
        $productDefault = null;
        foreach($userDefault->products as $product) {
            if($product->name == $nameProductDefault && $product->type == \Configuration\Constants::PRODUCT_TYPE_LIVE) {
                $productDefault = clone $product;
            }
        }
        if($productDefault == null) {
            throw new CException('unknown product');
        }
        $userNew->products[] = $productDefault;
        $this->saveUser($userNew);
        return $userNew;
    }
    
    /**
     * get default product from traffics user and create a new one for the client
     * 
     * @param string $license
     * @return \Configuration\Product
     */
    public function getDefaultProductId($licence)
    {
        $userDefault = $this->getUser(self::LICENCE_WITH_DEFAULTS);
        $nameProductDefault = $this->getDefaultProductName($licence);
        $productDefault = null;
        foreach($userDefault->products as $product) {
            if($product->name == $nameProductDefault && $product->type == \Configuration\Constants::PRODUCT_TYPE_LIVE) {
                return $product->id;
            }
        }
        if($productDefault == null) {
            throw new CException('unknown product');
        }
    }
    
    /**
     * Create product
     *
     * @param string $licence 
     * @param string $terminal 
     * @param string $name 
     * @return \Configuration\User
     */
    public function createProduct($name, $type, $description)
    {
        $product = new \Configuration\Product();
        $product->name = $name;
        $product->description = $description;
        $product->type = $type;
        
        return $product;
    }
    
    /**
     * @return array <string, string>
     */
    public static function getProductTypes()
    {
        return array(
            \Configuration\Constants::PRODUCT_TYPE_DRAFT => Yii::t('site', 'Vorlage'),
            \Configuration\Constants::PRODUCT_TYPE_LIVE => Yii::t('site', 'Live'),
            \Configuration\Constants::PRODUCT_TYPE_PREVIEW => Yii::t('site', 'Preview')
        );
    }
    
    /**
     * @return array <string, string>
     */
    public static function getPropertyTypes()
    {
        return array(
            \Configuration\Constants::PROPRTY_TYPE_COLOR => Yii::t('site', 'Farbe'),
            \Configuration\Constants::PROPRTY_TYPE_STRING => Yii::t('site', 'Text'),
            \Configuration\Constants::PROPRTY_TYPE_JSON => Yii::t('site', 'Json')
        );
        // \Configuration\Constants::PROPRTY_TYPE_PREDEFINED => Yii::t('site', 'PREDEFINED')
    }
    
    /**
     * @return array <string, string>
     */
    public static function getPropertyStatus()
    {
        return array(
            \Configuration\Constants::STATUS_EDITABLE => Yii::t('site', 'Editierbar'),
            \Configuration\Constants::STATUS_VISIBLE => Yii::t('site', 'Sichtbar'),
            \Configuration\Constants::STATUS_HIDDEN => Yii::t('site', 'Versteckt')
        );
    }
    
    /**
     * @param \Configuration\Product[] $products 
     * @return array <name,product[]>
     */
    public static function groupProductsPerName($products)
    {
        $result = array();
        if(is_array($products)) {
            foreach($products as $product) {
                if(! isset($result[$product->name])) {
                    $result[$product->name] = array();
                }
                $result[$product->name][] = $product;
            }
        }
        return $result;
    }
    
    /**
     * Resolve a Path
     * 
     * @param array $path attribute => id
     * @param object $start
     * @param int $getParentLevel
     * @throws CException
     * @return array|\Configuration\ArrayElement
     */
    public static function getFromPath($path, $start, $getParentLevel = 0){
        $length = count($path)-abs($getParentLevel);
        if ($length < 0) {
            throw new CException('path level not valid', 18);
        }
        for ($i=0; $i<$length; $i++) {
            if (isset($start->{$path[$i]})) {
                $start = $start->{$path[$i]};
            } elseif ((is_array($start)) && isset($start[$path[$i]])) {
                $start = $start[$path[$i]];
            } else {
                throw new CException('corrupt path', 18);
            }
        }
        return $start;
    }
    
    /**
     * Path to string (for input name)
     * @param array $array
     * @param string $seperator
     * @return string
     */
    public static function path2String($array, $separator = ','){
        return implode($separator, $array);
    }
    
    /**
     * String to Path (from input name)
     * @param string $string
     * @param string $seperator
     * @return array key => value
     */
    public static function string2Path($string, $seperator = ','){
        return explode($seperator, $string);
    }
    
    /**
     * Unlink images from deleted template
     * 
     * @param \Configuration\ElementTemplate $template
     * @param MediaClient $mediaClient
     * @param array $mediaMasterCredentials
     * @param FTP_Request $ftpRequest
     */
    public static function unlinkTemplateImages($template, $mediaClient, $mediaMasterCredentials, $ftpRequest)
    {
        if (!empty($template->properties)) {
            foreach ($template->properties as $property) {
                if (
                    ($property->type === \Configuration\Constants::PROPRTY_TYPE_IMAGE) &&
                    ($property->description !== '__image__')
                ){
                    // delete file from media servers
                    $file = new File();
                    $file->setName($property->description);
                    $mediaClient->unlinkFile($file, $mediaMasterCredentials['username'], $mediaMasterCredentials['password']);
                    
                    // delte file from mediamaster
                    $ftpRequest->deleteFile($property->description);
                }
            }
        }
        if (!empty($template->templates)) {
            foreach ($template->templates as $templateChild) {
                self::unlinkTemplateImages($templateChild, $mediaClient, $mediaMasterCredentials, $ftpRequest);
            }
        }
    }
    
    /**
     * Publish elements found in product
     * 
     * @param \Configuration\Product $product
     * @param string $cfg
     * @return bool
     */
    public function publishProductElements($product, $cfg)
    {
        if (!empty($product->sets)) {
            
            foreach ($product->sets as $set) {
                if (!empty($set->elements)) {
                    
                    foreach ($set->elements as $element) {
                        switch (get_class($element)) {
                            
                            // publish scss
                            case 'Configuration\ElementScss':
                                $this->_publishScss($element, $cfg);
                                break;
                            
                            // publish template
                            case 'Configuration\ElementTemplate':
                                $this->_publishTemplate($element, $cfg);
                                break;
                            
                            // publish json
                            case 'Configuration\ElementJson':
                                $this->_publishJson($element, $cfg);
                                break;
                            
                            // publish ini
                            case 'Configuration\ElementIni':
                                $this->_publishIni($element, $cfg);
                                break;
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Publish Scss
     * 
     * @param \Configuration\ElementScss $elementScss
     * @param string $cfg
     */
    private function _publishScss($elementScss, $cfg)
    {
        // delete evolution cache
        Yii::app()->cacheEvo->delete($cfg.$elementScss->name);
        
        // get _vars.scss
        if ($scssText = $elementScss->getScssText()) {
            $varsScssParams = Yii::app()->params['varsScss'];
            
            if (!empty($varsScssParams)) {
                
                // get _rules.scss
                $request = new REST_Request($varsScssParams['host'], $varsScssParams['port']);
                $scssText .= $request->get($varsScssParams['path']);
                
                // compile scss
                $scss = new scssc();
                $scssText = $scss->compile($scssText);
                
                // ftp request
                $templateFtp = Yii::app()->params['templateFtp'];
                $ftpRequest = new FTP_Request($templateFtp['host'], $templateFtp['username'], $templateFtp['password'], true);
                
                // set working ftp directory
                $directory = (isset($templateFtp['directory']))? $templateFtp['directory']: Yii::app()->user->cfg;
                $ftpRequest->setWorkingDirectory($directory, true);
                
                // upload file name
                $remoteFile = Yii::app()->user->cfg.'.css';
                
                // create temp file and write scss text
                $tempScss = tmpfile();
                fwrite($tempScss, $scssText);
                fseek($tempScss, 0);
                
                $ftpRequest->putFile($tempScss, $remoteFile, FTP_TEXT);
                fclose($tempScss);
                
                // push file to media servers
                $file = new File();
                $file->setName($remoteFile)
                     ->setComment('__css__');
                
                $mediaMasterCredentials = Yii::app()->params['mediaMaster'];
                
                // create media client request to store new image
                $mediaClient = MediaClient::getInstance(Yii::app()->user->cfg);
                $mediaClient->setMediaUrl($mediaMasterCredentials['host']);
                $mediaClient->pushFile($file, $mediaMasterCredentials['username'], $mediaMasterCredentials['password']);
                
                // store media server location for created css file
                $elementScss->url = $file->url;
                
                // close ftp connection
                $ftpRequest->closeConnection();
            }
        }
    }
    
    /**
     * Publish Template
     * 
     * @param \Configuration\ElementTemplate $elementTemplate
     * @param string $cfg
     */
    private function _publishTemplate($elementTemplate, $cfg)
    {
        // delete evolution cache
        Yii::app()->cacheEvo->delete($cfg.$elementTemplate->name);
        
        if ($templateText = $elementTemplate->getTemplateText()) {
            
            // ftp request
            $templateFtp = Yii::app()->params['templateFtp'];
            $ftpRequest = new FTP_Request($templateFtp['host'], $templateFtp['username'], $templateFtp['password'], true);
            
            // set working ftp directory
            $directory = (isset($templateFtp['directory']))? $templateFtp['directory']: Yii::app()->user->cfg;
            $ftpRequest->setWorkingDirectory($directory, true);
            
            // upload file name
            $remoteFile = Yii::app()->user->cfg.'.html';
            
            // create temp file and write html text
            $tempTemplate = tmpfile();
            fwrite($tempTemplate, $templateText);
            fseek($tempTemplate, 0);
            
            $ftpRequest->putFile($tempTemplate, $remoteFile, FTP_TEXT);
            fclose($tempTemplate);
            
            // push file to media servers
            $file = new File();
            $file->setName($remoteFile)
                 ->setComment('__html__');
            
            $mediaMasterCredentials = Yii::app()->params['mediaMaster'];
            
            // create media client request to store new image
            $mediaClient = MediaClient::getInstance(Yii::app()->user->cfg);
            $mediaClient->setMediaUrl($mediaMasterCredentials['host']);
            $mediaClient->pushFile($file, $mediaMasterCredentials['username'], $mediaMasterCredentials['password']);
            
            // store media server location for created css file
            $elementTemplate->url = $file->url;
            
            // close ftp connection
            $ftpRequest->closeConnection();
        }
    }
    
    /**
     * Publish Json
     * 
     * @param \Configuration\ElementJson $elementJson
     * @param string $cfg
     */
    private function _publishJson($elementJson, $cfg)
    {
        // delete evolution cache
        Yii::app()->cacheEvo->delete($cfg.$elementJson->name);
    }
    
    /**
     * Publish Ini
     * 
     * @param \Configuration\ElementIni $elementIni
     * @param string $cfg
     */
    private function _publishIni($elementIni, $cfg)
    {
        // delete evolution cache
        Yii::app()->cacheEvo->delete($cfg.$elementIni->name);
    }
}