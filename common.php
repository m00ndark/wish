<?php

// define password hash and encryption salt length
define('SALT_LENGTH', 16);

// define encryption key
define('ENCRYPTION_KEY', 'my-wish-is-your-wish');


// ***************************************************************
// ******** FUNCTIONS ********************************************
// ***************************************************************

function dbConnect()
{
	if (isset($_SESSION['environment']))
	{
		$dbName = 'wishlist_' . $_SESSION['environment'] . '_db';
	}
	else
	{
		$dbName = 'wishlist_db';
	}
	try
	{
		$connection = new PDO("mysql:host=127.0.0.1;dbname=$dbName;charset=utf8", 'wishlist', 'do-not-try-to-guess');
		$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		return $connection;
	}
	catch (PDOException $ex)
	{
		die('Could not connect to database server: ' . $ex->getMessage());
	}
}

function dbDisconnect($connection)
{
	$connection = null;
}

function dbExecute($connection, $query, $params = null)
{
	$statement = $connection->prepare($query);
	$statement->execute($params);
	return $statement;
}

function dbFetch($result)
{
	return $result->fetch(PDO::FETCH_OBJ);
}

function dbFetchColumn($result, int $columnNumber = 0)
{
	return $result->fetchColumn($columnNumber);
}

function makeDbParameters($values)
{
	return arrayFlatten(array_walk($values, function (&$value, $i) { $id = [":p$i" => $value]; }));
}

function arrayFlatten($array)
{
	$result = []; 
	foreach ($array as $key => $value)
	{
		$result = is_array($value)
			? array_merge($result, arrayFlatten($value))
			: array_merge($result, array($key => $value));
	}
	return $result; 
}

function generateHash($plainText, $salt = null)
{
	if ($salt === null)
	{
		$salt = substr(md5(uniqid(rand(), true)), 0, SALT_LENGTH);
	}
	else
	{
		$salt = substr($salt, 0, SALT_LENGTH);
	}
	return $salt . sha1($salt . $plainText);
}

function userOwnsWish($connection, $userId, $wishId)
{
	try
	{
		$result = dbExecute($connection, 'SELECT user_id, shared_with_user_id FROM wishes INNER JOIN wishlists'
			. ' ON wishes.wishlist_id = wishlists.wishlist_id WHERE wish_id = :wishId', array(':wishId' => $wishId));

		$row = dbFetch($result);
		return ($row->user_id == $userId || $row->shared_with_user_id == $userId);
	}
	catch (PDOException $ex)
	{
		die('Could not validate user ownership of wish towards database: ' . $ex->getMessage());
	}
}

function userOwnsWishList($connection, $userId, $listId)
{
	try
	{
		$result = dbExecute($connection, 'SELECT user_id, shared_with_user_id FROM wishlists WHERE wishlist_id = :listId', [':listId' => $listId]);

		$row = dbFetch($result);
		return ($row->user_id == $userId || $row->shared_with_user_id == $userId);
	}
	catch (PDOException $ex)
	{
		die('Could not validate user ownership of wish list towards database: ' . $ex->getMessage());
	}
}

function wishBelongsToChildList($connection, $wishId)
{
	try
	{
		$result = dbExecute($connection, 'SELECT is_child_list FROM wishes INNER JOIN wishlists'
			. ' ON wishes.wishlist_id = wishlists.wishlist_id WHERE wish_id = :wishId', [':wishId' => $wishId]);

		$row = dbFetch($result);
		return ($row->is_child_list == 1);
	}
	catch (PDOException $ex)
	{
		die('Could not validate type of wish list for wish towards database: ' . $ex->getMessage());
	}
}

function wash($str)
{
	return str_replace(array('"'), array('&quot;'), $str);
}

function encrypt($decrypted_data)
{
	$salt = substr(md5(uniqid(rand(), true)), 0, SALT_LENGTH);
	$decrypted_data = $decrypted_data . $salt;
	$td = mcrypt_module_open('cast-256', '', 'ecb', '');
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
	mcrypt_generic_init($td, ENCRYPTION_KEY, $iv);
	$encrypted_data = mcrypt_generic($td, $decrypted_data);
	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);
	$encoded_64 = base64_encode($encrypted_data);
	return $encoded_64;
}

function decrypt($encrypted_data)
{
	$decoded_64 = base64_decode($encrypted_data);
	$td = mcrypt_module_open('cast-256', '', 'ecb', '');
	$iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
	mcrypt_generic_init($td, ENCRYPTION_KEY, $iv);
	$decrypted_data = mdecrypt_generic($td, $decoded_64);
	mcrypt_generic_deinit($td);
	mcrypt_module_close($td);
	$decrypted_data = str_replace('\0', '', $decrypted_data);
	$decrypted_data = substr($decrypted_data, 0, strlen($decrypted_data) - SALT_LENGTH);
	return $decrypted_data;
}

function recursiveArraySearch($haystack, $needle, $index = null)
{
	$arrayIterator = new RecursiveArrayIterator($haystack);
	$iterator = new RecursiveIteratorIterator($arrayIterator);
	while ($iterator->valid())
	{
		if ((isset($index) && ($iterator->key() == $index) || !isset($index)) && $iterator->current() == $needle)
		{
			return $arrayIterator->key();
		}
		$iterator->next();
	}
	return false;
}

function redirect($location)
{
	header('Location: ' . $location);
	die();
}

function forwardTo($page)
{
	$fromPage = substr(strrchr($_SERVER['PHP_SELF'], '/'), 1);
	$params = (strcasecmp($fromPage, $page) != 0 ? '?page=' . urlencode($fromPage) : '');
	if (count($_GET) > 0)
	{
		$paramArray = [];
		foreach ($_GET as $pName => $pValue)
		{
			$paramArray[] = $pName . '=' . $pValue;
		}
		$params .= '&params=' . urlencode(join('&', $paramArray));
	}
	redirect($page . $params);
}
?>