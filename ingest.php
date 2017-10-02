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

require_once('config.php');

//:TODO:
// Timezone shit, refactor yyyymmddhhii in favor of date functions on epoch
// or store yyyymmddhhii as UTC

/**
--new table pings_display?

explain
select distinct yyyymmddhhii, 
target_id, 
min(if(millis > -1, millis, null)) millis_min, 
avg(if(millis > -1, millis, null)) millis_avg, 
max(if(millis > -1, millis, null)) millis_max,
count(1) totpings,
(select count(1) from pings use index(primary) where yyyymmddhhii=p.yyyymmddhhii and target_id=p.target_id and millis=-1) lospackets
from pings p use index (primary)
where source_id=3 
group by yyyymmddhhii, target_id

**/

$errors = array();
$responseCodes = array();
    
//Connect to the database
try {
    $conn = new PDO('mysql:host=' . PINGHAMPTON_DB_HOST . ';dbname=' 
        . PINGHAMPTON_DB_NAME. ';charsetlatin1', PINGHAMPTON_DB_USER, PINGHAMPTON_DB_PASS,
        array(PDO::MYSQL_ATTR_FOUND_ROWS => true));
} catch (PDOException $e) {
    //:TODO: print only generic msg, log the details of getmessage
    echo 'Database unavailable: ' . $e->getMessage();
    exit;
}

//See if we were called as a web API
$incoming = @file_get_contents('php://input');
if (strlen($incoming) > 0 && php_sapi_name() != 'cli') {

    //Refuse to process requests larger than ~1MB
    if (strlen($incoming) > 1024000) {
        $responseCodes[] = PINGHAMPTON_API_REQUEST_TOO_BIG;
        $errors[] = 'The request you submitted was too large.';
        emit_response_and_exit($responseCodes, $errors);
    }
    
    //Parse action, API key, and other tokens from incoming headers
    $headers = array(
        'action' => null,
        'apiKey' => null,
        'targetGroup' => null,
    );
    if (count(getallheaders()) == 0) {
        $responseCodes[] = PINGHAMPTON_API_GENERAL_ERROR;
        $errors[] = 'Unable to locate headers in POST request.';
    }
    else {
        foreach (getallheaders() as $key=>$val) {
            if (strcasecmp($key, 'X-API-Action') == 0) {
                $headers['action'] = $val;
            }
            if (strcasecmp($key, 'X-API-Key') == 0) {
                $headers['apiKey'] = $val;
            }
            if (strcasecmp($key, 'X-Target-Group') == 0) {
                $headers['targetGroup'] = $val;
            }
        }
    }
    
    //Make sure we received an action
    if (is_null($headers['action'])) {
        //Nothing to do
        $responseCodes[] = PINGHAMPTON_API_MISSING_ACTION;
        $errors[] = 'You must set the X-API-Action header.';
    }
    
    //Make sure we received a valid API key
    if (is_null($headers['apiKey'])) {
        //Missing API key
        $responseCodes[] = PINGHAMPTON_API_MISSING_KEY;
        $errors[] = 'You must set the X-API-Key header.';
    }
    else {
        //Test the provided API key and ensure it's valid
        $query = <<<EOT
            SELECT COUNT(1)
            FROM api_keys a
            JOIN users u
            ON u.id = a.user_id
            WHERE api_key = :apikey
            AND a.active=1
            AND u.active=1
EOT;
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':apikey', $headers['apiKey'], PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            //Invalid API key
            $responseCodes[] = PINGHAMPTON_API_INVALID_KEY;
            $errors[] = 'The API key you supplied is invalid.';
        }
    }
    
    //If there are errors at this stage, emit a failure response and exit
    if (count($errors) > 0) {
        emit_response_and_exit($responseCodes, $errors);
    }

    //Array to hold the 'data' portion of our JSON response
    $responseData = array();
        
    //Determine which API action was requested        
    switch (strtolower($headers['action'])) {
        
        case 'gettargets':
            if (is_null($headers['targetGroup'])) {
                //Missing target group ID
                $responseCodes[] = PINGHAMPTON_INGEST_MISSING_TARGET_GROUP;
                $errors[] = 'You must set the X-Target-Group header.';
                emit_response_and_exit($responseCodes, $errors);
            }
            $responseData = api_do_get_targets($headers['apiKey'], $headers['targetGroup']);
            break;
        
        case 'submitpings':
            //:TODO: ensure the api key matches whatever's being submitted
    
            if (is_null($headers['targetGroup'])) {
                //Missing target group ID
                $responseCodes[] = PINGHAMPTON_INGEST_MISSING_TARGET_GROUP;
                $errors[] = 'You must set the X-Target-Group header.';
                emit_response_and_exit($responseCodes, $errors);
            }
            $responseData = api_do_submit_pings($headers['apiKey'], $incoming);
            
            break;
            
        case 'getcommands':
            $responseCodes[] = PINGHAMPTON_API_SUCCESS;
            $responseData = array('GetTargets', 'SubmitPings');
        break;
            
        default:
            $errors[] = 'No valid API action was found.';
            break;
    }
    
    //Emit response
    $response = array(
        'response_codes' => array_unique($responseCodes),
        'data' => $responseData,
        'errors' => array_unique($errors),
    );
    header('Content-type: application/json');
    echo json_encode($response);
    exit;
}

