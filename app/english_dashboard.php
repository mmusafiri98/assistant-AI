<?php
session_start();

$dsn = "pgsql:host=ep-autumn-salad-adwou7x2-pooler.c-2.us-east-1.aws.neon.tech;port=5432;dbname=veronica_db_login;sslmode=require";
$username_db = "neondb_owner";
$password_db = "npg_QolPDv5L9gVj";

$username = $_SESSION['username'] ?? null;
$user_exists = false;

if($username){

try{

$conn = new PDO($dsn,$username_db,$password_db,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

$sql="SELECT username FROM users WHERE username=:username LIMIT 1";
$stmt=$conn->prepare($sql);
$stmt->execute([':username'=>$username]);

if($stmt->fetch()){
$user_exists=true;
}

}catch(PDOException $e){
error_log($e->getMessage());
}

}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<title>English Dashboard – Veronica AI</title>

<style>

body{
margin:0;
font-family:Arial;
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
text-align:center;
box-shadow:0 15px 40px rgba(0,0,0,0.2);
}

.grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:20px;
margin-top:30px;
}

.card{
background:#eef2ff;
padding:25px;
border-radius:15px;
cursor:pointer;
transition:0.3s;
font-size:18px;
font-weight:bold;
}

.card:hover{
background:#e0e7ff;
transform:translateY(-5px);
}

.icon{
font-size:35px;
margin-bottom:10px;
}

.logout button{
margin-top:30px;
background:#ef4444;
color:white;
border:none;
padding:10px 20px;
border-radius:10px;
cursor:pointer;
}

</style>

</head>

<body>

<div class="dashboard">

<?php if($user_exists): ?>

<h1>Welcome <?=htmlspecialchars($username)?> 👋</h1>

<p>Your English learning dashboard</p>

<div class="grid">

<div class="card" onclick="window.location.href='english_vocabulary.php'">
<div class="icon">📚</div>
Vocabulary
</div>

<div class="card" onclick="window.location.href='english_grammar.php'">
<div class="icon">✍️</div>
Grammar
</div>

<div class="card" onclick="window.location.href='english_pronunciation.php'">
<div class="icon">🎤</div>
Pronunciation
</div>

<div class="card" onclick="window.location.href='english_conversation.php'">
<div class="icon">💬</div>
Conversation
</div>

<div class="card" onclick="window.location.href='english_listening.php'">
<div class="icon">🎧</div>
Listening
</div>

<div class="card" onclick="window.location.href='english_reading.php'">
<div class="icon">📖</div>
Reading
</div>

</div>

<div class="logout">

<button onclick="window.location.href='index.php'">
Logout
</button>

</div>

<?php else: ?>

<h1>User not recognized</h1>
<button onclick="window.location.href='index.php'">Login</button>

<?php endif; ?>

</div>

</body>
</html>
