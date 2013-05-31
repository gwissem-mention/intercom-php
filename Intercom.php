<?php
/**
 * Intercom is a customer relationship management and messaging tool for web app owners
 * 
 * This library provides connectivity with the Intercom API (https://api.intercom.io)
 * 
 * Basic usage:
 * 
 * 1. Configure Intercom with your access credentials
 * <code>
 * <?php
 * $intercom = new Intercom('dummy-app-id', 'dummy-api-key');
 * ?>
 * </code>
 * 
 * 2. Make requests to the API
 * <code>
 * <?php
 * $intercom = new Intercom('dummy-app-id', 'dummy-api-key');
 * $users = $intercom->getAllUsers();
 * var_dump($users);
 * ?>
 * </code>
 * 
 * @author    Bruno Pedro <bruno.pedro@cloudwork.com>
 * @copyright Copyright 2013 Nubera eBusiness S.L. All rights reserved.
 * @link      http://www.nubera.com/
 * @license   http://opensource.org/licenses/MIT
 **/


/**
 * Intercom.io API 
 */
class Intercom
{
    /**
     * The Intercom API endpoint
     */
    protected $apiEndpoint = 'https://api.intercom.io/v1/';

    /**
     * The Intercom application ID
     */
    protected $appId = null;

    /**
     * The Intercom API key
     */
    protected $apiKey = null;

    /**
     * Last HTTP error obtained from curl_errno() and curl_error()
     */
    protected $lastError = null;

    /**
     * Whether we are in debug mode. This is set by the constructor
     */
    protected $debug = false;
    
    /**
     * Whether to send API call immediately or to use a cron job to send them
     */
    protected $delayed = false;
    
    /**
     * If $delayed is true, write api calls data to this file 
     * in order to be executed later.
     */
    protected $delayedCmdFile = '/tmp/intercom_delayed_commands';
    
    /**
     * This option est used to allow mass reseting of users different values
     * during tests or when changing the behaviour of some field.
     * If set to true, increments the fields on intercom by given values.
     * If set to false, then sets the fields on intercom to given values.
     * Default to true.
     * @var boolean
     */
    protected $incrementMode = true;

    /**
     * The constructor
     *
     * @param  string $appId  The Intercom application ID
     * @param  string $apiKey The Intercom API key
     * @param  string $debug  Optional debug flag
     * @return void
     **/
    public function __construct($appId, $apiKey, $debug = false, $delayed = false)
    {
        $this->appId = $appId;
        $this->apiKey = $apiKey;
        $this->debug = $debug;
        $this->delayed = $delayed;
    }

    /**
     * Check if a given value is an e-mail address.
     *
     * @param  string $value
     * @return boolean
     **/
    private function isEmail($value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Make an HTTP call using curl.
     * 
     * @param  string $url       The URL to call
     * @param  string $method    The HTTP method to use, by default GET
     * @param  string $post_data The data to send on an HTTP POST (optional)
     * @return object
     **/
    protected function httpCall($url, $method = 'GET', $post_data = null, $forceRealtime = false)
    {
        if(true === $forceRealtime || false === $this->delayed) {
        
            $headers = array('Content-Type: application/json');
    
            $ch = curl_init($url);
    
            if ($this->debug) {
                curl_setopt($ch, CURLOPT_VERBOSE, true);
            }
    
            if ($method == 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                curl_setopt($ch, CURLOPT_POST, true);
            } elseif ($method == 'PUT') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                $headers[] = 'Content-Length: ' . strlen($post_data);
            } elseif ($method != 'GET') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 4096);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC); 
            curl_setopt($ch, CURLOPT_USERPWD, $this->appId . ':' . $this->apiKey);
    
            $response = curl_exec($ch);
    
            // Set HTTP error, if any
            $this->lastError = array('code' => curl_errno($ch), 'message' => curl_error($ch));
    
