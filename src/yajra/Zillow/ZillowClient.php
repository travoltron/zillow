<?php namespace yajra\Zillow;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use yajra\Zillow\ZillowException;

/**
 * Client
 *
 * @author Arjay Angeles <aqangeles@gmail.com>
 */
class ZillowClient
{
    /**
     * @var zillow api endpoint
     */
    const END_POINT = 'http://www.zillow.com/webservice/';
    /**
     * @var object GuzzleClient
     */
    protected $client;

    /**
     * @var string ZWSID
     */
    protected $ZWSID;

    /**
     * @var int
     */
    protected $errorCode = 0;

    /**
     * @var string
     */
    protected $errorMessage = null;

    /**
     * @var array
     */
    protected $response;

    /**
     * @var array
     */
    protected $results;

    /**
     * @var array
     */
    protected $photos = [];

    /**
     * @var array - valid callbacks
     */
    public static $validCallbacks = [
        'getZestimate',
        'getSearchResults',
        'getChart',
        'getComps',
        'getDeepComps',
        'getDeepSearchResults',
        'getUpdatedPropertyDetails',
        'getDemographics',
        'getRegionChildren',
        'getRegionChart',
        'getRateSummary',
        'getMonthlyPayments',
        'calculateMonthlyPaymentsAdvanced',
        'calculateAffordability',
        'calculateRefinance',
        'calculateAdjustableMortgage',
        'calculateMortgageTerms',
        'calculateDiscountPoints',
        'calculateBiWeeklyPayment',
        'calculateNoCostVsTraditional',
        'calculateTaxSavings',
        'calculateFixedVsAdjustableRate',
        'calculateInterstOnlyVsTraditional',
        'calculateHELOC',
    ];

    /**
     * Initiate the class
     * @param string $ZWSID
     * @return object
     */
    public function __construct($ZWSID)
    {
        $this->setZWSID($ZWSID);
    }

    /**
     * Set client
     * return GuzzleClient
     */
    public function setClient(GuzzleClientInterface $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * get GuzzleClient, create if it's null
     * return GuzzleClient
     */
    public function getClient()
    {
        if (!$this->client) {
            $this->client = new GuzzleClient(array('defaults' => array('allow_redirects' => false, 'cookies' => true)));
        }

        return $this->client;
    }

    /**
     * @return string ZWSID
     */
    public function setZWSID($id)
    {
        return ($this->ZWSID = $id);
    }

    /**
     * @return string ZWSID
     */
    public function getZWSID()
    {
        return $this->ZWSID;
    }

    /**
     * Check if the last request was successful
     * @return bool
     */
    public function isSuccessful()
    {
        return (bool) ((int) $this->errorCode === 0);
    }

    /**
     * return the status code from the last call
     * @return int
     */
    public function getStatusCode()
    {
        return $this->errorCode;
    }

    /**
     * return the status message from the last call
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->errorMessage;
    }

    /**
     * return the actual response array from the last call
     * @return array
     */
    public function getResponse()
    {
        return isset($this->response['response']) ? $this->response['response'] : $this->response;
    }

    /**
     * return the results array from the GetSearchResults call
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * magic method to invoke the correct API call
     * if the passed name is within the valid callbacks
     * @param string $name
     * @param array $arguments
     * @return array
     */
    public function __call($name, $arguments)
    {
        if (in_array($name, self::$validCallbacks)) {
            return $this->doRequest($name, $arguments);
        }
    }

    /**
     * set the statis code and message of the api call
     * @param int $code
     * @param string $message
     * @return void
     */
    protected function setStatus($code, $message)
    {
        $this->errorCode    = $code;
        $this->errorMessage = $message;
    }

    /**
     * Perform the actual request to the zillow api endpoint
     * @param string $name
     * @param array $params
     * @return array
     */
    protected function doRequest($call, array $params)
    {
        // Validate
        if (!$this->getZWSID()) {
            throw new ZillowException("You must submit the ZWSID");
        }

        // Run the call
        $response = $this->getClient()->get(self::END_POINT.ucfirst($call).'.htm', ['query' => ['zws-id' => $this->getZWSID()] + $params]);

        $this->response = $response->xml();

        // Parse response
        return $this->parseResponse($this->response);
    }

    /**
     * Parse the reponse into a formatted array
     * also set the status code and status message
     * @param object $response
     * @return array
     */
    protected function parseResponse($response)
    {
        // Init
        $this->response = json_decode(json_encode($response), true);

        if (!$this->response['message']) {
            $this->setStatus(999, 'XML WAS NOT FOUND');
            return;
        }

        // Check if we have an error
        $this->setStatus($this->response['message']['code'], $this->response['message']['text']);

        // If request was succesful then parse the result
        if ($this->isSuccessful()) {
            if ($this->response['response'] && isset($this->response['response']['results']) && count($this->response['response']['results'])) {
                foreach ($this->response['response']['results'] as $result) {
                    if(!isset($result['zpid'])) {
                        $this->setStatus(400, 'This is not a valid address');
                        return;
                    }
                    $this->results[$result['zpid']] = $result;
                }
            }
        }

        return isset($this->response['response']) ? $this->response['response'] : $this->response;
    }
}
