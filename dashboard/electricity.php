<?php
header('Content-Type: application/json');

// 1. CONFIGURATION & PLACEHOLDERS (Erstattes automatisk via GitHub Actions)
$eloverblik_token = "###ELOVERBLIK_API_TOKEN_PLACEHOLDER###"; 
$meter_id = "###ELOVERBLIK_METERING_POINT_PLACEHOLDER###"; 
$cache_file = __DIR__ . '/electricity_cache.json';
$cooldown_seconds = 30 * 60; // 30 minutter
$price_area = "DK2"; 

// 2. TIME REGULATION
date_default_timezone_set('Europe/Copenhagen');
$current_time = time();
$force_update = (isset($_GET['force']) && $_GET['force'] === 'true');

// Forbedret cache-funktion: Serverer eksisterende data som fallback ved API-fejl
function respond_with_cache($file, $status_msg) {
    if (file_exists($file)) {
        $cached_data = json_decode(file_get_contents($file), true);
        $cached_data['status'] = $status_msg . " (Serving backup cache)";
        echo json_encode($cached_data);
    } else {
        echo json_encode([
            "error" => true,
            "message" => "Ingen data tilgængelig i cachen endnu. Energinet rate-limiter serveren (HTTP 429).",
            "status" => $status_msg
        ]);
    }
    exit;
}

// 3. COOLDOWN CHECK (Bypass med ?force=true)
if (file_exists($cache_file) && !$force_update) {
    clearstatcache(true, $cache_file);
    if (($current_time - filemtime($cache_file)) < $cooldown_seconds) {
        respond_with_cache($cache_file, "Cache active");
    }
}

$start_date_str = date('Y-m-d', strtotime('-2 days'));
$end_date_str   = date('Y-m-d', strtotime('+1 day'));

// 4. FETCH HISTORIC CONSUMPTION FROM ELOVERBLIK
$consumption_map = [];

$auth_url = "https://api.eloverblik.dk/CustomerApi/api/Token";
$auth_options = [
    'http' => [
        'method' => 'GET',
        'header' => "Authorization: Bearer " . $eloverblik_token . "\r\n"
    ]
];
$auth_context = stream_context_create($auth_options);
$auth_response = @file_get_contents($auth_url, false, $auth_context);

if ($auth_response !== FALSE) {
    $auth_data = json_decode($auth_response, true);
    $data_token = isset($auth_data['result']) ? $auth_data['result'] : null;

    if ($data_token) {
        $data_url = "https://api.eloverblik.dk/CustomerApi/api/MeterData/GetTimeSeries/" . $start_date_str . "/" . date('Y-m-d', strtotime('+1 day'));
        $post_body = json_encode([
            "QueryMeteringPoints" => [
                "MeteringPoint" => [$meter_id]
            ]
        ]);

        $data_options = [
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer " . $data_token . "\r\n" .
                            "Content-Type: application/json\r\n",
                'content' => $post_body,
                'timeout' => 15
            ]
        ];
        $data_context = stream_context_create($data_options);
        $data_response = @file_get_contents($data_url, false, $data_context);

        if ($data_response !== FALSE) {
            $raw_consumption = json_decode($data_response, true);
            if (isset($raw_consumption['result'][0]['MyEnergyData_MarketDocument']['TimeSeries'][0]['Period'])) {
                foreach ($raw_consumption['result'][0]['MyEnergyData_MarketDocument']['TimeSeries'][0]['Period'] as $period) {
                    $period_start = $period['timeInterval']['start']; 
                    $period_date = date('Y-m-d', strtotime($period_start));
                    
                    foreach ($period['Point'] as $point) {
                        $hour_int = (int)$point['position'] - 1;
                        $hour_label = sprintf('%02d:00', $hour_int);
                        $map_key = $period_date . " " . $hour_label;
                        $consumption_map[$map_key] = (float)$point['out_Quantity.quantity'];
                    }
                }
            }
        }
    }
}

// 5. FETCH ELECTRICITY SPOT PRICES FROM ENERGINET
$filter_object = [
    "PriceArea" => [$price_area]
];

$api_url = "https://api.energidataservice.dk/dataset/Elspotprices"
         . "?start=" . $start_date_str . "T00:00"
         . "&end=" . $end_date_str . "T23:59"
         . "&filter=" . urlencode(json_encode($filter_object))
         . "&sort=HourUTC%20ASC"
         . "&limit=150";

$options = [
    'http' => [
        'method' => 'GET',
        'timeout' => 15,
        'protocol_version' => 1.1,
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) PHP\r\n" .
                    "Accept: application/json\r\n" .
                    "Connection: close\r\n"
    ],
    'ssl' => [
        'verify_peer' => false, 
        'verify_peer_name' => false
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($api_url, false, $context);

if ($response === FALSE) { 
    $error_info = "Energinet API Error.";
    if (isset($http_response_header[0])) {
        $error_info .= " (" . $http_response_header[0] . ")";
    }
    respond_with_cache($cache_file, $error_info); 
}

$data = json_decode($response, true);
if (!isset($data['records']) || empty($data['records'])) {
    respond_with_cache($cache_file, "Ugyldigt svar eller tomme records fra Energinet.");
}

// 6. PROCESS AND COMBINE DATA
$hourly_data = [];
$elafgift = 0.7611;       
$energinet_tl = 0.1170;   

foreach ($data['records'] as $record) {
    $spot_kwh = $record['SpotPriceDKK'] / 1000;
    $local_timestamp = strtotime($record['HourDK']);
    $hour = (int)date('G', $local_timestamp);
    $date_label = date('Y-m-d', $local_timestamp);
    $hour_label = date('H:i', $local_timestamp);
    
    // Tariffer (SEF Net Sommermodel: Apr - Sep)
    if ($hour >= 0 && $hour < 6) { $nettarif = 0.1132; }
    elseif ($hour >= 17 && $hour < 21) { $nettarif = 0.4194; }
    else { $nettarif = 0.1704; }
    
    $total_price = round(($spot_kwh + $nettarif + $energinet_tl + $elafgift) * 1.25, 2);
    $timeline_key = date('Y-m-d H:i', $local_timestamp);

    $real_consumption = isset($consumption_map[$timeline_key]) ? $consumption_map[$timeline_key] : 0;

    $hourly_data[] = [
        "timestamp" => $timeline_key,
        "date" => $date_label,
        "hour" => $hour_label,
        "spot_price" => round($spot_kwh, 2),
        "total_price" => $total_price,
        "consumption_kwh" => $real_consumption
    ];
}

$status_label = $force_update ? "Forced Live Update" : "Live Update";

$payload = [
    "grid_operator" => "SEF Net (Sommermodel)",
    "status" => $status_label . " (" . date('H:i:s') . ")",
    "timeline" => $hourly_data
];

file_put_contents($cache_file, json_encode($payload), LOCK_EX);
clearstatcache(true, $cache_file);

echo json_encode($payload);