<?php
header('Content-Type: text/html; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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
	<div class="row">
		<div class="col-12">
			<h2 style="margin-top: 0px;">
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
		</div>
	</div>

<?php
if ($actionIsReserve)
{
?>
	<div class="auto-row">
		<i><?php echo $description; ?></i>
	</div>
	<div class="auto-row">
		<div class="input-label">
			Antal:
		</div>
		<div class="auto-col">
			<select name="count" style="width: 4rem;">
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
		</div>
	</div>
<?php
} // $actionIsReserve
else
{
?>
	<div class="row no-padding">
		<div class="col-12 grid">
			<div class="cell-category input-label">
				Kategori:
			</div>
			<div class="cell-category-value">
				<select name="category">
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
			</div>
			<div class="cell-space"></div>
			<div class="cell-count input-label">
				Antal:
			</div>
			<div class="cell-count-value">
				<select name="count">
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
			</div>
			<div class="cell-description input-label">
				Beskrivning:
			</div>
			<div class="cell-description-value-5">
				<input name="description" id="focus" type="text" value="<?php if ($actionIsEdit) { echo wash($description); } ?>">
			</div>
			<div class="cell-link input-label">
				Länk:
			</div>
			<div class="cell-link-value-5">
				<input name="link" type="text" value="<?php if ($actionIsEdit) { echo wash($link); } ?>">
			</div>
		</div>
	</div>
<?php
} // !$actionIsReserve
?>
	<div class="row-footer" style="padding-bottom: 0px;">
		<div class="col-12 right">
			<button type="button" class="primary" onClick="submitDialog()">
<?php
if ($actionIsReserve)
{
	echo 'Reservera';
}
elseif ($actionIsEdit)
{
	echo 'Ändra';
}
else
{
	echo 'Lägg&nbsp;till';
}
?>
			</button>&nbsp;<button type="button" onClick="cancelDialog()">Avbryt</button>
		</div>
	</div>
</form>
