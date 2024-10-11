<?php

function getDB()
{
    $db = new SQLite3(__DIR__ . "/assets/QuotesDB.db");

    if (!$db) {
        error_log("Failed to connect to the DB");
        return false;
    }

    return $db;
}

function getDBSQLite()
{
    $db = new SQLite3(__DIR__ . "/assets/QuotesDB.db");

    if (!$db) {
        error_log("Failed to connect to the DB");
        return false;
    }

    return $db;
}


function makeDBClone()
{
    $db = getDB();

    // Clone a SQLite DB
    // $db->exec("ATTACH DATABASE 'backup/QuotesDB.db' AS backup");
    $db->query("CREATE DATABASE IF NOT EXISTS backup");

    // $db->exec("CREATE TABLE backup.quotes AS SELECT * FROM quotes");
    $db->query("CREATE TABLE IF NOT EXISTS backup.quotes AS SELECT * FROM `quotes`");

    // $db->exec("DETACH DATABASE backup");
    // $db->query("DROP DATABASE backup");

    $db->close();
}

/**
 * Reads the json and creates a DB Table with exact structure
 *
 * @return void
 */
function create_table()
{
    $json = file_get_contents(__DIR__ . "/assets/quotes.json");

    $data = json_decode($json, true);

    $db = getDB();

    // $db->exec("DROP TABLE IF EXISTS quotes;") or die("Failed to drop table");
    $db->query("DROP TABLE IF EXISTS quotes;");

    // $db->exec("CREATE TABLE quotes (
    $db->query("CREATE TABLE
  `quotes` (
    `id` int(11) NOT NULL DEFAULT 'AUTOINCREMENT',
    `head` text CHARACTER SET utf8 COLLATE utf8_general_ci,
    `quote` text CHARACTER SET utf8 COLLATE utf8_general_ci,
    `author` text CHARACTER SET utf8 COLLATE utf8_general_ci,
    `hits` int(11) DEFAULT NULL,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `category` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT 'General',
    UNIQUE KEY `PRIMARY` (`id`) USING HASH,
    SHARD KEY `__SHARDKEY` (`id`),
    SORT KEY `__UNORDERED` ()
  ) AUTOSTATS_CARDINALITY_MODE = INCREMENTAL AUTOSTATS_HISTOGRAM_MODE = CREATE AUTOSTATS_SAMPLING = ON SQL_MODE = 'STRICT_ALL_TABLES' ");

    // bulk insert into tables from json
    foreach ($data as $row) {
        try {
            // generate a random numeric ID for each quote
            $row['id'] = rand(100000, 999999);

            // $db->exec("INSERT INTO quotes (head, quote, author, hits) VALUES (
            $db->query("INSERT INTO quotes (id, head, quote, author, hits, category) VALUES (
                " . $row['id'] . ",
                '" . $row['head'] . "',
                '" . $row['quote'] . "',
                '" . $row['author'] . "',
                " . $row['hits'] . ",
                \"General\"
            )");
        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
        }

        // $db->query("INSERT INTO quotes (head, quote, author, hits) VALUES (
        //     '" . $row['head'] . "',
        //     '" . $row['quote'] . "',
        //     '" . $row['author'] . "',
        //     " . $row['hits'] . "
        // )");
    }

    $db->close();
}


/**
 * Import data from the json file to the DB
 *
 * @return void
 */
function import_data()
{

    $json = file_get_contents(__DIR__ . "/assets/quotes.json");

    $data = json_decode($json, true);

    $db = getDB();

    // bulk insert into tables from json
    foreach ($data as $row) {
        echo "Importing " . $row['id'] . "\n";

        $row['hits'] = random_int(0, 100);

        // cleanup the quote
        $row['quote'] = preg_replace('/\s*\.[\w-]+\s*{[^}]+}/', '', $row['quote']);

        // . print_r($row, true) . "\n";

        try {
            // generate a random numeric ID for each quote
            // $row['id'] = rand(100000, 999999);

            $sql_query = "INSERT INTO quotes (head, quote, author, hits) VALUES (
                '" . $row['head'] . "',
                '" . $row['quote'] . "',
                '" . $row['author'] . "', " . $row['hits'] . ")";

            // pepare the query
            $stmt = $db->prepare($sql_query);

            // execute the query
            $stmt->execute();


            // $db->exec("INSERT INTO quotes (head, quote, author, hits) VALUES (
            // $db->query($sql_query);


        } catch (Exception $e) {
            error_log("Error: " . $e->getMessage());
        }

        // $db->query("INSERT INTO quotes (head, quote, author, hits) VALUES (
        //     '" . $row['head'] . "',
        //     '" . $row['quote'] . "',
        //     '" . $row['author'] . "',
        //     " . $row['hits'] . "
        // )");
    }
}
