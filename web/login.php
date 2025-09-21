<?php
require_once("inc/inc.php");

$alsovalid = array('-', '_');

if (isset($_GET['denied'])) {
	$error = "Your account is not eligible to submit a ballot.";
} else if (isset($_GET['logout'])) {
	$user->logout();
} else if (isset($_POST['login'])){
	if(isset($_POST['username']) && isset($_POST['password']) && !empty($_POST['username']) && !empty($_POST['password'])){
		$username = $_POST['username'];
		$password = $_POST['password'];
		if($user->login($username, $password)){
			header("Location: /");
			die();
		} else {
			$error = "Incorrect username or password.";
		}
	} else {
		$error = "Must fill in both username and password.";
	}
}

$header = new Header("Login Required");
$header->addStyle("/styles/style.css");
$header->setAttribute('title', 'Michigan Flyers');
$header->setAttribute('tagline', 'Online Election System');
$header->output();
?>
<h3 id="login-help">Sign in with your Skymanager Account</h3>
<?php
if(isset($error)) echo "<span class=\"errormessage\">$error</span>";
?>
<form action="login.php" method="POST">
<div class="form-row">
	<label for="username">Username</label>
	<input class="login" type="text" name="username" />
</div>
<div class="form-row">
	<label for="password">Password</label>
	<input class="login" type="password" name="password" />
</div>
<div class="form-row">
	<input class="loginbutton" type="submit" name="login" value="Log In" />
</div>
</form>
<?php
$footer = new Footer();
$footer->output();
