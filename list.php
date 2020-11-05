<?php
header('Content-Type: text/html; charset=utf-8');

// ini_set('display_errors', '1');

session_start();

// include common functions
include_once 'common.php';

// verify that user is logged in
if (!isset($_SESSION['user_id']))
{
	forwardTo('index.php');
}

// show page in print mode? .. set to false as default
$printMode = false;

if (isset($_POST['action']) || isset($_GET['action']))
{
	// handle post back
	define('_VALID_INCLUDE', TRUE);
	include 'handle_postback.php';
}

if (!isset($_GET['id']))
{
	die('Wish list ID missing!');
}
$listId = $_GET['id'];
// retrieve list information
$connection = dbConnect();
try
{
	$result = dbExecute($connection,
		 'SELECT wishlist_id, users.user_id, shared_with_user_id, user_name, title, is_locked_for_edit,'
			. ' UNIX_TIMESTAMP(locked_until) locked_until_timestamp, is_child_list, child_name FROM wishlists'
			. ' INNER JOIN users ON wishlists.user_id = users.user_id WHERE wishlist_id = :wishlistId',
		 [':wishlistId' => $listId]);
	$row = dbFetch($result);
}
catch (PDOException $ex)
{
	die('Could not retrieve wish list information from database: ' . $ex->getMessage());
}
$listUserId = $row->user_id;
$listSharedUserId = $row->shared_with_user_id;
$listUserName = $row->user_name;
$listTitle = $row->title;
$listIsLocked = ($row->is_locked_for_edit == 1);
$listLockedUntil = $row->locked_until_timestamp;
$listIsChildList = ($row->is_child_list == 1);
$listChildName = $row->child_name;
$listChildName = strlen($listChildName) > 0 && substr($listChildName, -1) != 's' ? $listChildName . 's' : $listChildName;
$myList = ($listUserId == $_SESSION['user_id'] || $listSharedUserId == $_SESSION['user_id']);
$unauthorizedCall = (!$myList && !$listIsLocked && !$_SESSION['user_is_super']);
$listIsShared = ($listSharedUserId != null);
if ($listIsShared)
{
	try
	{
		$result = dbExecute($connection, 'SELECT user_id, user_name FROM users WHERE user_id = :userId',
			[':userId' => $listSharedUserId]);
		$row = dbFetch($result);
	}
	catch (PDOException $ex)
	{
		die('Could not retrieve shared wish list information from database: ' . $ex->getMessage());
	}
	$listSharedUserName = $row->user_name;
}
dbDisconnect($connection);
?>

