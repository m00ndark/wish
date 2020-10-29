<?php

function dbConnect()
{
	if (isset($_SESSION["environment"]))
	{
		$dbName = "wishlist_" . $_SESSION["environment"] . "_db";
	}
	else
	{
		$dbName = "wishlist_db";
	}
	$connection = mysql_connect("localhost", "wishlist", "do-not-try-to-guess");
	if (!$connection)
	{
		die("Could not connect to database server: " . mysql_error());
	}
	$dbSelected = mysql_select_db($dbName, $connection);
	if (!$dbSelected)
	{
		die("Could not select database: " . mysql_error());
	}
	return $connection;
}

function dbDisconnect($connection)
{
	mysql_close($connection);
}

function executeSingle($query)
{
//	echo "query: " . $query . "\r\n";

	$connection = dbConnect();

	$result = mysql_query($query);

	if (!$result)
	{
		http_response_code(500);
		die("Failed to query database: " . mysql_error() . "\r\n" . $query);
	}
	
	$response = mysql_fetch_assoc($result);
	
	if (!$response)
	{
		http_response_code(404);
		die();
	}

	dbDisconnect($connection);

	return $response;
}

function executeQuery($query)
{
//	echo "query: " . $query . "\r\n";

	$connection = dbConnect();

	$result = mysql_query($query);

	if (!$result)
	{
		http_response_code(500);
		die("Failed to query database: " . mysql_error() . "\r\n" . $query);
	}
	
	$response = array();
	
	while ($row = mysql_fetch_assoc($result))
	{
		$response[] = $row;
	}

	dbDisconnect($connection);

	return $response;
}

function executeNonQuery($query, $isInsert = false)
{
//	echo "query: " . $query . "\r\n";

	$connection = dbConnect();

	$result = mysql_query($query);

	if (!$result)
	{
		http_response_code(500);
		die("Failed to execute non-query towards database: " . mysql_error() . "\r\n" . $query);
	}

	if ($isInsert)
	{
		$id = mysql_insert_id();
	}

	dbDisconnect($connection);
	
	if (isset($id))
	{
		return $id;
	}
}

?>