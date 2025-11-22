<?php
require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/config.php';
$turnstileEnabled = !empty($config['turnstile_enabled']);
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Registrieren — MaxxCloud</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
    }

    /* Hintergrundbild mit Blur */
    .background {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 900"><defs><linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:%23667eea;stop-opacity:1" /><stop offset="100%" style="stop-color:%23764ba2;stop-opacity:1" /></linearGradient></defs><rect width="1440" height="900" fill="url(%23grad)"/><path d="M0,600 Q360,500 720,550 T1440,500 L1440,900 L0,900 Z" fill="rgba(255,255,255,0.1)"/><path d="M0,700 Q360,600 720,650 T1440,600 L1440,900 L0,900 Z" fill="rgba(255,255,255,0.05)"/></svg>');
      background-size: cover;
      background-position: center;
      filter: blur(8px);
      transform: scale(1.1);
      z-index: 0;
    }

    /* Overlay für zusätzliche Tiefe */
    .background::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.3);
    }

    /* Formular Container */
    .register-container {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 450px;
      padding: 20px;
    }

    .register-card {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-radius: 20px;
      padding: 40px 35px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    }

    .register-title {
      color: #ffffff;
      font-size: 32px;
      font-weight: 700;
      text-align: center;
      margin-bottom: 35px;
      letter-spacing: -0.5px;
    }

    .form-group {
      margin-bottom: 25px;
    }

    .form-input {
      width: 100%;
      padding: 15px 0;
      background: transparent;
      border: none;
      border-bottom: 2px solid rgba(255, 255, 255, 0.4);
      color: #ffffff;
      font-size: 16px;
      outline: none;
      transition: border-color 0.3s ease;
    }

    .form-input::placeholder {
      color: rgba(255, 255, 255, 0.6);
    }

    .form-input:focus {
      border-bottom-color: rgba(255, 255, 255, 0.9);
    }

    .form-input:-webkit-autofill,
    .form-input:-webkit-autofill:hover,
    .form-input:-webkit-autofill:focus {
      -webkit-text-fill-color: #ffffff;
      -webkit-box-shadow: 0 0 0px 1000px transparent inset;
      transition: background-color 5000s ease-in-out 0s;
    }

    .checkbox-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      font-size: 14px;
    }

    .checkbox-wrapper {
      display: flex;
      align-items: center;
      color: rgba(255, 255, 255, 0.9);
    }

    .checkbox-wrapper input[type="checkbox"] {
      margin-right: 8px;
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: rgba(255, 255, 255, 0.8);
    }

    .forgot-link {
      color: rgba(255, 255, 255, 0.9);
      text-decoration: none;
      transition: opacity 0.2s;
    }

    .forgot-link:hover {
      opacity: 0.8;
      text-decoration: underline;
    }

    .submit-btn {
      width: 100%;
      padding: 16px;
      background: rgba(255, 255, 255, 0.2);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 12px;
      color: #ffffff;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-bottom: 25px;
    }

    .submit-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .submit-btn:active {
      transform: translateY(0);
    }

    .submit-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .register-link {
      text-align: center;
      color: rgba(255, 255, 255, 0.9);
      font-size: 14px;
      margin-top: 20px;
    }

    .register-link a {
      color: #ffffff;
      text-decoration: none;
      font-weight: 600;
      margin-left: 5px;
    }

    .register-link a:hover {
      text-decoration: underline;
    }

    .error-message {
      background: rgba(220, 53, 69, 0.2);
      border: 1px solid rgba(220, 53, 69, 0.4);
      color: #ffcccc;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      display: none;
    }

    .error-message.show {
      display: block;
    }

    .success-message {
      background: rgba(40, 167, 69, 0.2);
      border: 1px solid rgba(40, 167, 69, 0.4);
      color: #c3ffd0;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      display: none;
    }

    .success-message.show {
      display: block;
    }

    .turnstile-wrapper {
      margin-bottom: 25px;
      display: flex;
      justify-content: center;
    }

    @media (max-width: 500px) {
      .register-card {
        padding: 30px 25px;
      }

      .register-title {
        font-size: 28px;
        margin-bottom: 30px;
      }
    }
  </style>
