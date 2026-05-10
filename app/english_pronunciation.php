<?php
session_start();

/* ===========================
   CONFIG
=========================== */
$COHERE_API_KEY = "Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ";

/* ===========================
   CALL COHERE
=========================== */
function callCohere($prompt, $apiKey, $max_tokens = 600) {
    $url  = "https://api.cohere.ai/v1/chat";
    $data = [
        "model"       => "command-a-vision-07-2025",
        "temperature" => 0.85,
        "max_tokens"  => $max_tokens,
        "message"     => $prompt,
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);
    if ($err) return null;
    $json = json_decode($response, true);
    if (!is_array($json)) return null;
    if (isset($json["text"]))                              return $json["text"];
    if (isset($json["message"]["content"][0]["text"]))    return $json["message"]["content"][0]["text"];
    if (isset($json["generations"][0]["text"]))           return $json["generations"][0]["text"];
    return null;
}

function extractJson($text) {
    if (!$text) return null;
    $d = json_decode($text, true);
    if (is_array($d)) return $d;
    if (preg_match('/\{.*\}/s', $text, $m)) {
        $d = json_decode($m[0], true);
        if (is_array($d)) return $d;
    }
    return null;
}

/* ===========================
   AJAX: GENERATE SENTENCE
=========================== */
if (isset($_POST["action"]) && $_POST["action"] === "generate") {
    header("Content-Type: application/json");
    $level = in_array($_POST["level"] ?? "B1", ["A1","A2","B1","B2","C1"]) ? $_POST["level"] : "B1";
    $topic = trim($_POST["topic"] ?? "");
    $topicLine = $topic ? "The sentence should relate to this topic: \"$topic\"." : "";

    $prompt = "Generate a single English sentence suitable for pronunciation practice at CEFR level $level.
$topicLine
Rules:
- The sentence must be natural and spoken English, 8 to 16 words.
- Do NOT use overly literary or unusual vocabulary unless level is C1.
- Return ONLY a valid JSON object with a single key \"sentence\", no extra text, no markdown.
Example: {\"sentence\": \"She always drinks green tea before going to bed.\"}";

    $raw  = callCohere($prompt, $COHERE_API_KEY, 200);
    $data = extractJson($raw);
    if ($data && isset($data["sentence"])) {
        echo json_encode(["ok" => true, "sentence" => trim($data["sentence"], '"\'')]);
    } else {
        echo json_encode(["ok" => false, "error" => "Could not generate sentence."]);
    }
    exit;
}

/* ===========================
   AJAX: EVALUATE PRONUNCIATION
=========================== */
if (isset($_POST["action"]) && $_POST["action"] === "evaluate") {
    header("Content-Type: application/json");
    $target = trim($_POST["target"] ?? "");
    $spoken = trim($_POST["spoken"] ?? "");

    if (!$target || !$spoken) {
        echo json_encode(["ok" => false, "error" => "Missing data."]);
        exit;
    }

    $prompt = "You are an English pronunciation coach.

Target sentence (what the student should say):
\"$target\"

What the student said (speech-to-text transcript):
\"$spoken\"

Evaluate the student's pronunciation by comparing the two sentences word by word.

Return ONLY a valid JSON object with this exact structure:
{
  \"correct\": true or false,
  \"score\": number from 0 to 100,
  \"feedback\": \"One encouraging sentence summarising overall performance.\",
  \"errors\": [
    {
      \"word\": \"the wrong or missing word\",
      \"expected\": \"the correct word\",
      \"tip\": \"A brief phonetic or pronunciation tip for this word.\"
    }
  ]
}

Rules:
- \"correct\" is true only if the score is 80 or above.
- List ONLY words that were mispronounced, skipped, or substituted (maximum 4 errors).
- \"errors\" can be an empty array [] if the student did well.
- Do NOT add any text outside the JSON object.";

    $raw  = callCohere($prompt, $COHERE_API_KEY, 600);
    $data = extractJson($raw);

    if ($data && isset($data["score"])) {
        echo json_encode(["ok" => true, "result" => $data]);
    } else {
        echo json_encode(["ok" => false, "error" => "Could not evaluate. Please try again."]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI Pronunciation Trainer</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">

<style>
:root {
    --bg:       #0d0f1a;
    --card:     #151929;
    --card2:    #1c2238;
    --border:   rgba(255,255,255,.07);
    --accent:   #6c63ff;
    --green:    #22d3a0;
    --red:      #ff5c7c;
    --yellow:   #f9c846;
    --blue:     #38bdf8;
    --text:     #e8eaf0;
    --muted:    #6b7590;
    --radius:   18px;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 30px 16px 60px;
}

.app {
    width: 100%;
    max-width: 820px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* ---- Header ---- */
.header {
    text-align: center;
    padding: 20px 0 10px;
}

.header h1 {
    font-family: 'Syne', sans-serif;
    font-weight: 800;
    font-size: clamp(1.7rem, 5vw, 2.6rem);
    background: linear-gradient(90deg, var(--accent), var(--blue), var(--green));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
}

.header p {
    color: var(--muted);
    margin-top: 8px;
    font-size: .95rem;
}

/* ---- Settings bar ---- */
.settings {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 22px;
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: flex-end;
}

.settings label {
    font-size: .8rem;
    color: var(--muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: .06em;
    display: block;
    margin-bottom: 6px;
}

.settings select,
.settings input[type="text"] {
    background: var(--card2);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 10px;
    padding: 9px 14px;
    font-size: .95rem;
    font-family: inherit;
    outline: none;
    transition: border-color .2s;
}

.settings select:focus,
.settings input[type="text"]:focus {
    border-color: var(--accent);
}

.settings input[type="text"] {
    flex: 1;
    min-width: 160px;
}

/* ---- Score strip ---- */
.score-strip {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 24px;
    display: flex;
    justify-content: space-around;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.stat { text-align: center; }
.stat-value {
    font-family: 'Syne', sans-serif;
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--accent);
    line-height: 1;
}
.stat-label {
    font-size: .75rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-top: 4px;
}

/* ---- Sentence card ---- */
.sentence-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 30px 28px 24px;
    position: relative;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.sentence-text {
    font-family: 'Syne', sans-serif;
    font-size: clamp(1.3rem, 3.5vw, 2rem);
    font-weight: 700;
    line-height: 1.45;
    color: #fff;
    min-height: 2.5em;
    word-break: break-word;
}

.sentence-text .word {
    display: inline-block;
    transition: color .3s, background .3s;
    border-radius: 6px;
    padding: 0 2px;
}

.sentence-text .word.w-correct { color: var(--green); }
.sentence-text .word.w-wrong   { color: var(--red); text-decoration: underline wavy var(--red); }

.sentence-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* ---- Buttons ---- */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 11px 18px;
    border: none;
    border-radius: 12px;
    font-family: 'DM Sans', sans-serif;
    font-size: .9rem;
    font-weight: 600;
    cursor: pointer;
    transition: transform .15s, opacity .2s, box-shadow .2s;
    color: #fff;
}
.btn:hover  { transform: translateY(-2px); opacity: .9; }
.btn:active { transform: scale(.97); }
.btn:disabled { opacity: .4; cursor: not-allowed; transform: none; }

.btn-listen  { background: var(--accent); }
.btn-speak   { background: var(--green); color: #0d0f1a; }
.btn-next    { background: var(--yellow); color: #0d0f1a; }
.btn-reset   { background: var(--red); }
.btn-icon    { background: var(--card2); border: 1px solid var(--border); }

/* recording pulse */
@keyframes rec-pulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(255,92,124,.5); }
    50%      { box-shadow: 0 0 0 12px rgba(255,92,124,0); }
}
.btn-speak.recording {
    animation: rec-pulse 1s infinite;
    background: var(--red);
    color: #fff;
}

/* ---- Result panel ---- */
.result-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 22px 24px;
    display: none;
    flex-direction: column;
    gap: 14px;
    animation: fadeUp .35s ease;
}

@keyframes fadeUp {
    from { opacity:0; transform: translateY(10px); }
    to   { opacity:1; transform: translateY(0); }
}

.result-panel.visible { display: flex; }

.result-header {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.result-icon { font-size: 2rem; }

.result-score-circle {
    width: 58px; height: 58px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Syne', sans-serif;
    font-size: 1.2rem;
    font-weight: 800;
    flex-shrink: 0;
}
.score-good  { background: rgba(34,211,160,.15); border: 2px solid var(--green); color: var(--green); }
.score-mid   { background: rgba(249,200,70,.15);  border: 2px solid var(--yellow); color: var(--yellow); }
.score-bad   { background: rgba(255,92,124,.15);  border: 2px solid var(--red); color: var(--red); }

.result-feedback {
    font-size: .95rem;
    line-height: 1.6;
    color: var(--text);
    flex: 1;
}

.you-said {
    background: var(--card2);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: .88rem;
    color: var(--muted);
}
.you-said span { color: var(--text); font-weight: 500; }

.errors-list { display: flex; flex-direction: column; gap: 10px; }

.error-item {
    background: rgba(255,92,124,.07);
    border: 1px solid rgba(255,92,124,.2);
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.error-words {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Syne', sans-serif;
    font-size: 1rem;
    font-weight: 700;
}
.error-wrong    { color: var(--red); }
.error-arrow    { color: var(--muted); font-size: .85rem; }
.error-expected { color: var(--green); }

.error-tip {
    font-size: .83rem;
    color: var(--muted);
    line-height: 1.5;
}

/* ---- Speed control ---- */
.speed-row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: .82rem;
    color: var(--muted);
}
input[type="range"] {
    accent-color: var(--accent);
    width: 90px;
    cursor: pointer;
}

/* ---- Loading overlay ---- */
.loading-overlay {
    display: none;
    position: absolute;
    inset: 0;
    background: rgba(13,15,26,.75);
    border-radius: var(--radius);
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 10px;
    z-index: 10;
}
.loading-overlay.show { display: flex; }
.spinner {
    width: 36px; height: 36px;
    border: 3px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin .7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.loading-label { font-size: .85rem; color: var(--muted); }

/* ---- Toast ---- */
.toast {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(40px);
    background: var(--card2);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 12px 22px;
    border-radius: 50px;
    font-size: .88rem;
    pointer-events: none;
    opacity: 0;
    transition: opacity .3s, transform .3s;
    z-index: 999;
}
.toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}
</style>
</head>
<body>

<div class="app">

    <!-- Header -->
    <div class="header">
        <h1>🎤 AI Pronunciation Trainer</h1>
        <p>Speak the sentence — AI analyses your pronunciation and guides you to perfection</p>
    </div>

    <!-- Settings -->
    <div class="settings">
        <div>
            <label for="level">CEFR Level</label>
            <select id="level">
                <option value="A1">A1 – Beginner</option>
                <option value="A2">A2 – Elementary</option>
                <option value="B1" selected>B1 – Intermediate</option>
                <option value="B2">B2 – Upper-Intermediate</option>
                <option value="C1">C1 – Advanced</option>
            </select>
        </div>
        <div style="flex:1">
            <label for="topic">Topic (optional)</label>
            <input type="text" id="topic" placeholder="e.g. travel, food, technology…">
        </div>
    </div>

    <!-- Score strip -->
    <div class="score-strip">
        <div class="stat">
            <div class="stat-value" id="statScore">0</div>
            <div class="stat-label">Total Score</div>
        </div>
        <div class="stat">
            <div class="stat-value" id="statCorrect">0</div>
            <div class="stat-label">Correct</div>
        </div>
        <div class="stat">
            <div class="stat-value" id="statTotal">0</div>
            <div class="stat-label">Attempts</div>
        </div>
        <div class="stat">
            <div class="stat-value" id="statAvg">—</div>
            <div class="stat-label">Avg %</div>
        </div>
        <button class="btn btn-reset" onclick="resetAll()">♻ Reset</button>
    </div>

    <!-- Sentence card -->
    <div class="sentence-card">
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
            <div class="loading-label" id="loadingLabel">Generating sentence…</div>
        </div>

        <div class="sentence-text" id="sentenceText">
            <span style="color:var(--muted);font-size:1rem;font-weight:400">Click <strong style="color:var(--accent)">Next Sentence</strong> to begin.</span>
        </div>

        <div class="speed-row">
            🐢
            <input type="range" id="ttsSpeed" min="0.6" max="1.3" step="0.05" value="0.9">
            <span id="ttsSpeedVal">0.90×</span>
            🐇
        </div>

        <div class="sentence-actions">
            <button class="btn btn-listen"  id="btnListen"  onclick="playTTS()" disabled>🔊 Listen</button>
            <button class="btn btn-speak"   id="btnSpeak"   onclick="toggleRecognition()" disabled>🎙 Speak</button>
            <button class="btn btn-next"    id="btnNext"    onclick="loadSentence()">➡ Next Sentence</button>
        </div>
    </div>

    <!-- Result panel -->
    <div class="result-panel" id="resultPanel">
        <div class="result-header">
            <div class="result-icon" id="resultIcon">—</div>
            <div class="result-score-circle" id="resultCircle">—</div>
            <div class="result-feedback" id="resultFeedback"></div>
        </div>
        <div class="you-said" id="youSaid"></div>
        <div class="errors-list" id="errorsList"></div>
    </div>

</div>

<div class="toast" id="toast"></div>

<script>
/* ===========================
   STATE
=========================== */
let currentSentence = "";
let recognition     = null;
let isRecording     = false;
let totalScore      = 0;
let correctCount    = 0;
let attemptCount    = 0;
let scoreSum        = 0;

/* ===========================
   SPEED SLIDER
=========================== */
const speedSlider = document.getElementById("ttsSpeed");
const speedLabel  = document.getElementById("ttsSpeedVal");
speedSlider.addEventListener("input", () => {
    speedLabel.textContent = parseFloat(speedSlider.value).toFixed(2) + "×";
});

/* ===========================
   TOAST
=========================== */
function showToast(msg, dur = 2800) {
    const t = document.getElementById("toast");
    t.textContent = msg;
    t.classList.add("show");
    setTimeout(() => t.classList.remove("show"), dur);
}

/* ===========================
   LOAD SENTENCE FROM AI
=========================== */
async function loadSentence() {
    const level = document.getElementById("level").value;
    const topic = document.getElementById("topic").value.trim();

    setLoading(true, "Generating sentence…");
    disableActions(true);
    hideResult();

    try {
        const fd = new FormData();
        fd.append("action", "generate");
        fd.append("level", level);
        fd.append("topic", topic);

        const res  = await fetch("", { method: "POST", body: fd });
        const data = await res.json();

        if (data.ok) {
            currentSentence = data.sentence;
            renderSentence(currentSentence);
            disableActions(false);
        } else {
            showToast("⚠️ " + (data.error || "Error generating sentence."));
        }
    } catch (e) {
        showToast("⚠️ Network error. Please try again.");
    } finally {
        setLoading(false);
    }
}

/* ===========================
   RENDER SENTENCE (words as spans)
=========================== */
function renderSentence(sentence, spoken) {
    const el = document.getElementById("sentenceText");

    if (!spoken) {
        el.innerHTML = sentence.split(" ").map(w =>
            `<span class="word">${escHtml(w)}</span>`
        ).join(" ");
        return;
    }

    // Colour-code words based on match with spoken
    const targetWords = sentence.toLowerCase().split(" ");
    const spokenWords  = spoken.toLowerCase().split(" ");

    el.innerHTML = sentence.split(" ").map((w, i) => {
        const clean = w.toLowerCase().replace(/[^a-z']/g, "");
        const found = spokenWords.some(s => s.replace(/[^a-z']/g, "") === clean);
        const cls   = found ? "w-correct" : "w-wrong";
        return `<span class="word ${cls}">${escHtml(w)}</span>`;
    }).join(" ");
}

function escHtml(s) {
    return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
}

/* ===========================
   TTS – LISTEN
=========================== */
function playTTS() {
    if (!currentSentence || !("speechSynthesis" in window)) return;
    speechSynthesis.cancel();
    const utt  = new SpeechSynthesisUtterance(currentSentence);
    utt.lang   = "en-US";
    utt.rate   = parseFloat(speedSlider.value);
    utt.pitch  = 1.0;
    const voices = speechSynthesis.getVoices();
    const v = voices.find(v => v.lang.startsWith("en") && !v.name.toLowerCase().includes("compact"));
    if (v) utt.voice = v;
    speechSynthesis.speak(utt);
}

if (speechSynthesis.onvoiceschanged !== undefined) {
    speechSynthesis.onvoiceschanged = () => {};
}

/* ===========================
   SPEECH RECOGNITION
=========================== */
function toggleRecognition() {
    if (isRecording) {
        stopRecognition();
    } else {
        startRecognition();
    }
}

function startRecognition() {
    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRec) {
        showToast("⚠️ Speech recognition not supported in this browser.");
        return;
    }
    if (!currentSentence) {
        showToast("⚠️ Generate a sentence first.");
        return;
    }

    recognition = new SpeechRec();
    recognition.lang            = "en-US";
    recognition.interimResults  = false;
    recognition.maxAlternatives = 1;
    recognition.continuous      = false;

    recognition.onstart = () => {
        isRecording = true;
        const btn   = document.getElementById("btnSpeak");
        btn.classList.add("recording");
        btn.textContent = "⏹ Stop";
        showToast("🎙 Listening… speak now");
    };

    recognition.onresult = (e) => {
        const spoken = e.results[0][0].transcript;
        stopRecognition();
        evaluatePronunciation(spoken);
    };

    recognition.onerror = (e) => {
        stopRecognition();
        if (e.error !== "aborted") showToast("⚠️ Recognition error: " + e.error);
    };

    recognition.onend = () => {
        stopRecognition();
    };

    recognition.start();
}

function stopRecognition() {
    if (recognition) { try { recognition.stop(); } catch(e){} recognition = null; }
    isRecording = false;
    const btn = document.getElementById("btnSpeak");
    btn.classList.remove("recording");
    btn.innerHTML = "🎙 Speak";
}

/* ===========================
   EVALUATE WITH AI
=========================== */
async function evaluatePronunciation(spoken) {
    setLoading(true, "AI is analysing your pronunciation…");

    try {
        const fd = new FormData();
        fd.append("action",  "evaluate");
        fd.append("target",  currentSentence);
        fd.append("spoken",  spoken);

        const res  = await fetch("", { method: "POST", body: fd });
        const data = await res.json();

        if (data.ok) {
            showResult(data.result, spoken);
        } else {
            showToast("⚠️ " + (data.error || "Evaluation error."));
        }
    } catch (e) {
        showToast("⚠️ Network error. Please try again.");
    } finally {
        setLoading(false);
    }
}

/* ===========================
   SHOW RESULT
=========================== */
function showResult(result, spoken) {
    attemptCount++;
    scoreSum += result.score;
    if (result.correct) correctCount++;

    const panel    = document.getElementById("resultPanel");
    const icon     = document.getElementById("resultIcon");
    const circle   = document.getElementById("resultCircle");
    const feedback = document.getElementById("resultFeedback");
    const youSaid  = document.getElementById("youSaid");
    const errList  = document.getElementById("errorsList");

    // Score circle
    const sc = result.score;
    circle.textContent = sc + "%";
    circle.className   = "result-score-circle " +
        (sc >= 80 ? "score-good" : sc >= 55 ? "score-mid" : "score-bad");

    icon.textContent = sc >= 80 ? "🎉" : sc >= 55 ? "👍" : "📚";

    feedback.textContent = result.feedback || "";

    youSaid.innerHTML = `You said: <span>${escHtml(spoken)}</span>`;

    // Errors
    errList.innerHTML = "";
    if (result.errors && result.errors.length > 0) {
        result.errors.forEach(err => {
            const d = document.createElement("div");
            d.className = "error-item";
            d.innerHTML = `
                <div class="error-words">
                    <span class="error-wrong">❌ ${escHtml(err.word)}</span>
                    <span class="error-arrow">→</span>
                    <span class="error-expected">✅ ${escHtml(err.expected)}</span>
                </div>
                <div class="error-tip">💡 ${escHtml(err.tip)}</div>
            `;
            errList.appendChild(d);
        });
    } else if (result.correct) {
        errList.innerHTML = `<div style="color:var(--green);font-size:.9rem;">✨ No errors detected — excellent pronunciation!</div>`;
    }

    panel.classList.add("visible");

    // Colour words in sentence
    renderSentence(currentSentence, spoken);

    // Update stats
    totalScore = totalScore + sc;
    updateStats();
}

function hideResult() {
    document.getElementById("resultPanel").classList.remove("visible");
}

/* ===========================
   UPDATE STATS
=========================== */
function updateStats() {
    document.getElementById("statScore").textContent   = totalScore;
    document.getElementById("statCorrect").textContent = correctCount;
    document.getElementById("statTotal").textContent   = attemptCount;
    document.getElementById("statAvg").textContent     =
        attemptCount ? Math.round(scoreSum / attemptCount) + "%" : "—";
}

/* ===========================
   RESET
=========================== */
function resetAll() {
    totalScore = 0; correctCount = 0; attemptCount = 0; scoreSum = 0;
    currentSentence = "";
    updateStats();
    document.getElementById("sentenceText").innerHTML =
        `<span style="color:var(--muted);font-size:1rem;font-weight:400">Click <strong style="color:var(--accent)">Next Sentence</strong> to begin.</span>`;
    hideResult();
    disableActions(true);
}

/* ===========================
   UI HELPERS
=========================== */
function setLoading(show, label = "") {
    const ov = document.getElementById("loadingOverlay");
    document.getElementById("loadingLabel").textContent = label;
    ov.classList.toggle("show", show);
}

function disableActions(dis) {
    document.getElementById("btnListen").disabled = dis;
    document.getElementById("btnSpeak").disabled  = dis;
}
</script>
</body>
</html>
