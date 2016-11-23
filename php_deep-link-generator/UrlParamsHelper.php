<?php
/**
 * UrlParamsHelper
 *
 * @copyright   Copyright (c) 2013 Traffics Softwaresysteme für den Tourismus GmbH (http://www.traffics.de/)
 * @version     $Id:$
 * @author      Bogdan Nistor <bogdan.nistorb@yahoo.com>
 */
class UrlParamsHelper
{
    /**
     * all rules (paramName => regex)
     */
    public static $searchObjParamsRegex = array(
    	// used in link generator self::wrap(), please test if changes are made on regex
        'language'                      => '/^lang\-([a-zA-Z]{2})$/',
        'refresh'                       => '/^refresh$/',
        'authReference'                 => '/^ref-([a-zA-Z0-9\[\]\_\$]+)$/',
        'productSubType'                => '/([a-zA-Z\,]+)$/',
        'priceInterval'                 => '/^price([0-9]+)-([0-9]+)|PRICE([0-9]+)\-([0-9]+)$/',
        'theme'                         => '/^thm-([a-zA-Z0-9]+)$/',
        'license'                       => '/^cfg-([0-9]+)$/',
        'numberAdults'                  => '/^(\d+)(erw|Erw)$/',
        'numberChildren'                => '/^(\d+)(k|K)$/',
        'dateInterval'                  => '/^([0-9]{6})-([0-9]{6})$/',
        'daysInterval'                  => '/^([0-9]{1,3})-([0-9]{1,3})$/',
        'travelDuration'                => '/^(\d+)(t|T)|(\d+\-\d+t|\d+\-\d+T)|(\d+w|\d+W)$/',
        'categoryType'                  => '/^(\d+)(stars|Stars|star|Star)$/',
        'specialKeywords'               => '/^ekw-([a-zA-Z0-9\,]+)|EKW-([a-zA-Z0-9\,]+)$/',
        'minBoardType'                  => '/^min-([a-zA-Z]+)/',
        'inclusiveList'                 => '/^inclusive-([a-zA-Z\,]+)/',
        'excludedOperators'             => '/^opx-([a-zA-Z0-9\,]+)$/',
        'facts'                         => '/^facts-([a-zA-Z0-9\,]{1,})/',
        'quantity'                      => '/^qty-([0-9\,]+)$/',
        'hotelChain'                    => '/^hki-([0-9\,]+)$/',
        'regionCodesForFeed'            => '/^rgc-([0-9\,]+)$/',
        'hotelCodesForFeed'             => '/^gid-([0-9\,]+)$/',
        'locations'                     => '/^oid-([0-9\,]+)$/',
        'market'                        => '/^market-([a-zA-Z\,]+)/',
        // tourOperators, boardTypes, roomTypes, departureAirports, keywords
        'lists'                         => '/^([a-zA-Z0-9\,\_\=\s\+]+)$/',
        'uuid'                          => '/^uuid-([a-zA-Z0-9-]+)$/',
        'projectCode'                   => '/^projectCode-([a-zA-Z0-9-]+)$/',
    );
    
    /**
     * Container for unwrapped data
     * @var array $_params
     */
    private $_params = array();

    /**
     * static data
     * @var Connector_Response_GetStaticData $_staticData
     */
    private $_staticData;

    /**
     * Static data helper class
     * @var StaticDataHelper $_staticDataHelper
     */
    private $_staticDataHelper;

    /**
     * Configuration: merge between default config and client config
     * @var stdClass $_config
     */
    private $_config;

    /**
     * travelDuration, keywords, keywordGroups, airportCountryAliases
     * @var array
     */
    private $_defaultFilterData;
    
    /**
     * singleton purpouse for storage market
     * @var array
     */
    static private $_market;

    /**
     * Constructor
     * @param Connector_Response_GetStaticData|null $staticData
     * @param array|null $defaultFilterData
     * @param stdClass|null $config
     */
    public function __construct($staticData, $defaultFilterData, $config)
    {
        $this->_staticData = $staticData;
        $this->_defaultFilterData = $defaultFilterData;
        $this->_config = $config;

        $this->_staticDataHelper = new StaticDataHelper($this->_staticData, $this->_defaultFilterData);
    }

