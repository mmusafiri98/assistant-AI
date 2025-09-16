<?php
// Backend PHP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestData = json_decode(file_get_contents('php://input'), true);
    $input = $requestData['input'] ?? '';
    $model = $requestData['model'] ?? 'cohere';
    $chatHistory = $requestData['chat_history'] ?? [];

    // Fonction simple de détection de langue
    function langDetect($text) {
        $languages = [
            'fr' => ['bonjour','je','le','la','les','est','un','une','corrige'],
            'en' => ['hello','the','is','and','you','a','an','correct'],
            'es' => ['hola','el','la','y','que','un','una','corrige'],
        ];
        $lowerText = strtolower($text);
        foreach ($languages as $lang => $keywords) {
            foreach ($keywords as $kw) {
                if (stripos($lowerText, $kw) !== false) return $lang;
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

    // Cohere API
    if ($model === 'cohere') {
        $api_url = "https://api.cohere.ai/v1/chat";
        $data = [
            "model" => "command-a-vision-07-2025",
            "temperature" => 0.7,
            "max_tokens" => 300,
            "chat_history" => array_map(function($item){
                return [
                    "role" => strtoupper($item['role']),
                    "content" => $item['message']
                ];
            }, $chatHistory),
            "message" => "Tu es Veronica AI, professeure de langue patiente. Analyse et corrige : '".$input."' et explique clairement les corrections."
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer VOTRE_CLE_API_COHERE' // <-- Remplace par ta clé
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

        // Vérification robuste de la réponse
        if (isset($response_json['generations'][0]['text'])) {
            $generated_text = $response_json['generations'][0]['text'];
        } elseif (isset($response_json['error']['message'])) {
            $generated_text = "Erreur API Cohere : " . $response_json['error']['message'];
        } else {
            // debug complet si inattendu
            $generated_text = "Réponse inattendue de l'API : " . json_encode($response_json);
        }

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
body { font-family: 'Inter', sans-serif; background-color: violet; display:flex; justify-content:center; align-items:center; min-height:100vh; margin:0; }
.chat-container { border-radius:1.5rem; width:100%; max-width:500px; display:flex; flex-direction:column; padding:1.5rem; box-sizing:border-box; }
#chat-history { background-color:#e2e8f0; border-radius:1rem; padding:1rem; flex-grow:1; overflow-y:auto; height:350px; display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1rem; }
.chat-message { max-width:85%; padding:0.75rem 1rem; border-radius:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.1); word-wrap:break-word; }
.user-message { background-color:#2563eb; color:white; align-self:flex-end; margin-left:auto; }
.ai-message { background-color:white; color:#2d3748; align-self:flex-start; margin-right:auto; }
.input-area { display:flex; gap:0.5rem; align-items:center; }
#user-input { flex-grow:1; padding:0.75rem 1rem; font-size:1rem; border:1px solid #cbd5e0; border-radius:0.75rem; outline:none; }
#send-btn { background-color:#4299e1; color:white; padding:0.75rem 1.25rem; border:none; border-radius:0.75rem; cursor:pointer; }
</style>
</head>
<body>
<div class="chat-container">
    <h1>Veronica AI</h1>
    <div id="chat-history"></div>
    <div class="input-area">
        <input type="text" id="user-input" placeholder="Écrivez votre message..." />
        <button id="send-btn">Envoyer</button>
    </div>
</div>

<script>
const chatHistoryDiv = document.getElementById('chat-history');
const userInput = document.getElementById('user-input');
const sendBtn = document.getElementById('send-btn');
let chatHistory=[];

function addMessage(message,role){
    const div=document.createElement('div');
    div.classList.add('chat-message', role==='user'?'user-message':'ai-message');
    div.textContent=message;
    chatHistoryDiv.appendChild(div);
    chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
}

async function sendToAI(message){
    chatHistory.push({role:'USER', message});
    addMessage(message,'user');

    const response = await fetch(window.location.href,{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({input:message, model:'cohere', chat_history:chatHistory})
    });

    const result = await response.json();
    const aiText = result.response || "Erreur IA.";
    chatHistory.push({role:'CHATBOT', message:aiText});
    addMessage(aiText,'ai');
}

sendBtn.addEventListener('click',()=>{ 
    const message = userInput.value.trim(); 
    if(!message) return;
    userInput.value='';
    sendToAI(message);
});

userInput.addEventListener('keypress', e=>{
    if(e.key==='Enter') sendBtn.click();
});
</script>
</body>
</html>

