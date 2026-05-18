<?php
session_start();

require_once "ClassworkController.php";

$controller = new ClassworkController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* =========================
       BASIC DATA
    ========================= */

    $class_id   = $_POST['class_id'] ?? null;

    $title      = trim($_POST['title'] ?? '');

    $type       = $_POST['type'] ?? '';

    $desc       = trim($_POST['description'] ?? '');

    $max_points = $_POST['max_points'] ?? 100;

    $due_date   = $_POST['due_date'] ?? null;

    $allow_submission =
        isset($_POST['allow_submission']) ? 1 : 0;

    $attachment_required =
        isset($_POST['attachment_required']) ? 1 : 0;

    $allow_late =
        isset($_POST['allow_late']) ? 1 : 0;

    /* =========================
       ANNOUNCEMENT SETTINGS
    ========================= */

    if ($type === "announcement") {

        $max_points = 0;

        $due_date = null;

        $allow_submission = 0;

        $attachment_required = 0;

        $allow_late = 0;
    }

    /* =========================
       DEFAULT POINTS
    ========================= */

    if (!is_numeric($max_points) || $max_points < 0) {

        $max_points = 100;
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

    if (
        isset($_FILES['file']) &&
        $_FILES['file']['error'] === 0
    ) {

        $uploadDir = "../uploads/";

        if (!is_dir($uploadDir)) {

            mkdir($uploadDir, 0777, true);
        }

        $originalName =
            $_FILES['file']['name'];

        $tmpName =
            $_FILES['file']['tmp_name'];
//did not allow duplicate
        $fileName =
            time() . "_" .

            //Removes dangerous/special characters.
            preg_replace(
                "/[^a-zA-Z0-9.\-_]/",
                "",
                $originalName
            );

        $targetPath =
            $uploadDir . $fileName;

        move_uploaded_file(
            $tmpName,
            $targetPath
        );
//Store file location into database.
        $file_path =
            "uploads/" . $fileName;
    }

    /* =========================
       SAVE TO DATABASE
    ========================= */

    $conn = Database::getInstance()
        ->getConnection();

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
        $desc,
        $max_points,
        $file_path,
        $due_date,
        $allow_submission,
        $attachment_required,
        $allow_late

    ]);

    /* =========================
       SUCCESS MESSAGE
    ========================= */

    if ($type === "announcement") {

        $_SESSION['success'] =
            "📢 Announcement posted.";

    } else {

        $_SESSION['success'] =
            "✅ Classwork created.";
    }

    /* =========================
       REDIRECT
    ========================= */

    header(
        "Location: ../views/professor/view_class.php?id=" . $class_id
    );

    exit;
}
?>  