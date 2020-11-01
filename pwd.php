<?php
header('Content-Type: text/html; charset=utf-8');

// ini_set('display_errors', '1');

// start session
session_start();

if (isset($_POST['action']))
{
	// handle post back
	define('_VALID_INCLUDE', TRUE);
	include 'handle_postback.php';
}

// include common functions
include_once 'common.php';

$pwdSavedSuccessfully = false;
$editMode = false;
$userId = '';
if (isset($_GET['success']))
{
	$pwdSavedSuccessfully = true;
}
else if (isset($_GET['userid']))
{
	$userId = $_GET['userid'];
	$connection = dbConnect();
	if (isset($_GET['code']))
	{
		$editMode = true;
		$recoveryCode = $_GET['code'];
		$now = mktime(date('H'), date('i'), date('s'), date('n'), date('j'), date('Y')); // now
		try
		{
			$result = dbExecute($connection,
				'SELECT user_id, user_name, UNIX_TIMESTAMP(recovery_valid_until) recovery_valid_until_timestamp'
					. ' FROM users WHERE user_id = :userId AND recovery_code = :recoveryCode',
				[':userId' => $userId, ':recoveryCode' => $recoveryCode]);
			$row = dbFetch($result);
		}
		catch (PDOException $ex)
		{
			die('Could not retrieve password recovery information from database: ' . $ex->getMessage());
		}
		$userName = $row->user_name;
		$recoveryValidUntil = $row->recovery_valid_until_timestamp;
		$editModeAuthorized = ($now <= $recoveryValidUntil);
	}
	else
	{
		try
		{
			$result = dbExecute($connection, 'SELECT user_id, email FROM users WHERE user_id = :userId', [':userId' => $userId]);
			$row = dbFetch($result);
		}
		catch (PDOException $ex)
		{
			die('Could not retrieve wish email from database: ' . $ex->getMessage());
		}
		$email = $row->email;
	}
	dbDisconnect($connection);
}
?>

<html>
	<head>
		<link rel="stylesheet" type="text/css" href="styles/main.css">
		<script language="javascript">
			function checkEnter(e)
			{
				var characterCode;
				if (e && e.which)
				{
					characterCode = e.which; //character code is contained in NN4's which property
				}
				else
				{
					characterCode = event.keyCode; //character code is contained in IE's keyCode property
				}
				if (characterCode == 13)
				{
					submitForm();
					return false;
				}
				return true;
			}

			function submitForm()
			{
				var action = document.forms["pwd"].elements["action"];
				if (action.value == "recover")
				{
					if (document.forms["pwd"].elements["existing_user"].selectedIndex < 1)
					{
						window.alert("Vänligen välj ditt namn i listan.");
						return;
					}
				}
				else if (action.value == "edit")
				{
					if (document.forms["pwd"].elements["password"].value == "")
					{
						window.alert("Vänligen ange ett lösenord.");
						return;
					}
				}
				document.forms["pwd"].submit();
			}
		</script>
		<title>Familjens Önskelista</title>
	</head>
	<body<?php if ($editModeAuthorized) { echo ' onload="document.forms[\'pwd\'].elements[\'password\'].focus();"'; } ?>>
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
									Skapa nytt lösenord
								</h1>

								<form name="pwd" method="post" action="pwd.php">
									<input name="action" type="hidden" value="<?php echo ($editMode ? 'edit' : 'recover'); ?>">
									<br>
<?php
if ($pwdSavedSuccessfully)
{
?>
									Ditt nya lösenord är nu sparat. Vänligen klicka <a href="/">här</a> för att logga in.
									<br><br>
									<br><br>
									<br><br>
<?php
}
else if (!$editMode && $userId == '')
{
?>
									Välj ditt namn i listan och klicka därefter på länken för att skapa ett nytt lösenord.
									Du kommer få ett mail skickat till din e-postadress med vidare instruktioner.
									<br><br>
									<table width="100%">
										<tr>
											<td width="50%">
												<h2>
													Befintlig användare
												</h2>
												<table width="100%">
													<tr>
														<td>
															Namn:
														</td>
														<td align="right">
															<select name="existing_user" style="width: 150px">
																<option value="-1"></option>
<?php
	$connection = dbConnect();
	try
	{
		$result = dbExecute($connection, 'SELECT user_id, user_name FROM users ORDER BY user_name ASC');
		while ($row = dbFetch($result))
		{
			echo '<option value="' . $row->user_id . '"';
			if ($row->user_id == $userId)
			{
				echo ' selected="true"';
			}
			echo '>' . $row->user_name . "</option>\n";
		}
	}
	catch (PDOException $ex)
	{
		die('Could not retrieve user names from database: ' . $ex->getMessage());
	}
	dbDisconnect($connection);
?>
															</select>
														</td>
													</tr>
												</table>
											</td>
											<td rowspan="2" width="50%" valign="bottom" align="right">
												<a href="javascript:submitForm()">Skicka</a>
											</td>
										</tr>
									</table>
<?php
}
else if (!$editMode)
{
?>
									Ett mail med vidare instruktioner har skickats till <?php echo $email; ?>.
									Vänligen kolla din mail.
									<br><br>
									<br><br>
									<br><br>
<?php
}
else if ($editModeAuthorized)
{
?>
									Kontrollera först att det är ditt namn som står angivet nedan.
									Ange därefter ett nytt lösenord och klicka på länken för att spara.
									<br><br>
									<table width="100%">
										<tr>
											<td width="50%">
												<h2>
													Ändra lösenord
												</h2>
												<table width="100%">
													<tr>
														<td>
															Namn:
														</td>
														<td align="right">
															<input name="user_name" type="text" style="width: 150px" value="<?php echo $userName; ?>" disabled>
															<input name="user" type="hidden" value="<?php echo $userId; ?>">
														</td>
													</tr>
													<tr>
														<td>
															Nytt&nbsp;lösenord:
														</td>
														<td align="right">
															<input name="password" type="password" style="width: 150px" onKeyPress="return checkEnter(event);">
														</td>
													</tr>
												</table>
											</td>
											<td rowspan="2" width="50%" valign="bottom" align="right">
												<a href="javascript:submitForm()">Spara</a>
											</td>
										</tr>
									</table>
<?php
}
else
{
?>
									Länken du klickade på för att komma hit är ogiltig eller för gammal.
									Vänligen klicka <a href="/">här</a> för att återgå till inloggningssidan.
									<br><br>
									<br><br>
									<br><br>
<?php
}
?>
								</form>

								<br>
								<br>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</body>
</html>
