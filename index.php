<?php
$config = require __DIR__ . '/config.php';
$turnstileEnabled = !empty($config['turnstile_enabled']);
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>MaxxCloud – Deine Dateien in der Cloud</title>
  <meta name="description" content="Selfhosted MaxxCloud mit Login, Registrierung und echtem Dateiupload.">
<?php if ($turnstileEnabled): ?>
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
  <style>
    /* --- simplified adaptation of globals.css variables --- */
    :root{
      --bg:#eaf0fb; --panel:#27448C; --muted:#6b86c4; --accent:#27448C; --card:#233f85; --text:#ffffff; --border:#1f3a7a; --radius:12px; --gap:16px; --max-width:1280px; --sidebar-w:260px;
    }
    [data-theme='dark']{
      --bg:#0b1226; --panel:#111a33; --muted:#9fb4e6; --accent:#27448C; --card:#0e162d; --text:#e6e6e6; --border:#162044;
    }

    *{box-sizing:border-box}
    html,body{height:100%;margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--text)}

    /* container */
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{height:72px;display:flex;align-items:center;gap:12px;padding:0 20px;background:var(--panel);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100}
    .container{display:flex;flex:1;width:100%;margin:0;padding:16px;gap:16px}

    /* sidebar */
    .sidebar{width:var(--sidebar-w);background:var(--panel);padding:16px;border-right:1px solid var(--border);display:flex;flex-direction:column;gap:12px;border-radius:16px;border:1px solid var(--border);color:var(--text)}
    .brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:18px}
    .brand svg{width:28px;height:28px}
    .brand-badge{width:36px;height:36px;border-radius:999px;display:flex;align-items:center;justify-content:center;background:#1f3a7a;border:1px solid #162c5a}
    .theme-toggle{display:inline-flex;align-items:center;cursor:pointer}
    .theme-toggle input{display:none}
    .theme-toggle .toggle{width:44px;height:24px;border-radius:999px;background:#e5e7eb;position:relative;border:1px solid var(--border)}
    .theme-toggle .toggle::after{content:'';position:absolute;top:2px;left:2px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,0.15);transition:left .2s ease}
    .theme-toggle input:checked + .toggle{background:#1f2937}
    .theme-toggle input:checked + .toggle::after{left:22px;background:#111827}
    .folders{flex:1;overflow:auto}
    .folder{padding:10px;border-radius:10px;cursor:pointer;border:1px solid transparent}
    .folder:hover{background:#f2f4f8}
    .folder.active{background:#eef2ff;border-color:#dbeafe;color:#1e3a8a}
    .profile{position:relative;display:flex;flex-direction:column;align-items:center;gap:4px}
    .avatar{width:36px;height:36px;border-radius:999px;overflow:hidden;border:1px solid var(--border);background:#1f3a7a;display:flex;align-items:center;justify-content:center}
    .avatar img{width:100%;height:100%;object-fit:cover;display:block}
    .username{font-size:12px;color:var(--text)}
    .profile-menu{position:absolute;top:70px;right:0;background:var(--panel);border:1px solid var(--border);border-radius:12px;padding:8px;display:none;flex-direction:column;gap:6px}
    .profile:hover .profile-menu{display:flex}
    .profile.open .profile-menu{display:flex}
    .banner{padding:10px 12px;border-radius:12px;margin:8px 0;font-weight:700}
    .banner.danger{background:#3b1f1f;color:#ffb3b3;border:1px solid #832b2b}
    .menu-btn{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.1);border:1px solid var(--border);padding:8px 12px;border-radius:10px;color:var(--text);cursor:pointer}
    .menu-btn:hover{background:rgba(255,255,255,0.16)}
    .menu-btn.danger{background:#dc3545;border-color:#b02a37;color:#fff}
    .menu-btn.danger:hover{background:#c6323e}

    /* main */
    main{flex:1;display:flex;flex-direction:column;background:var(--panel);border:1px solid var(--border);border-radius:16px;overflow:hidden}
    .actionbar{padding:14px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:center;background:var(--panel)}
    .search{flex:1;position:relative}
    .search input{width:100%;padding:12px 12px 12px 40px;border-radius:12px;background:rgba(255,255,255,0.12);border:1px solid var(--border);color:var(--text)}
    .search .icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);opacity:0.6}

    .content{padding:16px;overflow:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}
    .file{background:var(--card);padding:14px;border-radius:14px;border:1px solid var(--border);display:flex;flex-direction:column;gap:10px}
    .file .name{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .file .meta{font-size:12px;color:var(--muted)}

    .action-row{display:flex;gap:8px}
    .btn{background:rgba(255,255,255,0.12);border:1px solid var(--border);padding:8px 12px;border-radius:10px;color:var(--text);cursor:pointer;transition:transform .12s ease, background .2s ease}
    .btn:hover{transform:translateY(-1px);background:rgba(255,255,255,0.18)}
    .btn.primary{background:#1f3a7a;border-color:#162c5a;color:#fff}

    .upload-card{position:relative;overflow:hidden;background:var(--card);border:1px solid var(--border);border-radius:16px;padding:16px}
    .upload-card .glow{position:absolute;width:160px;height:160px;border-radius:999px;filter:blur(40px);opacity:.6}
    .upload-card .glow.left{left:-60px;top:-60px;background:radial-gradient(circle, rgba(39,68,140,0.25), transparent 60%)}
    .upload-card .glow.right{right:-60px;bottom:-60px;background:radial-gradient(circle, rgba(39,68,140,0.25), transparent 60%)}
    .upload-head{display:flex;align-items:center;justify-content:space-between}
    .upload-icon{background:rgba(39,68,140,0.15);padding:8px;border-radius:10px}
    .dropzone{margin-top:12px;border:2px dashed var(--border);border-radius:12px;background:rgba(0,0,0,0.15);padding:16px;position:relative}
    .dropzone.active{border-color:var(--accent)}
    .dropzone input{position:absolute;inset:0;opacity:0;cursor:pointer}
    .drop-content{text-align:center}
    .drop-badge{width:80px;height:80px;margin:0 auto;border-radius:999px;background:rgba(0,0,0,0.2);display:flex;align-items:center;justify-content:center}

    /* login */
    .login-screen{
      position:fixed;
      top:0;
      left:0;
      width:100%;
      height:100%;
      display:flex;
      align-items:center;
      justify-content:center;
      z-index:1000;
    }
    .login-background{
      position:fixed;
      top:0;
      left:0;
      width:100%;
      height:100%;
      background-image:url('https://maxxcloud.it/assets/auth-bg.jpg');
      background-size:cover;
      background-position:center;
      filter:blur(6px);
      transform:scale(1.06);
      z-index:0;
    }
    .login-background::after{
      content:'';
      position:absolute;
      top:0;
      left:0;
      width:100%;
      height:100%;
      background:rgba(0,0,0,0.3);
    }
    .login-container{
      position:relative;
      z-index:1;
      width:100%;
      max-width:450px;
      padding:20px;
    }
    .login-card{
      background:rgba(255,255,255,0.15);
      backdrop-filter:blur(20px);
      -webkit-backdrop-filter:blur(20px);
      border-radius:20px;
      padding:40px 35px;
      border:1px solid rgba(255,255,255,0.2);
      box-shadow:0 8px 32px rgba(0,0,0,0.3);
    }
    .login-title{
      color:#ffffff;
      font-size:32px;
      font-weight:700;
      text-align:center;
      margin-bottom:35px;
      letter-spacing:-0.5px;
    }
    .field{display:flex;flex-direction:column;gap:6px;margin-bottom:25px}
    .form-input{
      width:100%;
      padding:15px 0;
      background:transparent;
      border:none;
      border-bottom:2px solid rgba(255,255,255,0.4);
      color:#ffffff;
      font-size:16px;
      outline:none;
      transition:border-color 0.3s ease;
    }
    .form-input::placeholder{
      color:rgba(255,255,255,0.6);
    }
    .form-input:focus{
      border-bottom-color:rgba(255,255,255,0.9);
    }
    .form-input:-webkit-autofill,
    .form-input:-webkit-autofill:hover,
    .form-input:-webkit-autofill:focus{
      -webkit-text-fill-color:#ffffff;
      -webkit-box-shadow:0 0 0px 1000px transparent inset;
      transition:background-color 5000s ease-in-out 0s;
    }
    .checkbox-row{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:30px;
      font-size:14px;
    }
    .checkbox-wrapper{
      display:flex;
      align-items:center;
      color:rgba(255,255,255,0.9);
    }
    .checkbox-wrapper input[type="checkbox"]{
      margin-right:8px;
      width:18px;
      height:18px;
      cursor:pointer;
      accent-color:rgba(255,255,255,0.8);
    }
    .forgot-link{
      color:rgba(255,255,255,0.9);
      text-decoration:none;
      transition:opacity 0.2s;
    }
    .forgot-link:hover{
      opacity:0.8;
      text-decoration:underline;
    }
    .submit-btn{
      width:100%;
      padding:16px;
      background:rgba(255,255,255,0.2);
      border:1px solid rgba(255,255,255,0.3);
      border-radius:12px;
      color:#ffffff;
      font-size:16px;
      font-weight:600;
      cursor:pointer;
      transition:all 0.3s ease;
      margin-bottom:25px;
    }
    .submit-btn:hover{
      background:rgba(255,255,255,0.3);
      transform:translateY(-2px);
      box-shadow:0 4px 12px rgba(0,0,0,0.2);
    }
    .submit-btn:active{
      transform:translateY(0);
    }
    .submit-btn:disabled{
      opacity:0.6;
      cursor:not-allowed;
      transform:none;
    }
    .register-link{
      text-align:center;
      color:rgba(255,255,255,0.9);
      font-size:14px;
      margin-top:20px;
    }
    .register-link a{
      color:#ffffff;
      text-decoration:none;
      font-weight:600;
      margin-left:5px;
    }
    .register-link a:hover{
      text-decoration:underline;
    }
    .error-message{
      background:rgba(220,53,69,0.2);
      border:1px solid rgba(220,53,69,0.4);
      color:#ffcccc;
      padding:12px;
      border-radius:8px;
      margin-bottom:20px;
      font-size:14px;
      display:none;
      max-height:120px;
      overflow:auto;
      word-wrap:break-word;
      white-space:pre-wrap;
    }
    .error-message.show{
      display:block;
    }
    .success-message{
      background:rgba(40,167,69,0.2);
      border:1px solid rgba(40,167,69,0.4);
      color:#c8f7dc;
      padding:12px;
      border-radius:8px;
      margin-bottom:20px;
      font-size:14px;
      display:none;
    }
    .success-message.show{ display:block; }
    .turnstile-wrapper{
      margin-bottom:25px;
      display:flex;
      justify-content:center;
    }

    /* connection status */
    .status{position:fixed;right:12px;top:12px;padding:6px 10px;border-radius:100px;background:#052;box-shadow:0 2px 6px rgba(0,0,0,0.6);font-size:13px}
    .status.off{background:#330202}

    /* debug widget */
    .debug-widget{position:fixed;right:20px;bottom:20px;z-index:1400;display:flex;flex-direction:column;align-items:flex-end;gap:10px}
    .debug-button{background:rgba(17,17,17,0.9);color:#fff;border:1px solid rgba(255,255,255,0.2);border-radius:999px;padding:9px 18px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 8px 30px rgba(0,0,0,0.45);backdrop-filter:blur(6px)}
    .debug-button:hover{background:rgba(17,17,17,1)}
    .debug-count{min-width:22px;height:22px;border-radius:999px;background:#dc3545;color:#fff;font-size:12px;display:none;align-items:center;justify-content:center}
    .debug-count.show{display:flex}
    .debug-panel{width:340px;max-width:90vw;max-height:60vh;overflow:auto;background:#161616;border:1px solid rgba(255,255,255,0.1);border-radius:16px;padding:16px;box-shadow:0 20px 60px rgba(0,0,0,0.65);display:none;flex-direction:column;gap:12px}
    .debug-panel.open{display:flex}
    .debug-panel__header{display:flex;align-items:center;justify-content:space-between;gap:12px}
    .debug-panel__title{font-weight:600}
    .debug-panel__subtitle{font-size:12px;color:var(--muted)}
    .debug-panel__actions{display:flex;align-items:center;gap:8px}
    .debug-close{background:transparent;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1;padding:2px 6px}
    .debug-link{background:transparent;border:none;color:var(--accent);cursor:pointer;font-size:13px}
    .debug-panel__content{display:flex;flex-direction:column;gap:10px;font-size:13px}
    .debug-entry{padding:10px;border-radius:10px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05)}
    .debug-entry__meta{display:flex;justify-content:space-between;color:var(--muted);font-size:11px;margin-bottom:4px}
    .debug-entry__message{font-weight:500;margin-bottom:6px}
    .debug-entry pre{margin:0;font-size:11px;background:#0f0f0f;padding:8px;border-radius:8px;overflow:auto}

    /* small helpers */
    .muted{color:var(--muted)}
    .flex{display:flex}
    .space{flex:1}

    .container{gap:16px;padding:16px}
    .rightbar{width:340px;display:flex;flex-direction:column;gap:16px}
    .card{background:var(--panel);border:1px solid var(--border);border-radius:16px;padding:16px}
    .storage-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
    .storage-ring{display:flex;align-items:center;justify-content:center;position:relative;height:200px}
    .ring-center{position:absolute;display:flex;align-items:center;justify-content:center;width:92px;height:92px;border-radius:999px;background:#ffffff;border:1px solid var(--border);font-weight:700;box-shadow:0 6px 18px rgba(0,0,0,0.06)}
    .legend{display:flex;gap:16px;margin-top:12px;color:var(--muted);font-size:12px}
    .upgrade-card{display:flex;flex-direction:column;gap:8px;background:#1f3a7a;border-color:#162c5a;color:#fff}
    .upgrade-btn{background:#27448C;color:#fff;border:none;border-radius:12px;padding:10px 12px;cursor:pointer}
    .fade-in{animation:fadeIn .25s ease}
    @keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
    .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.5)}
    .modal.open{display:flex}
    .modal-card{width:480px;max-width:90vw;background:var(--panel);color:var(--text);border:1px solid var(--border);border-radius:16px;padding:18px;box-shadow:0 20px 60px rgba(0,0,0,0.45)}
    .maintenance-card{border-color:#ffb74d}
    .maintenance-title{font-weight:800;color:#ffb74d}
    .maintenance-desc{color:#ffd699}
    .logout-overlay{position:fixed;inset:0;pointer-events:none;display:none;align-items:center;justify-content:center}
    .logout-overlay.show{display:flex}
    .logout-badge{background:#dc3545;color:#fff;padding:10px 14px;border-radius:999px;border:2px solid #b02a37;animation:pop 0.7s ease}
    @keyframes pop{0%{transform:scale(0.9);opacity:0}50%{transform:scale(1.08);opacity:1}100%{transform:scale(1);opacity:1}}
    .progress{height:10px;border-radius:999px;background:rgba(255,255,255,0.1);border:1px solid var(--border);overflow:hidden}
    .progress > div{height:100%;background:#27448C;width:0%}
    .confirm-card{background:#1f1f1f;border:1px solid #333;border-radius:16px;color:#e6e6e6}
    .confirm-head{padding:16px;text-align:center}
    .confirm-icon{width:48px;height:48px;margin:0 auto;display:flex;align-items:center;justify-content:center;color:#ff4d4f}
    .confirm-icon:hover{animation:bounce .8s infinite}
    @keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
    .confirm-body{padding:0 16px 16px;text-align:center}
    .confirm-actions{display:flex;justify-content:center;gap:10px;padding:12px}
    .btn-pill{border-radius:999px;padding:8px 16px;border:2px solid #555;background:#2a2a2a;color:#ddd}
    .btn-pill.primary{background:#dc3545;border-color:#dc3545;color:#fff}
    @media (max-width:1000px){
      .rightbar{display:none}
    }
    @media (max-width:800px){
      .sidebar{display:none}
      .container{padding:0}
    }
    @media (max-width:600px){
      .content{grid-template-columns:repeat(auto-fill,minmax(140px,1fr));padding:12px}
      .actionbar{flex-wrap:wrap}
    }
  </style>
</head>
<body>
  <div class="app" id="app">

    <!-- Connection status -->
    <div id="connection" class="status">Verbunden</div>
    <div id="appBanner" class="banner danger" style="display:none"></div>

    <!-- Topbar -->
    <header class="topbar">
      <div class="brand">
        <div class="brand-badge">
          <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#27448C"/><stop offset="1" stop-color="#4c6ed6"/></linearGradient></defs><path fill="url(#g2)" d="M24 48c-7.732 0-14-6.268-14-14 0-6.985 5.134-12.76 11.953-13.82C24.6 14.35 28.964 12 34 12c8.284 0 15 6.716 15 15 0 .338-.012.673-.036 1.004C53.73 29.48 58 33.98 58 39.5 58 46.404 52.404 52 45.5 52H24z"/></svg>
        </div>
        <span>MaxxCloud</span>
      </div>
      <label class="theme-toggle" title="Dark Mode">
        <input type="checkbox" id="themeSwitch" />
        <span class="toggle"></span>
      </label>
      <div class="space"></div>
      <div id="profile" class="profile">
        <div class="avatar" id="avatar"></div>
        <div class="username" id="username"></div>
        <div class="profile-menu" id="profileMenu">
          <button class="menu-btn" id="settingsBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 100-6 3 3 0 000 6z"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 01-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09a1.65 1.65 0 00-1-1.51 1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09a1.65 1.65 0 001.51-1 1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9c0 .66.38 1.26 1 1.51.62.25 1.31.49 2 .49"/></svg>
            Einstellungen
          </button>
          <button class="menu-btn danger" id="logoutBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M16 17l5-5-5-5M21 12H9"/></svg>
            Ausloggen
          </button>
        </div>
      </div>
    </header>

    <!-- Main layout -->
    <div class="container">
      <!-- Sidebar -->
      <aside class="sidebar" id="sidebar">
        <div class="muted">Speicher</div>
        <div id="storageText">0 / 0 MB</div>
        <hr style="border:none;border-top:1px solid var(--border);margin:12px 0">
        <div class="muted">Ordner</div>
        <div class="folders" id="folders"></div>
        <div style="margin-top:8px">
          <input type="text" id="newFolderName" placeholder="Neuer Ordner" style="width:100%;padding:10px;border-radius:12px;border:1px solid var(--border);background:#f9fafb;color:var(--text)">
          <div style="display:flex;margin-top:8px;gap:8px">
            <button class="btn primary" id="createFolderBtn">Erstellen</button>
            <button class="btn" id="refreshBtn">Aktualisieren</button>
          </div>
        </div>
      </aside>

      <!-- Main content -->
      <main>
        <div class="actionbar">
          <div class="search">
            <svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35"/><circle cx="11" cy="11" r="6" stroke="currentColor" stroke-width="2"/></svg>
            <input id="search" placeholder="Dateien durchsuchen..." />
          </div>
          <input type="file" id="fileInput" style="display:none" />
          <button class="btn primary" id="uploadBtn">Hochladen</button>
        </div>

        <div id="grid" class="content" aria-live="polite"></div>
      </main>

      <aside class="rightbar">
        <div class="card">
          <div class="storage-header">
            <div>Data Storage</div>
            <div id="storageValue">0 MB</div>
          </div>
          <div class="storage-ring">
            <svg width="200" height="200" viewBox="0 0 120 120">
              <circle cx="60" cy="60" r="54" stroke="#e5e7eb" stroke-width="12" fill="none"/>
              <circle id="ringFill" cx="60" cy="60" r="54" stroke="#27448C" stroke-width="12" fill="none" stroke-linecap="round" transform="rotate(-90 60 60)" stroke-dasharray="339.292" stroke-dashoffset="339.292"/>
            </svg>
            <div class="ring-center" id="storagePercent">0%</div>
          </div>
          <div class="legend"><span>Used</span><span>Free</span></div>
        </div>
        <div class="card upload-card">
          <div class="glow left"></div>
          <div class="glow right"></div>
          <div class="upload-head">
            <div>
              <div style="font-weight:700">Dateien hochladen</div>
              <div class="muted">Ziehe Dateien hierher oder auswählen</div>
            </div>
            <div class="upload-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path stroke="var(--accent)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
            </div>
          </div>
          <div class="dropzone" id="rightDropzone">
            <input type="file" id="rightUploadInput" multiple />
            <div class="drop-content">
              <div class="drop-badge">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path stroke="var(--accent)" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <div style="margin-top:8px">
                <div style="font-weight:600">Dateien hier ablegen oder durchsuchen</div>
                <div class="muted">Unterstützt: PDF, DOC, DOCX, JPG, PNG</div>
                <div class="muted" style="font-size:12px">Max. Dateigröße: 10MB</div>
              </div>
            </div>
          </div>
        </div>
        <div class="card">
          <div style="font-weight:700;margin-bottom:8px">Code einlösen</div>
          <div style="display:flex;gap:8px">
            <input id="redeemInput" placeholder="Code" style="flex:1;padding:10px;border-radius:12px;background:rgba(255,255,255,0.12);border:1px solid var(--border);color:var(--text)">
            <button class="btn primary" id="redeemBtn">Einlösen</button>
          </div>
          <div class="muted" style="margin-top:8px">Erhöht dein Speicherlimit</div>
        </div>
        
      </aside>
    </div>

    <div id="uploadModal" class="modal">
      <div class="modal-card fade-in">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div style="font-weight:700">Upload läuft</div>
          <div id="uploadPercent">0%</div>
        </div>
        <div class="progress"><div id="uploadBar"></div></div>
        <div id="uploadInfo" style="margin-top:10px;font-size:13px;color:var(--muted)"></div>
      </div>
    </div>

    <div id="confirmModal" class="modal">
      <div class="modal-card confirm-card fade-in" style="width:420px">
        <div class="confirm-head">
          <div class="confirm-icon">
            <svg fill="currentColor" viewBox="0 0 20 20" class="w-12 h-12" xmlns="http://www.w3.org/2000/svg">
              <path clip-rule="evenodd" fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"></path>
            </svg>
          </div>
          <h2 style="font-weight:700;margin-top:8px">Bist du sicher?</h2>
          <div class="muted" style="font-size:13px">Willst du wirklich fortfahren? Dieser Vorgang kann nicht rückgängig gemacht werden.</div>
        </div>
        <div class="confirm-actions">
          <button class="btn-pill" id="confirmCancel">Abbrechen</button>
          <button class="btn-pill primary" id="confirmOk">Löschen</button>
        </div>
      </div>
    </div>
    <div id="maintenanceModal" class="modal">
      <div class="modal-card maintenance-card fade-in" style="width:520px">
        <div class="maintenance-title">Wartungsmodus aktiv</div>
        <div class="maintenance-desc" style="margin-top:8px">MaxxCloud befindet sich im Wartungsmodus. Es kann zu Ausfällen kommen.</div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
          <button class="btn" id="maintenanceClose">Verstanden</button>
        </div>
      </div>
    </div>
    <div id="logoutOverlay" class="logout-overlay">
      <div class="logout-badge">Ausgeloggt</div>
    </div>

    <div id="settingsModal" class="modal">
      <div class="modal-card fade-in" style="width:480px">
        <div style="font-weight:700;margin-bottom:8px">Einstellungen</div>
        <div style="display:flex;flex-direction:column;gap:10px">
          <input id="displayNameInput" placeholder="Anzeigename" style="padding:10px;border-radius:12px;background:rgba(255,255,255,0.12);border:1px solid var(--border);color:var(--text)">
          <div>
            <div class="muted" style="margin-bottom:6px">Avatar hochladen</div>
            <input type="file" id="avatarFileInput" accept="image/png,image/jpeg,image/webp" />
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
          <button class="btn" id="settingsCancel">Abbrechen</button>
          <button class="btn primary" id="settingsSave">Speichern</button>
        </div>
      </div>
    </div>

    <!-- Login screen (hidden when logged in) -->
    <div class="login-screen" id="loginScreen" style="display:none">
      <div class="login-background"></div>
      <div class="login-container">
        <div class="login-card" id="loginCard">
          <h1 class="login-title">Login</h1>
          
          <div id="errorMessage" class="error-message"></div>
          <div id="successMessage" class="success-message"></div>

          <form id="loginForm">
            <div class="field">
              <input 
                type="email" 
                id="loginEmail" 
                class="form-input" 
                placeholder="Gebe deine Email Adresse ein" 
                required 
                autocomplete="email"
              />
            </div>

            <div class="field">
              <input 
                type="password" 
                id="loginPassword" 
                class="form-input" 
                placeholder="Gebe dein Passwort ein" 
                required 
                autocomplete="current-password"
              />
            </div>

            <div class="checkbox-row">
              <label class="checkbox-wrapper">
                <input type="checkbox" id="stayLoggedIn" />
                <span>Bleibe angemeldet</span>
              </label>
              <a href="#" class="forgot-link">Passwort vergessen?</a>
            </div>

            <?php if ($turnstileEnabled): ?>
              <div class="turnstile-wrapper">
                <div class="cf-turnstile" 
                     data-sitekey="<?php echo htmlspecialchars($config['turnstile_site_key'], ENT_QUOTES); ?>"
                     data-theme="light"
                     data-size="normal"
                     data-callback="onTurnstileSuccess"
                     data-expired-callback="onTurnstileExpired"
                     data-error-callback="onTurnstileError"></div>
              </div>
            <?php endif; ?>

            <button type="submit" class="submit-btn" id="loginBtn">
              Anmelden
            </button>
          </form>

          <div class="register-link">
            Du hast noch keinen Account? <a href="register.php">Registrieren</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Debug widget -->
    <div class="debug-widget" id="debugWidget">
      <div id="debugPanel" class="debug-panel" aria-hidden="true">
        <div class="debug-panel__header">
          <div>
            <div class="debug-panel__title">Debug Protokoll</div>
            <div class="debug-panel__subtitle">Letzte Meldungen</div>
          </div>
          <div class="debug-panel__actions">
            <button type="button" class="debug-link" id="debugCopy">Alles kopieren</button>
            <button type="button" class="debug-link" id="debugClear">Leeren</button>
            <button type="button" class="debug-close" id="debugClose" aria-label="Debug schließen">&times;</button>
          </div>
        </div>
        <div id="debugContent" class="debug-panel__content">
          <div class="muted">Keine Fehler protokolliert.</div>
        </div>
      </div>
      <button class="debug-button" id="debugToggle" type="button">
        Debug
        <span class="debug-count" id="debugCount"></span>
      </button>
    </div>

  </div>

  <script>
    const API_BASE = 'api.php';
    const DOWNLOAD_BASE = 'download.php?id=';
    const TURNSTILE_SITE_KEY = '<?php echo htmlspecialchars($config['turnstile_site_key'], ENT_QUOTES); ?>';
    const TURNSTILE_ENABLED = <?php echo $turnstileEnabled ? 'true' : 'false'; ?>;
    const THEME_KEY = 'maxx_theme';

    const state = {
      user: null,
      folders: [],
      files: [],
      currentFolderId: null,
      storage: {megabytes: 0},
      connected: true,
      cfToken: null,
      errors: [],
      debugOpen: false
    };

    const MAX_DEBUG_ENTRIES = 50;
    const el = id => document.getElementById(id);

    function createApiError(message, data = {}){
      const details = data.details ?? data.detail ?? null;
      const fullMessage = details ? `${message} — ${details}` : message;
      const error = new Error(fullMessage);
      error.details = details;
      error.meta = data;
      return error;
    }

    function logError(source, message, detail = null){
      const entry = {
        id: `${Date.now()}-${Math.random().toString(36).slice(2,7)}`,
        source,
        message: message || 'Unbekannter Fehler',
        detail: detail ? formatDetail(detail) : null,
        timestamp: new Date()
      };
      state.errors = [entry, ...state.errors].slice(0, MAX_DEBUG_ENTRIES);
      renderDebugLog();
    }

    function renderDebugLog(){
      const target = el('debugContent');
      const badge = el('debugCount');
      if(!target || !badge){
        return;
      }
      if(state.errors.length === 0){
        target.innerHTML = '<div class="muted">Keine Fehler protokolliert.</div>';
      }else{
        target.innerHTML = state.errors.map(entry => `
          <div class="debug-entry">
            <div class="debug-entry__meta">
              <span>${escapeHtml(entry.source)}</span>
              <span>${formatTime(entry.timestamp)}</span>
            </div>
            <div class="debug-entry__message">${escapeHtml(entry.message)}</div>
            ${entry.detail ? `<pre>${escapeHtml(entry.detail)}</pre>` : ''}
          </div>
        `).join('');
      }
      badge.textContent = state.errors.length ? state.errors.length : '';
      badge.classList.toggle('show', state.errors.length > 0);
    }

    function setDebugOpen(open){
      state.debugOpen = open;
      const panel = el('debugPanel');
      if(panel){
        panel.classList.toggle('open', open);
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
      }
    }

    function clearDebugLog(){
      state.errors = [];
      renderDebugLog();
    }

    async function copyDebugLog(){
      const btn = el('debugCopy');
      const fallbackText = 'Keine Fehler protokolliert.';
      const text = state.errors.length
        ? state.errors.map(entry=>{
            const detail = entry.detail ? `\nDetails: ${entry.detail}` : '';
            return `[${formatDateTime(entry.timestamp)}] ${entry.source}: ${entry.message}${detail}`;
          }).join('\n\n')
        : fallbackText;
      try{
        if(navigator?.clipboard?.writeText){
          await navigator.clipboard.writeText(text);
        }else{
          const ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.focus();
          ta.select();
          const ok = document.execCommand('copy');
          document.body.removeChild(ta);
          if(!ok){
            throw new Error('Clipboard API nicht verfügbar');
          }
        }
        if(btn){
          const previous = btn.textContent;
          btn.textContent = 'Kopiert!';
          btn.disabled = true;
          setTimeout(()=>{
            btn.textContent = previous;
            btn.disabled = false;
          }, 1500);
        }
      }catch(err){
        logError('DebugCopy', err?.message || 'Kopieren fehlgeschlagen', err?.stack || err);
        alert('Debug-Log konnte nicht kopiert werden. Bitte Browser-Rechte prüfen.');
      }
    }

    function showStatus(connected){
      const s = el('connection');
      s.textContent = connected ? 'Verbunden' : 'Offline';
      s.classList.toggle('off', !connected);
    }

    async function api(action, payload = {}, options = {}){
      const isForm = payload instanceof FormData;
      if(isForm){
        payload.append('action', action);
      }
      const body = isForm ? payload : JSON.stringify({action, ...payload});
      const headers = isForm ? {} : {'Content-Type':'application/json'};

      try{
        const res = await fetch(API_BASE, {
          method: options.method || 'POST',
          headers,
          body,
          credentials: 'same-origin'
        });
        const raw = await res.text();
        let data = {};
        if(raw){
          try{
            data = JSON.parse(raw);
          }catch(parseErr){
            throw createApiError('Ungültige Server-Antwort', {details: raw.slice(0, 400)});
          }
        }
        if(!res.ok || data.error){
          const meta = Object.assign({}, data, {status: res.status});
          throw createApiError(data.error || 'Unbekannter Fehler', meta);
        }
        state.connected = true;
        showStatus(true);
        return data;
      }catch(err){
        state.connected = false;
        showStatus(false);
        const errorMessage = err?.message || 'Unbekannter Fehler';
        const details = err?.details || err?.meta?.details || err?.meta || err?.stack || err;
        logError(`API:${action}`, errorMessage, details);
        throw err;
      }
    }

    async function refreshState(){
      try{
        const data = await api('status');
        state.user = data.user;
        state.folders = data.folders;
        state.files = data.files;
        state.storage = data.storage;
        state.maintenance = !!data.maintenance;
        renderUser();
        renderFolders();
        renderGrid();
        renderStorageWidget();
        await updateAppBanner();
      }catch(err){
        console.error(err);
        if(err?.meta?.status === 403 && err?.meta?.banned){
          showBannedBanner();
        }else{
          logError('Status', err?.message || 'Status fehlgeschlagen', err?.stack || err);
        }
        await updateAppBanner();
      }
    }

    function showBannedBanner(){
      const loginCard = document.getElementById('loginCard');
      if(!loginCard) return;
      let banner = document.getElementById('bannedBanner');
      if(!banner){
        banner = document.createElement('div');
        banner.id='bannedBanner';
        banner.className='banner danger';
        loginCard.insertBefore(banner, loginCard.firstChild);
      }
      banner.style.display='block';
      banner.textContent='Dein Account ist gesperrt';
    }

    function showUnverifiedBanner(email){
      const loginCard = document.getElementById('loginCard');
      if(!loginCard) return;
      let banner = document.getElementById('unverifiedBanner');
      if(!banner){
        banner = document.createElement('div');
        banner.id='unverifiedBanner';
        banner.className='banner warning';
        banner.innerHTML = 'Email noch nicht verifiziert. ';
        const btn = document.createElement('button');
        btn.className='btn';
        btn.textContent='Jetzt verifizieren';
        btn.onclick = async ()=>{
          try{ await api('resend_verification', {email}); showSuccess('Bestätigungslink gesendet'); }
          catch(err){ showError(err.message || 'Versand fehlgeschlagen'); }
        };
        banner.appendChild(btn);
        loginCard.insertBefore(banner, loginCard.firstChild);
      }
      banner.style.display='block';
    }

    function renderUser(){
      const container = document.querySelector('.container');
      const sidebar = el('sidebar');
      if(state.user){
        const name = state.user.display_name || (state.user.email ? state.user.email.split('@')[0] : 'User');
        const usernameEl = el('username');
        if(usernameEl){ usernameEl.textContent = name; }
        const avatarEl = el('avatar');
        if(avatarEl){
          const url = state.user.avatar_url;
          if(url){
            const src = `${API_BASE}?action=avatar&path=${encodeURIComponent(url)}`;
            avatarEl.innerHTML = `<img src="${src}" alt="avatar">`;
          }else{
            avatarEl.innerHTML = `<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g2a" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#27448C"/><stop offset="1" stop-color="#4c6ed6"/></linearGradient></defs><path fill="url(#g2a)" d="M24 48c-7.732 0-14-6.268-14-14 0-6.985 5.134-12.76 11.953-13.82C24.6 14.35 28.964 12 34 12c8.284 0 15 6.716 15 15 0 .338-.012.673-.036 1.004C53.73 29.48 58 33.98 58 39.5 58 46.404 52.404 52 45.5 52H24z"/></svg>`;
          }
        }
        el('loginScreen').style.display='none';
        container.style.display='flex';
        sidebar.style.display='flex';
        el('storageText').textContent = `${state.storage.megabytes ?? 0} / ${state.user.storage_limit} MB`;
        renderStorageWidget();
        if(state.maintenance){
          const m = el('maintenanceModal');
          const c = el('maintenanceClose');
          if(m){ m.classList.add('open'); }
          if(c){ c.onclick = ()=> m?.classList.remove('open'); }
        }
      }else{
        const usernameEl = el('username');
        if(usernameEl){ usernameEl.textContent=''; }
        const avatarEl = el('avatar');
        if(avatarEl){ avatarEl.innerHTML=''; }
        el('loginScreen').style.display='flex';
        container.style.display='none';
        sidebar.style.display='none';
        el('storageText').textContent = '0 / 0 MB';
        renderStorageWidget(true);
      }
    }

    function renderFolders(){
      const wrap = el('folders');
      wrap.innerHTML='';
      const rootBtn = document.createElement('div');
      rootBtn.className='folder'+(state.currentFolderId===null?' active':'');
      rootBtn.textContent='Alle Dateien';
      rootBtn.onclick=()=>{ state.currentFolderId=null; renderFolders(); renderGrid(); };
      wrap.appendChild(rootBtn);

      state.folders.forEach(f=>{
        const d = document.createElement('div');
        d.className='folder'+(state.currentFolderId===f.id?' active':'');
        d.textContent=f.name;
        d.onclick=()=>{ state.currentFolderId=f.id; renderFolders(); renderGrid(); };
        wrap.appendChild(d);
      });
    }

    function renderGrid(){
      const grid = el('grid');
      grid.innerHTML='';
      if(!state.user){
        grid.innerHTML='<div class="muted">Bitte zuerst einloggen.</div>';
        return;
      }

      const q = (el('search').value || '').toLowerCase().trim();
      const files = state.files.filter(file=>{
        if(state.currentFolderId!=null && file.folder_id!==state.currentFolderId) return false;
        if(!q) return true;
        return file.original_name.toLowerCase().includes(q);
      });

      if(files.length===0){
        grid.innerHTML='<div class="muted">Keine Dateien gefunden.</div>';
        return;
      }

      files.forEach(f=>{
        const card = document.createElement('div');
        card.className='file';
        const title = document.createElement('div');
        title.style.display='flex';
        title.style.justifyContent='space-between';
        title.style.alignItems='center';
        title.innerHTML = `<div class="name">${escapeHtml(f.original_name)}</div><div class="muted">${f.size_kb} KB</div>`;
        const meta = document.createElement('div');
        meta.className='meta';
        meta.textContent = f.mime_type || 'unbekannt';
        const actions = document.createElement('div');
        actions.className='action-row';

        const dl = document.createElement('a');
        dl.className='btn';
        dl.textContent='Download';
        dl.href=`${DOWNLOAD_BASE}${f.id}`;
        dl.onclick=(ev)=>{ ev.stopPropagation(); };

        const share = document.createElement('button');
        share.className='btn';
        share.textContent='Link';
        share.onclick=()=>createShareLink(f.id);

        const del = document.createElement('button');
        del.className='btn';
        del.textContent='Löschen';
        del.onclick=()=>requestDelete(f.id);

        actions.appendChild(dl);
        actions.appendChild(share);
        actions.appendChild(del);

        card.appendChild(title);
        card.appendChild(meta);
        card.appendChild(actions);
        grid.appendChild(card);
      });
    }

    function renderStorageWidget(reset){
      const valueEl = document.getElementById('storageValue');
      const percentEl = document.getElementById('storagePercent');
      const ringEl = document.getElementById('ringFill');
      if(!valueEl || !percentEl || !ringEl){
        return;
      }
      if(reset || !state.user){
        valueEl.textContent = '0 MB';
        percentEl.textContent = '0%';
        ringEl.style.strokeDashoffset = '339.292';
        return;
      }
      const used = Number(state.storage.megabytes || 0);
      const limit = Number(state.user.storage_limit || 0);
      const pct = limit > 0 ? Math.min(1, Math.max(0, used / limit)) : 0;
      const circumference = 339.292;
      const offset = circumference * (1 - pct);
      valueEl.textContent = `${used} MB`;
      percentEl.textContent = `${Math.round(pct*100)}%`;
      ringEl.style.strokeDashoffset = String(offset);
    }

    async function redeemCode(){
      const input = el('redeemInput');
      const code = (input?.value || '').trim();
      if(!code){
        showError('Bitte Code eingeben');
        return;
      }
      try{
        const res = await api('redeem_code', {code});
        await refreshState();
        showError('Code eingelöst. Neues Limit: '+(res?.new_limit_mb ?? '')+' MB');
        input.value='';
      }catch(err){
        showError(err?.message || 'Code konnte nicht eingelöst werden');
      }
    }

    function escapeHtml(s){
      return (s || '').toString().replace(/[&<>"]/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' })[c]);
    }

    function formatTime(date){
      const ts = date instanceof Date ? date : new Date(date);
      return ts.toLocaleTimeString('de-DE', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    }

    function formatDateTime(date){
      const ts = date instanceof Date ? date : new Date(date);
      return ts.toLocaleString('de-DE', {
        year:'numeric',
        month:'2-digit',
        day:'2-digit',
        hour:'2-digit',
        minute:'2-digit',
        second:'2-digit'
      });
    }

    function formatDetail(detail){
      if(!detail){
        return '';
      }
      if(detail instanceof Error){
        return detail.stack || detail.message;
      }
      if(typeof detail === 'string'){
        return detail.substring(0, 500);
      }
      try{
        const json = JSON.stringify(detail, null, 2);
        return json.substring(0, 800);
      }catch(_err){
        return String(detail).substring(0, 500);
      }
    }

    function showError(message){
      const err = el('errorMessage');
      const truncated = (message || '').substring(0, 300);
      err.textContent = truncated;
      err.title = message;
      err.classList.add('show');
      const duration = Math.max(3000, message?.length * 30);
      setTimeout(() => err.classList.remove('show'), Math.min(duration, 10000));
      logError('UI', message);
    }
    function showSuccess(message){
      const elx = document.getElementById('successMessage');
      if(!elx) return;
      const truncated = (message || '').substring(0, 300);
      elx.textContent = truncated;
      elx.title = message;
      elx.classList.add('show');
      const duration = Math.max(3000, message?.length * 30);
      setTimeout(() => elx.classList.remove('show'), Math.min(duration, 8000));
    }

    // Turnstile Callbacks
    if(TURNSTILE_ENABLED){
      window.onTurnstileSuccess = function(token){
        state.cfToken = token;
      };

      window.onTurnstileError = function(){
        state.cfToken = null;
        showError('Captcha-Fehler. Bitte versuche es erneut.');
      };

      window.onTurnstileExpired = function(){
        state.cfToken = null;
      };
    }

    async function login(){
      const email = el('loginEmail').value.trim();
      const password = el('loginPassword').value;

      if(!email){
        showError('Bitte gib deine Email-Adresse ein');
        return;
      }

      if(!password){
        showError('Bitte gib dein Passwort ein');
        return;
      }

      if(TURNSTILE_ENABLED && !state.cfToken){
        showError('Bitte löse das Captcha');
        return;
      }

      const btn = el('loginBtn');
      btn.disabled = true;
      btn.textContent = 'Anmeldung läuft...';

      try{
        const payload = {email, password};
        if(TURNSTILE_ENABLED){
          payload.cfToken = state.cfToken;
        }
        await api('login', payload);
        await refreshState();
        resetTurnstile();
      }catch(err){
        if(err?.meta?.unverified){
          showUnverifiedBanner(email);
        } else {
          showError(err.message || 'Login fehlgeschlagen');
        }
        resetTurnstile();
      }finally{
        btn.disabled = false;
        btn.textContent = 'Anmelden';
      }
    }

    async function logout(){
      try{
        await api('logout');
        state.user = null;
        state.files = [];
        state.folders = [];
        const ov = el('logoutOverlay');
        if(ov){ ov.classList.add('show'); setTimeout(()=> ov.classList.remove('show'), 1500); }
        renderUser();
        renderFolders();
        renderGrid();
      }catch(err){
        console.error(err);
        logError('Logout', err?.message || 'Logout fehlgeschlagen', err?.stack || err);
      }
    }

    async function createFolder(){
      const name = el('newFolderName').value.trim();
      if(!name){
        alert('Bitte Ordnernamen eingeben');
        return;
      }
      try{
        await api('create_folder', {name});
        el('newFolderName').value='';
        await refreshState();
      }catch(err){
        alert(err.message || 'Ordner konnte nicht erstellt werden');
        logError('Ordner', err?.message || 'Ordner konnte nicht erstellt werden', err?.stack || err);
      }
    }

    async function uploadFile(file){
      if(!file){
        return;
      }
      const MAX_FILE_BYTES = 50 * 1024 * 1024 * 1024;
      const limitMb = Number(state.user?.storage_limit || 0);
      const usedMb = Number(state.storage?.megabytes || 0);
      const freeMb = Math.max(0, limitMb - usedMb);
      const freeBytes = freeMb * 1024 * 1024;
      if(file.size > MAX_FILE_BYTES){
        showError('Maximale Dateigröße ist 50GB');
        return;
      }
      if(file.size > freeBytes){
        showError(`Zu wenig freier Speicher (${freeMb} MB frei)`);
        return;
      }
      const form = new FormData();
      form.append('action', 'upload_file');
      form.append('file', file);
      if(state.currentFolderId !== null){
        form.append('folder_id', state.currentFolderId);
      }

      const uploadBtn = el('uploadBtn');
      const originalText = uploadBtn?.textContent;
      if(uploadBtn){
        uploadBtn.disabled = true;
        uploadBtn.textContent = `Wird hochgeladen... (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
      }
      showUploadModal(file);

      try{
        await xhrUpload(form);
        showError(`Datei "${file.name}" erfolgreich hochgeladen`);
        hideUploadModal();
        await refreshState();
      }catch(err){
        hideUploadModal();
        showError(err.message || 'Upload fehlgeschlagen');
        logError('Upload', err?.message || 'Upload fehlgeschlagen', err?.meta?.details || err?.stack || err);
      }finally{
        el('fileInput').value='';
        if(uploadBtn){
          uploadBtn.disabled = false;
          uploadBtn.textContent = originalText;
        }
      }
    }

    function showUploadModal(file){
      const m = el('uploadModal');
      const info = el('uploadInfo');
      const bar = el('uploadBar');
      const pct = el('uploadPercent');
      if(m){ m.classList.add('open'); }
      if(info){ info.textContent = `${file.name} — ${(file.size/1024/1024).toFixed(2)} MB`; }
      if(bar){ bar.style.width = '0%'; }
      if(pct){ pct.textContent = '0%'; }
    }
    function hideUploadModal(){
      const m = el('uploadModal');
      if(m){ m.classList.remove('open'); }
    }
    async function createShareLink(fileId){
      try{
        const data = await api('create_share_link', {file_id: fileId});
        const url = data.url;
        await navigator.clipboard.writeText(url);
        showSuccess('Freigabe-Link kopiert');
      }catch(err){
        showError(err.message || 'Freigabe fehlgeschlagen');
      }
    }
    function setUploadProgress(p){
      const bar = el('uploadBar');
      const pct = el('uploadPercent');
      const clamped = Math.max(0, Math.min(100, p));
      if(bar){ bar.style.width = clamped + '%'; }
      if(pct){ pct.textContent = Math.round(clamped) + '%'; }
    }
    function xhrUpload(form){
      return new Promise((resolve, reject)=>{
        const xhr = new XMLHttpRequest();
        xhr.open('POST', API_BASE, true);
        xhr.upload.onprogress = (e)=>{
          if(e.lengthComputable){
            const p = (e.loaded / e.total) * 100;
            setUploadProgress(p);
          }
        };
        xhr.onreadystatechange = function(){
          if(xhr.readyState === 4){
            try{
              const ok = xhr.status >= 200 && xhr.status < 300;
              const data = xhr.responseText ? JSON.parse(xhr.responseText) : {};
              if(!ok || data.error){
                reject(new Error(data.error || 'Upload fehlgeschlagen'));
              }else{
                resolve(data);
              }
            }catch(err){
              reject(err);
            }
          }
        };
        xhr.onerror = ()=> reject(new Error('Netzwerkfehler'));
        xhr.send(form);
      });
    }

    function requestDelete(fileId){
      state.pendingDeleteId = fileId;
      const m = el('confirmModal');
      if(m){ m.classList.add('open'); }
    }
    function hideConfirm(){
      const m = el('confirmModal');
      if(m){ m.classList.remove('open'); }
      state.pendingDeleteId = null;
    }
    async function performDelete(){
      const id = state.pendingDeleteId;
      if(!id){ hideConfirm(); return; }
      try{
        await api('delete_file', {file_id: id});
        await refreshState();
      }catch(err){
        alert(err.message || 'Löschen fehlgeschlagen');
        logError('Löschen', err?.message || 'Löschen fehlgeschlagen', err?.stack || err);
      }finally{
        hideConfirm();
      }
    }

    function resetTurnstile(){
      state.cfToken = null;
      if(!TURNSTILE_ENABLED){
        return;
      }
      if(window.turnstile){
        window.turnstile.reset();
      }
    }

    function wire(){
      el('loginForm').addEventListener('submit', (e) => {
        e.preventDefault();
        login();
      });
      el('logoutBtn').addEventListener('click', logout);
      el('createFolderBtn').addEventListener('click', createFolder);
      el('refreshBtn').addEventListener('click', refreshState);
      el('uploadBtn').addEventListener('click',()=>el('fileInput').click());
      el('fileInput').addEventListener('change', ev=>uploadFile(ev.target.files[0]));
      el('search').addEventListener('input', renderGrid);
      const debugToggle = el('debugToggle');
      if(debugToggle){
        debugToggle.addEventListener('click', ()=>setDebugOpen(!state.debugOpen));
      }
      const debugClose = el('debugClose');
      if(debugClose){
        debugClose.addEventListener('click', ()=>setDebugOpen(false));
      }
      const debugClear = el('debugClear');
      if(debugClear){
        debugClear.addEventListener('click', clearDebugLog);
      }
      const debugCopy = el('debugCopy');
      if(debugCopy){
        debugCopy.addEventListener('click', copyDebugLog);
      }
      const themeSwitch = el('themeSwitch');
      if(themeSwitch){
        themeSwitch.addEventListener('change', ev=> setTheme(ev.target.checked ? 'dark' : 'light'));
      }
      const redeemBtn = el('redeemBtn');
      if(redeemBtn){
        redeemBtn.addEventListener('click', redeemCode);
      }
      const profile = document.getElementById('profile');
      const profileMenu = document.getElementById('profileMenu');
      let profileHideT = null;
      function openProfileMenu(){ profile?.classList.add('open'); }
      function closeProfileMenu(){ profile?.classList.remove('open'); }
      if(profile){
        profile.addEventListener('mouseenter', ()=>{ if(profileHideT){ clearTimeout(profileHideT); } openProfileMenu(); });
        profile.addEventListener('mouseleave', ()=>{ profileHideT = setTimeout(()=>{ closeProfileMenu(); }, 200); });
      }
      if(profileMenu){
        profileMenu.addEventListener('mouseenter', ()=>{ if(profileHideT){ clearTimeout(profileHideT); } openProfileMenu(); });
        profileMenu.addEventListener('mouseleave', ()=>{ profileHideT = setTimeout(()=>{ closeProfileMenu(); }, 200); });
      }
      const settingsBtn = el('settingsBtn');
      if(settingsBtn){ settingsBtn.addEventListener('click', openSettings); }
      const cCancel = el('confirmCancel');
      const cOk = el('confirmOk');
      if(cCancel){ cCancel.addEventListener('click', hideConfirm); }
      if(cOk){ cOk.addEventListener('click', performDelete); }
      const rz = el('rightDropzone');
      const ru = el('rightUploadInput');
      if(rz){
        ['dragover','dragenter'].forEach(evt=> rz.addEventListener(evt, e=>{e.preventDefault(); rz.classList.add('active');}));
        ['dragleave','dragend'].forEach(evt=> rz.addEventListener(evt, ()=> rz.classList.remove('active')));
        rz.addEventListener('drop', async e=>{
          e.preventDefault();
          rz.classList.remove('active');
          const files = e.dataTransfer?.files || [];
          for(let i=0;i<files.length;i++){
            await uploadFile(files[i]);
          }
        });
      }
      if(ru){
        ru.addEventListener('change', async ev=>{
          const files = ev.target.files || [];
          for(let i=0;i<files.length;i++){
            await uploadFile(files[i]);
          }
          ru.value='';
        });
      }
      document.addEventListener('keydown', event=>{
        if(event.key === 'Escape' && state.debugOpen){
          setDebugOpen(false);
        }
      });
      renderDebugLog();
      const forgot = document.querySelector('.forgot-link');
      if(forgot){
        forgot.addEventListener('click', async (e)=>{
          e.preventDefault();
          const email = prompt('Email für Passwort-Reset eingeben:');
          if(!email) return;
          try{
            await api('request_password_reset', {email});
            showSuccess('Falls die Email existiert, wurde ein Reset-Link gesendet.');
          }catch(err){
            showError(err.message || 'Reset fehlgeschlagen');
          }
        });
      }
    }

    async function updateBucketBanner(){
      try{
        const res = await api('buckets_status', {});
        const list = res?.buckets || [];
        const disabled = list.filter(b=> !b.active).map(b=> b.bucket_id);
        const loginCard = document.getElementById('loginCard');
        if(loginCard){
          let banner = document.getElementById('bucketBanner');
          if(!banner){
            banner = document.createElement('div');
            banner.id='bucketBanner';
            banner.className='banner danger';
            loginCard.insertBefore(banner, loginCard.firstChild);
          }
          banner.style.display = disabled.length ? 'block' : 'none';
          banner.textContent = disabled.length ? (`Offline: ${disabled.join(', ')}`) : '';
        }
      }catch(err){
        // Ignorieren
      }
    }

    async function updateAppBanner(){
      try{
        const res = await api('buckets_status', {});
        const list = res?.buckets || [];
        const map = new Map(list.map(b=> [b.bucket_id, !!b.active]));
        const banner = el('appBanner');
        const b = state.user?.bucket_id || null;
        const active = b ? map.get(b) : true;
        if(banner){
          if(b && active === false){
            banner.style.display='block';
            banner.textContent = `Bucket deaktiviert: ${b}`;
          }else{
            banner.style.display='none';
            banner.textContent = '';
          }
        }
      }catch(err){
        // Ignorieren
      }
    }

    window.addEventListener('error', event=>{
      const meta = event?.error?.stack || `${event.filename || ''}:${event.lineno || ''}`;
      logError('Runtime', event?.message || 'Unbekannter Fehler', meta);
    });

    window.addEventListener('unhandledrejection', event=>{
      const reason = event?.reason;
      const message = (reason && reason.message) ? reason.message : (typeof reason === 'string' ? reason : 'Unbehandelte Promise-Ablehnung');
      const detail = reason?.stack || (typeof reason === 'string' ? reason : null);
      logError('Promise', message, detail);
    });

    (async function init(){
      wire();
      showStatus(true);
      const savedTheme = localStorage.getItem(THEME_KEY);
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      setTheme(savedTheme ? savedTheme : (prefersDark ? 'dark' : 'light'));
      try{
        const url = new URL(window.location.href);
        const reset = url.searchParams.get('reset');
        const verified = url.searchParams.get('verified');
        if(verified){
          showSuccess('Email erfolgreich verifiziert');
          url.searchParams.delete('verified');
          history.replaceState(null,'',url.toString());
        }
        if(reset){
          const p1 = prompt('Neues Passwort eingeben (min. 8 Zeichen):');
          if(p1 && p1.length>=8){
            const p2 = prompt('Passwort bestätigen:');
            if(p1===p2){
              try{ await api('perform_password_reset', {token: reset, password: p1}); showSuccess('Passwort wurde zurückgesetzt'); }catch(err){ showError(err.message || 'Reset fehlgeschlagen'); }
            } else {
              showError('Passwörter stimmen nicht überein');
            }
          } else if(p1){
            showError('Passwort zu kurz');
          }
          url.searchParams.delete('reset');
          history.replaceState(null,'',url.toString());
        }
      }catch(_e){}
      await refreshState();
    })();

    function setTheme(theme){
      document.documentElement.setAttribute('data-theme', theme);
      const sw = document.getElementById('themeSwitch');
      if(sw){ sw.checked = theme === 'dark'; }
      try{ localStorage.setItem(THEME_KEY, theme); }catch(_e){}
    }

    function openSettings(){
      const m = el('settingsModal');
      const dn = el('displayNameInput');
      if(dn){ dn.value = state.user?.display_name || (state.user?.email ? state.user.email.split('@')[0] : ''); }
      const af = el('avatarFileInput');
      if(af){ af.value=''; }
      if(m){ m.classList.add('open'); }
      const cancel = el('settingsCancel');
      const save = el('settingsSave');
      if(cancel){ cancel.onclick = ()=>{ m?.classList.remove('open'); }; }
      if(save){ save.onclick = saveSettings; }
    }
    async function saveSettings(){
      const dn = el('displayNameInput')?.value || '';
      const file = el('avatarFileInput')?.files?.[0] || null;
      const form = new FormData();
      form.append('action','update_profile');
      form.append('display_name', dn);
      if(file){ form.append('avatar', file); }
      try{
        const res = await xhrJson(form);
        if(res?.error){ throw new Error(res.error); }
        el('settingsModal')?.classList.remove('open');
        await refreshState();
      }catch(err){
        showError(err?.message || 'Einstellungen konnten nicht gespeichert werden');
      }
    }

    function xhrJson(form){
      return new Promise((resolve, reject)=>{
        const xhr = new XMLHttpRequest();
        xhr.open('POST', API_BASE, true);
        xhr.onreadystatechange = function(){
          if(xhr.readyState === 4){
            try{
              const ok = xhr.status >= 200 && xhr.status < 300;
              const data = xhr.responseText ? JSON.parse(xhr.responseText) : {};
              if(!ok){
                reject(new Error(data.error || 'Anfrage fehlgeschlagen'));
              }else{
                resolve(data);
              }
            }catch(err){ reject(err); }
          }
        };
        xhr.onerror = ()=> reject(new Error('Netzwerkfehler'));
        xhr.send(form);
      });
    }

  </script>
</body>
</html>
      // Buckets Status Banner für Login
      updateBucketBanner();
