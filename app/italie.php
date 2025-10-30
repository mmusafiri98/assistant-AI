<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // 🔹 Messaggio di presentazione in italiano
    $presentation = <<<EOT
Ciao! Sono Veronica AI, la tua insegnante virtuale di francese. 🌸

In questa lezione interattiva ti accompagnerò passo dopo passo nell'apprendimento della lingua francese.
Tra poco, passeremo alla pagina successiva dove ti farò alcune domande per valutare il tuo livello di conoscenza.

Non preoccuparti! Non è un esame, ma un piccolo quiz per capire da dove iniziare il tuo percorso.
Quando sei pronto, clicca sul pulsante in basso per iniziare il test di italiano. 😊
EOT;

    echo json_encode(["response" => $presentation]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8" />
    <title>Presentazione di Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- ✅ Libreria ResponsiveVoice -->
    <script src="https://code.responsivevoice.org/responsivevoice.js?key=A0SDeHMK"></script>

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            height: 100%;
            font-family: "Segoe UI", sans-serif;

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
            font-size: 1.3rem;
            max-height: 30vh;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        #next-btn {
            background-color: #2b6cb0;
            color: white;
            padding: 0.8rem 1.5rem;
            font-size: 1.1rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: background 0.3s;
        }

        #next-btn:hover {
            background-color: #2563eb;
        }
    </style>
</head>

<body>
    <video id="full-video" loop muted playsinline autoplay>
        <source src="jennifer.mp4" type="video/mp4" />
        <source src="jennifer.webm" type="video/webm" />
        Il tuo browser non supporta la riproduzione video.
    </video>

    <div class="chat-container">
        <div id="messages"></div>
        <button id="next-btn">➡️ Inizia il test di italiano</button>
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

        // ✅ Utilizzo di ResponsiveVoice (voce femminile italiana)
        function speakText(text) {
            if (typeof responsiveVoice === "undefined") {
                console.error("❌ ResponsiveVoice non è caricato!");
                return;
            }
            responsiveVoice.cancel();
            responsiveVoice.speak(text, "Italian Female", {
                pitch: 1,
                rate: 0.95,
                volume: 1,
                onend: () => {
                    if (loopActive) {
                        setTimeout(() => speakText(text), 2000);
                    }
                }
            });
        }

        // ✅ Caricamento del testo di presentazione
        async function loadPresentation() {
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST'
                });
                const data = await res.json();
                presentationText = data.response || "Ciao! Sono Veronica AI.";
                addMessage(presentationText);
                speakText(presentationText);
            } catch (err) {
                console.error("Errore durante il caricamento della presentazione:", err);
            }
        }

        // ✅ Attesa dell'interazione utente (clic o tasto)
        window.onload = () => {
            function enableAudio() {
                document.removeEventListener('click', enableAudio);
                document.removeEventListener('keydown', enableAudio);
                console.log("🎤 Audio abilitato dall'utente.");
                loadPresentation();
            }

            document.addEventListener('click', enableAudio);
            document.addEventListener('keydown', enableAudio);

            // Avvia il video di sfondo
            setTimeout(() => {
                video.play().catch(err => console.warn("🎬 Il video non può essere riprodotto:", err));
            }, 500);

            video.onerror = () => {
                console.error("Errore nel caricamento del video.");
                speakText("⚠️ Il video non è stato caricato correttamente.");
            };
        };

        // ✅ Quando l’utente clicca sul pulsante, passa alla pagina quiz_italian.php
        nextBtn.addEventListener('click', () => {
            loopActive = false;
            responsiveVoice.cancel();
            window.location.href = "quiz_italian.php";
        });
    </script>
</body>

</html>