<?php

/**
 * Created by the Mastermind himself
 *
 * @package ma-qeal
 * @subpackage index.php
 * @since ma-qeal 1.0
 *
 */


// CLI response
$args = getopt("g:t:i:e:c:u:cc");

if (empty($args)) {

    error_log("No args passed! 🚧 Going normal...");

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
    $ReadMeA = update_readme(__DIR__ . "/README.md");

    echo "Daily quote updated.\n"
        . "🎯 Updating Todoist next...\n";

    $Todoist = retrieveTodoistStats($ReadMeA);

    echo "🎯 Todoist stats updated.\n";

    // echo "Usage: php index.php [options]\n"
    // . print_r($args, true) . "\n";

    // die( $_SERVER['REQUEST_URI'] . " - No arguments passed! 🚧");
    // die("No arguments passed");
} else {

    if (isset($args['t'])) {

        // get a random 5 quotes from the DB
        $db = getDB();

        $result = $db->query("SELECT * FROM `quotes` LIMIT 5");

        $five_quotes = [];

        while ($row = $result->fetch_assoc()) {
            $five_quotes[] = $row;
        }

        // die( print_r($five_quotes, true) . "\n");

        die($db->lastErrorMsg() . "\n"
            . $db->querySingle("SELECT COUNT(*) FROM `quotes`") . "\n");

        $the_quote = get_random_quote();

        if (!$the_quote) {
            error_log("No quote found");
            die("No quote found");
        }

        $msg = "Quote of the day: " . $the_quote['quote'] . " - " . $the_quote['author'];
    }

    if (isset($args['i'])) {

        // truncate first
        // $db = getDB();
        // $db->exec("DELETE FROM quotes");
        // $db->exec("DELETE FROM todoist");

        // import data
        import_data();
        die("Data imported");
    }

    if (isset($args['cc'])) {
        makeDBClone();
        die("DB cloned");
    }

    if (isset($args['c'])) {
        extract_quotes();
        create_table();

        // the total number of quotes
        $db = getDB();
        $result = $db->query("SELECT COUNT(*) FROM `quotes`");
        // $row = $result->fetchArray(SQLITE3_ASSOC);
        $row = $result->fetch_assoc();

        $db->close();

        $stats = "Total quotes: " . $row['COUNT(*)'] . "\n";
        die("DB created. 🚧 " . $stats);
    }

    if (isset($args['u']) && $args['u'] == "update") {
        update_wiki_url();
        die("Wiki URL updated");
    }
}



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
 * Undocumented function
 *
 * @return object
 */
function getDB()
{
    return connectDB();
}

#endregion

/**
 * Fetches the data from the Todoist API
 *
 * @param string $token
 * @return string
 */
function updateReadmeTodoist($data, $ReadMeChunk)
{
    // extract data from the response
    $karma = $data['karma'];
    $completed_count = $data['completed_count'];
    $days_items = $data['days_items'][0]['total_completed'];
    $goals = $data['goals'];
    $week_items = $data['week_items'][0]['total_completed'];

    // format the extracted data
    $newContent = "";
    $newContent .= "🏆  **" . number_format($karma) . "** Karma Points\n";
    $newContent .= "🌸  Completed **" . $days_items . "** tasks today\n";

    $newContent .= "🗓  Completed **" . $week_items . "** tasks this week\n";

    $newContent .= "✅  Completed **" . number_format($completed_count) . "** tasks so far\n";
    $newContent .= "⏳  Longest streak is **" . $goals['max_daily_streak']['count'] . "** days\n";

    // add the updated date
    $date = date('l, F j, Y');
    $newContent .= "📅  Last updated: **" . $date . "**";

    // read the existing README content
    // $readmeContent = file_get_contents('README.md');

    // replace the content between <!-- TODO-IST:START --> and <!-- TODO-IST:END -->
    $startTag = '<!-- TODO-IST:START -->';
    $endTag = '<!-- TODO-IST:END -->';
    $startTagPos = strpos($ReadMeChunk, $startTag);
    $endTagPos = strpos($ReadMeChunk, $endTag);

    if ($startTagPos !== false && $endTagPos !== false) {
        $newReadmeContent = substr($ReadMeChunk, 0, $startTagPos + strlen($startTag))
            . "\n" . $newContent . "\n"
            . substr($ReadMeChunk, $endTagPos);
        file_put_contents('README.md', $newReadmeContent);
    }

    return $newContent;
}

