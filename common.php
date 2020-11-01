<?php

// define password hash and encryption salt length
define('SALT_LENGTH', 16);

// define encryption key
define('ENCRYPTION_CIPHER', 'aes-128-ctr');
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

function dbDisconnect(&$connection)
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
			. ' ON wishes.wishlist_id = wishlists.wishlist_id WHERE wish_id = :wishId', [':wishId' => $wishId]);

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

function encrypt($decryptedData)
{
	$ivLength = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
	$iv = openssl_random_pseudo_bytes($ivLength); // initialization vector
	$encryptedData = openssl_encrypt($decryptedData, ENCRYPTION_CIPHER, ENCRYPTION_KEY, $options=0, $iv);
	$encryptedData = $iv . $encryptedData;
	$encoded_64 = base64_encode($encryptedData);
	return $encoded_64;
}

function decrypt($encryptedData)
{
	$decoded_64 = base64_decode($encryptedData);
	$ivLength = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
	$iv = substr($decoded_64, 0, $ivLength); // initialization vector
	$decryptedData = openssl_decrypt(substr($decoded_64, $ivLength), ENCRYPTION_CIPHER, ENCRYPTION_KEY, $options=0, $iv);
	return $decryptedData;
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