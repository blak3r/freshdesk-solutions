<?php
/**
 * Implements FreshDesk API methods for Tickets and Surveys in PHP.
 * See README.md
 * Forked from: https://github.com/blak3r/freshdesk-solutions
 * Big thanks to Blake for building the initial API Object Methods
 */

class FreshdeskRest {

    private $domain = '', $username = '', $password = '';
    private $lastHttpStatusCode = 200;
    private $lastHttpResponseText = '';
    private $proxyServer = "";

    /**
     * Constructor
     * @param $domain - yourname.freshdesk.com - but will also accept http://yourname.freshdesk.com/, etc.
     * @param $username String Can be your username or it can be the API Key.
     * @param $password String Optional if you use API Key.
     */
    function __construct($domain, $username, $password = 'X') {

        $strippedDomain = preg_replace('#^https?://#', '', $domain); // removes http:// or https://
        $strippedDomain = preg_replace('#/#', '', $strippedDomain); // get trailing slash

        $this->domain = $strippedDomain;
        $this->password = $password;
        $this->username = $username;
    }
    
        /**
     * @param $urlMinusDomain - should start with /... example /solutions/categories.xml
     * @param $method - should be either GET, POST, PUT (and theoretically DELETE but that's untested).
     * @param string $postData - only specified if $method == POST or PUT
     * @param $debugMode {bool} optional - prints the request and response with headers
     * @return the raw response
     */
    private function restCall($urlMinusDomain, $method, $postData = '',$debugMode=false) {
        $url = "https://{$this->domain}$urlMinusDomain";

		$header[] = "Content-type: application/json";
        $ch = curl_init ($url);

        if( $method == "POST") {
            if( empty($postData) ){
                $header[] = "Content-length: 0"; // <-- seems to be unneccessary to specify this... curl does it automatically
            }
            curl_setopt ($ch, CURLOPT_POST, true);
            curl_setopt ($ch, CURLOPT_POSTFIELDS, $postData);
        }
        else if( $method == "PUT" ) {
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "PUT" );
            curl_setopt ($ch, CURLOPT_POSTFIELDS, $postData);
        }
        else if( $method == "DELETE" ) {
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "DELETE" ); // UNTESTED!
        }
        else {
            curl_setopt ($ch, CURLOPT_POST, false);
        }

        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        if( !empty($this->proxyServer) ) {
            curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888');
        }

        $verbose = ''; // set later...
        if( $debugMode ) {
            // CURLOPT_VERBOSE: TRUE to output verbose information. Writes output to STDERR,
            // or the file specified using CURLOPT_STDERR.
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'rw+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
        }

        $httpResponse = curl_exec ($ch);

        if( $debugMode ) {
            !rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            print $verboseLog;
        }


        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //curl_close($http);
        if( !preg_match( '/2\d\d/', $http_status ) ) {
            //print "ERROR: HTTP Status Code == " . $http_status . " (302 also isn't an error)\n";
        }

        // print "\n\nREST RESPONSE: " . $httpResponse . "\n\n";
        $this->lastHttpResponseText = $httpResponse;

        return $httpResponse;
    }

    /**
     * Returns the HTTP status code of the last call, useful for error checking.
     * @return int
     */
    public function getLastHttpStatus() {
        return $this->lastHttpStatusCode;
    }

    /**
     * Returns the HTTP Response Text of the last curl call, useful for error checking.
     * @return int
     */
    public function getLastHttpResponseText() {
        return $this->lastHttpResponseText;
    }

    /**
     * Will force cURL requests to use the proxy.  Can be useful to debug requests and responses
     * using Fiddler2 or WireShark.
     * curl_setopt($ch, CURLOPT_PROXY, '127.0.0.1:8888'); // Use with Fiddler to debug
     * @param $proxyServer - example for fiddler2 default: '127.0.0.1:8888'
     */
    public function setProxyServer($proxyServer)
    {
        $this->proxyServer = $proxyServer;
    }

    
    /**
     * Returns "all" the tickets... open tickets for the API credentials used
     * @return bool FALSE if it doesn't exist, the id otherwise.
     */
    public function getAllTickets() {
        $xml = $this->restCall("/helpdesk/tickets.json", "GET");

        if( empty($json) ) {
            return FALSE;
        }

		$json = json_decode($json);
        return $json;
    }


    /**
     * Returns the article ID, this method takes in the IDs for category and folder rather then the names.
     * @return bool FALSE if it doesn't exist, the id otherwise.
     */
    public function getSingleTicket($ticketId) {
        $json = $this->restCall("/helpdesk/tickets/$ticketId.json", "GET");
        
        if( empty($json) ) {
            return FALSE;
        }

		$json = json_decode($json);
        return $json;
    }


    /**
     * Returns the Survey for a given ticket, this method takes in the IDs for a ticket
     * @param $ticketId 
     * @return bool FALSE if it doesn't exist, the object otherwise.
     */
    public function getTicketSurvey($ticketId) {
        $json = $this->restCall("/helpdesk/tickets/$ticketId/surveys.json", "GET");
        
        if( empty($json) ) {
            return FALSE;
        }

		$json = json_decode($json);
        return $json;
    }
    
}