<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Chinese learning presentation message
    $presentation = <<<EOT
Hello! I'm Veronica AI, your virtual Chinese instructor. 🌸

I'm here to help you discover and learn Mandarin Chinese step by step — in a fun and friendly way.

In a moment, we'll move to the next page where I'll ask you a few questions to understand your current Chinese level.

I'll also ask why you want to learn Chinese and how you discovered this application.

Don't worry — it's not a test yet! Just a short conversation so I can get to know you better and build your perfect Chinese learning journey. 😊

你好！Let's start together! 🐉
EOT;

    echo json_encode(["response" => $presentation]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Veronica AI – Learn Chinese</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            overflow: hidden;
            font-family: "Inter", sans-serif;
        }

        /* ── Background video ── */
        #full-video {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            object-fit: cover;
            z-index: 0;
        }

        /* ── Dark gradient overlay ── */
        .overlay {
            position: fixed;
            inset: 0;
            background: linear-gradient(
                to top,
                rgba(0, 0, 0, 0.82) 0%,
                rgba(0, 0, 0, 0.35) 55%,
                rgba(0, 0, 0, 0.10) 100%
            );
            z-index: 1;
        }

        /* ── Decorative Chinese characters ── */
        .hanzi-deco {
            position: fixed;
            z-index: 2;
            font-size: 7rem;
            font-weight: 700;
            opacity: 0.06;
            color: #fff;
            user-select: none;
            pointer-events: none;
            animation: floatDeco 8s ease-in-out infinite;
        }
        .hanzi-deco.h1 { top: 6%;  left: 4%;  animation-delay: 0s;   font-size: 9rem; }
        .hanzi-deco.h2 { top: 10%; right: 5%; animation-delay: 2.5s; font-size: 6rem; }
        .hanzi-deco.h3 { bottom: 22%; left: 3%;  animation-delay: 1.2s; font-size: 5rem; }
        .hanzi-deco.h4 { bottom: 18%; right: 4%; animation-delay: 3.5s; font-size: 8rem; }

        @keyframes floatDeco {
            0%, 100% { transform: translateY(0px); }
            50%       { transform: translateY(-18px); }
        }

        /* ── Top badge ── */
        .top-badge {
            position: fixed;
            top: 22px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 30px;
            padding: 8px 20px;
            color: rgba(255,255,255,0.85);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }
        .top-badge .dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #ff4444;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.5; transform: scale(1.4); }
        }

        /* ── Main chat container ── */
        .chat-container {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
            width: 100%;
            max-width: 780px;
            padding: 0 20px 36px;
        }

        /* ── Avatar + name row ── */
        .ai-identity {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .ai-avatar {
            width: 46px; height: 46px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e53e3e, #f6ad55);
            border: 2px solid rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(229,62,62,0.4);
        }
        .ai-name-block { line-height: 1.3; }
        .ai-name {
            font-size: 0.96rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.3px;
        }
        .ai-role {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.5);
            font-weight: 400;
        }

        /* ── Message bubble ── */
        .bubble-wrap {
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 18px;
            min-height: 80px;
            position: relative;
            overflow: hidden;
        }
        .bubble-wrap::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(229,62,62,0.06), rgba(246,173,85,0.04));
            pointer-events: none;
        }

        #messages {
            font-size: 1.05rem;
            line-height: 1.8;
            color: rgba(255,255,255,0.92);
            min-height: 60px;
        }

        /* ── Typing indicator ── */
        .typing {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 4px 0;
        }
        .typing span {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            animation: typingBounce 1.2s infinite ease-in-out;
        }
        .typing span:nth-child(2) { animation-delay: 0.2s; }
        .typing span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typingBounce {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.5; }
            40%           { transform: translateY(-8px); opacity: 1; }
        }

        /* ── Sound wave indicator ── */
        .sound-wave {
            display: none;
            align-items: center;
            gap: 3px;
            margin-top: 10px;
        }
        .sound-wave.active { display: flex; }
        .sound-wave .bar {
            width: 3px;
            border-radius: 2px;
            background: linear-gradient(180deg, #ff4444, #f6ad55);
            animation: soundAnim 0.8s ease-in-out infinite alternate;
        }
        .sound-wave .bar:nth-child(1) { height: 8px;  animation-delay: 0s; }
        .sound-wave .bar:nth-child(2) { height: 16px; animation-delay: 0.1s; }
        .sound-wave .bar:nth-child(3) { height: 22px; animation-delay: 0.2s; }
        .sound-wave .bar:nth-child(4) { height: 14px; animation-delay: 0.15s; }
        .sound-wave .bar:nth-child(5) { height: 20px; animation-delay: 0.05s; }
        .sound-wave .bar:nth-child(6) { height: 10px; animation-delay: 0.25s; }
        .sound-wave .bar:nth-child(7) { height: 18px; animation-delay: 0.1s; }
        .sound-wave span {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.45);
            margin-left: 8px;
            font-weight: 500;
        }
        @keyframes soundAnim {
            from { transform: scaleY(0.4); }
            to   { transform: scaleY(1.2); }
        }

        /* ── Button row ── */
        .btn-row {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-start {
            background: linear-gradient(135deg, #e53e3e, #c53030);
            color: #fff;
            padding: 14px 36px;
            font-size: 0.97rem;
            font-weight: 700;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            letter-spacing: 0.4px;
            transition: all 0.2s;
            box-shadow: 0 6px 24px rgba(229,62,62,0.4);
            display: flex;
            align-items: center;
            gap: 9px;
        }
        .btn-start:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(229,62,62,0.55);
        }
        .btn-start:active { transform: translateY(0); }

        .btn-replay {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.7);
            padding: 14px 22px;
            font-size: 0.9rem;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 7px;
            backdrop-filter: blur(8px);
        }
        .btn-replay:hover {
            background: rgba(255,255,255,0.14);
            color: #fff;
            transform: translateY(-2px);
        }

        /* ── Hanzi label ── */
        .hanzi-label {
            text-align: center;
            font-size: 1.35rem;
            letter-spacing: 6px;
            color: rgba(255,255,255,0.18);
            margin-bottom: 16px;
            font-weight: 700;
            user-select: none;
        }

        @media (max-width: 540px) {
            .chat-container  { padding: 0 14px 28px; }
            #messages        { font-size: 0.93rem; }
            .btn-start       { padding: 13px 28px; font-size: 0.9rem; }
            .hanzi-deco      { display: none; }
        }
    </style>
