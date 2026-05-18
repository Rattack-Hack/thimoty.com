<?php
// ══════════════════════════════════════════════════════════════
// dark-blog.php — Gestion des posts de blog (admin uniquement)
// Protégé : authentification + CSRF + PDO + sanitize XSS
// IDOR prevention : les IDs sont toujours validés en DB avec PDO
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/blog_helpers.php';

// ── Session sécurisée ──
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_name('__Host-ADMIN_SESS');
session_start();

// ── Gate d'authentification centralisée ──
requireAdminAuth();

$pdo        = getPDO();
$admin_id   = (int)$_SESSION['admin_id'];
$admin_user = htmlspecialchars($_SESSION['admin_user'] ?? 'admin');
$message    = '';
$msg_type   = '';

// ── CSRF token ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ══════════════════════════════════════════════════════════════
// ACTIONS POST
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Vérification CSRF systématique ──
    if (!checkCSRF($_POST['csrf_token'] ?? '')) {
        $message = 'Token CSRF invalide.'; $msg_type = 'error';
        goto render;
    }

    // ────────────────────────────────────────
    // CRÉER un post
    // ────────────────────────────────────────
    if ($action === 'create_post') {
        $title       = trim(strip_tags($_POST['title'] ?? ''));
        $content_raw = $_POST['content'] ?? '';
        $published   = isset($_POST['is_published']) ? 1 : 0;

        if (empty($title) || strlen($title) > 300) {
            $message = 'Titre invalide (1–300 caractères).'; $msg_type = 'error';
        } else {
            $content_html = sanitizeBlogHTML($content_raw);
            $slug         = generateSlug($pdo, $title);

            $stmt = $pdo->prepare(
                "INSERT INTO blog_posts (slug, title, content_html, is_published, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$slug, $title, $content_html, $published, $admin_id]);
            $new_id = (int)$pdo->lastInsertId();

            $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, ?, ?)")
                ->execute([$admin_id, "BLOG_CREATE:$new_id", $_SERVER['REMOTE_ADDR']]);

            $message = "Post \"$title\" créé avec succès (ID: $new_id, slug: $slug).";
            $msg_type = 'success';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Rediriger vers l'éditeur du post créé
            header('Location: /dark-blog.php?action=edit&id=' . $new_id . '&msg=created');
            exit;
        }

    // ────────────────────────────────────────
    // MODIFIER un post
    // ────────────────────────────────────────
    } elseif ($action === 'update_post') {
        // Validation stricte de l'ID (IDOR prevention)
        $post_id = filter_var($_POST['post_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$post_id) {
            $message = 'ID de post invalide.'; $msg_type = 'error'; goto render;
        }

        // Vérifier que le post existe VRAIMENT en DB (pas de manipulation d'ID)
        $existing = getPostByIdAdmin($pdo, (int)$post_id);
        if (!$existing) {
            $message = 'Post introuvable.'; $msg_type = 'error'; goto render;
        }

        $title       = trim(strip_tags($_POST['title'] ?? ''));
        $content_raw = $_POST['content'] ?? '';
        $published   = isset($_POST['is_published']) ? 1 : 0;

        if (empty($title) || strlen($title) > 300) {
            $message = 'Titre invalide.'; $msg_type = 'error'; goto render;
        }

        $content_html = sanitizeBlogHTML($content_raw);
        // Régénérer le slug uniquement si le titre change
        $slug = ($title !== $existing['title'])
            ? generateSlug($pdo, $title, (int)$post_id)
            : $existing['slug'];

        $stmt = $pdo->prepare(
            "UPDATE blog_posts
             SET title = ?, slug = ?, content_html = ?, is_published = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$title, $slug, $content_html, $published, $admin_id, (int)$post_id]);

        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, ?, ?)")
            ->execute([$admin_id, "BLOG_UPDATE:$post_id", $_SERVER['REMOTE_ADDR']]);

        $message = "Post #$post_id mis à jour."; $msg_type = 'success';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // ────────────────────────────────────────
    // SUPPRIMER un post
    // ────────────────────────────────────────
    } elseif ($action === 'delete_post') {
        // IDOR prevention : validation ID stricte + vérification DB
        $post_id = filter_var($_POST['post_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$post_id) {
            $message = 'ID invalide.'; $msg_type = 'error'; goto render;
        }

        $existing = getPostByIdAdmin($pdo, (int)$post_id);
        if (!$existing) {
            $message = 'Post introuvable.'; $msg_type = 'error'; goto render;
        }

        // Confirmation requise (champ caché dans le formulaire)
        if (($_POST['confirm_delete'] ?? '') !== 'DELETE') {
            $message = 'Confirmation de suppression manquante.'; $msg_type = 'error'; goto render;
        }

        $pdo->prepare("DELETE FROM blog_posts WHERE id = ?")->execute([(int)$post_id]);
        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, ip_address) VALUES (?, ?, ?)")
            ->execute([$admin_id, "BLOG_DELETE:$post_id({$existing['slug']})", $_SERVER['REMOTE_ADDR']]);

        $message = "Post #$post_id \"{$existing['title']}\" supprimé."; $msg_type = 'success';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: /dark-blog.php?deleted=1');
        exit;
    }
}

