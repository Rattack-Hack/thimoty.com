<?php
// ══════════════════════════════════════════════════════════════
// post.php — Affichage d'un article de blog (public)
// URL : thimoty.com/post.php?slug=mon-article
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/blog_helpers.php';

$pdo = getPDO();

// ── Récupérer le slug depuis l'URL — validation stricte ──
$raw_slug = $_GET['slug'] ?? '';
$slug     = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($raw_slug)));

if (empty($slug) || strlen($slug) > 200) {
    http_response_code(404);
    $post = null;
} else {
    $post = getPostBySlug($pdo, $slug);
    if (!$post) http_response_code(404);
}

$page_title = $post ? htmlspecialchars($post['title']) . ' — rattack@kali' : '404 — post not found';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/blog.css">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
</head>
<body style="position: relative; min-height: 100vh; margin: 0; padding-bottom: 120px;">
<div class="scanlines"></div>
<div class="crt-flicker"></div>

<div class="terminal-wrapper">
    <div class="terminal-bar">
        <div class="terminal-dots">
            <span class="dot dot-red"></span>
            <span class="dot dot-yellow"></span>
            <span class="dot dot-green"></span>
        </div>
        <div class="terminal-title">rattack@kali: ~/blog/<?php echo htmlspecialchars($slug); ?></div>
        <div class="terminal-bar-right">zsh · post · 256color</div>
    </div>

    <div class="terminal-body">
        <!-- BREADCRUMB PROMPT -->
        <div class="prompt-block">
            <span class="prompt-arrow">╭─</span><span class="prompt-user">rattack</span><span class="prompt-at">@</span><span class="prompt-host">kali</span><span class="prompt-path"> ~/blog</span><br>
            <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
            <span class="cmd-text">cat posts/<?php echo htmlspecialchars($slug); ?>.md</span>
        </div>

        <?php if (!$post): ?>
        <!-- 404 -->
        <div class="output-container">
            <div class="section-header">
                <span class="section-bracket">[</span>
                <span class="section-label" style="color:var(--orange)">ERROR 404</span>
                <span class="section-bracket">]</span>
                <span class="section-line">═══════════════════════════════════</span>
            </div>
            <div class="section-content">
                <p class="term-para" style="color:var(--orange)">cat: posts/<?php echo htmlspecialchars($slug); ?>.md: No such file or directory</p>
                <p class="term-para">Le post demandé n'existe pas ou n'est plus publié.</p>
                <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;">
                    <a href="/" class="nav-btn">← Retour au portfolio</a>
                    <a href="/blog.php" class="nav-btn">📋 Voir tous les posts</a>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- POST -->
        <div class="output-container">
            <!-- Header du post -->
            <div class="post-header-block">
                <div class="section-header">
                    <span class="section-bracket">[</span>
                    <span class="section-label">POST</span>
                    <span class="section-bracket">]</span>
                    <span class="section-line">══════════════════════════════════════════════</span>
                </div>
                <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>

                <!-- Bloc dates -->
                <div class="post-meta">
                    <div class="post-meta-item">
                        <span class="meta-key">author </span>
                        <span class="meta-sep">:</span>
                        <span class="meta-val"><?php echo htmlspecialchars($post['author'] ?? 'rattack'); ?></span>
                    </div>
                    <div class="post-meta-item">
                        <span class="meta-key">created</span>
                        <span class="meta-sep">:</span>
                        <span class="meta-val post-date">
                            <span class="date-icon">📅</span>
                            <?php echo formatDateFR($post['created_at']); ?>
                        </span>
                    </div>
                    <?php if (!empty($post['updated_at']) && $post['updated_at'] !== $post['created_at']): ?>
                    <div class="post-meta-item">
                        <span class="meta-key">updated</span>
                        <span class="meta-sep">:</span>
                        <span class="meta-val post-date post-date-modified">
                            <span class="date-icon">✏️</span>
                            <?php echo formatDateFR($post['updated_at']); ?>
                            <span class="modified-tag">modifié</span>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="post-meta-item">
                        <span class="meta-key">slug   </span>
                        <span class="meta-sep">:</span>
                        <span class="meta-val" style="color:var(--text-dim)"><?php echo htmlspecialchars($post['slug']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Contenu du post -->
            <div class="cmd-prompt-inline">
                <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                <span class="cmd-inline">less -R posts/<?php echo htmlspecialchars($slug); ?>.md</span>
            </div>
            <div class="post-content blog-content">
                <?php
                // Le contenu est déjà sanitizé à l'écriture ; on l'affiche directement
                // Double couche : on ne fait pas echo d'une valeur non vérifiée
                // Le contenu provient de la DB via PDO préparé, sanitizé lors de la sauvegarde
                echo $post['content_html'];
                ?>
            </div>

            <!-- Navigation -->
            <div class="post-nav">
                <a href="/" class="nav-btn">← Portfolio</a>
                <a href="/blog.php" class="nav-btn">📋 Tous les posts</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="assets/main.js"></script>
    <div style="position: absolute; bottom: 20px; right: 20px; text-align: center; font-family: 'JetBrains Mono', sans-serif; color: #fff; z-index: 100;">
        <img src="assets/arch.png" alt="Arch Logo" style="display: block; margin: 0 auto 5px auto; max-width: 50px;">
        <span style="font-size: 12px; font-weight: bold;">I use Arch btw.</span>
    </div>
</body>
</html>
