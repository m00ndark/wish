<?php
header('Content-Type: text/html; charset=utf-8');

ini_set('display_errors', '1');

session_start();

echo "<pre>\n";
echo 'User ID : ' . $_SESSION['user_id'] . "\n";
echo 'Super   : ' . $_SESSION['user_is_super'] . "\n";
echo 'DIR     : ' . __DIR__ . "\n\n\n";

// if ()

include_once "../common.php";

//$reservationIds = [];
//array_push($reservationIds, 160);
//$reservationIdParameters = makeDbParameters($reservationIds);
//print_r($reservationIds);
//print_r($reservationIdParameters);

$connection = dbConnect();

try
{
	// $keys = [];
	// $result = dbExecute($connection, 'SELECT wish_id, reservation_key FROM wishes');
	// while ($row = dbFetch($result))
	// {
	// 	$reservationKey = decrypt($row->reservation_key);
	// 	if ($reservationKey == '1108752362')
	// 	{
	// 		$keys[$row->wish_id] = $reservationKey;
	// 		echo '* ';
	// 	}
	// 	echo $row->wish_id . '  > ' . decrypt($row->reservation_key) . "\n";
	// }

	// echo "-----------------------\n";
	
	// foreach ($keys as $id => $key)
	// {
	// 	do
	// 	{
	// 		$key = rand();
	// 	}
	// 	while (array_search($key, $keys) !== false);

	// 	// $key = '1108752362';
	// 	$keys[$id] = $key;

	// 	$encKey = encrypt($key);
	// 	$decKey = decrypt($encKey);

	// 	echo "Id: $id, Plain: $key, Encrypted: $encKey, Decrypted: $decKey\n";

	// 	dbExecute($connection, 'UPDATE wishes SET reservation_key = :key WHERE wish_id = :wishId',
	// 		[':key' => encrypt($key), ':wishId' => $id]);
	// }

	echo "wishes: wish_id > reservation_key\n";
	echo "-----------------------\n";

	$result = dbExecute($connection, 'SELECT wish_id, reservation_key FROM wishes');
	while ($row = dbFetch($result))
	{
		echo $row->wish_id . ' > ' . decrypt($row->reservation_key) . "\n";
	}

	echo "\nreservations: reservation_id > key (reserved_by_user_id)\n";
	echo "-----------------------\n";

	$result = dbExecute($connection, 'SELECT reservation_id, `key`, reserved_by_user_id FROM reservations');
	while ($row = dbFetch($result))
	{
		echo $row->reservation_id . ' > ' . decrypt($row->key) . ' (' . decrypt($row->reserved_by_user_id) . ')' . "\n";
	}
}
catch (PDOException $ex)
{
	die('Could not retrieve reservation information from database: ' . $ex->getMessage());
}

echo "</pre>\n";

dbDisconnect($connection);

?>
