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
    if (isset($json["text"]))                           return $json["text"];
    if (isset($json["message"]["content"][0]["text"])) return $json["message"]["content"][0]["text"];
    if (isset($json["generations"][0]["text"]))        return $json["generations"][0]["text"];
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
    $level     = in_array($_POST["level"] ?? "B1", ["A1","A2","B1","B2","C1"]) ? $_POST["level"] : "B1";
    $topic     = trim($_POST["topic"] ?? "");
    $topicLine = $topic ? "The sentence should relate to the topic: \"$topic\"." : "";

    $prompt = "Generate a single natural English sentence for pronunciation practice at CEFR level $level.
$topicLine
Rules:
- 8 to 15 words, everyday spoken English.
- Return ONLY a valid JSON object with one key \"sentence\". No markdown, no extra text.
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

    $prompt = "You are an expert English pronunciation coach.

Target sentence (what the student should say):
\"$target\"

What the student actually said (speech-to-text transcript):
\"$spoken\"

Compare the two carefully word by word and evaluate the pronunciation.

Return ONLY a valid JSON object — no markdown, no extra text — with this exact structure:
{
  \"correct\": true or false,
  \"score\": integer from 0 to 100,
  \"feedback\": \"One encouraging sentence summarising the overall performance.\",
  \"errors\": [
    {
      \"word\": \"the mispronounced or missing word from the target\",
      \"expected\": \"the correct word as it should sound\",
      \"tip\": \"A short, practical phonetic tip to improve this specific word.\"
    }
  ]
}

Rules:
- \"correct\" is true only when score >= 80.
- List at most 4 errors — only for words clearly mispronounced, skipped, or replaced.
- If the student was perfect or nearly perfect, \"errors\" must be an empty array [].
- Do NOT output anything outside the JSON object.";

    $raw  = callCohere($prompt, $COHERE_API_KEY, 700);
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
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Mono:wght@400;500&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --bg:        #080b14;
    --surface:   #0f1422;
    --surface2:  #161c30;
    --border:    rgba(255,255,255,.06);
    --accent:    #7c6fff;
    --green:     #1edd9a;
    --red:       #ff4f72;
    --yellow:    #fbbf24;
    --blue:      #38bdf8;
    --text:      #dde3f5;
    --muted:     #5a6380;
    --radius:    20px;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'DM Sans',sans-serif;
    background:var(--bg);
    color:var(--text);
    min-height:100vh;
    padding:28px 14px 70px;
    display:flex;
    justify-content:center;
}
body::before{
    content:"";
    position:fixed;inset:0;
    background-image:
        linear-gradient(rgba(124,111,255,.03) 1px,transparent 1px),
        linear-gradient(90deg,rgba(124,111,255,.03) 1px,transparent 1px);
    background-size:40px 40px;
    pointer-events:none;z-index:0;
}
.app{
    width:100%;max-width:780px;
    display:flex;flex-direction:column;gap:18px;
    position:relative;z-index:1;
}

/* HEADER */
.header{text-align:center;padding:16px 0 6px;}
.header h1{
    font-family:'Syne',sans-serif;font-weight:800;
    font-size:clamp(1.8rem,5vw,2.8rem);
    background:linear-gradient(100deg,var(--accent) 0%,var(--blue) 50%,var(--green) 100%);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.header p{color:var(--muted);margin-top:6px;font-size:.93rem;}

/* SETTINGS */
.settings{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius);padding:16px 20px;
    display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;
}
.field{display:flex;flex-direction:column;gap:5px;}
.field label{font-size:.73rem;color:var(--muted);text-transform:uppercase;letter-spacing:.07em;font-weight:600;}
select,input[type="text"]{
    background:var(--surface2);border:1px solid var(--border);
    color:var(--text);border-radius:10px;padding:9px 13px;
    font-size:.92rem;font-family:'DM Sans',sans-serif;outline:none;transition:border-color .2s;
}
select:focus,input[type="text"]:focus{border-color:var(--accent);}
.field-topic{flex:1;min-width:180px;}
.field-topic input{width:100%;}

/* STATS */
.stats{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius);padding:14px 20px;
    display:flex;justify-content:space-around;align-items:center;flex-wrap:wrap;gap:8px;
}
.stat{text-align:center;}
.stat-val{font-family:'Syne',sans-serif;font-size:1.7rem;font-weight:800;color:var(--accent);line-height:1;}
.stat-lbl{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-top:3px;}
.btn-reset-small{
    background:transparent;border:1px solid rgba(255,79,114,.35);
    color:var(--red);border-radius:9px;padding:7px 14px;
    font-size:.82rem;font-weight:600;cursor:pointer;transition:background .2s;
}
.btn-reset-small:hover{background:rgba(255,79,114,.1);}

