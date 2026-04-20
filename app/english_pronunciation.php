<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Infinite English Pronunciation Trainer</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
}

body{
font-family:Arial, sans-serif;
background:linear-gradient(135deg,#4f46e5,#06b6d4);
min-height:100vh;
display:flex;
justify-content:center;
align-items:center;
padding:20px;
}

.container{
background:white;
width:95%;
max-width:900px;
padding:35px;
border-radius:25px;
box-shadow:0 15px 40px rgba(0,0,0,0.25);
text-align:center;
}

h1{
color:#4f46e5;
margin-bottom:10px;
}

.subtitle{
color:#64748b;
margin-bottom:25px;
font-size:16px;
}

.sentence-box{
background:#f8fafc;
border-radius:18px;
padding:25px;
margin-bottom:25px;
}

.sentence{
font-size:30px;
font-weight:bold;
color:#0f172a;
line-height:1.4;
}

.controls{
display:flex;
flex-wrap:wrap;
justify-content:center;
gap:12px;
margin-bottom:20px;
}

button{
padding:14px 22px;
border:none;
border-radius:12px;
cursor:pointer;
font-size:16px;
font-weight:bold;
color:white;
transition:0.2s;
}

button:hover{
transform:scale(1.04);
}

.listen{
background:#4f46e5;
}

.speak{
background:#10b981;
}

.next{
background:#f59e0b;
}

.auto{
background:#0ea5e9;
}

.reset{
background:#ef4444;
}

.result{
margin-top:20px;
font-size:20px;
color:#111827;
min-height:50px;
}

.score{
margin-top:15px;
font-size:18px;
font-weight:bold;
color:#4f46e5;
}

.counter{
margin-top:10px;
font-size:16px;
color:#475569;
}

.footer{
margin-top:25px;
font-size:14px;
color:#64748b;
}

</style>
</head>

<body>

<div class="container">

<h1>🎤 Infinite English Pronunciation Trainer</h1>

<div class="subtitle">
Practice with unlimited English phrases generated automatically
</div>

<div class="sentence-box">
<div class="sentence" id="sentence"></div>
</div>

<div class="controls">

<button class="listen" onclick="playSentence()">🔊 Listen</button>

<button class="speak" onclick="startRecognition()">🎙 Speak</button>

<button class="next" onclick="nextSentence()">➡ Next</button>

<button class="auto" onclick="autoListen()">🔁 Auto Listen</button>

<button class="reset" onclick="resetScore()">♻ Reset</button>

</div>

<div class="result" id="result"></div>

<div class="score" id="score">Score: 0</div>

<div class="counter" id="counter">
Phrase 1 / 10,000+
</div>

<div class="footer">
Unlimited combinations = 10,000+ phrases
</div>

</div>

<script>

/* ---------- WORD BANK ---------- */

const subjects = [
"I","You","We","They","He","She","My brother","My sister",
"The teacher","My friend","The student","Our family"
];

const verbs = [
"like","love","want","need","prefer","study","watch",
"build","create","buy","sell","find","choose","remember",
"visit","improve","practice","learn","open","close"
];

const objects = [
"music","a new car","this city","my future","English",
"a better job","success","the world","a laptop",
"new ideas","a house","the lesson","this game",
"good opportunities","real happiness","your help"
];

const extras = [
"every day",
"right now",
"in the morning",
"at night",
"with my friends",
"for the future",
"without fear",
"with passion",
"this week",
"next month",
"very quickly",
"with confidence",
"before dinner",
"after school",
"during summer"
];

/* ---------- VARIABLES ---------- */

let currentSentence = "";
let score = 0;
let phraseCount = 1;
let autoMode = false;

/* ---------- GENERATE SENTENCE ---------- */

function randomItem(arr){
return arr[Math.floor(Math.random()*arr.length)];
}

function generateSentence(){

let sentence =
randomItem(subjects) + " " +
randomItem(verbs) + " " +
randomItem(objects) + " " +
randomItem(extras);

return sentence;

}

/* ---------- SHOW SENTENCE ---------- */

function showSentence(){

currentSentence = generateSentence();

document.getElementById("sentence").innerText = currentSentence;

document.getElementById("result").innerHTML = "";

document.getElementById("counter").innerText =
"Phrase " + phraseCount + " / 10,000+";

}

showSentence();

/* ---------- SPEAK ---------- */

function playSentence(){

const speech = new SpeechSynthesisUtterance(currentSentence);

speech.lang = "en-US";
speech.rate = 0.9;
speech.pitch = 1;

speechSynthesis.cancel();
speechSynthesis.speak(speech);

}

/* ---------- RECOGNITION ---------- */

function startRecognition(){

if(!('webkitSpeechRecognition' in window)){

alert("Speech recognition not supported in this browser.");
return;

}

const recognition = new webkitSpeechRecognition();

recognition.lang = "en-US";
recognition.interimResults = false;
recognition.maxAlternatives = 1;

recognition.start();

recognition.onresult = function(event){

const spoken =
event.results[0][0].transcript.toLowerCase();

const target =
currentSentence.toLowerCase();

document.getElementById("result").innerHTML =
"You said: <b>" + spoken + "</b>";

if(compareSpeech(spoken,target)){

score++;

document.getElementById("result").innerHTML +=
"<br>✅ Great pronunciation!";

}else{

document.getElementById("result").innerHTML +=
"<br>❌ Try again";

}

document.getElementById("score").innerText =
"Score: " + score;

};

}

/* ---------- COMPARE ---------- */

function compareSpeech(user,target){

let userWords = user.split(" ");
let targetWords = target.split(" ");

let correct = 0;

targetWords.forEach(word=>{
if(userWords.includes(word)) correct++;
});

return correct >= Math.floor(targetWords.length * 0.6);

}

/* ---------- NEXT ---------- */

function nextSentence(){

phraseCount++;

showSentence();

}

/* ---------- AUTO LISTEN ---------- */

function autoListen(){

if(autoMode) return;

autoMode = true;

let count = 0;

const interval = setInterval(()=>{

playSentence();

count++;

if(count >= 8){
clearInterval(interval);
autoMode = false;
}

},4000);

}

/* ---------- RESET ---------- */

function resetScore(){

score = 0;
phraseCount = 1;

document.getElementById("score").innerText =
"Score: 0";

showSentence();

}

</script>

</body>
</html>
