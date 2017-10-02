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

/* Configure autoloading. This *must* run prior to any other code. */
spl_autoload_register(function($class) {
    include(dirname($_SERVER['SCRIPT_FILENAME']) . '/model/' . $class . '.php');
});

//Load client configuration parameters
require_once('client-config.php');

//:TODO: consistent null handling, pick either is_null() or === null

//:TODO: command to run via exec if no root access?
// ping -q -n -c 1 -W 1 cnn.com | grep received | cut -f10 -d' ' | sed s/[^0-9]//g

//Initialize a logger, if this fails we can't continue
try {
    $logger = new Logger(PINGHAMPTON_CLIENT_LOGFILE);
}
catch (Exception $e) {
    echo "Pinghampton was unable to properly initialize its logger:\n"
        . $e->getMessage() . "\nThis is a fatal error.\n"
        . 'Please ensure PINGHAMPTON_CLIENT_LOGFILE is set to a valid writable '
        . "location in your config file.\n";
    exit;
}
$logger->log('Pinghampton client version ' . PinghamptonClient::PINGHAMPTON_CLIENT_VERSION
    . ' starting up', Logger::LOGLEVEL_ALL, true);

//Create the PinghamptonClient object
$psc = new PinghamptonClient($logger);

//Register a signal handler for SIGHUP if pcntl is available
if (function_exists('pcntl_signal')) {
    $logger->log('pcntl detected, registering signal handler', 
        Logger::LOGLEVEL_DEBUG);
    pcntl_signal(SIGHUP, 'signal_handler');
}

//Look for any existing deferred work units to submit
if (defined('PINGHAMPTON_CLIENT_SAVEDIR')) {
    try {
        $psc->processDeferredWorkUnits();
    }
    catch (Exception $e) {
        $logger->log('Error processing deferred work units: ' . $e->getMessage(), 
            Logger::LOGLEVEL_ERROR);
    }
}

//Fetch the list of targets for the client to ping
$logger->log('Fetching list of ping targets', Logger::LOGLEVEL_DEBUG);
while (count($psc->getTargets()) == 0) {
    try {
        $psc->apiGetTargets();
    }
    catch (Exception $e) {
        $logger->log('Error fetching ping targets: ' . $e->getMessage(), 
            Logger::LOGLEVEL_ERROR);
    }
    //If we failed to fetch targets, sleep and try again later
    if (count($psc->getTargets()) == 0) {
        echo "Error fetching targets, see log file for details\n";
        $logger->log('GetTargets attempt ' . @++$i . ' failed, will retry in ' 
            . PINGHAMPTON_CLIENT_RETRY_INTERVAL . ' seconds', Logger::LOGLEVEL_ERROR);
        sleep(PINGHAMPTON_CLIENT_RETRY_INTERVAL);
    }
}

//An array to hold our ping data
$pings = array();

//The main client loop. Ping, submit, refresh, repeat.
while (1) {
    //Handle incoming SIGHUP if pcntl is available
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
    
    //Iterate through our targets
    $logger->log('Entering ping loop', Logger::LOGLEVEL_DEBUG);
    foreach ($psc->getTargets() as $t) {
        try {
            //Ping!
            $line = PINGHAMPTON_TARGET_GROUP . ':' . time() . ':' . $t . ':' . $psc->ping($t);
            $pings[] = $line;
            $logger->log('Ping result: ' . $line, Logger::LOGLEVEL_DEBUG);
        }
        catch (Exception $e) {
            $logger->log("Error pinging target {$t}: " . $e->getMessage(), 
                Logger::LOGLEVEL_ERROR, true);
        }
    }
    
    //See if it's time to submit a batch of pings to the API
    if ((time() - $psc->getTimeLastPingSubmitAttempted()) > PINGHAMPTON_CLIENT_SUBMIT_INTERVAL) {
        $psc->reportStats();
        $logger->log('Submitting ping data', Logger::LOGLEVEL_DEBUG);
        try {
            //Submit this batch of pings to the reporting API
            $psc->apiSubmitPings($pings);
        }
        catch (Exception $e) {
            $logger->log('Error submitting ping data: ' . $e->getMessage(), 
                Logger::LOGLEVEL_ERROR, true);
        }
        
        //Reset our ping data array
        $pings = array();
    }
    
    //See if it's time to perform a client refresh
    if (defined('PINGHAMPTON_CLIENT_REFRESH_INTERVAL') &&
        (time() - $psc->getTimeLastRefreshed()) > PINGHAMPTON_CLIENT_REFRESH_INTERVAL) {
        signal_handler('(invoked from controller)');
    }
    
    //Pause when agitated
    sleep(PINGHAMPTON_CLIENT_PING_INTERVAL);
}
        
function signal_handler($sig) {
    global $logger, $psc;
    $logger->log('signal_handler() caught signal ' . $sig . ', refreshing', 
        Logger::LOGLEVEL_DEBUG, true);
    
    //Process deferred work units and refresh the ping target list from the API
    try {
        $psc->refresh();
    }
    catch (Exception $e) {
        $logger->log('signal_handler() encountered an error refreshing the client: ' 
            . $e->getMessage(), Logger::LOGLEVEL_ERROR);
    }
}