/* SENTENCE CARD */
.sentence-card{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius);padding:26px 24px 22px;
    position:relative;display:flex;flex-direction:column;gap:18px;overflow:hidden;
}
.sentence-card::after{
    content:"";position:absolute;top:-60px;right:-60px;
    width:200px;height:200px;
    background:radial-gradient(circle,rgba(124,111,255,.12) 0%,transparent 70%);
    pointer-events:none;
}
.sentence-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);font-weight:600;}
.sentence-display{
    font-family:'Syne',sans-serif;font-size:clamp(1.3rem,3.5vw,2rem);
    font-weight:700;line-height:1.5;min-height:2.8em;color:#fff;word-break:break-word;
}
.sentence-display .w{
    display:inline-block;padding:1px 3px;border-radius:5px;transition:color .4s,background .4s;
}
.sentence-display .w.ok {color:var(--green);}
.sentence-display .w.bad{color:var(--red);text-decoration:underline wavy var(--red);}
.sentence-placeholder{color:var(--muted);font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:400;}

.speed-row{display:flex;align-items:center;gap:10px;font-size:.8rem;color:var(--muted);}
input[type="range"]{accent-color:var(--accent);width:85px;cursor:pointer;}
#speedVal{font-family:'DM Mono',monospace;font-size:.8rem;color:var(--accent);}

.actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;}
.btn{
    display:inline-flex;align-items:center;gap:7px;
    padding:11px 20px;border:none;border-radius:12px;
    font-family:'DM Sans',sans-serif;font-size:.9rem;font-weight:700;
    cursor:pointer;color:#fff;transition:transform .15s,opacity .2s;
}
.btn:hover{transform:translateY(-2px);opacity:.9;}
.btn:active{transform:scale(.96);}
.btn:disabled{opacity:.3;cursor:not-allowed;transform:none;}
.btn-listen{background:var(--accent);}
.btn-next  {background:var(--yellow);color:#0d0f1a;}

/* card loader */
.card-loader{
    display:none;position:absolute;inset:0;
    background:rgba(8,11,20,.82);border-radius:var(--radius);
    align-items:center;justify-content:center;flex-direction:column;gap:10px;
    z-index:20;backdrop-filter:blur(4px);
}
.card-loader.show{display:flex;}
.spinner{
    width:34px;height:34px;border:3px solid var(--border);
    border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg);}}
.loader-txt{font-size:.82rem;color:var(--muted);}

/* MIC SECTION */
.mic-section{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius);padding:30px 24px 26px;
    display:flex;flex-direction:column;align-items:center;gap:18px;
}
.mic-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);font-weight:600;align-self:flex-start;}

/* mic button */
.mic-btn{
    position:relative;width:100px;height:100px;
    background:none;border:none;cursor:pointer;
    display:flex;align-items:center;justify-content:center;outline:none;
}
.mic-btn:disabled{opacity:.3;cursor:not-allowed;}

/* ripple rings */
.mic-ring{
    position:absolute;border-radius:50%;
    border:2px solid var(--accent);opacity:0;pointer-events:none;
    width:100px;height:100px;
}
@keyframes mic-wave{
    0%  {transform:scale(1);  opacity:.55;}
    100%{transform:scale(2.3);opacity:0;}
}
.mic-btn.active .mic-ring-1{animation:mic-wave 1.8s 0.0s ease-out infinite;}
.mic-btn.active .mic-ring-2{animation:mic-wave 1.8s 0.45s ease-out infinite;}
.mic-btn.active .mic-ring-3{animation:mic-wave 1.8s 0.9s ease-out infinite;}

/* inner circle */
.mic-inner{
    width:76px;height:76px;
    background:var(--surface2);
    border:2px solid var(--border);border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:2rem;
    transition:background .25s,border-color .25s,transform .15s,box-shadow .25s;
    position:relative;z-index:2;
}
.mic-btn:not(:disabled):hover .mic-inner{
    background:rgba(124,111,255,.12);border-color:var(--accent);transform:scale(1.07);
}
.mic-btn.active .mic-inner{
    background:rgba(255,79,114,.15);border-color:var(--red);
    box-shadow:0 0 28px rgba(255,79,114,.35);
}

