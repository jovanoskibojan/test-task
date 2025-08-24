<?php
require_once 'classes/GoogleDocProcessor.php';
require_once 'classes/AirtableClient.php';
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$data = json_decode(file_get_contents('php://input'), true);
$url = $data['url'] ?? '';

$processor = new GoogleDocProcessor();
$result = $processor->process($url);

if($result["status"] == 'success') {
    $airtable = new AirtableClient('pat39CZ9t4QsYflc6.eedfdee891cdbef39cf959ad0585dc977b409e583aaab8d4ab1940f13c9d42f8', 'app5UWHpvXzSYsaId', 'Projects');
    $airtableResult = $airtable->createRecord([
        'Title' => $result['data']['title'],
        'Description' => $result['data']['description'],
        'Links' => $result['data']['links'],
        'Total images' => $result['data']['imageStats']['total'],
        'Images not in Drive' => $result['data']['imageStats']['not_drive'],
        'Private Drive images' => $result['data']['imageStats']['private'],
        'HTML Content' => $result['data']['html'],
    ], $result['data']['imageStats']['links']);
}
header('Content-Type: application/json');
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
