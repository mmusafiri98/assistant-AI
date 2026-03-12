<?php
ob_start();
session_start([
'cookie_httponly'=>true,
'cookie_secure'=>isset($_SERVER['HTTPS']),
'cookie_samesite'=>'Strict',
]);

if($_SERVER['REQUEST_METHOD']==='POST'){

$how_found=htmlspecialchars(trim($_POST['how_found']??''));
$level=htmlspecialchars(trim($_POST['level']??''));
$goal=htmlspecialchars(trim($_POST['goal']??''));
$duration=htmlspecialchars(trim($_POST['duration']??''));
$motivation=htmlspecialchars(trim($_POST['motivation']??''));
$skills=isset($_POST['skills']) && is_array($_POST['skills']) ? implode(", ",array_map('trim',$_POST['skills'])):'';
$accent=htmlspecialchars(trim($_POST['accent']??''));
$days=intval($_POST['days']??0);
$minutes=intval($_POST['minutes']??0);

if($how_found==''||$level==''||$goal==''||$duration==''||$motivation==''||$accent==''){
$_SESSION['quiz_error']="Please complete all required fields.";
}else{

try{

$dsn="pgsql:host=ep-autumn-salad-adwou7x2-pooler.c-2.us-east-1.aws.neon.tech;port=5432;dbname=veronica_db_login;sslmode=require";
$username_db="neondb_owner";
$password_db="npg_QolPDv5L9gVj";

$conn=new PDO($dsn,$username_db,$password_db,[
PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
PDO::ATTR_EMULATE_PREPARES=>false,
]);

$sql="INSERT INTO user_quiz
(username,how_found,level,goal,duration,motivation,skills,accent,days,minutes)
VALUES
(:username,:how_found,:level,:goal,:duration,:motivation,:skills,:accent,:days,:minutes)";

$stmt=$conn->prepare($sql);

$username=$_SESSION['username']??'guest';

$stmt->execute([
':username'=>$username,
':how_found'=>$how_found,
':level'=>$level,
':goal'=>$goal,
':duration'=>$duration,
':motivation'=>$motivation,
':skills'=>$skills,
':accent'=>$accent,
':days'=>$days,
':minutes'=>$minutes
]);

$conn=null;

header("Location: english_thankyou.php");
exit;

}catch(PDOException $e){

error_log("DB Error: ".$e->getMessage());
$_SESSION['quiz_error']="Database error while saving your answers.";

}

}

}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<title>English Learning Quiz – Veronica AI</title>

<meta name="viewport" content="width=device-width,initial-scale=1.0">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

