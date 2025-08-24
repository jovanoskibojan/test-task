<?php
class AirtableClient {
    private $apiKey;
    private $baseId;
    private $tableName;

    public function __construct($apiKey,  $baseId, $tableName) {
        $this->apiKey = $apiKey;
        $this->baseId = $baseId;
        $this->tableName = $tableName;
    }

    public function createRecord(array $fields, array $images = []): array {
        $url = "https://api.airtable.com/v0/{$this->baseId}/{$this->tableName}";

        if (!empty($images)) {
            // Assume your Attachment field is called 'Images' in Airtable
            $fields['Images'] = array_map(function($imgUrl) {
                return ['url' => $imgUrl];
            }, $images);
        }

        $postData = ['fields' => $fields];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->apiKey}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['status' => 'error', 'response' => $error];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['status' => 'success', 'response' => $response];
        } else {
            return ['status' => 'error', 'response' => $response];
        }
    }
}
