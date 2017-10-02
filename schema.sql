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

--Keys and indexes are geared towards InnoDB performance.

--:TODO: Force InnoDB, and also turn on innodb_file_per_table

--:TODO: reorder the drops, foreign keys prevent this file from working as a 
--"refresh" batch in its current order

DROP DATABASE IF EXISTS pinghampton;
CREATE DATABASE pinghampton;
GRANT ALL PRIVILEGES ON pinghampton.* 
    TO 'ping'@'localhost' IDENTIFIED BY '5cecb16e1f0133dcd43d8ef';
USE pinghampton;

DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email varchar(255) NOT NULL,
    hashed_password varchar(255) NOT NULL,
    active BIT NOT NULL DEFAULT 0,       /* Inactive until email verification */
    PRIMARY KEY(id),
    INDEX ix_users (id, email, hashed_password, active)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS sources;
CREATE TABLE sources (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id MEDIUMINT UNSIGNED NOT NULL,
    name VARCHAR(64) NOT NULL,
    PRIMARY KEY(id, user_id),
    FOREIGN KEY(user_id) REFERENCES users(id),
    INDEX ix_sources(id, user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS targets;
CREATE TABLE targets (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id MEDIUMINT UNSIGNED NOT NULL,
    ip VARCHAR(15) NOT NULL,
    name VARCHAR(64) NOT NULL,
    PRIMARY KEY(id, ip),
    FOREIGN KEY(user_id) REFERENCES users(id),
    INDEX ix_targets(id, user_id, ip, name)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/* used by the pinger to determine which hosts to ping */
DROP TABLE IF EXISTS target_groups;
CREATE TABLE target_groups (
    id INTEGER NOT NULL AUTO_INCREMENT,
    user_id MEDIUMINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    PRIMARY KEY(id, user_id),
    FOREIGN KEY(user_id) REFERENCES users(id),
    INDEX ix_target_groups(id, user_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/* used by the pinger to determine which hosts to ping */
DROP TABLE IF EXISTS target_group_associations;
CREATE TABLE target_group_associations (
    id INTEGER NOT NULL AUTO_INCREMENT,
    user_id MEDIUMINT UNSIGNED NOT NULL,
    target_group_id INTEGER NOT NULL,
    source_id MEDIUMINT UNSIGNED NOT NULL,
    target_id MEDIUMINT UNSIGNED NOT NULL,
    PRIMARY KEY(id, user_id, target_group_id, source_id, target_id),
    UNIQUE KEY unique_target_group_association(user_id, target_group_id, source_id, target_id),
    FOREIGN KEY(target_group_id) REFERENCES target_groups(id),
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(source_id) REFERENCES sources(id),
    FOREIGN KEY(target_id) REFERENCES targets(id),
    INDEX ix_target_group_associations(id, user_id, source_id, target_id),
    INDEX ix_target_group_associations_source_target(source_id,target_id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/* raw ping data submitted to the api */
DROP TABLE IF EXISTS pings;
CREATE TABLE pings (
    id INTEGER NOT NULL AUTO_INCREMENT,
    source_id MEDIUMINT UNSIGNED NOT NULL,
    target_id MEDIUMINT UNSIGNED NOT NULL,
    epoch INTEGER(10) UNSIGNED NOT NULL,
    yyyymmddhhii BIGINT(12) UNSIGNED NOT NULL,
    millis DECIMAL(6,2) NOT NULL,        /* -1.00 will indicate a lost packet */
    PRIMARY KEY(yyyymmddhhii, source_id, target_id, millis, id),
    UNIQUE KEY unique_ping (source_id, target_id, epoch),
    FOREIGN KEY(source_id) REFERENCES sources(id),
    FOREIGN KEY(target_id) REFERENCES targets(id),
    INDEX ix_pings(id, source_id, target_id, epoch, yyyymmddhhii, millis)
    /*INDEX ix_display(yyyymmddhhii, source_id, target_id, millis, id)*/
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS minutes;
CREATE TABLE minutes (
    minute BIGINT(12) UNSIGNED NOT NULL,
    PRIMARY KEY(minute)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

/* summarized ping data used by the grapher */
DROP TABLE IF EXISTS ping_minutes;
CREATE TABLE ping_minutes (
    id INTEGER NOT NULL AUTO_INCREMENT,
    yyyymmddhhii BIGINT(12) NOT NULL,
    source_id MEDIUMINT UNSIGNED NOT NULL,
    target_id MEDIUMINT UNSIGNED NOT NULL,
    millis_min DECIMAL(6,2) NOT NULL,
    millis_avg DECIMAL(8,4) NOT NULL,
    millis_max DECIMAL(6,2) NOT NULL,
    millis_sum DECIMAL(10,4) NOT NULL,
    lost_packets TINYINT UNSIGNED NOT NULL DEFAULT 0,
    valid_samples TINYINT UNSIGNED NOT NULL DEFAULT 0,
    total_samples TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY(yyyymmddhhii, source_id, target_id, millis_min, millis_avg, millis_max, millis_sum, lost_packets, valid_samples, total_samples, id),
    UNIQUE KEY unique_ping_minute (yyyymmddhhii, source_id, target_id),
    FOREIGN KEY(source_id) REFERENCES sources(id),
    FOREIGN KEY(target_id) REFERENCES targets(id),
    INDEX ix_ping_minutes_id (id)       /* Required for AUTO_INCREMENT column */
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS api_keys;
CREATE TABLE api_keys (
    id MEDIUMINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id MEDIUMINT UNSIGNED NOT NULL,
    api_key VARCHAR(64) NOT NULL,
    active BIT NOT NULL DEFAULT 1,
    PRIMARY KEY(user_id, active, id),
    FOREIGN KEY(user_id) REFERENCES users(id),
    INDEX ix_api_keys(user_id, id, api_key, active),
    INDEX ix_api_keys_id(id)            /* Required for AUTO_INCREMENT column */
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT users(email, hashed_password, active) VALUES ('root@example.local', 'x', 1);

INSERT targets (id, user_id, ip, name) VALUES (1, 1, '192.168.1.1',     'local baseline');
INSERT targets (id, user_id, ip, name) VALUES (5, 1, '216.58.193.131',  'Google - ATL');
INSERT targets (id, user_id, ip, name) VALUES (6, 1, '216.58.219.227',  'Google - NYC');
INSERT targets (id, user_id, ip, name) VALUES (7, 1, '216.58.192.131',  'Google - ORD');

INSERT sources (id, user_id, name) VALUES (1, 1, 'Office WLAN');
INSERT sources (id, user_id, name) VALUES (2, 1, 'Office Mac');
INSERT sources (id, user_id, name) VALUES (5, 1, 'Remote DFW');

INSERT target_groups (id, user_id, name) VALUES(1, 1, 'Servers pinged from office WLAN');
INSERT target_groups (id, user_id, name) VALUES(3, 1, 'Servers pinged from office Mac'); 
INSERT target_groups (id, user_id, name) VALUES(4, 1, 'Servers pinged from remote DFW'); 

INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 1, 1, 1);
INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 1, 1, 5);
INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 1, 1, 6);
INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 1, 1, 7);

INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 3, 2, 1);
INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 3, 2, 5);
INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 3, 2, 6);
INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 3, 2, 7);

INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 4, 5, 5);
INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 4, 5, 6);
INSERT target_group_associations (user_id, target_group_id, source_id, target_id) VALUES (1, 4, 5, 7);

-- SHA1 of random value
INSERT api_keys (user_id, api_key, active) VALUES (1, '5e1243bd22c66c76c2ba7eddc1f91394e57c9f83', 1);