            return json_decode($response);
        } else {
            $line = $url . '|||' . $method . '|||' . $post_data;
            try{
                $fp = fopen($this->delayedCmdFile, "a");
                if($fp) {
                    fputs($fp, $line . "\n");
                    fclose($fp);
                }
                return true;
            } catch (Exception $e){
                return false;
            }
        }
    }

    /**
     * Get all users from your Intercom account.
     * 
     * @param  integer $page    The results page number
     * @param  integer $perPage The number of results to return on each page
     * @return object
     **/
    public function getAllUsers($page = 1, $perPage = null)
    {
        $path = 'users/?page=' . $page;

        if (!empty($perPage)) {
            $path .= '&per_page=' . $perPage;
        }

        return $this->httpCall($this->apiEndpoint . $path, 'GET', null, true);
    }

    /**
     * Get a specific user from your Intercom account.
     * 
     * @param  string $id The ID of the user to retrieve
     * @return object
     **/
    public function getUser($id)
    {
        $path = 'users/';
        if ($this->isEmail($id)) {
            $path .= '?email=';
        } else {
            $path .= '?user_id=';
        }
        $path .= urlencode($id);
        return $this->httpCall($this->apiEndpoint . $path, 'GET', null, true);
    }
    
    /**
     * Get the message thread of a specific user from your Intercom account.
     * 
     * @param  string $id The ID of the user to retrieve thread for
     * @return object
     **/
    public function getThread($id)
    {
        $path = 'users/message_threads';
        if ($this->isEmail($id)) {
            $path .= '?email=';
        } else {
            $path .= '?user_id=';
        }
        $path .= urlencode($id);
        return $this->httpCall($this->apiEndpoint . $path, 'GET', null, true);
    }

    /**
     * Create a user on your Intercom account.
     * 
     * @param  string $id                The ID of the user to be created
     * @param  string $email             The user's email address (optional)
     * @param  string $name              The user's name (optional)
     * @param  array  $customData        Any custom data to be aggregate to the user's record (optional)
     * @param  long   $createdAt         UNIX timestamp describing the date and time when the user was created (optional)
     * @param  string $lastSeenIp        The last IP address where the user was last seen (optional)
     * @param  string $lastSeenUserAgent The last user agent of the user's browser (optional)
     * @param  array  $companies         The list of companies to which our user belongs (optional)
     * @param  long   $lastRequestAt     UNIX timestamp of the user's last request (optional)
     * @param  string $method            HTTP method, to be used by updateUser()
     * @return object
     **/
    public function createUser($id = null,
                               $email = null,
                               $name = null,
                               $customData = array(),
                               $createdAt = null,
                               $lastSeenIp = null,
                               $lastSeenUserAgent = null,
                               $companies = array(),
                               $lastRequestAt = null,
                               $method = 'POST')
    {
        $data = array();

        if(empty($id) && empty($email)) {
            return false;
        }
        
        if(!empty($id)) {
            $data['user_id'] = $id;
        }
        
        if (!empty($email)) {
            $data['email'] = $email;
        }

        if (!empty($name)) {
            $data['name'] = $name;
        }

        if (!empty($createdAt)) {
            $data['created_at'] = $createdAt;
        }

        if (!empty($lastSeenIp)) {
            $data['last_seen_ip'] = $lastSeenIp;
        }

        if (!empty($lastSeenUserAgent)) {
            $data['last_seen_user_agent'] = $lastSeenUserAgent;
        }

        if (!empty($lastRequestAt)) {
            $data['last_request_at'] = $lastRequestAt;
        }

        if (!empty($customData)) {
            $data['custom_data'] = $customData;
        }
        
        if (!empty($companies)) {
            $data['companies'] = $companies;
        }

        $path = 'users';
        return $this->httpCall($this->apiEndpoint . $path, $method, json_encode($data));
    }

    /**
     * Update an existing user on your Intercom account.
     * 
     * @param  string $id                The ID of the user to be updated
     * @param  string $email             The user's email address (optional)
     * @param  string $name              The user's name (optional)
     * @param  array  $customData        Any custom data to be aggregate to the user's record (optional)
     * @param  long   $createdAt         UNIX timestamp describing the date and time when the user was created (optional)
     * @param  string $lastSeenIp        The last IP address where the user was last seen (optional)
     * @param  string $lastSeenUserAgent The last user agent of the user's browser (optional)
     * @param  long   $lastRequestAt     UNIX timestamp of the user's last request (optional)
     * @return object
     **/
    public function updateUser($id = null,
                               $email = null,
                               $name = null,
                               $customData = array(),
                               $createdAt = null,
                               $lastSeenIp = null,
                               $lastSeenUserAgent = null,
                               $companies = array(),
                               $lastRequestAt = null)
    {
        return $this->createUser($id, $email, $name, $customData, $createdAt, $lastSeenIp, $lastSeenUserAgent, $companies, $lastRequestAt, 'PUT');
    }

    /**
     * Delete an existing user from your Intercom account
     * 
     * @param  string $id The ID of the user to be deleted
     * @return object
     **/
    public function deleteUser($id)
    {
        $path = 'users/';
        if ($this->isEmail($id)) {
            $path .= '?email=';
        } else {
            $path .= '?user_id=';
        }
        $path .= urlencode($id);
        return $this->httpCall($this->apiEndpoint . $path, 'DELETE');
    }

    /**
     * Create an impression associated with a user on your Intercom account
     * 
     * @param  string $userId     The ID of the user
     * @param  string $email      The email of the user (optional)
     * @param  string $userIp     The IP address of the user (optional)
     * @param  string $userAgent  The user agent of the user (optional)
     * @param  string $currentUrl The URL the user is visiting (optional)
     * @return object
     **/
    public function createImpression($userId, $email = null, $userIp = null, $userAgent = null, $currentUrl = null)
    {
        $data = array();

        $data['user_id'] = $userId;

        if (!empty($email)) {
            $data['email'] = $email;
        }

        if (!empty($userIp)) {
            $data['user_ip'] = $userIp;
        }

        if (!empty($userAgent)) {
            $data['user_agent'] = $userAgent;
        }

        if (!empty($currentUrl)) {
            $data['current_url'] = $currentUrl;
        }
        $path = 'users/impressions';

        return $this->httpCall($this->apiEndpoint . $path, 'POST', json_encode($data));
    }

    /**
     * Get the last error from curl.
     * 
     * @return array Array with 'code' and 'message' indexes
     */
    public function getLastError()
    {
        return $this->lastError;
    }
    
    public function executeDelayed()
    {
        try {
            $runningFileName = tempnam(sys_get_temp_dir(), "intercom");
            rename($this->delayedCmdFile, $runningFileName);
            
            $fp = fopen($runningFileName, "r");
            if($fp) {
                $handled = 0; 
                while (!feof($fp)) {
                    $query = fgets($fp);
                    
                    if ($query == false) {
                        continue;
                    }
                    
                    list($url, $method, $data) = explode('|||', $query);
                    
                    echo "sending call to " . $url . " \n";
                    $res = $this->httpCall($url, $method, $data, true);
                    $handled++;
                    
                    if($handled % 100 == 0){
                        echo "Handled " . $handled . " lines \n";
                    }
                }
                fclose($fp);
            }
            unlink($runningFileName);
        } catch(Exception $e) {
            $this->handle_delayed_error();
        }
    }
    
    public function handle_delayed_error()
    {
        $this->lastError = "An error occured when sending delayed calls";
        return $this->lastError;
    }
    
    public function setDelayed($delayed)
    {
        $this->delayed = $delayed;
    }
    
    public function getDelayed()
    {
        return $this->delayed;
    }
    
    public function setIncrementMode($value)
    {
        $this->incrementMode = $value;
    }
    
    public function getIncrementMode()
    {
        return $this->incrementMode;
    }
}
?>
