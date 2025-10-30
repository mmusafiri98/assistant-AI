<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // 🔹 Présentation en chinois simplifié
    $presentation = <<<EOT
你好！我是 Veronica AI，你的虚拟法语老师。🌸

我会陪伴你一起学习法语。接下来，我们将进入下一个页面，
我会问你几个问题，以评估你的法语水平。

我还会问你为什么想学习法语，以及你是如何了解到这个应用程序的。

我们会一步一步地学习，这既有趣又充满收获。😊
EOT;

    echo json_encode(["response" => $presentation]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8" />
    <title>Veronica AI 自我介绍</title>
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
            font-family: "Microsoft YaHei", "Noto Sans SC", sans-serif;

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
            background: rgba(0, 0, 0, 0.4);
        }

        #next-btn {
            background-color: #3182ce;
            color: white;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        #next-btn:hover {
            background-color: #2b6cb0;
        }

        .error {
            color: #ff8080;
            font-size: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>

<body>
    <video id="full-video" loop muted playsinline autoplay>
        <source src="jennifer.mp4" type="video/mp4" />
        <source src="jennifer.webm" type="video/webm" />
        您的浏览器不支持视频播放。
    </video>

    <div class="chat-container">
        <div id="messages"></div>
        <button id="next-btn">➡️ 开始法语测试</button>
        <div id="error-msg" class="error"></div>
    </div>

    <script>
        const messagesDiv = document.getElementById('messages');
        const nextBtn = document.getElementById('next-btn');
        const video = document.getElementById('full-video');
        const errorMsg = document.getElementById('error-msg');
        let presentationText = "";
        let loopActive = true; // 循环播放直到点击按钮

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
                errorMsg.textContent = "❌ 语音系统未正确加载，请检查网络连接。";
                console.error("❌ ResponsiveVoice 未加载！");
                return;
            }

            // Arrête toute voix précédente
            responsiveVoice.cancel();

            // 🗣️ Voix féminine chinoise (mandarin)
            responsiveVoice.speak(text, "Chinese Female", {
                pitch: 1.05,
                rate: 0.95,
                volume: 1,
                onend: () => {
                    if (loopActive) {
                        // Rejoue après 2 secondes tant que l'utilisateur n'a pas cliqué
                        setTimeout(() => speakText(text), 2000);
                    }
                },
                onerror: () => {
                    errorMsg.textContent = "⚠️ 无法播放语音，请稍后再试。";
                }
            });
        }

        async function loadPresentation() {
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST'
                });
                const data = await res.json();
                presentationText = data.response || "你好，我是 Veronica AI。";
                addMessage(presentationText);
                speakText(presentationText);
            } catch (err) {
                console.error("加载介绍时出错:", err);
                errorMsg.textContent = "⚠️ 加载介绍失败。";
            }
        }

        nextBtn.addEventListener('click', () => {
            loopActive = false;
            if (typeof responsiveVoice !== "undefined") responsiveVoice.cancel();
            window.location.href = "quiz_china.php";
        });

        window.onload = () => {
            loadPresentation();

            setTimeout(() => {
                video.play().catch(err => console.warn("视频无法播放:", err));
            }, 500);

            video.onerror = () => {
                console.error("视频加载失败。");
                speakText("⚠️ 视频加载失败。");
            };
        };
    </script>
</body>

</html>