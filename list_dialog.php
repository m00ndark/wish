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
	<table width="520" height="100">
			<tr>
				<td colspan="2">
					<h2 style="margin: 0px;">
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
				</td>
			</tr>
			<tr>
				<td valign="bottom">
					<table width="100%">
						<tr>
<?php
if ($actionIsLock)
{
?>
							<td>
							</td>
							<td class="small">
								ÅÅÅÅ-MM-DD
							</td>
						</tr>
						<tr>
							<td width="50">
								Lås&nbsp;t.o.m:&nbsp;
							</td>
							<td width="300">
								<input name="lock_date" type="text" style="width: 100px">
								<img title="&#214;ppna Kalender" class="tcalIcon" onclick="A_TCALS['calendar'].f_toggle()" id="tcalico_calendar" src="images/calendar/cal.gif">
							</td>
<?php
}
else
{
?>
							<td>
							</td>
							<td class="small">
								Använd inte ditt eller ditt barns namn i listans titel.
							</td>
						</tr>
						<tr>
							<td>
								Titel:&nbsp;
							</td>
							<td>
								<input name="title" type="text" value="<?php if ($actionIsEdit) { echo $title; } ?>" style="width: 300px">
							</td>
						</tr>
						<tr>
							<td height="5">
							</td>
						</tr>
						<tr>
							<td colspan="2" valign="top">
								<input name="share" type="checkbox" onclick="enableShareList();"
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
>&nbsp;Gemensam&nbsp;lista&nbsp;med:&nbsp;
								<select name="shared_with_user_id" style="width: 150px"<?php if ($sharedWithUserId == null || $sharedWithUserId < 0 || $actionIsEdit && $userId != $_SESSION['user_id']) { echo ' disabled'; } ?>>
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
							</td>
						</tr>


						<tr>
							<td height="5">
							</td>
						</tr>
						<tr>
							<td colspan="2" valign="top">
								<input name="child_list" type="checkbox" onclick="enableChildList();"
<?php
if ($isChildList == 1)
{
	echo ' checked';
}
?>
>&nbsp;Barnönskelista&nbsp;tillhörande:&nbsp;
								<input name="child_name" type="text" value="<?php if ($actionIsEdit) { echo $childName; } ?>" style="width: 150px"<?php if ($isChildList == 0) { echo ' disabled'; } ?>>
							</td>
<?php
}
?>
						</tr>




					</table>
				</td>
				<td valign="bottom" align="right">
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
&nbsp;|&nbsp;<a href="javascript:cancelDialog()">Avbryt</a>
				</td>
			</tr>
	</table>
</form>
