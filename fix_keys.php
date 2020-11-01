<?!php

// ini_set('display_errors', '1');

include_once "common.php";

$connection = dbConnect();
try
{
	$keys = [];
	$result = dbExecute($connection, 'SELECT wish_id FROM wishes ORDER BY wish_id');
	while ($row = dbFetch($result))
	{
		$keys[$row->wish_id] = '';
	}
	
	foreach ($keys as $id => $key)
	{
		do
		{
			$key = rand();
		}
		while (array_search($key, $keys) !== false);

		$key = '1108752362';
		$keys[$id] = $key;

		$encKey = encrypt($key);
		$decKey = decrypt($encKey);

		echo "Id: $id, Plain: $key, Encrypted: $encKey, Decrypted: $decKey\n";

		dbExecute($connection, 'UPDATE wishes SET reservation_key = :key WHERE wish_id = :wishId',
			[':key' => encrypt($key), ':wishId' => $id]);
	}
}
catch (PDOException $ex)
{
	die('Could not retrieve user names from database: ' . $ex->getMessage());
}
dbDisconnect($connection);

?>