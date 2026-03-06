<?php // ============================================================
//  music.php — My Music Studio
//  - Chaque user a son interface isolée (localStorage + IndexedDB par user_id)
//  - Sync automatique sur DB : projects, music_tracks, user_sessions
//  - Admin : panneau complet de tout ce qui a été créé
// ============================================================
session_start();
require_once __DIR__ . '/db.php';

// ── Protection ──────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// ── Logout ──────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    try {
        $pdo = getDB();
        $pdo->prepare('DELETE FROM user_sessions WHERE user_id = :uid')
            ->execute([':uid' => $_SESSION['user_id']]);
    } catch (PDOException $e) {}
    session_destroy();
    header('Location: index.php?logout=1');
    exit;
}

// ── Charge les données user ────────────────────────────────
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT id, username, email, role, is_active FROM users
          WHERE id = :id AND is_active = TRUE LIMIT 1'
    );
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) { session_destroy(); header('Location: index.php'); exit; }
} catch (PDOException $e) {
    die('DB Error: ' . $e->getMessage());
}

// ── Enregistre/rafraîchit la session dans user_sessions ────
try {
    $sessId = session_id();
    $ip     = substr($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
    $ua     = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $exp    = date('Y-m-d H:i:s', time() + 86400 * 7);
    $pdo->prepare(
        "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, expires_at)
         VALUES (:sid, :uid, :ip, :ua, :exp)
         ON CONFLICT (id) DO UPDATE SET last_activity = NOW(), expires_at = :exp2"
    )->execute([':sid'=>$sessId,':uid'=>$user['id'],':ip'=>$ip,':ua'=>$ua,':exp'=>$exp,':exp2'=>$exp]);
} catch (PDOException $e) {}

// ════════════════════════════════════════════════════════════
//  API AJAX — appelée depuis le JS avec fetch()
// ════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'];
    $uid    = (int)$_SESSION['user_id'];

    try {
        $pdo = getDB();

        // ── save_project ─────────────────────────────────────
        if ($action === 'save_project') {
            $d       = json_decode(file_get_contents('php://input'), true);
            $name    = substr(trim($d['name']     ?? 'Progetto'), 0, 100);
            $localId = substr(trim($d['local_id'] ?? ''),         0, 120);
            $did     = substr(trim($d['device_id']   ?? ''), 0, 120);
            $dname   = substr(trim($d['device_name'] ?? ''), 0, 100);

            $pdo->prepare(
                "UPDATE users SET
                    updated_at  = NOW(),
                    device_id   = COALESCE(:did, device_id),
                    device_name = COALESCE(:dn,  device_name)
                 WHERE id = :uid"
            )->execute([':uid'=>$uid, ':did'=>$did?:null, ':dn'=>$dname?:null]);

            $ex = $pdo->prepare(
                "SELECT id FROM projects WHERE user_id=:uid AND description=:lid LIMIT 1"
            );
            $ex->execute([':uid'=>$uid, ':lid'=>'local:'.$localId]);
            $row = $ex->fetch();

            if ($row) {
                $pdo->prepare("UPDATE projects SET name=:n, updated_at=NOW() WHERE id=:id")
                    ->execute([':n'=>$name, ':id'=>$row['id']]);
                echo json_encode(['ok'=>true, 'project_id'=>$row['id']]);
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO projects (user_id, name, description)
                     VALUES (:uid,:name,:desc) RETURNING id"
                );
                $ins->execute([':uid'=>$uid, ':name'=>$name, ':desc'=>'local:'.$localId]);
                echo json_encode(['ok'=>true, 'project_id'=>$ins->fetchColumn()]);
            }
            exit;
        }

        // ── save_track ───────────────────────────────────────
        if ($action === 'save_track') {
            $d = json_decode(file_get_contents('php://input'), true);
            $title    = substr(trim($d['title']       ?? ''), 0, 200);
            $artist   = substr(trim($d['artist']      ?? ''), 0, 200);
            $idbId    = substr(trim($d['local_idb_id']?? ''), 0, 200);
            $projDbId = isset($d['project_id']) ? (int)$d['project_id'] : null;
            $lang     = substr(trim($d['language']    ?? ''), 0, 30);
            $ctype    = in_array($d['cover_type'] ??'', ['upload','ai'])
                ? $d['cover_type']  : 'upload';
            $lmode    = in_array($d['lyrics_mode'] ??'', ['whisper','manual','none'])
                ? $d['lyrics_mode'] : 'none';
            $did      = substr(trim($d['device_id']   ?? ''), 0, 120);
            $dname    = substr(trim($d['device_name'] ?? ''), 0, 100);
            $sz       = isset($d['size_bytes'])  ? (int)$d['size_bytes']    : null;
            $dur      = isset($d['duration_s'])  ? (float)$d['duration_s'] : null;

            if (!$title || !$idbId) {
                echo json_encode(['ok'=>false,'error'=>'title and local_idb_id required']); exit;
            }

            $ins = $pdo->prepare(
                "INSERT INTO music_tracks
                   (user_id, project_id, title, artist, language,
                    cover_type_val, lyrics_mode_val, storage_type_val,
                    local_idb_id, device_id, device_name,
                    video_size_bytes, video_duration_s,
                    is_synced, synced_at, has_subtitles)
                 VALUES
                   (:uid,:pid,:title,:artist,:lang,
                    :ct,:lm,'both',
                    :iid,:did,:dn,
                    :sz,:dur,
                    TRUE,NOW(),:hs)
                 ON CONFLICT (local_idb_id, user_id) DO UPDATE SET
                   title=EXCLUDED.title, artist=EXCLUDED.artist,
                   project_id=EXCLUDED.project_id,
                   storage_type_val='both', is_synced=TRUE,
                   synced_at=NOW(), updated_at=NOW()
                 RETURNING id"
            );
            $ins->execute([
                ':uid'=>$uid,   ':pid'=>$projDbId, ':title'=>$title,
                ':artist'=>$artist, ':lang'=>$lang,
                ':ct'=>$ctype,  ':lm'=>$lmode,
                ':iid'=>$idbId, ':did'=>$did?:null, ':dn'=>$dname?:null,
                ':sz'=>$sz,     ':dur'=>$dur,
                ':hs'=>($lmode!=='none'),
            ]);
            $trackId = $ins->fetchColumn();

            $pdo->prepare(
                "UPDATE users SET
                    updated_at  = NOW(),
                    device_id   = COALESCE(:did, device_id),
                    device_name = COALESCE(:dn,  device_name)
                 WHERE id = :uid"
            )->execute([':uid'=>$uid, ':did'=>$did?:null, ':dn'=>$dname?:null]);

            echo json_encode(['ok'=>true,'track_id'=>$trackId]);
            exit;
        }

        // ── admin_data (admin only) ──────────────────────────
        if ($action === 'admin_data') {
            if ($user['role'] !== 'admin') {
                echo json_encode(['ok'=>false,'error'=>'Access denied']); exit;
            }
            $stats  = $pdo->query("SELECT * FROM v_admin_stats")->fetch();
            $tracks = $pdo->query(
                "SELECT mt.id, mt.title, mt.artist, mt.storage_type_val AS storage_type,
                        mt.device_name, mt.video_size_bytes, mt.created_at,
                        u.username, u.email, p.name AS project_name
                   FROM music_tracks mt
                   JOIN users u ON u.id = mt.user_id
                   LEFT JOIN projects p ON p.id = mt.project_id
                  WHERE mt.is_deleted = FALSE
                  ORDER BY mt.created_at DESC LIMIT 300"
            )->fetchAll();
            $users = $pdo->query(
                "SELECT u.id, u.username, u.email, u.role, u.is_active,
                        u.last_login_at, u.created_at,
                        COUNT(DISTINCT p.id)  AS project_count,
                        COUNT(DISTINCT mt.id) AS track_count,
                        COALESCE(SUM(mt.video_size_bytes),0) AS total_bytes
                   FROM users u
                   LEFT JOIN projects p  ON p.user_id  = u.id
                   LEFT JOIN music_tracks mt ON mt.user_id = u.id AND mt.is_deleted=FALSE
                  GROUP BY u.id ORDER BY track_count DESC"
            )->fetchAll();
            echo json_encode(['ok'=>true,'stats'=>$stats,'tracks'=>$tracks,'users'=>$users]);
            exit;
        }

        echo json_encode(['ok'=>false,'error'=>'Unknown action']);

    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>🎶 My Music Studio</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
@import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600&display=swap');
:root{
  --red:#d6004c;--purple:#7b1fa2;--bg:#0e0e0e;
  --card:#191919;--border:#2a2a2a;--green:#4caf50;--gold:#f0a500;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:#fff;min-height:100vh;}

/* ── HEADER ── */
header{background:linear-gradient(135deg,var(--red),var(--purple));text-align:center;padding:52px 20px 44px;position:relative;overflow:hidden;}
header::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 60% 40%,rgba(255,255,255,.1) 0%,transparent 65%);}
header h1{font-family:'Bebas Neue',sans-serif;font-size:3.8rem;letter-spacing:4px;position:relative;}
header p{margin-top:8px;font-size:.98rem;color:rgba(255,255,255,.75);letter-spacing:1px;position:relative;}

