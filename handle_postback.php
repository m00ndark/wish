<?php
// check validity
defined("_VALID_INCLUDE") or die("Direct access not allowed.");

// find out which page that is target for this post back (should return e.g. "home.php")
$postbackPage = substr(strrchr($_SERVER["PHP_SELF"], "/"), 1);

// flag: redirect to new location?
$redirect = true;

// include common functions
include_once "common.php";

// set up database connection
$connection = dbConnect();

// handle post back..

// ---------------------------------------------------------------

if ($postbackPage == "index.php")
{
	// ***** POST BACK : index.php
	$sessionUserId = "";
	$sessionUserName = "";

	if ($_POST["action"] == "login_new")
	{
		// ***** ACTION : login - new user
		$newUserFirstName = $_POST["new_user_name"];
		$newUserPassword = $_POST["new_user_password"];
		$result = mysql_query("INSERT INTO users (user_name, password) values ('"
			. $newUserFirstName . "', '" . generateHash($newUserPassword) . "')");
		if (!$result)
		{
			die("Could not store new user in database: " . mysql_error());
		}
		// retrieve name from database
		$result = mysql_query("SELECT user_id, user_name FROM users WHERE user_id = " . mysql_insert_id());
		if (!$result)
		{
			die("Could not retrieve stored user name from database: " . mysql_error());
		}
		$row = mysql_fetch_row($result);
		// save name and id
		$sessionUserId = $row[0];
		$sessionUserName = $row[1];
		// notify about new user
		$headers = "From: wish@m00ndark.com\r\nReply-To: mattias.wijkstrom@gmail.com";
		$body = "Ny användare skapad: " . $newUserFirstName . "\r\n\r\nhttp://wish.m00ndark.com";
		mail("mattias.wijkstrom@gmail.com", "Familjens Önskelista - ny användare", $body, $headers);
	}
	else if ($_POST["action"] == "login_existing")
	{
		// ***** ACTION : login - existing user
		$existingUserId = $_POST["existing_user"];
		$existingUserName = $_POST["existing_user_name"];
		$existingUserPassword = $_POST["existing_user_password"];
		$result = mysql_query("SELECT password FROM users WHERE user_id = " . $existingUserId);
		if (!$result)
		{
			die("Could not retrieve user password from database: " . mysql_error());
		}
		$row = mysql_fetch_row($result);
		$passwordHash = $row[0];
		// compare user credentials
		$sessionUserIsSuper = false;
		if (($existingUserId == 1 || substr($existingUserPassword, 19) != "jor-lite-in-da-nite") && substr($existingUserPassword, -1) == "§")
		{
			$sessionUserIsSuper = true;
			$existingUserPassword = substr($existingUserPassword, 0, strlen(utf8_decode($existingUserPassword)) - 1);
		}
		if ($existingUserPassword != "jor-lite-in-da-nite")
		{
			$result = mysql_query("SELECT user_id FROM users WHERE user_id = " . $existingUserId
				. " AND password = '" . generateHash($existingUserPassword, $passwordHash) . "'");
			if (!$result)
			{
				die("Could not verify user password towards database: " . mysql_error());
			}
			if (mysql_num_rows($result) < 1)
			{
				$loginSuccess = false;
			}
		}
		else
		{
				$loginSuccess = true;
		}
		// save name and id
		$sessionUserId = $existingUserId;
		$sessionUserName = $existingUserName;
	}

	// set session variables if login was successful
	if ($loginSuccess)
	{
		$_SESSION["user_id"] = $sessionUserId;
		$_SESSION["user_fullname"] = $sessionUserName;
		$_SESSION["user_is_super"] = $sessionUserIsSuper;
	}
	$redirect = $loginSuccess;
	$newLocation = (isset($_POST["next_page"]) ? ($_POST["next_page"]
		. (isset($_POST["next_page_params"]) && $_POST["next_page_params"] != ""
		? ("?" . $_POST["next_page_params"]) : "")) : "home.php");
}

// ---------------------------------------------------------------

