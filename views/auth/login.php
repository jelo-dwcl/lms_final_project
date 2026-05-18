<?php
session_start();

// REDIRECT IF ALREADY LOGGED IN
if (isset($_SESSION['user'])) {

    if ($_SESSION['user']['role'] === 'professor') {
        header("Location: ../professor/dashboard.php");
        exit;
    }

    header("Location: ../student/dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <link rel="stylesheet" href="../../assets/css/style.css">

    <style>
    
        #intro {
            position: fixed;
            inset: 0;
            background: black;
            z-index: 999999;
        }

        #intro video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .fade-out {
            opacity: 0;
            transition: 1.5s ease;
        }
    </style>
</head>

<body>

<!--INTRO VIDEO -->
<div id="intro">
    <video id="introVideo" autoplay muted>
        <source src="../../assets/video/intro.mp4" type="video/mp4">
    </video>
</div>

<div class="bg">
    <img src="../../assets/images/bg.png" alt="Background">
</div>

<img src="../../assets/images/logo.png" class="logo-left" alt="Logo">

<!--MESSAGE DISPLAY -->
<?php
if (isset($_SESSION['success_msg'])) {
    echo "<p class='msg'>" . $_SESSION['success_msg'] . "</p>";
    unset($_SESSION['success_msg']);
}

if (isset($_SESSION['error_msg'])) {
    echo "<p class='error'>" . $_SESSION['error_msg'] . "</p>";
    unset($_SESSION['error_msg']);
}
?>

<!-- CHOICE BOX -->
<div class="choice-box">
    <h2 style="font-size:50px; font-family:Arial; color: white; text-shadow: 3px 3px 0px black;"><b>Welcome</b></h2>

    <button onclick="openModal('loginModal')">Login</button>
    <button onclick="openModal('registerModal')">Sign Up</button>
</div>

<!--LOGIN MODAL -->
<div id="loginModal" class="modal">
    <div class="modal-content">

        <span class="close" onclick="closeModal('loginModal')">&times;</span>

        <h2>LOGIN</h2>

        <form method="POST" action="../../index.php?action=login">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>

            <button type="submit">Login</button>
        </form>

        <p class="link">
            <a href="forgot.php">Forgot Password?</a>
        </p>

    </div>
</div>

<!--REGISTER MODAL-->
<div id="registerModal" class="modal">
    <div class="modal-content">

        <span class="close" onclick="closeModal('registerModal')">&times;</span>

        <h2>REGISTER</h2>

        <form method="POST" action="../../index.php?action=register">
            <input type="text" name="fname" placeholder="First Name" required>
            <input type="text" name="mname" placeholder="Middle Name">
            <input type="text" name="lname" placeholder="Last Name" required>

            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>

            <select name="role" required>
                <option value="">Select Role</option>
                <option value="student">Student</option>
                <option value="professor">Professor</option>
            </select>

            <button type="submit">Register</button>
        </form>

    </div>
</div>

<script src="../../assets/js/script.js"></script>

<script>
    const video = document.getElementById("introVideo");
    const intro = document.getElementById("intro");

    video.onended = function () {
        endIntro();
    };

    setTimeout(() => {
        endIntro();
    }, 8000);

    function endIntro() {
        intro.classList.add("fade-out");

        setTimeout(() => {
            intro.style.display = "none";
        }, 1500);
    }
</script>

</body>
</html>