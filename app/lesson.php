<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// --- Base de données Neon ---
$db_host = 'ep-autumn-salad-adwou7x2-pooler.c-2.us-east-1.aws.neon.tech';
$db_port = '5432';
$db_name = 'veronica_db_login';
$db_user = 'neondb_owner';
$db_pass = 'npg_QolPDv5L9gVj';

try {
    $pdo = new PDO("pgsql:host=$db_host;port=$db_port;dbname=$db_name;sslmode=require", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur DB : " . $e->getMessage());
}

$username = $_SESSION['username'] ?? 'invité';

// --- Récupère le niveau ---
$stmt = $pdo->prepare("SELECT level FROM users WHERE username = :u");
$stmt->execute([':u' => $username]);
$user_level = $stmt->fetchColumn() ?: 'A1';

// --- Clé API Cohere ---
define('COHERE_API_KEY', 'Uw540GN865rNyiOs3VMnWhRaYQ97KAfudAHAnXzJ');

// --- Thèmes ---
$themes = [
    'articles' => ['title' => "Les articles (le, la, les)", 'desc' => "Les articles définissent le genre et le nombre d’un nom."],
    'etre_avoir' => ['title' => "Le présent des verbes être et avoir", 'desc' => "Les verbes être et avoir au présent."],
    'pronoms' => ['title' => "Les pronoms personnels", 'desc' => "Les pronoms remplacent le nom dans la phrase."],
    'negation' => ['title' => "La négation simple (ne...pas)", 'desc' => "La négation permet de dire le contraire."]
];

$themeKey = $_GET['theme'] ?? 'articles';
$title = $themes[$themeKey]['title'] ?? "Thème inconnu";
$intro = $themes[$themeKey]['desc'] ?? "Leçon en préparation.";

// --- Génération exercices Cohere ---
function getPromptForTheme($themeKey, $title, $level) {
    return "Tu es un professeur de français nommé Veronica AI. Génère 39 phrases à trous (niveau $level) sur le thème '$title'. 
    Réponds UNIQUEMENT avec un tableau JSON au format :
    [{\"question\":\"__ phrase\",\"answer\":\"mot\"}]";
}

function generateExercisesWithCohere($themeKey, $title, $level) {
    $api_url = "https://api.cohere.ai/v1/chat";
    $prompt = getPromptForTheme($themeKey, $title, $level);

    $payload = [
        "model" => "command-a-vision-07-2025",
        "message" => $prompt,
        "temperature" => 0.4,
        "max_tokens" => 4000
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . COHERE_API_KEY,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($resp, true);
    $text = $result['text'] ?? '';

    if (preg_match('/\[.*\]/s', $text, $m)) {
        $json = json_decode($m[0], true);
        if (is_array($json)) return array_slice($json, 0, 39);
    }
    return false;
}

// --- Charge ou génère exercices ---
if (!isset($_SESSION['exercises'][$themeKey])) {
    $ex = generateExercisesWithCohere($themeKey, $title, $user_level);
    if (!$ex) {
        $ex = [
            ['question' => '__ chat dort sur le canapé.', 'answer' => 'Le'],
            ['question' => '__ maison est grande.', 'answer' => 'La'],
            ['question' => '__ élèves étudient le français.', 'answer' => 'Les']
        ];
    }
    $_SESSION['exercises'][$themeKey] = $ex;
}
$exercises = $_SESSION['exercises'][$themeKey];

// --- Soumission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['user_answers'] = $_POST['answers'] ?? [];
    header("Location: resultat.php?theme=" . urlencode($themeKey));
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title) ?> — Veronica AI</title>
<style>
body{font-family:sans-serif;background:#f4f4f4;padding:30px;}
.container{max-width:900px;margin:auto;background:white;padding:30px;border-radius:10px;}
.exercise{background:#f9fafb;padding:15px;margin:10px 0;border-radius:8px;}
input{width:100%;padding:10px;border-radius:8px;border:1px solid #ccc;}
button{padding:12px 20px;border:none;background:#4f46e5;color:white;border-radius:8px;cursor:pointer;}
</style>
</head>
<body>
<div class="container">
    <h1><?= htmlspecialchars($title) ?></h1>
    <p><?= htmlspecialchars($intro) ?></p>

    <form method="post">
        <?php foreach ($exercises as $i => $ex): ?>
            <div class="exercise">
                <strong><?= ($i+1) ?>.</strong> <?= htmlspecialchars($ex['question']) ?><br>
                <input type="text" name="answers[<?= $i ?>]" placeholder="Ta réponse...">
            </div>
        <?php endforeach; ?>
        <button type="submit">✅ Soumettre</button>
    </form>
</div>
</body>
</html>