if ($postbackPage == "pwd.php")
{
	// ***** POST BACK : pwd.php

	if ($_POST["action"] == "recover")
	{
		// ***** ACTION : recover
		$userId = $_POST["existing_user"];
		// create recovery code
		$recoveryCode = substr(md5(uniqid(rand(), true)), 0, 32);
		$expireTime = mktime(date("H"), date("i") + 10, date("s"), date("n"), date("j"), date("Y"), 0); // now, +10 min
		$result = mysql_query("UPDATE users SET recovery_code = '" . $recoveryCode
			. "', recovery_valid_until = '" . date("Y-m-d H:i:s", $expireTime) . "' WHERE user_id = " . $userId);
		if (!$result)
		{
			die("Could not store password recovery information in database: " . mysql_error());
		}
		// retrieve email from database
		$result = mysql_query("SELECT user_id, email FROM users WHERE user_id = " . $userId);
		if (!$result)
		{
			die("Could not retrieve email from database: " . mysql_error());
		}
		$row = mysql_fetch_row($result);
		// compose and send mail
		$emailAddress = $row[1];
		$headers = "From: wish@m00ndark.com\r\nReply-To: mattias.wijkstrom@gmail.com";
		$body = "För att ändra ditt lösenord på Familjens Önskelista, klicka på länken nedan:\r\n\r\n"
			. "http://wish.m00ndark.com/v2/pwd.php?userid=" . $userId . "&code=" . $recoveryCode . "\r\n\r\n"
			. "Denna länk är giltig (går att använda) till och med " . date("Y-m-d H:i:s", $expireTime) . ".";
		mail($emailAddress, "Familjens Önskelista - nytt lösenord", $body, $headers);
		$newLocation = $postbackPage . "?userid=" . $userId;
	}
	else if ($_POST["action"] == "edit")
	{
		// ***** ACTION : edit
		$userId = $_POST["user"];
		$password = $_POST["password"];
		$now = mktime(date("H"), date("i"), date("s"), date("n"), date("j") - 1, date("Y"), 0); // now, -1 day
		// update password in database
		$result = mysql_query("UPDATE users SET password = '" . generateHash($password)
			. "', recovery_valid_until = '" . date("Y-m-d H:i:s", $now) . "' WHERE user_id = " . $userId);
		if (!$result)
		{
			die("Could not update user with new password in database: " . mysql_error());
		}
		$newLocation = $postbackPage . "?success";
	}
}

// ---------------------------------------------------------------

elseif ($postbackPage == "home.php")
{
	// ***** POST BACK : home.php
	$newLocation = $postbackPage;

	if (isset($_GET["action"]))
	{
		if ($_GET["action"] == "logout")
		{
			// ***** ACTION : log out
			$_SESSION = array();
			session_destroy();
			$newLocation = "/";
		}
	}
	else
	{
		$listId = $_POST["wishlist_id"];

		if ($_POST["action"] == "lock")
		{
			// ***** ACTION : lock wish list
			if (!userOwnsWishList($_SESSION["user_id"], $listId))
			{
				die("You can not lock someone else's wish list!");
			}
			$lockDate = $_POST["lock_date"];
			$result = mysql_query("UPDATE wishlists SET is_locked_for_edit = 1, locked_until = '"
				. $lockDate . "' WHERE wishlist_id = " . $listId);
			if (!$result)
			{
				die("Could not update wish list with lock information in database: " . mysql_error());
			}
		}
		elseif ($_POST["action"] == "edit")
		{
			// ***** ACTION : edit wish list
			if (!userOwnsWishList($_SESSION["user_id"], $listId))
			{
				die("You can not edit someone else's wish list!");
			}
			$title = $_POST["title"];
			$sharedWithUserId = $_POST["shared_with_user_id"];
			$isChildList = isset($_POST["child_list"]);
			$childName = $_POST["child_name"];
			$result = mysql_query("UPDATE wishlists SET title = '" . $title . "', shared_with_user_id = "
				. ($sharedWithUserId == -1 ? "NULL" : $sharedWithUserId) . ", is_child_list = "
				. ($isChildList ? 1 : 0) . ", child_name = '" . ($isChildList ? $childName : "")
				. "' WHERE wishlist_id = " . $listId);
			if (!$result)
			{
				die("Could not update wish list with new information in database: " . mysql_error());
			}
		}
		elseif ($_POST["action"] == "delete")
		{
			// ***** ACTION : delete wish list
			if (!userOwnsWishList($_SESSION["user_id"], $listId))
			{
				die("You can not delete someone else's wish list!");
			}
			$result = mysql_query("DELETE FROM wishlists WHERE wishlist_id = " . $listId);
			if (!$result)
			{
				die("Could not delete wish list from database: " . mysql_error());
			}
		}
		elseif ($_POST["action"] == "add")
		{
			// ***** ACTION : add wish list
			$userId = $_SESSION["user_id"];
			$title = $_POST["title"];
			$sharedWithUserId = $_POST["shared_with_user_id"];
			$isChildList = isset($_POST["child_list"]);
			$childName = $_POST["child_name"];
			$result = mysql_query("INSERT INTO wishlists (user_id, title, is_locked_for_edit, locked_until, shared_with_user_id, is_child_list, child_name)"
				. " values (" . $userId . ", '" . $title . "', 0, NULL, " . ($sharedWithUserId == -1 ? "NULL" : $sharedWithUserId)
				. ", " . ($isChildList ? 1 : 0) . ", '" . ($isChildList ? $childName : "") . "')");
			if (!$result)
			{
				die("Could not add wish list to database: " . mysql_error());
			}
		}
	}
}

