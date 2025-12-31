<?php
/**
 * Random HN Favorite Article Redirector
 * Uses binary search to efficiently find total favorites count,
 * then redirects to a random article or its comments.
 */

// Configuration from GET parameters
$username = $_GET['id'] ?? 'arnok';
$target = $_GET['target'] ?? 'comments';
$debug = isset($_GET['debug']);

// Validate username: 2-15 chars, only letters, digits, dashes, underscores
if (!preg_match('/^[a-zA-Z0-9_-]{2,15}$/', $username)) {
    http_response_code(400);
    die('Invalid username. Must be 2-15 characters, containing only letters, digits, dashes, and underscores.');
}

// Validate target: only 'article' or 'comments' allowed
if (!in_array($target, ['article', 'comments'], true)) {
    http_response_code(400);
    die('Invalid target. Must be "article" or "comments".');
}

// HTML cache to avoid duplicate requests
$htmlCache = [];

/**
 * Fetch a page with caching
 */
function fetchPage(string $url, array &$cache): ?string {
    if (isset($cache[$url])) {
        return $cache[$url];
    }
    $html = @file_get_contents($url);
    if ($html === false) {
        return null;
    }
    $cache[$url] = $html;
    return $html;
}

/**
 * Build favorites URL for a specific page
 */
function buildUrl(string $username, int $page): string {
    $url = 'https://news.ycombinator.com/favorites?id=' . urlencode($username);
    if ($page > 1) {
        $url .= '&p=' . $page;
    }
    return $url;
}

/**
 * Parse a page and return article count and whether there's a next page
 */
function parsePage(string $html): array {
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);

    // Count articles
    $storyRows = $xpath->query("//tr[contains(@class, 'athing')]");
    $articleCount = $storyRows->length;

    // Check for "More" link
    $moreNodes = $xpath->query("//a[@class='morelink']");
    $hasMore = $moreNodes->length > 0;

    return ['count' => $articleCount, 'hasMore' => $hasMore];
}

/**
 * Extract articles from a page's HTML
 */
function extractArticles(string $html): array {
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);

    $items = [];
    $storyRows = $xpath->query("//tr[contains(@class, 'athing')]");

    foreach ($storyRows as $row) {
        $itemId = $row->getAttribute('id');
        $linkNode = $xpath->query(".//span[contains(@class, 'titleline')]/a[1]/@href", $row);
        if ($linkNode->length > 0) {
            $url = $linkNode[0]->nodeValue;
            if (strpos($url, 'item?id=') === 0) {
                $url = 'https://news.ycombinator.com/' . $url;
            }
            $items[] = [
                'id' => $itemId,
                'article' => $url,
                'comments' => 'https://news.ycombinator.com/item?id=' . $itemId,
            ];
        }
    }

    return $items;
}

/**
 * Find random favorite using binary search
 */
