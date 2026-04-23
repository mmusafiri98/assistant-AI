<?php

/* ===========================
   CONFIG API
=========================== */

$apiKey = "Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ";
$apiUrl = "https://api.cohere.ai/v1/chat";

/* ===========================
   AJAX REQUEST
=========================== */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["topic"])) {

    header("Content-Type: application/json");

    $topic = trim($_POST["topic"]);

    $prompt = "
Create English grammar exercises for topic: $topic

Generate:
1. Short explanation in Italian
2. 5 multiple choice questions
3. 5 fill in the blank exercises
4. 5 sentence correction exercises
5. Solutions at the end

Write clearly for beginners and intermediate students.
";

    $data = [
        "model" => "command-a-vision-07-2025",
        "message" => $prompt,
        "temperature" => 0.7
    ];

    $ch = curl_init($apiUrl);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ]);

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode([
            "success" => false,
            "text" => "Errore CURL"
        ]);
        exit;
    }

    curl_close($ch);

    echo $response;
    exit;
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>English Grammar AI</title>

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
font-family:Arial,Helvetica,sans-serif;
}

body{
background:#f2f4f8;
padding:30px;
}

.container{
max-width:1100px;
margin:auto;
background:white;
padding:30px;
border-radius:14px;
box-shadow:0 10px 30px rgba(0,0,0,.08);
}

h1{
font-size:34px;
margin-bottom:10px;
}

p{
color:#555;
margin-bottom:25px;
}

.grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:15px;
margin-bottom:25px;
}

button.topic{
padding:15px;
border:none;
border-radius:10px;
cursor:pointer;
background:#0d6efd;
color:white;
font-weight:bold;
font-size:15px;
transition:.2s;
}

button.topic:hover{
background:#084fc7;
transform:translateY(-3px);
}

#loading{
display:none;
font-weight:bold;
margin-bottom:15px;
color:#0d6efd;
}

#result{
background:#f8fafc;
border:1px solid #ddd;
padding:20px;
border-radius:10px;
white-space:pre-wrap;
line-height:1.6;
min-height:250px;
}

.footer{
margin-top:20px;
font-size:14px;
color:#666;
}

</style>
</head>

<body>

<div class="container">

<h1>🇬🇧 English Grammar AI Exercises</h1>

<p>
Clicca una sezione grammaticale e l'AI creerà automaticamente nuovi esercizi.
</p>

<div class="grid">

<button class="topic" onclick="loadExercise('Present Simple')">Present Simple</button>

<button class="topic" onclick="loadExercise('Present Continuous')">Present Continuous</button>

<button class="topic" onclick="loadExercise('Past Simple')">Past Simple</button>

<button class="topic" onclick="loadExercise('Present Perfect')">Present Perfect</button>

<button class="topic" onclick="loadExercise('Future Will and Going To')">Future</button>

<button class="topic" onclick="loadExercise('Conditionals')">Conditionals</button>

<button class="topic" onclick="loadExercise('Modal Verbs')">Modal Verbs</button>

<button class="topic" onclick="loadExercise('Passive Voice')">Passive Voice</button>

<button class="topic" onclick="loadExercise('Reported Speech')">Reported Speech</button>

<button class="topic" onclick="loadExercise('Prepositions')">Prepositions</button>

<button class="topic" onclick="loadExercise('Articles')">Articles</button>

<button class="topic" onclick="loadExercise('Comparatives')">Comparatives</button>

</div>

<div id="loading">⏳ Generazione esercizio...</div>

<div id="result">
Seleziona un argomento grammaticale.
</div>

<div class="footer">
✔ Tutto in un file PHP  
✔ API nascosta nel backend  
✔ Esercizi AI infiniti
</div>

</div>

<script>

async function loadExercise(topic){

document.getElementById("loading").style.display = "block";
document.getElementById("result").innerHTML = "";

const formData = new FormData();
formData.append("topic", topic);

try{

const response = await fetch("",{
method:"POST",
body:formData
});

const data = await response.json();

document.getElementById("loading").style.display = "none";

if(data.text){
document.getElementById("result").innerText = data.text;
}
else if(data.response){
document.getElementById("result").innerText = data.response;
}
else{
document.getElementById("result").innerText = JSON.stringify(data,null,2);
}

}catch(error){

document.getElementById("loading").style.display = "none";
document.getElementById("result").innerText = "Errore caricamento.";

}

}

</script>

</body>
</html>