//Otherwise see if we were invoked from the command line
if (php_sapi_name() == 'cli') {
    
    $fp = fopen('ping-output.txt', 'r') or die("ping-output.txt not found\n");
    
    while ($line = fgets($fp)) {
        $i++;
        if (insert_record($line)) {
            $succ++;
        }
    }
    fclose($fp);
    
    echo "Processed {$i} lines, {$succ} succeeded\n";
}

echo 'Whup tee doo!';

function insert_ping_record($record) {
    global $conn, $errors;
    list ($targetgroup, $epoch, $ip, $millis) = explode(':', trim($record));
    if (!is_numeric($epoch) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        //:TODO: sane exception here
        throw new Exception('Useless exception about bad input');
        return false;
    }
    
    $query = <<<EOT
        INSERT pings (source_id, target_id, epoch, yyyymmddhhii, millis) 
        SELECT tga.source_id, t.id, :epoch, :yyyymmddhhii, :millis
        FROM targets t
        JOIN target_group_associations tga
        ON tga.target_group_id = :targetgroup
            AND tga.target_id = t.id
        WHERE ip = :target
        ON DUPLICATE KEY 
        UPDATE pings.id=pings.id      /* Suppress errors reimporting old logs */
EOT;

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':targetgroup', $targetgroup, PDO::PARAM_INT);
    $stmt->bindValue(':epoch', $epoch, PDO::PARAM_INT);
    $stmt->bindValue(':yyyymmddhhii', date('YmdHi', $epoch), PDO::PARAM_INT);
    $stmt->bindValue(':millis', (float)$millis, PDO::PARAM_STR);
    $stmt->bindValue(':target', $ip, PDO::PARAM_STR);
    $stmt->execute();
    
    //No rows means a failure
    if ($stmt->rowCount() == 0) {
        return false;
    }
    
    //:TODO: sane exception here
    if ($stmt->errorCode() != '00000') {
        throw new Exception('Useless exception about ' . var_export($stmt->errorInfo(), true));
        //die($conn->error . "\n");
        return false;
    }
    return true;
}

function api_do_submit_pings($apiKey, $incoming) {
    global $errors, $responseCodes;
    $i = 0;
    $successes = 0;
    
    //:TODO: if shit went south,
    //    $responseCodes[] = PINGHAMPTON_INGEST_NOT_ATTEMPTED;
        
    //Process incoming records
    //:TODO: Sanity checks on incoming data
    //apiKey isn't currently being checked
    $responseCodes[] = PINGHAMPTON_INGEST_ATTEMPTED;
    foreach (json_decode($incoming) as $line) {
        $i++;
        if(insert_ping_record($line)) {
            $successes++;
        }
    }
    if ($successes == $i) {
        //Every insert succeeded
        $responseCodes[] = PINGHAMPTON_INGEST_SUCCEEDED;
    }
    else if ($successes == 0) {
        //Every insert failed
        $responseCodes[] = PINGHAMPTON_INGEST_FAILED;
        $errors[] = 'Your client ping targets may be out of sync with your dashboard settings.';
    }
    else {
        //Some inserts succeeded, some failed
        $responseCodes[] = PINGHAMPTON_INGEST_MIXED;
        //:TODO:
        //Populating $errors here sends the client into a failure loop where processing
        //deferred work units never succeeds; suppress for now
        //$errors[] = 'Your client ping targets may be out of sync with your dashboard settings.';
    }
    
    return array('succeeded'=>$successes, 'failed'=>$i - $successes);
}

function api_do_get_targets($apiKey, $targetGroup) {
    global $conn, $errors, $responseCodes;
    
    $query = <<<EOT
        SELECT t.ip AS ip 
        FROM target_group_associations tga
        JOIN targets t
        ON t.id = tga.target_id
        JOIN api_keys a
        ON a.api_key = :apikey 
            AND a.user_id = tga.user_id
        WHERE tga.target_group_id = :targetgroup
        AND a.active = 1
EOT;
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':apikey', $apiKey, PDO::PARAM_STR);
    $stmt->bindValue(':targetgroup', $targetGroup, PDO::PARAM_INT);
    $stmt->execute();
    
    //No rows means a failure
    if ($stmt->rowCount() == 0) {
        $responseCodes[] = PINGHAMPTON_GETTARGETS_FAILED;
        $errors[] = 'No targets match the supplied target group and API key.';
        return null;
    }
    
    //Fetch the IP addresses
    $ips = array_column($stmt->fetchAll(), 'ip');
    $responseCodes[] = PINGHAMPTON_GETTARGETS_SUCCEEDED;
    return $ips;
}

function emit_response_and_exit($responseCodes, $errors, $responseData = null) {
    $response = array(
        'response_codes' => array_unique($responseCodes),
        'errors' => array_unique($errors),
        'data' => $responseData,
    );
    header('Content-type: application/json');
    echo json_encode($response);
    exit;
}

