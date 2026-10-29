<?php
require '../includes/functions.php'; // starts the session

// End only the artisan login (an admin logged in in the same browser stays logged in)
unset($_SESSION['artisan_account_id'], $_SESSION['artisan_name']);
session_regenerate_id(true);
set_flash('success', 'You have been logged out.');

header('Location: login.php');
exit;
