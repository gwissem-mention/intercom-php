<?php
require_once __DIR__.'/../../Intercom.php';

class Intercom_Api extends Intercom
{   
    static $instance = null;
    
    static $enabled = null;
    
    static $bulkImportData = array();

    static $incrementalFields = array(
        'Pluggued_social_account',
        'received_shared_alert',
        'has_sent_an_invite',
        'downloaded_stats',
        'shared_an_alert',
        'read_mention',
        'used_mention'
    );
    
    public function __construct($appId, $apiKey, $enabled, $debug = false, $delayed = false)
    {
        parent::__construct($appId, $apiKey, $debug, $delayed);
    }
    
    public static function init($appId, $key, $enabled, $debug = false, $delayed = false)
    {

        try {
            self::$instance = new self(
                $appId,
                $key,
                $enabled,
                $debug,
                $delayed
            );
            self::$instance->appId = $appId;
            self::$instance->apiKey = $key;
            self::$enabled = $enabled;
        } catch(Exception $e) {
            /**
             * @TODO : handle this correctly
             */
            return false;
        }
    }
    
    public static function add($account, $plan, $lastRequestAt=null, $companies = array())
    {
        if(!self::$instance) {
            return false;
        }
        if(false == self::$enabled) {
            return true;
        }
        try {        
            $teamMembers = ($account->getTeamAccount()) ? count($account->getTeamAccount()->getTeamMembers()) : 0; 
            $data = array(
                "end_of_trial" => $account->getCreatedAt()->getTimestamp() + (60*60*24*30),
                "consumed_mentions" => $account->getMentionsUsed(),
                "langue" => $account->getLanguageCode(),
                "VIP" => $account->getFavorite(),
                "actual_plan" => $plan->getCode(),
                "quota" => $plan->getQuota(),
                "pluggued_social_account" => 0,
                "received_shared_alert" => 0,
                "deleted_account" => null,
                "has_sent_an_invite" => 0,
                "downloaded_stats" => 0,
                "shared_an_alert" => 0,
                "created_alert" => count($account->getUserAlerts()),
                "downgraded" => null,
                "read_mention" => 0,
                "used_mention" => 1,
                "team_members" => $teamMembers,
            );
            $res = self::$instance->createUser(
                $account->getId(),
                $account->getEmail(),
                $account->getName(),
                $data,
                $account->getCreatedAt()->getTimestamp(),
                null, 
                null,
                $companies,
                $lastRequestAt
            );
            return $res;
        } catch(Exception $e) {
            return false;
        }
    }
    
    public static function update($account, $data=array(), $plan=null)
    {
        if(!self::$instance) {
            return false;
        }
        if(false == self::$enabled) {
            return true;
        }
        try {
            $intercomUser = self::$instance->getUser($account->getEmail());
            $curlError = self::$instance->getLastError();
            if(isset($curlError['code']) && $curlError['code'] > 0){
                return false;
            }
            
            $data["consumed_mentions"] = $account->getMentionsUsed();
            $data["langue"] = $account->getLanguageCode();
            $data["VIP"] = $account->getFavorite();
            $data['given_company'] = $account->getCompanyName();
            $data['phone'] = $account->getPhoneWithGuessedPrefix();
            
            $quotaExceededAt = $account->getQuotaExceededAt();
            if($quotaExceededAt) {
                $quotaExceededAt = $quotaExceededAt->getTimestamp();
            }
            $data['exceeded_quota'] = $quotaExceededAt;
            if(!isset($data['created_alert'])) {
                $data['created_alert'] = count($account->getUserAlerts());
            }
            if(!isset($data['team_members'])) {
                $data['team_members'] = ($account->getTeamAccount()) ? count($account->getTeamAccount()->getTeamMembers()) : 0;
            }
            if(!isset($data['deleted_account'])) {
                $data['deleted_account'] = $account->getDeletedAt();
            }
            if(!isset($data['end_of_trial'])) {
                $data['end_of_trial'] = $account->getCreatedAt()->getTimestamp() + (60*60*24*30);
            }
            if($plan) {
                $data["actual_plan"] = $plan->getCode();
                $data["quota"] = $plan->getQuota();
            }
            
            if(isset($data['companies'])) {
                $companies = $data['companies'];
                unset($data['companies']);
            } else {
                $companies = array();
            }
            
            // If the user already exists on Intercom, then we need to increment the given fields,
            // otherwise, we need to make sure all fields are initialized
            if($intercomUser) {
                foreach(self::$incrementalFields as $field) {
                    
                    // If the field is defined in Intercom, increment if needed, otherwise, set to what needed
                    if(isset($intercomUser->custom_data->$field) && $intercomUser->custom_data->$field !== null) {
                        
                        // if a value is given for the field, increment by this value
                        if(isset($data[$field])) {
                            
                            //first check if increment mode is enabled
                            if(true === self::isIncrementModeEnabled()) {
                                $data[$field] += $intercomUser->custom_data->$field;
                            }
                        }
                    } else {
                        
                        // if no value is given for the field, set to 0
                        if(!isset($data[$field])) {
                            $data[$field] = 0;
                        }
                    }
                }
            } else {
                foreach(self::$incrementalFields as $field) {
                    if(!isset($data[$field])) {
                        $data[$field] = 0;
                    }
                }
            }

            $res = self::$instance->updateUser(
                null,
                $account->getEmail(),
                $account->getName(),
                $data,
                $account->getCreatedAt()->getTimestamp(),
                null, 
                null, 
                $companies,
                time()
            );
            return $res;
        } catch(Exception $e) {
            return false;
        }
    }
    
