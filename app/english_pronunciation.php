<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<title>English Pronunciation Training</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

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

.container{

background:white;
padding:40px;
border-radius:20px;
max-width:700px;
width:90%;
text-align:center;
box-shadow:0 10px 30px rgba(0,0,0,0.2);

}

h1{
color:#4f46e5;
}

.word{

font-size:36px;
font-weight:bold;
margin:25px 0;
color:#1e293b;

}

.buttons{

margin-top:20px;

}

button{

padding:12px 20px;
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

.next{

background:#f59e0b;
color:white;

}

.result{

margin-top:20px;
font-size:18px;

}

.score{

margin-top:15px;
font-weight:bold;

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

<p>Listen to the word and repeat it with your microphone.</p>

<div class="word" id="word"></div>

<div class="buttons">

<button class="listen" onclick="playWord()">
🔊 Listen
</button>

<button class="speak" onclick="startRecognition()">
🎙 Speak
</button>

<button class="next" onclick="nextWord()">
➡ Next word
</button>

</div>

<div class="result" id="result"></div>

<div class="score" id="score"></div>

<button class="back" onclick="window.location.href='english_dashboard.php'">
⬅ Back to Dashboard
</button>

</div>

<script>

const words = [
"hello",
"teacher",
"student",
"language",
"computer",
"travel",
"music",
"friend",
"beautiful",
"opportunity"
];

let index = 0;
let score = 0;

const wordElement = document.getElementById("word");

function showWord(){

wordElement.innerText = words[index];

}

showWord();

function playWord(){

const speech = new SpeechSynthesisUtterance(words[index]);

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

const spoken = event.results[0][0].transcript.toLowerCase();

document.getElementById("result").innerHTML =
"You said: <b>"+spoken+"</b>";

if(spoken.includes(words[index])){

score++;

document.getElementById("result").innerHTML +=
"<br>✅ Good pronunciation!";

}else{

document.getElementById("result").innerHTML +=
"<br>❌ Try again";

}

document.getElementById("score").innerText =
"Score: "+score+" / "+words.length;

};

}

function nextWord(){

if(index < words.length-1){

index++;

showWord();

document.getElementById("result").innerHTML="";

}else{

document.getElementById("result").innerHTML =
"🎉 Exercise completed!";

}

}

</script>

</body>
</html>
