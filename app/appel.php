<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $data = json_decode(file_get_contents("php://input"), true);
    $input = $data['input'] ?? '';
    $chatHistory = $data['chat_history'] ?? [];

    $api_url = "https://api.cohere.ai/v1/chat";
    $api_key = "Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ";

    $system_prompt = <<<EOD
Tu es Veronica AI, une professeure de langue experte et patiente. Tu peux dialoguer avec l'utilisateur sur n'importe quel sujet et lui expliquer n'importe quel domaine. Tu es en modalité vocale. Message utilisateur: "{$input}"
EOD;

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
        echo json_encode(["response" => "Erreur de connexion à Cohere."]);
        exit;
    }
    curl_close($ch);

    $decoded = json_decode($result, true);
    $responseText = $decoded['text'] ?? "Aucune réponse de l'IA.";

    echo json_encode(["response" => $responseText]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <title>Appel avec Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            height: 100%;
            font-family: sans-serif;
        }

        #full-video {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            object-fit: cover;
            z-index: -1;
        }

        .chat-container {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            width: 100%;
            max-width: 90%;
            color: white;
        }

        #messages {
            border-radius: 1rem;
            padding: 1rem;
            font-size: 1.2rem;
            max-height: 30vh;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        #speak-btn {
            background-color: #48bb78;
            color: white;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
        }

        #speak-btn.recording {
            background-color: #e53e3e;
            animation: pulse 1s infinite;
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
    </style>
</head>

<body>
    <video id="full-video" loop muted playsinline autoplay>
        <source src="jennifer.mp4" type="video/mp4" />
        <source src="jennifer.webm" type="video/webm" />
        Votre navigateur ne supporte pas la lecture vidéo.
    </video>

    <div class="chat-container">
        <div id="messages"></div>
        <button id="speak-btn">🎤 Parler</button>
        <div class="mt-4 text-center">
            <a href="discussion.php" class="inline-flex items-center bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded-full shadow-md transition duration-300">
                changement ambiance
            </a>
        </div>
    </div>

    <script>
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

    </script>
</body>

</html>


