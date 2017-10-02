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
//objectify Graph from array to a class
//"Small" graph is 700x350 with max 6 targets, the minimum usable size w/legend and labels

//:TODO: default should use $samples minutes ago even when no sample exists for
//that minute, display no plot/empty plot. E.g. a graph of samples=1440 should
//show the past 24 hours even if there's only an hour worth of pings.

//:TODO: project wide, verify json_decode always has true as 2nd param

//If any target's lowest ping during the plot period is higher than the hardcap,
//show a notice somewhere. "Your hard cap setting is hiding all pings from at least one host"

//:TODO: from session
$user_id = 1;

//:todo: from session, prefs, or query string
$magnitude = 'MEDIUM';

//Some parameters that adjust the graph
$GRAPH = array(
    'width' => isset($_GET['width']) && is_numeric($_GET['width']) ? (int)$_GET['width'] : 1440,
    'height' => isset($_GET['height']) && is_numeric($_GET['height']) ? (int)$_GET['height'] : 600,
    'colors' => array('blue', 'green', 'black', 'brown', 'purple', 'gold', 'pink', 'orange'),

    'samples' => isset($_GET['samples']) && is_numeric($_GET['samples']) ? (int)$_GET['samples'] : 120,
    'hardcap' => isset($_GET['hardcap']) && is_numeric($_GET['hardcap']) ? (int)$_GET['hardcap'] : 250,
    //:TODO: security here
    'targetGroup' => isset($_GET['tg']) && is_numeric($_GET['tg']) ? (int)$_GET['tg'] : 0,
    'showAverages' => isset($_GET['showAverages']) ? true : false,
    //Ticks on the x-axis get in the way with a lot of plot points displayed
    'hideXTicks' => isset($_GET['xticks']) ? false : (
        //If plot points exceed 120, turn the ticks off
        (isset($_GET['samples']) && is_numeric($_GET['samples']) ? (int)$_GET['samples'] : 120) > 120 
        ? true : false
     )
);

//Get the list of target hosts as an array
//Need a graphs table and a graph_sources_targets table (?)
//:TODO: error handling, security, don't let user A see user B's shit
$query = <<<EOT
    SELECT DISTINCT
    t.id, t.name 
    FROM targets t 
    JOIN target_group_associations tga
    ON tga.target_id = t.id 
    WHERE tga.target_group_id = :targetgroup
    AND tga.user_id = :user_id
