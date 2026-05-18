<?php
// Load content from DB or fallback to static content
$sections = [
    'social'      => file_exists('content/social.html')      ? file_get_contents('content/social.html')      : null,
    'profil'      => file_exists('content/profil.html')      ? file_get_contents('content/profil.html')      : null,
    'experience'  => file_exists('content/experience.html')  ? file_get_contents('content/experience.html')  : null,
    'formations'  => file_exists('content/formations.html')  ? file_get_contents('content/formations.html')  : null,
    'competences' => file_exists('content/competences.html') ? file_get_contents('content/competences.html') : null,
    'projets'     => file_exists('content/projets.html')     ? file_get_contents('content/projets.html')     : null,
];
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/blog_helpers.php';
$pdo          = getPDO();
$latest_posts = getLatestPosts($pdo, 4);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="assets/blog.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thimoty — rattack@Rattack-Box:~$</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@300;400;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
</head>
<body style="position: relative; min-height: 100vh; margin: 0; padding-bottom: 120px;">
    <!-- Scanline overlay -->
    <div class="scanlines"></div>
    <!-- CRT flicker -->
    <div class="crt-flicker"></div>

    <div class="terminal-wrapper">

        <!-- TOP BAR -->
        <div class="terminal-bar">
            <div class="terminal-dots">
                <span class="dot dot-red"></span>
                <span class="dot dot-yellow"></span>
                <span class="dot dot-green"></span>
            </div>
            <div class="terminal-title">rattack@Rattack-Box: ~</div>
            <div class="terminal-bar-right">zsh · ssh · 256color</div>
        </div>

        <div class="terminal-body">

            <!-- BOOT SEQUENCE -->
            <div class="boot-sequence" id="bootSeq">
                <div class="boot-line" style="--d:0ms">[  OK  ] Starting kernel...</div>
                <div class="boot-line" style="--d:150ms">[  OK  ] Loaded network interfaces.</div>
                <div class="boot-line" style="--d:300ms">[  OK  ] Mounting encrypted volumes...</div>
                <div class="boot-line" style="--d:450ms">[WARN  ] Unauthorized access detected — logging.</div>
                <div class="boot-line" style="--d:600ms">[  OK  ] Firewall rules applied.</div>
                <div class="boot-line" style="--d:750ms">[  OK  ] Starting Wazuh agent...</div>
                <div class="boot-line" style="--d:900ms">[  OK  ] All services up. Welcome, Rattack.</div>
            </div>

            <!-- PROMPT HEADER -->
            <div class="prompt-block">
                <span class="prompt-arrow">╭─</span><span class="prompt-user">rattack</span><span class="prompt-at">@</span><span class="prompt-host">Rattack-Box</span><span class="prompt-path"> ~/portfolio</span><br>
                <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                <span class="cmd-text" id="typeCmd"></span><span class="cursor">█</span>
            </div>

            <div class="output-container" id="mainContent" style="display:none">

                <!-- ═══════════════════════════ IDENTITY ═══════════════════════════ -->
                <div class="section-header">
                    <span class="section-bracket">[</span>
                    <span class="section-label">WHOAMI</span>
                    <span class="section-bracket">]</span>
                    <span class="section-line">══════════════════════════════════════════</span>
                </div>
                <div class="identity-block">
                    <div class="ascii-name">
<pre class="ascii-art">
██████╗  █████╗ ████████╗████████╗ █████╗  ██████╗██╗  ██╗          /\_     _/\
██╔══██╗██╔══██╗╚══██╔══╝╚══██╔══╝██╔══██╗██╔════╝██║ ██╔╝         /   \___/   \
██████╔╝███████║   ██║      ██║   ███████║██║     █████╔╝         |   _     _   |
██╔══██╗██╔══██║   ██║      ██║   ██╔══██║██║     ██╔═██╗         |  (>_}  {_<) |
██║  ██║██║  ██║   ██║      ██║   ██║  ██║╚██████╗██║  ██╗         |   /=======\ |
╚═╝  ╚═╝╚═╝  ╚═╝   ╚═╝      ╚═╝   ╚═╝  ╚═╝ ╚═════╝╚═╝  ╚═╝        /   / # # # # \ \
                                                                 /   /   # # #   \ \
                                                                 \__/_____________\_/
