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

//Database configuration
require_once('/etc/config/pinghampton_db.conf');

$_APP = array(
    'name' => 'Pinghampton',
);

//Connect to the database
try {
    $conn = new PDO('mysql:host=' . PINGHAMPTON_DB_HOST . ';dbname=' 
        . PINGHAMPTON_DB_NAME. ';charsetlatin1', PINGHAMPTON_DB_USER, PINGHAMPTON_DB_PASS);
} catch (PDOException $e) {
    //:TODO: Display a public "DB unavailable" message and privately log the details
    echo 'Database unavailable: ' . $e->getMessage();
    exit;
}
$conn->query('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');

/* Administrative and control settings */
define('PINGHAMPTON_APPLICATION_VERSION', 0.1);
define('PINGHAMPTON_DOCROOT', '/home/sites/api.example.local/htdocs/pinghampton/');
define('PINGHAMPTON_ERROR_LOG', '/home/sites/api.example.local/htdocs/pinghampton/logs/error_log');
define('PINGHAMPTON_ERROR_MAIL_RECIPIENT', 'root@example.local');

//:TODO: move these into a static class shared between server and client
/* Define API status codes */
//General successes
define('PINGHAMPTON_API_SUCCESS',                  1001);

//General errors
define('PINGHAMPTON_API_GENERAL_ERROR',            4000);
define('PINGHAMPTON_API_MISSING_ACTION',           4001);
define('PINGHAMPTON_API_REQUEST_TOO_BIG',          4003);
define('PINGHAMPTON_API_MISSING_KEY',              4005);
define('PINGHAMPTON_API_INVALID_KEY',              4010);

//GetTargets successes
define('PINGHAMPTON_GETTARGETS_SUCCEEDED',         1105);
//GetTargets errors
define('PINGHAMPTON_GETTARGETS_FAILED',            4105);

//Ingest successes
define('PINGHAMPTON_INGEST_ATTEMPTED',             1200);
define('PINGHAMPTON_INGEST_SUCCEEDED',             1205);
define('PINGHAMPTON_INGEST_MIXED',                 1210);
//Ingest errors
define('PINGHAMPTON_INGEST_NOT_ATTEMPTED',         4200);
define('PINGHAMPTON_INGEST_FAILED',                4205);
define('PINGHAMPTON_INGEST_MISSING_TARGET_GROUP',  4215);
