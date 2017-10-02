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

class WorkUnit {
    
    //A work unit identifier, only used locally at this time
    private $id = null;
    //The API action this work unit will perform e.g. SubmitPings
    private $action = null;
    //The JSON contents of the work unit
    private $json = null;
    
    function __construct($action, $id = null) {
        //Generate or set the identifier
        if (is_null($id)) {
            //Create a quasi-unique identifier
            $this->id = time() . '_' . substr(md5(microtime(true) . rand(0,999)), 0, 16);
        }
        //Valid id format is "epoch_randtoken" e.g. 1472930491_c89d9ab61fc13ff3
        else if (!preg_match('|\d{10}_[a-z0-9]{16}|i', $id)) {
            throw new InvalidArgumentException('Invalid format supplied for WorkUnit::id');
        }
        else {
            $this->id = $id;
        }
        
        //Valid action format is "VerbNoun" e.g. SubmitPings, GetTargetList
        if (!preg_match('|^([A-Z][A-Za-z]+){2,}$|', $action)) {
            throw new InvalidArgumentException('Invalid $action parameter to WorkUnit::__construct()');
        }
        $this->action = $action;

    }
    
    public function getAction() {
        return $this->action;
    }
    
    public function getId() {
        return $this->id;
    }
/**    
    public function setId($id) {
        //Valid id format is "epoch_randtoken" e.g. 1472930491_c89d9ab61fc13ff3
        if (!preg_match('|^\d{10}_[a-z0-9]{16}|i', $id)) {
            throw new InvalidArgumentException('Poorly formatted ID supplied '
                . 'to WorkUnit::setId()');
        }
        $this->id = $id;
    }
*/    
    public function getJson() {
        return $this->json;
    }
    
    public function setJson($json) {
        //Test that the JSON is syntactically valid
        if (!$this->validateJson($json)) {
            throw new InvalidArgumentException('Unparseable JSON supplied to '
                . 'setJson(), json_last_error(): ' . json_last_error());
        }
        $this->json = $json;
    }
    
    public static function validateJson($json) {
        if (is_null($json)) {
            throw new InvalidArgumentException('You must supply a string to WorkUnit::getJson');
        }
        @json_decode($json);
        return (json_last_error() === JSON_ERROR_NONE);
    }
    
    public function save() {
        //Ensure the deferred work unit directory is defined        
        if (!defined('PINGHAMPTON_CLIENT_SAVEDIR')) {
            throw new Exception('PINGHAMPTON_CLIENT_SAVEDIR is undefined, '
                . 'unable to save WorkUnit to disk for deferred action. '
                . 'The ping data contained in this WorkUnit has been lost.');
        }
        
        //Check that the target directory exists
        if (!is_dir(PINGHAMPTON_CLIENT_SAVEDIR) && !@mkdir(PINGHAMPTON_CLIENT_SAVEDIR)) {
            throw new Exception(PINGHAMPTON_CLIENT_SAVEDIR . ' does not exist, '
                . 'and attempting to create it failed');
        }
        
        //Attempt to write the work unit's JSON to a text file
        $filename = PINGHAMPTON_CLIENT_SAVEDIR . '/' . $this->id . '.txt';
        if (file_put_contents($filename, $this->json) != strlen($this->json)) {
            throw new Exception('Error writing to ' . $filename);
        }
        return true;
    }
/**    
    public function load() {
        //Check that the target file exists
        $filename = PINGHAMPTON_CLIENT_SAVEDIR . '/' . $this->id . '.txt';
        if (!file_exists($filename)) {
            throw new Exception('Unable to open nonexistent file ' . $filename);
        }
        
        //Load the contents of the file and test for syntactically valid JSON
        $json = file_get_contents($filename);
        if (!$this->validateJson($json)) {
            throw new Exception('Unparseable JSON contents in ' . $filename
                . ', json_last_error(): ' . json_last_error());
        }
        
        //Set our json property
        $this->json = $json;
        return true;
    }
    */
}