<?php
// ══════════════════════════════════════════════════════════════
// DARK.PHP — Page d'administration sécurisée
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../config/db.php';

// ── Session sécurisée ──
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_name('__Host-ADMIN_SESS');
session_start();

// ── Vérification d'authentification ──
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /dark-login.php');
    exit;
}

// ── Expiration de session 3h ──
define('SESSION_LIFETIME', 10800); // 3 heures
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME) {
    session_destroy();
    header('Location: /dark-login.php?expired=1');
    exit;
}

$pdo = getPDO();
$admin_user = htmlspecialchars($_SESSION['admin_user'] ?? 'admin');
$message = '';
$msg_type = '';

// ── Génération token CSRF ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Sections modifiables ──
$sections = ['social', 'profil', 'experience', 'formations', 'competences', 'projets'];

// ── Logout ──
if (isset($_GET['logout'])) {
    $csrf = $_GET['csrf'] ?? '';
    if (hash_equals($_SESSION['csrf_token'], $csrf)) {
        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, 'LOGOUT', ?)")
            ->execute([$_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
        session_destroy();
        header('Location: /dark-login.php');
        exit;
    }
}

// ── Changement de mot de passe ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $message = 'Token CSRF invalide.'; $msg_type = 'error';
    } else {
        $old_pass  = $_POST['old_password'] ?? '';
        $new_pass  = $_POST['new_password'] ?? '';
        $new_pass2 = $_POST['new_password2'] ?? '';

        if (strlen($old_pass) < 1 || strlen($new_pass) < 12) {
            $message = 'Le nouveau mot de passe doit faire au moins 12 caractères.'; $msg_type = 'error';
        } elseif ($new_pass !== $new_pass2) {
            $message = 'Les mots de passe ne correspondent pas.'; $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $user = $stmt->fetch();
            if ($user && password_verify($old_pass, $user['password_hash'])) {
                $new_hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $_SESSION['admin_id']]);
                $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, 'PASSWORD_CHANGED', ?)")->execute([$_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
                $message = 'Mot de passe modifié avec succès.'; $msg_type = 'success';
            } else {
                $message = 'Ancien mot de passe incorrect.'; $msg_type = 'error';
            }
        }
    }
}

