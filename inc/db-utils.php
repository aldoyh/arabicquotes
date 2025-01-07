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
    $db = new SQLite3(dirname(__DIR__) . "/quotes.db");

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

    $db = getDBSQLite();

    // Drop the table if it exists
    $db->query("DROP TABLE IF EXISTS quotes;");

    $db->query("CREATE TABLE quotes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        head TEXT,
        quote TEXT,
        author TEXT,
        hits INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        category TEXT DEFAULT 'General'
    
  )") or die("Failed to create table");

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