EOT;
$stmt = $conn->prepare($query);
$stmt->bindValue(':targetgroup', $GRAPH['targetGroup'], PDO::PARAM_INT);
$stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT); //:TODO: from session
//Run the query
if ($stmt->execute()) {
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
else {
    var_dump($stmt->errorInfo());
    //:TODO: error handling
    //$stmt->debugDumpParams();
    exit;
}
//var_dump($targets);exit;
$numTargets = count($targets);
if ($numTargets == 0) {
    //:TODO:
    die('No targets for that source in the given time period');
}
if ($numTargets > count($GRAPH['colors'])) {
    //:TODO:
    die("There are $numTargets targets to plot but only " . count($GRAPH['colors']) . ' colors configured');
}

//Set the yyyymmddhhii range to narrow the inner query as much as possible.
//minuteEnd is "now" by default, minuteStart is minuteEnd - $samples.
//:TODO: accept this as a query parameter for adjustable graph timeframe
//We max out at "5 minutes ago" because the default submit window is 5 minutes
$minuteStart = date('YmdHi', time() - (60 * $GRAPH['samples']) - 300);
$minuteEnd = date('YmdHi', time() - 300);

//Build the SELECT subjects based on our targets
$selectList = array();
$columnList = array();
foreach ($targets as $t) {
    $columnList[] = "target_{$t['id']}_avg_ping";
    $selectList[] = <<<EOT
        CASE
            WHEN SUM(IF(pm.target_id={$t['id']}, millis_avg, NULL)) > :hardcap THEN :hardcap
            ELSE CAST(SUM(IF(pm.target_id={$t['id']}, millis_avg, NULL)) AS DECIMAL(6,2))
        END AS 'target_{$t['id']}_avg_ping'
EOT;
}
$selectList = trim(join(",\n", $selectList));

//Build the full query
$query = <<<EOT
SELECT * 
FROM minutes min
LEFT JOIN
(
    SELECT * FROM 
    (
        SELECT DISTINCT
            yyyymmddhhii, 
            {$selectList}
        FROM ping_minutes pm
        USE INDEX (primary)
        JOIN target_group_associations tga
            ON tga.target_id = pm.target_id
            AND tga.source_id = pm.source_id
        WHERE
            tga.target_group_id = :targetgroup
            AND millis_avg <> -1.00             /* -1.00 represents a lost packet */
            AND yyyymmddhhii BETWEEN :minute_start AND :minute_end
        GROUP BY yyyymmddhhii 
        ORDER BY yyyymmddhhii DESC
        LIMIT :samples
    ) z
    ORDER BY yyyymmddhhii ASC
) y

ON y.yyyymmddhhii = min.minute
WHERE min.minute BETWEEN :minute_start AND :minute_end
EOT;

//Bind our parameters
$stmt = $conn->prepare($query);
$stmt->bindValue(':hardcap', $GRAPH['hardcap'], PDO::PARAM_INT);
$stmt->bindValue(':targetgroup', $GRAPH['targetGroup'], PDO::PARAM_INT);
$stmt->bindValue(':target', $t['id'], PDO::PARAM_INT);
$stmt->bindValue(':minute_start', (int)$minuteStart, PDO::PARAM_INT);
$stmt->bindValue(':minute_end', (int)$minuteEnd, PDO::PARAM_INT);
$stmt->bindValue(':samples', $GRAPH['samples'], PDO::PARAM_INT);

//Run the query
if ($stmt->execute()) {
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
else {
    var_dump($stmt->errorInfo());
    //:TODO: error handling
    //$stmt->debugDumpParams();
    exit;
}

//var_dump(array_column($results, 'target_1_avg_ping'));
//exit;
if (isset($_GET['beta'])) {
    
    require_once('model/PinghamptonGraph.php');
    
    $psg = new PinghamptonGraph($magnitude);
    exit;
}
else {
    require_once(PINGHAMPTON_DOCROOT . '../../jpgraph/src/jpgraph.php');
    require_once(PINGHAMPTON_DOCROOT . '../../jpgraph/src/jpgraph_line.php');
}

//See if the hardcap is suppressing display of any targets (i.e. all pings exceed it)
//var_dump(array_column($results, 'target_9_avg_ping'));exit;
$targetHidden = false;
foreach ($columnList as $col) {
    $columnAverage = array_avg(array_column($results, $col));
    if ($columnAverage >= $GRAPH['hardcap']) {
        $targetHidden = true;
    }
}

//Create a graph object and configure it a bit
$graph = new Graph($GRAPH['width'], $GRAPH['height']);
$graph->SetScale("textlin");
$graph->SetTheme(new UniversalTheme);
$graph->SetBox(true);
$graph->tabtitle->set("spikes >{$GRAPH['hardcap']} capped at {$GRAPH['hardcap']}");

//Set the display title and indicate if any targets are hidden
$titleCount = (count($results) < $GRAPH['samples']) ? count($results) : $GRAPH['samples'];
$title = "Average ping per minute plotted over {$titleCount} minutes";
if ($targetHidden) {
    $title .= "\n\nNote: target(s) hidden by hard cap value";
}
$graph->title->Set($title);


$graph->img->SetAntiAliasing();

$graph->yaxis->setTitle('Milliseconds', 'middle');
$graph->yaxis->setTitleMargin(29);
$graph->yaxis->HideZeroLabel();
$graph->yaxis->HideLine(false);
$graph->yaxis->HideTicks(false,false);

$graph->xaxis->SetLabelAngle(90);
$graph->xaxis->HideTicks($GRAPH['hideXTicks'], $GRAPH['hideXTicks']);

//:TODO: figure out tick distribution
//$graph->xaxis->scale->ticks->Set(40,5); 
//$graph->yaxis->scale->ticks->Set(1,5); 

$graph->xgrid->Show(!$GRAPH['hideXTicks']);
$graph->xgrid->SetLineStyle("solid");
$graph->xgrid->SetColor('#E3E3E3');

// Loop through targets adding data to the graph
$plots = array();

$i = 0;
foreach ($targets as $t) {
    $values = array_column($results, "target_{$t['id']}_avg_ping");
   
    $plot = new LinePlot($values);
    $graph->Add($plot);
//    $plot->SetFastStroke(true);
//    $plot->SetColor($GRAPH['colors'][$t['id']-1]);
    $plot->SetColor($GRAPH['colors'][$i]);
    $plot->SetWeight(1);
    $plot->SetLegend($t['name']);
    
    $avg_avg = array_avg($values);
    $avg_arr = array_fill(0, count($results), $avg_avg);
    $plot = new LinePlot($avg_arr);

    if ($GRAPH['showAverages']) {
        $graph->Add($plot);
        $plot->SetFastStroke(true);
        $plot->SetColor($GRAPH['colors'][$i]);
        $plot->SetWeight(1);
        $plot->SetStyle('dashed');
        //Dashed plots won't render with anti-aliasing turned on
        $graph->img->SetAntiAliasing(false);
    }
    $i++;
}

/**
:TODO: 
select and display packet loss values
*/
    

$graph->legend->SetFrameWeight(1);

$graph->xaxis->SetTickLabels(array_column($results, 'minute'));
//To reduce clutter, limit the number of time labels displayed on the x axis
if (count(array_column($results, 'minute')) > 40) { //:TODO: PinghamptonGraph::MAX_LABELS
    $graph->xaxis->SetTextLabelInterval(round(count(array_column($results, 'minute')) / 40));//:TODO: PinghamptonGraph::MAX_LABELS
}

// Emit the graph
$graph->Stroke();

function array_avg($array) {
    //Remove any empty values or packet loss indicators (-1 values)
    $array = array_filter($array,
        function($val) {
            return !(is_null($val) || $val == '' || $val == -1);
        }
    );
    $count = count($array);
    return ($count == 0) ? false : array_sum($array) / $count;
}