body{
margin:0;
font-family:Poppins,sans-serif;
background:linear-gradient(135deg,#818cf8,#38bdf8);
min-height:100vh;
display:flex;
align-items:center;
justify-content:center;
}

.quiz-container{
background:rgba(255,255,255,0.25);
backdrop-filter:blur(20px);
border-radius:20px;
padding:40px;
width:90%;
max-width:700px;
text-align:center;
box-shadow:0 8px 32px rgba(0,0,0,0.2);
}

h2{
margin-bottom:20px;
}

label{
display:block;
text-align:left;
margin-top:12px;
font-weight:600;
}

select,textarea,input[type=number]{
width:100%;
padding:10px;
border-radius:10px;
border:1px solid #cbd5f5;
margin-top:6px;
}

textarea{
height:80px;
resize:none;
}

.skills-group{
display:flex;
flex-direction:column;
text-align:left;
gap:8px;
}

button{
margin-top:20px;
background:#4f46e5;
color:white;
border:none;
padding:12px;
border-radius:10px;
font-size:16px;
cursor:pointer;
width:100%;
}

button:hover{
background:#4338ca;
}

.alert-error{
background:#fee2e2;
color:#b91c1c;
padding:10px;
border-radius:8px;
margin-bottom:15px;
}

.veronica-message{
background:rgba(255,255,255,0.25);
padding:12px;
border-radius:10px;
margin-bottom:20px;
font-style:italic;
}

</style>

</head>

<body>

<div class="quiz-container">

<h2>🎓 Your English Learning Profile</h2>

<div class="veronica-message" id="veronicaMsg">
Hello 👋 I'm Veronica AI. Answer a few questions so I can personalize your English learning journey.
</div>

<?php if(!empty($_SESSION['quiz_error'])): ?>

<div class="alert-error"><?=htmlspecialchars($_SESSION['quiz_error'])?></div>

<?php unset($_SESSION['quiz_error']); endif; ?>

<form method="post">

<label>How did you discover this app?</label>

<select name="how_found" required>

<option value="">Select</option>
<option>Friend recommendation</option>
<option>Social media</option>
<option>Internet search</option>
<option>Advertisement</option>
<option>Other</option>

</select>

<label>What is your current English level?</label>

<select name="level" required>

<option value="">Choose level</option>
<option>Beginner (A1-A2)</option>
<option>Intermediate (B1-B2)</option>
<option>Advanced (C1-C2)</option>
<option>Not sure</option>

</select>

<label>Why do you want to learn English?</label>

<select name="goal" required>

<option value="">Select goal</option>
<option>Travel</option>
<option>Work</option>
<option>Study abroad</option>
<option>Personal interest</option>
<option>Other</option>

</select>

<label>Which English accent would you like to learn?</label>

<select name="accent" required>

<option value="">Choose accent</option>
<option>American English 🇺🇸</option>
<option>British English 🇬🇧</option>
<option>Australian English 🇦🇺</option>

</select>

<label>How long do you want to reach your goal?</label>

<select name="duration" required>

<option value="">Select duration</option>
<option>Less than 3 months</option>
<option>3–6 months</option>
<option>6–12 months</option>
<option>More than a year</option>

</select>

<label>How much do you want to practice?</label>

<div style="display:flex;gap:10px">

<input type="number" name="days" min="1" max="7" placeholder="Days/week">

<input type="number" name="minutes" min="5" max="300" placeholder="Minutes/day">

</div>

<div style="margin-top:20px">

<canvas id="practiceChart"></canvas>

</div>

<label style="margin-top:15px">Which skills do you want to improve?</label>

<div class="skills-group">

<label><input type="checkbox" name="skills[]" value="Speaking"> Speaking</label>

<label><input type="checkbox" name="skills[]" value="Listening"> Listening</label>

<label><input type="checkbox" name="skills[]" value="Reading"> Reading</label>

<label><input type="checkbox" name="skills[]" value="Writing"> Writing</label>

<label><input type="checkbox" name="skills[]" value="Pronunciation"> Pronunciation</label>

</div>

<label>Your motivation to learn English</label>

<textarea name="motivation" required placeholder="Example: I want to speak English fluently for travel and work."></textarea>

<button type="submit">Submit my English profile</button>

</form>

</div>

<script>

const ctx=document.getElementById("practiceChart");

let chart;

function updateChart(){

const days=parseInt(document.querySelector('[name="days"]').value)||0;

const minutes=parseInt(document.querySelector('[name="minutes"]').value)||0;

const week=days*minutes;

const month=week*4;

const data={
labels:["Minutes / week","Minutes / month"],
datasets:[{
data:[week,month],
backgroundColor:["#4f46e5","#38bdf8"]
}]
};

if(chart) chart.destroy();

chart=new Chart(ctx,{
type:"bar",
data:data,
options:{
plugins:{legend:{display:false}},
scales:{y:{beginAtZero:true}}
}
});

}

document.querySelector('[name="days"]').addEventListener("input",updateChart);
document.querySelector('[name="minutes"]').addEventListener("input",updateChart);

window.onload=()=>{

const msg="Hello! I'm Veronica AI. Answer these questions so I can create your personalized English learning plan.";

if('speechSynthesis' in window){

const speech=new SpeechSynthesisUtterance(msg);
speech.lang="en-US";

speechSynthesis.speak(speech);

}

};

</script>

</body>
</html>

<?php ob_end_flush(); ?>
