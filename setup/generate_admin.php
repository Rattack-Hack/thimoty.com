<?php
// ══════════════════════════════════════════════════════════════
// setup/generate_admin.php
// Script à exécuter UNE SEULE FOIS en CLI pour créer l'admin.
// Supprimer ce fichier après utilisation !
//
// Usage : php setup/generate_admin.php
// ══════════════════════════════════════════════════════════════

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Ce script ne peut être exécuté qu\'en CLI.');
}

echo "\n══════════════════════════════════════════════\n";
echo "  GÉNÉRATEUR DE COMPTE ADMIN — Portfolio\n";
echo "══════════════════════════════════════════════\n\n";

// ── Saisie sécurisée ──
echo "Nom d'utilisateur : ";
$username = trim(fgets(STDIN));
if (empty($username) || strlen($username) > 64) {
    die("Erreur : nom d'utilisateur invalide.\n");
}

// Mot de passe (masqué si possible)
if (function_exists('readline') && PHP_OS_FAMILY !== 'Windows') {
    system('stty -echo');
    echo "Mot de passe (min 12 chars) : ";
    $password = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
    echo "Confirmer le mot de passe : ";
    $password2 = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
} else {
    echo "Mot de passe (min 12 chars) : ";
    $password = trim(fgets(STDIN));
    echo "Confirmer le mot de passe : ";
    $password2 = trim(fgets(STDIN));
}

if ($password !== $password2) die("Erreur : les mots de passe ne correspondent pas.\n");
if (strlen($password) < 12) die("Erreur : mot de passe trop court (min 12 chars).\n");

// ── Hash bcrypt ──
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// ── Génération secret TOTP ──
$secret = generateTOTPSecret();
$qr_url = 'otpauth://totp/Portfolio%3A' . urlencode($username)
        . '?secret=' . $secret
        . '&issuer=RattackPortfolio'
        . '&algorithm=SHA1&digits=6&period=30';
$qr_img = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qr_url);

echo "\n══════════════════════════════════════════════\n";
echo "  RÉSULTATS — À copier dans schema.sql\n";
echo "══════════════════════════════════════════════\n\n";

echo "Username      : $username\n";
echo "Bcrypt hash   : $hash\n";
echo "OTP Secret    : $secret\n\n";

echo "── Commande SQL à exécuter ──\n";
echo "INSERT INTO admin_users (username, password_hash, otp_secret) VALUES (\n";
echo "    '" . addslashes($username) . "',\n";
echo "    '$hash',\n";
echo "    '$secret'\n";
echo ");\n\n";

echo "── QR Code Google Authenticator ──\n";
echo "Ouvrez cette URL dans votre navigateur pour scanner le QR code :\n";
echo $qr_img . "\n\n";

echo "── Lien otpauth (import manuel) ──\n";
echo $qr_url . "\n\n";

echo "⚠ IMPORTANT : Supprimez ce fichier après utilisation !\n";
echo "  rm setup/generate_admin.php\n\n";

// ══════════════════════════════════════════════
// TOTP Secret generator
// ══════════════════════════════════════════════
function generateTOTPSecret(int $length = 20): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    $random = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[ord($random[$i]) & 31];
    }
    return $secret;
}
