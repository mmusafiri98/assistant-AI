<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Message de présentation
    $presentation = <<<EOT
Bonjour, je suis Veronica AI, votre professeure virtuelle de français. 🌸

Je vais vous accompagner dans votre apprentissage de la langue française. 
Dans un instant, nous passerons à la page suivante où je vous poserai quelques questions pour évaluer votre niveau de français.

Je vous demanderai aussi pourquoi vous souhaitez apprendre le français et où vous avez entendu parler de cette application.

Allons-y pas à pas, ce sera amusant et instructif. 😊
EOT;

    echo json_encode(["response" => $presentation]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <title>Présentation de Veronica AI</title>
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

        #next-btn {
            background-color: #3182ce;
            color: white;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
        }

        #next-btn:hover {
            background-color: #2b6cb0;
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
        <button id="next-btn">➡️ Commencer le test de français</button>
    </div>

    <script>
        const messagesDiv = document.getElementById('messages');
        const nextBtn = document.getElementById('next-btn');
        const video = document.getElementById('full-video');
        let presentationText = "";
        let loopActive = true; // tant qu'on n'a pas cliqué

        function addMessage(text) {
            messagesDiv.textContent = ""; // garde un seul message visible
            const msg = document.createElement('div');
            msg.textContent = "🤖 " + text;
            messagesDiv.appendChild(msg);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        function speakText(text) {
            if (!window.speechSynthesis) return;
            window.speechSynthesis.cancel(); // arrête toute voix en cours
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'fr-FR';
            utterance.onend = () => {
                if (loopActive) {
                    // relancer la boucle après 2 secondes
                    setTimeout(() => speakText(text), 2000);
                }
            };
            window.speechSynthesis.speak(utterance);
        }

        async function loadPresentation() {
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST'
                });
                const data = await res.json();
                presentationText = data.response || "Bonjour, je suis Veronica AI.";
                addMessage(presentationText);
                speakText(presentationText);
            } catch (err) {
                console.error("Erreur de présentation :", err);
            }
        }

        nextBtn.addEventListener('click', () => {
            loopActive = false;
            window.speechSynthesis.cancel(); // stoppe la voix
            window.location.href = "quiz_french.php";
        });

        window.onload = () => {
            loadPresentation();

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