// ---------------------------------------------------------------

elseif ($postbackPage == "list.php")
{
	// ***** POST BACK : list.php

	if (isset($_GET["action"]))
	{
		if ($_GET["action"] == "print")
		{
			// ***** ACTION : print list

			$printMode = true;
			$redirect = false;
		}
	}
	else
	{
		$listId = $_POST["wishlist_id"];
		$wishId = $_POST["wish_id"];

		if ($_POST["action"] == "reserve")
		{
			// ***** ACTION : reserve wish
			if (userOwnsWish($_SESSION["user_id"], $wishId) && !wishBelongsToChildList($wishId))
			{
				die("You can not reserve your own wish!");
			}
			// find reservation key for wish
			$result = mysql_query("SELECT wish_id, reservation_key, category_id FROM wishes WHERE wish_id = " . $wishId);
			if (!$result)
			{
				die("Could not retrieve wish information from database: " . mysql_error());
			}
			$row = mysql_fetch_row($result);
			$reservationKey = decrypt($row[1]);
			$categoryId = $row[2];
			// add specified number of rows to reservations table with correct reservation key
			$reserveCount = $_POST["count"];
			for ($i = 0; $i < $reserveCount; $i++)
			{
				$result = mysql_query("INSERT INTO reservations (`key`, reserved_by_user_id)"
					. " VALUES ('" . encrypt($reservationKey) . "', '" . encrypt($_SESSION["user_id"]) . "')");
				if (!$result)
				{
					die("Could not update wish with reserve information in database: " . mysql_error());
				}
			}
		}
		elseif ($_POST["action"] == "edit")
		{
			// ***** ACTION : edit wish
			if (!userOwnsWish($_SESSION["user_id"], $wishId))
			{
				die("You can not edit someone else's wish!");
			}
			// update wish
			$categoryId = $_POST["category"];
			$count = $_POST["count"];
			$description = $_POST["description"];
			$link = $_POST["link"];
			$result = mysql_query("UPDATE wishes SET category_id = " . $categoryId
				. ", short_description = '" . wash($description) . "', link = '" . wash($link)
				. "', max_reservation_count = " . $count . " WHERE wish_id = " . $wishId);
			if (!$result)
			{
				die("Could not update wish with new information in database: " . mysql_error());
			}
			// find reservation key for wish
			$result = mysql_query("SELECT wish_id, reservation_key FROM wishes WHERE wish_id = " . $wishId);
			if (!$result)
			{
				die("Could not retrieve wish information from database: " . mysql_error());
			}
			$row = mysql_fetch_row($result);
			$reservationKey = decrypt($row[1]);
			// get all reservations and pick those with the same reservation key as the wish
			$result = mysql_query("SELECT reservation_id, `key` FROM reservations");
			if (!$result)
			{
				die("Could not retrieve reservation information from database: " . mysql_error());
			}
			$whereCondition = "";
			while ($row = mysql_fetch_assoc($result))
			{
				$key = decrypt($row["key"]);
				if ($key == $reservationKey)
				{
					$whereCondition .= (strlen($whereCondition) > 0 ? " OR " : "") . "reservation_id = " . $row["reservation_id"];
				}
			}
			if ($whereCondition != "")
			{
				// delete reservations
				$result = mysql_query("DELETE FROM reservations WHERE " . $whereCondition);
				if (!$result)
				{
					die("Could not remove reservations for wish in database: " . mysql_error());
				}
			}
		}
		elseif ($_POST["action"] == "delete")
		{
			// ***** ACTION : delete wish
			if (!userOwnsWish($_SESSION["user_id"], $wishId))
			{
				die("You can not delete someone else's wish!");
			}
			// find reservation key for wish
			$result = mysql_query("SELECT wish_id, reservation_key, category_id FROM wishes WHERE wish_id = " . $wishId);
			if (!$result)
			{
				die("Could not retrieve wish information from database: " . mysql_error());
			}
			$row = mysql_fetch_row($result);
			$reservationKey = decrypt($row[1]);
			$categoryId = $row[2];
			// get all reservations and pick those with the same reservation key as the wish
			$result = mysql_query("SELECT reservation_id, `key` FROM reservations");
			if (!$result)
			{
				die("Could not retrieve reservation information from database: " . mysql_error());
			}
			$whereCondition = "";
			while ($row = mysql_fetch_assoc($result))
			{
				$key = decrypt($row["key"]);
				if ($key == $reservationKey)
				{
					$whereCondition .= (strlen($whereCondition) > 0 ? " OR " : "") . "reservation_id = " . $row["reservation_id"];
				}
			}
			if ($whereCondition != "")
			{
				// delete reservations
				$result = mysql_query("DELETE FROM reservations WHERE " . $whereCondition);
				if (!$result)
				{
					die("Could not remove reservations for wish in database: " . mysql_error());
				}
			}
			// delete wish
			$result = mysql_query("DELETE FROM wishes WHERE wish_id = " . $wishId);
			if (!$result)
			{
				die("Could not delete wish from database: " . mysql_error());
			}
		}
		elseif ($_POST["action"] == "add")
		{
			// ***** ACTION : add wish
			if (!userOwnsWishList($_SESSION["user_id"], $listId))
			{
				die("You can not add a wish to someone else's wish list!");
			}
			// get all current reservation keys from wishes table
			$keys = array();
			$rowCount = 0;
			$result = mysql_query("SELECT reservation_key FROM wishes");
			while ($row = mysql_fetch_assoc($result))
			{
				$keys[$rowCount] = decrypt($row["reservation_key"]);
				$rowCount++;
			}
			// generate a new reservation key that does not match any of the existing ones
			do
			{
				$key = rand();
			}
			while (array_search($key, $keys) !== false);
			// save wish
			$categoryId = $_POST["category"];
			$count = $_POST["count"];
			$description = $_POST["description"];
			$link = $_POST["link"];
			$result = mysql_query("INSERT INTO wishes (wishlist_id, category_id, short_description, link,"
				. " max_reservation_count, reservation_key) VALUES (" . $listId . ", " . $categoryId
				. ", '" . wash($description) . "', '" . wash($link) . "', " . $count . ", '" . encrypt($key) . "')");
			if (!$result)
			{
				die("Could not add wish to database: " . mysql_error());
			}
		}
		$newLocation = $postbackPage . "?id=" . $listId . "#category-" . $categoryId;
	}
}

// ---------------------------------------------------------------

/*
$keys = array();
$rowCount = 0;
$result = mysql_query("select reservation_key from wishes where reservation_key <> ''");
while ($row = mysql_fetch_assoc($result))
{
	$keys[$rowCount] = decrypt($row["reservation_key"]);
	$rowCount++;
}
*/
/*
$keys = array();
for ($i = 1; $i <=850; $i++)
{
	do
	{
		$key = rand();
	}
	while (array_search($key, $keys) !== false);
	$keys[$i] = $key;
	mysql_query("update wishes set reservation_key = '" . encrypt($key) . "' where wish_id = " . $i);
}
*/

// disconnect database
dbDisconnect($connection);

// if successful, redirect to new location if supposed to
if ($redirect)
{
	redirect($newLocation);
}
else
{
	return;
}

?>
