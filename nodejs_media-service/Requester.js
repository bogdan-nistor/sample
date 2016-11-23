// Requester
var request = require('request')

module.exports = function(mapper){
    
    /**
     * public params
     */
    this.protocol   = mainConfig.traffics.protocol
    this.host       = mainConfig.traffics.host
    this.resource   = mainConfig.traffics.resource
    this.service    = null
    this.method     = 'GET'
    this.url        = null
    this.headers    = {}
    
    /**
     * Get url
     */
    this.getUrl = function(){
        if (this.url === null) {
            this.url = this.protocol + this.host + '/' + this.resource
                        + ((this.service !== null)? '/' + this.service: '')
        }
        return this.url
    }
    
    this.doRequest = function(req, res, callback) {
        var requestOptions = {
            url: this.getUrl(),
            method: this.method,
            qs: mapper(req.query),
            headers: this.headers
        }
        
        // log request
        logger.info({
            request: new Date().toString(),
            message: requestOptions
        })
        
        console.log(requestOptions)
    
        request(requestOptions, function(err, resObj, body) {
            if (err) {
                
                // connection problems
                logger.error(err)
                callback('')
            } else {
                
                // 200 OK
                logger.info({
                    message: body.substring(0,200)
                })
                callback(body.replace(/\n|\r/g, ''))
            }
        })
    }
}

module.exports.getDummyImage = function () {
    return mainConfig.traffics.protocol + mainConfig.traffics.host + '/MediaImages/dummy.gif'
}

module.exports.getTourOperatorLogo = function(tourOperatorCode){
    return mainConfig.traffics.protocol + mainConfig.traffics.host + '/vadata/logo/gif/h18/' + tourOperatorCode.toLowerCase() + '.gif'
}