/* User chip */
.user-chip{position:absolute;top:14px;right:16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;}
.user-avatar{width:34px;height:34px;border-radius:50%;background:rgba(0,0,0,.4);border:2px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:.95rem;flex-shrink:0;}
.user-info{display:flex;flex-direction:column;align-items:flex-end;}
.user-name{font-size:.8rem;font-weight:600;color:#fff;line-height:1.2;}
.user-role{font-size:.66rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.7px;}
.btn-header{padding:7px 13px;border-radius:20px;font-size:.78rem;font-weight:600;cursor:pointer;backdrop-filter:blur(6px);display:flex;align-items:center;gap:5px;transition:all .2s;text-decoration:none;border:none;}
.btn-exit{background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.25) !important;color:rgba(255,255,255,.85);}
.btn-exit:hover{background:rgba(0,0,0,.55);color:#fff;}
.btn-exit svg{width:13px;height:13px;}
.btn-admin{background:rgba(240,165,0,.2);border:1px solid rgba(240,165,0,.45) !important;color:var(--gold);}
.btn-admin:hover{background:rgba(240,165,0,.35);}

/* ── TABS ── */
.tabs-wrap{background:#111;border-bottom:2px solid var(--border);position:sticky;top:0;z-index:100;}
.tabs-inner{max-width:960px;margin:auto;display:flex;align-items:stretch;padding:0 20px;overflow-x:auto;}
.tab-btn{padding:0 20px;height:50px;font-family:'DM Sans',sans-serif;font-size:.86rem;font-weight:600;color:#555;border:none;background:transparent;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .2s;white-space:nowrap;display:flex;align-items:center;gap:7px;flex-shrink:0;}
.tab-btn:hover{color:#ccc;}
.tab-btn.active{color:#fff;border-bottom-color:var(--red);}
.tab-dot{width:7px;height:7px;border-radius:50%;background:#333;flex-shrink:0;}
.tab-btn.active .tab-dot{background:var(--red);}
.tab-sep{width:1px;background:var(--border);margin:12px 0;flex-shrink:0;}
.tab-new{margin-left:12px;align-self:center;flex-shrink:0;padding:7px 16px;background:linear-gradient(135deg,var(--red),var(--purple));color:#fff;border:none;border-radius:20px;font-family:'Bebas Neue',sans-serif;font-size:.88rem;letter-spacing:1px;cursor:pointer;transition:opacity .2s;white-space:nowrap;}
.tab-new:hover{opacity:.85;}

.container{max-width:960px;margin:auto;padding:28px 20px 60px;}

/* ── PROJECT BAR ── */
.project-bar{display:flex;align-items:center;gap:12px;margin-bottom:22px;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px 20px;}
.project-bar-icon{font-size:1.6rem;flex-shrink:0;}
.project-name-input{background:transparent;border:none;border-bottom:2px solid transparent;color:#fff;font-family:'Bebas Neue',sans-serif;font-size:1.45rem;letter-spacing:2px;outline:none;flex:1;min-width:160px;padding:2px 4px;transition:border-color .2s;}
.project-name-input:focus{border-bottom-color:var(--red);}
.btn-del-proj{background:#1a0a0a;border:1px solid #3a0a0a;color:#f44336;padding:7px 14px;border-radius:8px;font-size:.76rem;font-weight:600;cursor:pointer;transition:opacity .2s;white-space:nowrap;}
.btn-del-proj:hover{opacity:.75;}
.sync-badge{font-size:.7rem;padding:3px 10px;border-radius:20px;display:none;}
.sync-badge.ok{color:var(--green);background:#0d1a0d;border:1px solid #1e3a1e;}
.sync-badge.err{color:#f44336;background:#1a0808;border:1px solid #5a1a1a;}

/* ── MODEL BANNER ── */
.model-banner{background:#0d1a0d;border:1px solid #1e3a1e;border-radius:14px;padding:20px 22px;margin-bottom:26px;}
.model-top{display:flex;align-items:flex-start;gap:14px;flex-wrap:wrap;}
.model-icon{font-size:2rem;flex-shrink:0;margin-top:2px;}
.model-body{flex:1;min-width:180px;}
.model-title{font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:1px;color:var(--green);margin-bottom:4px;}
.model-desc{font-size:.8rem;color:#666;line-height:1.6;}
.model-desc b{color:#999;}
.status-pill{display:inline-flex;align-items:center;gap:7px;padding:6px 14px;border-radius:20px;background:#111;border:1px solid var(--border);font-size:.78rem;color:#666;transition:all .3s;margin-top:10px;}
.status-pill .dot{width:8px;height:8px;border-radius:50%;background:#444;flex-shrink:0;}
.status-pill.loading{color:#ffb300;border-color:#3a2a00;}
.status-pill.loading .dot{background:#ffb300;animation:blink 1s infinite;}
.status-pill.ready{color:var(--green);border-color:#1e3a1e;}
.status-pill.ready .dot{background:var(--green);}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
.model-dl{display:none;margin-top:16px;background:#0a0a0a;border:1px solid var(--border);border-radius:10px;padding:14px 16px;}
.model-dl-header{display:flex;justify-content:space-between;font-size:.78rem;color:#888;margin-bottom:8px;}
.model-dl-track{width:100%;height:7px;background:#1a1a1a;border-radius:4px;overflow:hidden;margin-bottom:10px;}
.model-dl-fill{height:100%;width:0%;background:linear-gradient(90deg,var(--green),#81c784);border-radius:4px;transition:width .4s ease;}
.model-dl-files{max-height:90px;overflow-y:auto;font-size:.74rem;color:#4a4a4a;font-family:monospace;line-height:1.7;}
.model-dl-files .f-done{color:var(--green);}
.model-dl-files .f-active{color:#ffb300;}

/* ── UPLOAD BOX ── */
.upload-box{background:var(--card);border:1px solid var(--border);padding:32px;border-radius:18px;margin-bottom:44px;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
.field{display:flex;flex-direction:column;gap:7px;}
.field-label{font-size:.74rem;letter-spacing:1.2px;text-transform:uppercase;color:#777;font-weight:600;}
input[type="text"]{background:#111;border:1px solid var(--border);color:#fff;padding:13px 15px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;transition:border-color .2s;width:100%;}
input[type="text"]:focus{border-color:var(--red);}
.lang-select{background:#111;border:1px solid var(--border);color:#fff;padding:12px 15px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.9rem;outline:none;width:100%;cursor:pointer;}
.lang-select:focus{border-color:var(--purple);}
.file-input-wrapper{position:relative;overflow:hidden;}
.file-btn{background:#111;border:1px dashed #3a3a3a;color:#888;padding:13px 15px;border-radius:10px;cursor:pointer;font-size:.9rem;text-align:center;display:block;width:100%;transition:border-color .2s,color .2s;}
.file-btn:hover{border-color:var(--red);color:#fff;}
input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.file-name{font-size:.76rem;color:#555;margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cover-tabs{display:flex;gap:8px;margin-bottom:10px;}
.cover-tab{flex:1;padding:9px;border-radius:8px;border:1px solid var(--border);background:#111;color:#666;font-family:'DM Sans',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .2s;text-align:center;}
.cover-tab:hover{color:#ccc;border-color:#555;}
.cover-tab.active{background:linear-gradient(135deg,var(--red),var(--purple));color:#fff;border-color:transparent;}
.ai-cover-box{background:#0d0a1a;border:1px solid #2a1a4a;border-radius:12px;padding:16px;}
.cover-prompt-area{width:100%;min-height:80px;background:#111;border:1px solid #3a3a3a;color:#fff;padding:12px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.88rem;outline:none;resize:vertical;line-height:1.6;transition:border-color .2s;margin-bottom:10px;}
.cover-prompt-area:focus{border-color:#9090ff;}
.prompt-hint{font-size:.72rem;color:#444;margin-bottom:10px;line-height:1.5;}
.prompt-hint b{color:#666;}
.gen-cover-btn{width:100%;padding:12px;background:linear-gradient(135deg,#7b1fa2,#4a148c);color:#fff;border:none;border-radius:10px;font-family:'Bebas Neue',sans-serif;font-size:1.1rem;letter-spacing:1.5px;cursor:pointer;transition:opacity .2s,transform .15s;display:flex;align-items:center;justify-content:center;gap:8px;}
.gen-cover-btn:hover:not(:disabled){opacity:.88;transform:translateY(-1px);}
.gen-cover-btn:disabled{background:#2a2a2a;color:#555;cursor:not-allowed;}
.gen-status{font-size:.76rem;color:#ffb300;margin-top:8px;display:none;text-align:center;}
.gen-status.error{color:#f44336;}
.gen-status.ok{color:var(--green);}
.ai-cover-preview{display:none;margin-top:12px;border-radius:10px;overflow:hidden;border:1px solid #3a3a3a;position:relative;}
.ai-cover-preview img{width:100%;display:block;}
.ai-cover-badge{position:absolute;bottom:8px;left:8px;background:rgba(0,0,0,.75);color:var(--green);font-size:.7rem;padding:4px 10px;border-radius:20px;font-weight:600;}
.ai-cover-retry{position:absolute;top:8px;right:8px;background:rgba(0,0,0,.75);color:#fff;border:none;font-size:.72rem;padding:5px 10px;border-radius:8px;cursor:pointer;font-family:'DM Sans',sans-serif;}
.ai-cover-retry:hover{background:rgba(214,0,76,.8);}
.toggle-row{display:flex;align-items:center;gap:12px;margin:10px 0 0;background:#111;border:1px solid var(--border);border-radius:10px;padding:13px 16px;}
.toggle-row input[type="checkbox"]{width:18px;height:18px;accent-color:var(--green);cursor:pointer;flex-shrink:0;}
.toggle-row label{font-size:.92rem;color:#ccc;cursor:pointer;flex:1;}
.badge-free{background:#1a3a1a;color:var(--green);border:1px solid #2a5a2a;font-size:.66rem;padding:3px 9px;border-radius:20px;font-weight:700;letter-spacing:.5px;}
.badge-prec{background:#1a1a3a;color:#9090ff;border:1px solid #2a2a5a;font-size:.66rem;padding:3px 9px;border-radius:20px;font-weight:700;letter-spacing:.5px;}
#lyricsBox{display:none;margin-top:12px;background:#0d0d1a;border:1px solid #2a2a5a;border-radius:12px;padding:18px;}
#lyricsText{width:100%;min-height:180px;background:#111;border:1px solid #3a3a3a;color:#fff;padding:14px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.9rem;outline:none;resize:vertical;line-height:1.7;transition:border-color .2s;}
#lyricsText:focus{border-color:#9090ff;}
.offset-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:14px;}
.offset-row span{font-size:.78rem;color:#777;white-space:nowrap;}
.offset-row input[type=range]{flex:1;accent-color:#9090ff;min-width:120px;}
#offsetVal{font-size:.82rem;color:#9090ff;width:44px;text-align:right;}
.create-btn{display:block;width:100%;margin-top:22px;background:linear-gradient(135deg,var(--red),var(--purple));color:#fff;border:none;padding:16px;border-radius:50px;font-family:'Bebas Neue',sans-serif;font-size:1.35rem;letter-spacing:2px;cursor:pointer;transition:opacity .2s,transform .15s;}
.create-btn:hover:not(:disabled){opacity:.88;transform:translateY(-2px);}
.create-btn:disabled{background:#2a2a2a;color:#555;cursor:not-allowed;}
.progress{display:none;margin-top:22px;}
.progress-header{display:flex;justify-content:space-between;font-size:.82rem;color:#888;margin-bottom:9px;}
.progress-track{width:100%;height:9px;background:#222;border-radius:5px;overflow:hidden;}
.progress-fill{height:100%;width:0%;background:linear-gradient(90deg,var(--red),var(--purple));border-radius:5px;transition:width .35s ease;}
.progress-sub{text-align:center;margin-top:10px;font-size:.8rem;color:#555;}
.trans-log{display:none;margin-top:14px;background:#0a0a0a;border:1px solid var(--border);border-radius:10px;padding:12px 16px;max-height:130px;overflow-y:auto;font-size:.78rem;color:var(--green);font-family:monospace;line-height:1.7;}

/* ── GALLERY ── */
.gallery{margin-top:6px;}
.gallery-title{font-family:'Bebas Neue',sans-serif;font-size:1.9rem;letter-spacing:2px;margin-bottom:22px;}
.video-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(278px,1fr));gap:22px;}
.video-card{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:transform .2s,box-shadow .2s;}
.video-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(214,0,76,.15);}
.video-card video{width:100%;height:176px;object-fit:cover;display:block;}
.video-meta{padding:13px 15px 6px;line-height:1.5;}
.video-meta strong{font-size:.97rem;display:block;}
.video-meta span{font-size:.83rem;color:#666;}
.db-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--green);margin-left:6px;vertical-align:middle;}
.video-actions{display:flex;gap:9px;padding:10px 15px 15px;}
.btn-dl,.btn-del{flex:1;border-radius:9px;text-align:center;padding:9px 6px;font-size:.82rem;font-family:'DM Sans',sans-serif;font-weight:600;cursor:pointer;transition:opacity .15s;border:none;display:flex;align-items:center;justify-content:center;gap:5px;text-decoration:none;}
.btn-dl{background:var(--red);color:#fff;}
.btn-del{background:#252525;color:#888;}
.btn-dl:hover,.btn-del:hover{opacity:.78;}
.empty{text-align:center;color:#3a3a3a;padding:80px 20px;font-size:1.05rem;}
.empty-icon{font-size:2.8rem;display:block;margin-bottom:12px;}

/* ── ADMIN OVERLAY ── */
.admin-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.97);z-index:998;overflow-y:auto;}
.admin-overlay.open{display:block;}
.admin-panel{max-width:1140px;margin:0 auto;padding:30px 24px 60px;}
.admin-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;border-bottom:1px solid var(--border);padding-bottom:20px;}
.admin-title{font-family:'Bebas Neue',sans-serif;font-size:2.2rem;letter-spacing:3px;color:var(--gold);}
.admin-close{background:#1a1a1a;border:1px solid #333;color:#aaa;padding:9px 18px;border-radius:10px;cursor:pointer;font-size:.84rem;font-weight:600;transition:all .2s;}
.admin-close:hover{background:#2a2a2a;color:#fff;}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:28px;}
.stat-card{background:#111;border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;}
.stat-val{font-family:'Bebas Neue',sans-serif;font-size:2rem;color:var(--gold);}
.stat-lbl{font-size:.68rem;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}
.admin-sec{font-family:'Bebas Neue',sans-serif;font-size:1.25rem;letter-spacing:2px;color:#888;margin:28px 0 12px;}
.admin-search{background:#111;border:1px solid var(--border);color:#fff;padding:9px 14px;border-radius:10px;font-size:.88rem;outline:none;width:100%;max-width:340px;margin-bottom:12px;transition:border-color .2s;}
.admin-search:focus{border-color:var(--gold);}
.tbl{width:100%;border-collapse:collapse;font-size:.81rem;}
.tbl th{text-align:left;padding:9px 11px;background:#111;color:#555;font-size:.68rem;text-transform:uppercase;letter-spacing:.7px;border-bottom:1px solid var(--border);}
.tbl td{padding:9px 11px;border-bottom:1px solid #161616;vertical-align:middle;color:#ccc;}
.tbl tr:hover td{background:rgba(255,255,255,.02);}
.chip{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.66rem;font-weight:700;}
.c-admin{background:#1a0a2a;color:#ce93d8;border:1px solid #4a1a6a;}
.c-user{background:#0d1a2a;color:#7090ff;border:1px solid #1a2a5a;}
.c-both{background:#0d1a0d;color:var(--green);border:1px solid #1e3a1e;}
.c-local{background:#1a1a0d;color:#ffb300;border:1px solid #3a3a1a;}
.c-server{background:#0d0d1a;color:#9090ff;border:1px solid #2a2a5a;}
.admin-loading{text-align:center;padding:60px;color:#444;font-size:1.1rem;}

/* ── MODAL ── */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:999;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-box{background:#191919;border:1px solid #333;border-radius:20px;padding:34px;width:92%;max-width:430px;animation:popIn .22s ease;}
@keyframes popIn{from{opacity:0;transform:translateY(20px) scale(.96)}to{opacity:1;transform:none}}
.modal-box h2{font-family:'Bebas Neue',sans-serif;font-size:1.6rem;letter-spacing:2px;margin-bottom:6px;}
.modal-box p{font-size:.84rem;color:#666;margin-bottom:20px;line-height:1.5;}
.modal-input{width:100%;background:#111;border:1px solid var(--border);color:#fff;padding:13px 15px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.95rem;outline:none;transition:border-color .2s;margin-bottom:18px;}
.modal-input:focus{border-color:var(--red);}
.modal-actions{display:flex;gap:10px;}
.modal-actions button{flex:1;padding:13px;border-radius:10px;border:none;font-family:'DM Sans',sans-serif;font-weight:600;font-size:.9rem;cursor:pointer;transition:opacity .2s;}
.btn-modal-cancel{background:#252525;color:#888;}
.btn-modal-cancel:hover{opacity:.8;}
.btn-modal-ok{background:linear-gradient(135deg,var(--red),var(--purple));color:#fff;}
.btn-modal-ok:hover{opacity:.88;}

footer{text-align:center;padding:28px;color:#2a2a2a;font-size:.8rem;border-top:1px solid #161616;}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
@media(max-width:580px){.row{grid-template-columns:1fr;}header h1{font-size:2.6rem;}.upload-box{padding:22px 18px;}.user-info{display:none;}}
</style>
</head>
<body>

<header>
  <h1>🎶 My Music Studio</h1>
  <p>Multi-Project · Whisper Base · AI Cover Generator · Free · In your browser</p>

  <div class="user-chip">
    <div class="user-info">
      <span class="user-name"><?= htmlspecialchars($user['username']) ?></span>
      <span class="user-role"><?= htmlspecialchars($user['role']) ?></span>
    </div>
    <div class="user-avatar"><?= strtoupper(mb_substr($user['username'],0,1)) ?></div>
    <?php if ($user['role'] === 'admin'): ?>
    <button class="btn-header btn-admin" onclick="openAdmin()">
      ⚙️ Admin
    </button>
    <?php endif; ?>
    <a class="btn-header btn-exit" href="music.php?logout=1">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Logout
    </a>
  </div>
</header>

<!-- TABS -->
<div class="tabs-wrap">
  <div class="tabs-inner" id="tabsInner">
    <button class="tab-new" id="btnNewProj">＋ New Project</button>
  </div>
</div>

<div class="container" id="appContent"></div>

<!-- ADMIN PANEL -->
<div class="admin-overlay" id="adminOverlay">
  <div class="admin-panel">
    <div class="admin-hdr">
      <span class="admin-title">⚙️ Admin Panel</span>
      <button class="admin-close" onclick="closeAdmin()">✕ Close</button>
    </div>
    <div id="adminContent"><div class="admin-loading">⏳ Loading…</div></div>
  </div>
</div>

<footer>© 2026 – My Music Studio · Whisper by OpenAI · Transformers.js by Hugging Face · Picasso AI by Muyumba</footer>

<!-- MODAL NEW PROJECT -->
<div class="modal-bg" id="modalBg">
  <div class="modal-box">
    <h2>🎵 New Project</h2>
    <p>Give your project a name. It will have its own separate video gallery.</p>
    <input class="modal-input" id="modalInput" type="text" placeholder="E.g.: My Album 2026…" maxlength="40">
    <div class="modal-actions">
      <button class="btn-modal-cancel" id="btnModalCancel">Cancel</button>
      <button class="btn-modal-ok" id="btnModalOk">🚀 Create Project</button>
    </div>
  </div>
</div>

<!-- PHP data → JS -->
<script>
const CURRENT_USER = {
  id:       <?= (int)$user['id'] ?>,
  username: <?= json_encode($user['username']) ?>,
  role:     <?= json_encode($user['role']) ?>
};
</script>

<script type="module">
'use strict';
import { pipeline, env }   from 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.1.0/dist/transformers.min.js';
env.allowLocalModels = false;
env.useBrowserCache  = true;

/* ══════════════════════════════════════════════════════════
   DEVICE ID
══════════════════════════════════════════════════════════ */
let DEVICE_ID = localStorage.getItem('mms_device_id');
if (!DEVICE_ID) {
  DEVICE_ID = 'dev_' + Math.random().toString(36).slice(2,10) + '_' + Date.now().toString(36);
  localStorage.setItem('mms_device_id', DEVICE_ID);
}
const ua = navigator.userAgent;
let bn = 'Browser';
if (ua.includes('Chrome') && !ua.includes('Edg')) bn='Chrome';
else if (ua.includes('Firefox')) bn='Firefox';
else if (ua.includes('Safari') && !ua.includes('Chrome')) bn='Safari';
else if (ua.includes('Edg')) bn='Edge';
let os = 'OS';
if (ua.includes('Windows')) os='Windows';
else if (ua.includes('Mac')) os='Mac';
else if (ua.includes('Android')) os='Android';
else if (ua.includes('iPhone')||ua.includes('iPad')) os='iOS';
else if (ua.includes('Linux')) os='Linux';
const DEVICE_NAME = bn + ' / ' + os;

/* ══════════════════════════════════════════════════════════
   PROJECTS — localStorage isolated per user
   Each user has their own key: mms_v3_u{id}
══════════════════════════════════════════════════════════ */
const LS_KEY = 'mms_v3_u' + CURRENT_USER.id;

function getProjects() {
  try {
    const r = localStorage.getItem(LS_KEY);
    if (r) { const l = JSON.parse(r); if (Array.isArray(l) && l.length) return l; }
  } catch(e) {}
  const def = [{ id:'proj_u'+CURRENT_USER.id+'_def', name:'Project 1', createdAt:Date.now(), dbId:null }];
  setProjects(def); return def;
}
function setProjects(l) { localStorage.setItem(LS_KEY, JSON.stringify(l)); }
function addProject(name) {
  const l = getProjects();
  const p = { id:'proj_u'+CURRENT_USER.id+'_'+Date.now(), name:name.trim()||'New Project', createdAt:Date.now(), dbId:null };
  l.push(p); setProjects(l); return p;
}
function removeProject(id) {
  let l = getProjects().filter(p => p.id !== id);
  if (!l.length) l = [{ id:'proj_u'+CURRENT_USER.id+'_'+Date.now(), name:'Project 1', createdAt:Date.now(), dbId:null }];
  setProjects(l); return l;
}
function renameProject(id, name) {
  const l = getProjects(); const p = l.find(p => p.id === id);
  if (p && name.trim()) { p.name = name.trim(); setProjects(l); }
}
function updateDbId(localId, dbId) {
  const l = getProjects(); const p = l.find(p => p.id === localId);
  if (p) { p.dbId = dbId; setProjects(l); }
}
let activeProjectId = getProjects()[0].id;

/* ══════════════════════════════════════════════════════════
   SYNC DB — project + track
══════════════════════════════════════════════════════════ */
async function syncProject(proj) {
  try {
    const r = await fetch('music.php?api=save_project', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ name: proj.name, local_id: proj.id, device_id: DEVICE_ID, device_name: DEVICE_NAME })
    });
    const d = await r.json();
    if (d.ok) { updateDbId(proj.id, d.project_id); return d.project_id; }
  } catch(e) { console.warn('syncProject:', e); }
  return null;
}

async function syncTrack(data) {
  try {
    const r = await fetch('music.php?api=save_track', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify(data)
    });
    const d = await r.json();
    if (d.ok) return d.track_id;
  } catch(e) { console.warn('syncTrack:', e); }
  return null;
}

/* ══════════════════════════════════════════════════════════
   AI COVER
══════════════════════════════════════════════════════════ */
let aiCoverBlob = null;
let coverMode   = 'upload';

window.switchCoverTab = function(mode) {
  coverMode = mode;
  document.getElementById('tabUpload')?.classList.toggle('active', mode==='upload');
  document.getElementById('tabGenerate')?.classList.toggle('active', mode==='generate');
  if (document.getElementById('coverUploadBox')) document.getElementById('coverUploadBox').style.display = mode==='upload' ? 'block' : 'none';
  if (document.getElementById('coverGenBox'))    document.getElementById('coverGenBox').style.display    = mode==='generate' ? 'block' : 'none';
};

window.generateCover = async function() {
  const prompt  = (document.getElementById('coverPrompt')?.value||'').trim();
  const negProm = (document.getElementById('coverNegPrompt')?.value||'').trim();
  if (!prompt) { alert('Please enter a description!'); return; }
  const btn=document.getElementById('genCoverBtn'), status=document.getElementById('genStatus');
  const preview=document.getElementById('aiCoverPreview'), img=document.getElementById('aiCoverImg');
  btn.disabled=true; btn.textContent='⏳ Generating…';
  status.style.display='block'; status.className='gen-status'; status.textContent='⏳ Connecting…';
  preview.style.display='none'; aiCoverBlob=null;
  try {
    if (!window.GradioClient) {
      await new Promise((res,rej) => {
        const s=document.createElement('script'); s.type='module';
        s.innerHTML=`import{Client}from"https://cdn.jsdelivr.net/npm/@gradio/client@1.6.0/dist/index.min.js";window.GradioClient=Client;window.dispatchEvent(new Event('gradio-ready'));`;
        window.addEventListener('gradio-ready',res,{once:true});
        setTimeout(()=>rej(new Error('Timeout')),15000); document.head.appendChild(s);
      });
    }
    const client = await window.GradioClient.connect("Muyumba/Picasso-Ai");
    status.textContent='⏳ Generating (30-60s)…';
    const result = await client.predict("/infer",{prompt,negative_prompt:negProm||"blurry,low quality,text,watermark",seed:0,randomize_seed:true,width:1024,height:1024,guidance_scale:0,num_inference_steps:4});
    let imgUrl=null;
    if (result?.data) { const d=Array.isArray(result.data)?result.data[0]:result.data; if(typeof d==='string')imgUrl=d; else if(d?.url)imgUrl=d.url; else if(d?.path)imgUrl=d.path; }
    if (!imgUrl) throw new Error('Invalid response format');
    if (imgUrl.startsWith('/')) imgUrl='https://muyumba-picasso-ai.hf.space'+imgUrl;
    const imgRes = await fetch(imgUrl);
    if (!imgRes.ok) throw new Error('Download failed');
    aiCoverBlob = await imgRes.blob();
    if (aiCoverBlob.size < 1000) throw new Error('Image too small');
    img.src=URL.createObjectURL(aiCoverBlob);
    preview.style.display='block'; status.className='gen-status ok'; status.textContent='✅ Cover ready!'; btn.textContent='🔄 Regenerate';
  } catch(err) {
    status.className='gen-status error'; status.textContent='❌ '+err.message; btn.textContent='✨ Generate Cover'; aiCoverBlob=null;
  } finally { btn.disabled=false; }
};

/* ══════════════════════════════════════════════════════════
   INDEXEDDB — isolated per user: MusicStudioDB_u{id}
══════════════════════════════════════════════════════════ */
let _db = null;
function getIDB() {
  if (_db) return Promise.resolve(_db);
  return new Promise((res,rej) => {
    const r = indexedDB.open('MusicStudioDB_u'+CURRENT_USER.id, 4);
    r.onupgradeneeded = e => {
      const d = e.target.result;
      if (!d.objectStoreNames.contains('videos'))
        d.createObjectStore('videos', {keyPath:'id'});
    };
    r.onsuccess = e => { _db=e.target.result; _db.onclose=()=>{_db=null;}; res(_db); };
    r.onerror   = e => rej(new Error('IndexedDB: '+e.target.error));
    r.onblocked = () => rej(new Error('IndexedDB blocked'));
  });
}
async function dbSave(blob, title, artist, projId, projDbId, lyricsMode) {
  const db = await getIDB();
  const idbId = 'vid_u'+CURRENT_USER.id+'_'+Date.now();
  return new Promise((res,rej) => {
    const tx = db.transaction('videos','readwrite');
    tx.objectStore('videos').put({ id:idbId, blob, title, artist, projId, date:Date.now(),
      projDbId:projDbId||null, device:DEVICE_NAME, lyricsMode:lyricsMode||'none', synced:false });
    tx.oncomplete = () => res(idbId);
    tx.onerror    = e => rej(new Error(e.target.error));
  });
}
async function dbGetByProject(projId) {
  const db = await getIDB();
  return new Promise((res,rej) => {
    const req = db.transaction('videos','readonly').objectStore('videos').getAll();
    req.onsuccess = e => res((e.target.result||[]).filter(v => v.projId === projId));
    req.onerror   = e => rej(e.target.error);
  });
}
async function dbMarkSynced(idbId) {
  const db = await getIDB();
  return new Promise((res,rej) => {
    const tx = db.transaction('videos','readwrite');
    const req = tx.objectStore('videos').get(idbId);
    req.onsuccess = e => { const v=e.target.result; if(!v){res();return;} v.synced=true; tx.objectStore('videos').put(v); };
    tx.oncomplete = ()=>res(); tx.onerror=e=>rej(e.target.error);
  });
}
async function dbDel(id) {
  const db = await getIDB();
  return new Promise((res,rej) => {
    const tx = db.transaction('videos','readwrite');
    tx.objectStore('videos').delete(id);
    tx.oncomplete=()=>res(); tx.onerror=e=>rej(e.target.error);
  });
}
async function dbDelByProject(projId) {
  const vs = await dbGetByProject(projId); if (!vs.length) return;
  const db = await getIDB();
  return new Promise((res,rej) => {
    const tx = db.transaction('videos','readwrite'); const st = tx.objectStore('videos');
    vs.forEach(v => st.delete(v.id)); tx.oncomplete=()=>res(); tx.onerror=e=>rej(e.target.error);
  });
}

/* ══════════════════════════════════════════════════════════
   WHISPER
══════════════════════════════════════════════════════════ */
const FILE_W={'encoder_model.onnx':30,'decoder_model_merged.onnx':20,'tokenizer.json':1,'config.json':1,'tokenizer_config.json':1,'preprocessor_config.json':1};
const TOT_W=Object.values(FILE_W).reduce((a,b)=>a+b,0);
let loadedW=0; const filesDone=new Set();
function safeId(n){return 'fr-'+n.replace(/[^a-z0-9]/gi,'_');}
function onProgress(p){
  const dlEl=document.getElementById('modelDl'),dlLbl=document.getElementById('dlLabel'),dlPct=document.getElementById('dlPct'),dlFill=document.getElementById('dlFill'),dlFiles=document.getElementById('dlFiles');
  if(!dlEl)return;
  if(p.status==='initiate'){dlEl.style.display='block';const id=safeId(p.file||'');if(!document.getElementById(id)){const d=document.createElement('div');d.id=id;d.className='f-active';d.textContent='⏳ '+(p.file||'');dlFiles.appendChild(d);dlFiles.scrollTop=dlFiles.scrollHeight;}}
  if(p.status==='progress'){const pct=p.progress?Math.floor(p.progress):0;const el=document.getElementById(safeId(p.file||''));if(el)el.textContent='⏳ '+(p.file||'')+' — '+pct+'%';const contrib=(pct/100)*(FILE_W[p.file||'']||0.5);const tot=Math.min(Math.floor(((loadedW+contrib)/TOT_W)*100),99);if(dlFill)dlFill.style.width=tot+'%';if(dlPct)dlPct.textContent=tot+'%';if(dlLbl)dlLbl.textContent='Downloading: '+(p.file||'')+' — '+pct+'%';setPill('loading','Downloading… '+tot+'%');}
  if(p.status==='done'){const f=p.file||'';if(!filesDone.has(f)){filesDone.add(f);loadedW+=FILE_W[f]||0.5;}const el=document.getElementById(safeId(f));if(el){el.className='f-done';el.textContent='✅ '+f;}}
}
let pipe=null, isLoading=false;
async function getModel(){
  if(pipe)return pipe;
  if(isLoading){await new Promise(res=>{const t=setInterval(()=>{if(!isLoading){clearInterval(t);res();}},200);});return pipe;}
  isLoading=true; setPill('loading','Loading Whisper Base…');
  pipe=await pipeline('automatic-speech-recognition','onnx-community/whisper-base',{dtype:{encoder_model:'fp32',decoder_model_merged:'q4'},progress_callback:onProgress});
  const dlFill=document.getElementById('dlFill'),dlPct=document.getElementById('dlPct'),dlLbl=document.getElementById('dlLabel');
  if(dlFill)dlFill.style.width='100%';if(dlPct)dlPct.textContent='100%';if(dlLbl)dlLbl.textContent='✅ Model ready — cached';
  setPill('ready','✅ Whisper Base ready (offline)'); isLoading=false; return pipe;
}
async function transcribeAll(audioFile,language){
  const model=await getModel(); logT('🎵 Reading audio file…');
  const arrayBuf=await audioFile.arrayBuffer();
  const actx=new (window.AudioContext||window.webkitAudioContext)({sampleRate:16000});
  const decoded=await actx.decodeAudioData(arrayBuf); await actx.close();
  const totalSec=decoded.duration,sr=decoded.sampleRate,channelData=decoded.getChannelData(0);
  logT('⏱ Duration: '+Math.floor(totalSec)+'s…');
  const CHUNK_SEC=28,STRIDE_SEC=4,CHUNK_SAMP=CHUNK_SEC*sr,STRIDE_SAMP=STRIDE_SEC*sr;
  const totalChunks=Math.ceil(totalSec/(CHUNK_SEC-STRIDE_SEC));
  const allSegs=[];let chunkStart=0,chunkIdx=0;
  while(chunkStart<channelData.length){
    chunkIdx++;const chunkEnd=Math.min(chunkStart+CHUNK_SAMP,channelData.length);
    const chunkData=channelData.slice(chunkStart,chunkEnd);const timeOff=chunkStart/sr;
    logT('🔄 Chunk '+chunkIdx+'/'+totalChunks+' — '+Math.floor(timeOff)+'s→'+Math.floor(timeOff+chunkData.length/sr)+'s');
    const opts={task:'transcribe',return_timestamps:true};if(language&&language!=='auto')opts.language=language;
    const result=await model(chunkData,opts);
    for(const c of (result.chunks||[])){
      const seg={start:(c.timestamp[0]??0)+timeOff,end:(c.timestamp[1]??((c.timestamp[0]??0)+3))+timeOff,text:(c.text||'').trim()};
      if(seg.text&&!allSegs.some(s=>Math.abs(s.start-seg.start)<1&&s.text===seg.text)){allSegs.push(seg);logT('📝 ['+seg.start.toFixed(1)+'s] '+seg.text);}
    }
    chunkStart+=CHUNK_SAMP-STRIDE_SAMP;if(channelData.length-chunkStart<sr*2)break;
  }
  allSegs.sort((a,b)=>a.start-b.start); logT('✅ '+allSegs.length+' segments'); return allSegs;
}
function syncLyricsWithTimings(whisperSegs,rawLyrics,userOffset){
  if(typeof userOffset!=='number'||isNaN(userOffset))userOffset=-0.3;
  function parseTimestamp(str){const m=str.match(/^\[(\d+):(\d{2})(?::(\d{2}))?\]\s*/);if(!m)return null;const h=m[3]?parseInt(m[1]):0,min=m[3]?parseInt(m[2]):parseInt(m[1]),sec=m[3]?parseInt(m[3]):parseInt(m[2]);return h*3600+min*60+sec;}
  const rawLines=rawLyrics.split('\n').map(l=>l.trim()).filter(l=>l.length>0);
  if(!rawLines.length||!whisperSegs.length)return whisperSegs;
  const parsed=rawLines.map(line=>{const ts=parseTimestamp(line);const text=ts!==null?line.replace(/^\[\d+:\d{2}(?::\d{2})?\]\s*/,'').trim():line;return{ts,text};}).filter(p=>p.text.length>0);
  const validSegs=whisperSegs.filter(s=>typeof s.start==='number'&&typeof s.end==='number'&&!isNaN(s.start)&&!isNaN(s.end)&&s.end>s.start);
  const totalDur=validSegs.length?validSegs[validSegs.length-1].end:180;
  const hasTimestamps=parsed.some(p=>p.ts!==null);const result=[];
  if(hasTimestamps){
    const wa=parsed.map((p,i)=>({text:p.text,start:p.ts!==null?p.ts:null,idx:i}));
    for(let i=0;i<wa.length;i++){if(wa[i].start!==null)continue;let prevTs=0,prevIdx=-1;for(let j=i-1;j>=0;j--){if(wa[j].start!==null){prevTs=wa[j].start;prevIdx=j;break;}}let nextTs=totalDur,nextIdx=wa.length;for(let j=i+1;j<wa.length;j++){if(wa[j].start!==null){nextTs=wa[j].start;nextIdx=j;break;}}const gc=nextIdx-prevIdx-1,pg=i-prevIdx,gd=nextTs-prevTs;wa[i].start=prevTs+(pg/gc)*gd;}
    for(let i=0;i<wa.length;i++){const st=wa[i].start;const nt=i<wa.length-1?wa[i+1].start:totalDur;result.push({start:Math.max(0,st+userOffset),end:Math.max(nt-0.1,st+1.2),text:wa[i].text});}
  }else{
    const N=parsed.length,sd=totalDur/N;
    for(let i=0;i<N;i++){const ts=i*sd,te=(i+1)*sd,tc=(i+0.5)*sd,mg=sd*0.3;let bs=null,bd=Infinity;for(const s of validSegs){if(s.start<ts-mg||s.start>te+mg)continue;const d=Math.abs(s.start-tc);if(d<bd){bd=d;bs=s;}}const rs=bs?bs.start:ts;result.push({start:Math.max(0,rs+userOffset),end:Math.max(bs?bs.end:te,rs+1.2),text:parsed[i].text});}
  }
  for(let i=1;i<result.length;i++){if(result[i].start<=result[i-1].start){result[i].start=result[i-1].start+0.5;result[i].end=Math.max(result[i].end,result[i].start+1.2);}}
  for(let i=0;i<result.length-1;i++){if(result[i].end>result[i+1].start){result[i].end=Math.max(result[i].start+0.4,result[i+1].start-0.05);}}
  return result;
}

/* ══ CANVAS ══ */
function getSub(segs,t){for(const s of segs)if(t>=s.start&&t<s.end)return s.text;return '';}
function roundRect(ctx,x,y,w,h,r){ctx.beginPath();ctx.moveTo(x+r,y);ctx.lineTo(x+w-r,y);ctx.quadraticCurveTo(x+w,y,x+w,y+r);ctx.lineTo(x+w,y+h-r);ctx.quadraticCurveTo(x+w,y+h,x+w-r,y+h);ctx.lineTo(x+r,y+h);ctx.quadraticCurveTo(x,y+h,x,y+h-r);ctx.lineTo(x,y+r);ctx.quadraticCurveTo(x,y,x+r,y);ctx.closePath();}
function clipTxt(ctx,txt,maxW){if(!txt)return '';if(ctx.measureText(txt).width<=maxW)return txt;while(txt.length>3&&ctx.measureText(txt+'…').width>maxW)txt=txt.slice(0,-1);return txt+'…';}
function drawFrame(ctx,img,title,artist,sub){
  const W=1280,H=720;ctx.fillStyle='#000';ctx.fillRect(0,0,W,H);
  const sc=Math.min(W/img.width,H/img.height);const iw=img.width*sc,ih=img.height*sc,ix=(W-iw)/2,iy=(H-ih)/2;
  ctx.drawImage(img,ix,iy,iw,ih);
  const grad=ctx.createLinearGradient(0,H-200,0,H);grad.addColorStop(0,'rgba(0,0,0,0)');grad.addColorStop(1,'rgba(0,0,0,0.9)');
  ctx.fillStyle=grad;ctx.fillRect(0,H-200,W,200);
  ctx.textAlign='left';ctx.shadowColor='rgba(0,0,0,1)';ctx.shadowBlur=14;
  ctx.fillStyle='#fff';ctx.font='bold 42px Arial';ctx.fillText(clipTxt(ctx,title,700),50,H-72);
  ctx.font='28px Arial';ctx.fillStyle='rgba(255,255,255,.75)';ctx.fillText(clipTxt(ctx,artist,700),50,H-36);
  ctx.shadowBlur=0;
  if(sub){
    ctx.font='bold 46px Arial';ctx.textAlign='center';
    const words=sub.split(' '),maxLW=1100,lines=[];let line='';
    for(const w of words){const test=line?line+' '+w:w;if(ctx.measureText(test).width>maxLW&&line){lines.push(line);line=w;}else line=test;}
    if(line)lines.push(line);
    const lineH=62,totalH=lines.length*lineH,centerY=iy+ih/2,startY=centerY-totalH/2+lineH*0.75,pad=30;
    const maxW=Math.max(...lines.map(l=>ctx.measureText(l).width));
    const boxW=Math.min(maxW+pad*2,1200),boxH=totalH+pad*1.5;
    ctx.fillStyle='rgba(0,0,0,0.62)';roundRect(ctx,(W-boxW)/2,startY-lineH*0.75-pad*0.5,boxW,boxH,18);ctx.fill();
    ctx.shadowColor='rgba(0,0,0,.95)';ctx.shadowBlur=18;ctx.fillStyle='#fff';ctx.strokeStyle='rgba(0,0,0,.5)';ctx.lineWidth=3;
    lines.forEach((l,i)=>{ctx.strokeText(clipTxt(ctx,l,1140),W/2,startY+i*lineH);ctx.fillText(clipTxt(ctx,l,1140),W/2,startY+i*lineH);});
    ctx.shadowBlur=0;ctx.lineWidth=1;
  }
}

/* ══ HELPERS ══ */
function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function setPill(state,txt){const p=document.getElementById('statusPill'),t=document.getElementById('statusPillTxt');if(p)p.className='status-pill '+state;if(t)t.textContent=txt;}
function logT(msg){const tl=document.getElementById('transLog');if(!tl)return;tl.style.display='block';const d=document.createElement('div');d.textContent=msg;tl.appendChild(d);tl.scrollTop=tl.scrollHeight;}
function setStatus(txt,pct,sub){const st=document.getElementById('statusText'),pf=document.getElementById('progressFill'),pt=document.getElementById('pctText'),ss=document.getElementById('statusSub');if(txt!=null&&st)st.textContent=txt;if(pct!=null&&pf){pf.style.width=pct+'%';if(pt)pt.textContent=Math.floor(pct)+'%';}if(sub!=null&&ss)ss.textContent=sub;}
function readAs(file,mode){return new Promise((res,rej)=>{const r=new FileReader();r.onload=e=>res(e.target.result);r.onerror=()=>rej(new Error('File read error'));mode==='buffer'?r.readAsArrayBuffer(file):r.readAsDataURL(file);});}
function toggleLyrics(){const lb=document.getElementById('lyricsBox'),ul=document.getElementById('useLyrics');if(lb&&ul)lb.style.display=ul.checked?'block':'none';}
window.toggleLyrics=toggleLyrics;
function updateLabel(iId,lId,nId){const f=document.getElementById(iId)?.files[0];if(!f)return;const l=document.getElementById(lId),n=document.getElementById(nId);if(l)l.textContent='✅ '+f.name;if(n)n.textContent=f.name;}
window.updateLabel=updateLabel;
window.clearAiCover=function(){aiCoverBlob=null;};

/* ══ TABS ══ */
function renderTabs() {
  const inner = document.getElementById('tabsInner'), newBtn = document.getElementById('btnNewProj');
  inner.querySelectorAll('.tab-btn,.tab-sep').forEach(el => el.remove());
  getProjects().forEach((p,i) => {
    if (i>0) { const sep=document.createElement('div'); sep.className='tab-sep'; inner.insertBefore(sep,newBtn); }
    const btn = document.createElement('button');
    btn.className = 'tab-btn' + (p.id===activeProjectId?' active':'');
    btn.innerHTML = '<span class="tab-dot"></span>' + esc(p.name);
    btn.onclick = () => { activeProjectId=p.id; renderTabs(); renderProject(); };
    inner.insertBefore(btn, newBtn);
  });
}

/* ══ RENDER PROJECT ══ */
function renderProject() {
  const projs = getProjects();
  const proj  = projs.find(p => p.id===activeProjectId) || projs[0];
  activeProjectId = proj.id; aiCoverBlob=null; coverMode='upload';

  syncProject(proj).then(dbId => {
    if (dbId) {
      const b = document.getElementById('syncBadge');
      if (b) { b.textContent='✅ DB'; b.className='sync-badge ok'; b.style.display='inline-block'; }
    }
  });

  document.getElementById('appContent').innerHTML = `
    <div class="project-bar">
      <span class="project-bar-icon">📁</span>
      <input class="project-name-input" id="projNameInput" value="${esc(proj.name)}"
             placeholder="Project name" maxlength="40"
             onchange="doRename(this.value)" onblur="doRename(this.value)">
      <span class="sync-badge" id="syncBadge"></span>
      <button class="btn-del-proj" onclick="doDeleteProject()">🗑 Delete project</button>
    </div>

    <div class="model-banner">
      <div class="model-top">
        <div class="model-icon">🤖</div>
        <div class="model-body">
          <div class="model-title">Whisper Base — Zero API key · Zero credits · Offline after first download</div>
          <div class="model-desc">Model: <b>Whisper Base (~75 MB)</b> · Downloaded once then cached · <b>Transcribes everything</b></div>
          <div class="status-pill" id="statusPill"><span class="dot"></span><span id="statusPillTxt">Waiting for first click</span></div>
        </div>
      </div>
      <div class="model-dl" id="modelDl">
        <div class="model-dl-header"><span id="dlLabel">Downloading model…</span><span id="dlPct">0%</span></div>
        <div class="model-dl-track"><div class="model-dl-fill" id="dlFill"></div></div>
        <div class="model-dl-files" id="dlFiles"></div>
      </div>
    </div>

    <div class="upload-box">
      <div class="row">
        <div class="field"><span class="field-label">Title</span><input type="text" id="title" placeholder="E.g.: Bohemian Rhapsody"></div>
        <div class="field"><span class="field-label">Artist</span><input type="text" id="artist" placeholder="E.g.: Queen"></div>
      </div>
      <div class="row">
        <div class="field">
          <span class="field-label">Song language</span>
          <select class="lang-select" id="songLang">
            <option value="italian">🇮🇹 Italian</option><option value="french">🇫🇷 French</option>
            <option value="english">🇬🇧 English</option><option value="spanish">🇪🇸 Spanish</option>
            <option value="portuguese">🇵🇹 Portuguese</option><option value="arabic">🇸🇦 Arabic</option>
            <option value="german">🇩🇪 German</option><option value="auto">🌍 Auto-detect</option>
          </select>
        </div>
        <div class="field">
          <span class="field-label">Cover art</span>
          <div class="cover-tabs">
            <button class="cover-tab active" id="tabUpload" onclick="switchCoverTab('upload')">📷 Upload</button>
            <button class="cover-tab" id="tabGenerate" onclick="switchCoverTab('generate')">🤖 Generate AI</button>
          </div>
          <div id="coverUploadBox">
            <div class="file-input-wrapper">
              <span class="file-btn" id="coverLabel">📷 Choose image</span>
              <input type="file" id="coverFile" accept="image/*" onchange="updateLabel('coverFile','coverLabel','coverName');clearAiCover()">
            </div>
            <div class="file-name" id="coverName">No file chosen</div>
          </div>
          <div id="coverGenBox" style="display:none">
            <div class="ai-cover-box">
              <textarea class="cover-prompt-area" id="coverPrompt" placeholder="Describe the cover…&#10;E.g.: neon city night rain cinematic dark purple"></textarea>
              <textarea class="cover-prompt-area" id="coverNegPrompt" style="min-height:44px;font-size:.8rem;color:#666;" placeholder="To avoid (optional): blurry, text, watermark…"></textarea>
              <div class="prompt-hint">💡 English gives better results · <b>Style:</b> cinematic, dark, neon, watercolor…</div>
              <button class="gen-cover-btn" id="genCoverBtn" onclick="generateCover()">✨ GENERATE AI COVER</button>
              <div class="gen-status" id="genStatus"></div>
              <div class="ai-cover-preview" id="aiCoverPreview">
                <img id="aiCoverImg" src="" alt="">
                <span class="ai-cover-badge">✅ AI Generated</span>
                <button class="ai-cover-retry" onclick="generateCover()">🔄 Regenerate</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row">
        <div class="field">
          <span class="field-label">Audio (MP3, WAV, MP4…)</span>
          <div class="file-input-wrapper">
            <span class="file-btn" id="audioLabel">🎵 Choose audio</span>
            <input type="file" id="audioFile" accept="audio/*,video/*" onchange="updateLabel('audioFile','audioLabel','audioName')">
          </div>
          <div class="file-name" id="audioName">No file chosen</div>
        </div>
      </div>
      <div class="toggle-row" style="margin-top:20px;">
        <input type="checkbox" id="useWhisper" checked onchange="toggleLyrics()">
        <label for="useWhisper">🤖 Automatic subtitles with Whisper</label>
        <span class="badge-free">FREE</span>
      </div>
      <div class="toggle-row">
        <input type="checkbox" id="useLyrics" onchange="toggleLyrics()">
        <label for="useLyrics">✏️ Paste exact lyrics — 100% precise sync</label>
        <span class="badge-prec">PRECISE</span>
      </div>
      <div id="lyricsBox">
        <div class="field-label" style="margin-bottom:8px;">Song lyrics</div>
        <div style="font-size:.76rem;color:#555;margin-bottom:10px;line-height:1.6;">Supports timestamps <b style="color:#777">[mm:ss]</b> as precise anchors.</div>
        <textarea id="lyricsText" placeholder="[0:00] First line&#10;[0:14] Second line&#10;Third line without timestamp"></textarea>
        <div class="offset-row">
          <span>⏱ Lead / Delay:</span>
          <input type="range" id="lyricsOffset" min="-3.0" max="3.0" step="0.1" value="-0.3"
            oninput="document.getElementById('offsetVal').textContent=(parseFloat(this.value)>=0?'+':'')+parseFloat(this.value).toFixed(1)+'s'">
          <span id="offsetVal">-0.3s</span>
          <span style="font-size:.72rem;color:#444;">← earlier | later →</span>
        </div>
      </div>
      <button class="create-btn" id="createBtn">🎬 CREATE VIDEO</button>
      <div class="progress" id="progressBox">
        <div class="progress-header"><span id="statusText">Processing…</span><span id="pctText">0%</span></div>
        <div class="progress-track"><div class="progress-fill" id="progressFill"></div></div>
        <div class="progress-sub" id="statusSub">Do not close this page</div>
      </div>
      <div class="trans-log" id="transLog"></div>
    </div>

    <div class="gallery">
      <div class="gallery-title">🎞️ Videos in this project</div>
      <div id="videoGrid" class="video-grid"></div>
      <div class="empty" id="emptyMsg" style="display:none"><span class="empty-icon">🎵</span>No videos yet — create your first!</div>
    </div>`;

  document.getElementById('createBtn').addEventListener('click', handleCreate);
  loadGallery();
}

/* ══ GALLERY ══ */
async function loadGallery() {
  try {
    const vs   = await dbGetByProject(activeProjectId);
    const grid = document.getElementById('videoGrid');
    const emp  = document.getElementById('emptyMsg');
    if (!grid) return;
    grid.innerHTML = '';
    if (!vs.length) { if (emp) emp.style.display='block'; return; }
    if (emp) emp.style.display='none';
    vs.sort((a,b) => b.date-a.date).forEach(v => {
      const url = URL.createObjectURL(v.blob);
      const c   = document.createElement('div'); c.className='video-card';
      const syncDot = v.synced ? '<span class="db-dot" title="Synced to DB"></span>' : '';
      c.innerHTML = `<video src="${url}" controls preload="metadata"></video>
        <div class="video-meta">
          <strong>${esc(v.title)}${syncDot}</strong>
          <span>${esc(v.artist)}</span>
        </div>
        <div class="video-actions">
          <a class="btn-dl" href="${url}" download="${esc(v.title)}.webm">⬇ Download</a>
          <button class="btn-del" onclick="doDelVideo('${v.id}')">🗑 Delete</button>
        </div>`;
      grid.appendChild(c);
    });
  } catch(e) { console.warn(e); }
}

window.doDelVideo = async function(id) {
  if (!confirm('Delete this video?')) return;
  await dbDel(id); loadGallery();
};
window.doRename = function(name) {
  renameProject(activeProjectId, name); renderTabs();
  const p = getProjects().find(p => p.id===activeProjectId);
  if (p) syncProject(p);
};
window.doDeleteProject = async function() {
  const list = getProjects();
  if (list.length <= 1) { alert('You cannot delete the only project!'); return; }
  const proj = list.find(p => p.id===activeProjectId);
  if (!confirm('Delete the project "'+proj.name+'" and all its videos?')) return;
  await dbDelByProject(activeProjectId);
  const rem = removeProject(activeProjectId); activeProjectId = rem[0].id;
  renderTabs(); renderProject();
};

/* ══ RESET UI ══ */
function resetUI() {
  const btn=document.getElementById('createBtn'); if(btn)btn.disabled=false;
  const pb=document.getElementById('progressBox'); if(pb)pb.style.display='none';
  const pf=document.getElementById('progressFill'); if(pf)pf.style.width='0%';
  const pt=document.getElementById('pctText'); if(pt)pt.textContent='0%';
  const tl=document.getElementById('transLog'); if(tl){tl.style.display='none';tl.innerHTML='';}
  ['title','artist'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  ['coverFile','audioFile'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
  const cl=document.getElementById('coverLabel'),al=document.getElementById('audioLabel');
  if(cl)cl.textContent='📷 Choose image'; if(al)al.textContent='🎵 Choose audio';
  const cn=document.getElementById('coverName'),an=document.getElementById('audioName');
  if(cn)cn.textContent='No file chosen'; if(an)an.textContent='No file chosen';
  const lt=document.getElementById('lyricsText'); if(lt)lt.value='';
  const ul=document.getElementById('useLyrics'); if(ul)ul.checked=false;
  const lb=document.getElementById('lyricsBox'); if(lb)lb.style.display='none';
  aiCoverBlob=null; coverMode='upload'; switchCoverTab('upload');
}

/* ══ CREATE VIDEO ══ */
async function handleCreate() {
  const title      = document.getElementById('title').value.trim();
  const artist     = document.getElementById('artist').value.trim();
  const coverFile  = document.getElementById('coverFile').files[0];
  const audioFile  = document.getElementById('audioFile').files[0];
  const useWhisper = document.getElementById('useWhisper').checked;
  const useLyrics  = document.getElementById('useLyrics').checked;
  const rawLyrics  = document.getElementById('lyricsText').value.trim();
  const lang       = document.getElementById('songLang').value;
  const projId     = activeProjectId;
  const proj       = getProjects().find(p => p.id===projId);
  const usesAiCover = coverMode==='generate' && aiCoverBlob;

  if (!title || !artist)           { alert('Please fill in title and artist.'); return; }
  if (!audioFile)                  { alert('Please upload an audio file.'); return; }
  if (!usesAiCover && !coverFile)  { alert('Please upload a cover image or generate one with AI.'); return; }
  if (useLyrics && !rawLyrics)     { alert('You selected "Manual lyrics" but the field is empty!'); return; }

  document.getElementById('createBtn').disabled = true;
  document.getElementById('progressBox').style.display = 'block';
  setStatus('Initializing…', 2, 'Do not close this page');

  const lyricsMode = useLyrics && rawLyrics ? 'manual' : (useWhisper ? 'whisper' : 'none');
  let drawLoop=null, audioCtx=null, stopped=false;
  function doStop(rec) {
    if (stopped) return; stopped=true;
    if (drawLoop) { clearInterval(drawLoop); drawLoop=null; }
    setStatus('Finalizing…', 99, '');
    if (rec.state==='recording') { rec.requestData(); setTimeout(()=>{ try{rec.stop();}catch(e){} if(audioCtx)audioCtx.close().catch(()=>{}); }, 700); }
  }

  try {
    await getIDB();
    let segs = [];
    if (useWhisper || useLyrics) {
      setStatus('Loading Whisper…', 5, 'First time: ~75 MB, then cached');
      try {
        const wSegs = await transcribeAll(audioFile, lang);
        if (useLyrics && rawLyrics) {
          setStatus('Syncing…', 18, '');
          const off = document.getElementById('lyricsOffset');
          segs = syncLyricsWithTimings(wSegs, rawLyrics, off ? parseFloat(off.value)||0 : -0.3);
        } else { segs = wSegs; }
        setStatus('Ready!', 22, segs.length+' lines');
        await new Promise(r => setTimeout(r, 400));
      } catch(e) {
        logT('⚠️ '+e.message);
        if (!confirm('Whisper error:\n"'+e.message+'"\n\nContinue without subtitles?')) { resetUI(); return; }
        segs = [];
      }
    }

    setStatus('Loading cover…', 24);
    let imgB64;
    if (usesAiCover) {
      imgB64 = await new Promise((res,rej) => { const r=new FileReader(); r.onload=e=>res(e.target.result); r.onerror=()=>rej(new Error('AI cover read error')); r.readAsDataURL(aiCoverBlob); });
    } else {
      imgB64 = await readAs(coverFile, 'dataurl');
    }
    const img = new Image();
    await new Promise((res,rej) => { img.onload=res; img.onerror=()=>rej(new Error('Invalid image')); img.src=imgB64; });

    setStatus('Decoding audio…', 30);
    const aBuf = await readAs(audioFile, 'buffer');
    audioCtx   = new (window.AudioContext||window.webkitAudioContext)();
    const decoded  = await audioCtx.decodeAudioData(aBuf);
    const duration = decoded.duration;

    const canvas = document.createElement('canvas'); canvas.width=1280; canvas.height=720;
    const ctx2d  = canvas.getContext('2d'); drawFrame(ctx2d, img, title, artist, '');

    const stream = canvas.captureStream(30);
    if (!stream || !stream.getVideoTracks().length) throw new Error('captureStream() not supported — use Chrome or Edge.');
    const bufSrc = audioCtx.createBufferSource(); bufSrc.buffer=decoded;
    const dest   = audioCtx.createMediaStreamDestination(); bufSrc.connect(dest);
    const aTrack = dest.stream.getAudioTracks()[0]; if (aTrack) stream.addTrack(aTrack);

    const mime = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm'].find(m=>MediaRecorder.isTypeSupported(m)) || 'video/webm';
    const rec    = new MediaRecorder(stream, {mimeType:mime});
    const chunks = [];
    rec.ondataavailable = e => { if (e.data?.size>0) chunks.push(e.data); };

    rec.onstop = async () => {
      setStatus('Saving…', 99, '');
      try {
        if (!chunks.length) throw new Error('No data — use Chrome or Edge.');
        const blob = new Blob(chunks, {type:'video/webm'});

        const idbId = await dbSave(blob, title, artist, projId, proj?.dbId||null, lyricsMode);
        setStatus('✅ Saved locally! Syncing to DB…', 100, '');
        logT('💾 Saved to IndexedDB: ' + idbId);

        let projDbId = proj?.dbId || null;
        if (!projDbId) {
          logT('📡 Syncing project to DB…');
          projDbId = await syncProject(proj);
          if (projDbId) {
            logT('✅ Project synced — DB id: ' + projDbId);
          } else {
            logT('⚠️ Project sync failed — retrying in 2s…');
            await new Promise(r => setTimeout(r, 2000));
            projDbId = await syncProject(proj);
            if (!projDbId) logT('❌ Unable to sync project — video will remain local only');
          }
        }

        if (projDbId) {
          logT('📡 Syncing track to DB…');
          try {
            const trackId = await syncTrack({
              title,
              artist,
              local_idb_id: idbId,
              project_id:   projDbId,
              language:     lang,
              cover_type:   usesAiCover ? 'ai' : 'upload',
              lyrics_mode:  lyricsMode,
              device_id:    DEVICE_ID,
              device_name:  DEVICE_NAME,
              size_bytes:   blob.size,
              duration_s:   Math.round(duration * 10) / 10,
            });

            if (trackId) {
              logT('✅ Track saved to DB — id: ' + trackId);
              await dbMarkSynced(idbId);
              loadGallery();
            } else {
              logT('⚠️ syncTrack returned null — retrying in 3s…');
              await new Promise(r => setTimeout(r, 3000));
              const trackId2 = await syncTrack({
                title, artist, local_idb_id: idbId,
                project_id: projDbId, language: lang,
                cover_type: usesAiCover ? 'ai' : 'upload',
                lyrics_mode: lyricsMode, device_id: DEVICE_ID,
                device_name: DEVICE_NAME, size_bytes: blob.size,
                duration_s: Math.round(duration * 10) / 10,
              });
              if (trackId2) {
                logT('✅ Track synced on second attempt — id: ' + trackId2);
                await dbMarkSynced(idbId);
                loadGallery();
              } else {
                logT('❌ Track sync failed — check PHP session and DB');
              }
            }
          } catch(syncErr) {
            logT('❌ Sync error: ' + syncErr.message);
            console.error('syncTrack error:', syncErr);
          }
        } else {
          logT('⚠️ Track not synced to DB (project not synced)');
        }

        setTimeout(() => { resetUI(); loadGallery(); }, 1200);

      } catch(err) {
        logT('❌ Save error: ' + err.message);
        alert('Save error: ' + err.message);
        resetUI();
      }
    };

    setStatus('Recording…', 32, 'Do not close this page');
    rec.start(1000); const t0 = audioCtx.currentTime; bufSrc.start(0);
    drawLoop = setInterval(() => {
      if (stopped) return;
      const el  = audioCtx.currentTime - t0;
      const pct = Math.min(32 + (el/duration)*66, 98);
      setStatus('Recording…', pct, Math.floor(el)+'s / '+Math.floor(duration)+'s');
      drawFrame(ctx2d, img, title, artist, getSub(segs, el));
      if (el >= duration) doStop(rec);
    }, 50);
    bufSrc.onended = () => doStop(rec);

  } catch(err) {
    if (drawLoop) clearInterval(drawLoop);
    if (audioCtx) audioCtx.close().catch(()=>{});
    console.error(err); alert('Error: '+err.message); resetUI();
  }
}

/* ══ ADMIN PANEL ══ */
window.openAdmin = async function() {
  document.getElementById('adminOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
  const content = document.getElementById('adminContent');
  content.innerHTML = '<div class="admin-loading">⏳ Loading data from DB…</div>';

  try {
    const res  = await fetch('music.php?api=admin_data', { method:'POST', body:'' });
    const data = await res.json();
    if (!data.ok) { content.innerHTML = '<div class="admin-loading" style="color:#f44336">❌ '+esc(data.error)+'</div>'; return; }

    const s   = data.stats || {};
    const fmt = n => Number(n||0).toLocaleString('en-US');
    const fmtMb = b => (Number(b||0)/1024/1024).toFixed(1)+' MB';
    const fmtDate = d => d ? new Date(d).toLocaleDateString('en-US') : '—';

    content.innerHTML = `
      <div class="stats-grid">
        <div class="stat-card"><div class="stat-val">${fmt(s.total_users)}</div><div class="stat-lbl">👥 Users</div></div>
        <div class="stat-card"><div class="stat-val">${fmt(s.total_tracks)}</div><div class="stat-lbl">🎬 Total videos</div></div>
        <div class="stat-card"><div class="stat-val">${fmt(s.total_projects)}</div><div class="stat-lbl">📁 Projects</div></div>
        <div class="stat-card"><div class="stat-val">${fmt(s.tracks_synced)}</div><div class="stat-lbl">☁️ Synced</div></div>
        <div class="stat-card"><div class="stat-val">${fmt(s.tracks_local_only)}</div><div class="stat-lbl">💾 Local only</div></div>
        <div class="stat-card"><div class="stat-val">${fmtMb(s.total_storage_bytes)}</div><div class="stat-lbl">📦 Storage</div></div>
        <div class="stat-card"><div class="stat-val">${fmt(s.unique_devices)}</div><div class="stat-lbl">📱 Devices</div></div>
        <div class="stat-card"><div class="stat-val">${fmt(s.active_users)}</div><div class="stat-lbl">✅ Active users</div></div>
      </div>

      <div class="admin-sec">👥 Registered users</div>
      <table class="tbl">
        <thead><tr>
          <th>User</th><th>Email</th><th>Role</th>
          <th>Projects</th><th>Videos</th><th>Storage</th>
          <th>Registered</th><th>Last login</th>
        </tr></thead>
        <tbody>
          ${(data.users||[]).map(u=>`<tr>
            <td><strong>${esc(u.username||'')}</strong></td>
            <td style="color:#666;font-size:.78rem">${esc(u.email||'')}</td>
            <td><span class="chip ${u.role==='admin'?'c-admin':'c-user'}">${u.role}</span>
                ${u.is_active?'':'<span class="chip" style="background:#1a0808;color:#f44336;border-color:#5a1a1a;margin-left:4px">off</span>'}</td>
            <td style="text-align:center">${fmt(u.project_count)}</td>
            <td style="text-align:center">${fmt(u.track_count)}</td>
            <td style="color:#777;font-size:.76rem">${fmtMb(u.total_bytes)}</td>
            <td style="color:#555;font-size:.74rem">${fmtDate(u.created_at)}</td>
            <td style="color:#555;font-size:.74rem">${fmtDate(u.last_login_at)}</td>
          </tr>`).join('')}
        </tbody>
      </table>

      <div class="admin-sec" style="margin-top:34px">🎬 All videos (last 300)</div>
      <input class="admin-search" type="text" id="adminSearch"
             placeholder="🔍 Search title, artist, user, project…"
             oninput="filterAdmin(this.value)">
      <table class="tbl" id="adminTbl">
        <thead><tr>
          <th>Title</th><th>Artist</th><th>User</th>
          <th>Project</th><th>Storage</th><th>Size</th><th>Date</th>
        </tr></thead>
        <tbody id="adminTblBody">
          ${(data.tracks||[]).map(t=>`<tr>
            <td><strong>${esc(t.title||'')}</strong></td>
            <td>${esc(t.artist||'')}</td>
            <td style="color:#9090ff">${esc(t.username||'')}</td>
            <td style="color:#777">${esc(t.project_name||'—')}</td>
            <td><span class="chip ${t.storage_type==='both'?'c-both':t.storage_type==='server'?'c-server':'c-local'}">${t.storage_type||'local'}</span></td>
            <td style="color:#555;font-size:.76rem">${fmtMb(t.video_size_bytes)}</td>
            <td style="color:#555;font-size:.74rem">${fmtDate(t.created_at)}</td>
          </tr>`).join('')}
        </tbody>
      </table>`;

  } catch(e) {
    content.innerHTML = '<div class="admin-loading" style="color:#f44336">❌ Error: '+esc(e.message)+'</div>';
  }
};

window.closeAdmin = function() {
  document.getElementById('adminOverlay').classList.remove('open');
  document.body.style.overflow = '';
};

window.filterAdmin = function(q) {
  const ql = q.toLowerCase();
  document.querySelectorAll('#adminTblBody tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(ql) ? '' : 'none';
  });
};

document.addEventListener('keydown', e => { if (e.key==='Escape') window.closeAdmin(); });

/* ══ MODAL ══ */
document.getElementById('btnNewProj').onclick = () => {
  document.getElementById('modalInput').value = '';
  document.getElementById('modalBg').classList.add('open');
  setTimeout(() => document.getElementById('modalInput').focus(), 120);
};
document.getElementById('btnModalCancel').onclick = () => document.getElementById('modalBg').classList.remove('open');
document.getElementById('btnModalOk').onclick = () => {
  const name = document.getElementById('modalInput').value.trim();
  if (!name) { alert('Please enter a name!'); return; }
  const p = addProject(name); activeProjectId = p.id;
  document.getElementById('modalBg').classList.remove('open');
  syncProject(p);
  renderTabs(); renderProject();
};
document.getElementById('modalInput').addEventListener('keydown', e => {
  if (e.key==='Enter') document.getElementById('btnModalOk').click();
  if (e.key==='Escape') document.getElementById('btnModalCancel').click();
});
document.getElementById('modalBg').addEventListener('click', e => {
  if (e.target===document.getElementById('modalBg')) document.getElementById('modalBg').classList.remove('open');
});

/* ══ INIT ══ */
getIDB()
  .then(() => { renderTabs(); renderProject(); })
  .catch(() => {
    document.getElementById('appContent').innerHTML =
      '<div class="empty"><span class="empty-icon">⚠️</span>IndexedDB not available.<br>Please use Chrome or Edge.</div>';
  });
</script>
</body>
</html>

<?php ob_start(); session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// ===== FORM PROCESSING =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $how_found  = htmlspecialchars(trim($_POST['how_found']  ?? ''));
    $level      = htmlspecialchars(trim($_POST['level']      ?? ''));
    $goal       = htmlspecialchars(trim($_POST['goal']       ?? ''));
    $duration   = htmlspecialchars(trim($_POST['duration']   ?? ''));
    $motivation = htmlspecialchars(trim($_POST['motivation'] ?? ''));
    $skills     = isset($_POST['skills']) && is_array($_POST['skills'])
        ? implode(", ", array_map('trim', $_POST['skills'])) : '';
    $accent     = htmlspecialchars(trim($_POST['accent']     ?? ''));
    $days       = intval($_POST['days']    ?? 0);
    $minutes    = intval($_POST['minutes'] ?? 0);

    if ($how_found === '' || $level === '' || $goal === '' || $duration === '' || $motivation === '' || $accent === '') {
        $_SESSION['quiz_error'] = "Please fill in all required fields.";
    } else {
        try {
            $dsn         = "pgsql:host=ep-autumn-salad-adwou7x2-pooler.c-2.us-east-1.aws.neon.tech;port=5432;dbname=veronica_db_login;sslmode=require";
            $username_db = "neondb_owner";
            $password_db = "npg_QolPDv5L9gVj";

            $conn = new PDO($dsn, $username_db, $password_db, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $sql = "INSERT INTO user_quiz (username, how_found, level, goal, duration, motivation, skills, accent, days, minutes)
                    VALUES (:username, :how_found, :level, :goal, :duration, :motivation, :skills, :accent, :days, :minutes)";

            $stmt     = $conn->prepare($sql);
            $username = $_SESSION['username'] ?? 'guest';

            $stmt->execute([
                ':username'   => $username,
                ':how_found'  => $how_found,
                ':level'      => $level,
                ':goal'       => $goal,
                ':duration'   => $duration,
                ':motivation' => $motivation,
                ':skills'     => $skills,
                ':accent'     => $accent,
                ':days'       => $days,
                ':minutes'    => $minutes,
            ]);

            $conn = null;
            header("Location: thankyou.php");
            exit;
        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
            $_SESSION['quiz_error'] = "An error occurred while saving your profile.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Language Quiz – Veronica AI</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    margin: 0;
    font-family: "Poppins", sans-serif;
    background: linear-gradient(135deg, #a78bfa, #7dd3fc);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #1e293b;
}
.quiz-container {
    background: rgba(255, 255, 255, 0.25);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 40px;
    width: 90%;
    max-width: 700px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    text-align: center;
    animation: fadeIn 0.8s ease-in-out;
}
h2 { font-size: 1.8rem; color: #1e293b; margin-bottom: 20px; }
label { font-weight: 600; display: block; margin: 10px 0 5px; text-align: left; }
select, textarea, input[type="number"] {
    width: 100%; padding: 10px; border-radius: 10px;
    border: 1px solid #dbeafe; margin-bottom: 15px;
    font-size: 1rem;
}
textarea { height: 80px; resize: none; }
.skills-group { display: flex; flex-direction: column; gap: 8px; text-align: left; }
.skills-group label { font-weight: 400; display: flex; align-items: center; gap: 8px; }
button {
    background-color: #4f46e5; color: white; border: none;
    border-radius: 10px; padding: 12px 25px; cursor: pointer;
    font-size: 1.1rem; width: 100%; transition: 0.3s;
}
button:hover { background-color: #4338ca; transform: translateY(-2px); }
.veronica-message {
    font-style: italic; margin-bottom: 20px;
    background-color: rgba(255,255,255,0.2);
    border-radius: 10px; padding: 15px;
}
.alert-error {
    background: rgba(220,38,38,0.12); color: #b91c1c;
    padding: 10px; border-radius: 8px; margin-bottom: 15px; font-weight: 600;
}
.chart-container { margin-top: 25px; }
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<div class="quiz-container">
    <h2>🎓 Your Language Profile – Veronica AI</h2>

    <div class="veronica-message" id="veronicaMsg">
        Hey 👋 I'm <strong>Veronica AI</strong>!
        Answer these questions so I can personalise your French learning journey 🇫🇷
    </div>

    <?php if (!empty($_SESSION['quiz_error'])): ?>
        <div class="alert-error"><?= htmlspecialchars($_SESSION['quiz_error']) ?></div>
        <?php unset($_SESSION['quiz_error']); ?>
    <?php endif; ?>

    <form method="post" id="quizForm">

        <label for="how_found">How did you hear about Veronica AI?</label>
        <select name="how_found" id="how_found" required>
            <option value="">-- Select an answer --</option>
            <option>Through a friend</option>
            <option>On social media</option>
            <option>Via an advertisement</option>
            <option>Internet search</option>
            <option>Other</option>
        </select>

        <label for="level">What is your current French level?</label>
        <select name="level" id="level" required>
            <option value="">-- Choose your level --</option>
            <option>Beginner (A1–A2)</option>
            <option>Intermediate (B1–B2)</option>
            <option>Advanced (C1–C2)</option>
            <option>I'm not sure</option>
        </select>

        <label for="goal">Why do you want to learn French?</label>
        <select name="goal" id="goal" required>
            <option value="">-- Your main goal --</option>
            <option>Travel to a French-speaking country</option>
            <option>Study in France / Canada</option>
            <option>Professional reasons</option>
            <option>Culture and passion for the language</option>
            <option>Other</option>
        </select>

        <label for="accent">Which accent would you like to learn?</label>
        <select name="accent" id="accent" required>
            <option value="">-- Choose an accent --</option>
            <option>Parisian French 🇫🇷</option>
            <option>Canadian French 🇨🇦</option>
            <option>Belgian French 🇧🇪</option>
        </select>

        <label for="duration">How long do you want to take to reach your goal?</label>
        <select name="duration" id="duration" required>
            <option value="">-- Choose a timeframe --</option>
            <option>Less than 3 months</option>
            <option>3 to 6 months</option>
            <option>6 to 12 months</option>
            <option>More than a year</option>
        </select>

        <label>How much do you want to practise?</label>
        <div style="display:flex; gap:10px;">
            <input type="number" name="days"    id="days"    min="1" max="7"   placeholder="Days / week">
            <input type="number" name="minutes" id="minutes" min="5" max="300" placeholder="Minutes / day">
        </div>

        <div class="chart-container">
            <canvas id="practiceChart" height="200"></canvas>
        </div>

        <label style="margin-top:10px;">Which skills would you like to develop?</label>
        <div class="skills-group">
            <label><input type="checkbox" name="skills[]" value="Professional interview"> Professional interview</label>
            <label><input type="checkbox" name="skills[]" value="Oral presentation"> Oral presentation</label>
            <label><input type="checkbox" name="skills[]" value="Negotiation"> Negotiation</label>
            <label><input type="checkbox" name="skills[]" value="Meeting or conference"> Meeting / conference</label>
            <label><input type="checkbox" name="skills[]" value="Informal communication"> Informal communication</label>
        </div>

        <label for="motivation" style="margin-top:16px;">Your motivation to learn French:</label>
        <textarea name="motivation" id="motivation" required
            placeholder="E.g.: I love French culture, music, cinema…"></textarea>

        <button type="submit">Submit my profile</button>
    </form>

    <footer style="margin-top:25px; font-size:0.9rem; color:#334155;">
        🪄 Veronica AI – Your intelligent language coach
    </footer>
</div>

<script>
const ctx = document.getElementById('practiceChart');
let chart;

function updateChart() {
    const days    = parseInt(document.getElementById('days').value)    || 0;
    const minutes = parseInt(document.getElementById('minutes').value) || 0;
    const totalWeekly  = days * minutes;
    const totalMonthly = totalWeekly * 4;

    const data = {
        labels: ['Minutes / week', 'Minutes / month'],
        datasets: [{
            label: 'Estimated practice time',
            data: [totalWeekly, totalMonthly],
            borderWidth: 2,
            backgroundColor: ['#4f46e5', '#7dd3fc']
        }]
    };

    if (chart) chart.destroy();
    chart = new Chart(ctx, {
        type: 'bar',
        data,
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
}

document.getElementById('days').addEventListener('input', updateChart);
document.getElementById('minutes').addEventListener('input', updateChart);

window.onload = () => {
    const intro = "Hello! I'm Veronica AI. Answer these questions so I can personalise your learning path based on your preferred accent and study rhythm.";
    const msg = document.getElementById('veronicaMsg');
    msg.textContent = intro;

    if ('speechSynthesis' in window) {
        const utter = new SpeechSynthesisUtterance(intro);
        utter.lang = 'en-US';
        window.speechSynthesis.speak(utter);
    }
};
</script>

</body>
</html>

<?php ob_end_flush(); ?>
