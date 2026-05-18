/* rattack portfolio — main.js */

document.addEventListener('DOMContentLoaded', () => {

    // ── Typewriter effect ──
    const cmdEl = document.getElementById('typeCmd');
    const content = document.getElementById('mainContent');
    const cmd = 'cat portfolio.sh && bash portfolio.sh';
    let i = 0;

    function typeChar() {
        if (i < cmd.length) {
            cmdEl.textContent += cmd[i++];
            setTimeout(typeChar, 55 + Math.random() * 40);
        } else {
            // Remove blinking cursor from prompt, show content
            document.querySelector('.cursor').style.display = 'none';
            setTimeout(() => {
                content.style.display = 'block';
                // Animate sections in
                animateSections();
            }, 300);
        }
    }

    // Wait for boot sequence to finish
    setTimeout(typeChar, 1100);

    // ── Section reveal on scroll ──
    function animateSections() {
        const items = document.querySelectorAll(
            '.job-card, .cert-item, .project-card, .social-card, .stat-item'
        );
        const obs = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.style.animation = 'slideIn 0.4s ease forwards';
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.1 });

        items.forEach((el, idx) => {
            el.style.opacity = '0';
            el.style.animationDelay = `${idx * 40}ms`;
            obs.observe(el);
        });
    }

    // ── Download button click feedback ──
    document.querySelectorAll('.download-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const bar = btn.querySelector('.dl-bar');
            if (!bar) return;
            bar.style.transition = 'width 0.6s ease';
            bar.style.width = '100%';
        });
    });

    // ── Glitch effect on ASCII name (random) ──
    const ascii = document.querySelector('.ascii-art');
    if (ascii) {
        setInterval(() => {
            if (Math.random() < 0.05) {
                ascii.style.textShadow = `0 0 20px #ff0000, 2px 0 8px #ff0000`;
                ascii.style.transform = `translateX(${Math.random() * 3 - 1.5}px)`;
                setTimeout(() => {
                    ascii.style.textShadow = '0 0 6px rgba(204,0,0,0.5)';
                    ascii.style.transform = 'none';
                }, 80);
            }
        }, 2000);
    }

    // ── Keyboard sound (subtle click on keypress in terminal) ──
    let audioCtx = null;
    document.addEventListener('keydown', () => {
        try {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.type = 'square';
            osc.frequency.setValueAtTime(800 + Math.random() * 400, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.03, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.04);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.04);
        } catch(e) {}
    });

    // ── Matrix rain cursor trail ──
    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:fixed;top:0;left:0;pointer-events:none;z-index:997;opacity:0.15;';
    document.body.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    const trails = [];
    const chars = '01アイウエオカキクケコサシスセソタチツテトナニヌネノ';

    document.addEventListener('mousemove', (e) => {
        if (Math.random() < 0.3) {
            trails.push({
                x: e.clientX, y: e.clientY,
                char: chars[Math.floor(Math.random() * chars.length)],
                alpha: 0.8, speed: 1 + Math.random() * 2
            });
        }
    });

    function renderTrails() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.font = '12px JetBrains Mono';
        for (let t = trails.length - 1; t >= 0; t--) {
            const tr = trails[t];
            ctx.fillStyle = `rgba(204, 0, 0, ${tr.alpha})`;
            ctx.fillText(tr.char, tr.x, tr.y);
            tr.y += tr.speed;
            tr.alpha -= 0.04;
            if (tr.alpha <= 0) trails.splice(t, 1);
        }
        requestAnimationFrame(renderTrails);
    }
    renderTrails();

    window.addEventListener('resize', () => {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    });

});
