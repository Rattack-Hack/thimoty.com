<?php
// ══════════════════════════════════════════════════════════════
// DARK-LOGIN.PHP — Page de connexion administration sécurisée
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '../../config/db.php';

// ── Session sécurisée ──
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_name('__Host-ADMIN_SESS');
session_start();

// ── Si déjà connecté, rediriger ──
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /dark.php');
    exit;
}

$pdo = getPDO();
$error = '';
$ip = $_SERVER['REMOTE_ADDR'];

// ── Constantes anti brute-force ──
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes en secondes

// ── Nettoyage des vieilles tentatives (> 15 min) ──
$pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->execute();

// ── Vérification brute force ──
function isLockedOut(PDO $pdo, string $ip): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();
    return (int)$row['cnt'] >= MAX_ATTEMPTS;
}

function getRemainingLockout(PDO $pdo, string $ip): int {
    $stmt = $pdo->prepare("SELECT MIN(attempt_time) as first FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();
    if (!$row['first']) return 0;
    $first = strtotime($row['first']);
    $remaining = ($first + LOCKOUT_DURATION) - time();
    return max(0, $remaining);
}

$locked = isLockedOut($pdo, $ip);
$lockRemaining = $locked ? getRemainingLockout($pdo, $ip) : 0;

// ── Génération token CSRF ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Traitement du formulaire ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {

    // Vérification CSRF
    $submitted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submitted_csrf)) {
        $error = 'Token CSRF invalide.';
    } else {
        // Sanitize inputs
        $username = trim(strip_tags($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $otp      = trim(strip_tags($_POST['otp'] ?? ''));

        if (empty($username) || empty($password) || empty($otp)) {
            $error = 'Tous les champs sont requis.';
        } elseif (strlen($username) > 64 || strlen($password) > 256 || strlen($otp) > 6) {
            $error = 'Données invalides.';
        } else {
            // Récupérer l'utilisateur
            $stmt = $pdo->prepare("SELECT id, username, password_hash, otp_secret, is_active FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            $authOk = false;

            if ($user && $user['is_active']) {
                // Vérifier le mot de passe bcrypt
                if (password_verify($password, $user['password_hash'])) {
                    // Vérifier l'OTP Google Authenticator (TOTP)
                    if (verifyTOTP($user['otp_secret'], $otp)) {
                        $authOk = true;
                    }
                }
            }

            if ($authOk) {
                // Succès — supprimer les tentatives pour cette IP
                $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);

                // Régénérer la session
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = $user['id'];
                $_SESSION['admin_user']      = $user['username'];
                $_SESSION['login_time']      = time();
                $_SESSION['csrf_token']      = bin2hex(random_bytes(32)); // reset CSRF

                // Logger la connexion
                $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, 'LOGIN_SUCCESS', ?)")
                    ->execute([$user['id'], $ip]);

                header('Location: /dark.php');
                exit;
            } else {
                // Enregistrer la tentative échouée
                $pdo->prepare("INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())")->execute([$ip]);
                $attempts_left = MAX_ATTEMPTS - (int)(function() use ($pdo, $ip) {
                    $s = $pdo->prepare("SELECT COUNT(*) as c FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                    $s->execute([$ip]);
                    return $s->fetch()['c'];
                })();
                if ($attempts_left <= 0) {
                    $error = 'Trop de tentatives. Compte bloqué 15 minutes.';
                    $locked = true;
                } else {
                    $error = sprintf('Identifiants incorrects. %d tentative(s) restante(s).', max(0, $attempts_left));
                }

                // Logger l'échec
                $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (NULL, 'LOGIN_FAILED', ?)")->execute([$ip]);
            }
        }
    }
}

// ══════════════════════════════════════════════
// TOTP / Google Authenticator
// ══════════════════════════════════════════════
function verifyTOTP(string $secret, string $code, int $window = 1): bool {
    $secret = base32Decode($secret);
    $timestamp = floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        $t = pack('N*', 0) . pack('N*', $timestamp + $i);
        $hash = hash_hmac('sha1', $t, $secret, true);
        $offset = ord($hash[19]) & 0xf;
        $otp = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            ((ord($hash[$offset + 3]) & 0xff))
        ) % 1000000;
        if (str_pad($otp, 6, '0', STR_PAD_LEFT) === $code) return true;
    }
    return false;
}

