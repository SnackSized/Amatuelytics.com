<?php
header('Content-Type: application/json');

// 1. SECURE KEY REGISTRATION
// GitHub Actions will replace the placeholder line below with your actual API key during deployment.
$alpha_vantage_key = "###ALPHA_VANTAGE_KEY_PLACEHOLDER###";
$ticker = "RR.L";
$cache_file = __DIR__ . '/cache.json';
$cooldown_seconds = 20 * 60; // 20 minutes to average max 24 calls a day

// 2. CHECK TIME RULES (EUROPE/COPENHAGEN CET/CEST)
date_default_timezone_set('Europe/Copenhagen');
$current_time = time();
$hour = (int)date('G', $current_time);
$minute = (int)date('i', $current_time);

// Helper function to send cached data or error if cache doesn't exist
function respond_with_cache_or_error($cache_file, $status_msg) {
    if (file_exists($cache_file)) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        $cached_data['status'] = $status_msg . " (Viser gemt cache)";
        echo json_encode($cached_data);
    } else {
        echo json_encode([
            "error" => true,
            "message" => "Ingen cache tilgængelig uden for markedets åbningstid.",
            "status" => $status_msg
        ]);
    }
    exit;
}

// Check window boundary: Must be between 09:00 and 17:15. First call permitted at 09:15.
if ($hour < 9 || $hour > 17 || ($hour === 9 && $minute < 15) || ($hour === 17 && $minute > 15)) {
    respond_with_cache_or_error($cache_file, "Uden for markedsvindue (09:15 - 17:15 CET)");
}

// 3. EVALUATE REFRESH COOLDOWN
if (file_exists($cache_file)) {
    $file_age = $current_time - filemtime($cache_file);
    if ($file_age < $cooldown_seconds) {
        respond_with_cache_or_error($cache_file, "Cached");
    }
}

// 4. FETCH FRESH DATA FROM ALPHA VANTAGE
$url = "https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol=" . urlencode($ticker) . "&apikey=" . $alpha_vantage_key;

// Use cURL or file_get_contents with a timeout fallback
$options = array('http' => array('timeout' => 10, 'header' => "User-Agent: PHP\r\n"));
$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === FALSE) {
    respond_with_cache_or_error($cache_file, "Netværksfejl ved API-kald");
}

$data = json_decode($response, true);

if (!isset($data['Global Quote']) || !isset($data['Global Quote']['05. price'])) {
    respond_with_cache_or_error($cache_file, "API-grænse nået eller fejl-respons");
}

$quote = $data['Global Quote'];

// Format and package structural values
$fresh_payload = [
    "price" => (float)$quote['05. price'],
    "change" => (float)$quote['09. change'],
    "pct" => (float)str_replace('%', '', $quote['10. change percent']),
    "marketTime" => isset($quote['07. latest trading day']) ? $quote['07. latest trading day'] : date('Y-m-d'),
    "status" => "Live Update"
];

// Write changes back to your one.com storage allocation
file_put_contents($cache_file, json_encode($fresh_payload));

// Return fresh data structure to JavaScript client
echo json_encode($fresh_payload);