    /**
     * get deep link market country list
     * 
     * @return array
     */
    static public function getDeepLinkMarketCountryList()
    {
        if (is_null(self::$_market)) {
            if ($deepLink = Yii::app()->request->getParam('searchObjParams')) {
                foreach (explode('/', $deepLink) as $param) {
                    if (preg_match(UrlParamsHelper::$searchObjParamsRegex['market'], $param, $matches)) {
                        if ((isset($matches[1])) && ($matches[1])){
                            return self::$_market = explode(',',$matches[1]);
                        }
                    }
                }
            }
        } else {
            return self::$_market;
        }
        
        // fallback must return array
        return self::$_market = array();
    }

    /**
     * prüft die URL auf den Referenzparameter
     * 
     * @return string|null
     */    
    static public function getReferenceFromDeepLink() {
        if ($urlString = Yii::app()->request->getParam('searchObjParams')) {
            $params = explode('/', $urlString);

            foreach ($params as $param){
                if (preg_match(self::$searchObjParamsRegex['authReference'], $param, $matches)){
                    if (isset($matches[1])){
                        return array('authReference' => $matches[1]);
                    }
                }
            }
        }
        return null;
    }    
    
    /**
     * prüft die URL auf den Sprachparameter
     * 
     * @return string|null
     */
    static public function getLanguageFromDeepLink() {
        if ($urlString = Yii::app()->request->getParam('searchObjParams')) {
            $params = explode('/', $urlString);

            foreach ($params as $param){
                if (preg_match(self::$searchObjParamsRegex['language'], $param, $matches)){
                    if (isset($matches[1])){
                        $lang = strtolower($matches[1]);
                        //$this->_add('lang', strtolower($lang));
                        return $lang;
                    }
                }
            }
        }
        return null;        
    }
    
    /**
     * Wrap params and create url
     */
    public function wrap($route, $params)
    {
    	// lists types
    	$lists = array(
    		'tourOperators',
    		'boardTypes',
    		'roomTypes',
    		'departureAirports',
    		'keywords'
    	);
    	
    	// params from IBE
        if (isset($params['SearchForm'])){
            
            // convert autocomplete IBE data to deep link data
            if (isset($params['SearchForm']['searchString'])){
                if (isset($params['autocompleteData'])){
                    
                    // store autocomplete selection data
                    Yii::app()->controller->setSession('AUTOCOMPETE_FILTER_'.'searchString', array(
                        'searchString' => $params['SearchForm']['searchString'],
                        'urlGeneratorData' => http_build_query(array('autocompleteData' => $params['autocompleteData']))
                    ));
                    
                    $params += $params['autocompleteData'];
                    unset($params['autocompleteData']);
                }
            }
            
            // convert connector search form data to deep link data
            $this->_formDataToDeepLinkData($params);
            if (isset($params['productTypeUrl'])){
                $params['productType'] = $params['productTypeUrl'];
            }
        }
        
        if ($route){
            // check for searchObjParams
            $searchObjParamsList = array();
            foreach ($params as $key=>$value){
            	// associate with lists if possible
            	if (in_array($key, $lists)){
            		$key = 'lists';
            	}
                if (isset(self::$searchObjParamsRegex[$key])){
                    unset($params[$key]);
                    $subject = preg_replace('/[\\\$\/\^]|\|.*/', '$1', self::$searchObjParamsRegex[$key]);
                    
                    if (is_array($value)){
                        // in case of interval: /1-560/
                        if (
                        	(strpos($key, 'Interval') !== false) &&
                        	(count($value) == 2) &&
                        	(isset($value['from'])) &&
                        	(isset($value['to']))
            			){
                            $searchObjParamsList[] = preg_replace('/\(.*\)-\(.*\)/', $value['from'].'-'.$value['to'], $subject);
                        } else {
                            // in case of multiple values: /2k/3k/...
                            foreach ($value as $val){
                                $searchObjParamsList[] = preg_replace('/\(.*\)\(|\(.*\)/', $val, $subject);
                            }
                        }
                    } else {
                    	// in case of travelDuration
                    	if (strpos($key, 'Duration') !== false){
                    		$searchObjParamsList[] = $value;
                    	} else {
                    		// in case single string value: /DZ,EZ/
                    		$searchObjParamsList[] = preg_replace('/\(.*\)\(|\(.*\)/', $value, $subject);
                    	}
                    }
                }
            }
            
            if (!empty($searchObjParamsList)){
            	$params['searchObjParams'] = implode('/', $searchObjParamsList);
            }
            
            $url = Yii::app()->createUrl($route, $params, '&');
            echo json_encode(array('generatedUrl' => $url));
        }
    }

