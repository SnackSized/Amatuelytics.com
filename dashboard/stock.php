<?php
header('Content-Type: application/json');

// 1. CONFIGURATION
$alpha_vantage_key = "###ALPHA_VANTAGE_KEY_PLACEHOLDER###";
$ticker = "RR.L";
$cache_file = __DIR__ . '/cache.json';
$cooldown_seconds = 20 * 60; // 20 minutter

// 2. TIME REGULATION (Europe/Copenhagen CET/CEST)
date_default_timezone_set('Europe/Copenhagen');
$current_time = time();
$hour = (int)date('G', $current_time);
$minute = (int)date('i', $current_time);

// Helper function to send cached data or error
function respond_with_cache($file, $status_msg) {
    if (file_exists($file)) {
        $cached_data = json_decode(file_get_contents($file), true);
        $cached_data['status'] = $status_msg;
        echo json_encode($cached_data);
    } else {
        echo json_encode([
            "error" => true,
            "message" => "No data available.",
            "status" => $status_msg
        ]);
    }
    exit;
}

// STRAM BESKYTTELSE: Tillad KUN live-kald mellem 09:16 og 17:15
// Alt uden for dette tidsrum samt weekender afvises med det samme og bruger 0 API-kvote.
$is_weekend = (date('N', $current_time) >= 6);
$too_early = ($hour < 9 || ($hour === 9 && $minute < 16));
$too_late  = ($hour > 17 || ($hour === 17 && $minute > 15));

if ($is_weekend || $too_early || $too_late) {
    respond_with_cache($cache_file, "Market closed. Serving cache.");
}

// 3. COOLDOWN CHECK (Beskytter mod at browseren trækker data for tit)
if (file_exists($cache_file)) {
    $file_age = $current_time - filemtime($cache_file);
    if ($file_age < $cooldown_seconds) {
        respond_with_cache($cache_file, "Cache active (Cooldown: " . (ceil(($cooldown_seconds - $file_age) / 60)) . "m left)");
    }
}

// 4. FETCH LIVE DATA (Udføres kun hvis markeds- og cooldown-tjek er bestået)
$url = "https://www.alphavantage.co/query?function=GLOBAL_QUOTE&symbol=" . urlencode($ticker) . "&apikey=" . $alpha_vantage_key;
$options = array('http' => array('timeout' => 10, 'header' => "User-Agent: PHP\r\n"));
$context = stream_context_create($options);
$response = @file_get_contents($url, false, $context);

if ($response === FALSE) {
    respond_with_cache($cache_file, "API Network Error. Serving cache.");
}

$data = json_decode($response, true);

// Hvis du rammer din max-grænse på 25 kald, vil Alpha Vantage returnere en besked om "rate limit".
// Denne blok opdager det og redder siden ved at vise den gamle cache i stedet for at crashe.
if (!isset($data['Global Quote']) || !isset($data['Global Quote']['05. price'])) {
    respond_with_cache($cache_file, "API Limit Hit or Invalid Response. Serving cache.");
}

$quote = $data['Global Quote'];

$fresh_payload = [
    "price" => (float)$quote['05. price'],
    "change" => (float)$quote['09. change'],
    "pct" => (float)str_replace('%', '', $quote['10. change percent']),
    "marketTime" => isset($quote['07. latest trading day']) ? $quote['07. latest trading day'] : date('Y-m-d'),
    "status" => "Live Update (" . date('H:i:s') . ")"
];

// Gem de friske data og opdater filens modifikationstid (filemtime)
file_put_contents($cache_file, json_encode($fresh_payload));
echo json_encode($fresh_payload);