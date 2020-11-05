<?php
header('Content-Type: text/html; charset=utf-8');

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
		<link rel="stylesheet" type="text/css" href="styles/main.css">
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
									Välkommen till Familjens Önskelista!
								</h1>
								<br>
								Har du varit här tidigare, välj ditt namn i listan.
								Är det första gången du är här, ange då ditt namn i textfältet.
								Ange även lösenord och klicka därefter på länken för att gå vidare.
								<br><br>
								Om ditt namn finns med i listan men du har glömt ditt lösenord, klicka då <a href="pwd.php">här</a> för att skapa ett nytt.
								<br>

								<form name="login" method="post" action="index.php">
									<input name="action" type="hidden" value="">
									<input name="existing_user_name" type="hidden" value="">
									<input name="next_page" type="hidden" value="<?php echo (isset($_GET['page']) ? urldecode($_GET['page']) : 'home.php'); ?>">
									<input name="next_page_params" type="hidden" value="<?php echo (isset($_GET['page']) && isset($_GET['params']) ? urldecode($_GET['params']) : ''); ?>">

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
															<select name="existing_user" style="width: 150px" onclick="clearNewUser();">
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
														</td>
													</tr>
													<tr>
														<td>
															Lösenord:
														</td>
														<td align="right">
															<input name="existing_user_password" type="password" style="width: 150px" onKeyPress="clearNewUser(); return checkEnter(event);">
														</td>
													</tr>
												</table>
											</td>
											<td rowspan="2" width="50%" valign="bottom" align="right">
												<a href="javascript:login()">Vidare</a>
											</td>
										</tr>
										<tr>
											<td>
												<h2>
													Ny användare
												</h2>
												<table width="100%">
													<tr>
														<td>
															Namn:
														</td>
														<td align="right">
															<input name="new_user_name" type="text" style="width: 150px" onKeyPress="clearExistingUser(); return checkEnter(event);">
														</td>
													</tr>
													<tr>
														<td>
															Lösenord:
														</td>
														<td align="right">
															<input name="new_user_password" type="password" style="width: 150px" onKeyPress="clearExistingUser(); return checkEnter(event);">
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
								</form>

								<br>
<?php
if (!$loginSuccess)
{
	echo "<span style=\"color: #FF0000;\">Lösenordet du angav är felaktigt!</span>\n";
}
?>
								<br>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</body>
</html>