    /**
     * Unwrap params from url string
     *
     * @param string $urlString
     * @return array
     */
    public function unwrap($urlString, $checkType = null)
    {
        $params = explode('/', $urlString);

        foreach ($params as $param){
            
            // reset session Search
            if (preg_match(self::$searchObjParamsRegex['refresh'], $param, $matches)){
                $this->_add('refresh', 'refresh');
                
                // if refresh in URL then unset the selected super region from regionList page
                unset(Yii::app()->session["STATICSEARCHKEY"."SUPERREGIONID"]);
                // delete autocomplete data on 'refresh' deeplink
                Yii::app()->controller->deleteFromSession('AUTOCOMPETE_FILTER_'.'searchStringFilterOptions');
                Yii::app()->controller->deleteFromSession('AUTOCOMPETE_FILTER_'.'searchString');
                
                continue;
            }
            
            // template
            if ($checkType === 'license'){
                // lincense
                if (preg_match(self::$searchObjParamsRegex['license'], $param, $matches)){
                    if (isset($matches[1])){
                        $this->_add('license', $matches[1]);
                    }
                }
                break;
            }

            // authReference
            if ($checkType === 'authReference'){
                if (preg_match(self::$searchObjParamsRegex['authReference'], $param, $matches)){
                    if (isset($matches[1])){
                        $this->_add('authReference', $matches[1]);
                    }
                }
                break;
            }
            
            // product subtype
            if ($checkType === 'productSubType'){
                if (preg_match(self::$searchObjParamsRegex['productSubType'], $param, $matches)){
                    $this->_add('productSubType', $param);
                }
                break;
            }

            // price
            if (preg_match(self::$searchObjParamsRegex['priceInterval'], $param, $matches)){
                if (isset($matches[1]) && isset($matches[2])){
                    $this->_add('minPricePerPerson', $matches[1]);
                    $this->_add('maxPricePerPerson', $matches[2]);
                }
                continue;
            }

            // numberAdults
            if (preg_match(self::$searchObjParamsRegex['numberAdults'], $param, $matches)){
                if (isset($matches[1])){
                    $this->_add('numberAdults', (int)$matches[1]);
                }
                continue;
            }

            // numberChildren
            if (preg_match(self::$searchObjParamsRegex['numberChildren'], $param, $matches)){
                if (isset($matches[1])){
                    $this->_add('numberChildren', (int)$matches[1], true);
                }
                continue;
            }

            // date
            if (preg_match(self::$searchObjParamsRegex['dateInterval'], $param, $matches)){
                if ((isset($matches[1])) && (isset($matches[2]))){
                    $dateFrom = preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{2})/', '${1}.${2}.20${3}', $matches[1]);
                    $dateTo = preg_replace('/^([0-9]{2})([0-9]{2})([0-9]{2})/', '${1}.${2}.20${3}', $matches[2]);
                    $this->_add('dateFrom', $dateFrom);
                    $this->_add('dateTo', $dateTo);
                }
                continue;
            }
            
            // day interval
            if(preg_match(self::$searchObjParamsRegex['daysInterval'], $param, $matches)){
                if((isset($matches[1])) && (isset($matches[2]))){
                    $dateFrom = date('d.m.Y', strtotime('+'.$matches[1].' days'));
                    $dateTo = date('d.m.Y', strtotime('+'.$matches[2].' days'));
                    $this->_add('dateFrom', $dateFrom);
                    $this->_add('dateTo', $dateTo);
                }
            }
            
            // travelDuration
            if (preg_match(self::$searchObjParamsRegex['travelDuration'], $param, $matches)){
                if (isset($matches[0])){
                    $this->_add('travelDuration', strtolower($matches[0]));
                }
                continue;
            }

            //stars
            if (preg_match(self::$searchObjParamsRegex['categoryType'], $param, $matches)){
                if (isset($matches[1])){
                    $this->_add('categoryType', (int)$matches[1]);
                }
                continue;
            }

            // special keywords list
            if ((preg_match(self::$searchObjParamsRegex['specialKeywords'], $param, $matches))){
                if (isset($matches[1])){
                    $codeList = explode(',',$matches[1]);
                    foreach ($codeList as $code){
                        $this->_add('specialKeywords', $code, true);
                    }
                }
                continue;
            }

            // minBoardType
            if(preg_match(self::$searchObjParamsRegex['minBoardType'], $param, $matches)){
                if (isset($matches[0])){
                    $this->_add('boardTypes', $matches[0], true);
                }
                continue;
            }

            // inclusiveList
            if (preg_match(self::$searchObjParamsRegex['inclusiveList'], $param, $matches)){
                if ((isset($matches[1])) && ($matches[1])){
                    $codeList = explode(',',$matches[1]);
                    foreach($codeList as $code){
                        if($code === Connector_Interface::SEARCH_OPTION_INCLUSIVE_RENTAL_CAR){
                            $this->_add('withCar', $code);
                        }else{
                            $this->_add('inclusiveList', $code, true);
                        }
                    }
                }
                continue;
            }
            
            // market
            if (is_null(self::$_market)) {
                if (preg_match(self::$searchObjParamsRegex['market'], $param, $matches)){
                    if ((isset($matches[1])) && ($matches[1])){
                        self::$_market = explode(',',$matches[1]);
                    }
                }
            }
            if (!empty(self::$_market)) {
                $countryAirports = $this->_staticDataHelper->getAirportsByCountryCodes(self::$_market);
                if (!empty($countryAirports)) {
                    
                    // add market to session
                    Yii::app()->session->add('market', $param);
                    
                    foreach($countryAirports as $airportCode => $airportName){
                        $this->_add('departureAirports', $airportCode, true);
                    }
                }
                continue;
            }

            // excluded tour operator list opx
            if (preg_match(self::$searchObjParamsRegex['excludedOperators'], $param, $matches)){
                if (isset($matches[1])){
                    $codeList = explode(',',$matches[1]);
                    foreach ($codeList as $code){
                        $this->_add('excludedOperators', $code, true);
                    }
                }
                continue;
            }

            // facts
            if (preg_match(self::$searchObjParamsRegex['facts'], $param, $matches)){
                if (isset($matches[1])){
                    $codeList = explode(',',$matches[1]);
                    foreach ($codeList as $code){
                        $this->_add('facts', $code, true);
                    }
                }
                continue;
            }

            // quantiy/Anzahl
            if (preg_match(self::$searchObjParamsRegex['quantity'], $param, $matches)) {
                if (isset($matches[1])) {
                    $codeList = explode(',', $matches[1]);
                    foreach ($codeList as $code) {
                        $this->_add('quantity', $code);
                        
                    }
                }
            }
            
            // Hotelkette / Hotelchain
            if (preg_match(self::$searchObjParamsRegex['hotelChain'], $param, $matches)) {
                if (isset($matches[1])) {
                    $codeList = explode(',', $matches[1]);
                    foreach ($codeList as $code) {
                        $this->_add('hotelChain', $code, true);
                        
                    }
                }
            }
            
            // Regionscodes (NUR Oberregionen)
            if (preg_match(self::$searchObjParamsRegex['regionCodesForFeed'], $param, $matches)) {
                if (isset($matches[1])) {
                    $codeList = explode(',', $matches[1]);
                    foreach ($codeList as $code) {
                        $this->_add('regionCodesForFeed', $code, true);
                        
                    }
                }
            }
            
            // GiataIDs
            if (preg_match(self::$searchObjParamsRegex['hotelCodesForFeed'], $param, $matches)) {
                if (isset($matches[1])) {
                    $codeList = explode(',', $matches[1]);
                    foreach ($codeList as $code) {
                        $this->_add('hotelCodesForFeed', $code, true);
                        
                    }
                }
            }            
            
            // locations
            if (preg_match(self::$searchObjParamsRegex['locations'], $param, $matches)) {
                if (isset($matches[1])) {
                    $codeList = explode(',', $matches[1]);
                    foreach ($codeList as $code) {
                        $this->_add('locations', $code, true);
                    }
                }
                continue;
            }
            
            // lists
            if ((preg_match(self::$searchObjParamsRegex['lists'], $param)) && ($codeList = explode(',',$param))){

                $iniTourOperators = explode(',' ,$this->_config->TOUR_OPERATORS->allowedTourOperators);
                foreach ($codeList as $code){
                    // correction for plus character (in url is space)
                    $code = str_replace(' ', '+', $code);

                    // tourOperators
                    if (in_array($code, $iniTourOperators)){

                        // check imploded tour operator list and use that for tour operator code
                        $tourOperatorCodeList = null;
                        $tourOperatorList = $this->_staticDataHelper->getPopulate('tourOperatorList');
                        foreach ($tourOperatorList as $codeList=>$tourOperatorName){
                            if (($codeList === $code) || (strpos($codeList, $code) !== false)){
                                $tourOperatorCodeList = $codeList;
                                break;
                            }
                        }

                        if ($tourOperatorCodeList){

                            // check for duplicate tour operator codes
                            if ((!isset($this->_params['tourOperators'])) || (!in_array($tourOperatorCodeList, $this->_params['tourOperators']))){
                                $this->_add('tourOperators', $tourOperatorCodeList, true);
                            }
                        }
                    } else {

                        // two character code lists: boardTypes(OV,AI,...) | roomTypes(EZ,DZ,...)
                        if (preg_match('/^([a-zA-Z0-9\+]{2})$/', $code, $matches)){
                            if (isset($matches[1])){
                                $this->_add('twoCharCode', $matches[1], true);
                            }
                            continue;
                        }

                        // three character code lists: departureAirports(HAM,CGN,...) | keywords(ipl,sub,...)
                        if (preg_match('/^([a-zA-Z0-9]{3})$/', $code, $matches)){
                            if (isset($matches[1])){
                                $this->_add('threeCharCode', $matches[1], true);
                            }
                            continue;
                        }
                    }
                }
                continue;
            }
            
            // ITO: User-ID
            if (preg_match(self::$searchObjParamsRegex['uuid'], $param, $matches)){
                if (isset($matches[1])){
                    $this->_add('uuid', $matches[1]);
                }
                continue;
            }
            
            // ITO: Project-ID
            if (preg_match(self::$searchObjParamsRegex['projectCode'], $param, $matches)){
                if (isset($matches[1])){
                    $this->_add('projectCode', $matches[1]);
                }
                continue;
            }            
        }
        // needed in connector adapter
        $this->_params['isDeepLink'] = true;;

