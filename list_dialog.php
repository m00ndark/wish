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
$actionIsEdit = ($action == 'edit');
$actionIsLock = ($action == 'lock');
if ($actionIsEdit || $actionIsLock)
{
	$listId = $_GET['listId'];
}
if ($actionIsEdit)
{
	$connection = dbConnect();
	try
	{
		$result = dbExecute($connection,
			'SELECT wishlist_id, title, user_id, shared_with_user_id, is_child_list, child_name FROM wishlists WHERE wishlist_id = :wishlistId',
			[':wishlistId' => $listId]);

		$row = dbFetch($result);
		$title = $row->title;
		$userId = $row->user_id;
		$sharedWithUserId = $row->shared_with_user_id;
		$isChildList = $row->is_child_list;
		$childName = $row->child_name;
	}
	catch (PDOException $ex)
	{
		die('Could not retrieve wish list information from database: ' . $ex->getMessage());
	}
	dbDisconnect($connection);
}
?>
<form name="list_dialog" method="post" action="home.php">
	<input name="action" type="hidden" value="<?php echo $action; ?>">
	<input name="wishlist_id" type="hidden" value="<?php if ($actionIsEdit || $actionIsLock) { echo $listId; } ?>">
	<div class="row">
		<div class="col-12">
			<h2 style="margin-top: 0px;">
<?php
if ($actionIsEdit)
{
	echo 'Ändra önskelista';
}
elseif ($actionIsLock)
{
	echo 'Lås önskelista';
}
else
{
	echo 'Ny önskelista';
}
?>
			</h2>
		</div>
	</div>

<?php
if ($actionIsLock)
{
?>
	<div class="auto-row">
		<div class="input-label">
			Till och med:
		</div>
		<div>
			<span class="small">ÅÅÅÅ-MM-DD</span>
			<br>
			<input name="lock_date" type="text" style="width: 10rem;">
		</div>
		<div>
			<img title="&#214;ppna Kalender" class="tcalIcon" style="width: 1.25rem; margin: 0.125rem" onclick="A_TCALS['calendar'].f_toggle()" id="tcalico_calendar" src="images/calendar/cal.gif">
		</div>
	</div>
<?php
}
else
{
?>
	<div class="auto-row">
		<div class="input-label">
			Titel:
		</div>
		<div class="auto-col">
			<span class="small">Använd inte ditt eller ditt barns namn i listans titel.</span>
			<br>
			<input name="title" id="focus" type="text" value="<?php if ($actionIsEdit) { echo $title; } ?>">
		</div>
	</div>
	<div class="auto-row">
		<div>
			<input name="share" id="is_shared" type="checkbox" onclick="enableShareList();"
<?php
if ($sharedWithUserId > -1)
{
	echo ' checked';
}
if ($actionIsEdit && $userId != $_SESSION['user_id'])
{
	echo ' disabled';
}
?>
>
		</div>
		<div class="input-label">
			<label for="is_shared">Gemensam lista med:</label>
		</div>
		<div class="auto-col">
			<select name="shared_with_user_id"<?php if ($sharedWithUserId == null || $sharedWithUserId < 0 || $actionIsEdit && $userId != $_SESSION['user_id']) { echo ' disabled'; } ?>>
				<option value="-1"></option>
<?php
$connection = dbConnect();
try
{
	$result = dbExecute($connection, 'SELECT user_id, user_name FROM users WHERE user_id != :userId ORDER BY user_name ASC',
		[':userId' => $_SESSION['user_id']]);
	while ($row = dbFetch($result))
	{
		echo '<option value="' . $row->user_id . '"';
		if ($actionIsEdit && ($row->user_id == $sharedWithUserId || $row->user_id == $userId))
		{
			echo ' selected="true"';
		}
		echo '>' . $row->user_name . "</option>\n";
	}
}
catch (PDOException $ex)
{
	die('Could not retrieve wish list information from database: ' . $ex->getMessage());
}
dbDisconnect($connection);
?>
			</select>
		</div>
	</div>
	<div class="auto-row">
		<div>
			<input name="child_list" id="is_child" type="checkbox" onclick="enableChildList();"
<?php
if ($isChildList == 1)
{
	echo ' checked';
}
?>
>
		</div>
		<div class="input-label">
			<label for="is_child">Barnlista tillhörande:</label>
		</div>
		<div class="auto-col">
			<input name="child_name" type="text" value="<?php if ($actionIsEdit) { echo $childName; } ?>"<?php if ($isChildList == 0) { echo ' disabled'; } ?>>
		</div>
	</div>
<?php
}
?>
	<div class="row-footer" style="padding-bottom: 0px;">
		<div class="auto-col">
			<a href="javascript:submitDialog()">
<?php
if ($actionIsEdit)
{
	echo 'Ändra</a>';
}
elseif ($actionIsLock)
{
	echo 'Lås</a>';
}
else
{
	echo 'Lägg&nbsp;till</a>';
}
?>
		</div>
		<div class="auto-col right">
			<a href="javascript:cancelDialog()">Avbryt</a>
		</div>
	</div>
</form>
