// ============================================================
// AgriConnect – Main JavaScript
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // ── SIDEBAR TOGGLE ──
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileOverlay = document.getElementById('mobileOverlay');

    function isMobile() { return window.innerWidth <= 768; }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (isMobile()) {
                sidebar.classList.toggle('mobile-open');
                mobileOverlay && mobileOverlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent && mainContent.classList.toggle('expanded');
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            }
        });
    }

    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', function () {
            sidebar.classList.remove('mobile-open');
            mobileOverlay.classList.remove('show');
        });
    }

    // Restore sidebar state on desktop
    const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (collapsed && !isMobile() && sidebar) {
        sidebar.classList.add('collapsed');
        mainContent && mainContent.classList.add('expanded');
    }

    // ── DROPDOWN MENUS ──
    document.querySelectorAll('[data-toggle="dropdown"]').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            const menu = document.getElementById(trigger.dataset.target);
            if (!menu) return;
            const isOpen = menu.classList.contains('show');
            // Close all dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
            if (!isOpen) menu.classList.add('show');
        });
    });

    document.addEventListener('click', function () {
        document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
    });

    // ── FLASH MESSAGE AUTO-DISMISS ──
    const flash = document.querySelector('.alert[data-dismiss]');
    if (flash) {
        setTimeout(() => { flash.style.opacity = '0'; flash.style.transition = 'opacity .5s'; setTimeout(() => flash.remove(), 500); }, 4000);
    }

    // ── MODAL ──
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = document.getElementById(btn.dataset.modalOpen);
            if (modal) modal.classList.add('show');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', function () {
            const modal = btn.closest('.modal-overlay');
            if (modal) modal.classList.remove('show');
        });
    });
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('show');
        });
    });

    // ── OTP INPUT BEHAVIOUR ──
    const otpInputs = document.querySelectorAll('.otp-input');
    if (otpInputs.length) {
        otpInputs.forEach((input, i) => {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 1);
                if (this.value) {
                    this.classList.add('filled');
                    if (i < otpInputs.length - 1) otpInputs[i + 1].focus();
                } else {
                    this.classList.remove('filled');
                }
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !this.value && i > 0) otpInputs[i - 1].focus();
            });
            input.addEventListener('paste', function (e) {
                e.preventDefault();
                const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                [...text].slice(0, otpInputs.length - i).forEach((ch, j) => {
                    otpInputs[i + j].value = ch;
                    otpInputs[i + j].classList.add('filled');
                });
                const next = Math.min(i + text.length, otpInputs.length - 1);
                otpInputs[next].focus();
            });
        });
        otpInputs[0] && otpInputs[0].focus();
    }

    // ── PASSWORD STRENGTH ──
    const pwInput = document.getElementById('password');
    const pwFill = document.querySelector('.pw-strength-fill');
    const pwText = document.querySelector('.pw-strength-text');
    if (pwInput && pwFill) {
        pwInput.addEventListener('input', function () {
            const v = this.value;
            let score = 0;
            if (v.length >= 8) score++;
            if (/[A-Z]/.test(v)) score++;
            if (/[0-9]/.test(v)) score++;
            if (/[^A-Za-z0-9]/.test(v)) score++;
            pwFill.className = 'pw-strength-fill';
            if (score <= 1) { pwFill.classList.add('weak'); pwText && (pwText.textContent = 'Weak password'); }
            else if (score <= 2) { pwFill.classList.add('medium'); pwText && (pwText.textContent = 'Medium strength'); }
            else { pwFill.classList.add('strong'); pwText && (pwText.textContent = 'Strong password'); }
        });
    }

    // ── TOGGLE PASSWORD VISIBILITY ──
    document.querySelectorAll('.toggle-pw').forEach(btn => {
        btn.addEventListener('click', function () {
            const input = this.closest('.input-group').querySelector('input');
            if (!input) return;
            const isText = input.type === 'text';
            input.type = isText ? 'password' : 'text';
            this.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    });

    // ── SEARCH LIVE FILTER (tables) ──
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('tbody tr[data-searchable]').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    // ── TOPBAR GLOBAL SEARCH ──
    const globalSearch = document.getElementById('globalSearch');
    if (globalSearch) {
        globalSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && this.value.trim()) {
                const base = window.BASE_URL || '/AgriConnect';
                window.location.href = base + '/marketplace/index.php?search=' + encodeURIComponent(this.value.trim());
            }
        });
    }

    // ── CONFIRM DELETE ──
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm || 'Are you sure?')) e.preventDefault();
        });
    });

    // ── SELECT ALL CHECKBOX ──
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(cb => { cb.checked = this.checked; });
        });
    }

    // ── TOOLTIP (simple) ──
    // Already handled via CSS attr(data-tooltip)

    // ── RESPONSIVE TABLE (add data-label for mobile) ──
    document.querySelectorAll('.table-responsive-labels th').forEach((th, i) => {
        const label = th.textContent.trim();
        document.querySelectorAll(`.table-responsive-labels tbody tr td:nth-child(${i + 1})`).forEach(td => {
            td.setAttribute('data-label', label);
        });
    });

    // ── IMAGE PREVIEW (file inputs) ──
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', function () {
            const previewId = this.dataset.preview;
            const preview = document.getElementById(previewId);
            if (!preview || !this.files[0]) return;
            const reader = new FileReader();
            reader.onload = e => { preview.src = e.target.result; };
            reader.readAsDataURL(this.files[0]);
        });
    });

    console.log('%cAgriConnect loaded ✅', 'color:#16a34a;font-weight:700;font-size:14px;');
});
