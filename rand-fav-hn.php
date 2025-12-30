<?php
/**
 * Random HN Favorite Article Redirector
 * Fetches all favorited articles from a Hacker News user and redirects to a random one.
 */

// Configuration from GET parameters
$username = $_GET['id'] ?? 'arnok';
$debug = isset($_GET['debug']);

// Fetch all favorited articles
function fetchFavorites(string $username, bool $debug): array {
    $articles = [];
    $pageUrls = [];
    $baseUrl = 'https://news.ycombinator.com/favorites?id=' . urlencode($username);
    $nextUrl = $baseUrl;

    while ($nextUrl) {
        $pageUrls[] = $nextUrl;
        $html = @file_get_contents($nextUrl);
        if ($html === false) {
            if ($debug) {
                echo "Failed to fetch: $nextUrl\n";
            }
            break;
        }

        // Parse HTML with DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        // Find all article links inside titleline spans
        // XPath: //span[@class='titleline']/a[1]/@href (first <a> inside each titleline span)
        $nodes = $xpath->query("//span[@class='titleline']/a[1]/@href");

        foreach ($nodes as $node) {
            $url = $node->nodeValue;
            // Convert internal HN links to full URLs
            if (strpos($url, 'item?id=') === 0) {
                $url = 'https://news.ycombinator.com/' . $url;
            }
            $articles[] = $url;
        }

        if ($debug) {
            echo "Page " . count($pageUrls) . ": " . $nodes->length . " articles found\n";
        }

        // Check for "More" link to get next page
        $moreNodes = $xpath->query("//a[@class='morelink']/@href");
        if ($moreNodes->length > 0) {
            $nextUrl = 'https://news.ycombinator.com/' . htmlspecialchars_decode($moreNodes[0]->nodeValue);
        } else {
            $nextUrl = null;
        }
    }

    if ($debug) {
        echo "\n=== Summary ===\n";
        echo "Pages fetched: " . count($pageUrls) . "\n";
        foreach ($pageUrls as $i => $url) {
            echo "  Page " . ($i + 1) . ": $url\n";
        }
        echo "\nTotal articles: " . count($articles) . "\n";
        echo "\n=== All Articles ===\n";
        foreach ($articles as $i => $url) {
            echo ($i + 1) . ". $url\n";
        }
        echo "\n";
    }

    return $articles;
}

// Main execution
if ($debug) {
    header('Content-Type: text/plain');
    echo "Debug mode enabled\n";
    echo "Username: $username\n\n";
}

$articles = fetchFavorites($username, $debug);

if (empty($articles)) {
    http_response_code(404);
    die('No favorited articles found for user: ' . htmlspecialchars($username));
}

// Select random article
$randomArticle = $articles[array_rand($articles)];

if ($debug) {
    echo "=== Random Selection ===\n";
    echo "Selected: $randomArticle\n";
} else {
    header('Location: ' . $randomArticle);
}
exit;
