<?php
header('Content-Type: text/html; charset=utf-8');

session_start();

// verify that user is logged in
if (!isset($_SESSION['user_id']))
{
	include 'auth_failed.php';
}

// include common functions
include_once 'common.php';

// handle input
$action = $_GET['action'];
$actionIsReserve = ($action == 'reserve');
$actionIsEdit = ($action == 'edit');
$actionIsAdd = ($action == 'add');
$listId = $_GET['listId'];
if ($actionIsReserve)
{
	$wishId = $_GET['wishId'];
	$connection = dbConnect();
	try
	{
		$result = dbExecute($connection,
			'SELECT wish_id, short_description, max_reservation_count, reservation_key, user_id, shared_with_user_id'
				. ' FROM wishes INNER JOIN wishlists ON wishlists.wishlist_id = wishes.wishlist_id WHERE wish_id = :wishId',
			[':wishId' => $wishId]);
		
		$row = dbFetch($result);
	}
	catch (PDOException $ex)
	{
		die('Could not retrieve wish information from database: ' . $ex->getMessage());
	}
	$description = $row->short_description;
	$maxReservationCount = $row->max_reservation_count;
	$reservationKey = decrypt($row->reservation_key);
	$listUserId = $row->user_id;
	$listSharedUserId = $row->shared_with_user_id;
	$reservationCount = 0;
	try
	{
		$result = dbExecute($connection, 'SELECT reservation_id, `key`, reserved_by_user_id FROM reservations');
		while ($row = dbFetch($result))
		{
			$key = decrypt($row->key);
			if ($key == $reservationKey)
				$reservationCount++;
		}
	}
	catch (PDOException $ex)
	{
		die('Could not retrieve reservation information from database: ' . $ex->getMessage());
	}
	dbDisconnect($connection);
}
elseif ($actionIsEdit)
{
	$wishId = $_GET['wishId'];
	$connection = dbConnect();
	try
	{
		$result = dbExecute($connection,
			 'SELECT wish_id, category_id, short_description, link, max_reservation_count FROM wishes'
				. ' INNER JOIN wishlists ON wishlists.wishlist_id = wishes.wishlist_id WHERE wish_id = :wishId',
			[':wishId' => $wishId]);

		$row = dbFetch($result);
	}
	catch (PDOException $ex)
	{
		die('Could not retrieve wish information from database: ' . $ex->getMessage());
	}
	dbDisconnect($connection);
	$categoryId = $row->category_id;
	$description = $row->short_description;
	$link = $row->link;
	$maxReservationCount = $row->max_reservation_count;
}
elseif ($actionIsAdd)
{
	$categoryId = $_GET['categoryId'];
}
?>
<form name="wish_dialog" method="post" action="list.php">
	<input name="action" type="hidden" value="<?php echo $action; ?>">
	<input name="wishlist_id" type="hidden" value="<?php echo $listId; ?>">
	<input name="wish_id" type="hidden" value="<?php if ($actionIsReserve || $actionIsEdit) { echo $wishId; } ?>">
	<table width="520" height="110" border="0">
			<tr>
				<td colspan="2">
					<h2 style="margin: 0px;">
<?php
if ($actionIsReserve)
{
	echo 'Reservera önskningen';
}
elseif ($actionIsEdit)
{
	echo 'Ändra önskan';
}
else
{
	echo 'Ny önskan';
}
?>
					</h2>
				</td>
			</tr>
			<tr>
<?php
if ($actionIsReserve)
{
?>
				<td colspan="2" valign="bottom">
					<i><?php echo $description; ?></i>
				</td>
			</tr>
			<tr>
				<td valign="bottom">
					<table width="100%">
						<tr>
							<td width="50">
								Antal:&nbsp;
							</td>
							<td>
								<select name="count" style="width: 40px">
<?php
	if ($maxReservationCount == -1)
	{
		echo "<option value=\"1\">*</option>\n";
	}
	else
	{
		for ($i = 1; $i <= ($maxReservationCount - $reservationCount); $i++)
		{
			echo '<option value="' . $i . '">' . $i . "</option>\n";
		}
	}
?>
								</select>
							</td>
						</tr>
<?php
} // $actionIsReserve
else
{
?>
				<td valign="bottom">
					<table>
						<tr>
							<td>
								Kategori:&nbsp;
							</td>
							<td>
								<select name="category" style="width: 150px">
<?php
	if (!$actionIsEdit)
	{
		echo "<option value=\"-1\"></option>\n";
	}
	$connection = dbConnect();
	try
	{
		$result = dbExecute($connection, 'SELECT category_id, name FROM categories ORDER BY category_id ASC');
		while ($row = dbFetch($result))
		{
			echo '<option value="' . $row->category_id . '"';
			if (($actionIsEdit || $actionIsAdd) && $row->category_id == $categoryId)
			{
				echo ' selected="true"';
			}
			echo '>' . $row->name . "</option>\n";
		}
	}
	catch (PDOException $ex)
	{
		die('Could not retrieve categories from database: ' . $ex->getMessage());
	}
	dbDisconnect($connection);
?>
								</select>
							</td>
							<td>
							</td>
							<td align="right">
								Antal:&nbsp;
							</td>
							<td align="right">
								<select name="count" style="width: 40px">
<?php
	echo '<option value="-1"' . ($actionIsEdit && $maxReservationCount == -1 ? ' selected="true"' : '') . ">*</option>\n";
	for ($i = 1; $i <= 15; $i++)
	{
		echo '<option value="' . $i . '"';
		if ($actionIsEdit && $i == $maxReservationCount || !$actionIsEdit && $i == 1)
		{
			echo ' selected="true"';
		}
		echo '>' . $i . "</option>\n";
	}
?>
								</select>
							</td>
						</tr>
						<tr>
							<td>
								Beskrivning:&nbsp;
							</td>
							<td colspan="4">
								<input name="description" id="focus" type="text" value="<?php if ($actionIsEdit) { echo wash($description); } ?>" style="width: 300px">
							</td>
						</tr>
						<tr>
							<td>
								Länk:&nbsp;
							</td>
							<td colspan="4">
								<input name="link" type="text" value="<?php if ($actionIsEdit) { echo wash($link); } ?>" style="width: 300px">
							</td>
						</tr>
<?php
} // !$actionIsReserve
?>
					</table>
				</td>
				<td valign="bottom" align="right">
<?php
echo '<a href="javascript:submitDialog()">'
	. ($actionIsReserve ? 'Reservera' : ($actionIsEdit ? 'Ändra' : 'Lägg&nbsp;till'))
	. "</a>&nbsp;|&nbsp;<a href=\"javascript:cancelDialog()\">Avbryt</a>\n";
?>
				</td>
			</tr>
	</table>
</form>
