<?php
include_once "db.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

function getTypeDefs()
{
	return [

		"list" => ["wishlists", "wishlist_id", [
			"wishlist_id" => ["id", false],
			"user_id" => ["userId", false],
			"title" => ["title", true],
			"is_locked_for_edit" => ["isLockedForEdit", false],
			"locked_until" => ["lockedUntil", false],
			"shared_with_user_id" => ["sharedWithUserId", false],
			"is_child_list" => ["isChildList", false],
			"child_name" => ["childName", true]]],

		"wish" => ["wishes", "wish_id", [
			"wish_id" => ["id", false],
			"wishlist_id" => ["listId", false],
			"category_id" => ["categoryId", false],
			"modify_date" => ["modifyDate", NULL],
			"short_description" => ["shortDescription", true],
			"link" => ["link", true],
			"max_reservation_count" => ["maxReservationCount", false],
			"reservation_key" => ["reservationKey", true]]],

		"user" => ["users", "user_id", [
			"user_id" => ["id", false],
			"user_name" => ["userName", true],
			"email" => ["email", true],
			"password" => ["password", true],
			"recovery_code" => ["recoveryCode", true],
			"recovery_valid_until" => ["recoveryValidUntil", true]]],

		"reservation" => ["reservations", "reservation_id", [
			"reservation_id" => ["id", false],
			"key" => ["key", true],
			"reserve_date" => ["reserveDate", true],
			"reserved_by_user_id" => ["reservedByUserId", true]]],

		"category" => ["categories", "category_id", [
			"category_id" => ["id", false],
			"name" => ["name", true]]]

		];
}

function getMethod()
{
	return $_SERVER["REQUEST_METHOD"];
}

function getBody()
{
	return file_get_contents("php://input");
}

function getItem($itemType, $id)
{
	$typeDefs = getTypeDefs();

	$idColName = $typeDefs[$itemType][1];
	$cols = array();
	foreach ($typeDefs[$itemType][2] as $colName => $colInfo)
	{
		$cols[] = "`" . $colName . "` `" . $colInfo[0] . "`";
	}
	$table = $typeDefs[$itemType][0];
	$query = "SELECT " . implode(", ", $cols) . " FROM `" . $table . "` WHERE `" . $idColName . "` = " . $id;
	return executeSingle($query);
}

function getItems($itemType)
{
	$typeDefs = getTypeDefs();

	$idColName = $typeDefs[$itemType][1];
	$cols = array();
	foreach ($typeDefs[$itemType][2] as $colName => $colInfo)
	{
		$cols[] = "`" . $colName . "` `" . $colInfo[0] . "`";
	}
	$table = $typeDefs[$itemType][0];
	$query = "SELECT " . implode(", ", $cols) . " FROM `" . $table . "` ORDER BY `" . $idColName . "` ASC";
	return executeQuery($query);
}

function addItem($itemType, $item)
{
	$typeDefs = getTypeDefs();

	$idColName = $typeDefs[$itemType][1];
	$id = $item[$typeDefs[$itemType][2][$idColName][0]];
	if (isset($id))
	{
		http_response_code(400);
		die("Item must not contain '" . $typeDefs[$itemType][2][$idColName][0] . "'");
	}
	$cols = array();
	$vals = array();
	foreach (array_keys($typeDefs[$itemType][2]) as $colName)
	{
		$colInfo = $typeDefs[$itemType][2][$colName];
		if ($colName != $idColName && !is_null($colInfo[1]))
		{
			$cols[] = "`" . $colName . "`";
			$value = $item[$colInfo[0]];
			$vals[] = is_null($value) ? "NULL" : ($colInfo[1] ? "'" . $value . "'" : $value);
		}
	}
	$table = $typeDefs[$itemType][0];
	$query = "INSERT INTO `" . $table . "` (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $vals) . ")";
	return executeNonQuery($query, true);
}

function updateItem($itemType, $item)
{
	$typeDefs = getTypeDefs();

	$idColName = $typeDefs[$itemType][1];
	$cols = array();
	foreach (array_keys($typeDefs[$itemType][2]) as $colName)
	{
		$colInfo = $typeDefs[$itemType][2][$colName];
		if ($colName != $idColName && !is_null($colInfo[1]))
		{
			$value = $item[$colInfo[0]];
			if (isset($value))
			{
				$cols[] = "`" . $colName . "` = " . (is_null($value) ? "NULL" : ($colInfo[1] ? "'" . $value . "'" : $value));
			}
		}
	}
	$table = $typeDefs[$itemType][0];
	$id = $item[$typeDefs[$itemType][2][$idColName][0]];
	if (!isset($id))
	{
		http_response_code(400);
		die("Item must contain '" . $typeDefs[$itemType][2][$idColName][0] . "'");
	}
	$query = "UPDATE `" . $table . "` SET " . implode(", ", $cols) . " WHERE `" . $idColName . "` = " . $id;
	executeNonQuery($query);
	return $id;
}

function deleteItem($itemType, $id)
{
	$typeDefs = getTypeDefs();

	$idColName = $typeDefs[$itemType][1];
	$table = $typeDefs[$itemType][0];
	$query = "DELETE FROM `" . $table . "` WHERE `" . $idColName . "` = " . $id;
	executeNonQuery($query);
}

function requireItems($array, $count)
{
	if (count($array) != $count)
	{
		http_response_code(400);
		die();
	}
}

function requireMaxItems($array, $maxCount)
{
	if (count($array) > $maxCount)
	{
		http_response_code(400);
		die();
	}
}



if (!isset($_GET["path"]))
{
	http_response_code(400);
	die();
}

$path = trim($_GET["path"], "/");
$pathItems = explode("/", $path);

$typeDefs = getTypeDefs();
if (!array_key_exists($pathItems[0], $typeDefs))
{
	http_response_code(400);
	die();
}

//echo $path;

if (getMethod() == "GET")
{
	requireMaxItems($pathItems, 2);
	$id = $pathItems[1];
	if (isset($id))
	{
		$response = getItem($pathItems[0], $id);
	}
	else
	{
		$response = getItems($pathItems[0]);
	}
}
elseif (getMethod() == "POST")
{
	requireItems($pathItems, 1);
	$item = json_decode(getBody(), true);
	$id = addItem($pathItems[0], $item);
	$response = getItem($pathItems[0], $id);
	http_response_code(201);
}
elseif (getMethod() == "PUT")
{
	requireItems($pathItems, 1);
	$item = json_decode(getBody(), true);
	$id = updateItem($pathItems[0], $item);
	$response = getItem($pathItems[0], $id);
}
elseif (getMethod() == "DELETE")
{
	requireItems($pathItems, 2);
	deleteItem($pathItems[0], $pathItems[1]);
}

if (isset($response))
{
	echo json_encode($response);
}

?>
