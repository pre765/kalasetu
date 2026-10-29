<?php
require '../includes/functions.php'; // starts the session

// End only the ADMIN login. An artisan logged in in the same browser
// (e.g. while testing approve → artisan dashboard) stays logged in.
unset($_SESSION['admin_logged_in'], $_SESSION['admin_username'], $_SESSION['login_attempts'], $_SESSION['login_locked_until']);
session_regenerate_id(true);   // new session id after a privilege change

header('Location: login.php');
exit;
