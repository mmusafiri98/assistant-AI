<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // 🔹 Présentation en japonais
    $presentation = <<<EOT
こんにちは！私はVeronica AIです、あなたのバーチャルフランス語教師です。🌸

これから、あなたの日本語レベルを確認するためにいくつかの質問をします。
また、なぜ日本語を学びたいのか、どのようにこのアプリを知ったのかもお聞きします。

安心してください — これはまだテストではありません！
まずは簡単な会話から始めましょう。楽しく学べるようにサポートします。😊
EOT;

    echo json_encode(["response" => $presentation]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8" />
    <title>Veronica AI - 自己紹介</title>
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
            font-family: "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif;
           
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
            background-color: rgba(0, 0, 0, 0.5);
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
        お使いのブラウザは動画の再生をサポートしていません。
    </video>

    <div class="chat-container">
        <div id="messages"></div>
        <button id="next-btn">➡️ 日本語レベルテストを始める</button>
    </div>

    <script>
        const messagesDiv = document.getElementById('messages');
        const nextBtn = document.getElementById('next-btn');
        const video = document.getElementById('full-video');
        let presentationText = "";
        let loopActive = true;

        // ✅ Ajoute le texte dans la bulle
        function addMessage(text) {
            messagesDiv.textContent = "";
            const msg = document.createElement('div');
            msg.textContent = "🤖 " + text;
            messagesDiv.appendChild(msg);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }

        // ✅ Lecture avec voix féminine japonaise
        function speakText(text) {
            if (typeof responsiveVoice === "undefined") {
                console.error("❌ ResponsiveVoice が読み込まれていません！");
                return;
            }

            responsiveVoice.cancel();

            // Utilise la voix féminine japonaise
            responsiveVoice.speak(text, "Japanese Female", {
                pitch: 1.05,
                rate: 0.95,
                volume: 1,
                onend: () => {
                    if (loopActive) {
                        setTimeout(() => speakText(text), 2000);
                    }
                }
            });
        }

        // ✅ Charge le texte de présentation depuis PHP
        async function loadPresentation() {
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST'
                });
                const data = await res.json();
                presentationText = data.response || "こんにちは、私はVeronica AIです。";
                addMessage(presentationText);
                speakText(presentationText);
            } catch (err) {
                console.error("プレゼンテーションの読み込みエラー:", err);
            }
        }

        // ✅ Au clic : arrêt + redirection
        nextBtn.addEventListener('click', () => {
            loopActive = false;
            if (typeof responsiveVoice !== "undefined") responsiveVoice.cancel();
            window.location.href = "quiz_japon.php";
        });

        // ✅ Démarrage automatique
        window.onload = () => {
            loadPresentation();

            setTimeout(() => {
                video.play().catch(err => console.warn("🎥 ビデオの再生がブロックされました:", err));
            }, 500);

            video.onerror = () => {
                console.error("🎥 ビデオの読み込みエラー。");
                speakText("⚠️ ビデオを読み込めませんでした。");
            };
        };
    </script>
</body>

</html>