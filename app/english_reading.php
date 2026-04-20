<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Infinite English Reading Practice</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
}

body{
font-family:Arial, sans-serif;
background:linear-gradient(135deg,#6366f1,#38bdf8);
min-height:100vh;
display:flex;
justify-content:center;
align-items:center;
padding:20px;
}

.container{
background:white;
padding:35px;
border-radius:22px;
max-width:950px;
width:100%;
box-shadow:0 12px 35px rgba(0,0,0,0.22);
}

h1{
text-align:center;
color:#4f46e5;
margin-bottom:10px;
}

.subtitle{
text-align:center;
color:#64748b;
margin-bottom:25px;
}

.story{
background:#f8fafc;
padding:25px;
border-radius:14px;
line-height:1.8;
font-size:18px;
color:#0f172a;
margin-bottom:25px;
}

.controls{
display:flex;
flex-wrap:wrap;
gap:10px;
justify-content:center;
margin-bottom:25px;
}

button{
padding:12px 18px;
border:none;
border-radius:10px;
cursor:pointer;
font-size:15px;
font-weight:bold;
color:white;
transition:0.2s;
}

button:hover{
transform:scale(1.04);
}

.listen{background:#10b981;}
.check{background:#4f46e5;}
.next{background:#f59e0b;}
.reset{background:#ef4444;}

.question{
background:#f1f5f9;
padding:18px;
border-radius:12px;
margin-bottom:15px;
}

.question p{
margin-bottom:12px;
font-weight:bold;
color:#111827;
}

label{
display:block;
padding:8px;
border-radius:8px;
margin-bottom:6px;
cursor:pointer;
color:#334155;
transition:0.2s;
}

label:hover{
background:#e2e8f0;
}

.correct{
background:#dcfce7 !important;
border:1px solid #22c55e;
}

.wrong{
background:#fee2e2 !important;
border:1px solid #ef4444;
}

.result{
margin-top:20px;
font-size:22px;
font-weight:bold;
text-align:center;
color:#111827;
}

.counter{
margin-top:12px;
text-align:center;
color:#64748b;
font-size:15px;
}

.stats{
margin-top:10px;
text-align:center;
font-size:16px;
color:#4f46e5;
font-weight:bold;
}

</style>
</head>

<body>

<div class="container">

<h1>📖 Infinite Reading Practice</h1>

<div class="subtitle">
Automatic reading + text comprehension with answer correction
</div>

<div class="controls">

<button class="listen" onclick="readStory()">🔊 Listen</button>

<button class="check" onclick="checkAnswers()">✅ Check Answers</button>

<button class="next" onclick="nextExercise()">➡ Next Exercise</button>

<button class="reset" onclick="resetStats()">♻ Reset</button>

</div>

<div class="story" id="story"></div>

<div id="questions"></div>

<div class="result" id="result"></div>

<div class="stats" id="stats">
Total Score: 0
</div>

<div class="counter" id="counter">
Exercise 1 / Infinite
</div>

</div>

<script>

/* =======================
DATA
======================= */

const names = [
"John","Emma","Lucas","Sophia","Daniel",
"Olivia","Michael","Anna","David","Julia"
];

const cities = [
"London","Rome","Paris","Madrid","Berlin",
"Toronto","Tokyo","Sydney","Dublin","New York"
];

const transport = [
"by bus","by train","by bike","by car","on foot"
];

const subjects = [
"English","history","math","science","music","art"
];

const hobbies = [
"plays football",
"reads books",
"goes swimming",
"watches movies",
"plays tennis",
"studies online",
"listens to music",
"goes jogging"
];

const drinks = [
"coffee","tea","juice","milk","water"
];

/* =======================
VARIABLES
======================= */

let exercise = 1;
let totalScore = 0;
let correctAnswers = {};

/* =======================
HELPERS
======================= */

function random(arr){
return arr[Math.floor(Math.random()*arr.length)];
}

function shuffle(arr){

for(let i=arr.length-1;i>0;i--){

let j=Math.floor(Math.random()*(i+1));

[arr[i],arr[j]]=[arr[j],arr[i]];

}

return arr;

}

/* =======================
GENERATE EXERCISE
======================= */

function generateExercise(){

const person = random(names);
const city = random(cities);
const move = random(transport);
const sub1 = random(subjects);

let sub2 = random(subjects);

while(sub2 === sub1){
sub2 = random(subjects);
}

const hobby = random(hobbies);
const drink = random(drinks);

document.getElementById("story").innerHTML = `
${person} is a student.<br><br>
${person} lives in ${city}.<br><br>
Every morning ${person} wakes up at 7 o'clock.<br><br>
${person} drinks ${drink} and eats breakfast.<br><br>
After breakfast, ${person} goes to school ${move}.<br><br>
${person} likes ${sub1} and ${sub2}.<br><br>
After school, ${person} ${hobby}.
`;

correctAnswers = {
q1: city,
q2: move,
q3: sub1 + " and " + sub2
};

const cityOptions = shuffle([
city,
random(cities),
random(cities)
]);

const moveOptions = shuffle([
move,
random(transport),
random(transport)
]);

const subOptions = shuffle([
sub1 + " and " + sub2,
random(subjects)+" and "+random(subjects),
random(subjects)+" and "+random(subjects)
]);

document.getElementById("questions").innerHTML = `

<div class="question">
<p>1. Where does ${person} live?</p>
${renderOptions("q1", cityOptions)}
</div>

<div class="question">
<p>2. How does ${person} go to school?</p>
${renderOptions("q2", moveOptions)}
</div>

<div class="question">
<p>3. What subjects does ${person} like?</p>
${renderOptions("q3", subOptions)}
</div>

`;

document.getElementById("result").innerHTML = "";
document.getElementById("counter").innerText =
"Exercise " + exercise + " / Infinite";

}

/* =======================
RENDER OPTIONS
======================= */

function renderOptions(name,options){

let html = "";

options.forEach(option=>{

html += `
<label>
<input type="radio" name="${name}" value="${option}">
 ${option}
</label>
`;

});

return html;

}

/* =======================
CHECK ANSWERS
======================= */

function checkAnswers(){

let score = 0;

/* remove old styles */
document.querySelectorAll("label").forEach(l=>{
l.classList.remove("correct","wrong");
});

for(let key in correctAnswers){

const radios =
document.querySelectorAll(`input[name="${key}"]`);

radios.forEach(radio=>{

const label = radio.parentElement;

/* correct answer highlighted */
if(radio.value === correctAnswers[key]){
label.classList.add("correct");
}

/* selected wrong */
if(radio.checked &&
radio.value !== correctAnswers[key]){
label.classList.add("wrong");
}

});

/* count score */
const selected =
document.querySelector(`input[name="${key}"]:checked`);

if(selected &&
selected.value === correctAnswers[key]){
score++;
}

}

totalScore += score;

document.getElementById("result").innerHTML =
"🎯 Score: " + score + " / 3";

document.getElementById("stats").innerHTML =
"Total Score: " + totalScore;

}

/* =======================
NEXT
======================= */

function nextExercise(){

exercise++;
generateExercise();

}

/* =======================
RESET
======================= */

function resetStats(){

exercise = 1;
totalScore = 0;

document.getElementById("stats").innerHTML =
"Total Score: 0";

generateExercise();

}

/* =======================
VOICE
======================= */

function readStory(){

const text =
document.getElementById("story").innerText;

const speech =
new SpeechSynthesisUtterance(text);

speech.lang = "en-US";
speech.rate = 0.95;

speechSynthesis.cancel();
speechSynthesis.speak(speech);

}

/* START */

generateExercise();

</script>

</body>
</html>
