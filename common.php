<?php

// define password hash and encryption salt length
define("SALT_LENGTH", 16);

// define encryption key
define("ENCRYPTION_KEY", "my-wish-is-your-wish");


// ***************************************************************
// ******** FUNCTIONS ********************************************
// ***************************************************************

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
	$connection = mysql_connect("192.168.50.210", "wishlist", "do-not-try-to-guess");
	if (!$connection)
	{
		die("Could not connect to database server: " . mysql_error());
	}
	$dbSelected = mysql_select_db($dbName, $connection);
	if (!$dbSelected)
	{
		die("Could not select database: " . mysql_error());
	}
	mysql_set_charset('utf8');
	return $connection;
}

function dbDisconnect($connection)
{
	mysql_close($connection);
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

function userOwnsWish($userId, $wishId)
{
	$result = mysql_query("SELECT user_id, shared_with_user_id FROM wishes INNER JOIN wishlists"
		. " ON wishes.wishlist_id = wishlists.wishlist_id WHERE wish_id = " . $wishId);
	if (!$result)
	{
		die("Could not validate user ownership of wish towards database: " . mysql_error());
	}
	$row = mysql_fetch_row($result);
	return ($row[0] == $userId || $row[1] == $userId);
}

function userOwnsWishList($userId, $listId)
{
	$result = mysql_query("SELECT user_id, shared_with_user_id FROM wishlists WHERE wishlist_id = " . $listId);
	if (!$result)
	{
		die("Could not validate user ownership of wish list towards database: " . mysql_error());
	}
	$row = mysql_fetch_row($result);
	return ($row[0] == $userId || $row[1] == $userId);
}

function wishBelongsToChildList($wishId)
{
	$result = mysql_query("SELECT is_child_list FROM wishes INNER JOIN wishlists"
		. " ON wishes.wishlist_id = wishlists.wishlist_id WHERE wish_id = " . $wishId);
	if (!$result)
	{
		die("Could not validate type of wish list for wish towards database: " . mysql_error());
	}
	$row = mysql_fetch_row($result);
	return ($row[0] == 1);
}

function wash($str)
{
	return str_replace(array("\""), array("&quot;"), $str);
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
	$decrypted_data = str_replace("\0", "", $decrypted_data);
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
	header("Location: " . $location);
	die();
}


function forwardTo($page)
{
	$fromPage = substr(strrchr($_SERVER["PHP_SELF"], "/"), 1);
	$params = (strcasecmp($fromPage, $page) != 0 ? "?page=" . urlencode($fromPage) : "");
	if (count($_GET) > 0)
	{
		$paramArray = array();
		foreach ($_GET as $pName => $pValue)
		{
			$paramArray[] = $pName . "=" . $pValue;
		}
		$params .= "&params=" . urlencode(join("&", $paramArray));
	}
	redirect($page . $params);
}

?>