/**
 * Iterate thru the quotes table and str_replace each href= with href={{ WIKI_URL }}/wiki
 *
 * @return void
 */
function update_wiki_url()
{
    $db = getDB();

    $result = $db->query("SELECT * FROM `quotes`");

    // while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    while ($row = $result->fetch_assoc()) {

        // $db->exec("UPDATE quotes SET quote = '" . str_replace("/wiki/", "https://ar.wikipedia.org/wiki/", $row['quote']) . "' WHERE id = " . $row['id']);

        $db->query("UPDATE quotes SET quote = '" . str_replace("/wiki/", "https://ar.wikipedia.org/wiki/", $row['quote']) . "' WHERE id = " . $row['id']);

        // echo affected rows
        echo "Edited " . $db->affected_rows . "\n";
    }

    $db->close();
}

function extract_quotes()
{

    $html = file_get_contents(__DIR__ . "/quotes.html");

    // Split the HTML content based on <hr> tag
    $divs = explode("<hr>", $html);

    // Remove the last element
    $footerStats = array_pop($divs);
    $footerStats = explode("<!--", $footerStats);
    array_walk($footerStats, function (&$value) {
        $value = str_replace("-->", "", $value);
        $value = trim($value);
        $value = explode("\n", $value);
    });

    file_put_contents(__DIR__ . "/assets/footerStats.json", json_encode($footerStats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    // error_log("Footer stats: " . $footerStats);

    $documents = [];
    $counter = 0;

    foreach ($divs as $div) {
        // Remove HTML tags
        $div = strip_tags($div, "<a>");

        // Remove empty lines
        // $div = trim($div, "\t\n");

        // Remove blank spaces
        // $div = str_replace("&nbsp;", "", $div);

        // preg match and remove
        $div = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $div);

        if (empty($div)) {
            die("Empty div found");
        }

        // echo $div . "---\n";

        $parts = explode("“", $div);

        $ph = [];

        $divHead = explode("”", $parts[0])[0];

        $ph['id'] = $counter++;

        $ph['head'] = trim($divHead);

        $quote = trim(explode("”", $parts[0])[1]) ?? error_log("No quote found");

        $ph['quote'] = $quote;

        $ph['author'] = trim($parts[1]);

        $ph['hits'] = 0;

        $documents[] = $ph;
    }

    // Convert to JSON
    $json_data = json_encode($documents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Save to a file
    file_put_contents(__DIR__ . "/assets/quotes.json", $json_data);
}

// create_table();

// extract_quotes();


// update_wiki_url();

/**
 * Hits the log for every new Quote of the day
 *
 * @param string $log
 * @return void
 */
function hit_log($log)
{
    $log = date('Y-m-d H:i:s') . " - " . $log . "\n";

    file_put_contents(__DIR__ . "/assets/DEPLOYMENT.log", $log, FILE_APPEND);
}

/**
 * Takes the random quote as a param, saves it to the DB along with todays date
 * as a unique ID
 *
 *
 * @param array $the_quote
 * @return array $the_quote
 */
function save_quote_of_the_day($the_quote)
{
    $db = getDB();

    $db->query("INSERT INTO quotes (quote_id, date) VALUES (
        " . $the_quote['id'] . ",
        '" . date('Y-m-d') . "'
    )");

    $db->close();

    return $the_quote;
}

// array_walk($the_quote, function (&$value) {
// $value = str_replace("/wiki/", "{{ WIKI_URL }}/wiki/", $value);
// $value = str_replace("/wiki/", "https://ar.wikipedia.org/wiki/", $value);
// });

function get_random_quote_of_the_day()
{

    $db = getDB();

    $result = $db->query("SELECT * FROM `quotes` ORDER BY RANDOM() LIMIT 1");

    // $row = $result->fetchArray(SQLITE3_ASSOC);
    $row = $result->fetch_assoc();

    // $db->exec("UPDATE quotes SET hits = hits + 1 WHERE id = " . $row['id']);
    $db->query("UPDATE quotes SET hits = hits + 1 WHERE id = " . $row['id']);

    $db->close();

    return $row;
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


/**
 * Updates the README.md file with the new quote
 *
 * @param string $ReadMe - the README.md chunks
 * @return void
 */
function retrieveTodoistStats($ReadMe)
{
    $TodoistApiKey = isset($_ENV['TODOIST_API_KEY']) ? $_ENV['TODOIST_API_KEY'] : die("No Todoist API key found");

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.todoist.com/sync/v9/completed/get_stats",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "UTF-8",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer " . $TodoistApiKey,
            "Content-Type: application/json"
        ),
    ));

    $response = curl_exec($curl);

    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        error_log("cURL Error #:" . $err);
    } else {
        // echo $response;
    }

    $data = json_decode($response, true);

    $ReadMeB = updateReadmeTodoist($data, $ReadMe);

    echo "Todoist stats updated 🚧 🎉 \n";

    return $ReadMeB;
}
// update_readme(__DIR__ . "/../doy-dash/README.md");

