<?php
require_once 'classes/GoogleDocProcessor.php';
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$data = json_decode(file_get_contents('php://input'), true);
$url = $data['url'] ?? '';

$processor = new GoogleDocProcessor();
$result = $processor->process($url);

header('Content-Type: application/json');
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
