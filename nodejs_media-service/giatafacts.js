var express          = require('express'),
    router           = express.Router(),
    expat            = require('node-expat'),
    entities         = require('html-entities'),
    Requester        = require('../lib/Requester'),
    RequestMapper    = require('../lib/RequestMapper'),
    models           = require('../models/media'),
    htmlEntities     = new entities.AllHtmlEntities()

router.get('/', function(req, res) {
    
    var mediaRequester  = new Requester(RequestMapper),
        giataRequester  = new Requester(RequestMapper),
        mediaResponse   = {factResource: null},
        giataResponse   = {mediaResult: []},
        mediaParser     = new expat.Parser('UTF-8'),
        giataParser     = new expat.Parser('UTF-8'),
        language        = req.query.language || mainConfig.DEFAULT_LANGUAGE
    
     // init parsers
    initMediaParser(mediaParser, mediaResponse, language)
    initGiataParser(giataParser, giataResponse, language)
    
    // configure mediaRequester
    mediaRequester.service = 'giatafacts'
    
    // add param sc
    req.query.sc = 'hotel'
    // add param show
    req.query.show = 'fact'
    // remove language param from request query
    delete req.query.language

    // add param forceCdata
    req.query.forceCdata = 1
    
    /*
     * first request to media
     */
    mediaRequester.doRequest(req, res, function(xmlResponse){
        
        // parse media response
        mediaParser.write(xmlResponse)
        
        if (mediaResponse.factResource !== null) {
            
            /*
             * second request to giata using mediaResponse.factResource
             */
            giataRequester.url = mediaResponse.factResource
            req.query = {
                userId: req.query.userId,
                password: req.query.password
            }
            giataRequester.doRequest(req, res, function(xmlGiataResponse){
                
                // parse media response
                giataParser.write(xmlGiataResponse)
                
                // return JSON
                res.json(giataResponse)
            });
        } else {
            // return JSON
            res.json(giataResponse)
        }
    });
});

/**
 * Initialise media parser
 * 
 * @param Object mediaParser
 * @param Object mediaResponse
 * @param string language
 */
function initMediaParser(mediaParser, mediaResponse, language)
{
    mediaParser.on('startElement', function (name, attrs) {
        this.currentXmlToken = name
    });
    mediaParser.on('text', function (text) {
        // decode html entities
        text = htmlEntities.decode(text)

        if (
            this.currentXmlToken === 'Factsheet'
            && text.replace(/\s/g, '') !== ''
        ){
            mediaResponse.factResource = (mediaResponse.factResource !== null)? mediaResponse.factResource+text: text
        }
    });
    mediaParser.on('error', function (error) {
        console.error(error)
    });
}

/**
 * Initialise giata parser
 * 
 * @param Object giataParser
 * @param Object giataResponse
 * @param string language
 */
function initGiataParser(giataParser, giataResponse, language)
{
    giataParser.on('startElement', function (name, attrs) {
        this.currentXmlToken = name
        
        if (name == "factgroup") {
            this.factGroup = new models.GiataFactGroup(attrs)
            translateLabel(this.factGroup, language)
            giataResponse.mediaResult.push(this.factGroup)
        } else if (name == "fact") {
            this.giataFact = new models.GiataFact(attrs)
            translateLabel(this.giataFact, language)
            this.factGroup.facts.push(this.giataFact)
        } else if (name == "attribute") {
            this.giataAtribute = new models.GiataAttribute(attrs)
            translateLabel(this.giataAtribute, language)
            this.giataFact.attributes.push(this.giataAtribute)
        }
    });
    giataParser.on('error', function (error) {
        console.error(error)
    });
}

/**
 * Translate label using giata definitions
 * 
 * @param Object object
 * @param string language
 */
function translateLabel(object, language) {
    if (giataDefinitions) {
        var corresponding = null
        
        if (object.constructor.name === 'GiataFactGroup') {
            
            corresponding = giataDefinitions.factGroups.filter(function(factGroup) {
                return (factGroup.code === object.code && factGroup.name === object.name)
            }).shift()
        } else if (object.constructor.name === 'GiataFact') {
            
            searchFact:
                for (k in giataDefinitions.factGroups) {
                    var facts = giataDefinitions.factGroups[k].facts
                    for (kk in facts) {
                        if (facts[kk].code === object.code && facts[kk].name === object.name) {
                            
                            corresponding = facts[kk]
                            break searchFact;
                        }
                    }
                }
        } else if (object.constructor.name === 'GiataAttribute') {
            
            corresponding = giataDefinitions.attributes.filter(function(attribute) {
                return (attribute.code === object.code && attribute.name === object.name)
            }).shift()
            
            // translate units
            if (object.unit !== null) {
                correspondingUnit = giataDefinitions.units.filter(function(unit) {
                    return (unit.name === object.unit)
                }).shift()
                if (correspondingUnit) {
                    object.unit = (correspondingUnit.labels[language])? correspondingUnit.labels[language]: correspondingUnit.labels[mainConfig.DEFAULT_LANGUAGE]
                }
            }
        }
        
        // set label
        if (corresponding !== null) {
            object.label = (corresponding.labels[language])? corresponding.labels[language]: corresponding.labels[mainConfig.DEFAULT_LANGUAGE]
        }
    } else {
        logger.error({
            error500: new Date().toString(),
            message: 'Global values for giata facts definitions are not initialized!'
        });
    }
}

module.exports = router;