'use strict'

function MediaData(data) {
    this.catalogId          = (data.cid)? data.cid: null
    this.previewText        = null
    this.pictureUrl         = null
    this.pictureOriginal    = null
    this.pictureType        = null
    this.videoUrl           = null
    this.videoOriginal      = null
    this.mapUrl             = null
    this.mapOriginal        = null
    this.flipCatUrl         = null
    this.hotel              = (data.htc || data.gid)? new Hotel(data): null
    this.tourOperator       = (data.toc)? new TourOperator(data): null
}

function ClimateData() {
    this.airportCode        = null
    this.month              = null
    this.avgAirTemperature  = null
    this.minAirTemperature  = null
    this.maxAirTemperature  = null
    this.rainfall           = null
    this.windForce          = null
    this.sunshineHours      = null
    this.rainyDays          = null
    this.waterTemperature   = null
    this.snowDepth          = null
    this.distance           = null
    this.estimatedFlightTime = null
}

function CatalogData(isAlternative) {
    this.catalogId          = null
    this.catalogName        = null
    this.dateStart          = null
    this.dateEnd            = null
    this.previewText        = null
    this.html               = null
    this.imageList          = []
    this.tourOperator       = null
    this.hotel              = null
    this.alternativeCatalogList     = (isAlternative)? undefined: []
}

function WeatherData(data) {
    this.airportCode        = data.airportCode
    this.dailyWeatherDataList = []
}

function DailyWeatherData(data) {
    this.day                = data.day
    this.minAirTemperature  = data.minTemp
    this.maxAirTemperature  = data.maxTemp
    this.clouds             = data.clouds
    this.text               = data.text
}

function WeatherData(data) {
    this.airportCode        = data.airportCode
    this.dailyWeatherDataList = []
}

function DailyWeatherData(data) {
    this.day                = data.day
    this.minAirTemperature  = data.minTemp
    this.maxAirTemperature  = data.maxTemp
    this.clouds             = data.clouds
    this.text               = data.text
}

function OrderData() {
    this.orderId                = null
    this.tourOperatorCode       = null
    this.hiddenTourOperatorCode = null
    this.hotelCode              = null
    this.productType            = null
    this.duration               = null
    this.departureDateFirst     = null
    this.departureDateLast      = null
    this.onlineDateFirst        = null
    this.onlineDateLast         = null
    this.mediaData              = null
}

function KeywordData(data) {
    this.productType        = data.typ  // P, H or U
    this.tourOperatorCodes  = data.opi  // csv
    this.hotelCodes         = null      // csv
}

function Hotel(data) {
    this.hotelCode          = (data && data.htc)? data.htc: null
    this.giataId            = (data && data.gid)? parseInt(data.gid): null
    this.name               = null
    this.category           = null
    this.locationName       = null
    this.regionName         = null
}

function TourOperator(data) {
    this.code       = (data && data.toc)? data.toc: null
    this.name       = (data && data.ton)? data.ton: null
    this.logo       = null
}

function GiataFactGroup(data, storeLabels) {
    this.code       = (data && data.code)? parseInt(data.code): null
    this.name       = (data && data.name)? data.name: null
    this.label      = null
//    this.matching   = null
    this.facts      = []
    
    // used for Internationalization storage
    this.labels     = (storeLabels)? {}: undefined
}

function GiataFact(data, storeLabels) {
    this.code       = (data && data.code)? parseInt(data.code): null
    this.name       = (data && data.name)? data.name: null
    this.label      = null
    this.attributes = []
    
    // used for Internationalization storage
    this.labels     = (storeLabels)? {}: undefined
}

function GiataAttribute(data, storeLabels) {
    this.code       = (data && data.code)? parseInt(data.code): null
    this.name       = (data && data.name)? data.name: null
    this.value      = (data && data.value)? data.value: null
    this.unit       = (data && data.unit)? data.unit: null
    
    // used for Internationalization storage
    this.labels     = (storeLabels)? {}: undefined
}

function GiataUnit(data, storeLabels) {
    this.name       = (data && data.name)? data.name: null
    this.label      = null
    
    // used for Internationalization storage
    this.labels     = (storeLabels)? {}: undefined
}

