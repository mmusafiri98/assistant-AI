<?php

session_start();

/* ===========================
CONFIG
=========================== */

$COHERE_API_KEY = "YOUR_COHERE_API_KEY";


/* ===========================
CALL COHERE
=========================== */

function callCohere($prompt,$apiKey){

$url="https://api.cohere.ai/v1/chat";

$data=[
"model"=>"command-r-plus",
"temperature"=>0.8,
"max_tokens"=>1200,
"message"=>$prompt
];

$ch=curl_init($url);

curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POST,true);

curl_setopt($ch,CURLOPT_HTTPHEADER,[
"Content-Type: application/json",
"Authorization: Bearer ".$apiKey
]);

curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));

$response=curl_exec($ch);

curl_close($ch);

$json=json_decode($response,true);

if(isset($json["text"])){
return $json["text"];
}

if(isset($json["message"]["content"][0]["text"])){
return $json["message"]["content"][0]["text"];
}

return null;

}


/* ===========================
GENERATE EXERCISE
=========================== */

if(isset($_POST["generate"])){

$prompt="
Create an english reading exercise.

Return ONLY valid JSON.

Format:

{
\"story\":\"...\",
\"questions\":[
{
\"question\":\"...\",
\"options\":[\"...\",\"...\",\"...\"],
\"answer\":\"...\"
}
]
}

Rules:

- story between 120 and 180 words
- 3 multiple choice questions
- level A2/B1
- educational
";

$result=callCohere($prompt,$COHERE_API_KEY);

$_SESSION["exercise"]=json_decode($result,true);

header("Location: lesson_english.php");
exit;

}


/* ===========================
RESET
=========================== */

if(isset($_POST["reset"])){

unset($_SESSION["exercise"]);

header("Location: lesson_english.php");
exit;

}

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>English AI Lesson</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
}

body{

font-family:Arial;
background:linear-gradient(135deg,#4f46e5,#06b6d4);

padding:30px;

}

.container{

max-width:1000px;

margin:auto;

background:white;

border-radius:20px;

padding:30px;

box-shadow:0 15px 40px rgba(0,0,0,.2);

}

h1{

text-align:center;

color:#4338ca;

margin-bottom:20px;

}

.story{

background:#f8fafc;

padding:25px;

border-radius:15px;

line-height:1.8;

font-size:18px;

margin-bottom:25px;

}

.question{

background:#f1f5f9;

padding:20px;

border-radius:12px;

margin-bottom:15px;

}

.question p{

font-weight:bold;

margin-bottom:10px;

}

label{

display:block;

padding:8px;

cursor:pointer;

border-radius:8px;

margin-bottom:5px;

}

.correct{

background:#dcfce7;

}

.wrong{

background:#fee2e2;

}

.controls{

display:flex;

gap:10px;

justify-content:center;

margin-bottom:20px;

flex-wrap:wrap;

}

button{

padding:12px 18px;

border:none;

border-radius:10px;

color:white;

font-weight:bold;

cursor:pointer;

}

.generate{

background:#4f46e5;

}

.listen{

background:#10b981;

}

.check{

background:#f59e0b;

}

.reset{

background:#ef4444;

}

.result{

text-align:center;

font-size:22px;

margin-top:20px;

font-weight:bold;

}

</style>

</head>
<body>

<div class="container">

<h1>AI English Reading Lesson</h1>


<div class="controls">

<form method="POST">

<button class="generate" name="generate">
Generate Exercise
</button>

</form>


<?php if(isset($_SESSION["exercise"])): ?>

<button
type="button"
class="listen"
onclick="readStory()">

Listen

</button>

<button
type="button"
class="check"
onclick="checkAnswers()">

Check

</button>

<form method="POST">

<button class="reset" name="reset">
Reset
</button>

</form>

<?php endif; ?>

</div>



<?php if(isset($_SESSION["exercise"])):

$exercise=$_SESSION["exercise"];

?>

<div
class="story"
id="story">

<?= nl2br(htmlspecialchars($exercise["story"])) ?>

</div>


<div id="quiz">

<?php

foreach($exercise["questions"] as $index=>$q):

?>

<div class="question">

<p>

<?= ($index+1).". ".htmlspecialchars($q["question"]) ?>

</p>

<?php

foreach($q["options"] as $option):

?>

<label>

<input
type="radio"
name="q<?= $index ?>"
value="<?= htmlspecialchars($option) ?>">

<?= htmlspecialchars($option) ?>

</label>

<?php endforeach; ?>

</div>

<?php endforeach; ?>

</div>


<div
class="result"
id="result">

</div>


<script>

const answers = <?= json_encode(
array_column(
$exercise["questions"],
"answer"
)
) ?>;



function readStory(){

const text =
document
.getElementById("story")
.innerText;


let speech =
new SpeechSynthesisUtterance(text);


speech.lang="en-US";

speech.rate=.95;


speechSynthesis.cancel();

speechSynthesis.speak(speech);

}



function checkAnswers(){

let score=0;


document
.querySelectorAll("label")
.forEach(el=>{

el.classList.remove(
"correct",
"wrong"
);

});


answers.forEach((correct,index)=>{

let radios=
document.querySelectorAll(
`input[name="q${index}"]`
);


radios.forEach(radio=>{

let label=
radio.parentElement;


if(
radio.value===correct
){

label.classList.add(
"correct"
);

}


if(
radio.checked &&
radio.value!==correct
){

label.classList.add(
"wrong"
);

}

});


let selected=
document.querySelector(
`input[name="q${index}"]:checked`
);


if(
selected &&
selected.value===correct
){

score++;

}

});


document
.getElementById("result")
.innerHTML=

"Score: "+
score+
" / "+
answers.length;

}

</script>


<?php else: ?>

<div class="story">

Click "Generate Exercise"
to create your first AI lesson.

</div>

<?php endif; ?>

</div>

</body>
</html>