/**
 * To add a new quote to the DB
 *
 * @param string $head
 * @param string $quote
 * @param string $author
 * @return void
 */
function add_quote($head, $quote, $author)
{
    $db = getDB();

    $db->query("INSERT INTO quotes (head, quote, author, hits) VALUES (
        '" . $head . "',
        '" . $quote . "',
        '" . $author . "',
        0
    )");

    error_log("New quote added: " . $db->affected_rows . " ID: " . $db->insert_id);

    $db->close();

    return true;
}

// TODO: Add a new quote via the webhook
// TODO: Add a new quote via the CLI


// List GitHub issues via the CLI
// https://docs.github.com/en/rest/reference/issues#list-repository-issues
// https://docs.github.com/en/free-pro-team@latest/rest/issues/issues?apiVersion=2022-11-28#list-repository-issues

/**
 * List all the issues from the GitHub repo
 * https://docs.github.com/en/rest/reference/issues#list-repository-issues
 *
 * @return array $issues - the issues array
 */
function list_issues()
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.github.com/repos/aldoyh/doy-dash/issues?state=open&sort=created&direction=desc&per_page=100&page=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Accept: application/vnd.github.v3+json",
            "Authorization: token " . $GLOBALS['GITHUB_TOKEN'],
            "Content-Type: application/json"
        ),
    ));

    $response = curl_exec($curl);

    die(print_r($response, true) . "\n");

    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        error_log("cURL Error #:" . $err);
    } else {
        // echo $response;
    }

    $data = json_decode($response, true);

    // die(print_r($data, true));

    $issues = [];

    foreach ($data as $issue) {
        $issues[] = [
            "title" => $issue['title'],
            "url" => $issue['html_url'],
            "labels" => $issue['labels'],
            "created_at" => $issue['created_at'],
            "updated_at" => $issue['updated_at'],
            "comments" => $issue['comments'],
            "body" => $issue['body'],
            "user" => $issue['user']['login'],
            "user_url" => $issue['user']['html_url'],
            "user_avatar" => $issue['user']['avatar_url'],
        ];
    }

    // die(print_r($issues, true));

    return $issues;
}



function list_gh_issues()
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.github.com/repos/aldoyh/doy-dash/issues?state=open&sort=created&direction=desc&per_page=100&page=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "User-Agent: " . CURLOPT_USERAGENT,
            "Accept: application/vnd.github.v3+json",
            "Authorization: Bearer " . $GLOBALS['GITHUB_TOKEN'],
            "Content-Type: application/json",
        ),
    ));

    $response = curl_exec($curl);



    die(print_r($response, true) . "\n");

    if (!$response) {

        error_log("No response from GitHub");
        return false;
    }

    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        error_log("cURL Error #:" . $err);
    } else {
        // echo $response;
    }

    $data = json_decode($response, true);

    $issues = [];

    foreach ($data as $issue) {
        $issues[] = [
            "title" => $issue['title'],
            "url" => $issue['html_url'],
            "labels" => $issue['labels'],
            "created_at" => $issue['created_at'],
            "updated_at" => $issue['updated_at'],
            "comments" => $issue['comments'],
            "body" => $issue['body'],
            "user" => $issue['user']['login'],
            "user_url" => $issue['user']['html_url'],
            "user_avatar" => $issue['user']['avatar_url'],
        ];
    }

    return $issues;
}

// echo json_encode(
//     list_gh_issues(),
//     JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
// );