<html>
	<head>
		<link rel="stylesheet" type="text/css" href="styles/<?php echo ($printMode ? 'print' : 'main'); ?>.css">
		<script src="scripts/common.js" type="text/javascript"></script>
		<script language="javascript">

			function highlightRow(row)
			{
				row.style.backgroundColor = "#F6F6F6";
				row.cells[0].style.backgroundColor = "#FFFFFF";
			}

			function dehighlightRow(row)
			{
				row.style.backgroundColor = "#FFFFFF";
			}

			function showTooltip(tooltipLink, wishId)
			{
				var linkPosition = getPosition(tooltipLink);
				document.getElementById("reservation_" + wishId).style.left = linkPosition[0] + "px";
				document.getElementById("reservation_" + wishId).style.top = (linkPosition[1] + 20) + "px";
				document.getElementById("reservation_" + wishId).style.display = "block";
			}

			function hideTooltip(wishId)
			{
				document.getElementById("reservation_" + wishId).style.display = "none";
			}

			function modifyWish(actionType, wishId)
			{
				document.forms["modify_wish"].elements["action"].value = actionType;
				document.forms["modify_wish"].elements["wish_id"].value = wishId;
				document.forms["modify_wish"].submit();
			}

			function reserveWish(wishId)
			{
				setDialogSize("overlay", "loader", "dialog");
				if (openAjaxPage("wish_dialog.php?action=reserve&listId=<?php echo $listId; ?>&wishId=" + wishId, "dialog"))
				{
					document.getElementById("overlay").style.display = "block";
					document.getElementById("loader").style.display = "block";
				}
			}

			function deleteWish(wishId)
			{
				if (window.confirm("Klicka på OK om du är säker på att du vill ta bort önskningen."))
				{
					modifyWish("delete", wishId);
				}
			}

			function editWish(wishId)
			{
				setDialogSize("overlay", "loader", "dialog");
				if (openAjaxPage("wish_dialog.php?action=edit&listId=<?php echo $listId; ?>&wishId=" + wishId, "dialog"))
				{
					document.getElementById("overlay").style.display = "block";
					document.getElementById("loader").style.display = "block";
				}
			}

			function addWish(categoryId)
			{
				setDialogSize("overlay", "loader", "dialog");
				if (openAjaxPage("wish_dialog.php?action=add&listId=<?php echo $listId; ?>&categoryId=" + categoryId, "dialog"))
				{
					document.getElementById("overlay").style.display = "block";
					document.getElementById("loader").style.display = "block";
				}
			}

			function printList()
			{
				window.open("list.php?id=<?php echo $listId; ?>&action=print", "print_list",
					"loation=no,menubar=no,status=yes,toolbar=no,scrollbars=yes,resizable=yes,width=800,height=700");
			}

			function submitDialog()
			{
				var action = document.forms["wish_dialog"].elements["action"];
				if (action.value == "edit" || action.value == "add")
				{
					var category = document.forms["wish_dialog"].elements["category"];
					var description = document.forms["wish_dialog"].elements["description"];
					if (action.value == "edit" && description.value == "")
					{
						window.alert("Vänligen ange en beskrivning för din önskan.");
						return;
					}
					else if (action.value == "add" && (category.selectedIndex < 1 || description.value == ""))
					{
						window.alert("Vänligen ange både kategori och beskrivning för din önskan.");
						return;
					}
				}
				document.forms["wish_dialog"].submit();
			}

			function cancelDialog()
			{
				document.getElementById("dialog").style.display = "none";
				document.getElementById("overlay").style.display = "none";
			}

		</script>
		<title>Familjens Önskelista</title>
	</head>
	<body<?php if ($printMode) { echo ' onload="window.print()"'; } ?>>
		<div id="overlay">
			<img id="loader" src="images/loader.gif">
		</div>
		<div id="dialog" style="display: none;"></div>
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
<?php
if ($unauthorizedCall)
{
		echo "Felaktigt anrop\n";
}
else
{
	if ($listIsChildList)
	{
		echo $listChildName . " önskelista\n";
	}
	elseif ($myList)
	{
		if ($listIsShared)
		{
			echo "Vår önskelista\n";
		}
		else
		{
			echo "Min önskelista\n";
		}
	}
	else
	{
		if ($listIsShared)
		{
			$postfix = (substr($listUserName, strlen($listUserName) - 1) == 's' ? '' : 's');
			$postfixShared = (substr($listSharedUserName, strlen($listSharedUserName) - 1) == 's' ? '' : 's');
			echo $listUserName . $postfix . ' & ' . $listSharedUserName . $postfixShared . " önskelista\n";
		}
		else
		{
			$postfix = (substr($listUserName, strlen($listUserName) - 1) == 's' ? '' : 's');
			echo $listUserName . $postfix . " önskelista\n";
		}
	}
}
?>
								</h1>
