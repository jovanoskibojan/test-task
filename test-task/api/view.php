<?php
$recordId = $_GET['recordId'];
$apiKey = 'pat39CZ9t4QsYflc6.eedfdee891cdbef39cf959ad0585dc977b409e583aaab8d4ab1940f13c9d42f8';
$baseId = 'app5UWHpvXzSYsaId';
$tableName = 'Projects';

$url = "https://api.airtable.com/v0/$baseId/$tableName/$recordId";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$htmlContent = $data['fields']['HTML Content'];
$title = $data['fields']['Title'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $title ?></title>
</head>
<body>
<?php echo $htmlContent ?>
</body>
</html>