</pre>
                    </div>
                    <div class="identity-meta">
                        <div class="meta-line"><span class="meta-key">role</span><span class="meta-sep">:</span><span class="meta-val">Pentester · Ethical Hacker</span></div>
                        <div class="meta-line"><span class="meta-key">mail</span><span class="meta-sep">:</span><span class="meta-val"><a href="mailto:it@thimoty.com" class="term-link">it@thimoty.com</a></span></div>
                    </div>
                </div>

                <!-- ═══════════════════════════ SOCIAL LINKS ═══════════════════════════ -->
                <div class="cmd-prompt-inline">
                    <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                    <span class="cmd-inline">cat social_links.txt</span>
                </div>
                <div class="section-content social-grid">
                    <?php if ($sections['social']): echo $sections['social']; else: ?>
                    <a href="https://www.root-me.org/Rattack" target="_blank" class="social-card" data-platform="root-me">
                        <div class="social-icon">⚡</div>
                        <div class="social-info">
                            <div class="social-name">Root-Me</div>
                            <div class="social-handle">Rattack · 1060 pts</div>
                        </div>
                        <div class="social-arrow">→</div>
                    </a>
                    <a href="https://github.com/Rattack-Hack" target="_blank" class="social-card" data-platform="github">
                        <div class="social-icon">◈</div>
                        <div class="social-info">
                            <div class="social-name">GitHub</div>
                            <div class="social-handle">Rattack-Hack</div>
                        </div>
                        <div class="social-arrow">→</div>
                    </a>
                    <a href="https://linkedin.com/in/lay-thimoty" target="_blank" class="social-card" data-platform="linkedin">
                        <div class="social-icon">◉</div>
                        <div class="social-info">
                            <div class="social-name">LinkedIn</div>
                            <div class="social-handle">LAY Thimoty</div>
                        </div>
                        <div class="social-arrow">→</div>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- CV DOWNLOAD -->
                <div class="cmd-prompt-inline">
                    <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                    <span class="cmd-inline">wget thimoty.com/CV-Thimoty-LAY.pdf</span>
                </div>
                <div class="section-content">
                    <a href="assets/CV-Rattack.pdf" download="CV-Rattack.pdf" class="download-btn">
                        <span class="dl-icon">↓</span>
                        <span class="dl-text">Télécharger le CV</span>
                        <span class="dl-meta">PDF · CV-Rattack.pdf</span>
                        <div class="dl-progress"><div class="dl-bar"></div></div>
                    </a>
                </div>

                <!-- ═══════════════════════════ PROFIL ═══════════════════════════ -->
                <div class="cmd-prompt-inline">
                    <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                    <span class="cmd-inline">cat profil.md</span>
                </div>
                <div class="section-header">
                    <span class="section-bracket">[</span>
                    <span class="section-label">PROFIL</span>
                    <span class="section-bracket">]</span>
                    <span class="section-line">═══════════════════════════════════════════</span>
                </div>
                <div class="section-content" id="sec-profil">
                    <?php if ($sections['profil']): echo $sections['profil']; else: ?>
                    <p class="term-para">Fort d'une expérience de <span class="hl">5 ans en alternance</span> combinant administration système et sécurité offensive/défensive, je souhaite approfondir mes compétences dans le domaine et apprendre des autres.</p>
                    <p class="term-para">Passionné par la culture du challenge et le dépassement de soi (<span class="hl">Root-Me</span>, <span class="hl">CTF</span>). Expérience en tests d'intrusion, déploiement SIEM, analyse SOC, hardening AD, sécurisation et administration Linux/Windows.</p>
                    <div class="stat-grid">
                        <div class="stat-item"><span class="stat-val">5+</span><span class="stat-lbl">ans d'expérience</span></div>
                        <div class="stat-item"><span class="stat-val">1060</span><span class="stat-lbl">pts Root-Me</span></div>
                        <div class="stat-item"><span class="stat-val">3</span><span class="stat-lbl">SIEM déployés</span></div>
                        <div class="stat-item"><span class="stat-val">5</span><span class="stat-lbl">reverse proxies sécurisés</span></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ═══════════════════════════ EXPERIENCE ═══════════════════════════ -->
                <div class="cmd-prompt-inline">
                    <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                    <span class="cmd-inline">cat experience.json | jq .</span>
                </div>
                <div class="section-header">
                    <span class="section-bracket">[</span>
                    <span class="section-label">EXPÉRIENCE PROFESSIONNELLE</span>
                    <span class="section-bracket">]</span>
                    <span class="section-line">════════════</span>
                </div>
                <div class="section-content" id="sec-experience">
                    <?php if ($sections['experience']): echo $sections['experience']; else: ?>
                    <div class="job-card">
                        <div class="job-header">
                            <div class="job-title">Consultant en cybersécurité & Admin Système/Réseau</div>
                            <div class="job-date"><span class="tag">05/2021 → Présent</span></div>
                        </div>
                        <div class="job-company">▸ Performance Conseil Informatique · Savenay</div>
                        <div class="job-note">Contrat d'alternance · fin prévue décembre 2026</div>
                        <ul class="job-tasks">
                            <li><span class="task-cat cat-red">PENTEST</span> Tests d'intrusion internes en greybox — identification, exploitation et remédiation des vulnérabilités.</li>
                            <li><span class="task-cat cat-orange">SOC</span> Déploiement de 3 SIEM <span class="hl">Wazuh</span> pour un centre opérationnel de sécurité.</li>
                            <li><span class="task-cat cat-yellow">AUDIT</span> Audit de 2 Active Directory via <span class="hl">Pingcastle</span> + application mesures de hardening Windows.</li>
                            <li><span class="task-cat cat-green">RÉSEAU</span> Audit et remédiation de pare-feu (appliance <span class="hl">OPNsense</span>).</li>
                            <li><span class="task-cat cat-blue">WAF</span> Sécurisation de 5 reverse proxies avec <span class="hl">Naxsi</span>, <span class="hl">Crowdsec</span> + intégration IPS.</li>
                            <li><span class="task-cat cat-red">LINUX</span> Application des mesures de hardening Linux sur serveurs internes.</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ═══════════════════════════ FORMATIONS ═══════════════════════════ -->
                <div class="cmd-prompt-inline">
                    <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                    <span class="cmd-inline">ls -la ~/certifications/</span>
                </div>
                <div class="section-header">
                    <span class="section-bracket">[</span>
                    <span class="section-label">FORMATIONS &amp; CERTIFICATIONS</span>
                    <span class="section-bracket">]</span>
                    <span class="section-line">═══════════</span>
                </div>
                <div class="section-content" id="sec-formations">
                    <?php if ($sections['formations']): echo $sections['formations']; else: ?>
                    <div class="cert-list">
                        <div class="cert-item">
                            <div class="cert-icon">🎓</div>
                            <div class="cert-body">
                                <div class="cert-title">Expert en sécurité digitale</div>
                                <div class="cert-sub">BAC+5 · Niveau 7 · RNCP 36399 · En alternance</div>
                                <div class="cert-meta">En cours / 08/2026 · ENI École Informatique, Nantes</div>
                            </div>
                            <div class="cert-badge wip">EN COURS</div>
                        </div>
                        <div class="cert-item">
                            <div class="cert-icon">🎓</div>
                            <div class="cert-body">
                                <div class="cert-title">Administrateur système et réseau</div>
                                <div class="cert-sub">BAC+4 · Niveau 6 · RNCP 41776 · En alternance</div>
                                <div class="cert-meta">04/2025 · ENI École Informatique, Nantes</div>
                            </div>
                            <div class="cert-badge done">OBTENU</div>
                        </div>
                        <div class="cert-item">
                            <div class="cert-icon">📜</div>
                            <div class="cert-body">
                                <div class="cert-title">CCNA Academy</div>
                                <div class="cert-meta">05/2024 · ENI École Informatique, Nantes</div>
                            </div>
                            <div class="cert-badge done">OBTENU</div>
                        </div>
                        <div class="cert-item">
                            <div class="cert-icon">📜</div>
                            <div class="cert-body">
                                <div class="cert-title">Crowdsec Fundamentals</div>
                                <div class="cert-meta">07/2023 · Crowdsec Academy</div>
                            </div>
                            <div class="cert-badge done">OBTENU</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ═══════════════════════════ COMPETENCES ═══════════════════════════ -->
                <div class="cmd-prompt-inline">
                    <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                    <span class="cmd-inline">nmap --skills rattack 2>&1</span>
                </div>
                <div class="section-header">
                    <span class="section-bracket">[</span>
                    <span class="section-label">COMPÉTENCES</span>
                    <span class="section-bracket">]</span>
                    <span class="section-line">══════════════════════════════════════════</span>
                </div>
                <div class="section-content" id="sec-competences">
                    <?php if ($sections['competences']): echo $sections['competences']; else: ?>
                    <div class="skills-grid">
                        <div class="skill-group">
                            <div class="skill-group-title">// Outils offensifs</div>
                            <div class="skill-tags">
                                <span class="stag red">Nmap</span>
                                <span class="stag red">Burp Suite</span>
                                <span class="stag red">BloodHound</span>
                                <span class="stag red">OpenVAS</span>
                                <span class="stag red">Responder</span>
                                <span class="stag red">NetExec</span>
                            </div>
                        </div>
                        <div class="skill-group">
                            <div class="skill-group-title">// Sécurité défensive</div>
                            <div class="skill-tags">
                                <span class="stag orange">Wazuh SIEM</span>
                                <span class="stag orange">Crowdsec IPS</span>
                                <span class="stag orange">Naxsi WAF</span>
                                <span class="stag orange">OPNsense</span>
                                <span class="stag orange">Pingcastle</span>
                            </div>
                        </div>
                        <div class="skill-group">
                            <div class="skill-group-title">// Référentiels</div>
                            <div class="skill-tags">
                                <span class="stag yellow">OWASP</span>
                                <span class="stag yellow">MITRE ATT&amp;CK</span>
                                <span class="stag yellow">ANSSI</span>
                            </div>
                        </div>
                        <div class="skill-group">
                            <div class="skill-group-title">// Scripting</div>
                            <div class="skill-tags">
                                <span class="stag green">Python</span>
                                <span class="stag green">Bash</span>
                                <span class="stag green">PowerShell</span>
                            </div>
                        </div>
                        <div class="skill-group">
                            <div class="skill-group-title">// Environnements</div>
                            <div class="skill-tags">
                                <span class="stag blue">Linux (Debian/Ubuntu/RHEL)</span>
                                <span class="stag blue">Windows / AD</span>
                                <span class="stag blue">Docker</span>
                                <span class="stag blue">Proxmox</span>
                                <span class="stag blue">VMware</span>
                                <span class="stag blue">FreeBSD</span>
                                <span class="stag blue">Azure / Entra ID</span>
                                <span class="stag blue">Microsoft 365</span>
                            </div>
                        </div>
                        <div class="skill-group">
                            <div class="skill-group-title">// Langues</div>
                            <div class="skill-tags">
                                <span class="stag purple">Français (natif)</span>
                                <span class="stag purple">Anglais B2</span>
                                <span class="stag purple">Espagnol B2</span>
                                <span class="stag purple">Portugais A2</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ═══════════════════════════ PROJETS ═══════════════════════════ -->
                <div class="cmd-prompt-inline">
                    <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                    <span class="cmd-inline">git log --oneline --all</span>
                </div>
                <div class="section-header">
                    <span class="section-bracket">[</span>
                    <span class="section-label">PROJETS</span>
                    <span class="section-bracket">]</span>
                    <span class="section-line">══════════════════════════════════════════</span>
                </div>
                <div class="section-content" id="sec-projets">
                    <?php if ($sections['projets']): echo $sections['projets']; else: ?>
                    <div class="projects-grid">
                        <div class="project-card">
                            <div class="project-header">
                                <span class="project-icon">⚡</span>
                                <span class="project-name">CTF / Root-Me</span>
                                <span class="project-lang">PHP · Python</span>
                            </div>
                            <div class="project-desc">Résolution de challenges de sécurité offensive — Web, Réseau, Crypto, Forensic. Score actuel : 1060 pts.</div>
                            <div class="project-tags"><span class="ptag">CTF</span><span class="ptag">Pentest</span><span class="ptag">OSCP-prep</span></div>
                            <a href="https://www.root-me.org/Rattack" class="project-link" target="_blank">→ Voir le profil</a>
                        </div>
                        <div class="project-card">
                            <div class="project-header">
                                <span class="project-icon">◈</span>
                                <span class="project-name">Labs & Scripts</span>
                                <span class="project-lang">Bash · Python</span>
                            </div>
                            <div class="project-desc">Scripts et outils personnels pour l'automatisation d'audits, hardening et recon. Disponibles sur GitHub.</div>
                            <div class="project-tags"><span class="ptag">Automation</span><span class="ptag">Hardening</span><span class="ptag">Recon</span></div>
                            <a href="https://github.com/Rattack-Hack" class="project-link" target="_blank">→ Voir le repo</a>
                        </div>
                        <div class="project-card">
                            <div class="project-header">
                                <span class="project-icon">◉</span>
                                <span class="project-name">Déploiement SOC Wazuh</span>
                                <span class="project-lang">Linux · Wazuh</span>
                            </div>
                            <div class="project-desc">Mise en place de 3 instances SIEM Wazuh en production pour un SOC d'entreprise. Règles custom, dashboards et alerting.</div>
                            <div class="project-tags"><span class="ptag">SIEM</span><span class="ptag">SOC</span><span class="ptag">Blue Team</span></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
