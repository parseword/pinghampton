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
 * Define a Timer class to measure elapsed time between two events.
 * 
 * The timing functions in PHP for Windows don't always provide enough precision
 * to achieve sub-millisecond resolution, so when running on Windows, we require
 * the HRTime extension. On a unix-based system, the internal microtime function
 * is sufficient, and no third party extension is necessary.
 */

if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
    //Windows OS, make sure we have HRTime
    if (phpversion('hrtime') != '') {
        //We'll use a Timer class based on HRTime
        class Timer {
            private $stopwatch = null;
            function __construct() {
                $this->stopwatch = new HRTime\StopWatch;
            }
            public function start() {
                $this->stopwatch->start();
            }
            public function stop() {
                $this->stopwatch->stop();
            }
            public function getElapsedMillis() {
                return sprintf('%.02f', $this->stopwatch->getLastElapsedTime(HRTime\Unit::MICROSECOND) / 1000);
            }
        }
    }
    else {
        //No HRTime, game over
        throw new Exception('To run Pinghampton on Windows, you need the HRTime extension. '
            . 'See https://secure.php.net/manual/en/book.hrtime.php for details.');
    }
}
else {
    //Non-Windows OS, built-in functions should have sufficient precision
    class Timer {
        private $start = 0;
        private $stop = 0;
        public function start() {
            $this->start = microtime(true);
        }
        public function stop() {
            $this->stop = microtime(true);
        }
        public function getElapsedMillis() {
            return sprintf('%.02f', ($this->stop - $this->start) * 1000);
        }
    }
}
