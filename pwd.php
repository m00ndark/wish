<?php
header('Content-Type: text/html; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ini_set('display_errors', '1');

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
$preSelectedUserId = $_COOKIE['user_id'];
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
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<meta name="theme-color" content="#2F2F2F"/>
		<link rel="icon" type="image/png" href="images/favicon.png?timestamp=<?php echo time()?>">
		<link rel="stylesheet" type="text/css" href="styles/main.css?timestamp=<?php echo time()?>"/>
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
		<form name="pwd" method="post" action="pwd.php">
			<input name="action" type="hidden" value="<?php echo ($editMode ? 'edit' : 'recover'); ?>">

			<div class="row-header">
				<div class="col-center header">
					Familjens Önskelista
				</div>
			</div>

			<div class="row">
				<div class="col-center content">
					<h1>
						Skapa nytt lösenord
					</h1>
<?php
if ($pwdSavedSuccessfully)
{
?>
					<p>
						Ditt nya lösenord är nu sparat. Vänligen klicka <a href="/">här</a> för att logga in.
					</p>
					<br><br>
					<br><br>
					<br><br>
<?php
}
else if (!$editMode && $userId == '')
{
?>
					<p>
						Välj ditt namn i listan och klicka därefter på länken för att skapa ett nytt lösenord.
						Du kommer få ett mail skickat till din e-postadress med vidare instruktioner.
					</p>

					<div class="row">
						<div class="col-8">
							<h2>
								Befintlig användare
							</h2>
						</div>
						<div class="col-4 empty"></div>
					</div>

					<div class="row" style="padding-bottom: 10px;">
						<div class="col-4">Namn:</div>
						<div class="col-4 right">
							<select name="existing_user">
								<option value="-1"></option>
<?php
	$connection = dbConnect();
	try
	{
		$result = dbExecute($connection, 'SELECT user_id, user_name FROM users ORDER BY user_name ASC');
		while ($row = dbFetch($result))
		{
			echo '<option value="' . $row->user_id . '"';
			if ($row->user_id == $preSelectedUserId)
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
						</div>
						<div class="col-3 empty"></div>
						<div class="col-1 right" style="margin-top: 10px;">
							<button type="button" class="primary" onClick="submitForm()">Skicka</button>
						</div>
					</div>
<?php
}
else if (!$editMode)
{
?>
					<p>
						Ett mail med vidare instruktioner har skickats till <?php echo $email; ?>.
						Vänligen kolla din mail.
					</p>
					<br><br>
					<br><br>
					<br><br>
<?php
}
else if ($editModeAuthorized)
{
?>
					<p>
						Kontrollera först att det är ditt namn som står angivet nedan.
						Ange därefter ett nytt lösenord och klicka på länken för att spara.
					</p>

					<div class="row">
						<div class="col-8">
							<h2>
								Ändra lösenord
							</h2>
							</div>
						<div class="col-4 empty"></div>
					</div>

					<div class="row">
						<div class="col-4">Namn:</div>
						<div class="col-4 right">
							<input name="user_name" type="text" value="<?php echo $userName; ?>" disabled>
							<input name="user" type="hidden" value="<?php echo $userId; ?>">
						</div>
						<div class="col-4 empty"></div>
					</div>

					<div class="row" style="padding-bottom: 10px;">
						<div class="col-4">Nytt&nbsp;lösenord:</div>
						<div class="col-4 right">
							<input name="password" type="password" onKeyPress="return checkEnter(event);">
						</div>
						<div class="col-3 empty"></div>
						<div class="col-1 right" style="margin-top: 10px;">
							<button type="button" class="primary" onClick="submitForm()">Spara</button>
						</div>
					</div>
<?php
}
else
{
?>
					<p>
						Länken du klickade på för att komma hit är ogiltig eller för gammal.
						Vänligen klicka <a href="/">här</a> för att återgå till inloggningssidan.
					</p>
					<br><br>
					<br><br>
					<br><br>
<?php
}
?>
				</div>
			</div>
		</form>
	</body>
</html>
