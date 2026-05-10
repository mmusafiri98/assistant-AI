<?php

session_start();

/* ===========================
   CONFIG
=========================== */

$COHERE_API_KEY = "Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ";


/* ===========================
   CALL COHERE
=========================== */

function callCohere($prompt, $apiKey) {

    $url  = "https://api.cohere.ai/v1/chat";

    $data = [
        "model"       => "command-r-plus",
        "temperature" => 0.8,
        "max_tokens"  => 1200,
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
    $curlErr  = curl_error($ch);
    curl_close($ch);

    /* --- Debug: salva risposta grezza in sessione per eventuali errori --- */
    if ($curlErr) {
        return null;
    }

    $json = json_decode($response, true);

    if (!is_array($json)) {
        return null;
    }

    /* Cohere command-r-plus: risposta in $json["text"] */
    if (isset($json["text"]) && is_string($json["text"])) {
        return $json["text"];
    }

    /* Formato alternativo */
    if (isset($json["message"]["content"][0]["text"])) {
        return $json["message"]["content"][0]["text"];
    }

    /* Fallback: cerca qualsiasi "text" nel payload */
    if (isset($json["generations"][0]["text"])) {
        return $json["generations"][0]["text"];
    }

    return null;
}


/* ===========================
   ESTRAI JSON DALLA RISPOSTA
   (Cohere a volte aggiunge testo extra attorno al JSON)
=========================== */

function extractJson($text) {

    if ($text === null || $text === '') {
        return null;
    }

    /* Prova direttamente */
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    /* Cerca il primo { ... } che sia JSON valido */
    if (preg_match('/\{.*\}/s', $text, $matches)) {
        $decoded = json_decode($matches[0], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}


/* ===========================
   GENERATE EXERCISE
=========================== */

if (isset($_POST["generate"])) {

    $prompt = '
Create an English reading comprehension exercise.

Return ONLY a valid JSON object — no extra text, no markdown, no code fences.

Use exactly this format:

{
  "story": "...",
  "questions": [
    {
      "question": "...",
      "options": ["...", "...", "..."],
      "answer": "..."
    }
  ]
}

Rules:
- The story must be between 120 and 180 words.
- Include exactly 3 multiple-choice questions.
- Each question must have exactly 3 options.
- The "answer" field must match one of the options exactly.
- Target level: A2/B1 (elementary-intermediate).
- Topic must be educational and suitable for all ages.
- Do NOT add any text outside the JSON object.
';

    $raw     = callCohere($prompt, $COHERE_API_KEY);
    $exercise = extractJson($raw);

    if (
        is_array($exercise)
        && isset($exercise["story"])
        && isset($exercise["questions"])
        && is_array($exercise["questions"])
        && count($exercise["questions"]) > 0
    ) {
        $_SESSION["exercise"] = $exercise;
        $_SESSION["error"]    = null;
    } else {
        $_SESSION["exercise"] = null;
        $_SESSION["error"]    = "Could not generate the exercise. Please try again.";
    }

    header("Location: " . $_SERVER["PHP_SELF"]);
    exit;
}


/* ===========================
   RESET
=========================== */

if (isset($_POST["reset"])) {
    unset($_SESSION["exercise"]);
    unset($_SESSION["error"]);
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>English AI Lesson</title>

<style>

*, *::before, *::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #4f46e5, #06b6d4);
    min-height: 100vh;
    padding: 30px 16px;
}

.container {
    max-width: 860px;
    margin: auto;
    background: #fff;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 15px 40px rgba(0,0,0,.25);
}

h1 {
    text-align: center;
    color: #4338ca;
    margin-bottom: 24px;
    font-size: 1.8rem;
}

/* ---- Controls ---- */

.controls {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.controls form {
    display: inline;
}

button {
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    color: #fff;
    font-weight: bold;
    font-size: 1rem;
    cursor: pointer;
    transition: opacity .2s;
}

button:hover { opacity: .85; }

.btn-generate { background: #4f46e5; }
.btn-listen   { background: #10b981; }
.btn-check    { background: #f59e0b; }
.btn-reset    { background: #ef4444; }

/* ---- Story ---- */

.story {
    background: #f8fafc;
    padding: 24px;
    border-radius: 14px;
    line-height: 1.85;
    font-size: 1.1rem;
    margin-bottom: 28px;
    color: #1e293b;
}

/* ---- Questions ---- */

.question {
    background: #f1f5f9;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 16px;
}

.question p {
    font-weight: bold;
    margin-bottom: 12px;
    color: #1e293b;
}

label {
    display: block;
    padding: 9px 12px;
    cursor: pointer;
    border-radius: 8px;
    margin-bottom: 6px;
    transition: background .15s;
}

label:hover { background: #e2e8f0; }

label.correct { background: #dcfce7; color: #15803d; font-weight: bold; }
label.wrong   { background: #fee2e2; color: #b91c1c; }

/* ---- Result ---- */

.result {
    text-align: center;
    font-size: 1.4rem;
    font-weight: bold;
    margin-top: 20px;
    color: #4338ca;
    min-height: 36px;
}

/* ---- Error / Placeholder ---- */

.placeholder {
    background: #f8fafc;
    padding: 40px;
    border-radius: 14px;
    text-align: center;
    color: #64748b;
    font-size: 1.1rem;
}

.error-box {
    background: #fee2e2;
    color: #b91c1c;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: bold;
}

</style>
</head>
<body>

<div class="container">

    <h1>🤖 AI English Reading Lesson</h1>

    <!-- Controls -->
    <div class="controls">

        <form method="POST">
            <button class="btn-generate" name="generate">
                ✨ Generate Exercise
            </button>
        </form>

        <?php if (!empty($_SESSION["exercise"])): ?>

            <button type="button" class="btn-listen" onclick="readStory()">
                🔊 Listen
            </button>

            <button type="button" class="btn-check" onclick="checkAnswers()">
                ✅ Check Answers
            </button>

            <form method="POST">
                <button class="btn-reset" name="reset">
                    🔄 Reset
                </button>
            </form>

        <?php endif; ?>

    </div>

    <!-- Error message -->
    <?php if (!empty($_SESSION["error"])): ?>
        <div class="error-box">
            ⚠️ <?= htmlspecialchars($_SESSION["error"]) ?>
        </div>
    <?php endif; ?>


    <?php if (!empty($_SESSION["exercise"])):
        $exercise = $_SESSION["exercise"];
    ?>

        <!-- Story -->
        <div class="story" id="story">
            <?= nl2br(htmlspecialchars($exercise["story"])) ?>
        </div>

        <!-- Questions -->
        <div id="quiz">
            <?php foreach ($exercise["questions"] as $index => $q): ?>

                <div class="question">
                    <p><?= ($index + 1) . ". " . htmlspecialchars($q["question"]) ?></p>

                    <?php foreach ($q["options"] as $option): ?>
                        <label>
                            <input
                                type="radio"
                                name="q<?= $index ?>"
                                value="<?= htmlspecialchars($option) ?>">
                            <?= htmlspecialchars($option) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

            <?php endforeach; ?>
        </div>

        <div class="result" id="result"></div>

        <script>
        const answers = <?= json_encode(array_column($exercise["questions"], "answer"), JSON_UNESCAPED_UNICODE) ?>;

        function readStory() {
            const text = document.getElementById("story").innerText;
            const speech = new SpeechSynthesisUtterance(text);
            speech.lang = "en-US";
            speech.rate = 0.95;
            speechSynthesis.cancel();
            speechSynthesis.speak(speech);
        }

        function checkAnswers() {

            document.querySelectorAll("label").forEach(el => {
                el.classList.remove("correct", "wrong");
            });

            let score = 0;

            answers.forEach((correct, index) => {

                const radios   = document.querySelectorAll(`input[name="q${index}"]`);
                const selected = document.querySelector(`input[name="q${index}"]:checked`);

                radios.forEach(radio => {
                    const label = radio.parentElement;
                    if (radio.value === correct) {
                        label.classList.add("correct");
                    }
                    if (radio.checked && radio.value !== correct) {
                        label.classList.add("wrong");
                    }
                });

                if (selected && selected.value === correct) {
                    score++;
                }
            });

            const resultEl = document.getElementById("result");

            if (score === answers.length) {
                resultEl.innerHTML = "🎉 Perfect! Score: " + score + " / " + answers.length;
            } else if (score >= Math.ceil(answers.length / 2)) {
                resultEl.innerHTML = "👍 Good job! Score: " + score + " / " + answers.length;
            } else {
                resultEl.innerHTML = "📚 Keep practising! Score: " + score + " / " + answers.length;
            }
        }
        </script>

    <?php else: ?>

        <div class="placeholder">
            Click <strong>✨ Generate Exercise</strong> to create your first AI lesson.
        </div>

    <?php endif; ?>

</div>

</body>
</html>
