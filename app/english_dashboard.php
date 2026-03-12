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
<title>English Dashboard – Veronica AI</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>

body{
margin:0;
font-family:Poppins,Arial;
background:linear-gradient(135deg,#6366f1,#38bdf8);
min-height:100vh;
display:flex;
justify-content:center;
align-items:center;
}

.dashboard{

background:white;
width:95%;
max-width:900px;
padding:40px;
border-radius:20px;
box-shadow:0 15px 40px rgba(0,0,0,0.2);
text-align:center;

}

h1{
color:#4f46e5;
margin-bottom:5px;
}

.subtitle{
color:#475569;
margin-bottom:30px;
}

.grid{

display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:20px;

}

.card{

background:#eef2ff;
padding:25px;
border-radius:15px;
cursor:pointer;
transition:0.3s;
font-size:18px;
font-weight:600;

}

.card:hover{

background:#e0e7ff;
transform:translateY(-5px);

}

.icon{

font-size:35px;
margin-bottom:10px;

}

.progress-box{

margin-top:30px;
background:#f1f5f9;
padding:20px;
border-radius:15px;

}

.progress-bar{

width:100%;
height:18px;
background:#e2e8f0;
border-radius:10px;
overflow:hidden;
margin-top:10px;

}

.progress{

height:100%;
width:30%;
background:#4f46e5;

}

.logout{

margin-top:25px;

}

.logout button{

background:#ef4444;
color:white;
border:none;
padding:10px 20px;
border-radius:10px;
cursor:pointer;

}

.logout button:hover{

background:#dc2626;

}

</style>

</head>

<body>

<div class="dashboard">

<?php if($user_exists): ?>

<h1>Welcome <?=htmlspecialchars($username)?> 👋</h1>

<p class="subtitle">
Your English learning dashboard powered by Veronica AI
</p>

<div class="grid">

<div class="card" onclick="alert('Vocabulary lessons coming soon')">
<div class="icon">📚</div>
Vocabulary
</div>

<div class="card" onclick="alert('Grammar lessons coming soon')">
<div class="icon">✍️</div>
Grammar
</div>

<div class="card" onclick="alert('Pronunciation practice coming soon')">
<div class="icon">🎤</div>
Pronunciation
</div>

<div class="card" onclick="alert('Conversation training coming soon')">
<div class="icon">💬</div>
Conversation
</div>

<div class="card" onclick="alert('Listening exercises coming soon')">
<div class="icon">🎧</div>
Listening
</div>

<div class="card" onclick="alert('Reading exercises coming soon')">
<div class="icon">📖</div>
Reading
</div>

</div>

<div class="progress-box">

<h3>Your English Progress</h3>

<p>Current Level: Beginner</p>

<div class="progress-bar">

<div class="progress"></div>

</div>

<p style="margin-top:10px">30% of your learning path completed</p>

</div>

<div class="logout">

<button onclick="window.location.href='logout.php'">
Logout
</button>

</div>

<?php else: ?>

<h1>User not recognized</h1>

<p>Please login again.</p>

<button onclick="window.location.href='index.php'">
Login
</button>

<?php endif; ?>

</div>

<script>

<?php if($user_exists): ?>

const msg="Welcome <?=htmlspecialchars($username)?>. This is your English learning dashboard. Choose a lesson to start improving your English.";

<?php else: ?>

const msg="User not recognized. Please login again.";

<?php endif; ?>

if('speechSynthesis' in window){

const speech = new SpeechSynthesisUtterance(msg);
speech.lang = "en-US";
speechSynthesis.speak(speech);

}

</script>

</body>
</html>
