<?php
// ══════════════════════════════════════════════════════════════
// blog_helpers.php — Fonctions partagées blog
// ══════════════════════════════════════════════════════════════

// ── Récupérer les N derniers posts publiés ──
function getLatestPosts(PDO $pdo, int $limit = 4): array {
    $stmt = $pdo->prepare(
        "SELECT id, slug, title, created_at, updated_at
         FROM blog_posts
         WHERE is_published = 1
         ORDER BY created_at DESC
         LIMIT :lim"
    );
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ── Récupérer tous les posts publiés (pagination possible) ──
function getAllPosts(PDO $pdo, int $page = 1, int $per_page = 20): array {
    $offset = ($page - 1) * $per_page;
    $stmt = $pdo->prepare(
        "SELECT id, slug, title, created_at, updated_at
         FROM blog_posts
         WHERE is_published = 1
         ORDER BY created_at DESC
         LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue(':lim', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function countAllPosts(PDO $pdo): int {
    return (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE is_published = 1")->fetchColumn();
}

// ── Récupérer un post par slug (lecture publique) ──
function getPostBySlug(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare(
        "SELECT bp.*, au.username AS author
         FROM blog_posts bp
         LEFT JOIN admin_users au ON bp.created_by = au.id
         WHERE bp.slug = ? AND bp.is_published = 1
         LIMIT 1"
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ── Récupérer un post par ID (admin uniquement, tous statuts) ──
function getPostByIdAdmin(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare(
        "SELECT bp.*, au.username AS author
         FROM blog_posts bp
         LEFT JOIN admin_users au ON bp.created_by = au.id
         WHERE bp.id = ?
         LIMIT 1"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// ── Générer un slug unique depuis un titre ──
function generateSlug(PDO $pdo, string $title, ?int $exclude_id = null): string {
    $slug = strtolower(trim($title));
    // Translittération basique fr → ASCII
    $from = ['à','â','ä','é','è','ê','ë','î','ï','ô','ö','ù','û','ü','ç','ñ','æ','œ'];
    $to   = ['a','a','a','e','e','e','e','i','i','o','o','u','u','u','c','n','ae','oe'];
    $slug = str_replace($from, $to, $slug);
    $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
    $slug = preg_replace('/[\s\-]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 180);

    // S'assurer de l'unicité
    $base = $slug;
    $i = 1;
    while (true) {
        if ($exclude_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $exclude_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        if ((int)$stmt->fetchColumn() === 0) break;
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

// ── Sanitize HTML (même logique que dark.php) ──
function sanitizeBlogHTML(string $html): string {
    if (!$html) return '';
    $allowed_tags = [
        'p','br','strong','em','b','i','u','span','div','ul','ol','li',
        'a','h1','h2','h3','h4','h5','h6','code','pre','blockquote',
        'table','thead','tbody','tr','th','td','hr','mark','small',
        'dl','dt','dd','sup','sub','abbr','cite','figure','figcaption',
    ];
    $dangerous_attrs = [
        'onload','onerror','onclick','onmouseover','onfocus','onblur',
        'onchange','onsubmit','onkeyup','onkeydown','onkeypress',
    ];

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="_wrap">' . $html . '</div>',
                   LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    sanitizeNode($doc->documentElement, $allowed_tags, $dangerous_attrs);

    $wrap   = $doc->getElementById('_wrap');
    $result = '';
    if ($wrap) {
        foreach ($wrap->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }
    }
    return $result;
}

function sanitizeNode(DOMNode $node, array $allowed, array $bad_attrs): void {
    $to_remove = [];
    foreach ($node->childNodes as $child) {
        if (!($child instanceof DOMElement)) continue;
        if (!in_array(strtolower($child->nodeName), $allowed, true)) {
            $to_remove[] = $child;
        } else {
            $rm = [];
            foreach ($child->attributes as $attr) {
                $name = strtolower($attr->name);
                $val  = strtolower($attr->value);
                if (in_array($name, $bad_attrs, true)
                    || str_starts_with($val, 'javascript:')
                    || str_starts_with($val, 'data:')
                    || str_starts_with($val, 'vbscript:')) {
                    $rm[] = $name;
                }
                if (in_array($name, ['href','src','action'], true)) {
                    if (!preg_match('/^(https?:\/\/|mailto:|tel:|#|\/)/i', $attr->value)) {
                        $rm[] = $name;
                    }
                }
            }
            foreach ($rm as $a) $child->removeAttribute($a);
            sanitizeNode($child, $allowed, $bad_attrs);
        }
    }
    foreach ($to_remove as $el) {
        while ($el->firstChild) $node->insertBefore($el->firstChild, $el);
        $node->removeChild($el);
    }
}

// ── Vérifier l'authentification admin (gate centralisée) ──
function requireAdminAuth(): void {
    // Les ini_set doivent être faits AVANT session_start
    // Cette fonction suppose que la session est déjà démarrée
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: /dark-login.php');
        exit;
    }
    define('SESSION_LIFETIME_BH', 10800);
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_LIFETIME_BH) {
        session_destroy();
        header('Location: /dark-login.php?expired=1');
        exit;
    }
}

// ── Vérifier CSRF ──
function checkCSRF(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ── Formatter une date FR ──
function formatDateFR(string $datetime, bool $with_time = true): string {
    $ts = strtotime($datetime);
    $d  = date('d/m/Y', $ts);
    $h  = date('H:i', $ts);
    return $with_time ? "$d à $h" : $d;
}

// ── Extraire un extrait texte d'un HTML ──
function htmlExcerpt(string $html, int $length = 160): string {
    $text = strip_tags($html);
    $text = preg_replace('/\s+/', ' ', trim($text));
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '…';
}
