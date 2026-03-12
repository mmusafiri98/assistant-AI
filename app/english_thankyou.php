<?php
session_start();

/* ===== DATABASE CONNECTION ===== */

$dsn = "pgsql:host=ep-autumn-salad-adwou7x2-pooler.c-2.us-east-1.aws.neon.tech;port=5432;dbname=veronica_db_login;sslmode=require";
$username_db = "neondb_owner";
$password_db = "npg_QolPDv5L9gVj";

$username = $_SESSION['username'] ?? null;
$user_exists = false;

if($username){

    try{

        $conn = new PDO($dsn,$username_db,$password_db,[
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $sql = "SELECT username FROM users WHERE username = :username LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':username'=>$username]);

        if($stmt->fetch()){
            $user_exists = true;
        }

    }catch(PDOException $e){
        error_log("DB error: ".$e->getMessage());
    }

}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<title>Thank You – Veronica AI</title>

<style>

body{
margin:0;
font-family:Arial;
background:linear-gradient(135deg,#6366f1,#38bdf8);
height:100vh;
display:flex;
align-items:center;
justify-content:center;
}

.container{
background:white;
padding:40px;
border-radius:20px;
text-align:center;
width:90%;
max-width:500px;
box-shadow:0 10px 30px rgba(0,0,0,0.2);
}

h1{
color:#4f46e5;
}

.username{
font-weight:bold;
color:#1e40af;
}

button{
margin-top:25px;
padding:12px 25px;
border:none;
background:#4f46e5;
color:white;
font-size:16px;
border-radius:10px;
cursor:pointer;
}

button:hover{
background:#4338ca;
}

.error{
color:red;
font-weight:bold;
}

</style>

</head>

<body>

<div class="container">

<?php if($user_exists): ?>

<h1>🎉 Quiz Completed!</h1>

<p>

Thank you <span class="username"><?=htmlspecialchars($username)?></span> for completing your English learning profile.

</p>

<p>

Veronica AI is preparing your personalized English learning path.

</p>

<button onclick="window.location.href='english_dashboard.php'">

Go to my English Dashboard

</button>

<?php else: ?>

<h1>User not recognized</h1>

<p class="error">

We could not verify your account. Please log in again.

</p>

<button onclick="window.location.href='index.php'">

Return to Login

</button>

<?php endif; ?>

</div>

<script>

<?php if($user_exists): ?>

const msg="Congratulations <?=htmlspecialchars($username)?>. Your English learning profile has been created. Click the button to go to your dashboard.";

<?php else: ?>

const msg="User not recognized. Please log in again.";

<?php endif; ?>

if('speechSynthesis' in window){

const speech=new SpeechSynthesisUtterance(msg);
speech.lang="en-US";
speechSynthesis.speak(speech);

}

</script>

</body>
</html>
