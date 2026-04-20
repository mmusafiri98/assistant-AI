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
max-width:900px;
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
margin-bottom:25px;
justify-content:center;
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
margin-bottom:8px;
cursor:pointer;
color:#334155;
}

.result{
margin-top:20px;
font-size:20px;
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

</style>
</head>

<body>

<div class="container">

<h1>📖 Infinite Reading Practice</h1>

<div class="subtitle">
Unlimited automatic reading + text comprehension exercises
</div>

<div class="controls">

<button class="listen" onclick="readStory()">🔊 Listen</button>

<button class="check" onclick="checkAnswers()">✅ Check Answers</button>

<button class="next" onclick="generateExercise()">➡ Next Exercise</button>

<button class="reset" onclick="resetStats()">♻ Reset</button>

</div>

<div class="story" id="story"></div>

<div id="questions"></div>

<div class="result" id="result"></div>

<div class="counter" id="counter">
Exercise 1 / Infinite
</div>

</div>

<script>

/* ===========================
   DATA BANK
=========================== */

const names = [
"John","Emma","Lucas","Sophia","Daniel",
"Olivia","Michael","Anna","David","Julia"
];

const cities = [
"London","Rome","Paris","Madrid","Berlin",
"Toronto","New York","Sydney","Dublin","Tokyo"
];

const transport = [
"by bus","by train","by bike","by car","on foot"
];

const subjects = [
"English","history","math","science","music","art"
];

const hobbies = [
"plays football","reads books","goes swimming",
"watches movies","plays tennis","studies online",
"listens to music","goes jogging"
];

const foods = [
"coffee","tea","juice","milk","water"
];

let exerciseCount = 1;
let totalScore = 0;

/* ===========================
   HELPERS
=========================== */

function random(arr){
return arr[Math.floor(Math.random()*arr.length)];
}

function shuffle(array){

for(let i=array.length-1;i>0;i--){

let j=Math.floor(Math.random()*(i+1));

[array[i],array[j]]=[array[j],array[i]];

}

return array;

}

/* ===========================
   GENERATE EXERCISE
=========================== */

let correctAnswers = {};

function generateExercise(){

const person = random(names);
const city = random(cities);
const go = random(transport);
const subject1 = random(subjects);

let subject2 = random(subjects);

while(subject2 === subject1){
subject2 = random(subjects);
}

const hobby = random(hobbies);
const drink = random(foods);

const storyText = `
${person} is a student.<br><br>
${person} lives in ${city}.<br><br>
Every morning ${person} wakes up at 7 o'clock.<br><br>
${person} drinks ${drink} and eats breakfast.<br><br>
After breakfast, ${person} goes to school ${go}.<br><br>
${person} likes ${subject1} and ${subject2}.<br><br>
After school, ${person} ${hobby}.
`;

document.getElementById("story").innerHTML = storyText;

/* correct answers */

correctAnswers = {
q1: city,
q2: go,
q3: subject1 + " and " + subject2
};

/* fake answers */

let cityOptions = shuffle([
city,
random(cities),
random(cities)
]);

let transportOptions = shuffle([
go,
random(transport),
random(transport)
]);

let subjectOptions = shuffle([
subject1 + " and " + subject2,
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
${renderOptions("q2", transportOptions)}
</div>

<div class="question">
<p>3. What subjects does ${person} like?</p>
${renderOptions("q3", subjectOptions)}
</div>

`;

document.getElementById("result").innerHTML = "";

document.getElementById("counter").innerText =
"Exercise " + exerciseCount + " / Infinite";

}

/* ===========================
   RENDER OPTIONS
=========================== */

function renderOptions(name,options){

let html = "";

options.forEach(opt=>{

html += `
<label>
<input type="radio" name="${name}" value="${opt}">
 ${opt}
</label>
`;

});

return html;

}

/* ===========================
   CHECK
=========================== */

function checkAnswers(){

let score = 0;

for(let key in correctAnswers){

const selected =
document.querySelector(`input[name="${key}"]:checked`);

if(selected){

if(selected.value === correctAnswers[key]){
score++;
}

}

}

totalScore += score;

document.getElementById("result").innerHTML =
"🎯 Score: " + score + " / 3";

}

/* ===========================
   LISTEN STORY
=========================== */

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

/* ===========================
   RESET
=========================== */

function resetStats(){

exerciseCount = 1;
totalScore = 0;
generateExercise();

}

/* ===========================
   NEXT EXERCISE
=========================== */

document.querySelector(".next")?.addEventListener("click",()=>{

exerciseCount++;
generateExercise();

});

/* first load */

generateExercise();

</script>

</body>
</html>
