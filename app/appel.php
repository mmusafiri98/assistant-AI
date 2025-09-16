<?php
// appel.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents("php://input"), true);
    $input = $data['input'] ?? '';
    $chatHistory = $data['chat_history'] ?? [];

    $api_url = "https://api.cohere.ai/v1/chat";
    $api_key = "VOTRE_CLE_API_COHERE"; // <-- Remplacez par votre clé Cohere

    $system_prompt = "Tu es Veronica AI, professeure de langue patiente et experte. Message utilisateur: '{$input}'";

    $payload = json_encode([
        "model" => "command-a-vision-07-2025",
        "temperature" => 0.7,
        "max_tokens" => 300,
        "message" => $system_prompt,
        "chat_history" => $chatHistory
    ]);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $api_key"
    ]);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo json_encode(["response" => "Erreur de connexion à Cohere : " . curl_error($ch)]);
        exit;
    }
    curl_close($ch);

    $decoded = json_decode($result, true);
    $responseText = $decoded['generations'][0]['text'] ?? "Aucune réponse de l'IA.";

    echo json_encode(["response" => $responseText]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appel avec Veronica AI</title>
<style>
body { margin:0; font-family:sans-serif; background:#222; color:white; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh; }
video#assistant-video { position:fixed; top:0; left:0; width:100%; height:100%; object-fit:cover; z-index:-1; }
.chat-container { position:relative; width:90%; max-width:500px; background:rgba(0,0,0,0.6); border-radius:1rem; padding:1rem; display:flex; flex-direction:column; }
#chat-history { height:300px; overflow-y:auto; margin-bottom:1rem; display:flex; flex-direction:column; gap:0.5rem; }
.chat-message { padding:0.5rem 1rem; border-radius:1rem; max-width:80%; }
.user-message { background:#4299e1; align-self:flex-end; }
.ai-message { background:#e2e8f0; color:#222; align-self:flex-start; }
.input-area { display:flex; gap:0.5rem; }
#user-input { flex-grow:1; padding:0.5rem 1rem; border-radius:0.5rem; border:none; outline:none; }
#send-btn,#voice-btn { padding:0.5rem 1rem; border:none; border-radius:0.5rem; cursor:pointer; }
#voice-btn.recording { background:#e53e3e; animation:pulse 1s infinite; }
@keyframes pulse {0%{transform:scale(1);}50%{transform:scale(1.05);}100%{transform:scale(1);}}
</style>
</head>
<body>

<video id="assistant-video" loop muted autoplay playsinline>
    <source src="jennifer.mp4" type="video/mp4">
    <source src="jennifer.webm" type="video/webm">
</video>

<div class="chat-container">
    <div id="chat-history"></div>
    <div class="input-area">
        <input type="text" id="user-input" placeholder="Écrivez ou parlez...">
        <button id="voice-btn">🎤</button>
        <button id="send-btn">Envoyer</button>
    </div>
</div>

<script>
const chatHistoryDiv = document.getElementById('chat-history');
const userInput = document.getElementById('user-input');
const sendBtn = document.getElementById('send-btn');
const voiceBtn = document.getElementById('voice-btn');

let chatHistory = [];
let recognition = null;
let isListening = false;

// Ajouter un message au chat
function addMessage(text, sender) {
    const div = document.createElement('div');
    div.classList.add('chat-message', sender==='user'?'user-message':'ai-message');
    div.textContent = text;
    chatHistoryDiv.appendChild(div);
    chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
}

// Synthèse vocale
function speakText(text) {
    if (!window.speechSynthesis) return;
    const utterance = new SpeechSynthesisUtterance(text);
    const voices = speechSynthesis.getVoices();
    const frVoice = voices.find(v => v.lang.startsWith('fr')) || voices[0];
    utterance.voice = frVoice;
    utterance.lang = 'fr-FR';
    speechSynthesis.speak(utterance);
}

// Envoyer au serveur
async function sendToAI(inputText) {
    addMessage(inputText,'user');
    chatHistory.push({role:'USER', message:inputText});
    try {
        const res = await fetch('appel.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({input:inputText, chat_history:chatHistory})
        });
        const data = await res.json();
        const response = data.response || "Erreur IA.";
        chatHistory.push({role:'CHATBOT', message:response});
        addMessage(response,'ai');
        speakText(response);
    } catch(e) {
        console.error(e);
        speakText("Une erreur est survenue.");
    }
}

// Reconnaissance vocale
function initSpeechRecognition() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) return alert("Reconnaissance vocale non supportée.");
    recognition = new SpeechRecognition();
    recognition.lang = 'fr-FR';
    recognition.continuous = false;
    recognition.interimResults = false;

    recognition.onstart = () => { isListening = true; voiceBtn.classList.add('recording'); }
    recognition.onresult = e => { sendToAI(e.results[0][0].transcript); }
    recognition.onerror = e => { console.error(e.error); }
    recognition.onend = () => { isListening = false; voiceBtn.classList.remove('recording'); }
}

// Événements
sendBtn.addEventListener('click',()=>{ 
    const msg = userInput.value.trim();
    if(msg){ userInput.value=''; sendToAI(msg); }
});
userInput.addEventListener('keypress',e=>{ if(e.key==='Enter') sendBtn.click(); });

voiceBtn.addEventListener('click',()=>{
    if(!recognition) return;
    if(isListening) recognition.stop();
    else navigator.mediaDevices.getUserMedia({audio:true})
        .then(stream=>{stream.getTracks().forEach(t=>t.stop()); recognition.start();})
        .catch(()=>alert("Micro inaccessible."));
});

window.onload = () => {
    initSpeechRecognition();
    const welcome = "Bonjour ! Je suis Veronica AI. Parlez ou écrivez pour commencer.";
    addMessage(welcome,'ai');
    speakText(welcome);
};
</script>

</body>
</html>




