 <?php
// resultat.php - page de résultats et explications IA
ob_start();

// Session start (sécurisé)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'cookie_samesite' => 'Lax',
    ]);
}

// Si l'utilisateur n'est pas connecté -> rediriger vers la page de connexion
if (empty($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

// Paramètres API Cohere (remplace par ta clé si nécessaire)
define('COHERE_API_KEY', 'Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ');

// Récupération des données stockées en session par lesson.php
$theme = $_GET['theme'] ?? ($_SESSION['current_theme'] ?? 'articles');
$exercises = $_SESSION['exercises'][$theme] ?? [];
$userAnswers = $_SESSION['user_answers'] ?? [];

// Vérifications
if (!is_array($exercises) || count($exercises) === 0) {
    // Pas d'exercices : afficher message utile
    http_response_code(400);
    echo "<h3 style='color:red;'>❌ Aucun exercice trouvé pour le thème sélectionné. Retourne sur la page des leçons.</h3>";
    exit;
}

// Calcul du score et collecte des erreurs
$results = [];
$score = 0;
$mistakes = [];

foreach ($exercises as $i => $ex) {
    $question = isset($ex['question']) ? (string)$ex['question'] : '';
    $correct = isset($ex['answer']) ? trim((string)$ex['answer']) : '';
    $user = isset($userAnswers[$i]) ? trim((string)$userAnswers[$i]) : '';

    // comparaison insensible à la casse, en normalisant les apostrophes / espaces
    $normalize = function($s) {
        $s = mb_strtolower($s, 'UTF-8');
        $s = str_replace(["’", "‘", "`"], "'", $s);
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return $s;
    };

    $isCorrect = ($normalize($user) === $normalize($correct));

    if ($isCorrect) {
        $results[] = ['q' => $question, 'user' => $user, 'ok' => true];
        $score++;
    } else {
        $results[] = ['q' => $question, 'user' => $user, 'ok' => false, 'answer' => $correct];
        $mistakes[] = ['question' => $question, 'user' => $user, 'answer' => $correct];
    }
}

// Option : seuil de validation. Tu as demandé 20/39 précédemment.
// Si tu veux un pourcentage, remplace 20 par round(count($exercises)*0.6) etc.
$validation_threshold = 20;
$validated = ($score >= $validation_threshold);

// Préparer le prompt pour Cohere (explications) uniquement si erreurs
$explanationText = "Aucune erreur détectée. Excellent travail !";

if (!empty($mistakes)) {
    // Construire un prompt clair et court pour expliquer chaque erreur
    $promptParts = [];
    foreach ($mistakes as $m) {
        $promptParts[] = [
            'question' => $m['question'],
            'user_answer' => $m['user'],
            'correct_answer' => $m['answer']
        ];
    }

    $prompt = "Tu es Veronica AI, professeur de français bienveillant. Pour chaque élément du tableau JSON suivant, explique en 2 à 3 phrases de façon claire et naturelle :\n";
    $prompt .= json_encode($promptParts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $prompt .= "\nPour chaque erreur :\n1) Pourquoi la réponse correcte est celle indiquée\n2) Quelle est l'erreur faite par l'élève\n3) Une règle simple à retenir et un exemple court.\nRéponds en texte naturel (pas de JSON), séparé par paragraphes pour chaque erreur.";

    // Appel API Cohere
    $payload = [
        "model" => "command-a-vision-07-2025",
        "message" => $prompt,
        "temperature" => 0.5,
        "max_tokens" => 800
    ];

    $ch = curl_init("https://api.cohere.ai/v1/chat");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . COHERE_API_KEY,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        $explanationText = "⚠️ Impossible de contacter le service d'explication (cURL error). Réessaie plus tard.";
        error_log("Cohere cURL error: " . $curlErr);
    } else {
        $decoded = json_decode($resp, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['text']) && trim($decoded['text']) !== '') {
            $explanationText = trim($decoded['text']);
        } else {
            // Tentative : parfois la clé peut être 'response' ou autre - on affiche le debug minimal
            if (isset($decoded['response'])) {
                $explanationText = is_string($decoded['response']) ? $decoded['response'] : json_encode($decoded['response'], JSON_UNESCAPED_UNICODE);
            } else {
                $explanationText = "⚠️ L'IA n'a pas renvoyé d'explication lisible pour le moment.";
                error_log("Cohere unexpected response (HTTP $httpCode): " . substr($resp ?? '', 0, 1000));
            }
        }
    }
}

// Récupérer username pour affichage sécurisé
$username_display = htmlspecialchars((string)($_SESSION['username'] ?? 'invité'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$total = count($exercises);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Résultats — Veronica AI</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
    body{font-family:"Poppins",sans-serif;background:#f4f7fb;padding:20px;color:#1e293b;}
    .container{max-width:960px;margin:20px auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 6px 30px rgba(2,6,23,0.08);}
    h1{margin:0 0 8px;}
    .summary{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin:12px 0 18px;}
    .badge{background:linear-gradient(135deg,#4f46e5,#6366f1);color:#fff;padding:8px 12px;border-radius:999px;font-weight:700;}
    .correct{color:#16a34a;}
    .wrong{color:#dc2626;}
    .list{margin:14px 0;padding-left:18px;}
    .explanation{background:#f8fafc;padding:14px;border-radius:8px;border-left:4px solid #c7d2fe;margin-top:16px;white-space:pre-wrap;}
    a.button{display:inline-block;margin-top:18px;background:linear-gradient(135deg,#4f46e5,#6366f1);color:white;padding:10px 16px;border-radius:10px;text-decoration:none;font-weight:700;}
</style>
</head>
<body>
<div class="container">
    <h1>Résultats du thème « <?= htmlspecialchars($theme, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> »</h1>
    <div class="summary">
        <div>👋 Bonjour <strong><?= $username_display ?></strong></div>
        <div class="badge"><?= $score ?> / <?= $total ?></div>
        <div style="margin-left:auto;">
            <?php if ($validated): ?>
                <span style="background:#d1fae5;color:#065f46;padding:8px 12px;border-radius:999px;font-weight:700;">🎉 Validé (seuil <?= $validation_threshold ?>)</span>
            <?php else: ?>
                <span style="background:#fef3c7;color:#92400e;padding:8px 12px;border-radius:999px;font-weight:700;">⚠️ À améliorer</span>
            <?php endif; ?>
        </div>
    </div>

    <h3>✅ Réponses correctes</h3>
    <ul class="list">
        <?php foreach ($results as $r): if ($r['ok']): ?>
            <li class="correct"><?= htmlspecialchars($r['q'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> — <strong><?= htmlspecialchars($r['user'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong></li>
        <?php endif; endforeach; ?>
    </ul>

    <h3>❌ Réponses incorrectes</h3>
    <ul class="list">
        <?php foreach ($results as $r): if (!$r['ok']): ?>
            <li class="wrong">
                <?= htmlspecialchars($r['q'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><br>
                Ta réponse : <strong><?= htmlspecialchars($r['user'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?: '(vide)' ?></strong><br>
                Bonne réponse : <strong><?= htmlspecialchars($r['answer'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
            </li>
        <?php endif; endforeach; ?>
    </ul>

    <div class="explanation">
        <h4 style="margin-top:0;">🧠 Explications de Veronica AI</h4>
        <?= nl2br(htmlspecialchars($explanationText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?>
    </div>

    <a class="button" href="dashboard.php">⬅ Retour au tableau de bord</a>
</div>
</body>
</html>
<?php
ob_end_flush();



