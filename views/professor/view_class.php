<?php
session_start();
require_once "../../config/Database.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$conn = Database::getInstance()->getConnection();
$class_id = $_GET['id'] ?? null;

if (!$class_id) die("Invalid class ID");

/* SUCCESS MESSAGE */
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

/* CLASS */
$stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) die("Class not found");

/* CLASSWORKS */
$stmt = $conn->prepare("
    SELECT * FROM classworks
    WHERE class_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$class_id]);
$classworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* STUDENTS */
$stmt = $conn->prepare("
    SELECT u.id, u.fname, u.lname, u.profile_pic
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    WHERE e.class_id = ?
    AND u.role = 'student'
");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* JOIN REQUESTS */
$stmt = $conn->prepare("
    SELECT jr.*, u.fname, u.lname
    FROM join_requests jr
    JOIN users u ON u.id = jr.student_id
    WHERE jr.class_id = ? AND jr.status = 'pending'
");
$stmt->execute([$class_id]);
$join_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* SUBMISSIONS — grouped by classwork_id + student_id */
$stmt = $conn->prepare("
    SELECT s.*, u.fname, u.lname, cw.title AS cw_title
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    JOIN classworks cw ON cw.id = s.classwork_id
    WHERE cw.class_id = ?
    ORDER BY s.submitted_at DESC
");
$stmt->execute([$class_id]);
$all_submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Index submissions by classwork_id → student_id */
$submissions_map = [];
foreach ($all_submissions as $sub) {
    $submissions_map[$sub['classwork_id']][$sub['student_id']] = $sub;
}

/* GRADES — index by classwork_id → student_id */
$stmt = $conn->prepare("
    SELECT g.*, u.fname, u.lname
    FROM grades g
    JOIN users u ON g.student_id = u.id
    WHERE g.class_id = ?
");
$stmt->execute([$class_id]);
$all_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grades_map = [];
foreach ($all_grades as $g) {
    $grades_map[$g['classwork_id']][$g['student_id']] = $g;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Class View</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
*{ margin:0; padding:0; box-sizing:border-box; }

body{
    background:linear-gradient(135deg, #153c6b, #7d784a);;
    font-family:'Poppins',sans-serif;
    color:#2c3e50;
}

/* HEADER */
.header{
    background:#2c3e50;
    color:white;
    padding:25px 30px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.header h1{ font-size:26px; }

.header-meta{
    font-size:13px;
    opacity:.8;
    margin-top:4px;
}

/* NAV */
.nav{
    background:white;
    padding:12px 15px;
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    box-shadow:0 2px 10px rgba(0,0,0,0.06);
    position:sticky;
    top:0;
    z-index:100;
}

.nav a{
    text-decoration:none;
    background:#eef2ff;
    color:#4338ca;
    padding:8px 14px;
    border-radius:10px;
    font-size:13px;
    font-weight:600;
    transition:.2s;
    white-space:nowrap;
}

.nav a:hover, .nav a.active{
    background:#2c3e50;;
    color:white;
}

/* CONTAINER */
.container{
    max-width:1100px;
    margin:20px auto;
    padding:0 15px 40px;
}

/* SECTION */
.section{ display:none; }
.section.active{ display:block; }

/* CARD */
.card{
    background:white;
    border-radius:18px;
    box-shadow:0 4px 14px rgba(0,0,0,0.06);
    overflow:hidden;
}

.card-header{
    padding:18px 22px;
    border-bottom:1px solid #e2e8f0;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.card-header h2{ font-size:18px; color:#1e293b; }

/* FEED / POSTS */
.post{
    border-left:5px solid #7c3aed;
    padding:18px 22px;
    border-bottom:1px solid #f1f5f9;
    transition:.2s;
    cursor:pointer;
}

.post:hover{ background:#faf5ff; }
.post:last-child{ border-bottom:none; }

.post-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    margin-bottom:8px;
}

.post h3{ font-size:16px; color:#1e293b; }

.post p{
    color:#64748b;
    font-size:14px;
    line-height:1.6;
    margin-top:6px;
}

.post-meta{
    font-size:12px;
    color:#94a3b8;
    margin-top:8px;
    display:flex;
    gap:15px;
}

/* TYPE BADGES */
.badge{
    display:inline-block;
    padding:4px 10px;
    border-radius:999px;
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.5px;
}

.badge-lesson{ background:#dbeafe; color:#1d4ed8; }
.badge-quiz{ background:#fef3c7; color:#92400e; }
.badge-assignment{ background:#dcfce7; color:#166534; }
.badge-activity{ background:#f0fdf4; color:#15803d; }
.badge-announcement{ background:#fef9c3; color:#854d0e; }
.badge-lab{ background:#ede9fe; color:#5b21b6; }

/* POST type border colors */
.post[data-type="lesson"]{ border-color:#3b82f6; }
.post[data-type="quiz"]{ border-color:#f59e0b; }
.post[data-type="assignment"]{ border-color:#10b981; }
.post[data-type="activity"]{ border-color:#22c55e; }
.post[data-type="announcement"]{ border-color:#eab308; background:#fffbeb; }
.post[data-type="lab"]{ border-color:#7c3aed; }

/* CLASSWORK DETAIL / GRADE VIEW */
.cw-detail{
    display:none;
    background:#f8fafc;
    border-top:1px solid #e2e8f0;
    padding:15px 22px;
}

.cw-detail.open{ display:block; }

.grade-table{
    width:100%;
    border-collapse:collapse;
    font-size:14px;
    margin-top:10px;
}

.grade-table th{
    background:#1e293b;
    color:white;
    padding:10px 14px;
    text-align:left;
    font-weight:600;
}

.grade-table td{
    padding:10px 14px;
    border-bottom:1px solid #e2e8f0;
}

.grade-table tr:last-child td{ border-bottom:none; }
.grade-table tr:nth-child(even) td{ background:#f8fafc; }

.status-submitted{
    background:#dcfce7;
    color:#166534;
    padding:3px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:600;
}

.status-missing{
    background:#fee2e2;
    color:#991b1b;
    padding:3px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:600;
}

.grade-input{
    display:inline-flex;
    align-items:center;
    gap:6px;
    margin:0;
}

.grade-input input[type="number"]{
    width:70px;
    padding:5px 8px;
    border:1px solid #cbd5e1;
    border-radius:8px;
    font-size:13px;
}

.btn-grade{
    background:#4f46e5;
    color:white;
    border:none;
    padding:6px 12px;
    border-radius:8px;
    cursor:pointer;
    font-size:12px;
    font-weight:600;
}

.btn-grade:hover{ background:#4338ca; }

.grade-saved{
    color:#16a34a;
    font-weight:700;
    font-size:14px;
}

/* STUDENTS */
.student-item{
    display:flex;
    align-items:center;
    gap:12px;
    padding:12px 22px;
    border-bottom:1px solid #f1f5f9;
}

.student-item:last-child{ border-bottom:none; }

.student-avatar{
    width:40px;
    height:40px;
    border-radius:50%;
    background:#4f46e5;
    color:white;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:700;
    font-size:15px;
    flex-shrink:0;
}

.student-avatar img{
    width:40px;
    height:40px;
    border-radius:50%;
    object-fit:cover;
}

/* REQUEST ITEM */
.request-item{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:12px 22px;
    border-bottom:1px solid #f1f5f9;
}

.request-item:last-child{ border-bottom:none; }

.btn-accept{
    background:#10b981;
    color:white;
    border:none;
    padding:7px 14px;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
    font-size:13px;
}

.btn-reject{
    background:#ef4444;
    color:white;
    border:none;
    padding:7px 14px;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
    font-size:13px;
}

/* MODAL */
.modal{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.55);
    z-index:9999;
    overflow-y:auto;
    padding:30px 15px;
}

.modal-content{
    background:white;
    width:480px;
    max-width:100%;
    margin:auto;
    padding:28px;
    border-radius:18px;
    animation:fadeIn .3s ease;
}

.modal-content h2{
    margin-bottom:20px;
    color:#1e293b;
}

.form-group{
    margin-bottom:15px;
}

.form-group label{
    display:block;
    font-size:13px;
    font-weight:600;
    margin-bottom:5px;
    color:#374151;
}

.form-group input,
.form-group select,
.form-group textarea{
    width:100%;
    padding:10px 14px;
    border:1px solid #cbd5e1;
    border-radius:10px;
    font-family:'Poppins',sans-serif;
    font-size:14px;
}

.form-group textarea{
    min-height:90px;
    resize:vertical;
}

.conditional{
    transition:.3s;
}

.form-row{
    display:flex;
    gap:12px;
}

.form-row .form-group{
    flex:1;
}

.checkbox-group{
    display:flex;
    flex-direction:column;
    gap:8px;
}

.checkbox-group label{
    display:flex;
    align-items:center;
    gap:8px;
    font-weight:500;
    cursor:pointer;
}

.checkbox-group input[type="checkbox"]{
    width:auto;
    margin:0;
}

.btn-create{
    background:#4f46e5;
    color:white;
    border:none;
    padding:12px 20px;
    border-radius:10px;
    cursor:pointer;
    font-size:15px;
    font-weight:600;
    width:100%;
    margin-top:5px;
}

.btn-create:hover{ background:#4338ca; }

.btn-close-modal{
    background:#f1f5f9;
    color:#64748b;
    border:none;
    padding:12px 20px;
    border-radius:10px;
    cursor:pointer;
    font-size:14px;
    font-weight:600;
    width:100%;
    margin-top:8px;
}

/* EMPTY STATE */
.empty{
    text-align:center;
    color:#94a3b8;
    padding:40px;
    font-size:15px;
}

/* SUCCESS BANNER */
.success-banner{
    background:#10b981;
    color:white;
    padding:12px 20px;
    border-radius:12px;
    margin-bottom:15px;
    font-weight:600;
}

/* STATS ROW */
.stats-row{
    display:flex;
    gap:10px;
    margin-bottom:20px;
    flex-wrap:wrap;
}

.stat-pill{
    background:white;
    border-radius:12px;
    padding:12px 18px;
    box-shadow:0 2px 8px rgba(0,0,0,0.06);
    font-size:13px;
    font-weight:600;
    color:#374151;
}

.stat-pill span{
    font-size:22px;
    display:block;
    color:#4f46e5;
}

@keyframes fadeIn{
    from{ opacity:0; transform:translateY(10px); }
    to{ opacity:1; transform:translateY(0); }
}
</style>
</head>

<body>

<!-- HEADER -->
<div class="header">
    <div>
        <h1>📚 <?= htmlspecialchars($class['class_name']) ?></h1>
        <div class="header-meta">
            <?= htmlspecialchars($class['program'] ?? '') ?>
            <?= !empty($class['block']) ? ' · Block ' . htmlspecialchars($class['block']) : '' ?>
            · Code: <b><?= htmlspecialchars($class['class_code']) ?></b>
        </div>
    </div>
    <a href="dashboard.php" style="color:white;font-size:13px;text-decoration:none;opacity:.8;">← Dashboard</a>
</div>

<!-- NAV -->
<div class="nav">
    <a href="#" onclick="showSection('feed'); setActive(this);" class="active">🏠 All</a>
    <a href="#" onclick="filterFeed('lesson'); setActive(this);">📘 Lesson</a>
    <a href="#" onclick="filterFeed('quiz'); setActive(this);">🧠 Quiz</a>
    <a href="#" onclick="filterFeed('assignment'); setActive(this);">📝 Assignment</a>
    <a href="#" onclick="filterFeed('activity'); setActive(this);">🎯 Activity</a>
    <a href="#" onclick="filterFeed('announcement'); setActive(this);">📢 Announcement</a>
    <a href="#" onclick="showSection('students'); setActive(this);">👥 Students (<?= count($students) ?>)</a>
    <a href="#" onclick="showSection('requests'); setActive(this);">📩 Requests (<?= count($join_requests) ?>)</a>
    <a href="#" onclick="openModal(); return false;">➕ Create</a>
</div>

<div class="container">

<?php if (isset($success_message)): ?>
<div class="success-banner">✅ <?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>

<!-- STATS -->
<div class="stats-row">
    <div class="stat-pill"><span><?= count($students) ?></span>Students</div>
    <div class="stat-pill"><span><?= count($classworks) ?></span>Activities</div>
    <div class="stat-pill"><span><?= count($all_submissions) ?></span>Submissions</div>
    <div class="stat-pill"><span><?= count($join_requests) ?></span>Pending Requests</div>
</div>

<!-- ===========================
     FEED SECTION
=========================== -->
<div id="feed" class="section active">

<div class="card">
    <div class="card-header">
        <h2>Class Feed</h2>
        <span style="font-size:13px;color:#64748b;"><?= count($classworks) ?> items</span>
    </div>

    <?php if (empty($classworks)): ?>
        <div class="empty">No classworks yet. Click ➕ Create to add one.</div>
    <?php endif; ?>

    <?php foreach ($classworks as $cw): ?>

    <?php
        $type = strtolower(trim($cw['type']));
        $sub_count = count($submissions_map[$cw['id']] ?? []);
        $graded_count = count($grades_map[$cw['id']] ?? []);
        $student_count = count($students);
        $is_gradable = ($type !== 'announcement');
    ?>

    <div class="post" data-type="<?= $type ?>">

        <div class="post-header">
            <h3><?= htmlspecialchars($cw['title']) ?></h3>
            <span class="badge badge-<?= $type ?>"><?= ucfirst($type) ?></span>
        </div>

        <p><?= nl2br(htmlspecialchars($cw['description'])) ?></p>

        <div class="post-meta">
            <span>📅 <?= date("M d, Y", strtotime($cw['created_at'])) ?></span>
            <?php if ($is_gradable): ?>
                <span>🏅 <?= $cw['max_points'] ?> pts</span>
                <?php if (!empty($cw['due_date'])): ?>
                    <span>⏰ Due: <?= date("M d, Y g:i A", strtotime($cw['due_date'])) ?></span>
                <?php endif; ?>
                <span>📥 <?= $sub_count ?>/<?= $student_count ?> submitted</span>
                <span>✅ <?= $graded_count ?> graded</span>
            <?php endif; ?>
        </div>

        <?php if ($is_gradable && $student_count > 0): ?>

        <!-- TOGGLE GRADE PANEL -->
        <button onclick="toggleGradePanel(<?= $cw['id'] ?>)"
                style="background:#eef2ff;color:#4338ca;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;margin-top:12px;">
            📊 View Grades (<?= $graded_count ?>/<?= $student_count ?>)
        </button>

        <div id="grade-panel-<?= $cw['id'] ?>" class="cw-detail">

            <table class="grade-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Submission</th>
                        <th>Grade (/ <?= $cw['max_points'] ?>)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($students as $s): ?>

                <?php
                    $sub = $submissions_map[$cw['id']][$s['id']] ?? null;
                    $grade_row = $grades_map[$cw['id']][$s['id']] ?? null;
                    $current_grade = $grade_row ? $grade_row['grade'] : '';
                ?>

                <tr>
                    <td>
                        <b><?= htmlspecialchars($s['fname'] . ' ' . $s['lname']) ?></b>
                    </td>
                    <td>
                        <?php if ($sub): ?>
                            <span class="status-submitted">Submitted</span>
                            <?php if (!empty($sub['file_path'])): ?>
                                <a href="../../<?= htmlspecialchars($sub['file_path']) ?>"
                                   target="_blank"
                                   style="font-size:12px;margin-left:6px;color:#4f46e5;">📎 File</a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="status-missing">No submission</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($grade_row): ?>
                            <span class="grade-saved">
                                <?= $grade_row['grade'] ?> / <?= $cw['max_points'] ?>
                            </span>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:13px;">Not graded</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST"
                              action="../../controllers/add_grade.php"
                              class="grade-input">

                            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                            <input type="hidden" name="classwork_id" value="<?= $cw['id'] ?>">
                            <input type="hidden" name="class_id" value="<?= $class_id ?>">

                            <input type="number"
                                   name="grade"
                                   value="<?= $current_grade !== '' ? $current_grade : $cw['max_points'] ?>"
                                   min="0"
                                   max="<?= $cw['max_points'] ?>"
                                   required>

                            <button type="submit" class="btn-grade">
                                <?= $grade_row ? 'Update' : 'Save' ?>
                            </button>

                        </form>
                    </td>
                </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        </div>

        <?php endif; ?>

    </div>

    <?php endforeach; ?>

</div>
</div>

<!-- ===========================
     STUDENTS SECTION
=========================== -->
<div id="students" class="section">

<div class="card">
    <div class="card-header">
        <h2>👥 Students</h2>
        <span style="font-size:13px;color:#64748b;"><?= count($students) ?> enrolled</span>
    </div>

    <?php if (empty($students)): ?>
        <div class="empty">No students enrolled yet.</div>
    <?php else: ?>
        <?php foreach ($students as $s): ?>
        <div class="student-item">
            <div class="student-avatar">
                <?php if (!empty($s['profile_pic']) && file_exists("../../uploads/" . $s['profile_pic'])): ?>
                    <img src="../../uploads/<?= $s['profile_pic'] ?>">
                <?php else: ?>
                    <?= strtoupper(substr($s['fname'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-weight:600;">
                    <?= htmlspecialchars($s['fname'] . ' ' . $s['lname']) ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
</div>

<!-- ===========================
     JOIN REQUESTS SECTION
=========================== -->
<div id="requests" class="section">

<div class="card">
    <div class="card-header">
        <h2>📩 Join Requests</h2>
        <span style="font-size:13px;color:#64748b;"><?= count($join_requests) ?> pending</span>
    </div>

    <?php if (empty($join_requests)): ?>
        <div class="empty">No pending requests.</div>
    <?php else: ?>
        <?php foreach ($join_requests as $r): ?>
        <div class="request-item">
            <div style="font-weight:600;">
                <?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?>
            </div>
            <form method="POST" action="../../controllers/process_join_request.php"
                  style="display:flex;gap:8px;">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <input type="hidden" name="student_id" value="<?= $r['student_id'] ?>">
                <input type="hidden" name="class_id" value="<?= $r['class_id'] ?>">
                <button class="btn-accept" name="action" value="accept">✓ Accept</button>
                <button class="btn-reject" name="action" value="reject">✕ Reject</button>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>
</div>

</div>

<!-- CREATE CLASSWORK -->
<div id="modal" class="modal">
<div class="modal-content">

    <h2>➕ Create Classwork</h2>

    <form method="POST" action="../../controllers/add_classwork.php" enctype="multipart/form-data">

        <input type="hidden" name="class_id" value="<?= $class_id ?>">

        <div class="form-group">
            <label>Title *</label>
            <input type="text" name="title" required placeholder="e.g. Chapter 1 Quiz">
        </div>

        <div class="form-group">
            <label>Type *</label>
            <select name="type" id="cw_type" onchange="typeChanged(this.value)" required>
                <option value="lesson">📘 Lesson</option>
                <option value="quiz">🧠 Quiz</option>
                <option value="assignment">📝 Assignment</option>
                <option value="activity">🎯 Activity</option>
                <option value="announcement">📢 Announcement</option>
            </select>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description" placeholder="Instructions or details..."></textarea>
        </div>

        <div id="grade-fields" class="conditional">
            <div class="form-row">
                <div class="form-group">
                    <label>Max Points</label>
                    <input type="number" name="max_points" value="100" min="0">
                </div>
                <div class="form-group">
                    <label>Due Date (optional)</label>
                    <input type="datetime-local" name="due_date">
                </div>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="allow_submission" checked>
                        Allow submission
                    </label>
                    <label>
                        <input type="checkbox" name="allow_late">
                        Allow late submission
                    </label>
                    <label>
                        <input type="checkbox" name="attachment_required">
                        Require file attachment
                    </label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Attach File (optional)</label>
            <input type="file" name="file">
        </div>

        <button type="submit" class="btn-create">Create Classwork</button>
        <button type="button" class="btn-close-modal" onclick="closeModal()">Cancel</button>

    </form>

</div>
</div>

<script>
/*SECTION SWITCHING*/
function showSection(id) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
}

/* NAV ACTIVE STATE */
function setActive(el) {
    document.querySelectorAll('.nav a').forEach(a => a.classList.remove('active'));
    el.classList.add('active');
}
function filterFeed(type) {
    showSection('feed');
    document.querySelectorAll('.post').forEach(post => {
        const postType = post.getAttribute('data-type').trim().toLowerCase();
        post.style.display = (type === 'all' || postType === type) ? 'block' : 'none';
    });
}

//GRADE PANEL
function toggleGradePanel(id) {
    const panel = document.getElementById('grade-panel-' + id);
    panel.classList.toggle('open');
}

//MODAL
function openModal() {
    document.getElementById('modal').style.display = 'block';
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
}

document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

/* ===========================
   TYPE TOGGLE (hide grade fields for announcement)
=========================== */
function typeChanged(val) {
    const gradeFields = document.getElementById('grade-fields');
    gradeFields.style.display = (val === 'announcement') ? 'none' : 'block';
}

/* Auto-show all posts on load */
document.querySelectorAll('.post').forEach(p => p.style.display = 'block');
</script>

</body>
</html>