</head>
<body>

    <!-- Background video -->
    <video id="full-video" loop muted playsinline autoplay>
        <source src="jennifer.mp4"  type="video/mp4" />
        <source src="jennifer.webm" type="video/webm" />
    </video>

    <!-- Overlay -->
    <div class="overlay"></div>

    <!-- Decorative Chinese characters -->
    <div class="hanzi-deco h1">中</div>
    <div class="hanzi-deco h2">文</div>
    <div class="hanzi-deco h3">学</div>
    <div class="hanzi-deco h4">汉</div>

    <!-- Live badge -->
    <div class="top-badge">
        <div class="dot"></div>
        Veronica AI · Chinese Instructor
    </div>

    <!-- Main UI -->
    <div class="chat-container">

        <div class="hanzi-label">你好 · nǐ hǎo · 学中文</div>

        <!-- Avatar + name -->
        <div class="ai-identity">
            <div class="ai-avatar">🌸</div>
            <div class="ai-name-block">
                <div class="ai-name">Veronica AI</div>
                <div class="ai-role">Virtual Chinese Instructor</div>
            </div>
        </div>

        <!-- Bubble -->
        <div class="bubble-wrap">
            <div id="messages">
                <!-- typing indicator while loading -->
                <div class="typing" id="typingDots">
                    <span></span><span></span><span></span>
                </div>
            </div>
            <div class="sound-wave" id="soundWave">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <span>Speaking…</span>
            </div>
        </div>

        <!-- Buttons -->
        <div class="btn-row">
            <button class="btn-replay" id="replay-btn" style="display:none">
                🔊 Replay
            </button>
            <button class="btn-start" id="next-btn" style="display:none">
                🐉 Start Chinese Journey ➡️
            </button>
        </div>

    </div>

    <script>
        const messagesDiv = document.getElementById('messages');
        const nextBtn     = document.getElementById('next-btn');
        const replayBtn   = document.getElementById('replay-btn');
        const soundWave   = document.getElementById('soundWave');
        const typingDots  = document.getElementById('typingDots');
        const video       = document.getElementById('full-video');

        let presentationText = "";
        let loopActive       = true;

        // ── Show message text ──
        function showMessage(text) {
            typingDots.style.display = 'none';
            messagesDiv.innerHTML = '';
            const p = document.createElement('p');
            p.textContent = text;
            messagesDiv.appendChild(p);
            // Show buttons
            nextBtn.style.display   = 'flex';
            replayBtn.style.display = 'flex';
        }

        // ── Speech synthesis with female voice ──
        function speakText(text) {
            if (!window.speechSynthesis) return;
            window.speechSynthesis.cancel();

            const utterance  = new SpeechSynthesisUtterance(text);
            utterance.lang   = 'en-US';
            utterance.rate   = 0.95;
            utterance.pitch  = 1.15; // slightly higher pitch for feminine voice

            const trySpeak = () => {
                const voices = window.speechSynthesis.getVoices();

                // Priority: female English voice
                const femaleVoice =
                    voices.find(v => v.lang.startsWith('en') && /female|woman|girl|zira|susan|karen|samantha|victoria|moira|fiona|tessa/i.test(v.name)) ||
                    voices.find(v => v.lang === 'en-US' && v.name.toLowerCase().includes('female')) ||
                    voices.find(v => v.lang === 'en-GB') ||
                    voices.find(v => v.lang.startsWith('en'));

                if (femaleVoice) utterance.voice = femaleVoice;

                soundWave.classList.add('active');

                utterance.onend = () => {
                    soundWave.classList.remove('active');
                    if (loopActive) {
                        setTimeout(() => speakText(text), 4000);
                    }
                };
                utterance.onerror = () => {
                    soundWave.classList.remove('active');
                };

                window.speechSynthesis.speak(utterance);
            };

            if (window.speechSynthesis.getVoices().length === 0) {
                window.speechSynthesis.onvoiceschanged = trySpeak;
            } else {
                trySpeak();
            }
        }

        // ── Load presentation from PHP ──
        async function loadPresentation() {
            try {
                const res  = await fetch(window.location.href, { method: 'POST' });
                const data = await res.json();
                presentationText = data.response || "Hello! I'm Veronica AI, your Chinese instructor.";

                // Small delay to simulate thinking
                setTimeout(() => {
                    showMessage(presentationText);
                    speakText(presentationText);
                }, 1200);

            } catch (err) {
                console.error("Presentation error:", err);
                showMessage("Hello! I'm Veronica AI, your Chinese instructor. Let's get started! 🌸");
            }
        }

        // ── Replay button ──
        replayBtn.addEventListener('click', () => {
            loopActive = true;
            speakText(presentationText);
        });

        // ── Next button → go to Chinese quiz ──
        nextBtn.addEventListener('click', () => {
            loopActive = false;
            window.speechSynthesis.cancel();
            soundWave.classList.remove('active');
            window.location.href = "quiz_chine.php";
        });

        // ── Init ──
        window.onload = () => {
            loadPresentation();

            setTimeout(() => {
                video.play().catch(err => console.warn("Autoplay blocked:", err));
            }, 400);
        };
    </script>
</body>
</html>
