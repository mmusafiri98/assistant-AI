<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<title>English Reading Practice</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>

body{
margin:0;
font-family:Arial, sans-serif;
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
box-shadow:0 10px 30px rgba(0,0,0,0.2);

}

h1{
text-align:center;
color:#4f46e5;
}

.story{

background:#f1f5f9;
padding:20px;
border-radius:10px;
line-height:1.6;
margin-top:20px;

}

.questions{

margin-top:25px;

}

.question{

margin-bottom:15px;

}

button{

margin-top:20px;
padding:12px 20px;
border:none;
border-radius:10px;
cursor:pointer;
font-size:16px;

}

.check{

background:#4f46e5;
color:white;

}

.back{

background:#ef4444;
color:white;
margin-left:10px;

}

.result{

margin-top:20px;
font-weight:bold;
font-size:18px;

}

.listen{

background:#10b981;
color:white;
margin-top:15px;

}

</style>

</head>

<body>

<div class="container">

<h1>📖 Reading Practice</h1>

<p>Read the story below and answer the questions.</p>

<button class="listen" onclick="readText()">🔊 Listen to the story</button>

<div class="story" id="story">

John is a student.  
He lives in London.  
Every morning he wakes up at 7 o'clock.  

He eats breakfast and drinks coffee.  
After breakfast, he goes to school by bus.  

John likes English and history.  
After school he studies and sometimes plays football with his friends.

</div>

<div class="questions">

<div class="question">

<p><b>1. Where does John live?</b></p>

<label><input type="radio" name="q1" value="0"> Paris</label><br>
<label><input type="radio" name="q1" value="1"> London</label><br>
<label><input type="radio" name="q1" value="0"> Rome</label>

</div>

<div class="question">

<p><b>2. How does John go to school?</b></p>

<label><input type="radio" name="q2" value="1"> By bus</label><br>
<label><input type="radio" name="q2" value="0"> By train</label><br>
<label><input type="radio" name="q2" value="0"> By bike</label>

</div>

<div class="question">

<p><b>3. What does John like?</b></p>

<label><input type="radio" name="q3" value="1"> English and history</label><br>
<label><input type="radio" name="q3" value="0"> Math and science</label><br>
<label><input type="radio" name="q3" value="0"> Art and music</label>

</div>

</div>

<button class="check" onclick="checkAnswers()">Check Answers</button>

<button class="back" onclick="window.location.href='english_dashboard.php'">
Back to Dashboard
</button>

<div class="result" id="result"></div>

</div>

<script>

function readText(){

const text = document.getElementById("story").innerText;

const speech = new SpeechSynthesisUtterance(text);
speech.lang = "en-US";

speechSynthesis.speak(speech);

}

function checkAnswers(){

let score = 0;

const answers = document.querySelectorAll("input[type=radio]:checked");

answers.forEach(a=>{
score += parseInt(a.value);
});

document.getElementById("result").innerHTML =
"Your score: "+score+" / 3";

}

</script>

</body>
</html>
