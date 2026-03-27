<?php
// Detectar mensaje desde cualquier fuente disponible en el scope
$_toast_msg  = '';
$_toast_type = 'info';

if (!empty($mensaje)) {
    $_toast_msg  = $mensaje;
    $_toast_type = !empty($tipo_mensaje) ? $tipo_mensaje : 'info';
}
if (!empty($exito)) {
    $_toast_msg  = $exito;
    $_toast_type = 'success';
}
if (!empty($error)) {
    $_toast_msg  = $error;
    $_toast_type = 'error';
}
?>

<!-- ===== TOAST CONTAINER ===== -->
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- ===== CUSTOM CONFIRM MODAL ===== -->
<div id="confirm-overlay" role="dialog" aria-modal="true" aria-labelledby="confirm-title" style="display:none;">
    <div id="confirm-box">
        <div id="confirm-icon"></div>
        <h3 id="confirm-title">¿Estás seguro?</h3>
        <p  id="confirm-text"></p>
        <div id="confirm-buttons">
            <button id="confirm-cancel" type="button" onclick="cerrarConfirm()">Cancelar</button>
            <button id="confirm-ok"     type="button">Confirmar</button>
        </div>
    </div>
</div>

<style>
/* ===========================
   TOAST NOTIFICATIONS
   =========================== */
#toast-container {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 360px;
}

.toast {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: white;
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.13), 0 2px 8px rgba(0,0,0,0.08);
    animation: toastIn 0.35s cubic-bezier(.22,1,.36,1) both;
    position: relative;
    overflow: hidden;
    border-left: 4px solid transparent;
    min-width: 280px;
}
.toast.removing {
    animation: toastOut 0.3s ease forwards;
}
@keyframes toastIn {
    from { opacity:0; transform: translateX(60px) scale(0.95); }
    to   { opacity:1; transform: translateX(0)    scale(1); }
}
@keyframes toastOut {
    to   { opacity:0; transform: translateX(60px) scale(0.95); max-height:0; padding:0; margin:0; }
}

.toast-icon { font-size: 22px; flex-shrink: 0; margin-top: 1px; }
.toast-body { flex: 1; }
.toast-title { font-weight: 700; font-size: 14px; margin-bottom: 2px; }
.toast-msg   { font-size: 13px; color: #555; line-height: 1.4; }

.toast.success { border-color: #10b981; }
.toast.success .toast-title { color: #065f46; }
.toast.error   { border-color: #ef4444; }
.toast.error   .toast-title { color: #991b1b; }
.toast.warning { border-color: #f59e0b; }
.toast.warning .toast-title { color: #92400e; }
.toast.info    { border-color: #667eea; }
.toast.info    .toast-title { color: #3730a3; }

.toast-progress {
    position: absolute;
    bottom: 0; left: 0;
    height: 3px;
    border-radius: 0 0 12px 12px;
    animation: toastProgress linear forwards;
}
.toast.success .toast-progress { background: #10b981; }
.toast.error   .toast-progress { background: #ef4444; }
.toast.warning .toast-progress { background: #f59e0b; }
.toast.info    .toast-progress  { background: #667eea; }

@keyframes toastProgress {
    from { width: 100%; }
    to   { width: 0%; }
}

.toast-close {
    background: none;
    border: none;
    color: #aaa;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 0 0 0 8px;
    flex-shrink: 0;
    transition: color 0.2s;
}
.toast-close:hover { color: #555; }


/* ===========================
   CUSTOM CONFIRM MODAL
   =========================== */
#confirm-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.50);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.2s ease;
}
#confirm-overlay[style*="display: none"],
#confirm-overlay[style*="display:none"] { display: none !important; }

@keyframes fadeIn { from{opacity:0} to{opacity:1} }

#confirm-box {
    background: white;
    border-radius: 20px;
    padding: 36px 32px 28px;
    max-width: 400px;
    width: 100%;
    text-align: center;
    box-shadow: 0 24px 64px rgba(0,0,0,0.18);
    animation: scaleIn 0.25s cubic-bezier(.22,1,.36,1) both;
}
@keyframes scaleIn {
    from { transform: scale(0.88); opacity: 0; }
    to   { transform: scale(1);    opacity: 1; }
}

#confirm-icon {
    font-size: 52px;
    margin-bottom: 14px;
    line-height: 1;
}

#confirm-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 10px;
}

#confirm-text {
    font-size: 14px;
    color: #666;
    line-height: 1.6;
    margin-bottom: 28px;
}

#confirm-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

#confirm-cancel {
    padding: 11px 28px;
    border: 2px solid #e5e7eb;
    background: white;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #555;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
}
#confirm-cancel:hover { background: #f3f4f6; border-color: #d1d5db; }

#confirm-ok {
    padding: 11px 28px;
    border: none;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    box-shadow: 0 4px 14px rgba(239,68,68,0.35);
}
#confirm-ok:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(239,68,68,0.45); }
</style>

<script>
/* ===========================
   TOAST API
   =========================== */
const _toastIcons = {
    success: '✅',
    error:   '❌',
    warning: '⚠️',
    info:    'ℹ️'
};
const _toastTitles = {
    success: 'Éxito',
    error:   'Error',
    warning: 'Advertencia',
    info:    'Información'
};

function showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    toast.innerHTML = `
        <div class="toast-icon">${_toastIcons[type] || 'ℹ️'}</div>
        <div class="toast-body">
            <div class="toast-title">${_toastTitles[type] || 'Aviso'}</div>
            <div class="toast-msg">${message}</div>
        </div>
        <button class="toast-close" onclick="removeToast(this.closest('.toast'))" aria-label="Cerrar">×</button>
        <div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
    `;

    container.appendChild(toast);
    const timer = setTimeout(() => removeToast(toast), duration);
    toast.dataset.timer = timer;
}

function removeToast(toast) {
    if (!toast || toast.classList.contains('removing')) return;
    clearTimeout(parseInt(toast.dataset.timer));
    toast.classList.add('removing');
    toast.addEventListener('animationend', () => toast.remove(), { once: true });
}

/* ===========================
   CONFIRM MODAL API
   =========================== */
let _confirmCallback = null;

function confirmacion(message, actionLabel = 'Confirmar', callback) {
    _confirmCallback = callback;
    const overlay = document.getElementById('confirm-overlay');
    document.getElementById('confirm-text').textContent  = message;
    document.getElementById('confirm-ok').textContent    = actionLabel;
    document.getElementById('confirm-icon').textContent  = '🗑️';
    overlay.classList.remove('hidden');
    overlay.style.display = 'flex';
}

function cerrarConfirm() {
    const overlay = document.getElementById('confirm-overlay');
    overlay.style.display = 'none';
    _confirmCallback = null;
}

document.getElementById('confirm-ok').addEventListener('click', function() {
    cerrarConfirm();
    if (typeof _confirmCallback === 'function') _confirmCallback();
});

// Cerrar al click fuera
document.getElementById('confirm-overlay').addEventListener('click', function(e) {
    if (e.target === this) cerrarConfirm();
});

// Cerrar con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarConfirm();
});

<?php if (!empty($_toast_msg)): ?>
// Mostrar toast desde PHP
showToast(<?php echo json_encode(htmlspecialchars_decode($_toast_msg)); ?>, '<?php echo htmlspecialchars($_toast_type ?: 'info'); ?>');
<?php endif; ?>
</script>