// ── Changement de secret OTP ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_otp') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $message = 'Token CSRF invalide.'; $msg_type = 'error';
    } else {
        $current_pass = $_POST['current_password'] ?? '';
        $new_secret   = trim(strtoupper(preg_replace('/\s+/', '', $_POST['new_otp_secret'] ?? '')));
        $otp_verify   = trim($_POST['otp_verify'] ?? '');

        // Valider le format Base32 du secret
        if (!preg_match('/^[A-Z2-7]{16,32}$/', $new_secret)) {
            $message = 'Secret OTP invalide (Base32, 16–32 caractères A-Z2-7).'; $msg_type = 'error';
        } elseif (strlen($otp_verify) !== 6 || !ctype_digit($otp_verify)) {
            $message = 'Code OTP de vérification invalide (6 chiffres requis).'; $msg_type = 'error';
        } else {
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $user = $stmt->fetch();
            if ($user && password_verify($current_pass, $user['password_hash'])) {
                // Vérifier que le nouveau code OTP est valide avec le nouveau secret
                if (verifyTOTP($new_secret, $otp_verify)) {
                    $pdo->prepare("UPDATE admin_users SET otp_secret = ? WHERE id = ?")
                        ->execute([$new_secret, $_SESSION['admin_id']]);
                    $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, 'OTP_SECRET_CHANGED', ?)")
                        ->execute([$_SESSION['admin_id'], $_SERVER['REMOTE_ADDR']]);
                    $message = 'Secret OTP mis à jour avec succès.'; $msg_type = 'success';
                } else {
                    $message = 'Code OTP de vérification incorrect. Le secret n\'a pas été modifié.'; $msg_type = 'error';
                }
            } else {
                $message = 'Mot de passe incorrect.'; $msg_type = 'error';
            }
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Sauvegarde de contenu HTML ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_section') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $message = 'Token CSRF invalide.'; $msg_type = 'error';
    } else {
        $section = $_POST['section'] ?? '';
        $html    = $_POST['content'] ?? '';

        if (!in_array($section, $sections, true)) {
            $message = 'Section invalide.'; $msg_type = 'error';
        } else {
            // Nettoyer le HTML mais autoriser les balises sûres
            $allowed_tags = [
                'p','br','strong','em','b','i','u','span','div','ul','ol','li',
                'a','h1','h2','h3','h4','h5','h6','code','pre','blockquote',
                'table','thead','tbody','tr','th','td','hr','mark','small',
                'dl','dt','dd','sup','sub','abbr','cite',
            ];
            $html = cleanHTML($html, $allowed_tags);

            // Sauvegarder en base et en fichier
            $stmt = $pdo->prepare("INSERT INTO site_content (section_key, content_html, updated_by, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE content_html = VALUES(content_html), updated_by = VALUES(updated_by), updated_at = NOW()");
            $stmt->execute([$section, $html, $_SESSION['admin_id']]);

            // Sauvegarder aussi en fichier (fallback)
            $dir = __DIR__ . '/content/';
            if (!is_dir($dir)) mkdir($dir, 0750, true);
            file_put_contents($dir . $section . '.html', $html);

            $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, ?, ?)")->execute([$_SESSION['admin_id'], 'EDIT_SECTION:' . $section, $_SERVER['REMOTE_ADDR']]);
            $message = "Section '{$section}' sauvegardée avec succès."; $msg_type = 'success';
        }
    }
    // Régénérer le token CSRF après chaque action
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Charger le contenu actuel depuis DB ──
function getSectionContent(PDO $pdo, string $section): string {
    $stmt = $pdo->prepare("SELECT content_html FROM site_content WHERE section_key = ?");
    $stmt->execute([$section]);
    $row = $stmt->fetch();
    if ($row) return $row['content_html'];
    // Fallback fichier
    $file = __DIR__ . '/content/' . $section . '.html';
    return file_exists($file) ? file_get_contents($file) : '';
}

// ── Purificateur HTML simple ──
function cleanHTML(string $html, array $allowed_tags): string {
    // Supprimer les attributs dangereux
    $dangerous_attrs = ['onload','onerror','onclick','onmouseover','onfocus','onblur','onchange',
                        'onsubmit','onkeyup','onkeydown','onkeypress','javascript','vbscript'];
    // Utiliser DOMDocument pour parser proprement
    if (!$html) return '';
    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . '<div id="_wrap">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    cleanNode($doc->documentElement, $allowed_tags, $dangerous_attrs);
    $wrap = $doc->getElementById('_wrap');
    $result = '';
    if ($wrap) {
        foreach ($wrap->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }
    }
    return $result;
}

function cleanNode(DOMNode $node, array $allowed_tags, array $dangerous_attrs): void {
    $to_remove = [];
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMElement) {
            if (!in_array(strtolower($child->nodeName), $allowed_tags)) {
                $to_remove[] = $child;
            } else {
                // Vérifier les attributs
                $attrs_to_remove = [];
                foreach ($child->attributes as $attr) {
                    $name = strtolower($attr->name);
                    $val  = strtolower($attr->value);
                    if (in_array($name, $dangerous_attrs) || str_starts_with($val, 'javascript:') || str_starts_with($val, 'data:')) {
                        $attrs_to_remove[] = $name;
                    }
                    // Pour href et src : autoriser seulement http/https/mailto/tel
                    if (in_array($name, ['href','src','action'])) {
                        if (!preg_match('/^(https?:\/\/|mailto:|tel:|#|\/)/i', $attr->value)) {
                            $attrs_to_remove[] = $name;
                        }
                    }
                }
                foreach ($attrs_to_remove as $a) $child->removeAttribute($a);
                cleanNode($child, $allowed_tags, $dangerous_attrs);
            }
        }
    }
    foreach ($to_remove as $el) {
        // Conserver le texte
        while ($el->firstChild) {
            $node->insertBefore($el->firstChild, $el);
        }
        $node->removeChild($el);
    }
}

