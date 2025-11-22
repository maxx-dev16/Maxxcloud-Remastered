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
      --bg:#1a1a1a; --panel:#242424; --muted:#6b7280; --accent:#0ea5a4; --card:#111; --text:#e6e6e6; --border:#333; --radius:8px; --gap:12px; --max-width:1200px;
      --sidebar-w:260px;
    }

    *{box-sizing:border-box}
    html,body{height:100%;margin:0;font-family:Inter,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--text)}

    /* container */
    .app{min-height:100vh;display:flex;flex-direction:column}
    .topbar{height:64px;display:flex;align-items:center;gap:16px;padding:0 16px;background:linear-gradient(180deg,rgba(255,255,255,0.02),transparent);border-bottom:1px solid var(--border)}
    .container{display:flex;flex:1;max-width:calc(var(--max-width));margin:0 auto;width:100%}

    /* sidebar */
    .sidebar{width:var(--sidebar-w);background:var(--panel);padding:16px;border-right:1px solid var(--border);display:flex;flex-direction:column;gap:12px}
    .logo{font-weight:700;font-size:18px}
    .folders{flex:1;overflow:auto}
    .folder{padding:8px;border-radius:6px;cursor:pointer}
    .folder.active{background:#111}

    /* main */
    main{flex:1;display:flex;flex-direction:column}
    .actionbar{padding:12px;border-bottom:1px solid var(--border);background:var(--panel);display:flex;gap:12px;align-items:center}
    .search{flex:1;position:relative}
    .search input{width:100%;padding:10px 12px;padding-left:36px;border-radius:6px;background:transparent;border:1px solid var(--border);color:var(--text)}
    .search .icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);opacity:0.6}

    .content{padding:16px;overflow:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
    .file{background:var(--card);padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.02);display:flex;flex-direction:column;gap:8px}
    .file .name{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .file .meta{font-size:12px;color:var(--muted)}

    .action-row{display:flex;gap:8px}
    .btn{background:transparent;border:1px solid var(--border);padding:8px 10px;border-radius:6px;color:var(--text);cursor:pointer}
    .btn.primary{border-color:var(--accent);color:var(--accent)}

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
      background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      background-image:url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 900"><defs><linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:%23667eea;stop-opacity:1" /><stop offset="100%" style="stop-color:%23764ba2;stop-opacity:1" /></linearGradient></defs><rect width="1440" height="900" fill="url(%23grad)"/><path d="M0,600 Q360,500 720,550 T1440,500 L1440,900 L0,900 Z" fill="rgba(255,255,255,0.1)"/><path d="M0,700 Q360,600 720,650 T1440,600 L1440,900 L0,900 Z" fill="rgba(255,255,255,0.05)"/></svg>');
      background-size:cover;
      background-position:center;
      filter:blur(8px);
      transform:scale(1.1);
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

    @media (max-width:800px){
      .sidebar{display:none}
      .container{padding:0}
    }
  </style>
</head>
<body>
  <div class="app" id="app">

    <!-- Connection status -->
    <div id="connection" class="status">Verbunden</div>

    <!-- Topbar -->
    <header class="topbar">
      <div class="logo">MaxxCloud</div>
      <div class="space"></div>
      <div id="user-info" style="display:flex;gap:8px;align-items:center">
        <div class="muted" id="user-email"></div>
        <button class="btn" id="logoutBtn">Logout</button>
      </div>
    </header>

    <!-- Main layout -->
    <div class="container">
      <!-- Sidebar -->
      <aside class="sidebar" id="sidebar">
        <div class="muted">Speicher</div>
        <div id="storageText">0 / 0 MB</div>
        <hr style="border:none;border-top:1px solid var(--border);margin:8px 0">
        <div class="muted">Ordner</div>
        <div class="folders" id="folders"></div>
        <div style="margin-top:8px">
          <input type="text" id="newFolderName" placeholder="Neuer Ordner" style="width:100%;padding:8px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text)">
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
    </div>

    <!-- Login screen (hidden when logged in) -->
    <div class="login-screen" id="loginScreen" style="display:none">
      <div class="login-background"></div>
      <div class="login-container">
        <div class="login-card" id="loginCard">
          <h1 class="login-title">Login</h1>
          
          <div id="errorMessage" class="error-message"></div>

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
          throw new Error('Clipboard API nicht verfügbar');
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
          throw createApiError(data.error || 'Unbekannter Fehler', data);
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
        renderUser();
        renderFolders();
        renderGrid();
      }catch(err){
        console.error(err);
        logError('Status', err?.message || 'Status fehlgeschlagen', err?.stack || err);
      }
    }

    function renderUser(){
      const container = document.querySelector('.container');
      const sidebar = el('sidebar');
      if(state.user){
        el('user-email').textContent = state.user.email;
        el('loginScreen').style.display='none';
        container.style.display='flex';
        sidebar.style.display='flex';
        el('storageText').textContent = `${state.storage.megabytes ?? 0} / ${state.user.storage_limit} MB`;
      }else{
        el('user-email').textContent = '';
        el('loginScreen').style.display='flex';
        container.style.display='none';
        sidebar.style.display='none';
        el('storageText').textContent = '0 / 0 MB';
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

        const del = document.createElement('button');
        del.className='btn';
        del.textContent='Löschen';
        del.onclick=()=>deleteFile(f.id);

        actions.appendChild(dl);
        actions.appendChild(del);

        card.appendChild(title);
        card.appendChild(meta);
        card.appendChild(actions);
        grid.appendChild(card);
      });
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
        showError(err.message || 'Login fehlgeschlagen');
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
      const form = new FormData();
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

      try{
        await api('upload_file', form);
        showError(`Datei "${file.name}" erfolgreich hochgeladen`);
        await refreshState();
      }catch(err){
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

    async function deleteFile(fileId){
      if(!confirm('Datei wirklich löschen?')){
        return;
      }
      try{
        await api('delete_file', {file_id: fileId});
        await refreshState();
      }catch(err){
        alert(err.message || 'Löschen fehlgeschlagen');
        logError('Löschen', err?.message || 'Löschen fehlgeschlagen', err?.stack || err);
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
      document.addEventListener('keydown', event=>{
        if(event.key === 'Escape' && state.debugOpen){
          setDebugOpen(false);
        }
      });
      renderDebugLog();
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
      await refreshState();
    })();

  </script>
</body>
</html>
