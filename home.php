<?php
// include common functions
include_once "common.php";

header('Content-Type: text/html; charset=utf-8');
session_start();
// verify that user is logged in
if (!isset($_SESSION["user_id"]))
{
	forwardTo("index.php");
}
if (isset($_GET["action"]) || isset($_POST["action"]))
{
	// handle post back
	define('_VALID_INCLUDE', TRUE);
	include "handle_postback.php";
}

// check if any lists should be unlocked
$connection = dbConnect();
$result = mysql_query("SELECT wishlist_id, is_locked_for_edit, UNIX_TIMESTAMP(locked_until)"
	. " locked_until_timestamp FROM wishlists WHERE is_locked_for_edit = 1 ORDER BY user_id ASC, title ASC");
if (!$result)
{
	die("Could not retrieve wish lists from database: " . mysql_error());
}
$ulCount = 0;
$unlockLists = array();
$now = mktime(date("H"), date("i"), date("s"), date("n"), date("j") - 1, date("Y"), 0); // now, -1 day
while ($row = mysql_fetch_assoc($result))
{
	$lockDate = $row["locked_until_timestamp"];
	if ($lockDate < $now)
	{
		$unlockLists[$ulCount++] = $row["wishlist_id"];
	}
}

// unlock overdue lists
for ($i = 0; $i < $ulCount; $i++)
{
	$result = mysql_query("UPDATE wishlists SET is_locked_for_edit = 0,"
		. " locked_until = NULL WHERE wishlist_id = " . $unlockLists[$i]);
	if (!$result)
	{
		die("Could not update wish list with unlock information in database: " . mysql_error());
	}
}
dbDisconnect($connection);
?>

<html>
	<head>
		<link rel="stylesheet" type="text/css" href="styles/main.css">
		<link rel="stylesheet" type="text/css" href="styles/calendar.css">
		<script src="scripts/common.js" type="text/javascript"></script>
		<script src="scripts/calendar.js" type="text/javascript"></script>
		<script language="javascript">

			function highlightRow(row, modifyFirstChild)
			{
				row.style.backgroundColor = "#F6F6F6";
				if (modifyFirstChild)
				{
					row.cells[0].style.backgroundColor = "#FFFFFF";
				}
			}

			function dehighlightRow(row)
			{
				row.style.backgroundColor = "#FFFFFF";
			}

			function modifyList(actionType, listId)
			{
				document.forms["modify_list"].elements["action"].value = actionType;
				document.forms["modify_list"].elements["wishlist_id"].value = listId;
				document.forms["modify_list"].submit();
			}

			function lockList(listId)
			{
				setDialogSize("overlay", "loader", "dialog");
				if (openAjaxPage("list_dialog.php?action=lock&listId=" + listId, "dialog"))
				{
					document.getElementById("overlay").style.display = "block";
					document.getElementById("loader").style.display = "block";
				}
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
				setDialogSize("overlay", "loader", "dialog");
				if (openAjaxPage("list_dialog.php?action=edit&listId=" + listId, "dialog"))
				{
					document.getElementById("overlay").style.display = "block";
					document.getElementById("loader").style.display = "block";
				}
			}

			function addList()
			{
				setDialogSize("overlay", "loader", "dialog");
				if (openAjaxPage("list_dialog.php?action=add", "dialog"))
				{
					document.getElementById("overlay").style.display = "block";
					document.getElementById("loader").style.display = "block";
				}
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
				document.getElementById("loader").style.display = "none";
				document.getElementById("dialog").style.display = "none";
				document.getElementById("overlay").style.display = "none";
			}

		</script>
		<title>Familjens Önskelista</title>
	</head>
	<body>
		<div id="overlay">
			<img id="loader" src="images/loader.gif">
		</div>
		<div id="dialog" style="display: none;">test</div>
		<table class="main">
			<tr>
				<td class="header_back">
					<table>
						<tr>
							<td class="header"></td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td class="back">
					<table>
						<tr>
							<td class="content">
								<h1>
									Önskelistor
								</h1>
								<br>
								Välj att titta på någon önskelista eller ändra dina egna listor genom att klicka på länkarna nedan.
								Klicka på motsvarande länkar om du vill skapa en ny lista eller ta bort en befintlig lista.
								<br><br>
								Du kan låsa en lista för att göra den synlig för andra. I en låst lista kan du inte ändra eller ta bort önskningar
								och inte heller se vad andra har reserverat. Däremot kan du fortfarande lägga till nya önskningar.
								<br><br>
								<table width="100%">
									<form name="modify_list" method="post" action="home.php">
										<input name="action" type="hidden" value="">
										<input name="wishlist_id" type="hidden" value="">
									</form>
									<tr>
										<td width="50%" valign="top">
											<h2>
												Mina önskelistor
											</h2>
											<table width="100%">
												<tr><td height="9"></td></tr>
