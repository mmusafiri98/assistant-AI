<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<title>English Vocabulary</title>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>

body{
margin:0;
font-family:Arial;
background:linear-gradient(135deg,#6366f1,#38bdf8);
padding:30px;
}

.container{
background:white;
padding:30px;
border-radius:20px;
max-width:900px;
margin:auto;
box-shadow:0 10px 30px rgba(0,0,0,0.2);
}

h1{
text-align:center;
color:#4f46e5;
}

.search{
width:100%;
padding:10px;
margin:15px 0;
border-radius:10px;
border:1px solid #ccc;
}

.category{
margin-top:20px;
font-size:20px;
font-weight:bold;
color:#1e293b;
}

table{
width:100%;
border-collapse:collapse;
margin-top:10px;
}

th,td{
border:1px solid #ddd;
padding:10px;
text-align:left;
}

th{
background:#6366f1;
color:white;
}

tr:hover{
background:#f1f5f9;
}

.listen-btn{
background:#10b981;
color:white;
border:none;
padding:5px 10px;
border-radius:6px;
cursor:pointer;
}

.back{
margin-top:20px;
background:#ef4444;
color:white;
padding:10px 20px;
border:none;
border-radius:10px;
cursor:pointer;
}

</style>

</head>

<body>

<div class="container">

<h1>📚 English Vocabulary</h1>

<input type="text" id="search" class="search" placeholder="Search a word..." onkeyup="searchWord()">

<!-- CATEGORY 1 -->
<div class="category">Daily Life</div>

<table id="vocabTable">

<tr>
<th>English</th>
<th>Meaning</th>
<th>🔊</th>
</tr>

<tr><td>House</td><td>Maison</td><td><button class="listen-btn" onclick="speak('House')">🔊</button></td></tr>
<tr><td>Food</td><td>Nourriture</td><td><button onclick="speak('Food')">🔊</button></td></tr>
<tr><td>Water</td><td>Eau</td><td><button onclick="speak('Water')">🔊</button></td></tr>
<tr><td>Family</td><td>Famille</td><td><button onclick="speak('Family')">🔊</button></td></tr>
<tr><td>Friend</td><td>Ami</td><td><button onclick="speak('Friend')">🔊</button></td></tr>

<!-- CATEGORY 2 -->
<tr><td colspan="3"><b>Travel</b></td></tr>

<tr><td>Airport</td><td>Aéroport</td><td><button onclick="speak('Airport')">🔊</button></td></tr>
<tr><td>Ticket</td><td>Billet</td><td><button onclick="speak('Ticket')">🔊</button></td></tr>
<tr><td>Hotel</td><td>Hôtel</td><td><button onclick="speak('Hotel')">🔊</button></td></tr>
<tr><td>Passport</td><td>Passeport</td><td><button onclick="speak('Passport')">🔊</button></td></tr>
<tr><td>Travel</td><td>Voyager</td><td><button onclick="speak('Travel')">🔊</button></td></tr>

<!-- CATEGORY 3 -->
<tr><td colspan="3"><b>Work</b></td></tr>

<tr><td>Work</td><td>Travail</td><td><button onclick="speak('Work')">🔊</button></td></tr>
<tr><td>Office</td><td>Bureau</td><td><button onclick="speak('Office')">🔊</button></td></tr>
<tr><td>Meeting</td><td>Réunion</td><td><button onclick="speak('Meeting')">🔊</button></td></tr>
<tr><td>Job</td><td>Emploi</td><td><button onclick="speak('Job')">🔊</button></td></tr>

<!-- CATEGORY 4 -->
<tr><td colspan="3"><b>Emotions</b></td></tr>

<tr><td>Happy</td><td>Heureux</td><td><button onclick="speak('Happy')">🔊</button></td></tr>
<tr><td>Sad</td><td>Triste</td><td><button onclick="speak('Sad')">🔊</button></td></tr>
<tr><td>Angry</td><td>En colère</td><td><button onclick="speak('Angry')">🔊</button></td></tr>
<tr><td>Love</td><td>Amour</td><td><button onclick="speak('Love')">🔊</button></td></tr>

</table>

<button class="back" onclick="window.location.href='english_dashboard.php'">
⬅ Back to Dashboard
</button>

</div>

<script>

function speak(word){

const speech = new SpeechSynthesisUtterance(word);
speech.lang = "en-US";

speechSynthesis.speak(speech);

}

function searchWord(){

const input = document.getElementById("search").value.toLowerCase();
const rows = document.querySelectorAll("#vocabTable tr");

rows.forEach(row=>{
const text = row.innerText.toLowerCase();

if(text.includes(input)){
row.style.display="";
}else{
row.style.display="none";
}
});

}

</script>

</body>
</html>
