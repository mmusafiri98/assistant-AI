<?php
// PHP Backend Logic
// This part handles the API requests from the frontend
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode the JSON input from the frontend
    $requestData = json_decode(file_get_contents('php://input'), true);
    $input = $requestData['input'] ?? '';
    $model = $requestData['model'] ?? '';
    // MODIFICATION CLÉ : Récupère l'historique du chat envoyé par le frontend
    $chatHistory = $requestData['chat_history'] ?? [];

    // Simple language detection (based on keywords)
    function langDetect($text)
    {
        $languages = [
            'fr' => ['bonjour', 'je', 'le', 'la', 'les', 'est', 'un', 'une', 'corrige'],
            'en' => ['hello', 'the', 'is', 'and', 'you', 'a', 'an', 'correct'],
            'es' => ['hola', 'el', 'la', 'y', 'que', 'un', 'una', 'corrige'],
            'de' => ['hallo', 'der', 'die', 'und', 'sie', 'ein', 'eine', 'korrigieren'],
            'it' => ['ciao', 'il', 'la', 'e', 'che', 'un', 'una', 'correggi'],
            'pt' => ['olá', 'o', 'a', 'e', 'que', 'um', 'uma', 'corrigir'],
            'nl' => ['hallo', 'de', 'het', 'en', 'dat', 'een', 'corrigeer'],
            'ru' => ['привет', 'и', 'в', 'не', 'на'], // Simplified for non-Latin chars
            'zh' => ['你好', '是', '的', '我', '在'], // Simplified
            'ja' => ['こんにちは', 'の', 'に', 'を', 'です'], // Simplified
            'ar' => ['مرحبا', 'و', 'في', 'من', 'على'] // Simplified
        ];

        // Convert input to lowercase for case-insensitive matching
        $lowerText = strtolower($text);

        foreach ($languages as $lang => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($lowerText, $keyword) !== false) {
                    return $lang;
                }
            }
        }
        return 'en'; // Default to English if no language is detected
    }

    $detectedLanguage = langDetect($input);

    // Specific responses for "time" and "date" keywords
    if (stripos($input, 'time') !== false || stripos($input, 'heure') !== false) {
        // Set timezone to Europe/Brussels for Antwerp, Belgium
        date_default_timezone_set('Europe/Brussels');
        echo json_encode(['response' => "L'heure actuelle est : " . date('H:i:s')]);
        exit;
    }

    if (stripos($input, 'date') !== false || stripos($input, 'aujourd\'hui') !== false) {
        date_default_timezone_set('Europe/Brussels');
        echo json_encode(['response' => "La date d'aujourd'hui est : " . date("d/m/Y")]);
        exit;
    }

    // Use Cohere AI (command-r-plus) for general chat
    if ($model === 'cohere') {
        $api_url = "https://api.cohere.ai/v1/chat";
        $data = [
            "model" => "command-a-vision-07-2025",
            "temperature" => 0.7, // Adjust creativity
            "max_tokens" => 300,  // Max length of AI response
            "chat_history" => $chatHistory, // MODIFICATION CLÉ : Passe l'historique complet
            "message" => "Tu es Veronica AI, une professeure de langue experte et patiente. Tu peux dialoguer avec l'utilisateur sur n'importe quel sujet pour lui apprendre la langue. Ton créateur est Pepe Musafiri, un développeur web. Tu crées des scénarios de discussion pour lui apprendre la langue. L'utilisateur a demandé de l'aide pour apprendre ou corriger sa langue. Ton objectif est d'aider à améliorer la phrase de l'utilisateur. Analyse la phrase suivante de l'utilisateur : '" . $input . "'.
            1. Si elle contient des erreurs de grammaire, de syntaxe ou de vocabulaire, corrige-les clairement quand il te le demande de corriger.
            2. Pour chaque correction, fournis des explications claires et concises sur la faute, avec des exemples si possible pour illustrer la règle.
            3. Suggère également des mots ou expressions plus appropriés ou naturels en fonction du contexte de la phrase, si pertinent.
            4. Si la phrase est déjà correcte, félicite l'utilisateur et propose des façons alternatives de dire la même chose ou des améliorations subtiles pour la rendre plus idiomatique.
            Exprime-toi dans la langue de l'utilisateur. Ne réponds pas à des questions hors sujet dans ce mode. Concentre-toi uniquement sur l'analyse linguistique de la phrase fournie." . $input
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ' // REMPLACER PAR VOTRE VRAIE CLÉ COHERE API EN PROD !
        ];

        // Initialize cURL session
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the transfer as a string
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Set HTTP headers
        curl_setopt($ch, CURLOPT_POST, true); // Set as POST request
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Set POST data (JSON encoded)

        // Execute cURL and get response
        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
        $error = curl_error($ch); // Get cURL error
        curl_close($ch); // Close cURL session

        // Handle API response
        if ($api_response === false) {
            error_log("cURL error: " . $error);
            echo json_encode(['response' => "Désolé, une erreur technique est survenue lors de la communication avec l'IA. Code cURL: " . $error]);
            exit;
        }

        $response_json = json_decode($api_response, true);

        // Log the full API response for debugging
        // error_log("Cohere API response: " . json_encode($response_json));

        // Check for specific API errors from Cohere
        if (isset($response_json['message']) && $http_code >= 400) {
            $generated_text = "Désolé, l'IA a rencontré un problème: " . ($response_json['message'] ?? 'Erreur inconnue de l\'API Cohere.');
        } else {
            // Extract the generated text from Cohere's response structure
            $generated_text = $response_json['text'] ?? ($response_json['generations'][0]['text'] ?? 'Erreur de réponse de l\'IA.');
        }

        echo json_encode(['response' => $generated_text]);
        exit;
    }

    // Fallback if no valid model is specified
    echo json_encode(['response' => 'Modèle non supporté.']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <title>Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: violet;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .chat-container {

            border-radius: 1.5rem;

            width: 100%;
            max-width: 500px;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            box-sizing: border-box;
        }


        .video-container {
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
            /* Ajout pour le ratio */
            width: 100%;
            /* Pour contenir la vidéo */
            padding-bottom: 56.25%;
            /* Ratio 16:9 (9/16 * 100) */
            height: 0;
            /* Important pour que padding-bottom fonctionne */
            overflow: hidden;
            /* Cache les débordements */
            border-radius: 1.5rem;
            /* Coins arrondis pour le conteneur */
            background-color: #000;
            /* Fond noir pour le chargement */
        }

        #assistant-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* Assure que la vidéo couvre le conteneur */
            border-radius: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            display: block;
        }

        /* Chat History Styling */
        #chat-history {
            background-color: #e2e8f0;
            border-radius: 1rem;
            padding: 1rem;
            flex-grow: 1;
            overflow-y: auto;
            height: 350px;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .chat-message {
            max-width: 85%;
            padding: 0.75rem 1rem;
            border-radius: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            word-wrap: break-word;
        }

        .user-message {
            background-color: #2563eb;
            color: white;
            align-self: flex-end;
            margin-left: auto;
            /* Force l'alignement à droite */
        }

        .ai-message {
            background-color: white;
            color: #2d3748;
            align-self: flex-start;
            margin-right: auto;
            /* Force l'alignement à gauche */
        }

        .typing-indicator {
            align-self: flex-start;
            background-color: #cbd5e0;
            color: #4a5568;
            padding: 0.75rem 1rem;
            border-radius: 1.25rem;
            font-style: italic;
        }

        /* Input and Button Styling */
        .input-area {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        #user-input {
            flex-grow: 1;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 1px solid #cbd5e0;
            border-radius: 0.75rem;
            outline: none;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        #user-input:focus {
            border-color: #4299e1;
            box-shadow: 0 0 0 2px rgba(66, 153, 225, 0.5);
        }

        #send-btn,
        #voice-btn {
            color: white;
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 50px;
        }

        #send-btn {
            background-color: #4299e1;
        }

        #send-btn:hover {
            background-color: #3182ce;
        }

        #send-btn:disabled {
            background-color: #a0aec0;
            cursor: not-allowed;
        }

        #voice-btn {
            background-color: #48bb78;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            padding: 0;
        }

        #voice-btn:hover {
            background-color: #38a169;
        }

        #voice-btn.recording {
            background-color: #e53e3e;
            animation: pulse 1s infinite;
        }

        #voice-btn.recording:hover {
            background-color: #c53030;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }

            100% {
                transform: scale(1);
            }
        }

        .voice-status {
            text-align: center;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: #4a5568;
            min-height: 1.25rem;
        }

        .voice-status.listening {
            color: #e53e3e;
            font-weight: 600;
        }

        /* Microphone icon SVG styling */
        .mic-icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* Clear History Button */
        #clear-history-btn {
            background-color: #e2e8f0;
            color: #4a5568;
            border: 1px solid #cbd5e0;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            transition: background-color 0.2s ease-in-out;
            margin-top: 1rem;
            align-self: center;
        }

        #clear-history-btn:hover {
            background-color: #cbd5e0;
        }

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .chat-container {
                margin: 1rem;
                padding: 1rem;
            }

            h1 {
                font-size: 1.75rem;
            }

            .input-area {
                flex-direction: column;
            }

            #user-input {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            #send-btn,
            #voice-btn {
                width: 100%;
                max-width: none;
            }

            #voice-btn {
                border-radius: 0.75rem;
                width: 100%;
                height: auto;
                padding: 0.75rem 1.25rem;
            }

            .mic-icon {
                margin-right: 8px;
            }
        }
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
        <!-- Nouveau bouton d'appel -->
        <div class="mt-4 text-center">
            <a href="appel.php" class="inline-flex items-center bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-full shadow-md transition duration-300">
                📞 Appeler Veronica AI
            </a>
        </div>


        <div id="voice-status" class="voice-status"></div>

        <div id="chat-history">
        </div>

        <div class="input-area">
            <input type="text" id="user-input" placeholder="Écrivez votre message ou utilisez le micro..." />
            <button id="voice-btn" title="Cliquez pour parler">
                <svg class="mic-icon" viewBox="0 0 24 24">
                    <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z" />
                    <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z" />
                </svg>
            </button>
            <button id="send-btn">Envoyer</button>
        </div>

        <button id="clear-history-btn">Effacer l'historique</button>
    </div>

    <script src="https://code.responsivevoice.org/responsivevoice.js?key=A0SDeHMK"></script>

    <script>
        // Récupération des éléments du DOM
        const video = document.getElementById('assistant-video');
        const chatHistoryDiv = document.getElementById('chat-history');
        const userInput = document.getElementById('user-input');
        const sendBtn = document.getElementById('send-btn');
        const voiceBtn = document.getElementById('voice-btn');
        const voiceStatus = document.getElementById('voice-status');
        const clearHistoryBtn = document.getElementById('clear-history-btn');

        // Clé pour le stockage local de l'historique
        const CHAT_HISTORY_STORAGE_KEY = 'veronica_ai_chat_history';

        // Variables pour la reconnaissance vocale
        let recognition = null;
        let isListening = false;

        // Historique de la conversation pour l'API Cohere
        let chatHistory = [];

        // Fonction pour ajouter un message à l'interface de chat
        function addMessageToChat(message, sender, isTypingIndicator = false) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('chat-message');

            if (sender === 'user') {
                messageDiv.classList.add('user-message');
            } else if (sender === 'ai') {
                messageDiv.classList.add('ai-message');
            }

            if (isTypingIndicator) {
                messageDiv.classList.add('typing-indicator');
                messageDiv.textContent = message;
            } else {
                messageDiv.textContent = message;
            }

            chatHistoryDiv.appendChild(messageDiv);
            chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
            return messageDiv;
        }

        // Fonction pour afficher le texte lettre par lettre et gérer la synthèse vocale/vidéo
        function speakAndShow(sentence) {
            let index = 0;
            const aiMessageDiv = addMessageToChat("", 'ai');

            function typeWriter() {
                if (index < sentence.length) {
                    aiMessageDiv.textContent += sentence.charAt(index);
                    index++;
                    setTimeout(typeWriter, 40);
                }
            }

            typeWriter();

            responsiveVoice.speak(sentence, "French Female", {
                rate: 1,
                pitch: 1,
                onstart: () => {
                    video.play(); // S'assure que la vidéo est bien en lecture au début de la parole
                    sendBtn.disabled = true;
                    userInput.disabled = true;
                    voiceBtn.disabled = true;
                },
                onend: () => {
                    // video.pause(); // C'est cette ligne que nous avons commentée/supprimée
                    sendBtn.disabled = false;
                    userInput.disabled = false;
                    voiceBtn.disabled = false;
                    userInput.focus();
                    if (voiceStatus.textContent.includes("Vous avez dit")) {
                        setTimeout(() => {
                            voiceStatus.textContent = "";
                        }, 2000);
                    }
                },
            });
        }

        // Fonction pour envoyer le message à l'API PHP
        async function sendToAI(input, currentChatHistory) {
            let typingIndicator = null;
            try {
                sendBtn.disabled = true;
                userInput.disabled = true;
                voiceBtn.disabled = true;
                typingIndicator = addMessageToChat("Veronica est en train d'écrire...", 'ai', true);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        input: input,
                        model: 'cohere',
                        chat_history: currentChatHistory,
                    }),
                });

                if (!response.ok) {
                    const errorDetails = await response.text();
                    console.error('Erreur de réponse du serveur:', response.status, errorDetails);
                    throw new Error(`Erreur du serveur (${response.status}). Détails: ${errorDetails.substring(0, 100)}...`);
                }

                const result = await response.json();
                return result.response;
            } catch (error) {
                console.error('Erreur lors de la communication avec l\'IA:', error);
                return "Désolé, une erreur est survenue. Veuillez réessayer.";
            } finally {
                if (typingIndicator && typingIndicator.parentNode) {
                    typingIndicator.parentNode.removeChild(typingIndicator);
                }
                sendBtn.disabled = false;
                userInput.disabled = false;
                voiceBtn.disabled = false;
                userInput.focus();
            }
        }

        // --- Logique de Reconnaissance Vocale (Web Speech API) ---
        function initSpeechRecognition() {
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                recognition = new SpeechRecognition();

                recognition.continuous = false;
                recognition.interimResults = false;
                recognition.lang = 'fr-FR';

                recognition.onstart = function() {
                    isListening = true;
                    voiceBtn.classList.add('recording');
                    voiceStatus.textContent = "🎤 Écoute en cours... Parlez maintenant !";
                    voiceStatus.classList.add('listening');
                };

                recognition.onresult = function(event) {
                    const transcript = event.results[0][0].transcript;
                    userInput.value = transcript;
                    voiceStatus.textContent = `Vous avez dit: "${transcript}"`;
                    voiceStatus.classList.remove('listening');

                    setTimeout(() => {
                        sendBtn.click();
                    }, 1000);
                };

                recognition.onerror = function(event) {
                    isListening = false;
                    voiceBtn.classList.remove('recording');
                    voiceStatus.classList.remove('listening');

                    let errorMessage = "Erreur de reconnaissance vocale.";
                    switch (event.error) {
                        case 'no-speech':
                            errorMessage = "Aucune parole détectée. Réessayez.";
                            break;
                        case 'audio-capture':
                            errorMessage = "Impossible d'accéder au microphone. Vérifiez les branchements.";
                            break;
                        case 'not-allowed':
                            errorMessage = "Permission microphone refusée. Autorisez l'accès dans les paramètres du navigateur.";
                            break;
                        case 'network':
                            errorMessage = "Erreur réseau pour la reconnaissance vocale.";
                            break;
                        case 'bad-grammar':
                            errorMessage = "Impossible de comprendre la grammaire.";
                            break;
                        default:
                            errorMessage = `Erreur inattendue: ${event.error}`;
                    }
                    voiceStatus.textContent = errorMessage;
                    setTimeout(() => {
                        voiceStatus.textContent = "";
                    }, 5000);
                };

                recognition.onend = function() {
                    isListening = false;
                    voiceBtn.classList.remove('recording');
                    if (!voiceStatus.textContent.includes("Vous avez dit")) {
                        voiceStatus.textContent = "";
                        voiceStatus.classList.remove('listening');
                    }
                };
                return true;
            } else {
                voiceBtn.style.display = 'none';
                console.warn('Reconnaissance vocale non supportée par ce navigateur.');
                return false;
            }
        }

        // Gestion du clic sur le bouton vocal
        voiceBtn.addEventListener('click', function() {
            if (!recognition) {
                alert('La reconnaissance vocale n\'est pas disponible ou n\'a pas pu être initialisée sur votre navigateur.');
                return;
            }

            if (isListening) {
                recognition.stop();
                voiceStatus.textContent = "Arrêt de l'écoute...";
            } else {
                navigator.mediaDevices.getUserMedia({
                        audio: true
                    })
                    .then(function(stream) {
                        stream.getTracks().forEach(track => track.stop());
                        recognition.start();
                    })
                    .catch(function(err) {
                        alert('Accès au microphone refusé. Veuillez autoriser l\'accès dans les paramètres de votre navigateur.');
                        console.error('Erreur d\'accès au microphone:', err);
                        voiceStatus.textContent = "Accès micro refusé.";
                        setTimeout(() => {
                            voiceStatus.textContent = "";
                        }, 5000);
                    });
            }
        });

        // --- Logique de persistance de l'historique du chat (localStorage) ---
        function saveChatHistory() {
            localStorage.setItem(CHAT_HISTORY_STORAGE_KEY, JSON.stringify(chatHistory));
        }

        function loadChatFromStorage() {
            const savedHistory = localStorage.getItem(CHAT_HISTORY_STORAGE_KEY);
            if (savedHistory) {
                try {
                    const parsedHistory = JSON.parse(savedHistory);
                    if (Array.isArray(parsedHistory) && parsedHistory.every(item => item.role && item.message)) {
                        chatHistory = parsedHistory;
                        chatHistory.forEach(item => {
                            addMessageToChat(item.message, item.role.toLowerCase());
                        });
                        return true;
                    }
                } catch (e) {
                    console.error("Erreur de parsing de l'historique local:", e);
                    localStorage.removeItem(CHAT_HISTORY_STORAGE_KEY);
                }
            }
            return false;
        }

        clearHistoryBtn.addEventListener('click', () => {
            if (confirm("Êtes-vous sûr de vouloir effacer tout l'historique de conversation ?")) {
                chatHistory = [];
                chatHistoryDiv.innerHTML = '';
                localStorage.removeItem(CHAT_HISTORY_STORAGE_KEY);
                const welcomeMessage = "Bonjour ! Je suis Veronica AI. Vous pouvez m'écrire ou utiliser le bouton microphone pour me parler. Comment puis-je vous aider aujourd'hui ?";
                addMessageToChat(welcomeMessage, 'ai');
                chatHistory.push({
                    role: 'CHATBOT',
                    message: welcomeMessage
                });
                saveChatHistory();
                speakAndShow(welcomeMessage); // Reprononcer le message de bienvenue
            }
        });

        // --- Gestion de l'envoi de message (bouton Envoyer ou Enter) ---
        sendBtn.addEventListener('click', async () => {
            const message = userInput.value.trim();
            if (!message) return;

            addMessageToChat(message, 'user');

            chatHistory.push({
                role: 'USER',
                message: message
            });

            userInput.value = '';

            const aiResponse = await sendToAI(message, chatHistory);

            chatHistory.push({
                role: 'CHATBOT',
                message: aiResponse
            });

            saveChatHistory();

            speakAndShow(aiResponse);
        });

        userInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                sendBtn.click();
            }
        });

        // --- Nouvelles fonctions pour l'initialisation structurée ---
        function setupEventListeners() {
            // Les écouteurs pour sendBtn, userInput (keypress) et voiceBtn sont déjà définis ci-dessus.
        }

        function updateMemoryStatus() {
            console.log('État de la mémoire mis à jour (non affiché dans l\'UI pour l\'instant).');
        }

        // --- Fonction d'initialisation principale ---
        function initApp() {
            console.log('🚀 Initialisation de l\'assistant Veronica...');

            // Charger la conversation précédente
            const hasLoadedChat = loadChatFromStorage();

            // Initialiser la reconnaissance vocale
            const speechInitialized = initSpeechRecognition();

            // Configurer les écouteurs d'événements
            setupEventListeners();

            // Mettre à jour le statut initial de la mémoire (pour le débogage/consolidation)
            updateMemoryStatus();

            // Focus sur l'input
            if (userInput) {
                userInput.focus();
            }

            // Message de bienvenue si pas de conversation chargée
            if (!hasLoadedChat) {
                setTimeout(() => {
                    const welcomeMessage = "Bonjour ! Je suis Veronica, votre assistante IA prête à vous aider.";
                    addMessageToChat(welcomeMessage, 'ai');
                    chatHistory.push({
                        role: 'CHATBOT',
                        message: welcomeMessage
                    });
                    saveChatHistory();
                    speakAndShow(welcomeMessage);
                }, 500);
            } else {
                // Si l'historique a été chargé, on s'assure que la vidéo continue de jouer
                video.play();
            }
        }

        // --- Appel de la fonction d'initialisation lorsque le DOM est entièrement chargé ---
        document.addEventListener('DOMContentLoaded', initApp);
    </script>
</body>

</html>