// ══════════════════════════════════════════════════════════════
// DÉTERMINER LA VUE
// ══════════════════════════════════════════════════════════════
render:

$view    = $_GET['action'] ?? 'list'; // list | create | edit
$edit_post = null;

if ($view === 'edit') {
    // IDOR prevention sur GET aussi
    $get_id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($get_id) {
        $edit_post = getPostByIdAdmin($pdo, (int)$get_id);
    }
    if (!$edit_post) {
        $message = 'Post introuvable.'; $msg_type = 'error';
        $view = 'list';
    }
}

// Message de redirect
if (isset($_GET['deleted']) && empty($message)) { $message = 'Post supprimé.'; $msg_type = 'success'; }
if (isset($_GET['msg']) && $_GET['msg'] === 'created' && empty($message)) { $message = 'Post créé avec succès.'; $msg_type = 'success'; }

// Liste tous les posts (admin voit brouillons et publiés)
$all_posts = $pdo->query(
    "SELECT id, slug, title, is_published, created_at, updated_at
     FROM blog_posts ORDER BY created_at DESC"
)->fetchAll();

$logout_url = '/dark.php?logout=1&csrf=' . urlencode($_SESSION['csrf_token']);

// Toolbar HTML pour l'éditeur
$toolbar_html = <<<'HTML'
<div class="editor-toolbar" id="toolbar">
    <button type="button" class="tb-btn" onclick="wrapSel('<strong>','</strong>')"><b>B</b></button>
    <button type="button" class="tb-btn" onclick="wrapSel('<em>','</em>')"><i>I</i></button>
    <button type="button" class="tb-btn" onclick="wrapSel('<u>','</u>')"><u>U</u></button>
    <div class="tb-sep"></div>
    <button type="button" class="tb-btn" onclick="wrapSel('<h2>','</h2>')">H2</button>
    <button type="button" class="tb-btn" onclick="wrapSel('<h3>','</h3>')">H3</button>
    <button type="button" class="tb-btn" onclick="wrapSel('<p>','</p>')">P</button>
    <div class="tb-sep"></div>
    <button type="button" class="tb-btn" onclick="wrapSel('<ul>\n  <li>','</li>\n</ul>')">UL</button>
    <button type="button" class="tb-btn" onclick="wrapSel('<ol>\n  <li>','</li>\n</ol>')">OL</button>
    <button type="button" class="tb-btn" onclick="insertAtCursor('<br>')">BR</button>
    <div class="tb-sep"></div>
    <button type="button" class="tb-btn" onclick="wrapSel('<code>','</code>')">CODE</button>
    <button type="button" class="tb-btn" onclick="wrapSel('<pre><code>','</code></pre>')">PRE</button>
    <button type="button" class="tb-btn" onclick="wrapSel('<blockquote>','</blockquote>')">QUOTE</button>
    <div class="tb-sep"></div>
    <button type="button" class="tb-btn" onclick="wrapSel('<a href=&quot;https://&quot; target=&quot;_blank&quot; class=&quot;term-link&quot;>','</a>')">LINK</button>
    <button type="button" class="tb-btn" onclick="wrapSel('<span class=&quot;hl&quot;>','</span>')">HL</button>
    <button type="button" class="tb-btn" onclick="wrapSel('<mark>','</mark>')">MARK</button>
    <button type="button" class="tb-btn" onclick="insertAtCursor('<hr>')" title="Séparateur">HR</button>
