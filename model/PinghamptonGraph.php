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

//Configuration
require_once('config.php');

//JPGraph main and line graph libraries
require_once(PINGHAMPTON_DOCROOT . '../../jpgraph/src/jpgraph.php');
require_once(PINGHAMPTON_DOCROOT . '../../jpgraph/src/jpgraph_line.php');

//:TODO: Plenty, this hasn't been implemented and display.php doesn't use it yet
class PinghamptonGraph {
    
    /* Graph constants */
    const MAGNITUDES      = array('SMALL', 'MEDIUM', 'LARGE', 'BIGLY');
    const MAX_LABELS      = 40;
    const WIDTH_SMALL     = 700;
    const WIDTH_MEDIUM    = 1000;
    const WIDTH_LARGE     = 1440;
    const WIDTH_BIGLY     = 2880;
    const HEIGHT_SMALL    = 350;
    const HEIGHT_MEDIUM   = 500;
    const HEIGHT_LARGE    = 720;
    const HEIGHT_BIGLY    = 900;
                          
    /* Properties */      
    private $jpgraph      = null;
    private $width        = null;
    private $height       = null;
    private $samples      = 120;
    private $hardcap      = 200;
    private $showAverages = false;
    private $hideVerticalTicks = false;

    function __construct($magnitude = 'MEDIUM', $width = null, $height = null) {
        if (!in_array($magnitude, self::MAGNITUDES)) {
            die('drat');
        }
        
        //Configure some basic graph parameters
        switch($magnitude) {
        case 'SMALL':
            $this->width = is_numeric($width) ? $width : self::WIDTH_SMALL;
            $this->height = is_numeric($height) ? $height : self::HEIGHT_SMALL;
            break;
        case 'MEDIUM':
            $this->width = is_numeric($width) ? $width : self::WIDTH_MEDIUM;
            $this->height = is_numeric($height) ? $height : self::HEIGHT_MEDIUM;
            break;
        case 'LARGE':
            $this->width = is_numeric($width) ? $width : self::WIDTH_LARGE;
            $this->height = is_numeric($height) ? $height : self::HEIGHT_LARGE;
            break;
        case 'BIGLY':
            $this->width = is_numeric($width) ? $width : self::WIDTH_BIGLY;
            $this->height = is_numeric($height) ? $height : self::HEIGHT_BIGLY;
            break;
        }
        
        //Initialize the JpGraph object
        $this->jpgraph = new Graph($this->width, $this->height);
        
        //Determine whether or not to hide the X-axis ticks
        if (isset($_GET['xticks']) && $_GET['xticks'] == 1) {
            //Force tick display
            $this->hideVerticalTicks = false;
        }
        else if ($this->samples > 120) {
            //Hide ticks to reduce clutter
            $this->hideVerticalTicks = true;
        }
        
        return $this;
    }
    
	public function getJpgraph() {
		return $this->jpgraph;
	}
	function setJpgraph($jpgraph) {
		$this->jpgraph = $jpgraph;
	}
	public function getWidth() {
		return $this->width;
	}
	function setWidth($width) {
		$this->width = $width;
	}
	public function getHeight() {
		return $this->height;
	}
	function setHeight($height) {
		$this->height = $height;
	}
	public function getSamples() {
		return $this->samples;
	}
	function setSamples($samples) {
		$this->samples = $samples;
	}
	public function getHardcap() {
		return $this->hardcap;
	}
	function setHardcap($hardcap) {
		$this->hardcap = $hardcap;
	}
	public function getShowAverages() {
		return $this->showAverages;
	}
	function setShowAverages($showAverages) {
		$this->showAverages = $showAverages;
	}
	public function getHideVerticalTicks() {
		return $this->hideVerticalTicks;
	}
	function setHideVerticalTicks($hideVerticalTicks) {
		$this->hideVerticalTicks = $hideVerticalTicks;
	}
	
}
