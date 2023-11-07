<?php
header('Content-Type: text/html; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ini_set('display_errors', '1');

// start session
session_start();

if (isset($_GET['test']))
{
	// run in test mode
	$_SESSION['environment'] = 'test';
}
else
{
	unset($_SESSION['environment']);
}

$loginSuccess = true;

if (isset($_POST['action']))
{
	// handle post back
	define('_VALID_INCLUDE', TRUE);
	include 'handle_postback.php';
}

// include common functions
include_once 'common.php';
?>
<html>
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
		<meta name="theme-color" content="#2F2F2F"/>
		<link rel="stylesheet" type="text/css" href="styles/main.css?timestamp=<? echo time()?>"/>
		<script language="javascript">
			function clearExistingUser()
			{
				document.forms["login"].elements["existing_user"].selectedIndex = -1;
				document.forms["login"].elements["existing_user_password"].value = "";
			}

			function clearNewUser()
			{
				document.forms["login"].elements["new_user_name"].value = "";
				document.forms["login"].elements["new_user_password"].value = "";
			}

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
					login();
					return false;
				}
				return true;
			}

			function login()
			{
				var existing_user = document.forms["login"].elements["existing_user"];
				var existing_user_password = document.forms["login"].elements["existing_user_password"];
				var new_user_name = document.forms["login"].elements["new_user_name"];
				var new_user_password = document.forms["login"].elements["new_user_password"];
				if ((existing_user.selectedIndex < 1 || existing_user_password.value == "")
					&& (new_user_name.value == "" || new_user_password.value == ""))
				{
					window.alert("Vänligen välj eller ange ditt namn och lösenord.");
				}
				else
				{
					var action = document.forms["login"].elements["action"];
					if (new_user_name.value != "")
					{
						// set action
						action.value = "login_new";
						// check if new name exists
						var new_user_name = new_user_name.value;
						for (i = 0; i < existing_user.length; i++)
						{
							if (existing_user.options[i].text.toLowerCase() == new_user_name.toLowerCase())
							{
								window.alert("Namnet du har angivit finns redan sparat.\nVänligen välj ditt namn i listan istället.");
								return;
							}
						}
					}
					else if (existing_user.selectedIndex >= 1)
					{
						// set action
						action.value = "login_existing";
						// copy name of selected existing user
						document.forms["login"].elements["existing_user_name"].value = existing_user.options[existing_user.selectedIndex].text;
					}
					document.forms["login"].submit();
				}
			}
		</script>
		<title>Familjens Önskelista</title>
	</head>
	<body<?php if (!$loginSuccess) { echo ' onload="document.forms[\'login\'].elements[\'existing_user_password\'].focus();"'; } ?>>
		<form name="login" method="post" action="index.php">
			<input name="action" type="hidden" value="">
			<input name="existing_user_name" type="hidden" value="">
			<input name="next_page" type="hidden" value="<?php echo (isset($_GET['page']) ? urldecode($_GET['page']) : 'home.php'); ?>">
			<input name="next_page_params" type="hidden" value="<?php echo (isset($_GET['page']) && isset($_GET['params']) ? urldecode($_GET['params']) : ''); ?>">

			<div class="row-header">
				<div class="col-center header">
					Familjens Önskelista
				</div>
			</div>

			<div class="row">
				<div class="col-center content">
					<h1>
						Välkommen till Familjens Önskelista!
					</h1>
					<p>
						Har du varit här tidigare, välj ditt namn i listan.
						Är det första gången du är här, ange då ditt namn i textfältet.
						Ange även lösenord och klicka därefter på länken för att logga in.
					</p>
					<p>
						Om ditt namn finns med i listan men du har glömt ditt lösenord, klicka då <a href="pwd.php">här</a> för att skapa ett nytt.
					</p>

					<div class="row">
						<div class="col-8">
							<h2>
								Befintlig användare
							</h2>
						</div>
						<div class="col-4 empty"></div>
					</div>

					<div class="row">
						<div class="col-4">Namn:</div>
						<div class="col-4 right">
							<select name="existing_user" onclick="clearNewUser();">
								<option value="-1"></option>
<?php
$connection = dbConnect();
try
{
	$result = dbExecute($connection, 'SELECT user_id, user_name FROM users ORDER BY user_name ASC');
	while ($row = dbFetch($result))
	{
		echo '<option value="' . $row->user_id . '"';
		if (!$loginSuccess && $row->user_id == $_POST['existing_user'])
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
						<div class="col-4 empty"></div>
					</div>

					<div class="row">
						<div class="col-4">Lösenord:</div>
						<div class="col-4 right">
							<input name="existing_user_password" type="password" onKeyPress="clearNewUser(); return checkEnter(event);">
						</div>
						<div class="col-4 empty"></div>
					</div>

<?php
if (!$loginSuccess)
{
?>
					<div class="row">
						<div class="col-12">
							<span style="color: #FF0000;">Lösenordet du angav är felaktigt!</span>
						</div>
					</div>
<?php
}
?>
					<div class="row">
						<div class="col-8">
							<h2>
								Ny användare
							</h2>
						</div>
						<div class="col-4 empty"></div>
					</div>

					<div class="row">
						<div class="col-4">Namn:</div>
						<div class="col-4 right">
							<input name="new_user_name" type="text" onKeyPress="clearExistingUser(); return checkEnter(event);">
						</div>
						<div class="col-4 empty"></div>
					</div>

					<div class="row">
						<div class="col-4">Lösenord:</div>
						<div class="col-4 right">
							<input name="new_user_password" type="password" onKeyPress="clearExistingUser(); return checkEnter(event);">
						</div>
						<div class="col-3 empty"></div>
						<div class="col-1 right" style="margin-top: 10px;">
							<a href="javascript:login()">Logga in</a>
						</div>
					</div>
				</div>
			</div>
		</form>
	</body>
</html>
