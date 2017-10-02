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

USE pinghampton;

/* Pull a snapshot of current ping IDs into a temp table */
DROP TABLE IF EXISTS current_ping_ids;
CREATE TABLE current_ping_ids AS SELECT id FROM pings;
ALTER TABLE current_ping_ids ADD PRIMARY KEY(id);

/* Insert/update ping_minutes */
INSERT
    ping_minutes 
    (source_id, target_id, yyyymmddhhii, millis_min, millis_avg, millis_max, millis_sum, lost_packets, valid_samples, total_samples) 
SELECT DISTINCT 
    source_id, 
    target_id,  
    yyyymmddhhii,  
    CASE WHEN MIN(IF(millis > -1, millis, NULL)) IS NULL THEN -1 ELSE MIN(IF(millis > -1, millis, NULL)) END AS millis_min, 
    CASE WHEN AVG(IF(millis > -1, millis, NULL)) IS NULL THEN -1 ELSE CAST(AVG(IF(millis > -1, millis, NULL)) AS DECIMAL(8,4)) END AS millis_avg, 
    CASE WHEN MAX(IF(millis > -1, millis, NULL)) IS NULL THEN -1 ELSE MAX(IF(millis > -1, millis, NULL)) END AS millis_max,
    CASE WHEN SUM(IF(millis > -1, millis, NULL)) IS NULL THEN -1 ELSE SUM(IF(millis > -1, millis, NULL)) END AS millis_sum,
    (
        SELECT COUNT(1)
        FROM pings USE INDEX(PRIMARY)
        WHERE yyyymmddhhii=p.yyyymmddhhii AND source_id=p.source_id AND target_id=p.target_id AND millis=-1
    ) lost_packets, 
    (
        SELECT COUNT(1)
        FROM pings USE INDEX(PRIMARY)
        WHERE yyyymmddhhii=p.yyyymmddhhii AND source_id=p.source_id AND target_id=p.target_id AND millis <> -1
    ) valid_samples, 
    COUNT(1) total_samples
FROM pings p USE INDEX (PRIMARY)
JOIN current_ping_ids cpi ON cpi.id = p.id
GROUP BY yyyymmddhhii, source_id, target_id

ON DUPLICATE KEY UPDATE 
    millis_min = CASE WHEN (VALUES(millis_min) < millis_min AND VALUES(millis_min) <> -1) THEN VALUES(millis_min) ELSE millis_min END,  
    millis_avg = CASE WHEN VALUES(millis_sum) <> -1 THEN CAST((millis_sum + VALUES(millis_sum)) / (valid_samples + VALUES(valid_samples)) AS DECIMAL(8,4)) ELSE millis_avg END, 
    millis_max = CASE WHEN (VALUES(millis_max) > millis_max AND VALUES(millis_max) <> -1) THEN VALUES(millis_max) ELSE millis_max END, 
    millis_sum = CASE WHEN (VALUES(millis_sum) <> -1 AND VALUES(millis_sum) <> millis_sum) THEN CAST(millis_sum + VALUES(millis_sum) AS DECIMAL(10,4)) ELSE millis_sum END,
    lost_packets = lost_packets + VALUES(lost_packets), 
    valid_samples = valid_samples + VALUES(valid_samples),
    total_samples = total_samples + VALUES(total_samples);
    
/* Delete our now-tallied rows from the pings table */
INSERT backup_pings (id, source_id, target_id, epoch, yyyymmddhhii, millis)
SELECT pings.id, pings.source_id, pings.target_id, pings.epoch, pings.yyyymmddhhii, pings.millis FROM pings
JOIN current_ping_ids cpi ON cpi.id = pings.id;

DELETE pings FROM pings
JOIN current_ping_ids cpi ON cpi.id = pings.id
WHERE 1=1;