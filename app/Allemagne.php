<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // 🔹 Présentation en allemand (traduction fluide et naturelle)
    $presentation = <<<EOT
Hallo! Ich bin Veronica AI, deine virtuelle Französischlehrerin. 🌸

Ich werde dich beim Erlernen der französischen Sprache begleiten. 
In wenigen Augenblicken wechseln wir zur nächsten Seite, 
wo ich dir einige Fragen stellen werde, um dein Sprachniveau im Französischen zu bewerten.

Ich werde dich auch fragen, warum du Französisch lernen möchtest 
und wie du von dieser Anwendung erfahren hast.

Wir gehen Schritt für Schritt vor – es wird spannend und lehrreich sein. 😊
EOT;

    echo json_encode(["response" => $presentation]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8" />
    <title>Vorstellung von Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- ✅ Librairie ResponsiveVoice -->
    <script src="https://code.responsivevoice.org/responsivevoice.js?key=A0SDeHMK"></script>

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            height: 100%;
            font-family: 'Segoe UI', Arial, sans-serif;

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
            background-color: rgba(0, 0, 0, 0.4);
        }

        #next-btn {
            background-color: #3182ce;
            color: white;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: background-color 0.3s;
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
        Dein Browser unterstützt das Abspielen von Videos nicht.
    </video>

    <div class="chat-container">
        <div id="messages"></div>
        <button id="next-btn">➡️ Französisch-Test starten</button>
    </div>

    <script>
        const messagesDiv = document.getElementById('messages');
        const nextBtn = document.getElementById('next-btn');
        const video = document.getElementById('full-video');
        let presentationText = "";
        let loopActive = true;

        function addMessage(text) {
            messagesDiv.textContent = "";
            const msg = document.createElement('div');
            msg.textContent = "🤖 " + text;
            messagesDiv.appendChild(msg);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        // ✅ Lecture avec ResponsiveVoice
        function speakText(text) {
            if (typeof responsiveVoice === "undefined") {
                console.error("❌ ResponsiveVoice n'est pas chargé !");
                return;
            }

            // Arrête toute voix précédente
            responsiveVoice.cancel();

            // Lecture de la voix féminine allemande
            responsiveVoice.speak(text, "Deutsch Female", {
                pitch: 1.05,
                rate: 0.95,
                volume: 1,
                onend: () => {
                    if (loopActive) {
                        // Rejoue après 2 secondes tant que l'utilisateur n'a pas cliqué
                        setTimeout(() => speakText(text), 2000);
                    }
                }
            });
        }

        async function loadPresentation() {
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST'
                });
                const data = await res.json();
                presentationText = data.response || "Hallo, ich bin Veronica AI.";
                addMessage(presentationText);
                speakText(presentationText);
            } catch (err) {
                console.error("Fehler beim Laden der Präsentation:", err);
            }
        }

        nextBtn.addEventListener('click', () => {
            loopActive = false;
            if (typeof responsiveVoice !== "undefined") responsiveVoice.cancel();
            window.location.href = "quiz_Allemagne.php";
        });

        window.onload = () => {
            loadPresentation();

            setTimeout(() => {
                video.play().catch(err => console.warn("Video konnte nicht abgespielt werden:", err));
            }, 500);

            video.onerror = () => {
                console.error("Fehler beim Laden des Videos.");
                speakText("⚠️ Das Video konnte nicht geladen werden.");
            };
        };
    </script>
</body>

</html>