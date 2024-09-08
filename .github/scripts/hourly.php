<?php


error_reporting(E_ALL);

error_log("Current directory: " . __DIR__ . "/../..");

// change working directory
// chdir(__DIR__ . "/../..");

// current directory

/**
 * Selects a random quote from the DB, then updates the hits
 *
 * @return array | bool
 */
function get_random_quote()
{
        $quotes = json_decode(file_get_contents("assets/quotes.json"), true);
    return $quotes[array_rand($quotes)];
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


function update_readme()
{
    $the_quote = get_random_quote();

    if (!$the_quote) {
        error_log("No quote found");
        return false;
    } else {
        error_log("Quote found: " . $the_quote['id']);
    }

    $theChosen = '
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

    // get the README.md file
    $path = "./README.md";
    $readme = file_get_contents($path);
    if (!$readme) {
        error_log("No README.md file found");
        return false;
    }
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

// echo $msg;

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

