<!?php
header('Content-Type: text/html; charset=utf-8');

ini_set('display_errors', '1');

session_start();

echo 'User ID : ' . $_SESSION['user_id'] . "\n";
echo 'Super   : ' . $_SESSION['user_is_super'] . "\n";

// if ()

include_once "../common.php";

$reservationIds = [];
array_push($reservationIds, 160);
$reservationIdParameters = makeDbParameters($reservationIds);
print_r($reservationIds);
print_r($reservationIdParameters);

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

	echo "-----------------------\n";

	$result = dbExecute($connection, 'SELECT wish_id, reservation_key FROM wishes');
	while ($row = dbFetch($result))
	{
		echo $row->wish_id . ' > ' . decrypt($row->reservation_key) . "\n";
	}

	echo "-----------------------\n";

	$result = dbExecute($connection, 'SELECT reservation_id, `key` FROM reservations');
	while ($row = dbFetch($result))
	{
		echo $row->reservation_id . ' > ' . decrypt($row->key) . "\n";
	}
}
catch (PDOException $ex)
{
	die('Could not retrieve reservation information from database: ' . $ex->getMessage());
}

dbDisconnect($connection);

?>
