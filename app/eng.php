<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // English quiz introduction message
    $presentation = <<<EOT
Hello! I'm Veronica AI, your English learning assistant. 🌸

Welcome to this English quiz designed for people who want to learn and improve their English.

In a moment, you will start a short quiz that will help me understand your current level of English.

The quiz is simple and friendly. It will ask you a few questions about vocabulary, grammar and understanding.

Don't worry — it is not an exam. It is only to help personalize your learning experience.

When you are ready, click the button below to start the quiz. Good luck! 😊
EOT;

    echo json_encode(["response" => $presentation]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<title>English Learning Quiz - Veronica AI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<style>

html,body{
margin:0;
padding:0;
height:100%;
overflow:hidden;
font-family:Arial, Helvetica, sans-serif;
}

#full-video{
position:fixed;
top:0;
left:0;
width:100vw;
height:100vh;
object-fit:cover;
z-index:-1;
}

.chat-container{
position:absolute;
bottom:40px;
left:50%;
transform:translateX(-50%);
width:90%;
max-width:700px;
text-align:center;
color:white;
}

#messages{
background:rgba(0,0,0,0.4);
border-radius:15px;
padding:20px;
font-size:20px;
max-height:200px;
overflow-y:auto;
margin-bottom:20px;
}

#start-btn{
background:#3182ce;
color:white;
padding:14px 30px;
font-size:18px;
border:none;
border-radius:12px;
cursor:pointer;
}

#start-btn:hover{
background:#2b6cb0;
}

</style>
</head>

<body>

<video id="full-video" loop muted autoplay playsinline>
<source src="jennifer.mp4" type="video/mp4">
<source src="jennifer.webm" type="video/webm">
</video>

<div class="chat-container">

<div id="messages"></div>

<button id="start-btn">Start English Quiz ➜</button>

</div>

<script>

const messagesDiv=document.getElementById("messages");
const startBtn=document.getElementById("start-btn");
const video=document.getElementById("full-video");

let presentationText="";
let loopActive=true;

function addMessage(text){

messagesDiv.textContent="";
const msg=document.createElement("div");
msg.textContent="🤖 "+text;

messagesDiv.appendChild(msg);

}

function speakText(text){

if(!window.speechSynthesis){
alert("Speech synthesis not supported in this browser.");
return;
}

window.speechSynthesis.cancel();

const utterance=new SpeechSynthesisUtterance(text);
utterance.lang="en-US";
utterance.rate=1;
utterance.pitch=1;

const speakNow=()=>{

const voices=speechSynthesis.getVoices();

const voice=voices.find(v=>v.lang.startsWith("en"));

if(voice) utterance.voice=voice;

utterance.onend=()=>{
if(loopActive){
setTimeout(()=>speakText(text),3000);
}
};

speechSynthesis.speak(utterance);

};

if(speechSynthesis.getVoices().length===0){
speechSynthesis.onvoiceschanged=speakNow;
}else{
speakNow();
}

}

async function loadPresentation(){

try{

const res=await fetch(window.location.href,{method:"POST"});
const data=await res.json();

presentationText=data.response;

addMessage(presentationText);

speakText(presentationText);

}catch(e){

console.error(e);

}

}

startBtn.addEventListener("click",()=>{

loopActive=false;

speechSynthesis.cancel();

window.location.href="quiz_eng.php";

});

window.onload=()=>{

loadPresentation();

setTimeout(()=>{

video.play().catch(err=>console.warn(err));

},500);

};

</script>

</body>
</html>
