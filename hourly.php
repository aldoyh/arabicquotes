<?php


error_reporting(E_ALL);


/**
 * Selects a random quote from the DB, then updates the hits
 *
 * @return array | bool
 */
function get_random_quote()
{
    $db = getDB();

    $result = $db->query("SELECT * FROM `quotes` ORDER BY RAND() LIMIT 1");

    if (!$result) {
        error_log("No result found");
        return false;
    }

    // $row = $result->fetchArray(SQLITE3_ASSOC);
    $row = $result->fetch_assoc();

    // die(print_r($row, true) . "\n");

    $db->query("UPDATE `quotes` SET hits = hits + 1 WHERE id = " . $row['id']);

    $db->close();

    return $row;
}

#region Database

/**
 * Connect to the DB MySQL PlanetScale
 *
 *
 * @return void
 */
function connectDB()
{

    // TODO: Move these credentials to GitHub Secrets
    // $db_info_dev = getenv('DB_INFO_DEV') ? getenv('DB_INFO_DEV') : die("No DB info found");
    $db_info_prod = getenv('DB_INFO');

    if (!$db_info_prod) {
        error_log("No DB info found");
        return false;
    }

    $db_info = json_decode($db_info_prod, false);

    $db = mysqli_init();

    $db->ssl_set(
        getenv('MYSQL_SSL_KEY'),
        null,
        null,
        null,
        null
    );

    $db->real_connect($db_info->host, $db_info->username, $db_info->password, $db_info->database, $db_info->port, null, MYSQLI_CLIENT_SSL);

    if ($db->connect_errno) {
        error_log("Failed to connect to MySQL: (" . $db->connect_errno . ") " . $db->connect_error);
        return false;
    }

    $db->query("SET NAMES utf8mb4");
    $db->query("SET CHARACTER SET utf8mb4");
    $db->query("SET SESSION collation_connection = 'utf8mb4_unicode_ci'");

    return $db;
}


/**
 * Hits the log for every new Quote of the day
 *
 * @param string $log
 * @return void
 */
function hit_log($log)
{
    $log = date('Y-m-d H:i:s') . " - " . $log . "\n";

    file_put_contents("assets/DEPLOYMENT.log", $log, FILE_APPEND);
}


/**
 * Undocumented function
 *
 * @return object
 */
function getDB()
{
    return connectDB();
}


function update_readme($path)
{


    $the_quote = get_random_quote();

    if (!$the_quote) {
        error_log("No quote found");
        return false;
    }

    $theChosen =
        '
<div class="flex justify-center mt-16 px-0 sm:items-center sm:justify-between quote-of-the-day">
    <div class="flex flex-col items-center w-full max-w-xl px-4 py-8 mx-auto bg-white rounded-lg shadow dark:bg-gray-800 sm:px-6 md:px-8 lg:px-10">
        <div class="text-center text-sm text-gray-500 dark:text-gray-400 sm:text-right">
            <div class="flex items-center gap-4">
                <div class="quote-header">
                    <p class="quote-date" style="font-size: smaller;">اليوم: ' . date('l jS \of F Y - H:i') . ' 🎯 المشاهدات: ' . $the_quote['hits'] . '</p>
                </div>
                <div class="ml-4 text-center text-sm text-gray-500 dark:text-gray-400 sm:text-right sm:ml-0 quote-content" dir="rtl">
                    <h1 class="quote-text">' . $the_quote['quote'] . '</h1>
                </div>
                <div class="quote-footer">
                    <p class="quote-author">' . $the_quote['author'] . '</p>
                </div>
            </div>
        </div>
    </div>
</div>
';

    $readme = file_get_contents($path);

    $readme = preg_replace("/<!-- QUOTE:START -->.*<!-- QUOTE:END -->/s", "<!-- QUOTE:START -->\n" . $theChosen . "\n<!-- QUOTE:END -->", $readme);

    // save the new README.md file
    file_put_contents($path, $readme);

    hit_log($the_quote['id'] . " - " . $the_quote['hits']);

    return $the_quote;
}



$the_quote = get_random_quote();

if (!$the_quote) {
    error_log("No quote found");
    die("No quote found");
}

$msg = "Quote of the day: " . $the_quote['id'] . " - " . $the_quote['hits'] . $the_quote['author'];

echo $msg;

/**
 * Ignite the function to update the README.md, then pass it to
 *
 *
 *
 *
 * 📮 🚧
 *
 */
$ReadMeA = update_readme("README.md");

echo "Daily quote updated.\n";

#endregion

// flatten a string to be used in a URL
function slugify($text)
{
    // replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // trim
    $text = trim($text, '-');

    // remove duplicate -
    $text = preg_replace('~-+~', '-', $text);

    // lowercase
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}

// TODO: Move these credentials to GitHub Secrets

// delete the ssl cert file
// unlink("assets/ssl_ca.pem");
// unlink("assets/ssl_cert.pem");