function HotelReview(data) {
    this.title                      = (data.title)? data.title: null
    this.travelDate                 = (data.travelDate)? data.travelDate: null
    this.travelDuration             = (data.travelDuration)? data.travelDuration: null
    this.travelReason               = (data.travelReason)? data.travelReason: null
    this.traveledWith               = (data.traveledWith)? data.traveledWith: null
    this.children                   = (data.children)? data.children: 'no'
    this.language                   = (data.language)? data.language: mainConfig.DEFAULT_LANGUAGE
    this.originalLanguage           = (data.originalLanguage)? data.originalLanguage: mainConfig.DEFAULT_LANGUAGE
    
    this.hotelId                    = (data.id)? data.id: null
    this.hotelName                  = (data.hotelName)? data.hotelName: null
    this.hotelUuid                  = (data.hotelUuid)? data.hotelUuid: null
    
    this.firstName                  = (data.firstName)? data.firstName: null
    this.email                      = (data.email)? data.email: null
    this.age                        = (data.age)? data.age: null
    this.homeCountryId              = (data.homeCountryId)? data.homeCountryId: null
    this.homeCityName               = (data.homeCityName)? data.homeCityName: null
    
    this.averageRating              = (data.averageRating)? data.averageRating: null
    this.recommendation             = (data.recommendation)? data.recommendation: null
    
    this.ratingFood                 = (data.ratingFood)? data.ratingFood: null
    this.ratingFoodAtmosphere       = (data.ratingFoodAtmosphere)? data.ratingFoodAtmosphere: null
    this.ratingFoodCleanness        = (data.ratingFoodCleanness)? data.ratingFoodCleanness: null
    this.ratingFoodQuality          = (data.ratingFoodQuality)? data.ratingFoodQuality: null
    this.ratingFoodVariety          = (data.ratingFoodVariety)? data.ratingFoodVariety: null
    
    this.ratingHotel                = (data.ratingHotel)? data.ratingHotel: null
    this.ratingHotelCleanness       = (data.ratingHotelCleanness)? data.ratingHotelCleanness: null
    this.ratingHotelCondition       = (data.ratingHotelCondition)? data.ratingHotelCondition: null
    this.ratingHotelFamily          = (data.ratingHotelFamily)? data.ratingHotelFamily: null
    this.ratingHotelHandicapped     = (data.ratingHotelHandicapped)? data.ratingHotelHandicapped: null
    
    this.ratingLocation             = (data.ratingLocation)? data.ratingLocation: null
    this.ratingLocationActivities   = (data.ratingLocationActivities)? data.ratingLocationActivities: null
    this.ratingLocationBeach        = (data.ratingLocationBeach)? data.ratingLocationBeach: null
    this.ratingLocationFood         = (data.ratingLocationFood)? data.ratingLocationFood: null
    this.ratingLocationShopping     = (data.ratingLocationShopping)? data.ratingLocationShopping: null
    this.ratingLocationSkiing       = (data.ratingLocationSkiing)? data.ratingLocationSkiing: null
    this.ratingLocationTraffic      = (data.ratingLocationTraffic)? data.ratingLocationTraffic: null
    
    this.ratingRoom                 = (data.ratingRoom)? data.ratingRoom: null
    this.ratingRoomBath             = (data.ratingRoomBath)? data.ratingRoomBath: null
    this.ratingRoomCleanness        = (data.ratingRoomCleanness)? data.ratingRoomCleanness: null
    this.ratingRoomInterior         = (data.ratingRoomInterior)? data.ratingRoomInterior: null
    this.ratingRoomSize             = (data.ratingRoomSize)? data.ratingRoomSize: null
    
    this.ratingService              = (data.ratingService)? data.ratingService: null
    this.ratingServiceCheckin       = (data.ratingServiceCheckin)? data.ratingServiceCheckin: null
    this.ratingServiceClaim         = (data.ratingServiceClaim)? data.ratingServiceClaim: null
    this.ratingServiceService       = (data.ratingServiceService)? data.ratingServiceService: null
    this.ratingServiceSkills        = (data.ratingServiceSkills)? data.ratingServiceSkills: null
    
    this.ratingSport                = (data.ratingSport)? data.ratingSport: null
    this.ratingSportBeach           = (data.ratingSportBeach)? data.ratingSportBeach: null
    this.ratingSportChildren        = (data.ratingSportChildren)? data.ratingSportChildren: null
    this.ratingSportOffer           = (data.ratingSportOffer)? data.ratingSportOffer: null
    this.ratingSportPool            = (data.ratingSportPool)? data.ratingSportPool: null
    
    this.text                       = (data.text)? data.text: null
    this.textAdvice                 = (data.textAdvice)? data.textAdvice: null
    this.textFood                   = (data.textFood)? data.textFood: null
    this.textHotel                  = (data.textHotel)? data.textHotel: null
    this.textLocation               = (data.textLocation)? data.textLocation: null
    this.textRoom                   = (data.textRoom)? data.textRoom: null
    this.textService                = (data.textService)? data.textService: null
    this.textSport                  = (data.textSport)? data.textSport: null
}

module.exports.TourOperator 	= TourOperator;
module.exports.MediaData        = MediaData;
module.exports.ClimateData      = ClimateData;
module.exports.CatalogData      = CatalogData;
module.exports.WeatherData      = WeatherData;
module.exports.OrderData        = OrderData;
module.exports.DailyWeatherData = DailyWeatherData;
module.exports.Hotel            = Hotel;
module.exports.OrderData        = OrderData;
module.exports.KeywordData      = KeywordData;
module.exports.GiataFactGroup   = GiataFactGroup;
module.exports.GiataFact        = GiataFact;
module.exports.GiataAttribute   = GiataAttribute;
module.exports.GiataUnit        = GiataUnit;
module.exports.HotelReview      = HotelReview;