<!-- ═══════════════════════════ BLOG ═══════════════════════════ -->
<div class="cmd-prompt-inline">
    <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
    <span class="cmd-inline">tail -4 ~/blog/posts.log | sort -r</span>
</div>
<div class="section-header">
    <span class="section-bracket">[</span>
    <span class="section-label">BLOG — DERNIERS POSTS</span>
    <span class="section-bracket">]</span>
    <span class="section-line">══════════════════════════════════════</span>
</div>
<div class="section-content">
    <?php if (empty($latest_posts)): ?>
    <p class="term-para" style="color:var(--text-dim)">// Aucun post publié pour l'instant.</p>
    <?php else: ?>
    <div class="blog-preview-grid">
        <?php foreach ($latest_posts as $lp): ?>
        <a href="/post.php?slug=<?php echo urlencode($lp['slug']); ?>" class="blog-preview-card">
            <div class="bpc-date">
                <?php
                $ts  = strtotime($lp['created_at']);
                echo date('d/m/Y', $ts) . '<br>';
                echo '<span style="color:var(--red-dim)">' . date('H:i', $ts) . '</span>';
                ?>
            </div>
            <div class="bpc-body">
                <div class="bpc-title"><?php echo htmlspecialchars($lp['title']); ?></div>
                <div class="bpc-meta" style="font-size:0.7rem;color:var(--text-dim);margin-top:3px;">
                    <span style="color:var(--red-dim)">created</span>
                    <?php echo htmlspecialchars(formatDateFR($lp['created_at'])); ?>
                    <?php if (!empty($lp['updated_at']) && $lp['updated_at'] !== $lp['created_at']): ?>
                    · <span style="color:var(--orange)">✏ modifié</span>
                    <?php echo htmlspecialchars(formatDateFR($lp['updated_at'])); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="bpc-arrow">→</div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <a href="/blog.php" class="blog-see-all">
        <span>→</span> Voir tous les posts
    </a>
</div>

                <!-- FOOTER PROMPT -->
                <div class="footer-prompt">
                    <span class="prompt-arrow">╭─</span><span class="prompt-user">rattack</span><span class="prompt-at">@</span><span class="prompt-host">Rattack-Box</span><span class="prompt-path"> ~/portfolio</span><br>
                    <span class="prompt-arrow">╰─</span><span class="prompt-dollar">$</span>
                    <span class="footer-blink">█</span>
                    <div class="footer-meta">// EOF · Par Rattack en <?php echo date('Y'); ?> · rattack@Rattack-Box</div>
			
	   </div>

            </div><!-- /output-container -->
        </div><!-- /terminal-body -->
    </div><!-- /terminal-wrapper -->

    <script src="assets/main.js"></script>

	<div style="position: absolute; bottom: 20px; right: 20px; text-align: center; font-family: 'JetBrains Mono', sans-serif; color: #fff; z-index: 100;">
        <img src="assets/arch.png" alt="Arch Logo" style="display: block; margin: 0 auto 5px auto; max-width: 50px;">
        <span style="font-size: 12px; font-weight: bold;">I use Arch btw.</span>
    </div>

</body>
</html>
