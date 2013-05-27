<?php
require_once __DIR__.'/../../Intercom.php';

class Intercom_Api extends Intercom
{
    static $instance = null;
    
    static $bulkImportData = array();
    
    public function __construct($appId, $apiKey, $debug = false, $delayed = false)
    {
        parent::__construct($appId, $apiKey, $debug, $delayed);
    }
    
    public static function init($appId, $key, $debug = false, $delayed = false)
    {

        try {
            self::$instance = new self(
                $appId,
                $key,
                $debug,
                $delayed
            );
            self::$instance->appId = $appId;
            self::$instance->apiKey = $key;
        } catch(Exception $e) {
            /**
             * @TODO : handle this correctly
             */
            return false;
        }
    }
    
    public static function add($account, $plan, $lastRequestAt=null)
    {
        if(!self::$instance) {
            return false;
        }
        try {            
            $data = array(
                "end_of_trial" => $account->getQuotaNextRenew()->getTimestamp(),
                "consumed_mentions" => $account->getMentionsUsed(),
                "langue" => $account->getLanguageCode(),
                "VIP" => $account->getFavorite(),
                "plan" => $plan->getName(),
                "quota" => $plan->getQuota(),
                "pluggued_social_account" => 0,
                "received_shared_alert" => 0,
                "deleted_account" => false,
                "has_sent_an_invite" => 0,
                "downloaded_stats" => 0,
                "shared_an_alert" => 0,
                "created_alert" => 0,
                "read_mention" => 0,
                "used_mention" => 1
            );
            $res = self::$instance->createUser(
                $account->getId(),
                $account->getEmail(),
                $account->getName(),
                $data,
                $account->getCreatedAt()->getTimestamp(),
                null, 
                null,
                array(),
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
        try {
            $intercomUser = self::$instance->getUser($account->getId());
            
            $data["end_of_trial"] = $account->getQuotaNextRenew()->getTimestamp();
            $data["consumed_mentions"] = $account->getMentionsUsed();
            $data["langue"] = $account->getLanguageCode();
            $data["VIP"] = $account->getFavorite();
            
            if($plan) {
                $data["actual_plan"] = $plan->getName();
                $data["quota"] = $plan->getQuota();
            }
            
            if(isset($data['created_alert']) && isset($intercomUser->custom_data->created_alert)) {
                $data["created_alert"] += $intercomUser->custom_data->created_alert;
            }
            
            if(isset($data['pluggued_social_account']) && isset($intercomUser->custom_data->pluggued_social_account)) {
                $data["pluggued_social_account"] += $intercomUser->custom_data->pluggued_social_account;
            }
            
            if(isset($data['received_shared_alert']) && isset($intercomUser->custom_data->received_shared_alert)) {
                $data["received_shared_alert"] += $intercomUser->custom_data->received_shared_alert;
            }
            
            if(isset($data['has_sent_an_invite']) && isset($intercomUser->custom_data->has_sent_an_invite)) {
                $data["has_sent_an_invite"] += $intercomUser->custom_data->has_sent_an_invite;
            }
            
            if(isset($data['downloaded_stats']) && isset($intercomUser->custom_data->downloaded_stats)) {
                $data["downloaded_stats"] += $intercomUser->custom_data->downloaded_stats;
            }
            
            if(isset($data['shared_an_alert']) && isset($intercomUser->custom_data->shared_an_alert)) {
                $data["shared_an_alert"] += $intercomUser->custom_data->shared_an_alert;
            }
            
            if(isset($data['read_mention']) && isset($intercomUser->custom_data->read_mention)) {
                $data["read_mention"] += $intercomUser->custom_data->read_mention;
            }
            
            if(isset($data['used_mention']) && isset($intercomUser->custom_data->used_mention)) {
                $data["used_mention"] += $intercomUser->custom_data->used_mention;
            }
            
            $res = self::$instance->updateUser(
                $intercomUser->user_id,
                $intercomUser->email,
                $account->getName(),
                $data,
                $account->getCreatedAt()->getTimestamp()
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
    
    /** 
     * This method is not working for now because the api method on the server side is broken.
     * there is actually no documentation for it.
     */
    public static function bulkImport()
    {
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
        $res = self::$instance->executeDelayed();
    }
}