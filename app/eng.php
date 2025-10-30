<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // English presentation message
    $presentation = <<<EOT
Hello! I'm Veronica AI, your virtual English instructor. 🌸

I'm here to help you improve your English step by step — in a friendly and enjoyable way.

In a moment, we'll move to the next page where I'll ask you a few questions to understand your current English level.

I'll also ask why you want to learn English and how you discovered this application.

Don't worry — it's not a test yet! Just a short conversation to get to know you better. 😊
EOT;

    echo json_encode(["response" => $presentation]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Veronica AI Introduction</title>
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
        Your browser does not support video playback.
    </video>

    <div class="chat-container">
        <div id="messages"></div>
        <button id="next-btn">➡️ Start English Test</button>
    </div>

    <script>
        const messagesDiv = document.getElementById('messages');
        const nextBtn = document.getElementById('next-btn');
        const video = document.getElementById('full-video');
        let presentationText = "";
        let loopActive = true; // keep speaking until button clicked

        function addMessage(text) {
            messagesDiv.textContent = ""; // only keep one message visible
            const msg = document.createElement('div');
            msg.textContent = "🤖 " + text;
            messagesDiv.appendChild(msg);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        function speakText(text) {
            if (!window.speechSynthesis) {
                alert("⚠️ Your browser does not support speech synthesis.");
                return;
            }

            window.speechSynthesis.cancel(); // stop any current speech
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'en-US';
            utterance.rate = 1.0;
            utterance.pitch = 1.0;

            // Wait for voices to load, then select English female voice if available
            const speakNow = () => {
                const voices = window.speechSynthesis.getVoices();
                const englishVoice = voices.find(v =>
                    v.lang.startsWith('en') && v.name.toLowerCase().includes('female')
                ) || voices.find(v => v.lang.startsWith('en'));
                if (englishVoice) utterance.voice = englishVoice;

                utterance.onend = () => {
                    if (loopActive) {
                        setTimeout(() => speakText(text), 3000);
                    }
                };
                window.speechSynthesis.speak(utterance);
            };

            if (speechSynthesis.getVoices().length === 0) {
                speechSynthesis.onvoiceschanged = speakNow;
            } else {
                speakNow();
            }
        }

        async function loadPresentation() {
            try {
                const res = await fetch(window.location.href, { method: 'POST' });
                const data = await res.json();
                presentationText = data.response || "Hello, I'm Veronica AI.";
                addMessage(presentationText);
                speakText(presentationText);
            } catch (err) {
                console.error("Presentation error:", err);
            }
        }

        nextBtn.addEventListener('click', () => {
            loopActive = false;
            window.speechSynthesis.cancel(); // stop speaking
            window.location.href = "quiz_eng.php"; // go to the English test
        });

        window.onload = () => {
            loadPresentation();

            setTimeout(() => {
                video.play().catch(err => console.warn("Autoplay blocked:", err));
            }, 500);

            video.onerror = () => {
                console.error("Video load error.");
                speakText("⚠️ The video could not be loaded.");
            };
        };
    </script>
</body>

</html>
