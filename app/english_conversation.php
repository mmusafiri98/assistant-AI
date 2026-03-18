<?php
// PHP Backend Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestData = json_decode(file_get_contents('php://input'), true);
    $input = $requestData['input'] ?? '';
    $model = $requestData['model'] ?? '';
    $chatHistory = $requestData['chat_history'] ?? [];

    // Specific responses for "time" and "date" keywords
    if (stripos($input, 'time') !== false || stripos($input, "what time") !== false) {
        date_default_timezone_set('Europe/Brussels');
        echo json_encode(['response' => "The current time is: " . date('H:i:s') . ". By the way, a natural way to ask this in English is: 'What time is it?' or 'Could you tell me the time, please?'"]);
        exit;
    }

    if (stripos($input, 'date') !== false || stripos($input, "what day") !== false || stripos($input, "today") !== false) {
        date_default_timezone_set('Europe/Brussels');
        echo json_encode(['response' => "Today's date is: " . date("l, F j, Y") . ". A natural way to ask this in English is: 'What's today's date?' or 'What day is it today?'"]);
        exit;
    }

    // Use Cohere AI for general chat
    if ($model === 'cohere') {
        $api_url = "https://api.cohere.ai/v1/chat";
        $data = [
            "model" => "command-a-vision-07-2025",
            "temperature" => 0.7,
            "max_tokens" => 400,
            "chat_history" => $chatHistory,
            "message" => "You are Veronica AI, a warm, patient, and encouraging English language teacher. Your creator is Pepe Musafiri, a web developer. Your mission is to help users improve their English through natural conversation on any topic they choose.

ALWAYS respond exclusively in English.

Your approach:
1. CONVERSATION FIRST: Engage naturally and warmly with what the user said. Answer their question, share your thoughts, keep the conversation flowing on whatever topic they bring up (travel, food, movies, sports, daily life, etc.).
2. GENTLE CORRECTION: If the user made grammar, vocabulary, or spelling mistakes, correct them naturally at the end of your message. Use a friendly format like: '💡 Small tip: Instead of [wrong], try saying: [correct]. This is because [brief explanation].'
3. VOCABULARY BOOST: Occasionally suggest a more natural or advanced way to express their idea. Use: '✨ Pro tip: A more natural way to say this would be: [example].'
4. ENCOURAGEMENT: Always be positive and encouraging. Celebrate progress. Never make the user feel bad about mistakes.
5. KEEP TALKING: Always end your message with a follow-up question or invitation to continue the conversation to keep the user practicing English.
6. If the user writes in another language, gently remind them that you practice together in English, and kindly ask them to try expressing their thought in English, offering help if needed.

The user's message is: '" . $input . "'"
        ];
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ'
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $api_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($api_response === false) {
            error_log("cURL error: " . $error);
            echo json_encode(['response' => "Sorry, a technical error occurred. Please try again! (cURL error: " . $error . ")"]);
            exit;
        }

        $response_json = json_decode($api_response, true);

        if (isset($response_json['message']) && $http_code >= 400) {
            $generated_text = "Sorry, I encountered a problem: " . ($response_json['message'] ?? 'Unknown API error.');
        } else {
            $generated_text = $response_json['text'] ?? ($response_json['generations'][0]['text'] ?? 'AI response error.');
        }

        echo json_encode(['response' => $generated_text]);
        exit;
    }

    echo json_encode(['response' => 'Model not supported.']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Veronica AI – English Teacher</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 50%, #1e40af 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .chat-container {
            border-radius: 1.5rem;
            width: 100%;
            max-width: 520px;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            box-sizing: border-box;
            background: rgba(255,255,255,0.07);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.15);
        }

        .app-title {
            text-align: center;
            color: #ffffff;
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            letter-spacing: -0.5px;
        }

        .app-subtitle {
            text-align: center;
            color: rgba(255,255,255,0.65);
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
        }

        .video-container {
            margin-bottom: 1.25rem;
            text-align: center;
            position: relative;
            width: 100%;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 1.25rem;
            background-color: #0f172a;
            border: 2px solid rgba(255,255,255,0.15);
        }

        #assistant-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 1.25rem;
            display: block;
        }

        /* Level badge */
        .level-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(37,99,235,0.35);
            border: 1px solid rgba(96,165,250,0.4);
            color: #93c5fd;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 999px;
            margin-bottom: 1rem;
            align-self: center;
        }

        #chat-history {
            background-color: rgba(15,23,42,0.6);
            border-radius: 1rem;
            padding: 1rem;
            flex-grow: 1;
            overflow-y: auto;
            height: 340px;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .chat-message {
            max-width: 88%;
            padding: 0.7rem 1rem;
            border-radius: 1.1rem;
            word-wrap: break-word;
            font-size: 0.93rem;
            line-height: 1.55;
        }

        .user-message {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            align-self: flex-end;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }

        .ai-message {
            background-color: rgba(255,255,255,0.93);
            color: #1e293b;
            align-self: flex-start;
            margin-right: auto;
            border-bottom-left-radius: 4px;
        }

        .typing-indicator {
            align-self: flex-start;
            background-color: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.7);
            padding: 0.65rem 1rem;
            border-radius: 1.1rem;
            font-style: italic;
            font-size: 0.875rem;
        }

        .input-area {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        #user-input {
            flex-grow: 1;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 0.75rem;
            outline: none;
            background: rgba(255,255,255,0.1);
            color: #ffffff;
        }

        #user-input::placeholder {
            color: rgba(255,255,255,0.4);
        }

        #user-input:focus {
            border-color: #60a5fa;
            background: rgba(255,255,255,0.15);
            box-shadow: 0 0 0 2px rgba(96,165,250,0.3);
        }

        #send-btn {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            padding: 0.75rem 1.1rem;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        #send-btn:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            transform: translateY(-1px);
        }

        #send-btn:disabled {
            background: rgba(255,255,255,0.15);
            color: rgba(255,255,255,0.4);
            cursor: not-allowed;
            transform: none;
        }

        #voice-btn {
            background-color: #10b981;
            color: white;
            border: none;
            border-radius: 50%;
            width: 46px;
            height: 46px;
            padding: 0;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        #voice-btn:hover { background-color: #059669; }

        #voice-btn.recording {
            background-color: #ef4444;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%   { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,68,68,0.5); }
            50%  { transform: scale(1.07); box-shadow: 0 0 0 8px rgba(239,68,68,0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239,68,68,0); }
        }

        .mic-icon { width: 20px; height: 20px; fill: currentColor; }

        .voice-status {
            text-align: center;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
            min-height: 1.2rem;
        }
        .voice-status.listening { color: #f87171; font-weight: 600; }

        .bottom-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.85rem;
            gap: 0.5rem;
        }

        #clear-history-btn {
            background: transparent;
            color: rgba(255,255,255,0.5);
            border: 1px solid rgba(255,255,255,0.2);
            padding: 0.4rem 0.9rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        #clear-history-btn:hover {
            background: rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.8);
        }

        .call-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #ef4444;
            color: white;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0.4rem 0.9rem;
            border-radius: 999px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .call-btn:hover { background: #dc2626; }

        /* Scrollbar */
        #chat-history::-webkit-scrollbar { width: 4px; }
        #chat-history::-webkit-scrollbar-track { background: transparent; }
        #chat-history::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

        @media (max-width: 640px) {
            .chat-container { margin: 0.75rem; padding: 1rem; }
            .input-area { flex-wrap: wrap; }
            #user-input { width: 100%; }
            #send-btn { flex: 1; }
            #voice-btn { border-radius: 0.75rem; width: auto; height: auto; padding: 0.75rem 1rem; }
        }
    </style>
</head>

<body>
    <div class="chat-container">
        <h1 class="app-title">Veronica AI</h1>
        <p class="app-subtitle">Your personal English teacher — practice anytime, on any topic</p>

        <div class="video-container">
            <video id="assistant-video" loop muted playsinline autoplay>
                <source src="jennifer.mp4" type="video/mp4" />
                <source src="jennifer.webm" type="video/webm" />
                Your browser does not support video playback.
            </video>
        </div>

        <div class="level-badge">
            🇬🇧 English Practice Mode — All levels welcome
        </div>

        <div id="voice-status" class="voice-status"></div>

        <div id="chat-history"></div>

        <div class="input-area">
            <input type="text" id="user-input" placeholder="Write anything in English…" />
            <button id="voice-btn" title="Click to speak">
                <svg class="mic-icon" viewBox="0 0 24 24">
                    <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/>
                    <path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/>
                </svg>
            </button>
            <button id="send-btn">Send →</button>
        </div>

        <div class="bottom-row">
            <button id="clear-history-btn">🗑 Clear history</button>
            <a href="appel.php" class="call-btn">📞 Call Veronica</a>
        </div>
    </div>

    <script src="https://code.responsivevoice.org/responsivevoice.js?key=A0SDeHMK"></script>

    <script>
        const video            = document.getElementById('assistant-video');
        const chatHistoryDiv   = document.getElementById('chat-history');
        const userInput        = document.getElementById('user-input');
        const sendBtn          = document.getElementById('send-btn');
        const voiceBtn         = document.getElementById('voice-btn');
        const voiceStatus      = document.getElementById('voice-status');
        const clearHistoryBtn  = document.getElementById('clear-history-btn');

        const CHAT_HISTORY_STORAGE_KEY = 'veronica_ai_english_chat_history';

        let recognition  = null;
        let isListening  = false;
        let chatHistory  = [];

        // ── DOM helpers ─────────────────────────────────────────────────────────
        function addMessageToChat(message, sender, isTypingIndicator = false) {
            const div = document.createElement('div');
            div.classList.add('chat-message');
            if (sender === 'user')      div.classList.add('user-message');
            else if (sender === 'ai')   div.classList.add('ai-message');
            if (isTypingIndicator)      div.classList.add('typing-indicator');
            div.textContent = message;
            chatHistoryDiv.appendChild(div);
            chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
            return div;
        }

        // ── Typewriter + TTS ────────────────────────────────────────────────────
        function speakAndShow(sentence) {
            let index = 0;
            const aiDiv = addMessageToChat('', 'ai');

            (function typeWriter() {
                if (index < sentence.length) {
                    aiDiv.textContent += sentence.charAt(index++);
                    chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
                    setTimeout(typeWriter, 30);
                }
            })();

            responsiveVoice.speak(sentence, 'UK English Female', {
                rate: 0.95,
                pitch: 1,
                onstart: () => {
                    video.play();
                    sendBtn.disabled = true;
                    userInput.disabled = true;
                    voiceBtn.disabled = true;
                },
                onend: () => {
                    sendBtn.disabled = false;
                    userInput.disabled = false;
                    voiceBtn.disabled = false;
                    userInput.focus();
                    if (voiceStatus.textContent.includes('You said')) {
                        setTimeout(() => { voiceStatus.textContent = ''; }, 2000);
                    }
                }
            });
        }

        // ── API call ─────────────────────────────────────────────────────────────
        async function sendToAI(input, currentChatHistory) {
            let typingIndicator = null;
            try {
                sendBtn.disabled = true;
                userInput.disabled = true;
                voiceBtn.disabled = true;
                typingIndicator = addMessageToChat('Veronica is typing…', 'ai', true);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        input: input,
                        model: 'cohere',
                        chat_history: currentChatHistory
                    })
                });

                if (!response.ok) {
                    const err = await response.text();
                    throw new Error(`Server error (${response.status}): ${err.substring(0, 100)}`);
                }

                const result = await response.json();
                return result.response;
            } catch (error) {
                console.error('AI communication error:', error);
                return "Sorry, an error occurred. Please try again!";
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

        // ── Speech recognition ───────────────────────────────────────────────────
        function initSpeechRecognition() {
            if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
                voiceBtn.style.display = 'none';
                return false;
            }
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            recognition = new SR();
            recognition.continuous     = false;
            recognition.interimResults = false;
            recognition.lang           = 'en-US';

            recognition.onstart = () => {
                isListening = true;
                voiceBtn.classList.add('recording');
                voiceStatus.textContent = '🎤 Listening… speak now!';
                voiceStatus.classList.add('listening');
            };

            recognition.onresult = (e) => {
                const transcript = e.results[0][0].transcript;
                userInput.value = transcript;
                voiceStatus.textContent = `You said: "${transcript}"`;
                voiceStatus.classList.remove('listening');
                setTimeout(() => sendBtn.click(), 900);
            };

            recognition.onerror = (e) => {
                isListening = false;
                voiceBtn.classList.remove('recording');
                voiceStatus.classList.remove('listening');
                const messages = {
                    'no-speech':       'No speech detected. Please try again.',
                    'audio-capture':   'Microphone not found. Check your device.',
                    'not-allowed':     'Microphone access denied. Please allow it in your browser settings.',
                    'network':         'Network error during voice recognition.'
                };
                voiceStatus.textContent = messages[e.error] || `Error: ${e.error}`;
                setTimeout(() => { voiceStatus.textContent = ''; }, 5000);
            };

            recognition.onend = () => {
                isListening = false;
                voiceBtn.classList.remove('recording');
                if (!voiceStatus.textContent.includes('You said')) {
                    voiceStatus.textContent = '';
                    voiceStatus.classList.remove('listening');
                }
            };
            return true;
        }

        voiceBtn.addEventListener('click', () => {
            if (!recognition) {
                alert('Voice recognition is not available in your browser.');
                return;
            }
            if (isListening) {
                recognition.stop();
                voiceStatus.textContent = 'Stopping…';
                return;
            }
            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(stream => { stream.getTracks().forEach(t => t.stop()); recognition.start(); })
                .catch(() => {
                    voiceStatus.textContent = 'Microphone access denied.';
                    setTimeout(() => { voiceStatus.textContent = ''; }, 5000);
                });
        });

        // ── Local storage ────────────────────────────────────────────────────────
        function saveChatHistory() {
            localStorage.setItem(CHAT_HISTORY_STORAGE_KEY, JSON.stringify(chatHistory));
        }

        function loadChatFromStorage() {
            const saved = localStorage.getItem(CHAT_HISTORY_STORAGE_KEY);
            if (!saved) return false;
            try {
                const parsed = JSON.parse(saved);
                if (Array.isArray(parsed) && parsed.every(i => i.role && i.message)) {
                    chatHistory = parsed;
                    chatHistory.forEach(i => addMessageToChat(i.message, i.role === 'USER' ? 'user' : 'ai'));
                    return true;
                }
            } catch (e) {
                localStorage.removeItem(CHAT_HISTORY_STORAGE_KEY);
            }
            return false;
        }

        clearHistoryBtn.addEventListener('click', () => {
            if (!confirm('Are you sure you want to clear the entire conversation history?')) return;
            chatHistory = [];
            chatHistoryDiv.innerHTML = '';
            localStorage.removeItem(CHAT_HISTORY_STORAGE_KEY);
            const welcome = "Hello! I'm Veronica, your personal English teacher. I'm here to help you practice English through fun and natural conversation. We can talk about absolutely anything — movies, travel, your day, dreams… you name it! Don't worry about making mistakes; that's how we learn. So, what would you like to chat about today?";
            addMessageToChat(welcome, 'ai');
            chatHistory.push({ role: 'CHATBOT', message: welcome });
            saveChatHistory();
            speakAndShow(welcome);
        });

        // ── Send message ─────────────────────────────────────────────────────────
        sendBtn.addEventListener('click', async () => {
            const message = userInput.value.trim();
            if (!message) return;

            addMessageToChat(message, 'user');
            chatHistory.push({ role: 'USER', message });
            userInput.value = '';

            const aiResponse = await sendToAI(message, chatHistory);
            chatHistory.push({ role: 'CHATBOT', message: aiResponse });
            saveChatHistory();
            speakAndShow(aiResponse);
        });

        userInput.addEventListener('keypress', e => { if (e.key === 'Enter') sendBtn.click(); });

        // ── Init ─────────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', () => {
            initSpeechRecognition();
            const hasHistory = loadChatFromStorage();

            if (!hasHistory) {
                setTimeout(() => {
                    const welcome = "Hello! I'm Veronica, your personal English teacher. 🎉 I'm so happy you're here! We can practice English by chatting about anything you like — movies, travel, hobbies, work, or whatever is on your mind. I'll gently correct any mistakes and help you sound more natural. Don't be shy — every sentence is a step forward! So, tell me… what's something you enjoy doing in your free time?";
                    addMessageToChat(welcome, 'ai');
                    chatHistory.push({ role: 'CHATBOT', message: welcome });
                    saveChatHistory();
                    speakAndShow(welcome);
                }, 500);
            } else {
                video.play();
            }
        });
    </script>
</body>
</html>
