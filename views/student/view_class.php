<?php
session_start();
require_once "../../config/Database.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user = $_SESSION['user'];
$conn = Database::getInstance()->getConnection();

$class_id = $_GET['id'] ?? null;

if (!$class_id) {
    die("Missing class ID");
}

//CLASS INFO
$stmt = $conn->prepare("
    SELECT 
        c.*,
        CONCAT(u.fname, ' ', u.lname) AS prof_name,
        u.profile_pic
    FROM classes c
    JOIN users u ON c.professor_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    die("Class not found");
}

// CLASSWORKS
$works = $conn->prepare("
    SELECT * FROM classworks
    WHERE class_id = ?
    ORDER BY created_at DESC
");
$works->execute([$class_id]);
$classworks = $works->fetchAll(PDO::FETCH_ASSOC);

/*SUBMISSIONS*/
$stmt = $conn->prepare("
    SELECT *
    FROM submissions
    WHERE class_id = ? AND student_id = ?
");
$stmt->execute([$class_id, $user['id']]);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$submission_map = [];
foreach ($submissions as $sub) {
    $submission_map[$sub['classwork_id']] = $sub;
}

/*GRADES*/
$stmt = $conn->prepare("
    SELECT *
    FROM grades
    WHERE class_id = ? AND student_id = ?
");
$stmt->execute([$class_id, $user['id']]);
$grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grade_map = [];
foreach ($grades as $g) {
    $grade_map[$g['classwork_id']] = $g;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>My Class</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">

<style>
body{
    margin:0;
    font-family:'Poppins', sans-serif;
    background:linear-gradient(135deg, #153c6b, #7d784a);;
}

.header{
    background:linear-gradient(135deg,#1f2a38,#2c3e50);
    color:white;
    padding:30px;
}

.container{
    padding:20px;
    max-width:1100px;
    margin:auto;
}

.card{
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    margin-bottom:20px;
}

.work{
    padding:15px;
    border-radius:10px;
    margin-bottom:10px;
    background:#f9fafc;
}

.lesson{ border-left:5px solid #3498db; }
.lab{ border-left:5px solid #9b59b6; }
.quiz{ border-left:5px solid #e67e22; }
.assignment{ border-left:5px solid #10b981; }
.activity{ border-left:5px solid #22c55e; }

.submit-box{
    margin-top:10px;
    padding:10px;
    background:#eef2f7;
    border-radius:8px;
}

.open{ color:green; font-weight:bold; }
.locked{ color:red; font-weight:bold; }
.late{ color:orange; font-weight:bold; }

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    padding:10px;
    border-bottom:1px solid #ddd;
}

th{
    background:#2c3e50;
    color:white;
}

.pass{ color:green; font-weight:bold; }
.fail{ color:red; font-weight:bold; }

.empty{
    text-align:center;
    color:gray;
    padding:10px;
}
</style>
</head>

<body>

<div class="header">
    <h2><?= htmlspecialchars($class['class_name']) ?></h2>
    <small>Professor: <?= htmlspecialchars($class['prof_name']) ?></small>
</div>

<div class="container">

<!-- CLASSWORKS -->
<div class="card">
<h3>Class Activities</h3>

<?php foreach ($classworks as $cw): ?>

<?php
$submission = $submission_map[$cw['id']] ?? null;
$grade = $grade_map[$cw['id']] ?? null;

$isSubmitted = !empty($submission);
$isGraded = !empty($grade);

$now = date("Y-m-d H:i:s");
$isClosed = !empty($cw['due_date']) && $now > $cw['due_date'];

$status = "OPEN";
if (!empty($cw['due_date'])) {
    if ($isClosed && !empty($cw['allow_late'])) {
        $status = "LATE";
    } elseif ($isClosed) {
        $status = "CLOSED";
    }
}
?>

<div class="work <?= $cw['type'] ?>">

    <h4><?= htmlspecialchars($cw['title']) ?></h4>
    <p><?= htmlspecialchars($cw['description']) ?></p>

    <small>📅 Posted: <?= date("F d, Y h:i A", strtotime($cw['created_at'])) ?></small><br>

    <?php if (!empty($cw['due_date'])): ?>
        <small>⏰ Deadline: <?= date("F d, Y h:i A", strtotime($cw['due_date'])) ?></small><br>

        <?php if ($status == "OPEN"): ?>
            <span class="open">🟢 OPEN</span>
        <?php elseif ($status == "CLOSED"): ?>
            <span class="locked">🔴 CLOSED</span>
        <?php else: ?>
            <span class="late">⚠ LATE</span>
        <?php endif; ?>
    <?php endif; ?>

    <!-- FILE ATTACHMENT -->
    <?php if (!empty($cw['file_path'])): ?>
        <br>📎 <a href="../../uploads/<?= $cw['file_path'] ?>">View File</a>
    <?php endif; ?>

    <!-- SUBMISSION -->
    <div class="submit-box">

    <?php if ($isSubmitted): ?>

        <div class="open">
            ✅ Submitted
            <?php if (!empty($submission['file_path'])): ?>
                <br>
                📎 <a href="../../<?= $submission['file_path'] ?>" target="_blank">View Submission</a>
            <?php endif; ?>
        </div>

    <?php else: ?>

        <form method="POST" enctype="multipart/form-data"
              action="../student/submit_work.php">

            <input type="hidden" name="classwork_id" value="<?= $cw['id'] ?>">
            <input type="hidden" name="class_id" value="<?= $class_id ?>">

            <label>Submit Work</label>
            <input type="file" name="file" required>

            <button type="submit">Submit</button>

        </form>

    <?php endif; ?>

    </div>

</div>

<?php endforeach; ?>

</div>

<!--GRADES-->
<div class="card">
<h3>My Grades</h3>

<?php if (!empty($grade_map)): ?>
<table>
<tr>
    <th>Activity</th>
    <th>Score</th>
    <th>Status</th>
</tr>

<?php foreach ($classworks as $cw): ?>

<?php
$g = $grade_map[$cw['id']] ?? null;
if (!$g) continue;

$percent = ($cw['max_points'] > 0)
    ? ($g['grade'] / $cw['max_points']) * 100
    : 0;

$status = ($percent >= 75) ? "PASS" : "FAIL";
?>

<tr>
    <td><?= htmlspecialchars($cw['title']) ?></td>
    <td><?= $g['grade'] ?> / <?= $cw['max_points'] ?></td>
    <td class="<?= $status == 'PASS' ? 'pass' : 'fail' ?>">
        <?= $status ?>
    </td>
</tr>

<?php endforeach; ?>

</table>
<?php else: ?>
<div class="empty">No grades yet.</div>
<?php endif; ?>

</div>

</div>

</body>
</html>