    /**
     * Used for the initial import of all users into Intercom
     * @param Account $account
     * @param array $data
     * @param Plan $plan
     * @return stdClass|boolean
     */
    public static function import($account, $data, $lastRequestAt=null, $companies=array())
    {
        if(!self::$instance) {
            self::$instance = new self(
                self::$appId,
                self::$key
            );
        }
        if(false == self::$enabled) {
            return true;
        }
        try {
            $res = self::$instance->createUser(
                $account->getId(),
                $account->getEmail(),
                $account->getName(),
                $data,
                $account->getCreatedAt()->getTimestamp(),
                null, 
                null,
                $companies,
                $lastRequestAt
            );
            return $res;
        } catch(Exception $e) {
            return false;
        }
    }
    
    /**
     * Used for the initial import iterations 
     * to delete all users from Intercom
     */
    public static function deleteAllUsers()
    {
    
        if(false == self::$enabled) {
            return true;
        }
        
        if(!self::$instance) {
            self::$instance = new self(
                            self::$appId,
                            self::$key
            );
        }
        try {
            $users = self::$instance->getAllUsers(1, 1000);
            echo "got : " . count($users->users) . "\n";
            foreach($users->users as $user) {
                if( !self::$instance->deleteUser($user->email)) {
                    echo "Oups ! " . $user->email . "\n";
                }
            }
        } catch(Exception $e) {
            return false;
        } 
    }
    
    public static function delete($email)
    {
        if(false == self::$enabled) {
            return true;
        }
        
        self::$instance->deleteUser($email);
    }
    
    public static function prepareUserforBulkImport($account, $customData, $lastRequestAt, $companies)
    {
        $data = array();
        $data['user_id'] = $account->getId();
        $data['email'] = $account->getEmail();
        $data['name'] = $account->getName();
        $data['created_at'] = $account->getCreatedAt()->getTimestamp();
        
        if (!empty($lastRequestAt)) {
            $data['last_request_at'] = $lastRequestAt;
        }
        
        if (!empty($customData)) {
            $data['custom_data'] = $customData;
        }
        
        if (!empty($companies)) {
            $data['companies'] = $companies;
        }
        
        self::$bulkImportData[] = $data;
    }
    
    public static function getUsersByPage($page, $perPage)
    {
        if(false == self::$enabled) {
            return true;
        }
        
        return self::$instance->getAllUsers($page, $perPage);
    }
    
    /** 
     * This method is not working for now because the api method on the server side is broken.
     * there is actually no documentation for it.
     */
    public static function bulkImport()
    {
        if(false == self::$enabled) {
            return true;
        }
        
        $path = 'users/bulk_create';
        $res = self::$instance->httpCall(
            self::$instance->apiEndpoint . $path, 
            'POST', 
            json_encode(self::$bulkImportData)
        );
        self::$bulkImportData = array();
        return $res;
    }
    
    public static function send_delayed_calls()
    {
        if(false == self::$enabled) {
            return true;
        }
        
        $res = self::$instance->executeDelayed();
    }
    
    public static function enableDelayedMode()
    {
        self::$instance->setDelayed(true);
    }
    
    public static function disableDelayedMode()
    {
        self::$instance->setDelayed(false);
    }
    
    public static function isDelayedModeEnabled()
    {
        return self::$instance->getDelayed();
    }
    
    public static function enableEncrementMode()
    {
        self::$instance->setIncrementMode(true);
    }
    
    public static function disableIncrementMode()
    {
        self::$instance->setIncrementMode(false);
    }
    
    public static function isIncrementModeEnabled()
    {
        return self::$instance->getIncrementMode();
    }
}