</div>
HTML;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Admin — rattack@Rattack-Box</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <style>
        :root{--red:#cc0000;--red-b:#ff2222;--red-g:rgba(204,0,0,0.25);--bg:#080808;--sidebar:#0d0d0d;--card:#111;--border:#1e0000;--text:#cc2200;--dim:#661100;--white:#ddd;--green:#00aa44;--yellow:#ccaa00;--orange:#cc5500;}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        body{background:var(--bg);color:var(--text);font-family:'JetBrains Mono',monospace;min-height:100vh;display:flex;flex-direction:column;}
        a{color:var(--red);text-decoration:none;} a:hover{color:var(--red-b);}

        .topbar{background:#0e0e0e;border-bottom:1px solid var(--border);padding:10px 20px;display:flex;align-items:center;gap:16px;}
        .topbar-brand{color:var(--red-b);font-weight:700;letter-spacing:0.1em;font-size:0.9rem;}
        .topbar-user{color:var(--dim);font-size:0.8rem;}
	   .topbar-time{color:var(--dim);font-size:0.75rem;}
        .topbar-nav{display:flex;gap:6px;margin-left:auto;align-items:center;}
        .nav-pill{background:#1a0000;border:1px solid var(--border);color:#aaa;padding:4px 12px;border-radius:3px;font-size:0.78rem;transition:all 0.15s;}
        .nav-pill:hover,.nav-pill.active{border-color:var(--red);color:var(--red-b);}
        .logout-btn{background:#1a0000;border:1px solid #330000;color:var(--red);padding:5px 12px;border-radius:3px;font-family:inherit;font-size:0.78rem;cursor:pointer;}
        .logout-btn:hover{background:var(--red);color:#000;}

        .admin-layout{display:flex;flex:1;}
        .sidebar{width:220px;min-width:200px;background:var(--sidebar);border-right:1px solid var(--border);padding:16px 0;display:flex;flex-direction:column;gap:2px;}
        .sidebar-label{color:var(--dim);font-size:0.7rem;padding:8px 16px 4px;letter-spacing:0.08em;}
        .sidebar-link{display:flex;align-items:center;justify-content:space-between;padding:8px 16px;color:#aaa;font-size:0.82rem;border-left:2px solid transparent;transition:all 0.15s;}
        .sidebar-link:hover{color:var(--red-b);border-left-color:var(--red);}
        .sidebar-link.active{color:var(--red-b);border-left-color:var(--red);background:rgba(204,0,0,0.05);}
        .sidebar-sep{border:none;border-top:1px solid var(--border);margin:8px 0;}
        .post-badge{background:#1a0000;border:1px solid var(--border);color:var(--dim);padding:1px 6px;border-radius:10px;font-size:0.68rem;}
        .badge-pub{border-color:var(--green);color:var(--green);background:#001a0d;}
        .badge-draft{border-color:var(--yellow);color:var(--yellow);background:#1a1500;}

        .admin-main{flex:1;padding:24px;overflow-y:auto;}
        .page-title{color:var(--red-b);font-size:1rem;font-weight:700;margin-bottom:4px;letter-spacing:0.06em;}
        .page-sub{color:var(--dim);font-size:0.75rem;margin-bottom:20px;}

        .msg{padding:10px 14px;border-radius:3px;font-size:0.82rem;margin-bottom:16px;display:flex;gap:8px;align-items:center;}
        .msg.success{background:#001a0d;border:1px solid var(--green);color:var(--green);}
        .msg.error{background:#1a0000;border:1px solid var(--red);color:var(--red-b);}

        .session-bar{background:#0d0000;border:1px solid var(--border);border-radius:3px;padding:8px 14px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:0.78rem;}
        .session-ok{color:var(--green);}
        .session-time{color:var(--dim);margin-left:auto;}

        /* POSTS TABLE */
        .posts-table{width:100%;border-collapse:collapse;font-size:0.78rem;}
        .posts-table th{color:var(--dim);padding:8px 10px;text-align:left;border-bottom:1px solid var(--border);white-space:nowrap;}
        .posts-table td{padding:8px 10px;border-bottom:1px solid #0f0f0f;color:#aaa;vertical-align:middle;}
        .posts-table tr:hover td{background:rgba(204,0,0,0.03);}
        .post-id{color:var(--dim);font-size:0.72rem;}
        .post-title-cell{color:#ddd;max-width:280px;}
        .post-title-link{color:#ddd;} .post-title-link:hover{color:var(--red-b);}
        .post-slug-cell{color:var(--dim);font-size:0.7rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .action-btns{display:flex;gap:6px;white-space:nowrap;}
        .btn-edit{background:#001a0d;border:1px solid var(--green);color:var(--green);padding:3px 10px;border-radius:2px;font-family:inherit;font-size:0.72rem;cursor:pointer;text-decoration:none;transition:all 0.15s;}
        .btn-edit:hover{background:var(--green);color:#000;}
        .btn-del{background:#1a0000;border:1px solid var(--red);color:var(--red);padding:3px 10px;border-radius:2px;font-family:inherit;font-size:0.72rem;cursor:pointer;transition:all 0.15s;}
        .btn-del:hover{background:var(--red);color:#000;}
        .btn-view{background:#000d1a;border:1px solid #003366;color:#0066cc;padding:3px 10px;border-radius:2px;font-family:inherit;font-size:0.72rem;cursor:pointer;text-decoration:none;transition:all 0.15s;}
        .btn-view:hover{background:#0066cc;color:#000;}
        .new-btn{background:#1a0000;border:1px solid var(--red);color:var(--red-b);padding:8px 18px;border-radius:3px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block;letter-spacing:0.04em;transition:all 0.2s;margin-bottom:16px;}
        .new-btn:hover{background:var(--red);color:#000;box-shadow:0 0 16px var(--red-g);}

        /* EDITOR */
        .card{background:var(--card);border:1px solid var(--border);border-radius:4px;padding:20px;margin-bottom:16px;}
        .form-field{margin-bottom:14px;}
        .form-label{display:block;color:var(--dim);font-size:0.75rem;margin-bottom:5px;}
        .form-label span{color:var(--red-b);margin-right:4px;}
        .form-input{width:100%;background:#080808;border:1px solid var(--border);border-radius:3px;padding:9px 12px;color:var(--white);font-family:inherit;font-size:0.85rem;outline:none;transition:border-color 0.2s;}
        .form-input:focus{border-color:var(--red);}

        .toggle-row{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
        .toggle-label{color:var(--dim);font-size:0.78rem;}
        input[type=checkbox]{accent-color:var(--red);width:16px;height:16px;cursor:pointer;}
        .toggle-val{color:#ddd;font-size:0.8rem;}

        .editor-tabs{display:flex;gap:6px;margin-bottom:-1px;}
        .editor-tab{padding:6px 14px;border:1px solid var(--border);border-bottom:none;border-radius:3px 3px 0 0;font-family:inherit;font-size:0.78rem;background:#0d0d0d;color:var(--dim);cursor:pointer;transition:all 0.15s;}
        .editor-tab.active{background:var(--card);color:var(--red-b);border-bottom:1px solid var(--card);}
        .editor-wrap{border:1px solid var(--border);border-radius:0 4px 4px 4px;overflow:hidden;}
        .editor-toolbar{background:#0d0d0d;border-bottom:1px solid var(--border);padding:6px 10px;display:flex;flex-wrap:wrap;gap:4px;}
        .tb-btn{background:#1a0000;border:1px solid var(--border);color:#aaa;padding:3px 8px;border-radius:2px;font-family:inherit;font-size:0.72rem;cursor:pointer;transition:all 0.15s;}
        .tb-btn:hover{background:var(--red);color:#000;border-color:var(--red);}
        .tb-sep{width:1px;background:var(--border);margin:0 4px;}
        #blog-editor{width:100%;min-height:400px;background:#080808;color:#ddd;border:none;outline:none;padding:16px;font-family:'JetBrains Mono',monospace;font-size:0.82rem;line-height:1.7;resize:vertical;}
        .preview-area{min-height:400px;background:#080808;padding:16px;color:#ddd;font-size:0.85rem;line-height:1.7;overflow-y:auto;}

        .save-row{display:flex;align-items:center;gap:12px;margin-top:12px;flex-wrap:wrap;}
        .save-btn{background:#1a0000;border:1px solid var(--red);color:var(--red-b);padding:10px 24px;border-radius:3px;font-family:inherit;font-size:0.88rem;font-weight:700;cursor:pointer;letter-spacing:0.05em;transition:all 0.2s;}
        .save-btn:hover{background:var(--red);color:#000;box-shadow:0 0 20px var(--red-g);}
        .cancel-btn{background:transparent;border:1px solid var(--dim);color:var(--dim);padding:10px 18px;border-radius:3px;font-family:inherit;font-size:0.82rem;cursor:pointer;text-decoration:none;transition:all 0.15s;}
        .cancel-btn:hover{border-color:#aaa;color:#aaa;}

        /* DELETE MODAL */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:1000;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:#111;border:1px solid var(--red);border-radius:6px;padding:28px 32px;max-width:420px;width:90%;box-shadow:0 0 40px var(--red-g);}
        .modal-title{color:var(--red-b);font-weight:700;font-size:0.95rem;margin-bottom:8px;}
        .modal-text{color:#aaa;font-size:0.82rem;line-height:1.6;margin-bottom:16px;}
        .modal-target{color:#ddd;font-style:italic;}
        .modal-actions{display:flex;gap:10px;}
        .modal-cancel{background:transparent;border:1px solid var(--dim);color:var(--dim);padding:8px 16px;border-radius:3px;font-family:inherit;font-size:0.82rem;cursor:pointer;}
        .modal-cancel:hover{border-color:#aaa;color:#aaa;}
        .modal-confirm{background:#1a0000;border:1px solid var(--red);color:var(--red-b);padding:8px 16px;border-radius:3px;font-family:inherit;font-size:0.82rem;font-weight:700;cursor:pointer;}
        .modal-confirm:hover{background:var(--red);color:#000;}

        .no-posts{color:var(--dim);font-size:0.85rem;padding:20px 0;}
        .pub-yes{color:var(--green);} .pub-no{color:var(--yellow);}
    </style>
</head>
<body style="position: relative; min-height: 100vh; margin: 0; padding-bottom: 120px;">
<!-- TOPBAR -->
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
        <div class="sidebar-label">// BLOG</div>
        <a href="/dark-blog.php" class="sidebar-link <?php echo $view === 'list' ? 'active' : ''; ?>">
            📋 Tous les posts
            <span class="post-badge"><?php echo count($all_posts); ?></span>
        </a>
        <a href="/dark-blog.php?action=create" class="sidebar-link <?php echo $view === 'create' ? 'active' : ''; ?>">
            ➕ Nouveau post
        </a>
        <hr class="sidebar-sep">
        <div class="sidebar-label">// POSTS RÉCENTS</div>
        <?php foreach (array_slice($all_posts, 0, 6) as $p): ?>
        <a href="/dark-blog.php?action=edit&id=<?php echo $p['id']; ?>"
           class="sidebar-link <?php echo ($view === 'edit' && $edit_post && $edit_post['id'] === $p['id']) ? 'active' : ''; ?>"
           title="<?php echo htmlspecialchars($p['title']); ?>">
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px">
                #<?php echo $p['id']; ?> <?php echo htmlspecialchars(substr($p['title'], 0, 22)) . (strlen($p['title']) > 22 ? '…' : ''); ?>
            </span>
            <span class="post-badge <?php echo $p['is_published'] ? 'badge-pub' : 'badge-draft'; ?>">
                <?php echo $p['is_published'] ? 'pub' : 'draft'; ?>
            </span>
        </a>
        <?php endforeach; ?>
        <hr class="sidebar-sep">
        <div class="sidebar-label">// CONTENU</div>
        <a href="/dark.php" class="sidebar-link">↗ Modifier le portfolio</a>
        <hr class="sidebar-sep">
        <div class="sidebar-label">// SYSTÈME</div>
        <a href="/dark.php?section=password" class="sidebar-link">🔒 Mot de passe</a>
        <a href="/dark.php?section=otp" class="sidebar-link">🔑 OTP Secret</a>
        <a href="/dark.php?section=logs" class="sidebar-link"> 📋 Logs</a>
        <a href="/" target="_blank" class="sidebar-link">↗ Voir le site</a>
        <a href="/blog.php" target="_blank" class="sidebar-link">↗ Voir le blog</a>
    </nav>

    <!-- MAIN -->
    <main class="admin-main">
        <div class="session-bar">
            <span class="session-ok">● SESSION ACTIVE</span>
            <span>Blog Manager · <strong><?php echo $admin_user; ?></strong></span>
            <span class="session-time">Expire dans <span id="session-countdown"></span></span>
        </div>

        <?php if ($message): ?>
        <div class="msg <?php echo $msg_type; ?>">
            <?php echo $msg_type === 'success' ? '✓' : '⚠'; ?>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php
        // ══════════════════════════════════════════════
        // VUE : LISTE
        // ══════════════════════════════════════════════
        if ($view === 'list'):
        ?>
        <div class="page-title">// Gestion des posts de blog</div>
        <div class="page-sub">Liste complète · publiés et brouillons · CRUD complet</div>

        <a href="/dark-blog.php?action=create" class="new-btn">+ Nouveau post</a>

        <?php if (empty($all_posts)): ?>
        <p class="no-posts">// Aucun post pour l'instant. Créez le premier !</p>
        <?php else: ?>
        <div class="card" style="padding:0;overflow:hidden;">
            <table class="posts-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Titre</th>
                        <th>Slug</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                        <th>Modifié le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_posts as $p): ?>
                    <tr>
                        <td class="post-id">#<?php echo (int)$p['id']; ?></td>
                        <td class="post-title-cell">
                            <a href="/dark-blog.php?action=edit&id=<?php echo (int)$p['id']; ?>" class="post-title-link">
                                <?php echo htmlspecialchars($p['title']); ?>
                            </a>
                        </td>
                        <td class="post-slug-cell" title="<?php echo htmlspecialchars($p['slug']); ?>">
                            <?php echo htmlspecialchars($p['slug']); ?>
                        </td>
                        <td>
                            <?php if ($p['is_published']): ?>
                            <span class="pub-yes">● publié</span>
                            <?php else: ?>
                            <span class="pub-no">○ brouillon</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;font-size:0.72rem"><?php echo htmlspecialchars(formatDateFR($p['created_at'])); ?></td>
                        <td style="white-space:nowrap;font-size:0.72rem;color:var(--dim)">
                            <?php echo !empty($p['updated_at']) ? htmlspecialchars(formatDateFR($p['updated_at'])) : '—'; ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="/dark-blog.php?action=edit&id=<?php echo (int)$p['id']; ?>" class="btn-edit">✏ éditer</a>
                                <?php if ($p['is_published']): ?>
                                <a href="/post.php?slug=<?php echo urlencode($p['slug']); ?>" target="_blank" class="btn-view">↗ voir</a>
                                <?php endif; ?>
                                <button type="button" class="btn-del"
                                    onclick="openDeleteModal(<?php echo (int)$p['id']; ?>, <?php echo htmlspecialchars(json_encode($p['title'])); ?>)">
                                    ✕ suppr
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php
        // ══════════════════════════════════════════════
        // VUE : CRÉER
        // ══════════════════════════════════════════════
        elseif ($view === 'create'):
        ?>
        <div class="page-title">// Créer un nouveau post</div>
        <div class="page-sub">Le slug est généré automatiquement depuis le titre.</div>

        <form method="POST" action="/dark-blog.php" id="blogForm">
            <input type="hidden" name="action" value="create_post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="content" id="content_hidden">

            <div class="card">
                <div class="form-field">
                    <label class="form-label"><span>$</span> Titre du post <span style="color:var(--red)">*</span></label>
                    <input type="text" name="title" class="form-input"
                           placeholder="Mon article sur la sécurité offensive..."
                           maxlength="300" required
                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                </div>
                <div class="toggle-row">
                    <input type="checkbox" name="is_published" id="pub_toggle"
                           <?php echo isset($_POST['is_published']) ? 'checked' : ''; ?>>
                    <label for="pub_toggle" class="toggle-label">Publié immédiatement</label>
                    <span class="toggle-val" id="pub_val"><?php echo isset($_POST['is_published']) ? '● publié' : '○ brouillon'; ?></span>
                </div>
            </div>

            <div class="editor-tabs">
                <button type="button" class="editor-tab active" onclick="switchEditorTab('html')">HTML</button>
                <button type="button" class="editor-tab" onclick="switchEditorTab('preview')">Prévisualisation</button>
            </div>
            <div class="editor-wrap">
                <?php echo $toolbar_html; ?>
                <textarea id="blog-editor" name="_editor_display"
                    placeholder="// Contenu HTML de votre post..."><?php echo htmlspecialchars($_POST['_editor_display'] ?? ''); ?></textarea>
                <div class="preview-area" id="preview-area" style="display:none"></div>
            </div>

            <div class="save-row">
                <button type="submit" class="save-btn" onclick="syncEditor()">→ Créer le post</button>
                <a href="/dark-blog.php" class="cancel-btn">Annuler</a>
                <span style="color:var(--dim);font-size:0.75rem;margin-left:auto">Ctrl+S pour sauvegarder</span>
            </div>
        </form>

        <?php
        // ══════════════════════════════════════════════
        // VUE : ÉDITER
        // ══════════════════════════════════════════════
        elseif ($view === 'edit' && $edit_post):
        ?>
        <div class="page-title">// Éditer le post #<?php echo (int)$edit_post['id']; ?></div>
        <div class="page-sub">
            Slug actuel : <span style="color:var(--dim)"><?php echo htmlspecialchars($edit_post['slug']); ?></span>
            · Créé le <?php echo htmlspecialchars(formatDateFR($edit_post['created_at'])); ?>
            <?php if (!empty($edit_post['updated_at'])): ?>
            · Modifié le <?php echo htmlspecialchars(formatDateFR($edit_post['updated_at'])); ?>
            <?php endif; ?>
        </div>

        <form method="POST" action="/dark-blog.php" id="blogForm">
            <input type="hidden" name="action" value="update_post">
            <input type="hidden" name="post_id" value="<?php echo (int)$edit_post['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="content" id="content_hidden">

            <div class="card">
                <div class="form-field">
                    <label class="form-label"><span>$</span> Titre <span style="color:var(--red)">*</span></label>
                    <input type="text" name="title" class="form-input"
                           maxlength="300" required
                           value="<?php echo htmlspecialchars($edit_post['title']); ?>">
                </div>
                <div class="toggle-row">
                    <input type="checkbox" name="is_published" id="pub_toggle"
                           <?php echo $edit_post['is_published'] ? 'checked' : ''; ?>>
                    <label for="pub_toggle" class="toggle-label">Publié</label>
                    <span class="toggle-val" id="pub_val"><?php echo $edit_post['is_published'] ? '● publié' : '○ brouillon'; ?></span>
                </div>
            </div>

            <div class="editor-tabs">
                <button type="button" class="editor-tab active" onclick="switchEditorTab('html')">HTML</button>
                <button type="button" class="editor-tab" onclick="switchEditorTab('preview')">Prévisualisation</button>
            </div>
            <div class="editor-wrap">
                <?php echo $toolbar_html; ?>
                <textarea id="blog-editor" name="_editor_display"><?php echo htmlspecialchars($edit_post['content_html']); ?></textarea>
                <div class="preview-area" id="preview-area" style="display:none"></div>
            </div>

            <div class="save-row">
                <button type="submit" class="save-btn" onclick="syncEditor()">→ Sauvegarder</button>
                <a href="/dark-blog.php" class="cancel-btn">← Retour</a>
                <?php if ($edit_post['is_published']): ?>
                <a href="/post.php?slug=<?php echo urlencode($edit_post['slug']); ?>" target="_blank" class="cancel-btn">↗ Voir le post</a>
                <?php endif; ?>
                <button type="button" class="btn-del" style="padding:10px 16px;font-size:0.82rem;margin-left:auto"
                    onclick="openDeleteModal(<?php echo (int)$edit_post['id']; ?>, <?php echo htmlspecialchars(json_encode($edit_post['title'])); ?>)">
                    ✕ Supprimer ce post
                </button>
            </div>
        </form>

        <?php endif; ?>

    </main>
</div>

<!-- MODAL DE CONFIRMATION DE SUPPRESSION -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-title">⚠ Confirmer la suppression</div>
        <div class="modal-text">
            Cette action est <strong>irréversible</strong>. Le post sera définitivement supprimé de la base de données.<br><br>
            Post ciblé : <span class="modal-target" id="modal-post-title"></span>
        </div>
        <form method="POST" action="/dark-blog.php" id="deleteForm">
            <input type="hidden" name="action" value="delete_post">
            <input type="hidden" name="post_id" id="modal-post-id" value="">
            <input type="hidden" name="confirm_delete" value="DELETE">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="modal-actions">
                <button type="button" class="modal-cancel" onclick="closeDeleteModal()">Annuler</button>
                <button type="submit" class="modal-confirm">✕ Supprimer définitivement</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Clock ──
function updateClock() {
    document.getElementById('clock').textContent = new Date().toLocaleTimeString('fr-FR');
}
updateClock(); setInterval(updateClock, 1000);

// ── Session countdown ──
const loginTime = <?php echo json_encode($_SESSION['login_time']); ?>;
const maxLife   = 10800;
function updateCountdown() {
    const left = maxLife - (Math.floor(Date.now() / 1000) - loginTime);
    if (left <= 0) { location.href = '/dark-login.php?expired=1'; return; }
    const h = Math.floor(left / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
    const el = document.getElementById('session-countdown');
    if (el) el.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}
updateCountdown(); setInterval(updateCountdown, 1000);

// ── Published toggle ──
const pubToggle = document.getElementById('pub_toggle');
const pubVal    = document.getElementById('pub_val');
if (pubToggle && pubVal) {
    pubToggle.addEventListener('change', () => {
        pubVal.textContent = pubToggle.checked ? '● publié' : '○ brouillon';
        pubVal.style.color = pubToggle.checked ? 'var(--green)' : 'var(--yellow)';
    });
}

// ── Editor tabs ──
function switchEditorTab(tab) {
    const ed  = document.getElementById('blog-editor');
    const pre = document.getElementById('preview-area');
    const tabs = document.querySelectorAll('.editor-tab');
    const tb   = document.getElementById('toolbar');
    if (tab === 'html') {
        ed.style.display = ''; pre.style.display = 'none';
        tabs[0].classList.add('active'); tabs[1].classList.remove('active');
        if (tb) tb.style.display = '';
    } else {
        pre.innerHTML = ed.value;
        ed.style.display = 'none'; pre.style.display = '';
        tabs[0].classList.remove('active'); tabs[1].classList.add('active');
        if (tb) tb.style.display = 'none';
    }
}

// ── Toolbar helpers ──
function wrapSel(open, close) {
    const ta = document.getElementById('blog-editor');
    const s = ta.selectionStart, e = ta.selectionEnd;
    const sel = ta.value.substring(s, e);
    ta.value = ta.value.substring(0, s) + open + sel + close + ta.value.substring(e);
    ta.focus();
    ta.selectionStart = s + open.length;
    ta.selectionEnd   = s + open.length + sel.length;
}
function insertAtCursor(text) {
    const ta = document.getElementById('blog-editor');
    const pos = ta.selectionStart;
    ta.value = ta.value.substring(0, pos) + text + ta.value.substring(pos);
    ta.focus();
}

// ── Sync editor content to hidden input ──
function syncEditor() {
    const hidden = document.getElementById('content_hidden');
    const ed     = document.getElementById('blog-editor');
    if (hidden && ed) hidden.value = ed.value;
}

// ── Ctrl+S ──
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        syncEditor();
        document.getElementById('blogForm')?.submit();
    }
});
document.getElementById('blogForm')?.addEventListener('submit', syncEditor);

// ── Delete modal ──
function openDeleteModal(id, title) {
    document.getElementById('modal-post-id').value   = id;
    document.getElementById('modal-post-title').textContent = `#${id} — ${title}`;
    document.getElementById('deleteModal').classList.add('open');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
}
// Fermer en cliquant hors de la modal
document.getElementById('deleteModal')?.addEventListener('click', e => {
    if (e.target === e.currentTarget) closeDeleteModal();
});
</script>
    <div style="position: absolute; bottom: 20px; right: 20px; text-align: center; font-family: 'JetBrains Mono', sans-serif; color: #fff; z-index: 100;">
        <img src="assets/arch.png" alt="Arch Logo" style="display: block; margin: 0 auto 5px auto; max-width: 50px;">
        <span style="font-size: 12px; font-weight: bold;">I use Arch btw.</span>
    </div>
</body>
</html>
