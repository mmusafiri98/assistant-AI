<?php
session_start();
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

</style>

</head>

<body>

<div class="container">

<h1>🎉 Quiz Completed!</h1>

<p>

Thank you for completing your English learning profile.

Veronica AI will now prepare your personalized learning path.

</p>

<button onclick="window.location.href='english_dashboard.php'">

Go to my English Dashboard

</button>

</div>

<script>

const msg="Congratulations. Your English learning profile has been created. Click the button to go to your dashboard.";

if('speechSynthesis' in window){

const speech=new SpeechSynthesisUtterance(msg);
speech.lang="en-US";
speechSynthesis.speak(speech);

}

</script>

</body>
</html>
