<?php
if (ob_get_level()) while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$month = (int)($_GET['month'] ?? date('n'));
$year = (int)($_GET['year'] ?? date('Y'));

$url = "https://api-hari-libur.vercel.app/api?year={$year}&month={$month}";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || $response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Gagal mengambil data libur'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);
if (!$data || !isset($data['data'])) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

// Normalize to match frontend format
$result = [];
foreach ($data['data'] as $item) {
    $result[] = [
        'tanggal' => $item['date'],
        'keterangan' => $item['description'],
        'is_cuti' => false,
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