<?php
if ($unauthorizedCall)
{
	echo "Den efterfrågade listan är inte tillgänglig.\n";
	echo "<br><br><br>\n";
}
else
{
	if (!$printMode)
	{
		echo "<br>\n";
		if ($myList || $_SESSION['user_is_super'])
		{
			if (!$listIsLocked)
			{
				echo "Klicka på länkarna för att lägga till, ändra eller ta bort önskningar.\n";
			}
			else
			{
				echo 'Listan är låst för ändringar t.o.m. ' . date('Y-m-d', $listLockedUntil) . ' men det är fortfarande möjligt att utöka '
					. "med nya önskningar. Välj en kategori, beskriv önskningen och klicka på länken för att lägga till en ny önskning.\n";
			}
		}
		else
		{
			echo 'Listan är låst t.o.m. ' . date('Y-m-d', $listLockedUntil)
				. ". Klicka på länkarna för att reservera en sak som du har köpt.\n";
		}
	}
?>
								<table width="100%">
									<tr>
										<td align="right">
<?php
if (!$unauthorizedCall)
{
	if (!$printMode)
	{
		echo '<a href="javascript:printList()">Skriv&nbsp;ut</a>';
		if ($myList)
		{
			echo "&nbsp;|&nbsp;<a href=\"javascript:addWish()\">Lägg&nbsp;till</a>\n";
		}
	}
}
?>
										</td>
									</tr>
								</table>
								<table width="100%">
									<form name="modify_wish" method="post" action="list.php">
										<input name="action" type="hidden" value="">
										<input name="wishlist_id" type="hidden" value="<?php echo $listId; ?>">
										<input name="wish_id" type="hidden" value="">
									</form>
									<tr>
										<td>
											<h2>
<?php
	echo $listTitle . "\n";
?>
											</h2>
											<table width="100%">
												<tr><td height="9"></td></tr>
<?php
	$connection = dbConnect();
	$wishes = [];
	$rowCount = 0;
	try
	{
		$result = dbExecute($connection,
			'SELECT wishes.wish_id, categories.category_id, categories.name, modify_date,'
				. ' short_description, link, max_reservation_count, reservation_key FROM wishes'
				. ' INNER JOIN categories ON wishes.category_id = categories.category_id'
				. ' WHERE wishlist_id = :wishlistId ORDER BY category_id ASC, short_description ASC',
			[':wishlistId' => $listId]);

		while ($row = dbFetch($result))
		{
			$wishes[$rowCount]['wish_id'] = $row->wish_id;
			$wishes[$rowCount]['category_id'] = $row->category_id;
			$wishes[$rowCount]['name'] = $row->name;
			$wishes[$rowCount]['modify_date'] = $row->modify_date;
			$wishes[$rowCount]['short_description'] = $row->short_description;
			$wishes[$rowCount]['link'] = $row->link;
			$wishes[$rowCount]['max_reservation_count'] = $row->max_reservation_count;
			$wishes[$rowCount]['reservation_count'] = $row->reservation_count;
			$wishes[$rowCount]['reservation_key'] = decrypt($row->reservation_key);
			$wishes[$rowCount]['reservations'] = [];
			$rowCount++;
		}
	}
	catch (PDOException $ex)
	{
		die('Could not retrieve wish list information from database: ' . $ex->getMessage());
	}
	if ($rowCount > 0)
	{
		try
		{
			$result = dbExecute($connection, 'SELECT reservation_id, `key`, reserved_by_user_id FROM reservations');
			while ($row = dbFetch($result))
			{
				$key = decrypt($row->key);
				$wishesRow = recursiveArraySearch($wishes, $key, 'reservation_key');
				if ($wishesRow !== false)
				{
					$wishes[$wishesRow]['reservations'][] = decrypt($row->reserved_by_user_id);
				}
			}
		}
		catch (PDOException $ex)
		{
			die('Could not retrieve reservation information from database: ' . $ex->getMessage());
		}
		$last_category_id = -1;
		for ($row = 0; $row < $rowCount; $row++)
		{
			if ($wishes[$row]['category_id'] != $last_category_id)
			{
				if ($last_category_id != -1)
				{
					echo "	<tr>\n";
					echo "		<td height=\"20\"></td>\n";
					echo "	</tr>\n";
				}
				echo "	<tr>\n";
				echo "		<td class=\"list_header_left\" colspan=\"3\">\n";
				echo '			<a name="category-' . $wishes[$row]['category_id'] . "\"></a>\n";
				echo '			<h3>' . $wishes[$row]['name'] . "</h3>\n";
				echo "		</td>\n";
				echo "		<td class=\"list_header_right\">\n";
				if (!$printMode && $myList)
				{
					echo '			<a href="javascript:addWish(' . $wishes[$row]['category_id'] . ")\">Lägg&nbsp;till</a>\n";
				}
				echo "		</td>\n";
				echo "	</tr>\n";
				$last_category_id = $wishes[$row]['category_id'];
			}
			// show indication for reserved wishes?
			$reservationCount = count($wishes[$row]['reservations']);
			$maxReservationCount = $wishes[$row]['max_reservation_count'];
			$isReserved = ($reservationCount > 0 && (!$myList || $listIsChildList || !$listIsLocked));
			$isFullyReserved = ($isReserved && $reservationCount >= $maxReservationCount && $maxReservationCount != -1);
			$canBeReserved = ($listIsLocked && !$isFullyReserved && ($maxReservationCount > 0
					|| $maxReservationCount == -1 && array_search($_SESSION['user_id'], $wishes[$row]['reservations']) === false));
			$link = (strpos($wishes[$row]['link'], 'http://') === FALSE && strpos($wishes[$row]['link'], 'https://') === FALSE ? 'http://' : '') . $wishes[$row]['link'];
			echo "	<tr onMouseOver=\"highlightRow(this);\" onMouseOut=\"dehighlightRow(this);\">\n";
			echo "		<td width=\"5\">&nbsp;&nbsp;</td>\n";
			echo "		<td class=\"list_row_left\">\n";
			echo '			' . ($isFullyReserved ? '<span class="reserved_wish">' : '') . $wishes[$row]['short_description'];
			echo ($wishes[$row]['link'] != '' ? "\n			<br><a href=\"" . $link . '" class="' . ($isFullyReserved ? 'reserved_wish' : 'wish') . '" target="wish-link-'
				. $wishes[$row]['wish_id'] . '">' . $link . '</a>' : '');
			echo ($isFullyReserved ? '</span>' : '') . "\n";
			echo "		</td>\n";
			echo "		<td class=\"list_row_center\" width=\"20\"></td>\n";
			echo "		<td class=\"list_row_right\">\n";
			if ($isReserved)
			{
				$showReservedInMyList = ($myList && !$listIsLocked);
				echo '			<div id="reservation_' . $wishes[$row]['wish_id'] . "\" class=\"tooltip\">\n";
				$reservedByUserIds = '';
				foreach ($wishes[$row]['reservations'] as $reservedByUserId)
				{
					$reservedByUserIds .= $reservedByUserId . ', ';
				}
				try
				{
					$result = dbExecute($connection, 'SELECT user_id, user_name FROM users WHERE user_id IN (' . trim($reservedByUserIds, ', ') . ')');
				}
				catch (PDOException $ex)
				{
					die('Could not retrieve wish reserve information from database: ' . $ex->getMessage());
				}
				echo "				<table width=\"100%\">\n";
				while ($dbRow = dbFetch($result))
				{
					echo "					<tr>\n";
					if ($maxReservationCount != -1)
					{
						echo "						<td style=\"padding-right: 10px;\">\n";
						echo '							' . substr_count($reservedByUserIds, $dbRow->user_id) . "&nbsp;st\n";
						echo "						</td>\n";
					}
					echo "						<td>\n";
					echo '							' . $dbRow->user_name . "\n";
					echo "						</td>\n";
					echo "					</tr>\n";
				}
				echo "				</table>\n";
				echo "			</div>\n";
				echo '			<span class="tooltip_link"' . ($printMode ? ''
					: ' onMouseOver="showTooltip(this, ' . $wishes[$row]['wish_id']	. ');" onMouseOut="hideTooltip(' . $wishes[$row]['wish_id'] . ');"')
					. '>' . ($maxReservationCount > 1 ? $reservationCount . '&nbsp;av&nbsp;'
					. $maxReservationCount . '&nbsp;reserverad' . ($showReservedInMyList ? 'es' : ($reservationCount > 1 ? 'e' : ''))
					: 'Reserverad' . ($showReservedInMyList ? 'es' : '')) . '</span>'
					. (!$printMode && $showReservedInMyList || $canBeReserved ? '' : "\n");
			}
			elseif ($maxReservationCount > 0)
			{
				echo '			' . ($printMode ? '(' : '') . $maxReservationCount . '&nbsp;st' . ($printMode ? ')' : '');
			}
			if (!$printMode)
			{
				if (!$listIsLocked || $_SESSION['user_is_super'])
				{
					if ($myList)
					{
						echo ($isReserved || $maxReservationCount > 0 ? '&nbsp;|&nbsp;' : '			');
						echo '<a href="javascript:editWish(' . $wishes[$row]['wish_id'] . ')">Ändra</a>&nbsp;|&nbsp;'
							. '<a href="javascript:deleteWish(' . $wishes[$row]['wish_id'] . ")\">Ta&nbsp;bort</a>\n";
					}
				}
				if ($listIsLocked && (!$myList || $listIsChildList) && $canBeReserved)
				{
					echo ($maxReservationCount == -1 ? '' : '&nbsp;|&nbsp;')
						. '<a href="javascript:reserveWish(' . $wishes[$row]['wish_id'] . ")\">Reservera</a>\n";
				}
			}
			echo "		</td>\n";
			echo "	</tr>\n";
		}
	}
	else
	{
		echo "	<tr>\n";
		echo "		<td class=\"small\">\n";
		echo "			(det finns inga önskningar i denna lista)\n";
		echo "		</td>\n";
		echo "	</tr>\n";
	}
	dbDisconnect($connection);
?>
											</table>
										</td>
									</tr>
								</table>
								<br><br>
<?php
} // $unauthorizedCall
if (!$printMode)
{
	echo "<br>\n";
}
?>
								<table width="100%">
									<tr>
										<td>
<?php
if (!$printMode)
{
	echo "<a href=\"home.php\">Tillbaka</a>\n";
}
?>
										</td>
										<td align="right">
<?php
if (!$unauthorizedCall)
{
	if ($printMode)
	{
		echo "<span class=\"print_date\">\n";
		if ($listIsLocked)
		{
			echo '	Låst t.o.m. ' . date('Y-m-d', $listLockedUntil) . "\n";
		}
		else
		{
			echo "	Listan är olåst och därför ej synlig för andra.\n";
		}
		echo "	<br>\n";
		echo '	Utskriven ' . date('Y-m-d H:i:s') . "\n";
		echo "</span>\n";
	}
	else
	{
		echo '<a href="javascript:printList()">Skriv&nbsp;ut</a>';
		if ($myList)
		{
			echo "&nbsp;|&nbsp;<a href=\"javascript:addWish()\">Lägg&nbsp;till</a>\n";
		}
	}
}
?>
										</td>
									</tr>
								</table>
<?php
/*
	foreach ($_SESSION as $key => $value)
	{
		echo $key . ' = ' . $value . '<br>';
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
