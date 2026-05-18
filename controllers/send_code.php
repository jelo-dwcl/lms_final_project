<?php
//pass reset
session_start();

require_once __DIR__ . "/MessageController.php";
require_once __DIR__ . "/UserController.php";

if (!isset($_SESSION['user'])) {
    exit("Unauthorized");
}

$userController = new UserController();
$messageController = new MessageController();

$userId = $_SESSION['user']['id'];
$role = $_GET['role'] ?? $_SESSION['user']['role'] ?? 'student';

$code = rand(100000, 999999);

// send message
$messageController->sendCode($userId, $code, $role);

$_SESSION['success'] = "Code sent successfully!";

header("Location: ../views/student/dashboard.php");
exit;