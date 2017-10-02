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

class PinghamptonClient {
//:TODO: ensure "fail fast" everywhere getResponseData is being called, test
//for null and bail right away
    const PINGHAMPTON_CLIENT_VERSION = 10084;

    private $id = null;
    private $logger = null;
    private $timer = null;
    private $targets = array();
    private $timeCreated = null;
    private $timeLastPingSubmitAttempted = null;
    private $timeLastRefreshed = null;
    private $pingCountSuccess = 0;
    private $pingCountFailure = 0;
    private $hasDeferredWorkUnits = false;
    
    function __construct(Logger $logger) {
        //Set a few properties
        $this->logger = $logger;
        $this->timeCreated = time();
        $this->timeLastPingSubmitAttempted = time();
        $this->timeLastRefreshed = time();
        
        //Set a randomish ID
        $this->id = substr(md5(microtime(true) . rand(0,999)), 0, 16);
        $logger->log("Client {$this->id} instantiated", Logger::LOGLEVEL_DEBUG);
        
        //Initialize a timer object, if this fails we can't continue
        try {
            $this->timer = new Timer;
        }
        catch (Exception $e) {
            $message = "Pinghampton was unable to properly initialize a timer:\n"
                . $e->getMessage() . "\nThis is a fatal error.\n";
            $logger->log($message, Logger::LOGLEVEL_ERROR, true);
            exit;
        }
        $logger->log("Client {$this->id} Timer initialized", Logger::LOGLEVEL_DEBUG);
    }
    
    public function getTargets() {
        $this->logger->log("Client {$this->id} getTargets() returning "
            . var_export($this->targets, true), Logger::LOGLEVEL_DEBUG);
        return $this->targets;
    }
    
    public function hasDeferredWorkUnits() {
        return $this->hasDeferredWorkUnits;
    }
    
    public function getTimeLastPingSubmitAttempted() {
        $this->logger->log("Client {$this->id} getTimeLastPingSubmitAttempted() "
            . "returning {$this->timeLastPingSubmitAttempted}", 
            Logger::LOGLEVEL_DEBUG);
        return $this->timeLastPingSubmitAttempted;
    }
    
    public function getTimeLastRefreshed() {
        $this->logger->log("Client {$this->id} getTimeLastRefreshed() "
            . "returning {$this->timeLastRefreshed}", 
            Logger::LOGLEVEL_DEBUG);
        return $this->timeLastRefreshed;
    }
    
    function apiGetTargets() {
        $this->logger->log("Client {$this->id} apiGetTargets() for target group "
            . PINGHAMPTON_TARGET_GROUP, Logger::LOGLEVEL_DEBUG);
        //Retrieve the set of hosts we intend to ping
        $unit = new WorkUnit('GetTargets');
        $unit->setJson(json_encode(PINGHAMPTON_TARGET_GROUP));
        $api = new ApiClient($unit);
        $api->post();
        
        //Test for response errors
        if (is_array($api->getResponseErrors()) && count($api->getResponseErrors()) > 0) {
            //Log errors and raise an exception
            $message = "Client {$this->id} apiGetTargets() failed, server said: "
                . var_export($api->getResponseErrors(), true);
            $this->logger->log($message, Logger::LOGLEVEL_ERROR);
            throw new Exception($message);
        }
        
        //Get the response data
        $responseData = $api->getResponseData();
        if (!is_null($responseData)) {

            //Disregard any elements that aren't valid IPs
            $this->targets = array_filter(
                $responseData,
                function($val) {
                    return filter_var($val, FILTER_VALIDATE_IP);
                }
            );
            $this->logger->log("Client {$this->id} apiGetTargets() received targets "
                . var_export($this->targets, true), Logger::LOGLEVEL_DEBUG);
        }
        else {
            $this->logger->log("Client {$this->id} apiGetTargets() received null "
                . 'targets, rawResponse ' . var_export($api->getResponseRaw(), true), 
                Logger::LOGLEVEL_DEBUG);
            return false;
        }
        return true;
    }
    
    function apiSubmitPings($pings, $deferOnFailure = true) {
        
        if (!is_array($pings)) {
            throw new Exception('apiSubmitPings() requires an array parameter');
        }
        
        //Update the timestamp to reflect this attempt
        $this->timeLastPingSubmitAttempted = time();
        
        //:TODO: eventually refactor so we submit target IDs not raw ips
        //format....    target_group:epoch:target_id:millis
        
        $unit = new WorkUnit('SubmitPings');
        $unit->setJson(json_encode($pings));
        
        $this->logger->log("Client {$this->id} apiSubmitPings() submitting WorkUnit " 
            . "{$unit->getId()} with " . count($pings) . ' pings', Logger::LOGLEVEL_DEBUG);
        
        $api = new ApiClient($unit);
        $api->post();
        
        //Test for response errors
        if (is_array($api->getResponseErrors()) && count($api->getResponseErrors()) > 0) {
            if ($deferOnFailure === true) {
                //Attempt to store the work unit for later processing
                $this->logger->log("Client {$this->id} deferring work unit "
                    . $unit->getId() . ' to disk', Logger::LOGLEVEL_ERROR);
                $unit->save();
                $this->hasDeferredWorkUnits = true;
            }
            
            //Log errors and raise an exception
            $message = "Client {$this->id} apiSubmitPings() failed, server said: "
                . var_export($api->getResponseErrors(), true);
            $this->logger->log($message, Logger::LOGLEVEL_ERROR);
            throw new Exception($message);
        }
        
        //Get the response data
        $responseData = $api->getResponseData();
        if (!is_null($responseData)) {
            if (isset($responseData['succeeded']) && isset($responseData['failed'])) {
                $this->logger->log("Client {$this->id} apiSubmitPings() recorded "
                    . "{$responseData['succeeded']} successes, {$responseData['failed']} "
                    . 'failures', Logger::LOGLEVEL_DEBUG);
            }
        }
        else {
            $this->logger->log("Client {$this->id} apiSubmitPings() received "
                . 'null response, rawResponse ' . var_export($api->getResponseRaw(), true),
                Logger::LOGLEVEL_DEBUG);
            return false;
        }
        
        return true;
    }
    
