<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<title>English Pronunciation Training</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>

body{
font-family:Arial;
background:linear-gradient(135deg,#6366f1,#38bdf8);
margin:0;
padding:40px;
}

.container{

background:white;
padding:40px;
border-radius:20px;
max-width:700px;
margin:auto;
text-align:center;
box-shadow:0 10px 30px rgba(0,0,0,0.2);

}

.word{

font-size:32px;
font-weight:bold;
margin:20px 0;

}

button{

padding:10px 20px;
margin:10px;
border:none;
border-radius:10px;
cursor:pointer;
font-size:16px;

}

.listen{

background:#4f46e5;
color:white;

}

.speak{

background:#10b981;
color:white;

}

.result{

margin-top:20px;
font-size:18px;
color:#374151;

}

.back{

margin-top:30px;
background:#ef4444;
color:white;

}

</style>

</head>

<body>

<div class="container">

<h1>🎤 English Pronunciation Practice</h1>

<p>Listen to the word and repeat it.</p>

<div class="word" id="word">Hello</div>

<button class="listen" onclick="playWord()">
🔊 Listen
</button>

<button class="speak" onclick="startRecognition()">
🎙 Speak
</button>

<div class="result" id="result"></div>

<button class="back" onclick="window.location.href='english_dashboard.php'">
⬅ Back to Dashboard
</button>

</div>

<script>

let currentWord = "Hello";

function playWord(){

const speech = new SpeechSynthesisUtterance(currentWord);
speech.lang="en-US";

speechSynthesis.speak(speech);

}

function startRecognition(){

if(!('webkitSpeechRecognition' in window)){

alert("Speech recognition not supported in this browser");
return;

}

const recognition = new webkitSpeechRecognition();

recognition.lang="en-US";
recognition.start();

recognition.onresult = function(event){

const spoken = event.results[0][0].transcript;

document.getElementById("result").innerHTML =
"You said: <b>"+spoken+"</b>";

}

}

</script>

</body>
</html>