/* volume bars */
.vol-bars{
    display:flex;align-items:flex-end;gap:4px;height:32px;
    opacity:0;transition:opacity .3s;
}
.vol-bars.show{opacity:1;}
.vol-bar{
    width:6px;background:linear-gradient(to top,var(--accent),var(--blue));
    border-radius:3px 3px 0 0;height:4px;transition:height .09s ease;
}

/* status */
.mic-status{
    font-size:.88rem;color:var(--muted);
    letter-spacing:.03em;height:1.2em;transition:color .2s;
    font-weight:500;
}
.mic-status.listening{color:var(--red);font-weight:700;}

/* live transcript */
.transcript-wrap{width:100%;}
.transcript-lbl{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:600;margin-bottom:6px;}
.transcript-box{
    width:100%;
    background:var(--surface2);border:1px solid var(--border);
    border-radius:12px;padding:13px 16px;min-height:50px;
    font-family:'DM Mono',monospace;font-size:.9rem;
    color:var(--text);line-height:1.65;
    transition:border-color .3s,opacity .2s;word-break:break-word;
}
.transcript-box.has-text{border-color:rgba(124,111,255,.3);}
.transcript-box.interim{opacity:.65;}
.transcript-placeholder{color:var(--muted);font-style:italic;font-family:'DM Sans',sans-serif;font-size:.87rem;}

/* RESULT PANEL */
.result-panel{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--radius);padding:24px;
    display:none;flex-direction:column;gap:16px;animation:fadeUp .35s ease;
}
.result-panel.show{display:flex;}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}

