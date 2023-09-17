<?php


$GLOBALS['cwd_now'] = __DIR__ . "/../../";

error_log("CWD: " . $GLOBALS['cwd_now']);

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

    die(print_r($row, true) . "\n");

    // $db->exec("UPDATE quotes SET hits = hits + 1 WHERE id = " . $row['id']);
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
    $db_info_dev = isset($_ENV['DB_INFO_DEV']) ? $_ENV['DB_INFO_DEV'] : die("No DB info found");
    $db_info_prod = isset($_ENV['DB_INFO_PROD'])? $_ENV['DB_INFO_PROD'] : die("No DB info found");

    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] == "dev") {
        $db_info = $db_info_dev;
    } else {
        $db_info = $db_info_prod;
    }

    $db_info = json_decode($db_info, true);

    $db = mysqli_init();

    // $db->ssl_set(
    //     $_ENV['MYSQL_SSL_KEY'],
    //     $_ENV['MYSQL_SSL_CERT'],
    //     $_ENV['MYSQL_SSL_CA'],
    //     $_ENV['MYSQL_SSL_CAPATH'],
    //     $_ENV['MYSQL_SSL_CIPHER']
    // );

    $db->real_connect($db_info["DB_HOST"], $db_info["DB_USERNAME"], $db_info["DB_PASSWORD"], $db_info["DB_NAME"]);

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

    file_put_contents($GLOBALS['cwd_now'] . "/assets/DEPLOYMENT.log", $log, FILE_APPEND);
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

$msg = "Quote of the day: " . $the_quote['quote'] . " - " . $the_quote['author'];

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
$ReadMeA = update_readme($GLOBALS['cwd_now'] . "/README.md");

echo "Daily quote updated.\n"
    . "🎯 Updating Todoist next...\n";