function base32Decode(string $b32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper($b32);
    $bits = '';
    foreach (str_split($b32) as $char) {
        $val = strpos($alphabet, $char);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $result = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) $result .= chr(bindec($byte));
    }
    return $result;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied — rattack@Rattack-Box</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <style>
        :root {
            --red: #cc0000; --red-b: #ff2222; --red-g: rgba(204,0,0,0.3);
            --bg: #080808; --card: #0e0e0e; --border: #2a0000;
            --text: #cc2200; --dim: #661100; --white: #dddddd;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: var(--bg); color: var(--text);
            font-family: 'JetBrains Mono', monospace;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 20px;
        }
        .scanlines {
            position: fixed; inset: 0; z-index: 99; pointer-events: none;
            background: repeating-linear-gradient(to bottom, transparent, transparent 2px, rgba(0,0,0,0.08) 2px, rgba(0,0,0,0.08) 4px);
        }
        .login-box {
            width: 100%; max-width: 480px;
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 0 60px var(--red-g);
        }
        .login-bar {
            background: #1a0000;
            border-bottom: 1px solid var(--border);
            padding: 10px 16px;
            display: flex; align-items: center; gap: 10px;
        }
        .dots { display: flex; gap: 6px; }
        .dot { width: 12px; height: 12px; border-radius: 50%; }
        .dr { background: #ff5f57; } .dy { background: #ffbd2e; } .dg { background: #28c940; }
        .bar-title { flex: 1; text-align: center; color: var(--dim); font-size: 0.8rem; }

        .login-body { background: var(--card); padding: 32px 28px; }

        .skull {
            text-align: center; font-size: 3rem; margin-bottom: 8px;
            filter: drop-shadow(0 0 12px var(--red));
        }
        .login-title {
            text-align: center; font-size: 1rem; color: var(--red-b);
            margin-bottom: 4px; font-weight: 700; letter-spacing: 0.1em;
        }
        .login-sub { text-align: center; color: var(--dim); font-size: 0.75rem; margin-bottom: 28px; }

        .field { margin-bottom: 16px; }
        .field label { display: block; color: var(--dim); font-size: 0.75rem; margin-bottom: 6px; }
        .field label span { color: var(--red-b); margin-right: 4px; }
        .field input {
            width: 100%;
            background: #080808;
            border: 1px solid var(--border);
            border-radius: 3px;
            padding: 10px 12px;
            color: var(--white);
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .field input:focus { border-color: var(--red); box-shadow: 0 0 12px rgba(204,0,0,0.2); }
        .field input::placeholder { color: #333; }

        .error-box {
            background: #1a0000; border: 1px solid var(--red);
            border-radius: 3px; padding: 10px 14px;
            color: var(--red-b); font-size: 0.82rem;
            margin-bottom: 16px; display: flex; align-items: center; gap: 8px;
        }
        .locked-box {
            background: #1a0d00; border: 1px solid #cc5500;
            border-radius: 3px; padding: 14px;
            color: #cc5500; font-size: 0.82rem;
            margin-bottom: 16px; text-align: center;
        }

        .submit-btn {
            width: 100%; padding: 12px;
            background: #1a0000;
            border: 1px solid var(--red);
            border-radius: 3px;
            color: var(--red-b); font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.08em;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .submit-btn:hover:not(:disabled) { background: var(--red); color: #000; box-shadow: 0 0 20px var(--red-g); }
        .submit-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        .back-link { display: block; text-align: center; margin-top: 16px; color: var(--dim); font-size: 0.75rem; text-decoration: none; }
        .back-link:hover { color: var(--text); }

        .otp-hint { color: var(--dim); font-size: 0.72rem; margin-top: 4px; }
    </style>
</head>
<body>
<div class="scanlines"></div>
<div class="login-box">
    <div class="login-bar">
        <div class="dots"><span class="dot dr"></span><span class="dot dy"></span><span class="dot dg"></span></div>
        <div class="bar-title">root@dark-admin: ~/.auth</div>
    </div>
    <div class="login-body">
        <div class="skull">💀</div>
        <div class="login-title">RESTRICTED ACCESS</div>
        <div class="login-sub">Authentification requise · Toute tentative est tracée</div>

        <?php if ($locked): ?>
        <div class="locked-box">
            ⚠ ACCÈS BLOQUÉ<br>
            Trop de tentatives échouées.<br>
            Réessayez dans <?php echo ceil($lockRemaining / 60); ?> minute(s).
        </div>
        <?php elseif ($error): ?>
        <div class="error-box">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="/dark-login.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="field">
                <label><span>$</span> username</label>
                <input type="text" name="username" placeholder="root" maxlength="64"
                    autocomplete="off" <?php echo $locked ? 'disabled' : ''; ?> required>
            </div>
            <div class="field">
                <label><span>$</span> password</label>
                <input type="password" name="password" placeholder="••••••••" maxlength="256"
                    autocomplete="new-password" <?php echo $locked ? 'disabled' : ''; ?> required>
            </div>
            <div class="field">
                <label><span>$</span> otp_code <em>(Google Authenticator)</em></label>
                <input type="text" name="otp" placeholder="000000" maxlength="6"
                    pattern="[0-9]{6}" inputmode="numeric"
                    autocomplete="one-time-code" <?php echo $locked ? 'disabled' : ''; ?> required>
                <div class="otp-hint">// Code TOTP à 6 chiffres</div>
            </div>
            <button type="submit" class="submit-btn" <?php echo $locked ? 'disabled' : ''; ?>>
                → AUTHENTICATE
            </button>
        </form>
        <a class="back-link" href="/">← Retour au portfolio</a>
    </div>
</div>
</body>
</html>
