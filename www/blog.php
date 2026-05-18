<?php
// ══════════════════════════════════════════════════════════════
// blog.php — Liste de tous les articles (public)
// URL : thimoty.com/blog.php
// ══════════════════════════════════════════════════════════════
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/blog_helpers.php';

$pdo = getPDO();

// Pagination
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$total    = countAllPosts($pdo);
$posts    = getAllPosts($pdo, $page, $per_page);
$pages    = (int)ceil($total / $per_page);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog — rattack@kali</title>
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
        <div class="terminal-title">rattack@kali: ~/blog</div>
        <div class="terminal-bar-right">zsh · blog · 256color</div>
    </div>

    <div class="terminal-body">
        <div class="prompt-block">
            <span class="prompt-arrow">╭─</span><span class="prompt-user">rattack</span><span class="prompt-at">@</span><span class="prompt-host">kali</span><span class="prompt-path"> ~/blog</span><br>
            <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
            <span class="cmd-text">ls -lt posts/ | head -<?php echo $per_page; ?></span><span class="cursor">█</span>
        </div>

        <div class="output-container">
            <div class="section-header">
                <span class="section-bracket">[</span>
                <span class="section-label">BLOG — TOUS LES POSTS</span>
                <span class="section-bracket">]</span>
                <span class="section-line">══════════════════════════</span>
            </div>

            <div class="cmd-prompt-inline">
                <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                <span class="cmd-inline">find ./posts -name "*.md" | wc -l → <strong style="color:#fff"><?php echo $total; ?> post<?php echo $total !== 1 ? 's' : ''; ?></strong></span>
            </div>

            <div class="section-content">
                <?php if (empty($posts)): ?>
                <p class="term-para" style="color:var(--text-dim)">// Aucun post publié pour l'instant.</p>
                <?php else: ?>
                <div class="blog-list">
                    <?php foreach ($posts as $i => $p): ?>
                    <a href="/post.php?slug=<?php echo urlencode($p['slug']); ?>" class="blog-list-item">
                        <div class="bli-index"><?php echo str_pad($i + 1 + ($page - 1) * $per_page, 2, '0', STR_PAD_LEFT); ?></div>
                        <div class="bli-body">
                            <div class="bli-title"><?php echo htmlspecialchars($p['title']); ?></div>
                            <div class="bli-meta">
                                <span class="bli-date">
                                    <span style="color:var(--red-dim)">created</span>
                                    <?php echo formatDateFR($p['created_at']); ?>
                                </span>
                                <?php if (!empty($p['updated_at']) && $p['updated_at'] !== $p['created_at']): ?>
                                <span class="bli-updated">
                                    · <span style="color:var(--orange)">✏ modifié</span>
                                    <?php echo formatDateFR($p['updated_at']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="bli-arrow">→</div>
                    </a>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="/blog.php?page=<?php echo $page - 1; ?>" class="page-btn">← prev</a>
                    <?php endif; ?>
                    <span class="page-info">page <?php echo $page; ?> / <?php echo $pages; ?></span>
                    <?php if ($page < $pages): ?>
                    <a href="/blog.php?page=<?php echo $page + 1; ?>" class="page-btn">next →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="post-nav" style="margin-top:30px;">
                <a href="/" class="nav-btn">← Retour au portfolio</a>
            </div>
        </div>
    </div>
</div>

<script src="assets/main.js"></script>
    <div style="position: absolute; bottom: 20px; right: 20px; text-align: center; font-family: 'JetBrains Mono', sans-serif; color: #fff; z-index: 100;">
        <img src="assets/arch.png" alt="Arch Logo" style="display: block; margin: 0 auto 5px auto; max-width: 50px;">
        <span style="font-size: 12px; font-weight: bold;">I use Arch btw.</span>
    </div>
</body>
</html>
