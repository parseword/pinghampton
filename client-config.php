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

/*
 * Environment configuration. These settings control where Pinghampton writes
 * its log file, and where it saves deferred work units if the API fails.
 */
//The directory where you installed Pinghampton
define('PINGHAMPTON_CLIENT_DIRECTORY', '/usr/local/etc/pinghampton');

//Where to save failed API submissions to retry later. Comment out to disable.
define('PINGHAMPTON_CLIENT_SAVEDIR', PINGHAMPTON_CLIENT_DIRECTORY . '/deferred-work-units');

//Where Pinghampton should put its log file
define('PINGHAMPTON_CLIENT_LOGFILE', PINGHAMPTON_CLIENT_DIRECTORY . '/pinghampton-log.txt');

//The verbosity level for the log file
define('PINGHAMPTON_CLIENT_LOGLEVEL', Logger::LOGLEVEL_DEBUG);

//If your system has multiple IPs, you can force a non-default one to be used
//define('PINGHAMPTON_CLIENT_BIND_IP', '192.168.40.116');

/* 
 * Timing configuration. Use these settings to control how frequently Pinghampton
 * performs various actions. Each value is given in seconds. The defaults are
 * recommended for most situations, and will make Pinghampton behave as follows:
 *
 * - Targets are pinged about every 20 seconds (3x per minute),
 * - Ping data is reported to the server about every 5 minutes,
 * - On connection failure to the API server, client will retry after 5 minutes,
 * - Failed work units are re-submitted, and ping targets updated, once an hour
 */
//Pinghampton will pause this many seconds between pings, 20 or 30 are good values
define('PINGHAMPTON_CLIENT_PING_INTERVAL', 20);

//How often ping data will be submitted to the API 
define('PINGHAMPTON_CLIENT_SUBMIT_INTERVAL', 300);

//How long to pause when connectivity to the API server appears down
define('PINGHAMPTON_CLIENT_RETRY_INTERVAL', 300);

//How often the client will process deferred work and update its ping targets
define('PINGHAMPTON_CLIENT_REFRESH_INTERVAL', 3600);

//Account, authentication, and ping target configuration
//:TODO: these all need to have CLIENT in them
define('PINGHAMPTON_TARGET_GROUP', 1);
define('PINGHAMPTON_HT_USER', 'pinghampton');
define('PINGHAMPTON_HT_PASS', 'f95cc48a2789d680_Truncheon_5f356f3960');
//:TODO: document what to do with SSL cert error. http://stackoverflow.com/a/31830614
// Failed to post to API server, curl error: SSL certificate problem: unable to get local issuer certificate
define('PINGHAMPTON_API_URI', 'https://api.example.local/pinghampton/ingest.php');
define('PINGHAMPTON_API_KEY', '5e1243bd22c66c76c2ba7eddc1f91394e57c9f83');
