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

class Logger {
    
    const LOGLEVEL_DEBUG = 0;
    const LOGLEVEL_INFO  = 2;
    const LOGLEVEL_ERROR = 4;
    const LOGLEVEL_ALL   = 6;
    
    private $filename = null;
    private $fp = null;
    
    public function __construct($filename) {
        $this->filename = $filename;
        if (!$this->fp = @fopen($filename, 'a')) {
            throw new Exception("Couldn't open log file for writing: $filename");
        }
    }
    
    public function log($message, $level = self::LOGLEVEL_ERROR, $console = false) {

        if ($level >= PINGHAMPTON_CLIENT_LOGLEVEL) {
            //Set the message preamble
            switch ($level) {
                case self::LOGLEVEL_DEBUG:
                    $preface = 'DEBUG';
                    break;
                case self::LOGLEVEL_ALL:
                case self::LOGLEVEL_INFO:
                    $preface = ' INFO';
                    break;
                case self::LOGLEVEL_ERROR:
                    $preface = 'ERROR';
                    break;
                default:
                    //Famous last words: "This is impossible!"
                    $preface = '  LOG';
                    break;
            }
            //Write to the log file
            @fwrite($this->fp, date('Y-m-d,H:i:s') . " {$preface}: " . 
                preg_replace('|[\r\n]|s', ' ', $message) . "\n");
            //Should we also output the message to the console?
            if ($console) {
                echo date('Y-m-d,H:i:s') . ': ' . $message . "\n";
            }
        }
    }
    
    public function truncate() {
        @fclose($this->fp);
        if (!$this->fp = @fopen($this->filename, 'w+')) {
            throw new Exception("Couldn't open log file for writing: " . $this->filename);
        }
    }
}
