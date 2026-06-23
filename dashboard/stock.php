<?php
header('Content-Type: application/json');

// 1. CONFIGURATION
$alpha_vantage_key = "###ALPHA_VANTAGE_KEY_PLACEHOLDER###";
$ticker = "RR.L";
$cache_file = __DIR__ . '/cache.json';
$cooldown_seconds = 20 * 60; // 20 minutter

// 2. TIME REGULATION
date_default_timezone_set('Europe/Copenhagen');
$current_time = time();

// Tjek om du bevidst prøver at tvinge en opdatering igennem (?force=true)
$force_update = (isset($_GET['force']) && $_GET['force'] === 'true');

// Helper function
function respond_with_cache($file, $status_msg) {
    if (file_exists($file)) {
        $cached_data = json_decode(file_get_contents($file), true);
        $cached_data['status'] = $status_msg;
        echo json_encode($cached_data);
    } else {
        echo json_encode([
            "error" => true,
            "message" => "No data available in cache yet.",
            "status" => $status_msg
        ]);
    }
    exit;
}

// 3. COOLDOWN CHECK (Springes over hvis ?force=true er sat)
if (file_exists($cache_file) && !$force_update) {
    clearstatcache(true, $cache_file);
    
    $file_age = $current_time - filemtime($cache_file);
    if ($file_age < $cooldown_seconds) {
        respond_with_cache($cache_file, "Cache active (" . (ceil(($cooldown_seconds - $file_age) / 60)) . "m left)");
    }
}

// 4. FETCH LATEST DATA
$url = "https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol=" . urlencode($ticker) . "&apikey=" . $alpha_vantage_key;
$options = array('http' => array('timeout' => 10, 'header' => "User-Agent: PHP\r\n"));
$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === FALSE) {
    respond_with_cache($cache_file, "API Network Error. Serving cache.");
}

$data = json_decode($response, true);

// Sikring hvis du rammer din max-grænse på 25 kald
if (!isset($data['Global Quote']) || !isset($data['Global Quote']['05. price'])) {
    respond_with_cache($cache_file, "API Limit Hit or Invalid Response. Serving cache.");
}

$quote = $data['Global Quote'];

// Hvis du tvang den, skriver vi det i statusteksten så du kan se det virkede
$status_label = $force_update ? "Forced Live Update" : "Live/Latest";

$fresh_payload = [
    "price" => (float)$quote['05. price'],
    "change" => (float)$quote['09. change'],
    "pct" => (float)str_replace('%', '', $quote['10. change percent']),
    "marketTime" => isset($quote['07. latest trading day']) ? $quote['07. latest trading day'] : date('Y-m-d'),
    "status" => $status_label . " (" . date('H:i:s') . ")"
];

// Gem data og nulstil metadata-cachen på serveren bagefter
file_put_contents($cache_file, json_encode($fresh_payload), LOCK_EX);
clearstatcache(true, $cache_file);

echo json_encode($fresh_payload);