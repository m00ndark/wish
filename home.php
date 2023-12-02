<?php
header('Content-Type: text/html; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ini_set('display_errors', '1');

session_start();

// include common functions
include_once 'common.php';

// verify that user is logged in
if (!isset($_SESSION['user_id']))
{
	forwardTo('index.php');
}
if (isset($_GET['action']) || isset($_POST['action']))
{
	// handle post back
	define('_VALID_INCLUDE', TRUE);
	include 'handle_postback.php';
}

$userId = $_SESSION['user_id'];
$userIsSuper = $_SESSION['user_is_super'];

// check if any lists should be unlocked
$connection = dbConnect();
$yesterday = mktime(date('H'), date('i'), date('s'), date('n'), date('j') - 1, date('Y')); // now - 1 day
$unlockLists = [];
try
{
	$result = dbExecute($connection, 'SELECT wishlist_id, is_locked_for_edit, UNIX_TIMESTAMP(locked_until)'
		. ' locked_until_timestamp FROM wishlists WHERE is_locked_for_edit = 1 ORDER BY user_id ASC, title ASC');
	while ($row = dbFetch($result))
	{
		$lockDate = $row->locked_until_timestamp;
		if ($lockDate < $yesterday)
		{
			array_push($unlockLists, $row->wishlist_id);
		}
	}
}
catch (PDOException $ex)
{
	die('Could not retrieve wish lists from database: ' . $ex->getMessage());
}

// unlock overdue lists
foreach ($unlockLists as $unlockListId)
{
	try
	{
		dbExecute($connection, 'UPDATE wishlists SET is_locked_for_edit = 0, locked_until = NULL WHERE wishlist_id = :wishlistId',
			[':wishlistId' => $unlockListId]);
	}
	catch (PDOException $ex)
	{
		die('Could not update wish list with unlock information in database: ' . $ex->getMessage());
	}
}
dbDisconnect($connection);
?>

<html>
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<meta name="theme-color" content="#2F2F2F"/>
		<link rel="icon" type="image/png" href="images/favicon.png?timestamp=<?php echo time()?>">
		<link rel="stylesheet" type="text/css" href="styles/main.css?timestamp=<?php echo time()?>"/>
		<link rel="stylesheet" type="text/css" href="styles/calendar.css?timestamp=<?php echo time()?>"/>
		<script src="scripts/common.js?timestamp=<?php echo time()?>" type="text/javascript"></script>
		<script src="scripts/calendar.js?timestamp=<?php echo time()?>" type="text/javascript"></script>
		<script language="javascript">

			function modifyList(actionType, listId)
			{
				document.forms["modify_list"].elements["action"].value = actionType;
				document.forms["modify_list"].elements["wishlist_id"].value = listId;
				document.forms["modify_list"].submit();
			}

			function lockList(listId)
			{
				showDialog("list_dialog.php?action=lock&listId=" + listId);
			}

			function deleteList(listId)
			{
				if (window.confirm("Klicka på OK om du är säker på att du vill ta bort önskelistan."))
				{
					modifyList("delete", listId);
				}
			}

			function editList(listId)
			{
				showDialog("list_dialog.php?action=edit&listId=" + listId);
			}

			function addList()
			{
				showDialog("list_dialog.php?action=add")
			}

			function enableShareList()
			{
				var sharedWithUserId = document.forms["list_dialog"].elements["shared_with_user_id"];
				var share = document.forms["list_dialog"].elements["share"];
				sharedWithUserId.disabled = !share.checked;
				if (!share.checked) sharedWithUserId.selectedIndex = 0;
			}

			function enableChildList()
			{
				var childName = document.forms["list_dialog"].elements["child_name"];
				var childList = document.forms["list_dialog"].elements["child_list"];
				childName.disabled = !childList.checked;
				if (!childList.checked) childName.value = "";
			}

			function doCustomDialogAction()
			{
				// initialize calendar if action is "lock"
				if (document.forms["list_dialog"].elements["action"].value == "lock")
				{
					new tcal(
					{
						"formname" : "list_dialog",
						"controlname" : "lock_date",
						"id" : "calendar"
					},
					{
						"months" : ["Januari", "Februari", "Mars", "April", "Maj", "Juni", "Juli", "Augusti", "September", "Oktober", "November", "December"],
						"weekdays" : ["Sö", "Må", "Ti", "On", "To", "Fr", "Lö"],
						"yearscroll" : true,
						"weekstart" : 1,
						"centyear" : 70,
						"imgpath" : "images/calendar/"
					});
				}
			}

			function submitDialog()
			{
				var action = document.forms["list_dialog"].elements["action"];
				if (action.value == "lock")
				{
					var lockDate = document.forms["list_dialog"].elements["lock_date"];
					if (!A_TCALS["calendar"].f_parseDate(lockDate.value))
					{
						return;
					}
					else
					{
						var lockedUntil = new Date();
						lockedUntil.setFullYear(parseInt(lockDate.value.substr(0, 4), 10));
						lockedUntil.setMonth(parseInt(lockDate.value.substr(5, 2), 10) - 1); // -1 since setMonth counts 0-11
						lockedUntil.setDate(parseInt(lockDate.value.substr(8, 2), 10));
						var now = new Date();
						if (lockedUntil <= now)
						{
							window.alert("Vänligen ange ett datum i framtiden.");
							return;
						}
						if (!window.confirm("Klicka på OK om du är säker på att du vill låsa önskelistan.\n\n"
							+ "När du låst önskelistan kan du inte längre ändra eller ta bort befintliga önskningar, "
							+ "du kan endast lägga till nya. Listan låses automatiskt upp igen efter datumet du angivit."))
						{
							return;
						}
					}
				}
				else if (action.value != "lock")
				{
					if (document.forms["list_dialog"].elements["title"].value == "")
					{
						window.alert("Vänligen ange en titel på din önskelista.");
						return;
					}
					if (document.forms["list_dialog"].elements["child_list"].checked && document.forms["list_dialog"].elements["child_name"].value == "")
					{
						window.alert("Vänligen ange namnet på barnet som önskelistan ska tillhöra.");
						return;
					}
					document.forms["list_dialog"].elements["shared_with_user_id"].disabled = false;
				}
				document.forms["list_dialog"].submit();
			}

			function cancelDialog()
			{
				var action = document.forms["list_dialog"].elements["action"].value;
				if (action == "lock")
				{
					A_TCALS["calendar"].f_hide();
				}
				closeDialog();
			}

		</script>
		<title>Familjens Önskelista</title>
	</head>
	<body>
		<div id="overlay">
			<img id="loader" src="images/loader.gif">
		</div>
		<div class="dialog" id="dialog"></div>

		<form name="modify_list" method="post" action="home.php">
			<input name="action" type="hidden" value="">
			<input name="wishlist_id" type="hidden" value="">
		</form>

		<div class="row-header">
			<div class="col-center header">
				Familjens Önskelista
			</div>
		</div>

		<div class="row">
			<div class="col-center content">
				<h1>
					Önskelistor
				</h1>
				<p>
					Välj att titta på någon önskelista eller ändra dina egna listor genom att klicka på länkarna nedan.
					Klicka på motsvarande länkar om du vill skapa en ny lista eller ta bort en befintlig lista.
				</p>
				<p>
					Du kan låsa en lista för att göra den synlig för andra. I en låst lista kan du inte ändra eller ta bort önskningar
					och inte heller se vad andra har reserverat. Däremot kan du fortfarande lägga till nya önskningar.
				</p>

				<div class="row">
					<div class="col-12">
						<h2>
							Mina önskelistor
						</h2>
					</div>
				</div>

<?php
$connection = dbConnect();
try
{
	$result = dbExecute($connection,
		'SELECT wishlist_id, wishlists.user_id, shared_with_user_id, user_name, title, is_locked_for_edit, locked_until_timestamp, is_child_list, child_name FROM ('
			. 'SELECT wishlist_id, user_id, IF(shared_with_user_id IS NULL, NULL, IF(shared_with_user_id != :userId, shared_with_user_id, user_id))'
			. ' shared_with_user_id, title, is_locked_for_edit, UNIX_TIMESTAMP(locked_until) locked_until_timestamp, is_child_list, child_name FROM wishlists'
			. ($userIsSuper ? '' : ' WHERE user_id = :userId OR shared_with_user_id = :userId')
			. ') AS wishlists INNER JOIN users ON wishlists.user_id = users.user_id AND wishlists.shared_with_user_id IS NULL OR wishlists.shared_with_user_id = users.user_id ORDER BY title ASC, child_name ASC',
		[':userId' => $userId]);

	$gotRows = false;
	while ($row = dbFetch($result))
	{
		$gotRows = true;
		$childName = $row->is_child_list == 1 ? $row->child_name : '';
		$childName = strlen($childName) > 0 && substr($childName, -1) != 's' ? $childName . 's' : $childName;
		echo "	<div class=\"row-list hover\">\n";
		echo "		<div class=\"auto-col\">\n";
		echo '			' . (($userIsSuper) ? '[' . $row->user_name . '] ' : '') . '<a href="list.php?id=' . $row->wishlist_id . '">' . $row->title . '</a>';
		if (strlen($childName) > 0 || $row->shared_with_user_id != null)
		{
			echo ' (' . (strlen($childName) > 0 ? $childName . ' önskelista' : '')
				. (strlen($childName) > 0 && $row->shared_with_user_id != null ? ', ' : '')
				. ($row->shared_with_user_id != null ? 'delas med ' . $row->user_name : '') . ")\n";
		}
		else
		{
			echo "\n";
		}
		echo "		</div>\n";
		echo "		<div class=\"auto-col right\">\n";
		if ($row->is_locked_for_edit == 0)
		{
			echo '			<button type="button" onClick="lockList(' . $row->wishlist_id . ')">Lås</button>&nbsp;'
				. '<button type="button" onClick="editList(' . $row->wishlist_id . ')">Ändra</button>';
			echo ($row->user_id == $userId ? '&nbsp;<button type="button" onClick="deleteList(' . $row->wishlist_id . ')">Ta bort</button>' : '') . "\n";
		}
		else
		{
			echo '			<i>Låst t.o.m. ' . date('Y-m-d', $row->locked_until_timestamp) . "</i>\n";
		}
		echo "		</div>\n";
		echo "	</div>\n";
	}

	if (!$gotRows)
	{
		echo "	<div class=\"row\">\n";
		echo "		<div class=\"col-12 small\">\n";
		echo "			(klicka på \"Lägg till\" för att skapa en ny lista)\n";
		echo "		</div>\n";
		echo "	</div>\n";
	}
}
catch (PDOException $ex)
{
	die('Could not retrieve own wish lists from database: ' . $ex->getMessage());
}
dbDisconnect($connection);
?>

				<div class="row">
					<div class="col-12">
						<h2>
							Andras önskelistor
						</h2>
					</div>
				</div>

				<div class="row">

<?php
$connection = dbConnect();
try
{
	$result = dbExecute($connection,
		'SELECT wishlist_id, user_id, shared_with_user_id, IF(child_name = \'\', user_name, child_name) user_name, title FROM ('
			. 'SELECT wishlist_id, user_id, shared_with_user_id, user_name, child_name, title FROM ('
			. 'SELECT user_x_id, user_y_id, CONCAT_WS(\' & \', users_x.user_name, users_y.user_name) user_name FROM ('
			. 'SELECT DISTINCT IF(shared_with_user_id IS NULL OR shared_with_user_id > user_id, user_id, shared_with_user_id) user_x_id,'
			. ' IF(shared_with_user_id IS NULL OR shared_with_user_id > user_id, shared_with_user_id, user_id) user_y_id FROM wishlists'
			. ' WHERE is_locked_for_edit = 1) AS wishlists_users LEFT JOIN users AS users_x ON user_x_id = users_x.user_id'
			. ' LEFT JOIN users AS users_y ON user_y_id = users_y.user_id'
			. ' WHERE user_x_id != :userId AND (user_y_id != :userId OR user_y_id IS NULL))'
			. ' AS wishlists_user_name INNER JOIN wishlists ON user_id = user_x_id AND'
			. ' (shared_with_user_id = user_y_id OR shared_with_user_id IS NULL AND user_y_id IS NULL)'
			. ' OR user_id = user_y_id AND shared_with_user_id = user_x_id WHERE is_locked_for_edit = 1) AS wishlists_by_user_name ORDER BY user_name ASC',
		[':userId' => $userId]);

	$last_user_name = '';
	while ($row = dbFetch($result))
	{
		if ($row->user_name != $last_user_name)
		{
			if ($last_user_name != '')
			{
				echo "			</div>\n";
				echo "		</div>\n";
			}
			echo "		<div class=\"col-6\">\n";
			echo "			<div class=\"bubble hover\">\n";
			echo '				<h3>' . $row->user_name . "</h3>\n";
			$last_user_name = $row->user_name;
		}
		else
		{
			echo "				<br>\n";
		}
		echo '				<a href="list.php?id=' . $row->wishlist_id . '">' . $row->title . "</a>\n";
	}

	if ($last_user_name != '')
	{
		echo "			</div>\n";
		echo "		</div>\n";
	}
	else
	{
		echo "		<div class=\"col-12 small\">\n";
		echo "			(det finns för närvarande inga låsta listor)\n";
		echo "		</td>\n";
	}
}
catch (PDOException $ex)
{
	die('Could not retrieve others\' wish lists from database: ' . $ex->getMessage());
}
dbDisconnect($connection);
?>

				</div>

				<div class="row-footer">
					<div class="auto-col">
						<button type="button" onClick="goto('home.php?action=logout')">Logga ut</button>
					</div>
					<div class="auto-col right">
						<button type="button" onClick="addList()">Lägg&nbsp;till</button>
					</div>
				</div>

<?php
/*
	foreach ($_SESSION as $key => $value)
	{
		echo $key . ' = ' . $value . '<br>';
	}
*/
?>

			</div>
		</div>
	</body>
</html>