        return $this->_params;
    }

    /**
     * Validate
     * @param string $value
     */
    private function _validate($type, $value)
    {
        switch ($type){
            case 'numberAdults':
            case 'withCar':
            case 'tourOperators':
            case 'travelDuration':
            case 'license':
            case 'theme':
            case 'specialKeywords':
            case 'authReference':
            case 'excludedOperators':
            case 'refresh':
            case 'facts':
            case 'locations':
            case 'lang':
            case 'uuid':
            case 'projectCode':
            case 'departureAirports':
                return $type;
                break;
            case 'productSubType':
                $subTypeValues = IbeConfig::getConstantPatternValues('Connector_Constant', 'SEARCH_PRODUCT_SUBTYPE');
                foreach ($subTypeValues as $subTypeConstName=>$subTypeValue){
                    if ($value === $subTypeValue){
                        return $type;
                    }
                }
                break;
            case 'dateFrom':
                if (strtotime($value)){
                    return $type;
                }
                break;
            case 'dateTo':
                if (($dateTo = strtotime($value)) && isset($this->_params['dateFrom'])){
                    if (
                        ($dateTo > strtotime($this->_params['dateFrom'])) &&
                        (strtotime($this->_params['dateFrom']) >= strtotime(date("Y-m-d")))
                    ){
                        return $type;
                    } else {
                        unset($this->_params['dateFrom']);
                    }
                }
                break;
            case 'minPricePerPerson':
                if ((int)$value > 0){
                    return $type;
                }
                break;
            case 'maxPricePerPerson':
                if (((int)$value > 0) &&
                    isset($this->_params['minPricePerPerson']) &&
                    ((int)$this->_params['minPricePerPerson'] < (int)$value)
                ){
                    return $type;
                }
                break;

            case 'numberChildren':
                if ((int)$value < 18){
                    return $type;
                }
                break;
            case 'categoryType':
                if(((int)$value < 6) && ((int)$value > 0)){
                    return $type;
                }
                break;
            case 'twoCharCode':
                $staticDataBoardTypes = $this->_staticDataHelper->getPopulate('boardTypeList');
                if (array_key_exists($value, $staticDataBoardTypes)){
                    return 'boardTypes';
                }

                $staticDataRoomTypes = $this->_staticDataHelper->getPopulate('roomTypeList');
                if (array_key_exists($value, $staticDataRoomTypes)){
                    return 'roomTypes';
                }

                break;

            case 'threeCharCode':
                $staticDataDepartureAirports = $this->_staticDataHelper->getPopulate('departureAirportList');
                if (array_key_exists($value, $staticDataDepartureAirports)){
                    return 'departureAirports';
                }

                $staticDataKeywords = $this->_staticDataHelper->getPopulate('keywords');
                if (array_key_exists($value, $staticDataKeywords)){
                    return 'keywords';
                }
                $staticDataKeywords = $this->_staticDataHelper->getPopulate('keywordGroups');
                if (array_key_exists($value, $staticDataKeywords)){
                    return 'keywords';
                }

                break;
            case 'boardTypes':
                $staticDataBoardTypes = $this->_staticDataHelper->getPopulate('boardTypeList');
                if (array_key_exists($value, $staticDataBoardTypes)){
                    return $type;
                }

                break;
            case 'inclusiveList':
                $staticInclusiveList = $this->_staticDataHelper->getPopulate('inclusiveList');
                if (array_key_exists($value, $staticInclusiveList)){
                    return 'inclusiveList';
                }

                break;
            case 'hotelChain':
                return $type;
            case 'regionCodesForFeed':
                return $type;
            case 'hotelCodesForFeed':
                return $type;
            case 'quantity':
                if ((int)$value > 0){
                    return $type;
                }
                break;
        }

        return false;
    }

    /**
     * Add detected param to container
     *
     * @param string $type
     * @param string | integer $value
     * @param bool $multiple
     */
    private function _add($type, $value, $multiple = false)
    {
        if ($validType = $this->_validate($type, $value)){
            if ($multiple){
                $this->_params[$validType][] = $value;
            } else {
                $this->_params[$validType] = $value;
            }
        }
    }
    
    /**
     * convert IBE form data to be recognised by deep link structure
     * 
     * @param array &$params
     */
    private function _formDataToDeepLinkData(&$params)
    {
        /* --- Top search params --- */
        
        if (isset($params['SearchForm']['productType'])){
            $params['productType'] = UrlManager::getProductTypeUrlValue($params['SearchForm']['productType']);
        }
        
        // departureAirports
        if (isset($params['SearchForm']['departureAirports'])){
            $airports = array_filter($params['SearchForm']['departureAirports']);
            if (!empty($airports)){
                $params['departureAirports'] = implode(',', $airports);
            }
        }
        
        // dateFrom - dateTo
        if ((isset($params['SearchForm']['dateFrom'])) && (isset($params['SearchForm']['dateTo']))){
            $params['dateInterval']['from'] = date('dmy', strtotime($params['SearchForm']['dateFrom']));
            $params['dateInterval']['to'] = date('dmy', strtotime($params['SearchForm']['dateTo']));
        }
        
        // travelDuration
        if ((isset($params['SearchForm']['travelDuration'])) && (!empty($params['SearchForm']['travelDuration']))){
            $params['travelDuration'] = $params['SearchForm']['travelDuration'];
        }
        
        // numberChildren
        if (isset($params['SearchForm']['numberChildren'])){
            $children = array_filter($params['SearchForm']['numberChildren']);
            if ($children){
                $params['numberChildren'] = $children;
            }
        }
        
        // numberAdults
        if (isset($params['SearchForm']['numberAdults'])){
            $params['numberAdults'] = $params['SearchForm']['numberAdults'];
        }
        
        // inclusiveList
        if (isset($params['SearchForm']['inclusiveList'])){
            $inclusives = array_filter($params['SearchForm']['inclusiveList']);
            if (!empty($inclusives)){
                $params['inclusiveList'] = implode(',', $inclusives);
            }
        }
        
        /* --- Extra filter params --- */
        
        // tourOperators
        if (isset($params['SearchForm']['tourOperators'])){
            $tourOperators = array_filter($params['SearchForm']['tourOperators']);
            if (!empty($tourOperators)){
                $params['tourOperators'] = implode(',', $tourOperators);
            }
        }
        
        // minPricePerPerson and maxPricePerPerson
        if (isset($params['SearchForm']['minPricePerPerson']) && (isset($params['SearchForm']['minPricePerPerson']))){
            $params['priceInterval']['from'] = $params['SearchForm']['minPricePerPerson'];
            $params['priceInterval']['to'] = $params['SearchForm']['maxPricePerPerson'];
        }
        
        // categoryType
        if (isset($params['SearchForm']['categoryType'])){
            $params['categoryType'] = $params['SearchForm']['categoryType'];
        }
        
        // roomTypes
        if (isset($params['SearchForm']['roomTypes'])){
            $roomTypes = array_filter($params['SearchForm']['roomTypes']);
            if (!empty($roomTypes)){
                $params['roomTypes'] = implode(',', $roomTypes);
            }
        }
        
        // boardTypes
        if (isset($params['SearchForm']['boardTypes'])){
            $boardTypes = array_filter($params['SearchForm']['boardTypes']);
            if (!empty($boardTypes)){
                $params['boardTypes'] = implode(',', $boardTypes);
            }
        }
        
        // hotelChain
        if (isset($params['SearchForm']['hotelChain'])){
            $hotelChain = array_filter($params['SearchForm']['hotelChain']);
            if (!empty($hotelChain)){
                $params['hotelChain'] = implode(',', $hotelChain);
            }
        }
        
        // facts
        if (isset($params['SearchForm']['facts'])){
            $facts = array_filter($params['SearchForm']['facts']);
            if (!empty($facts)){
                $params['facts'] = implode(',', $facts);
            }
        }
        
        // keywords
        if (isset($params['SearchForm']['keywords'])){
            $keywords = array_filter($params['SearchForm']['keywords']);
            if (!empty($keywords)){
                $params['keywords'] = implode(',', $keywords);
            }
        }
        
        // daysInterval
        if ((isset($params['SearchForm']['daysInterval']['to'])) && (isset($params['SearchForm']['daysInterval']['from']))){
            $params['daysInterval']['to'] = $params['SearchForm']['daysInterval']['to'];
            $params['daysInterval']['from'] = $params['SearchForm']['daysInterval']['from'];
        }
        
        unset($params['SearchForm']);
    }
}
?>