function findRandomFavorite(string $username, string $target, bool $debug, array &$cache): ?array {
    // Step 1: Fetch first page to get items per page and check if there's more
    $url1 = buildUrl($username, 1);
    $html1 = fetchPage($url1, $cache);

    if ($html1 === null) {
        http_response_code(502);
        die('Failed to fetch favorites from Hacker News.');
    }

    $page1 = parsePage($html1);
    $itemsPerPage = $page1['count'];

    if ($debug) {
        echo "Page 1: {$page1['count']} articles, hasMore: " . ($page1['hasMore'] ? 'yes' : 'no') . "\n";
    }

    if ($itemsPerPage === 0) {
        return null;
    }

    // If no more pages, select from page 1
    if (!$page1['hasMore']) {
        $articles = extractArticles($html1);
        $randomArticle = $articles[array_rand($articles)];
        if ($debug) {
            echo "\nOnly 1 page with {$itemsPerPage} articles\n";
            echo "Selected article #{$randomArticle['id']}\n";
        }
        return $randomArticle;
    }

    // Step 2: Exponential search to find upper bound
    if ($debug) echo "\n=== Exponential Search ===\n";

    $lastGoodPage = 1;
    $probe = 2;

    while (true) {
        $urlProbe = buildUrl($username, $probe);
        $htmlProbe = fetchPage($urlProbe, $cache);

        if ($htmlProbe === null) {
            http_response_code(502);
            die('Failed to fetch favorites from Hacker News.');
        }

        $pageProbe = parsePage($htmlProbe);

        if ($debug) {
            echo "Page $probe: {$pageProbe['count']} articles\n";
        }

        if ($pageProbe['count'] === 0) {
            // Overshot - upper bound found
            break;
        }

        $lastGoodPage = $probe;

        if (!$pageProbe['hasMore']) {
            // This is the last page
            break;
        }

        $probe *= 2;
    }

    // Step 3: Binary search between lastGoodPage and probe to find actual last page
    if ($debug) echo "\n=== Binary Search ===\n";

    $low = $lastGoodPage;
    $high = $probe;
    $lastPage = $lastGoodPage;
    $lastPageCount = 0;

    while ($low <= $high) {
        $mid = (int)(($low + $high) / 2);
        $urlMid = buildUrl($username, $mid);
        $htmlMid = fetchPage($urlMid, $cache);

        if ($htmlMid === null) {
            http_response_code(502);
            die('Failed to fetch favorites from Hacker News.');
        }

        $pageMid = parsePage($htmlMid);

        if ($debug) {
            echo "Checking page $mid: {$pageMid['count']} articles\n";
        }

        if ($pageMid['count'] === 0) {
            // Overshot
            $high = $mid - 1;
        } else {
            // Valid page
            $lastPage = $mid;
            $lastPageCount = $pageMid['count'];

            if (!$pageMid['hasMore']) {
                // Found the last page
                break;
            }

            $low = $mid + 1;
        }
    }

    // Step 4: Calculate total articles
    $totalArticles = ($lastPage - 1) * $itemsPerPage + $lastPageCount;

    if ($debug) {
        echo "\n=== Summary ===\n";
        echo "Items per page: $itemsPerPage\n";
        echo "Last page: $lastPage\n";
        echo "Items on last page: $lastPageCount\n";
        echo "Total articles: $totalArticles\n";
        echo "Pages fetched: " . count($cache) . "\n";
    }

    // Step 5: Select random article number (1-indexed)
    $randomIndex = mt_rand(1, $totalArticles);

    // Step 6: Calculate which page it's on and position within page
    $targetPage = (int)ceil($randomIndex / $itemsPerPage);
    $positionOnPage = $randomIndex - ($targetPage - 1) * $itemsPerPage - 1; // 0-indexed

    if ($debug) {
        echo "\n=== Random Selection ===\n";
        echo "Random index: $randomIndex of $totalArticles\n";
        echo "Target page: $targetPage\n";
        echo "Position on page: " . ($positionOnPage + 1) . "\n";
    }

    // Step 7: Fetch target page (may be cached) and extract article
    $targetUrl = buildUrl($username, $targetPage);
    $targetHtml = fetchPage($targetUrl, $cache);

    if ($targetHtml === null) {
        http_response_code(502);
        die('Failed to fetch favorites from Hacker News.');
    }

    $articles = extractArticles($targetHtml);

    if ($positionOnPage >= count($articles)) {
        if ($debug) echo "Position out of bounds, selecting last article\n";
        $positionOnPage = count($articles) - 1;
    }

    return $articles[$positionOnPage];
}

// Main execution
if ($debug) {
    header('Content-Type: text/plain');
    echo "Debug mode enabled\n";
    echo "Username: $username\n";
    echo "Target: $target\n\n";
}

$randomItem = findRandomFavorite($username, $target, $debug, $htmlCache);

if ($randomItem === null) {
    http_response_code(404);
    die('No favorited articles found for user: ' . htmlspecialchars($username));
}

$redirectUrl = $randomItem[$target];

if ($debug) {
    echo "\n=== Result ===\n";
    echo "Item ID: " . $randomItem['id'] . "\n";
    echo "Article: " . $randomItem['article'] . "\n";
    echo "Comments: " . $randomItem['comments'] . "\n";
    echo "Redirecting to ($target): $redirectUrl\n";
} else {
    header('Location: ' . $redirectUrl);
}
exit;
