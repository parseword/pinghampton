<?php
/*
 * Copyright 2016 Shaun Cummiskey, <shaun@shaunc.com> <http://shaunc.com>
 * <https://github.com/parseword/pinghampton/>
 *
 * This code is part of an experimental and unfinished project. Use at your
 * own risk.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and 
 * limitations under the License.
 */

class ApiClient {
    
    //A WorkUnit object encapsulating the API commands and data to post
    private $workUnit = null;
    //Response from the API server
    private $response = null;
    
    function __construct(WorkUnit $workUnit = null) {
        //Check for existence of required configuration options
        foreach (array('PINGHAMPTON_API_URI', 'PINGHAMPTON_API_KEY') as $const) {
            if (!defined($const)) {
                throw new Exception($const . ' must be defined to continue');
            }
        }
        
        $this->workUnit = $workUnit;
    }
    
    public function setWorkUnit(WorkUnit $workUnit) {
        $this->workUnit = $workUnit;
    }
    
    public function post() {
        //Make sure we have a work unit to send
        if (!($this->workUnit instanceof WorkUnit)) {
            throw new Exception('A valid WorkUnit must be set before calling post()');
        }
        
        //See if we can let curl do the heavy lifting
        if (function_exists('curl_init')) {
            
            //Set up the curl handle
            $ch = curl_init(PINGHAMPTON_API_URI);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Pinghampton Client/' 
                . PinghamptonClient::PINGHAMPTON_CLIENT_VERSION . ' +curl');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->workUnit->getJson());
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'X-API-Action: ' . $this->workUnit->getAction(),
                'X-API-Key: ' . PINGHAMPTON_API_KEY,
                'X-Target-Group: ' . PINGHAMPTON_TARGET_GROUP,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($this->workUnit->getJson())
            ));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            if (defined('PINGHAMPTON_CLIENT_BIND_IP')) {
                curl_setopt($ch, CURLOPT_INTERFACE, PINGHAMPTON_CLIENT_BIND_IP);
            }
            
            //Handle .htpasswd protection of the API endpoint if necessary
            if (defined('PINGHAMPTON_HT_USER') && !empty('PINGHAMPTON_HT_USER')
                && defined('PINGHAMPTON_HT_PASS') && !empty('PINGHAMPTON_HT_PASS')) {
                curl_setopt($ch, CURLOPT_USERPWD, PINGHAMPTON_HT_USER . ':' . PINGHAMPTON_HT_PASS);
            }
            
            //Post to API
            $result = curl_exec($ch);
            if ($result === false) {
                //Attempt to defer the work unit to disk
                $this->workUnit->save();
                //Raise an exception with the curl error
                throw new Exception('Failed to post to API server, curl error: '
                    . curl_error($ch));
            }
            curl_close($ch);
            
            $this->response = $result;
            return true;
        }
        
        else {
            //Fuck it, we'll do it live!
            //Create an array of HTTP headers
            $headers = array(
                'X-API-Action: ' . $this->workUnit->getAction(),
                'X-API-Key: ' . PINGHAMPTON_API_KEY,
                'X-Target-Group: ' . PINGHAMPTON_TARGET_GROUP,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($this->workUnit->getJson())
            );
            
            //Handle .htpasswd protection of the API endpoint if necessary
            if (defined('PINGHAMPTON_HT_USER') && !empty('PINGHAMPTON_HT_USER')
                && defined('PINGHAMPTON_HT_PASS') && !empty('PINGHAMPTON_HT_PASS')) {
                $headers[] = 'Authorization: Basic ' 
                    . base64_encode(PINGHAMPTON_HT_USER . ':' . PINGHAMPTON_HT_PASS);
            }
            
            //Create a stream context
            $context = stream_context_create(array(
                    'http' => array(
                        'method' => 'POST',
                        'timeout' => 30,
                        'user_agent' => 'Pinghampton Client/' . PinghamptonClient::PINGHAMPTON_CLIENT_VERSION,
                        'header' => $headers,
                        'content' => $this->workUnit->getJson()
                    )
                )
            );
            
            //Perform the HTTP POST 
            $result = @file_get_contents(PINGHAMPTON_API_URI, false, $context);
            
            if ($result === false) {
                //Attempt to defer the work unit to disk
                $this->workUnit->save();
                //Raise an exception with the error
                global $php_errormsg; //:TODO: This is deprecated in PHP 7.2
                throw new Exception('Failed to post to API server, PHP error: '
                    . $php_errormsg);
            }
            
            $this->response = $result;
            return true;
        }
    }
    
    public function getResponse() {
        return json_decode($this->response, true);
    }
    
    public function getResponseCodes() {
        $response = json_decode($this->response, true);
        if (is_array($response) && isset($response['response_codes'])) {
            return $response['response_codes'];
        }
        return null;
    }
    
    public function getResponseData() {
        $response = json_decode($this->response, true);
        if (is_array($response) && isset($response['data'])) {
            return $response['data'];
        }
        return null;
    }
    
    public function getResponseErrors() {
        $response = json_decode($this->response, true);
        if (is_array($response) && isset($response['errors'])) {
            return $response['errors'];
        }
        return null;
    }
    
    public function getResponseRaw() {
        return $this->response;
    }
}