<?php
$connection = dbConnect();
$result = mysql_query("SELECT wishlist_id, wishlists.user_id, shared_with_user_id, user_name, title, is_locked_for_edit, locked_until_timestamp, is_child_list, child_name FROM ("
	. "SELECT wishlist_id, user_id, IF(shared_with_user_id IS NULL, NULL, IF(shared_with_user_id != " . $_SESSION["user_id"] . ", shared_with_user_id, user_id))"
	. " shared_with_user_id, title, is_locked_for_edit, UNIX_TIMESTAMP(locked_until) locked_until_timestamp, is_child_list, child_name FROM wishlists"
	. ($_SESSION["user_is_super"] ? "" : " WHERE user_id = " . $_SESSION["user_id"] . " OR shared_with_user_id = " . $_SESSION["user_id"])
	. ") AS wishlists INNER JOIN users ON wishlists.user_id = users.user_id AND wishlists.shared_with_user_id IS NULL OR wishlists.shared_with_user_id = users.user_id ORDER BY title ASC, child_name ASC");
if (!$result)
{
	die("Could not retrieve own wish lists from database: " . mysql_error());
}
if (mysql_num_rows($result) > 0)
{
	while ($row = mysql_fetch_assoc($result))
	{
		$childName = $row["is_child_list"] == 1 ? $row["child_name"] : "";
		$childName = strlen($childName) > 0 && substr($childName, -1) != "s" ? $childName . "s" : $childName;
		echo "	<tr onMouseOver=\"highlightRow(this, false);\" onMouseOut=\"dehighlightRow(this);\">\n";
		echo "		<td class=\"list_row_left\">\n";
		echo "			" . (($_SESSION["user_is_super"]) ? "[" . $row["user_name"] . "] " : "") . "<a href=\"list.php?id=" . $row["wishlist_id"] . "\">" . $row["title"] . "</a>";
		if (strlen($childName) > 0 || $row["shared_with_user_id"] != null)
		{
			echo " (" . (strlen($childName) > 0 ? $childName . " önskelista" : "")
				. (strlen($childName) > 0 && $row["shared_with_user_id"] != null ? ", " : "")
				. ($row["shared_with_user_id"] != null ? "delas med " . $row["user_name"] : "") . ")\n";
		}
		else
		{
			echo "\n";
		}
		echo "		</td>\n";
		echo "		<td class=\"list_row_right\">\n";
		if ($row["is_locked_for_edit"] == 0)
		{
			echo "			<a href=\"javascript:lockList(" . $row["wishlist_id"] . ")\">Lås</a>&nbsp;|&nbsp;"
				. "<a href=\"javascript:editList(" . $row["wishlist_id"] . ")\">Ändra</a>";
			echo ($row["user_id"] == $_SESSION["user_id"] ? "&nbsp;|&nbsp;<a href=\"javascript:deleteList(" . $row["wishlist_id"] . ")\">Ta bort</a>" : "") . "\n";
		}
		else
		{
			echo "			<i>Låst t.o.m. " . date("Y-m-d", $row["locked_until_timestamp"]) . "</i>\n";
		}
		echo "		</td>\n";
		echo "	</tr>\n";
	}
}
else
{
	echo "	<tr>\n";
	echo "		<td class=\"small\">\n";
	echo "			(klicka på \"Lägg till\" för att skapa en ny lista)\n";
	echo "		</td>\n";
	echo "	</tr>\n";
}
dbDisconnect($connection);
?>
											</table>
										</td>
									</tr>
									<tr>
										<td width="50%" valign="top">
											<br><br>
											<h2>
												Andras önskelistor
											</h2>
											<table width="100%">
												<tr><td height="9"></td></tr>
