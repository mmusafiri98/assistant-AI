<?php

session_start();

/* ===========================
CONFIG
=========================== */

$apiKey = "YOUR_COHERE_API_KEY";
$apiUrl = "https://api.cohere.ai/v1/chat";


/* ===========================
COHERE
=========================== */

function callAI($topic,$apiKey,$apiUrl){

$prompt = '

Create english grammar exercises.

Return ONLY JSON.

{
 "topic":"",
 "explanation":"",
 "multiple_choice":[
   {
     "question":"",
     "options":["","","",""],
     "answer":""
   }
 ],
 "fill_blank":[
   {
     "question":"",
     "answer":""
   }
 ],
 "correction":[
   {
     "wrong":"",
     "correct":""
   }
 ]
}

Rules:

- italian explanation
- 5 multiple choice
- 5 fill blank
- 5 correction
- topic: '.$topic.'

';


$data = [

"model"=>"command-r-plus",
"message"=>$prompt,
"temperature"=>0.7

];


$ch = curl_init($apiUrl);

curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POST,true);

curl_setopt($ch,CURLOPT_HTTPHEADER,[

"Content-Type: application/json",
"Authorization: Bearer ".$apiKey

]);

curl_setopt(
$ch,
CURLOPT_POSTFIELDS,
json_encode($data)
);


$response = curl_exec($ch);

curl_close($ch);


$json = json_decode($response,true);


if(isset($json["text"])){

return trim($json["text"]);

}


if(isset($json["message"]["content"][0]["text"])){

return trim(
$json["message"]["content"][0]["text"]
);

}


return null;

}



/* ===========================
GENERATE
=========================== */

if(
$_SERVER["REQUEST_METHOD"]==="POST"
&& isset($_POST["topic"])
){

header("Content-Type: application/json");


$topic =
trim(
$_POST["topic"]
);


$result =
callAI(
$topic,
$apiKey,
$apiUrl
);


if(empty($result)){

echo json_encode([
"success"=>false
]);

exit;

}


$data =
json_decode(
$result,
true
);


echo json_encode([
"success"=>true,
"exercise"=>$data
]);

exit;

}

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Learning Exercises AI</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial;
}

body{
background:#f1f5f9;
padding:30px;
}

.container{

max-width:1200px;
margin:auto;
background:white;
padding:30px;
border-radius:20px;

}

h1{

margin-bottom:20px;

}

.grid{

display:grid;
grid-template-columns:
repeat(auto-fit,minmax(200px,1fr));

gap:10px;

margin-bottom:25px;

}

button{

padding:14px;
border:none;
border-radius:10px;
cursor:pointer;
font-weight:bold;

}

.topic{

background:#4f46e5;
color:white;

}

.check{

background:#10b981;
color:white;

margin-top:25px;

}

.question{

background:#f8fafc;
padding:20px;
border-radius:12px;
margin-bottom:15px;

}

.correct{

background:#dcfce7;

}

.wrong{

background:#fee2e2;

}

input[type=text]{

width:100%;
padding:10px;
margin-top:10px;

}

#score{

margin-top:25px;
font-size:25px;
font-weight:bold;

}

</style>

</head>

<body>

<div class="container">

<h1>
English AI Exercises
</h1>


<div class="grid">

<button
class="topic"
onclick="loadExercise('Present Simple')">

Present Simple

</button>


<button
class="topic"
onclick="loadExercise('Past Simple')">

Past Simple

</button>


<button
class="topic"
onclick="loadExercise('Present Perfect')">

Present Perfect

</button>


<button
class="topic"
onclick="loadExercise('Conditionals')">

Conditionals

</button>

</div>


<div id="exercise">

Choose topic

</div>


<button
class="check"
onclick="checkAnswers()">

Check Answers

</button>


<div id="score"></div>


</div>


<script>

let exerciseData = null;



async function loadExercise(topic){

document
.getElementById("exercise")
.innerHTML="Loading...";


const fd = new FormData();

fd.append(
"topic",
topic
);


const res =
await fetch(
"",
{
method:"POST",
body:fd
}
);


const json =
await res.json();


exerciseData =
json.exercise;


renderExercise();

}



function renderExercise(){

let html = "";


html +=
`
<h2>
${exerciseData.topic}
</h2>

<p>
${exerciseData.explanation}
</p>
`;



exerciseData
.multiple_choice
.forEach((q,i)=>{

html +=
`
<div class="question">

<p>
${q.question}
</p>
`;


q.options
.forEach(opt=>{

html +=
`

<label>

<input
type="radio"
name="mc${i}"
value="${opt}">

${opt}

</label>

`;

});


html += "</div>";

});



exerciseData
.fill_blank
.forEach((q,i)=>{

html +=
`

<div class="question">

<p>
${q.question}
</p>

<input
type="text"
id="fill${i}">

</div>

`;

});



exerciseData
.correction
.forEach((q,i)=>{

html +=
`

<div class="question">

<p>

Correct:

${q.wrong}

</p>

<input
type="text"
id="corr${i}">

</div>

`;

});



document
.getElementById("exercise")
.innerHTML = html;

}




function checkAnswers(){

let total = 0;
let correct = 0;



exerciseData
.multiple_choice
.forEach((q,i)=>{

total++;


let selected =
document.querySelector(
`input[name="mc${i}"]:checked`
);


if(
selected &&
selected.value===q.answer
){

correct++;

}

});




exerciseData
.fill_blank
.forEach((q,i)=>{

total++;

let val =
document
.getElementById(
`fill${i}`
)
.value
.trim()
.toLowerCase();


if(
val===
q.answer
.toLowerCase()
){

correct++;

}

});




exerciseData
.correction
.forEach((q,i)=>{

total++;

let val =
document
.getElementById(
`corr${i}`
)
.value
.trim()
.toLowerCase();


if(
val===
q.correct
.toLowerCase()
){

correct++;

}

});



let failed =
total-correct;


let percent =
Math.round(
(correct/total)*100
);



document
.getElementById("score")
.innerHTML=

`
Success:
${correct}

<br>

Failed:
${failed}

<br>

Score:
${percent}%
`;

}

</script>

</body>
</html>