<?php if ($turnstileEnabled): ?>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
</head>
<body>
  <div class="background"></div>
  
  <div class="register-container">
    <div class="register-card">
      <h1 class="register-title">Registrieren</h1>
      
      <div id="errorMessage" class="error-message"></div>
      <div id="successMessage" class="success-message"></div>

      <form id="registerForm">
        <div class="form-group">
          <input 
            type="email" 
            id="registerEmail" 
            class="form-input" 
            placeholder="Gebe deine Email Adresse ein" 
            required 
            autocomplete="email"
          />
        </div>

        <div class="form-group">
          <input 
            type="password" 
            id="registerPassword" 
            class="form-input" 
            placeholder="Gebe dein Passwort ein" 
            required 
            autocomplete="new-password"
            minlength="8"
          />
        </div>

        <div class="form-group">
          <input 
            type="password" 
            id="registerPasswordConfirm" 
            class="form-input" 
            placeholder="Passwort bestätigen" 
            required 
            autocomplete="new-password"
            minlength="8"
          />
        </div>

        <div class="checkbox-row">
          <label class="checkbox-wrapper">
            <input type="checkbox" id="stayLoggedIn" />
            <span>Bleibe angemeldet</span>
          </label>
        </div>

        <?php if ($turnstileEnabled): ?>
          <div class="turnstile-wrapper">
            <div class="cf-turnstile" 
                 data-sitekey="<?php echo htmlspecialchars($config['turnstile_site_key'], ENT_QUOTES); ?>"
                 data-theme="light"
                 data-size="normal"></div>
          </div>
        <?php endif; ?>

        <button type="submit" class="submit-btn" id="submitBtn">
          Registrieren
        </button>
      </form>

      <div class="register-link">
        Du hast bereits einen Account? <a href="index.php">Anmelden</a>
      </div>
    </div>
  </div>

  <script>
    const API_BASE = 'api.php';
    const TURNSTILE_ENABLED = <?php echo $turnstileEnabled ? 'true' : 'false'; ?>;
    const el = id => document.getElementById(id);
    let turnstileToken = null;

    function showError(message) {
      const err = el('errorMessage');
      err.textContent = message;
      err.classList.add('show');
      el('successMessage').classList.remove('show');
      setTimeout(() => err.classList.remove('show'), 5000);
    }

    function showSuccess(message) {
      const succ = el('successMessage');
      succ.textContent = message;
      succ.classList.add('show');
      el('errorMessage').classList.remove('show');
    }

    async function api(action, payload = {}) {
      const body = JSON.stringify({ action, ...payload });
      const res = await fetch(API_BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body
      });
      const data = await res.json();
      if (!res.ok || data.error) {
        throw new Error(data.error || 'Unbekannter Fehler');
      }
      return data;
    }

    el('registerForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const email = el('registerEmail').value.trim();
      const password = el('registerPassword').value;
      const passwordConfirm = el('registerPasswordConfirm').value;

      // Validierung
      if (!email) {
        showError('Bitte gib deine Email-Adresse ein');
        return;
      }

      if (password.length < 8) {
        showError('Passwort muss mindestens 8 Zeichen lang sein');
        return;
      }

      if (password !== passwordConfirm) {
        showError('Passwörter stimmen nicht überein');
        return;
      }

      if (TURNSTILE_ENABLED && !turnstileToken) {
        showError('Bitte löse das Captcha');
        return;
      }

      const btn = el('submitBtn');
      btn.disabled = true;
      btn.textContent = 'Registrierung läuft...';

      try {
        const payload = { email, password };
        if (TURNSTILE_ENABLED) {
          payload.cfToken = turnstileToken;
        }
        await api('register', payload);
        
        showSuccess('Registrierung erfolgreich! Du wirst weitergeleitet...');
        
        // Nach erfolgreicher Registrierung zum Login weiterleiten
        setTimeout(() => {
          window.location.href = 'index.php';
        }, 1500);
      } catch (err) {
        showError(err.message || 'Registrierung fehlgeschlagen');
        btn.disabled = false;
        btn.textContent = 'Registrieren';
        
        // Turnstile zurücksetzen
        if (TURNSTILE_ENABLED && window.turnstile) {
          window.turnstile.reset();
        }
        if (TURNSTILE_ENABLED) {
          turnstileToken = null;
        }
      }
    });
<?php if ($turnstileEnabled): ?>
    window.onTurnstileSuccess = function(token) {
      turnstileToken = token;
    };

    window.onTurnstileError = function() {
      turnstileToken = null;
      showError('Captcha-Fehler. Bitte versuche es erneut.');
    };

    window.onTurnstileExpired = function() {
      turnstileToken = null;
    };
<?php endif; ?>
  </script>
</body>
</html>