<?php
$connection = dbConnect();
$result = mysql_query("SELECT wishlist_id, user_id, shared_with_user_id, IF(child_name = '', user_name, child_name) user_name, title FROM ("
. "SELECT wishlist_id, user_id, shared_with_user_id, user_name, child_name, title FROM ("
. "SELECT user_x_id, user_y_id, CONCAT_WS(' & ', users_x.user_name, users_y.user_name) user_name FROM ("
. "SELECT DISTINCT IF(shared_with_user_id IS NULL OR shared_with_user_id > user_id, user_id, shared_with_user_id) user_x_id,"
. " IF(shared_with_user_id IS NULL OR shared_with_user_id > user_id, shared_with_user_id, user_id) user_y_id FROM wishlists"
. " WHERE is_locked_for_edit = 1) AS wishlists_users LEFT JOIN users AS users_x ON user_x_id = users_x.user_id"
. " LEFT JOIN users AS users_y ON user_y_id = users_y.user_id"
. " WHERE user_x_id != " . $_SESSION["user_id"] . " AND (user_y_id != " . $_SESSION["user_id"] . " OR user_y_id IS NULL))"
. " AS wishlists_user_name INNER JOIN wishlists ON user_id = user_x_id AND"
. " (shared_with_user_id = user_y_id OR shared_with_user_id IS NULL AND user_y_id IS NULL)"
. " OR user_id = user_y_id AND shared_with_user_id = user_x_id WHERE is_locked_for_edit = 1) AS wishlists_by_user_name ORDER BY user_name ASC");
if (!$result)
{
	die("Could not retrieve others' wish lists from database: " . mysql_error());
}
if (mysql_num_rows($result) > 0)
{
	$last_user_name = "";
	while ($row = mysql_fetch_assoc($result))
	{
		if ($row["user_name"] != $last_user_name)
		{
			if ($last_user_name != "")
			{
				echo "	<tr>\n";
				echo "		<td height=\"20\"></td>\n";
				echo "	</tr>\n";
			}
			echo "	<tr>\n";
			echo "		<td class=\"list_header\" colspan=\"3\">\n";
			echo "			<h3>" . $row["user_name"] . "</h3>\n";
			echo "		</td>\n";
			echo "	</tr>\n";
			$last_user_name = $row["user_name"];
		}
		echo "	<tr onMouseOver=\"highlightRow(this, true);\" onMouseOut=\"dehighlightRow(this);\">\n";
		echo "		<td width=\"5\">&nbsp;&nbsp;</td>\n";
		echo "		<td width=\"100%\" class=\"list_row_left\">\n";
		echo "			<a href=\"list.php?id=" . $row["wishlist_id"] . "\">" . $row["title"] . "</a>\n";
		echo "		</td>\n";
		echo "		<td class=\"list_row_right\">\n";
	// right stuff here
		echo "		</td>\n";
		echo "	</tr>\n";
	}
}
else
{
	echo "	<tr>\n";
	echo "		<td class=\"small\">\n";
	echo "			(det finns för närvarande inga låsta listor)\n";
	echo "		</td>\n";
	echo "	</tr>\n";
}
dbDisconnect($connection);
?>
											</table>
										</td>
									</tr>
								</table>
								<br><br><br>
								<table width="100%">
									<tr>
										<td>
											<a href="home.php?action=logout">Logga ut</a>
										</td>
										<td align="right">
											<a href="javascript:addList()">Lägg&nbsp;till</a>
										</td>
									</tr>
								</table>
<?php
/*
	foreach ($_SESSION as $key => $value)
	{
		echo $key . " = " . $value . "<br>";
	}
*/
?>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</body>
</html>
