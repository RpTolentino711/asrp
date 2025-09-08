<?php
session_start();

$_SESSION['logout_success'] = "You have been successfully logged out.";


unset($_SESSION['client_id']);
unset($_SESSION['C_username']);
unset($_SESSION['C_status']); 

header("Location: index.php");
exit();


?>