    function ping($target) {
        //Payload content is 'PINGSEER' from old project name
        $payload = "\x08\x00\xc0\xd7\x00\x00\x00\x00\x50\x49\x4e\x47\x53\x45\x45\x52";
        
        $sock = @socket_create(AF_INET, SOCK_RAW, 1);
        if ($sock === false) {
            throw new Exception('Unable to create socket. You must become root to run this utility.');
            return null;
        }
        @socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>2, 'usec'=>0));
        //See if we need to bind to a specific interface
        if (defined('PINGHAMPTON_CLIENT_BIND_IP')) {
            if (!@socket_bind($sock, PINGHAMPTON_CLIENT_BIND_IP)) {
                throw new Exception('Unable to bind to IP address ' . PINGHAMPTON_CLIENT_BIND_IP);
            }
        }
        @socket_connect($sock, $target, null);
        
        $this->timer->start();
        @socket_send($sock, $payload, strlen($payload), 0);
       
        if(@socket_read($sock, 512)) {
            $this->timer->stop();
            @socket_close($sock);
            $this->pingCountSuccess++;
            return $this->timer->getElapsedMillis();
        }
        else {
            $this->timer->stop();
            @socket_close($sock);
            $this->pingCountFailure++;
            //We use -1 to signify a lost packet
            $this->logger->log("Client {$this->id} ping() lost packet to {$target}",
                Logger::LOGLEVEL_DEBUG);
            return -1.00;
        }
    }
    
    function reportStats() {
        //Only log statistics if the logging level is DEBUG
        if (PINGHAMPTON_CLIENT_LOGLEVEL > Logger::LOGLEVEL_DEBUG) {
            return;
        }
        
        //Longevity
        $dateTime = new DateTime('@0');
        $this->logger->log("Client {$this->id} has been alive " 
            . $dateTime->diff(new DateTime('@' . (int)(time() - $this->timeCreated)))
            ->format('%a days, %h hours, %i minutes, %s seconds'), 
            Logger::LOGLEVEL_DEBUG);
        
        //Ping counts
        $this->logger->log($this->pingCountSuccess . ' pings succeeded, '
            . $this->pingCountFailure . ' pings failed', Logger::LOGLEVEL_DEBUG);
        
        //Memory usage
        $this->logger->log('memory_get_usage() ' . memory_get_usage(), 
            Logger::LOGLEVEL_DEBUG);
        $this->logger->log('memory_get_usage(true) ' . memory_get_usage(true), 
            Logger::LOGLEVEL_DEBUG);
        $this->logger->log('memory_get_peak_usage() ' . memory_get_peak_usage(), 
            Logger::LOGLEVEL_DEBUG);
        $this->logger->log('memory_get_peak_usage(true) ' . 
            memory_get_peak_usage(true), Logger::LOGLEVEL_DEBUG);
    }
    
    function processDeferredWorkUnits() {
        //Check the environment
        if (!defined('PINGHAMPTON_CLIENT_SAVEDIR')) {
            throw new Exception('PINGHAMPTON_CLIENT_SAVEDIR is undefined, '
                . 'unable to process deferred work units');
        }
        $this->logger->log("Client {$this->id} processDeferredWorkUnits() "
            . 'looking for files to process', Logger::LOGLEVEL_DEBUG);
        
        //Look for files in the deferred work unit directory
        $files = @array_slice(@scandir(PINGHAMPTON_CLIENT_SAVEDIR), 2);
        if (!is_array($files) || @count($files) == 0) {
            $this->logger->log("Client {$this->id} processDeferredWorkUnits() "
                . 'found no saved files', Logger::LOGLEVEL_DEBUG);
            return;
        }
        
        //Iterate through the files looking for work units
        foreach ($files as $file) {
            
            //Valid format is "epoch_randtoken" e.g. 1472930491_c89d9ab61fc13ff3
            if (!preg_match('|^\d{10}_[a-z0-9]{16}|i', $file, $result)) {
                $this->logger->log("Client {$this->id} processDeferredWorkUnits() "
                    . 'skipping ' . $file, Logger::LOGLEVEL_DEBUG);
                continue;
            }
            $this->logger->log("Client {$this->id} processDeferredWorkUnits() "
                    . 'processing ' . $file, Logger::LOGLEVEL_DEBUG);

            //Attempt to submit the work unit
            if (!$this->apiSubmitPings(json_decode(@file_get_contents(PINGHAMPTON_CLIENT_SAVEDIR . '/' . $file), true), false)) {
            
                $this->logger->log("Client {$this->id} processDeferredWorkUnits() "
                    . 'failed to submit ' . $file, Logger::LOGLEVEL_DEBUG);
                continue;
            }
            
            //Submitted successfully, delete this work unit file
            $this->logger->log("Client {$this->id} processDeferredWorkUnits() "
                . 'success, deleting file ' . $file, Logger::LOGLEVEL_DEBUG);
            @unlink(PINGHAMPTON_CLIENT_SAVEDIR . '/' . $file);
        }
 
        //Clear the hasDeferredWorkUnits flag
        $this->hasDeferredWorkUnits = false;
    }
    
    public function refresh() {
        $this->timeLastRefreshed = time();
        $this->apiGetTargets();
        $this->processDeferredWorkUnits();
    }
}
