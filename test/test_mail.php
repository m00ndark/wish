<?php

ini_set('display_errors', '1');

// include_once "../common.php";

$emailAddress = 'mattias.wijkstrom@gmail.com';
$headers = 'From: wish@m00ndark.com\r\nReply-To: mattias.wijkstrom@gmail.com';
$body = "F�r att �ndra ditt l�senord p� Familjens �nskelista, klicka p� l�nken nedan:\r\n\r\n";
$result = mail($emailAddress, 'Familjens �nskelista - nytt l�senord', $body, $headers);

echo "Result: $result";

?>