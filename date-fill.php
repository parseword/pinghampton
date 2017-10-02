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

//Fill the "minutes" database table with a few years of values
    
//Connect to the database
try {
    $conn = new PDO('mysql:host=' . PINGHAMPTON_DB_HOST . ';dbname=' 
        . PINGHAMPTON_DB_NAME. ';charsetlatin1', PINGHAMPTON_DB_USER, PINGHAMPTON_DB_PASS,
        array(PDO::MYSQL_ATTR_FOUND_ROWS => true));
} catch (PDOException $e) {
    echo 'Database unavailable: ' . $e->getMessage();
    exit;
}

$j=0;
$dateStart = new DateTime('2016-01-01');
$dateEnd = new DateTime('2019-01-01');
$queryBase = 'INSERT minutes(minute) VALUES ';
$queryValues = array();

for ($i = $dateStart; $i <= $dateEnd; $i->modify('+1 minute')) {
    $queryValues[] = '(' . $i->format('YmdHi') . ')';
    //Insert 1000 at a time
    if (++$j % 1000 == 0) {
        echo "$j\n";
        $query = $queryBase . join(',', $queryValues) . ' ON DUPLICATE KEY UPDATE minute=minute';
        $conn->query($query);
        $queryValues = array();
    }
}
$query = $queryBase . join(',', $queryValues);
$conn->query($query);
