<?php

/**
 * Created by the Mastermind himself
 *
 * @package ma-qeal
 * @subpackage index.php
 * @since ma-qeal 1.0
 *
 */

error_reporting(E_ALL);
error_log("Current directory: " . __DIR__ . "/../..");


/**
 * Selects a random quote from the JSON file
 *
 * @return array|null
 */
function getRandomQuote()
{
    $quotes = json_decode(file_get_contents("assets/quotes.json"), true);
    if (!$quotes) {
        error_log('Error opening json file.');
    }
    return $quotes ? $quotes[array_rand($quotes)] : null;
}

/**
 * Logs the new Quote of the day
 *
 * @param string $logMessage
 * @return void
 */
function logQuoteUpdate($logMessage)
{
    $logEntry = date('Y-m-d H:i:s') . " - " . $logMessage . "\n";
    file_put_contents("assets/DEPLOYMENT.log", $logEntry, FILE_APPEND);
}

/**
 * Updates the README.md file with a new quote
 *
 * @return array|false
 */
function updateReadme()
{
    $selectedQuote = getRandomQuote();

    if (!$selectedQuote) {
        error_log("No quote found");
        return false;
    }
    $selectedQuote['hits'] = $selectedQuote['hits'] + 1;
    // remove new lines from the quote
    $selectedQuote['quote'] = str_replace("\n", " ", $selectedQuote['quote']);

    $quoteHtml = generateQuoteHtml($selectedQuote);

    $quoteMarkdown = PHP_EOL . "# " . $selectedQuote['quote'] . PHP_EOL . PHP_EOL . "- " . $selectedQuote['author']
        . PHP_EOL . PHP_EOL;
    if (isset($selectedQuote['image'])) {
        $quoteMarkdown .= PHP_EOL . "![Quote Image](" . $selectedQuote['image'] . ")";
    }

    $readmePath = __DIR__ . "/README.md";
    if (!file_exists($readmePath)) {
        error_log("README.md file not found");
        return false;
    }
    $readmeContent = file_get_contents($readmePath);

    if (!$readmeContent) {
        error_log("No README.md file found");
        return false;
    }

    $updatedReadme = preg_replace(
        "/<!-- QUOTE:START -->[\s\S]*<!-- QUOTE:END -->/",
        // "<!-- QUOTE:START -->\n" . $quoteHtml . "\n<!-- QUOTE:END -->",
        "<!-- QUOTE:START -->\n" . $quoteMarkdown . "\n<!-- QUOTE:END -->",
        $readmeContent
    );

    file_put_contents($readmePath, $updatedReadme);
    logQuoteUpdate($selectedQuote['id'] . " - " . $selectedQuote['hits']);



    return $selectedQuote;
}

/**
 * Creates a new GitHub issue with a body content made from the passed quoteHtml
 * 
 * @param string
 * @response boolean
 */


/**
 * Generates HTML for the quote
 *
 * @param array $quote
 * @return string
 */
function generateQuoteHtml($quote)
{
    return '
    <div class="flex justify-center mt-16 px-0 sm:items-center sm:justify-between quote-of-the-day">
        <div class="flex flex-col items-center w-full max-w-xl px-4 py-8 mx-auto bg-white rounded-lg shadow dark:bg-gray-800 sm:px-6 md:px-8 lg:px-10">
            <div class="text-center text-sm text-gray-500 dark:text-gray-400 sm:text-right">
                <div class="flex items-center gap-4">
                    <div class="quote-header">
                        <p class="quote-date" style="font-size: smaller;">اليوم: ' . date('l jS \of F Y - H:i') . ' 🎯 المشاهدات: ' . $quote['hits'] . '</p>
                    </div>
                    <div class="ml-4 text-center text-sm text-gray-500 dark:text-gray-400 sm:text-right sm:ml-0 quote-content" dir="rtl">
                        <h1 class="quote-text">' . $quote['quote'] . '</h1>
                    </div>
                    <div class="quote-footer">
                        <p class="quote-author">' . $quote['author'] .
        '</p>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Converts a string to a URL-friendly slug
 *
 * @param string $text
 * @return string
 */
function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);

    return empty($text) ? 'n-a' : $text;
}

/**
 * Fetches a random quote from Wikiquote
 *
 */
function fetchFromWiki()
{
    $html = fetchRaw();
    if (!$html) {
        return null;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $quoteNodes = $xpath->query('//div[@class="mw-parser-output"]/ul/li');
    if ($quoteNodes->length === 0) {
        error_log("No quote nodes found in Heystack!");
        return null;
    }

    $totalFound = $quoteNodes->length;
    error_log("Total quotes found: " . $totalFound);

    $randomQuote = $quoteNodes->item(rand(0, $quoteNodes->length - 1))->textContent;

    $authorNode = $xpath->query('//h1[@id="firstHeading"]');
    $author = $authorNode->length > 0 ? $authorNode->item(0)->textContent : 'Unknown';

    return [
        'quote' => trim($randomQuote),
        'author' => trim($author)
    ];
}



function fetchRandomWikiQuote()
{
    $htmlChunk = fetchFromWiki();
    if (!$htmlChunk) {
        return null;
    }

    $quote = $htmlChunk['quote'];
    $author = $htmlChunk['author'];

    return [
        'quote' => $quote,
        'author' => $author
    ];
}

// fetch an html page from a url
function fetchRaw()
{
    $url = "https://ar.wikiquote.org/wiki/%D8%A7%D9%84%D8%B5%D9%81%D8%AD%D8%A9_%D8%A7%D9%84%D8%B1%D8%A6%D9%8A%D8%B3%D9%8A%D8%A9";
    if (!$html = file_get_contents($url)) {
        return null;
    }

    return $html;
}

// Main execution
$updatedQuote = updateReadme();

if (!$updatedQuote) {
    echo "Failed to update daily quote.\n";

    echo "Fetching a random quote from Wikipedia...\n";
    $wikiQuote = fetchRandomWikiQuote();
    if (!$wikiQuote) {
        echo "Failed to fetch a random quote from Wikipedia.\n";
    }
} else {
    echo "✅ Daily quote updated successfully.\n";
    echo "Quote ID: " . $updatedQuote['id'];
}
