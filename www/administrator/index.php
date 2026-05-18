<?php
// On garde les en-têtes pour contourner le blocage de ton serveur
header("Cross-Origin-Embedder-Policy: unsafe-none");
header("Cross-Origin-Opener-Policy: unsafe-none");
header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval'; frame-src *;");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chargement...</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #000; /* Écran totalement noir au départ */
            cursor: default;
        }

        #video-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
        }

        /* Styliser l'iframe pour qu'il remplisse l'écran */
        iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
            z-index: 1; /* La vidéo est en-dessous */
        }

        /* Styliser le texte "Nice HACKING try you fool!" */
        .hacking-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 5rem; /* Très gros texte */
            font-weight: bold;
            color: #FF0000; /* Rouge vif */
            text-align: center;
            text-transform: uppercase;
            z-index: 100; /* Toujours au-dessus de la vidéo */
            text-shadow: 4px 4px 8px #000; /* Pour qu'il soit lisible */
            pointer-events: none; /* L'utilisateur ne peut pas interagir avec le texte */
            width: 90%; /* Pour éviter que le texte ne touche les bords */
        }
    </style>
</head>
<body>

    <div id="video-container"></div>

    <script>
        let triggered = false;

        function launchRickRoll() {
            if (triggered) return; 
            triggered = true;

            // Injecte l'iframe YouTube DIRECTEMENT avec le son, ET le texte overlay
            document.getElementById('video-container').innerHTML = `
                <iframe 
                    src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1&controls=0&modestbranding=1&rel=0&disablekb=1" 
                    allow="autoplay; encrypted-media; fullscreen" 
                    allowfullscreen>
                </iframe>
                <div class="hacking-text">Nice HACKING try you fool !</div>
            `;

            // On retire les écouteurs pour que ça ne se relance pas en boucle
            document.removeEventListener('click', launchRickRoll);
            document.removeEventListener('keydown', launchRickRoll);
        }

        // Le piège invisible : on écoute directement toute la page (le body)
        document.addEventListener('click', launchRickRoll);
        document.addEventListener('keydown', launchRickRoll);
    </script>

</body>
</html>
