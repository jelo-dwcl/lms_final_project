<?php
session_start();

/* =========================
   DATABASE CONNECTION
========================= */
require_once __DIR__ . "/../../config/Database.php";

/* =========================
   AUTH CHECK
========================= */
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit;
}

/* =========================
   ONLY POST ALLOWED
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request.");
}

$conn = Database::getInstance()->getConnection();

/* =========================
   GET DATA
========================= */
$class_id = $_POST['class_id'] ?? null;
$title = trim($_POST['title'] ?? '');
$type = $_POST['type'] ?? '';
$description = trim($_POST['description'] ?? '');

/* DEFAULT VALUES */
$max_points = $_POST['max_points'] ?? 100;
$due_date = $_POST['due_date'] ?? null;

$allow_late = isset($_POST['allow_late']) ? 1 : 0;
$allow_submission = isset($_POST['allow_submission']) ? 1 : 0;
$attachment_required = isset($_POST['attachment_required']) ? 1 : 0;

/* =========================
   ANNOUNCEMENT SETTINGS
========================= */
if ($type === "announcement") {

    $max_points = 0;

    $due_date = null;

    $allow_late = 0;

    $allow_submission = 0;

    $attachment_required = 0;
}

/* =========================
   VALIDATION
========================= */
if (!$class_id || !$title || !$type) {
    die("Missing required fields.");
}

/* =========================
   FORMAT DATE
========================= */
$due_date = !empty($due_date)
    ? date("Y-m-d H:i:s", strtotime($due_date))
    : null;

/* =========================
   FILE UPLOAD
========================= */
$file_path = null;

if (!empty($_FILES['file']['name'])) {

    $uploadDir = __DIR__ . "/../../uploads/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = time() . "_" . basename($_FILES['file']['name']);

    $fileName = preg_replace(
        "/[^a-zA-Z0-9.\-_]/",
        "",
        $fileName
    );

    $targetFile = $uploadDir . $fileName;

    if (move_uploaded_file(
        $_FILES['file']['tmp_name'],
        $targetFile
    )) {

        $file_path = "uploads/" . $fileName;
    }
}

/* =========================
   INSERT CLASSWORK
========================= */
$stmt = $conn->prepare("
    INSERT INTO classworks
    (
        class_id,
        title,
        type,
        description,
        created_at,
        max_points,
        file_path,
        due_date,
        allow_submission,
        attachment_required,
        allow_late
    )
    VALUES
    (
        ?, ?, ?, ?, NOW(),
        ?, ?, ?, ?, ?, ?
    )
");

$stmt->execute([

    $class_id,
    $title,
    $type,
    $description,
    $max_points,
    $file_path,
    $due_date,
    $allow_submission,
    $attachment_required,
    $allow_late

]);

/* SUCCESS MESSAGE */
if ($type === "announcement") {

    $_SESSION['success'] =
        "📢 Announcement posted successfully.";

} else {

    $_SESSION['success'] =
        "✅ Classwork created successfully.";
}

/*REDIRECT */
header(
    "Location: ../professor/view_class.php?id=" . $class_id
);

exit;
?>