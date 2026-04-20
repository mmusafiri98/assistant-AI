<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>English Pronunciation Master Trainer</title>

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
max-width:850px;
padding:35px;
border-radius:25px;
box-shadow:0 15px 40px rgba(0,0,0,0.25);
text-align:center;
}

h1{
color:#4f46e5;
margin-bottom:15px;
}

p{
color:#475569;
margin-bottom:20px;
}

.level-box{
display:flex;
flex-wrap:wrap;
justify-content:center;
gap:10px;
margin-bottom:25px;
}

.level-btn{
padding:10px 18px;
border:none;
border-radius:10px;
cursor:pointer;
background:#e2e8f0;
font-weight:bold;
}

.level-btn.active{
background:#4f46e5;
color:white;
}

.sentence{
font-size:30px;
font-weight:bold;
color:#0f172a;
margin:30px 0;
line-height:1.4;
}

.controls{
display:flex;
flex-wrap:wrap;
justify-content:center;
gap:12px;
margin-top:20px;
}

button{
padding:14px 20px;
border:none;
border-radius:12px;
font-size:16px;
cursor:pointer;
font-weight:bold;
transition:0.2s;
}

button:hover{
transform:scale(1.04);
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

.auto{
background:#0ea5e9;
color:white;
}

.back{
background:#ef4444;
color:white;
margin-top:20px;
}

.result{
margin-top:25px;
font-size:20px;
color:#111827;
min-height:40px;
}

.score{
margin-top:15px;
font-size:18px;
font-weight:bold;
color:#4f46e5;
}

.progress{
margin-top:10px;
font-size:15px;
color:#64748b;
}

</style>
</head>

<body>

<div class="container">

<h1>🎤 English Pronunciation Master Trainer</h1>

<p>Listen to billions of English phrases and improve pronunciation.</p>

<div class="level-box">

<button class="level-btn active" onclick="changeLevel('easy',this)">Easy</button>
<button class="level-btn" onclick="changeLevel('medium',this)">Medium</button>
<button class="level-btn" onclick="changeLevel('hard',this)">Hard</button>

</div>

<div class="sentence" id="sentence"></div>

<div class="controls">

<button class="listen" onclick="playSentence()">🔊 Listen</button>

<button class="speak" onclick="startRecognition()">🎙 Speak</button>

<button class="next" onclick="nextSentence()">➡ Next</button>

<button class="auto" onclick="autoMode()">🔁 Auto Listen</button>

</div>

<div class="result" id="result"></div>

<div class="score" id="score">Score: 0</div>

<div class="progress" id="progress"></div>

<button class="back" onclick="window.location.href='english_dashboard.php'">
⬅ Back
</button>

</div>

<script>

const easy = [
"Hello how are you",
"I like music",
"Where are you",
"I am happy today",
"Can you help me",
"I love this city",
"What is your name",
"This is my house",
"I want some water",
"Good morning friend"
];

const medium = [
"I would like to travel around the world",
"The weather is beautiful today",
"I need to improve my English speaking skills",
"She is studying at the university",
"We are going to the supermarket later",
"My brother works in a big company",
"I enjoy reading books at night",
"The train arrives in ten minutes",
"Can you repeat that sentence please",
"I forgot my wallet at home"
];

const hard = [
"Success comes from consistency and discipline",
"I want to become fluent in English quickly",
"The opportunity was greater than I expected",
"Technology is changing the modern world rapidly",
"I believe confidence is built through practice",
"Sometimes failure teaches more than success",
"The pronunciation of this phrase is difficult",
"I am creating a better future for myself",
"Every challenge can become a lesson",
"Communication is the key to growth"
];

let currentList = easy;
let index = 0;
let score = 0;
let autoPlaying = false;

const sentenceEl = document.getElementById("sentence");

function showSentence(){

sentenceEl.innerText = currentList[index];

document.getElementById("progress").innerText =
"Phrase " + (index+1) + " / " + currentList.length;

}

showSentence();

function changeLevel(level,btn){

document.querySelectorAll(".level-btn").forEach(b=>{
b.classList.remove("active");
});

btn.classList.add("active");

if(level==="easy") currentList = easy;
if(level==="medium") currentList = medium;
if(level==="hard") currentList = hard;

index = 0;
score = 0;

document.getElementById("score").innerText="Score: 0";
document.getElementById("result").innerHTML="";

showSentence();

}

function playSentence(){

const speech = new SpeechSynthesisUtterance(currentList[index]);

speech.lang="en-US";
speech.rate=0.9;
speech.pitch=1;

speechSynthesis.speak(speech);

}

function startRecognition(){

if(!('webkitSpeechRecognition' in window)){

alert("Speech recognition not supported");

return;

}

const recognition = new webkitSpeechRecognition();

recognition.lang="en-US";
recognition.interimResults=false;

recognition.start();

recognition.onresult = function(event){

const spoken = event.results[0][0].transcript.toLowerCase();

const target = currentList[index].toLowerCase();

document.getElementById("result").innerHTML =
"You said: <b>"+spoken+"</b>";

if(compareWords(spoken,target)){

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

function compareWords(a,b){

let aw = a.split(" ");
let bw = b.split(" ");

let count = 0;

bw.forEach(word=>{
if(aw.includes(word)) count++;
});

return count >= Math.floor(bw.length*0.7);

}

function nextSentence(){

if(index < currentList.length-1){

index++;

showSentence();

document.getElementById("result").innerHTML="";

}else{

document.getElementById("result").innerHTML =
"🎉 Training completed!";

}

}

function autoMode(){

if(autoPlaying) return;

autoPlaying = true;

let repeat = setInterval(()=>{

playSentence();

if(!autoPlaying){
clearInterval(repeat);
}

},4000);

setTimeout(()=>{
autoPlaying=false;
},30000);

}

</script>

</body>
</html>
