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
        const speakBtn = document.getElementById('speak-btn');
        const messagesDiv = document.getElementById('messages');
        const video = document.getElementById('full-video');
        let recognition = null;
        let isListening = false;
        let chatHistory = [];

        function addMessage(text, sender) {
            const msg = document.createElement('div');
            msg.textContent = (sender === 'user' ? "👤 " : "🤖 ") + text;
            messagesDiv.appendChild(msg);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        function speakText(text) {
            if (!window.speechSynthesis) return;
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'fr-FR';
            window.speechSynthesis.speak(utterance);
        }

        async function sendToAI(inputText) {
            addMessage(inputText, 'user');
            chatHistory.push({
                role: 'USER',
                message: inputText
            });

            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        input: inputText,
                        chat_history: chatHistory
                    })
                });
                const data = await res.json();
                const response = data.response || "Désolé, je n'ai pas compris.";
                chatHistory.push({
                    role: 'CHATBOT',
                    message: response
                });
                addMessage(response, 'ai'); // Affiche aussi le message
                speakText(response); // Lit à voix haute
            } catch (err) {
                console.error("Erreur avec l'IA :", err);
                speakText("Une erreur s'est produite. Veuillez réessayer.");
            }
        }

        function initSpeechRecognition() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                alert("Votre navigateur ne supporte pas la reconnaissance vocale.");
                return;
            }

            recognition = new SpeechRecognition();
            recognition.lang = "fr-FR";
            recognition.continuous = false;
            recognition.interimResults = false;

            recognition.onstart = () => {
                isListening = true;
                speakBtn.classList.add("recording");
            };
            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                sendToAI(transcript);
            };
            recognition.onerror = (event) => {
                console.error("Erreur vocale :", event.error);
            };
            recognition.onend = () => {
                isListening = false;
                speakBtn.classList.remove("recording");
            };
        }

        speakBtn.addEventListener('click', () => {
            if (!recognition) return;
            if (isListening) recognition.stop();
            else {
                navigator.mediaDevices.getUserMedia({
                        audio: true
                    })
                    .then(stream => {
                        stream.getTracks().forEach(track => track.stop());
                        recognition.start();
                    })
                    .catch(() => alert("Accès au micro refusé."));
            }
        });

        window.onload = () => {
            initSpeechRecognition();
            const welcome = "Bienvenue dans cette modalité vidéo avec moi, Veronica AI. Vous pouvez discuter de n'importe quel sujet pour améliorer votre langue.";
            addMessage(welcome, 'ai');
            speakText(welcome);

            setTimeout(() => {
                video.play().catch(err => console.warn("Lecture auto bloquée :", err));
            }, 500);

            video.onerror = () => {
                console.error("Erreur de chargement de la vidéo.");
                speakText("⚠️ La vidéo n'a pas pu être chargée.");
            };
        };
    </script>
</body>

</html>