// ── Récupérer les logs ──
$logs = $pdo->query("SELECT al.*, au.username FROM admin_logs al LEFT JOIN admin_users au ON al.admin_id = au.id ORDER BY al.created_at DESC LIMIT 20")->fetchAll();

// Section active

$system_sections = ['password', 'logs', 'otp'];
$raw_section = $_GET['section'] ?? '';
if (in_array($raw_section, $sections, true)) {
    $active_section = $raw_section;
} elseif (in_array($raw_section, $system_sections, true)) {
    $active_section = $raw_section;
} else {
    $active_section = 'profil';
}
$current_content = in_array($active_section, $sections, true) ? getSectionContent($pdo, $active_section) : '';

$section_labels = [
    'social'      => '⚡ Liens Sociaux',
    'profil'      => '◉ Profil',
    'experience'  => '◈ Expérience',
    'formations'  => '🎓 Formations',
    'competences' => '⚙ Compétences',
    'projets'     => '◇ Projets',
];

$logout_url = '/dark.php?logout=1&csrf=' . urlencode($_SESSION['csrf_token']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dark Admin — rattack@Rattack-Box</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <style>
        :root {
            --red:#cc0000;--red-b:#ff2222;--red-g:rgba(204,0,0,0.25);
            --bg:#080808;--sidebar:#0d0d0d;--main:#0a0a0a;--card:#111;
            --border:#1e0000;--text:#cc2200;--dim:#661100;--white:#ddd;
            --green:#00aa44;--yellow:#ccaa00;--orange:#cc5500;
        }
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{background:var(--bg);color:var(--text);font-family:'JetBrains Mono',monospace;min-height:100vh;display:flex;flex-direction:column;}
        a{color:var(--red);text-decoration:none;}
        a:hover{color:var(--red-b);}

        /* TOPBAR */
        .topbar{background:#0e0e0e;border-bottom:1px solid var(--border);padding:10px 20px;display:flex;align-items:center;gap:16px;}
	   .topbar-nav{display:flex;gap:6px;margin-left:auto;align-items:center;}
        .topbar-brand{color:var(--red-b);font-weight:700;letter-spacing:0.1em;font-size:0.9rem;}
	   .topbar-user{color:var(--dim);font-size:0.8rem;}
        .topbar-time{color:var(--dim);font-size:0.75rem;} 
        .logout-btn{background:#1a0000;border:1px solid #330000;color:var(--red);padding:5px 12px;border-radius:3px;font-family:inherit;font-size:0.78rem;cursor:pointer;}
        .logout-btn:hover{background:var(--red);color:#000;}

        /* LAYOUT */
        .admin-layout{display:flex;flex:1;min-height:0;}
        .sidebar{width:200px;min-width:180px;background:var(--sidebar);border-right:1px solid var(--border);padding:16px 0;display:flex;flex-direction:column;gap:4px;}
        .sidebar-label{color:var(--dim);font-size:0.7rem;padding:8px 16px 4px;letter-spacing:0.08em;}
        .sidebar-link{display:block;padding:8px 16px;color:#aaa;font-size:0.82rem;border-left:2px solid transparent;transition:all 0.15s;}
        .sidebar-link:hover{color:var(--red-b);border-left-color:var(--red);}
        .sidebar-link.active{color:var(--red-b);border-left-color:var(--red);background:rgba(204,0,0,0.05);}
        .sidebar-sep{border:none;border-top:1px solid var(--border);margin:8px 0;}

        /* MAIN */
        .admin-main{flex:1;padding:24px;overflow-y:auto;}
        .page-title{color:var(--red-b);font-size:1rem;font-weight:700;margin-bottom:4px;letter-spacing:0.06em;}
        .page-sub{color:var(--dim);font-size:0.75rem;margin-bottom:20px;}

        /* MESSAGE */
        .msg{padding:10px 14px;border-radius:3px;font-size:0.82rem;margin-bottom:16px;display:flex;gap:8px;align-items:center;}
        .msg.success{background:#001a0d;border:1px solid var(--green);color:var(--green);}
        .msg.error{background:#1a0000;border:1px solid var(--red);color:var(--red-b);}

        /* EDITOR */
        .editor-tabs{display:flex;gap:6px;margin-bottom:-1px;}
        .editor-tab{padding:6px 14px;border:1px solid var(--border);border-bottom:none;border-radius:3px 3px 0 0;font-family:inherit;font-size:0.78rem;background:#0d0d0d;color:var(--dim);cursor:pointer;transition:all 0.15s;}
        .editor-tab.active{background:var(--card);color:var(--red-b);border-bottom:1px solid var(--card);}

        .editor-wrap{border:1px solid var(--border);border-radius:0 4px 4px 4px;overflow:hidden;}
        .editor-toolbar{background:#0d0d0d;border-bottom:1px solid var(--border);padding:6px 10px;display:flex;flex-wrap:wrap;gap:4px;}
        .tb-btn{background:#1a0000;border:1px solid var(--border);color:#aaa;padding:3px 8px;border-radius:2px;font-family:inherit;font-size:0.72rem;cursor:pointer;transition:all 0.15s;}
        .tb-btn:hover{background:var(--red);color:#000;border-color:var(--red);}
        .tb-sep{width:1px;background:var(--border);margin:0 4px;}

        #editor-area{
            width:100%;min-height:320px;background:#080808;color:#ddd;
            border:none;outline:none;padding:16px;font-family:'JetBrains Mono',monospace;
            font-size:0.82rem;line-height:1.7;resize:vertical;
        }
        .preview-area{min-height:320px;background:#080808;padding:16px;color:#ddd;font-size:0.85rem;line-height:1.7;overflow-y:auto;}

        .save-row{display:flex;align-items:center;gap:12px;margin-top:12px;}
        .save-btn{background:#1a0000;border:1px solid var(--red);color:var(--red-b);padding:10px 24px;border-radius:3px;font-family:inherit;font-size:0.88rem;font-weight:700;cursor:pointer;letter-spacing:0.05em;transition:all 0.2s;}
        .save-btn:hover{background:var(--red);color:#000;box-shadow:0 0 20px var(--red-g);}
        .preview-btn{background:transparent;border:1px solid var(--dim);color:var(--dim);padding:10px 18px;border-radius:3px;font-family:inherit;font-size:0.82rem;cursor:pointer;transition:all 0.15s;}
        .preview-btn:hover{border-color:#aaa;color:#aaa;}

        /* CARD */
        .card{background:var(--card);border:1px solid var(--border);border-radius:4px;padding:20px;margin-bottom:16px;}
        .card-title{color:var(--red-b);font-size:0.85rem;font-weight:700;margin-bottom:14px;letter-spacing:0.06em;}

        /* FORM */
        .form-field{margin-bottom:14px;}
        .form-label{display:block;color:var(--dim);font-size:0.75rem;margin-bottom:5px;}
        .form-input{width:100%;background:#080808;border:1px solid var(--border);border-radius:3px;padding:9px 12px;color:var(--white);font-family:inherit;font-size:0.85rem;outline:none;transition:border-color 0.2s;}
        .form-input:focus{border-color:var(--red);}

        /* LOGS */
        .logs-table{width:100%;border-collapse:collapse;font-size:0.78rem;}
        .logs-table th{color:var(--dim);padding:6px 10px;text-align:left;border-bottom:1px solid var(--border);}
        .logs-table td{padding:6px 10px;border-bottom:1px solid #111;color:#aaa;}
        .logs-table tr:hover td{background:rgba(204,0,0,0.03);}
        .log-action{color:var(--red-b);}

        /* SESSION BAR */
        .session-bar{background:#0d0000;border:1px solid var(--border);border-radius:3px;padding:8px 14px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:0.78rem;}
        .session-ok{color:var(--green);}
        .session-time{color:var(--dim);margin-left:auto;}
    </style>
</head>
<body style="position: relative; min-height: 100vh; margin: 0; padding-bottom: 120px;">
<div class="topbar">
    <div class="topbar-brand">💀 DARK ADMIN</div>
    <div class="topbar-user">$ whoami → <?php echo $admin_user; ?></div>
    <div class="topbar-time" id="clock"></div>
    <div class="topbar-nav">
    		<a href="<?php echo htmlspecialchars($logout_url); ?>" class="logout-btn">logout →</a>
    </div>
</div>

<div class="admin-layout">
    <!-- SIDEBAR -->
    <nav class="sidebar">
        <div class="sidebar-label">// CONTENU</div>
        <?php foreach ($sections as $sec): ?>
        <a href="/dark.php?section=<?php echo $sec; ?>"
           class="sidebar-link <?php echo $active_section === $sec ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($section_labels[$sec] ?? $sec); ?>
        </a>
        <?php endforeach; ?>
        <a href="/dark-blog.php" class="sidebar-link">📋 Blog manager</a>
        <hr class="sidebar-sep">
        <div class="sidebar-label">// SYSTÈME</div>
        <a href="/dark.php?section=password" class="sidebar-link <?php echo $active_section === 'password' ? 'active' : ''; ?>">🔐 Mot de passe</a>
        <a href="/dark.php?section=otp" class="sidebar-link <?php echo $active_section === 'otp' ? 'active' : ''; ?>">🔑 OTP Secret</a>
        <a href="/dark.php?section=logs" class="sidebar-link <?php echo $active_section === 'logs' ? 'active' : ''; ?>">📋 Logs</a>
        <a href="/" target="_blank" class="sidebar-link">↗ Voir le site</a>
        <a href="/blog.php" target="_blank" class="sidebar-link">↗ Voir le blog</a>
    </nav>

    <!-- MAIN -->
    <main class="admin-main">
        <div class="session-bar">
            <span class="session-ok">● SESSION ACTIVE</span>
            <span>Connecté en tant que <strong><?php echo $admin_user; ?></strong></span>
            <span class="session-time">Expire dans <span id="session-countdown"></span></span>
        </div>

        <?php if ($message): ?>
        <div class="msg <?php echo $msg_type; ?>">
            <?php echo $msg_type === 'success' ? '✓' : '⚠'; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($active_section === 'password'): ?>
        <!-- ── CHANGEMENT MOT DE PASSE ── -->
        <div class="page-title">// Modifier le mot de passe</div>
        <div class="page-sub">Minimum 12 caractères recommandé.</div>
        <div class="card">
            <form method="POST" action="/dark.php">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-field">
                    <label class="form-label">Ancien mot de passe</label>
                    <input type="password" name="old_password" class="form-input" autocomplete="current-password" required>
                </div>
                <div class="form-field">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="new_password" class="form-input" autocomplete="new-password" minlength="12" required>
                </div>
                <div class="form-field">
                    <label class="form-label">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="new_password2" class="form-input" autocomplete="new-password" minlength="12" required>
                </div>
                <button type="submit" class="save-btn">→ Changer le mot de passe</button>
            </form>
        </div>

        <?php elseif ($active_section === 'otp'): ?>
        <!-- ── CHANGEMENT OTP ── -->
        <div class="page-title">// Modifier le secret OTP</div>
        <div class="page-sub">Génère un nouveau secret sur <a href="https://totp.danhersam.com/" target="_blank" style="color:var(--red-b)">totp.danhersam.com</a> ou via <code style="color:#ddd;background:#111;padding:1px 5px">python3 -c "import pyotp; print(pyotp.random_base32())"</code>, scanne le QR code avec Google Authenticator, puis entre le code généré pour confirmer.</div>
        <div class="card">
            <form method="POST" action="/dark.php?section=otp">
                <input type="hidden" name="action" value="change_otp">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-field">
                    <label class="form-label">Mot de passe actuel (confirmation)</label>
                    <input type="password" name="current_password" class="form-input" autocomplete="current-password" required>
                </div>
                <div class="form-field">
                    <label class="form-label">Nouveau secret OTP (Base32 — ex: <span style="color:#ddd">JBSWY3DPEHPK3PXP</span>)</label>
                    <input type="text" name="new_otp_secret" class="form-input"
                           placeholder="JBSWY3DPEHPK3PXP..."
                           maxlength="64" pattern="[A-Za-z2-7]+" required
                           autocomplete="off" style="letter-spacing:0.1em">
                </div>
                <div class="form-field">
                    <label class="form-label">Code OTP de vérification (depuis l'app avec le nouveau secret)</label>
                    <input type="text" name="otp_verify" class="form-input"
                           placeholder="000000" maxlength="6"
                           pattern="[0-9]{6}" inputmode="numeric"
                           autocomplete="one-time-code" required>
                    <div style="color:var(--dim);font-size:0.72rem;margin-top:4px;">// Scanne d'abord le nouveau secret dans Google Authenticator, puis entre le code ici pour valider.</div>
                </div>
                <button type="submit" class="save-btn">→ Mettre à jour le secret OTP</button>
            </form>
        </div>

        <?php elseif ($active_section === 'logs'): ?>
        <!-- ── LOGS ── -->
        <div class="page-title">// Journaux d'activité</div>
        <div class="page-sub">20 dernières entrées.</div>
        <div class="card">
            <table class="logs-table">
                <thead>
                    <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>IP</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($log['username'] ?? '—'); ?></td>
                        <td class="log-action"><?php echo htmlspecialchars($log['action']); ?></td>
                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- ── ÉDITEUR DE SECTION ── -->
        <div class="page-title">// Éditer : <?php echo htmlspecialchars($section_labels[$active_section] ?? $active_section); ?></div>
        <div class="page-sub">Le contenu est du HTML sanitizé. Les balises de script et attributs dangereux sont supprimés automatiquement.</div>

        <form method="POST" action="/dark.php?section=<?php echo urlencode($active_section); ?>" id="editorForm">
            <input type="hidden" name="action" value="save_section">
            <input type="hidden" name="section" value="<?php echo htmlspecialchars($active_section); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="content" id="content_hidden">

            <div class="editor-tabs">
                <button type="button" class="editor-tab active" onclick="switchTab('html')">HTML</button>
                <button type="button" class="editor-tab" onclick="switchTab('preview')">Prévisualisation</button>
            </div>
            <div class="editor-wrap">
                <div class="editor-toolbar" id="toolbar">
                    <button type="button" class="tb-btn" onclick="wrap('&lt;strong&gt;','&lt;/strong&gt;')"><b>B</b></button>
                    <button type="button" class="tb-btn" onclick="wrap('&lt;em&gt;','&lt;/em&gt;')"><i>I</i></button>
                    <button type="button" class="tb-btn" onclick="wrap('&lt;u&gt;','&lt;/u&gt;')"><u>U</u></button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn" onclick="wrap('&lt;span class=&quot;hl&quot;&gt;','&lt;/span&gt;')">HL</button>
                    <button type="button" class="tb-btn" onclick="insertTag('&lt;br&gt;')">BR</button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn" onclick="wrap('&lt;ul&gt;\n  &lt;li&gt;','&lt;/li&gt;\n&lt;/ul&gt;')">UL</button>
                    <button type="button" class="tb-btn" onclick="wrap('&lt;p&gt;','&lt;/p&gt;')">P</button>
                    <button type="button" class="tb-btn" onclick="wrap('&lt;div class=&quot;card&quot;&gt;','&lt;/div&gt;')">DIV</button>
                    <div class="tb-sep"></div>
                    <button type="button" class="tb-btn" onclick="wrap('&lt;a href=&quot;https://&quot; target=&quot;_blank&quot; class=&quot;term-link&quot;&gt;','&lt;/a&gt;')">LINK</button>
                    <button type="button" class="tb-btn" onclick="wrap('&lt;code&gt;','&lt;/code&gt;')">CODE</button>
                </div>
                <textarea id="editor-area" name="_editor_display"
                    placeholder="// Entrez votre HTML ici..."><?php echo htmlspecialchars($current_content); ?></textarea>
                <div class="preview-area" id="preview-area" style="display:none"></div>
            </div>

            <div class="save-row">
                <button type="submit" class="save-btn" onclick="syncContent()">→ Sauvegarder</button>
                <button type="button" class="preview-btn" onclick="switchTab('preview')">Prévisualiser</button>
                <span style="color:var(--dim);font-size:0.75rem;margin-left:auto;">Ctrl+S pour sauvegarder</span>
            </div>
        </form>
        <?php endif; ?>
    </main>
</div>

<script>
// ── Clock ──
function updateClock() {
    document.getElementById('clock').textContent = new Date().toLocaleTimeString('fr-FR');
}
updateClock(); setInterval(updateClock, 1000);

// ── Session countdown ──
const loginTime = <?php echo json_encode($_SESSION['login_time']); ?>;
const maxLife   = <?php echo SESSION_LIFETIME; ?>;
function updateCountdown() {
    const elapsed = Math.floor(Date.now() / 1000) - loginTime;
    const left = maxLife - elapsed;
    if (left <= 0) { location.href = '/dark-login.php?expired=1'; return; }
    const h = Math.floor(left / 3600);
    const m = Math.floor((left % 3600) / 60);
    const s = left % 60;
    document.getElementById('session-countdown').textContent =
        `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}
updateCountdown(); setInterval(updateCountdown, 1000);

// ── Editor tabs ──
function switchTab(tab) {
    const htmlEl = document.getElementById('editor-area');
    const prevEl = document.getElementById('preview-area');
    const tabs   = document.querySelectorAll('.editor-tab');
    const toolbar = document.getElementById('toolbar');
    if (tab === 'html') {
        htmlEl.style.display = ''; prevEl.style.display = 'none';
        tabs[0].classList.add('active'); tabs[1].classList.remove('active');
        if(toolbar) toolbar.style.display = '';
    } else {
        prevEl.innerHTML = htmlEl.value;
        htmlEl.style.display = 'none'; prevEl.style.display = '';
        tabs[0].classList.remove('active'); tabs[1].classList.add('active');
        if(toolbar) toolbar.style.display = 'none';
    }
}

// ── Toolbar helpers ──
function wrap(open, close) {
    const ta = document.getElementById('editor-area');
    const start = ta.selectionStart, end = ta.selectionEnd;
    const sel = ta.value.substring(start, end);
    const decoded_open = open.replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g,'"');
    const decoded_close = close.replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&quot;/g,'"');
    ta.value = ta.value.substring(0, start) + decoded_open + sel + decoded_close + ta.value.substring(end);
    ta.focus();
}
function insertTag(tag) {
    const ta = document.getElementById('editor-area');
    const pos = ta.selectionStart;
    const decoded = tag.replace(/&lt;/g,'<').replace(/&gt;/g,'>');
    ta.value = ta.value.substring(0, pos) + decoded + ta.value.substring(pos);
    ta.focus();
}

function syncContent() {
    const hidden = document.getElementById('content_hidden');
    const editor = document.getElementById('editor-area');
    if (hidden && editor) hidden.value = editor.value;
}

// ── Ctrl+S ──
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        syncContent();
        document.getElementById('editorForm')?.submit();
    }
});

// Sync on submit
document.getElementById('editorForm')?.addEventListener('submit', syncContent);
</script>
    <div style="position: absolute; bottom: 20px; right: 20px; text-align: center; font-family: 'JetBrains Mono', sans-serif; color: #fff; z-index: 100;">
        <img src="assets/arch.png" alt="Arch Logo" style="display: block; margin: 0 auto 5px auto; max-width: 50px;">
        <span style="font-size: 12px; font-weight: bold;">I use Arch btw.</span>
    </div>
</body>
</html>
