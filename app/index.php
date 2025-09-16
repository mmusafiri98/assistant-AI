<?php
// PHP Backend Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestData = json_decode(file_get_contents('php://input'), true);
    $input = $requestData['input'] ?? '';
    $model = $requestData['model'] ?? 'cohere';
    $chatHistory = $requestData['chat_history'] ?? [];

    // Détection de langue simple
    function langDetect($text)
    {
        $languages = [
            'fr' => ['bonjour','je','le','la','les','est','un','une','corrige'],
            'en' => ['hello','the','is','and','you','a','an','correct'],
            'es' => ['hola','el','la','y','que','un','una','corrige'],
            'de' => ['hallo','der','die','und','sie','ein','eine','korrigieren'],
            'it' => ['ciao','il','la','e','che','un','una','correggi'],
            'pt' => ['olá','o','a','e','que','um','uma','corrigir'],
            'nl' => ['hallo','de','het','en','dat','een','corrigeer'],
            'ru' => ['привет','и','в','не','на'],
            'zh' => ['你好','是','的','我','在'],
            'ja' => ['こんにちは','の','に','を','です'],
            'ar' => ['مرحبا','و','في','من','على']
        ];

        $lowerText = strtolower($text);
        foreach ($languages as $lang => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($lowerText, $keyword) !== false) return $lang;
            }
        }
        return 'en';
    }

    $detectedLanguage = langDetect($input);

    // Gestion heure/date
    if (stripos($input,'time')!==false || stripos($input,'heure')!==false) {
        date_default_timezone_set('Europe/Brussels');
        echo json_encode(['response'=>"L'heure actuelle est : ".date('H:i:s')]);
        exit;
    }
    if (stripos($input,'date')!==false || stripos($input,"aujourd'hui")!==false) {
        date_default_timezone_set('Europe/Brussels');
        echo json_encode(['response'=>"La date d'aujourd'hui est : ".date("d/m/Y")]);
        exit;
    }

    // Cohere API pour chat
    if ($model === 'cohere') {
        $api_url = "https://api.cohere.ai/v1/chat";
        $data = [
            "model" => "command-a-vision-07-2025", // MODIFICATION : modèle valide
            "temperature" => 0.7,
            "max_tokens" => 300,
            "chat_history" => $chatHistory,
            "message" => "Tu es Veronica AI, professeure de langue experte et patiente. Analyse et corrige la phrase suivante : '" . $input . "'. Explique les corrections clairement et propose des alternatives naturelles."
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer xTASVp3SAphp5gSwvtPVtsQbXrRtYaFT7Pb2o8gB' // REMPLACEZ par votre clé API Cohere
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($api_response === false) {
            echo json_encode(['response'=>"Erreur technique Cohere : ".$error]);
            exit;
        }

        $response_json = json_decode($api_response,true);
        $generated_text = $response_json['text'] ?? ($response_json['generations'][0]['text'] ?? 'Erreur réponse IA.');
        echo json_encode(['response'=>$generated_text]);
        exit;
    }

    echo json_encode(['response'=>'Modèle non supporté.']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Veronica AI</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
<style>
/* Styles similaires à votre code original */
body { font-family: 'Inter', sans-serif; background-color: violet; display:flex; justify-content:center; align-items:center; min-height:100vh; margin:0; }
.chat-container { border-radius:1.5rem; width:100%; max-width:500px; display:flex; flex-direction:column; padding:1.5rem; box-sizing:border-box; }
.video-container { margin-bottom:1.5rem; text-align:center; position:relative; width:100%; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:1.5rem; background-color:#000; }
#assistant-video { position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover; border-radius:1.5rem; box-shadow:0 5px 15px rgba(0,0,0,0.2); display:block; }
/* Chat History */
#chat-history { background-color:#e2e8f0; border-radius:1rem; padding:1rem; flex-grow:1; overflow-y:auto; height:350px; display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1rem; }
.chat-message { max-width:85%; padding:0.75rem 1rem; border-radius:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.1); word-wrap:break-word; }
.user-message { background-color:#2563eb; color:white; align-self:flex-end; margin-left:auto; }
.ai-message { background-color:white; color:#2d3748; align-self:flex-start; margin-right:auto; }
.typing-indicator { align-self:flex-start; background-color:#cbd5e0; color:#4a5568; padding:0.75rem 1rem; border-radius:1.25rem; font-style:italic; }
/* Input & Buttons */
.input-area { display:flex; gap:0.5rem; align-items:center; }
#user-input { flex-grow:1; padding:0.75rem 1rem; font-size:1rem; border:1px solid #cbd5e0; border-radius:0.75rem; outline:none; box-shadow:inset 0 1px 2px rgba(0,0,0,0.05);}
#send-btn, #voice-btn { color:white; padding:0.75rem 1.25rem; border:none; border-radius:0.75rem; cursor:pointer; transition:all 0.2s ease-in-out; font-weight:600; display:flex; align-items:center; justify-content:center; min-width:50px;}
#send-btn { background-color:#4299e1; } #send-btn:hover { background-color:#3182ce; } #send-btn:disabled { background-color:#a0aec0; cursor:not-allowed;}
#voice-btn { background-color:#48bb78; border-radius:50%; width:50px; height:50px; padding:0;}
#voice-btn:hover { background-color:#38a169; }
#voice-btn.recording { background-color:#e53e3e; animation:pulse 1s infinite; } #voice-btn.recording:hover { background-color:#c53030; }
@keyframes pulse { 0%{transform:scale(1);}50%{transform:scale(1.05);}100%{transform:scale(1);} }
.voice-status { text-align:center; margin-bottom:0.5rem; font-size:0.875rem; color:#4a5568; min-height:1.25rem;}
.voice-status.listening { color:#e53e3e; font-weight:600; }
#clear-history-btn { background-color:#e2e8f0; color:#4a5568; border:1px solid #cbd5e0; padding:0.5rem 1rem; border-radius:0.5rem; cursor:pointer; font-size:0.875rem; margin-top:1rem; align-self:center;}
#clear-history-btn:hover { background-color:#cbd5e0; }
</style>
</head>
<body>
<div class="chat-container">
    <h1>Veronica AI</h1>
    <div class="video-container">
        <video id="assistant-video" loop muted playsinline autoplay>
            <source src="jennifer.mp4" type="video/mp4" />
            <source src="jennifer.webm" type="video/webm" />
            Votre navigateur ne supporte pas la lecture vidéo.
        </video>
    </div>
    <div class="mt-4 text-center">
        <a href="appel.php" class="inline-flex items-center bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-full shadow-md transition duration-300">
            📞 Appeler Veronica AI
        </a>
    </div>

    <div id="voice-status" class="voice-status"></div>
    <div id="chat-history"></div>
    <div class="input-area">
        <input type="text" id="user-input" placeholder="Écrivez votre message ou utilisez le micro..." />
        <button id="voice-btn" title="Cliquez pour parler">🎤</button>
        <button id="send-btn">Envoyer</button>
    </div>
    <button id="clear-history-btn">Effacer l'historique</button>
</div>

<script src="https://code.responsivevoice.org/responsivevoice.js?key=A0SDeHMK"></script>
<script>
// Variables et fonctions JS pour chat, synthèse vocale, reconnaissance vocale et stockage
const video = document.getElementById('assistant-video');
const chatHistoryDiv = document.getElementById('chat-history');
const userInput = document.getElementById('user-input');
const sendBtn = document.getElementById('send-btn');
const voiceBtn = document.getElementById('voice-btn');
const voiceStatus = document.getElementById('voice-status');
const clearHistoryBtn = document.getElementById('clear-history-btn');
const CHAT_HISTORY_STORAGE_KEY = 'veronica_ai_chat_history';
let recognition=null, isListening=false, chatHistory=[];

function addMessageToChat(message,sender,isTyping=false){
    const div=document.createElement('div'); div.classList.add('chat-message');
    if(sender==='user') div.classList.add('user-message'); else div.classList.add('ai-message');
    if(isTyping){ div.classList.add('typing-indicator'); div.textContent=message; }
    else div.textContent=message;
    chatHistoryDiv.appendChild(div); chatHistoryDiv.scrollTop=chatHistoryDiv.scrollHeight;
    return div;
}

function speakAndShow(sentence){
    let index=0; const aiMessageDiv=addMessageToChat("",'ai');
    function typeWriter(){ if(index<sentence.length){ aiMessageDiv.textContent+=sentence.charAt(index); index++; setTimeout(typeWriter,40); } }
    typeWriter();
    responsiveVoice.speak(sentence,"French Female",{
        rate:1,pitch:1,
        onstart:()=>{video.play(); sendBtn.disabled=true; userInput.disabled=true; voiceBtn.disabled=true;},
        onend:()=>{sendBtn.disabled=false; userInput.disabled=false; voiceBtn.disabled=false; userInput.focus();}
    });
}

async function sendToAI(input,currentChatHistory){
    let typingIndicator=null;
    try{
        sendBtn.disabled=true; userInput.disabled=true; voiceBtn.disabled=true;
        typingIndicator=addMessageToChat("Veronica est en train d'écrire...",'ai',true);
        const response=await fetch(window.location.href,{method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({input:input, model:'cohere', chat_history:currentChatHistory})});
        if(!response.ok){ const errText=await response.text(); console.error('Erreur serveur',response.status,errText); throw new Error(`Erreur serveur ${response.status}`);}
        const result=await response.json();
        return result.response;
    } catch(e){ console.error('Erreur API',e); return "Désolé, une erreur est survenue."; }
    finally{ if(typingIndicator && typingIndicator.parentNode) typingIndicator.parentNode.removeChild(typingIndicator); sendBtn.disabled=false; userInput.disabled=false; voiceBtn.disabled=false; userInput.focus();}
}

// Initialisation
document.addEventListener('DOMContentLoaded',()=>{
    const savedHistory=localStorage.getItem(CHAT_HISTORY_STORAGE_KEY);
    if(savedHistory){ try{ chatHistory=JSON.parse(savedHistory); chatHistory.forEach(item=>addMessageToChat(item.message,item.role.toLowerCase())); } catch(e){localStorage.removeItem(CHAT_HISTORY_STORAGE_KEY);} }
    
    sendBtn.addEventListener('click',async()=>{
        const message=userInput.value.trim(); if(!message) return; addMessageToChat(message,'user');
        chatHistory.push({role:'USER',message:message}); userInput.value='';
        const aiResponse=await sendToAI(message,chatHistory);
        chatHistory.push({role:'CHATBOT',message:aiResponse});
        localStorage.setItem(CHAT_HISTORY_STORAGE_KEY,JSON.stringify(chatHistory));
        speakAndShow(aiResponse);
    });

    userInput.addEventListener('keypress',e=>{ if(e.key==='Enter') sendBtn.click(); });

    clearHistoryBtn.addEventListener('click',()=>{ if(confirm("Effacer l'historique ?")){ chatHistory=[]; chatHistoryDiv.innerHTML=''; localStorage.removeItem(CHAT_HISTORY_STORAGE_KEY);
        const welcome="Bonjour ! Je suis Veronica AI. Écrivez ou parlez pour commencer."; addMessageToChat(welcome,'ai'); chatHistory.push({role:'CHATBOT',message:welcome}); localStorage.setItem(CHAT_HISTORY_STORAGE_KEY,JSON.stringify(chatHistory)); speakAndShow(welcome);}});
});
</script>
</body>
</html>