.result-top{display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.score-circle{
    width:64px;height:64px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-family:'Syne',sans-serif;font-size:1.1rem;font-weight:800;flex-shrink:0;
}
.sc-good{background:rgba(30,221,154,.12);border:2px solid var(--green);color:var(--green);}
.sc-mid {background:rgba(251,191,36,.12); border:2px solid var(--yellow);color:var(--yellow);}
.sc-bad {background:rgba(255,79,114,.12); border:2px solid var(--red);color:var(--red);}
.result-emoji{font-size:2.2rem;line-height:1;}
.result-feedback{font-size:.95rem;line-height:1.65;flex:1;}

.you-said{
    background:var(--surface2);border-radius:10px;
    padding:10px 14px;font-size:.86rem;color:var(--muted);
    font-family:'DM Mono',monospace;
}
.you-said span{color:var(--text);}

.errors{display:flex;flex-direction:column;gap:10px;}
.error-item{
    background:rgba(255,79,114,.06);border:1px solid rgba(255,79,114,.18);
    border-radius:12px;padding:13px 16px;display:flex;flex-direction:column;gap:5px;
}
.err-words{
    display:flex;align-items:center;gap:9px;
    font-family:'Syne',sans-serif;font-size:.98rem;font-weight:700;
}
.err-wrong{color:var(--red);}
.err-arrow{color:var(--muted);font-size:.8rem;}
.err-right{color:var(--green);}
.err-tip{font-size:.82rem;color:var(--muted);line-height:1.55;}
.no-errors{font-size:.88rem;color:var(--green);}

/* TOAST */
.toast{
    position:fixed;bottom:22px;left:50%;
    transform:translateX(-50%) translateY(40px);
    background:var(--surface2);border:1px solid var(--border);
    color:var(--text);padding:11px 22px;border-radius:50px;
    font-size:.86rem;pointer-events:none;opacity:0;
    transition:opacity .3s,transform .3s;z-index:999;white-space:nowrap;
}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
</style>
</head>
<body>
<div class="app">

    <!-- HEADER -->
    <div class="header">
        <h1>🎤 AI Pronunciation Trainer</h1>
        <p>Speak into the mic — AI transcribes your voice live and tells you exactly what to fix</p>
    </div>

    <!-- SETTINGS -->
    <div class="settings">
        <div class="field">
            <label for="level">Level</label>
            <select id="level">
                <option value="A1">A1 – Beginner</option>
                <option value="A2">A2 – Elementary</option>
                <option value="B1" selected>B1 – Intermediate</option>
                <option value="B2">B2 – Upper-Intermediate</option>
                <option value="C1">C1 – Advanced</option>
            </select>
        </div>
        <div class="field field-topic">
            <label for="topic">Topic (optional)</label>
            <input type="text" id="topic" placeholder="e.g. travel, food, technology…">
        </div>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat">
            <div class="stat-val" id="sTotal">0</div>
            <div class="stat-lbl">Attempts</div>
        </div>
        <div class="stat">
            <div class="stat-val" id="sCorrect">0</div>
            <div class="stat-lbl">Correct</div>
        </div>
        <div class="stat">
            <div class="stat-val" id="sAvg">—</div>
            <div class="stat-lbl">Avg Score</div>
        </div>
        <div class="stat">
            <div class="stat-val" id="sBest">—</div>
            <div class="stat-lbl">Best</div>
        </div>
        <button class="btn-reset-small" onclick="resetAll()">♻ Reset</button>
    </div>

    <!-- SENTENCE CARD -->
    <div class="sentence-card" id="sentenceCard">
        <div class="card-loader" id="cardLoader">
            <div class="spinner"></div>
            <div class="loader-txt" id="loaderTxt">Generating sentence…</div>
        </div>

        <div class="sentence-label">📖 Sentence to pronounce</div>

        <div class="sentence-display" id="sentenceDisplay">
            <span class="sentence-placeholder">Click <strong style="color:var(--accent)">Next Sentence</strong> to start your first exercise.</span>
        </div>

        <div class="speed-row">
            🐢
            <input type="range" id="speedSlider" min="0.6" max="1.3" step="0.05" value="0.9">
            <span id="speedVal">0.90×</span>
            🐇
        </div>

        <div class="actions">
            <button class="btn btn-listen" id="btnListen" onclick="playTTS()" disabled>🔊 Listen</button>
            <button class="btn btn-next"   id="btnNext"   onclick="loadSentence()">➡ Next Sentence</button>
        </div>
    </div>

    <!-- MIC SECTION -->
    <div class="mic-section" id="micSection">
        <div class="mic-label">🎙 Your pronunciation</div>

        <!-- animated mic button -->
        <button class="mic-btn" id="micBtn" onclick="toggleMic()" disabled title="Click to speak">
            <div class="mic-ring mic-ring-1"></div>
            <div class="mic-ring mic-ring-2"></div>
            <div class="mic-ring mic-ring-3"></div>
            <div class="mic-inner" id="micInner">🎙</div>
        </button>

        <!-- volume visualiser bars -->
        <div class="vol-bars" id="volBars">
            <div class="vol-bar" id="vb0"></div>
            <div class="vol-bar" id="vb1"></div>
            <div class="vol-bar" id="vb2"></div>
            <div class="vol-bar" id="vb3"></div>
            <div class="vol-bar" id="vb4"></div>
            <div class="vol-bar" id="vb5"></div>
            <div class="vol-bar" id="vb6"></div>
        </div>

        <!-- mic status text -->
        <div class="mic-status" id="micStatus">Click the mic to start speaking</div>

        <!-- live transcript -->
        <div class="transcript-wrap">
            <div class="transcript-lbl">Live transcript</div>
            <div class="transcript-box" id="transcriptBox">
                <span class="transcript-placeholder" id="transcriptPlaceholder">Your speech will appear here as you speak…</span>
            </div>
        </div>
    </div>

    <!-- RESULT PANEL -->
    <div class="result-panel" id="resultPanel">
        <div class="result-top">
            <div class="result-emoji" id="resultEmoji">—</div>
            <div class="score-circle" id="scoreCircle">—</div>
            <div class="result-feedback" id="resultFeedback"></div>
        </div>
        <div class="you-said" id="youSaid"></div>
        <div class="errors" id="errorsList"></div>
    </div>

</div>

<div class="toast" id="toast"></div>

<script>
/* ============================================================
   STATE
============================================================ */
let currentSentence = "";
let recognition     = null;
let isRecording     = false;
let volInterval     = null;

let attempts = 0, correct = 0, scoreSum = 0, bestScore = 0;

/* ============================================================
   SPEED SLIDER
============================================================ */
const speedSlider = document.getElementById("speedSlider");
const speedVal    = document.getElementById("speedVal");
speedSlider.addEventListener("input", () => {
    speedVal.textContent = parseFloat(speedSlider.value).toFixed(2) + "×";
});

/* ============================================================
   TOAST
============================================================ */
function toast(msg, ms = 2800) {
    const el = document.getElementById("toast");
    el.textContent = msg;
    el.classList.add("show");
    setTimeout(() => el.classList.remove("show"), ms);
}

/* ============================================================
   LOAD SENTENCE FROM AI
============================================================ */
async function loadSentence() {
    const level = document.getElementById("level").value;
    const topic = document.getElementById("topic").value.trim();

    setLoader(true, "Generating sentence…");
    hideResult();
    setMicEnabled(false);
    clearTranscript();

    try {
        const fd = new FormData();
        fd.append("action", "generate");
        fd.append("level",  level);
        fd.append("topic",  topic);
        const res  = await fetch("", { method:"POST", body:fd });
        const data = await res.json();

        if (data.ok) {
            currentSentence = data.sentence;
            renderWords(currentSentence);
            document.getElementById("btnListen").disabled = false;
            setMicEnabled(true);
        } else {
            toast("⚠️ " + (data.error || "Error generating sentence."));
        }
    } catch(e) {
        toast("⚠️ Network error. Please try again.");
    } finally {
        setLoader(false);
    }
}

/* ============================================================
   RENDER WORDS (colour-coded after evaluation)
============================================================ */
function renderWords(sentence, spoken) {
    const el    = document.getElementById("sentenceDisplay");
    const words = sentence.split(" ");

    if (!spoken) {
        el.innerHTML = words.map(w => `<span class="w">${esc(w)}</span>`).join(" ");
        return;
    }

    const spokenClean = spoken.toLowerCase().split(" ").map(w => w.replace(/[^a-z']/g,""));
    el.innerHTML = words.map(w => {
        const clean = w.toLowerCase().replace(/[^a-z']/g,"");
        const found = spokenClean.includes(clean);
        return `<span class="w ${found ? "ok" : "bad"}">${esc(w)}</span>`;
    }).join(" ");
}

function esc(s) {
    return s.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
}

/* ============================================================
   TTS — LISTEN BUTTON
============================================================ */
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
if (speechSynthesis.onvoiceschanged !== undefined)
    speechSynthesis.onvoiceschanged = () => {};

/* ============================================================
   MIC — TOGGLE
============================================================ */
function toggleMic() {
    if (isRecording) stopMic();
    else             startMic();
}

function startMic() {
    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRec) { toast("⚠️ Speech recognition not supported in this browser."); return; }
    if (!currentSentence) { toast("⚠️ Generate a sentence first."); return; }

    recognition = new SpeechRec();
    recognition.lang            = "en-US";
    recognition.interimResults  = true;   // live transcript while speaking
    recognition.maxAlternatives = 1;
    recognition.continuous      = false;

    recognition.onstart = () => {
        isRecording = true;
        setMicActive(true);
        startVolBars();
        clearTranscript();
        hideResult();
    };

    recognition.onresult = (e) => {
        let interim = "", final = "";
        for (let i = e.resultIndex; i < e.results.length; i++) {
            const t = e.results[i][0].transcript;
            if (e.results[i].isFinal) final += t;
            else                      interim += t;
        }
        // show live text while speaking
        if (interim) setTranscript(interim, true);
        if (final) {
            setTranscript(final, false);
            stopMic();
            evaluatePronunciation(final.trim());
        }
    };

    recognition.onerror = (e) => {
        stopMic();
        if (e.error !== "aborted") toast("⚠️ Mic error: " + e.error);
    };

    recognition.onend = () => { if (isRecording) stopMic(); };

    recognition.start();
}

function stopMic() {
    if (recognition) { try { recognition.stop(); } catch(e){} recognition = null; }
    isRecording = false;
    setMicActive(false);
    stopVolBars();
}

/* ============================================================
   MIC UI
============================================================ */
function setMicActive(active) {
    const btn    = document.getElementById("micBtn");
    const status = document.getElementById("micStatus");
    const inner  = document.getElementById("micInner");
    btn.classList.toggle("active", active);
    status.classList.toggle("listening", active);
    status.textContent = active ? "🔴 Listening… speak now" : "Click the mic to start speaking";
    inner.textContent  = active ? "⏹" : "🎙";
}

function setMicEnabled(on) {
    document.getElementById("micBtn").disabled = !on;
}

/* ============================================================
   VOLUME BARS (animated while recording)
============================================================ */
const BAR_IDS = ["vb0","vb1","vb2","vb3","vb4","vb5","vb6"];

function startVolBars() {
    document.getElementById("volBars").classList.add("show");
    volInterval = setInterval(() => {
        BAR_IDS.forEach(id => {
            const h = 4 + Math.random() * 26;
            document.getElementById(id).style.height = h + "px";
        });
    }, 90);
}

function stopVolBars() {
    clearInterval(volInterval);
    document.getElementById("volBars").classList.remove("show");
    BAR_IDS.forEach(id => document.getElementById(id).style.height = "4px");
}

/* ============================================================
   TRANSCRIPT BOX
============================================================ */
function setTranscript(text, interim) {
    const box = document.getElementById("transcriptBox");
    const ph  = document.getElementById("transcriptPlaceholder");
    if (ph) ph.remove();
    box.classList.add("has-text");
    box.classList.toggle("interim", interim);
    box.textContent = text;
}

function clearTranscript() {
    const box = document.getElementById("transcriptBox");
    box.classList.remove("has-text","interim");
    box.innerHTML = `<span class="transcript-placeholder" id="transcriptPlaceholder">Your speech will appear here as you speak…</span>`;
}

/* ============================================================
   EVALUATE WITH AI
============================================================ */
async function evaluatePronunciation(spoken) {
    setLoader(true, "AI is analysing your pronunciation…");

    try {
        const fd = new FormData();
        fd.append("action",  "evaluate");
        fd.append("target",  currentSentence);
        fd.append("spoken",  spoken);
        const res  = await fetch("", { method:"POST", body:fd });
        const data = await res.json();

        if (data.ok) {
            showResult(data.result, spoken);
        } else {
            toast("⚠️ " + (data.error || "Evaluation error."));
        }
    } catch(e) {
        toast("⚠️ Network error. Please try again.");
    } finally {
        setLoader(false);
    }
}

/* ============================================================
   SHOW RESULT
============================================================ */
function showResult(r, spoken) {
    attempts++;
    scoreSum += r.score;
    if (r.correct) correct++;
    if (r.score > bestScore) bestScore = r.score;
    updateStats();

    const sc = r.score;

    document.getElementById("scoreCircle").textContent = sc + "%";
    document.getElementById("scoreCircle").className =
        "score-circle " + (sc >= 80 ? "sc-good" : sc >= 55 ? "sc-mid" : "sc-bad");
    document.getElementById("resultEmoji").textContent = sc >= 80 ? "🎉" : sc >= 55 ? "👍" : "📚";
    document.getElementById("resultFeedback").textContent = r.feedback || "";
    document.getElementById("youSaid").innerHTML = `You said: <span>${esc(spoken)}</span>`;

    const errList = document.getElementById("errorsList");
    errList.innerHTML = "";

    if (r.errors && r.errors.length > 0) {
        r.errors.forEach(err => {
            const d = document.createElement("div");
            d.className = "error-item";
            d.innerHTML = `
                <div class="err-words">
                    <span class="err-wrong">❌ ${esc(err.word)}</span>
                    <span class="err-arrow">→</span>
                    <span class="err-right">✅ ${esc(err.expected)}</span>
                </div>
                <div class="err-tip">💡 ${esc(err.tip)}</div>`;
            errList.appendChild(d);
        });
    } else {
        errList.innerHTML = `<div class="no-errors">✨ No errors detected — excellent pronunciation!</div>`;
    }

    document.getElementById("resultPanel").classList.add("show");
    renderWords(currentSentence, spoken);

    setTimeout(() => {
        document.getElementById("resultPanel").scrollIntoView({behavior:"smooth",block:"nearest"});
    }, 120);
}

function hideResult() {
    document.getElementById("resultPanel").classList.remove("show");
    if (currentSentence) renderWords(currentSentence);
}

/* ============================================================
   STATS
============================================================ */
function updateStats() {
    document.getElementById("sTotal").textContent   = attempts;
    document.getElementById("sCorrect").textContent = correct;
    document.getElementById("sAvg").textContent     = attempts ? Math.round(scoreSum/attempts)+"%" : "—";
    document.getElementById("sBest").textContent    = attempts ? bestScore+"%" : "—";
}

/* ============================================================
   RESET
============================================================ */
function resetAll() {
    attempts = 0; correct = 0; scoreSum = 0; bestScore = 0;
    currentSentence = "";
    updateStats();
    document.getElementById("sentenceDisplay").innerHTML =
        `<span class="sentence-placeholder">Click <strong style="color:var(--accent)">Next Sentence</strong> to start your first exercise.</span>`;
    document.getElementById("btnListen").disabled = true;
    setMicEnabled(false);
    hideResult();
    clearTranscript();
    stopMic();
}

/* ============================================================
   LOADER
============================================================ */
function setLoader(show, txt = "") {
    document.getElementById("loaderTxt").textContent = txt;
    document.getElementById("cardLoader").classList.toggle("show", show);
}
</script>
</body>
</html>
