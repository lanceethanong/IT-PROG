(function () {
    'use strict';

    /* helpers */

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

    function setError(input, msg) {
        input.classList.add('ez-invalid');
        input.classList.remove('ez-valid');
        let tip = input.parentElement.querySelector('.ez-tip');
        if (!tip) {
            tip = document.createElement('span');
            tip.className = 'ez-tip ez-tip--err';
            input.parentElement.appendChild(tip);
        }
        tip.className = 'ez-tip ez-tip--err';
        tip.textContent = msg;
    }

    function setOk(input) {
        input.classList.remove('ez-invalid');
        input.classList.add('ez-valid');
        const tip = input.parentElement.querySelector('.ez-tip');
        if (tip) tip.remove();
    }

    function clearState(input) {
        input.classList.remove('ez-invalid', 'ez-valid');
        const tip = input.parentElement.querySelector('.ez-tip');
        if (tip) tip.remove();
    }

    /* password strength */

    function passwordScore(pw) {
        let s = 0;
        if (pw.length >= 8) s++;
        if (pw.length >= 12) s++;
        if (/[a-zA-Z]/.test(pw)) s++;
        if (/[0-9]/.test(pw)) s++;
        return s;
    }

    function strengthLabel(score) {
        if (score <= 1) return { label: 'Too weak', cls: 'ez-str--weak', pct: 25 };
        if (score === 2) return { label: 'Weak', cls: 'ez-str--weak', pct: 50 };
        if (score === 3) return { label: 'Fair', cls: 'ez-str--fair', pct: 75 };
        return { label: 'Strong', cls: 'ez-str--strong', pct: 100 };
    }

    function attachStrengthMeter(pwInput) {
        if (!pwInput) return;
        const wrap = pwInput.parentElement;

        // bar container
        const bar = document.createElement('div');
        bar.className = 'ez-str-bar';
        bar.innerHTML = '<div class="ez-str-fill"></div>';
        wrap.appendChild(bar);

        // label
        const lbl = document.createElement('span');
        lbl.className = 'ez-str-lbl';
        wrap.appendChild(lbl);

        pwInput.addEventListener('input', function () {
            const v = pwInput.value;
            if (!v) {
                bar.style.display = 'none';
                lbl.textContent = '';
                return;
            }
            bar.style.display = 'block';
            const info = strengthLabel(passwordScore(v));
            const fill = bar.querySelector('.ez-str-fill');
            fill.style.width = info.pct + '%';
            fill.className = 'ez-str-fill ' + info.cls;
            lbl.className = 'ez-str-lbl ' + info.cls;
            lbl.textContent = info.label;
        });
    }

    /* show/hide toggle */

    function attachShowHide(pwInput) {
        if (!pwInput) return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ez-eye';
        btn.setAttribute('aria-label', 'Toggle password visibility');
        btn.innerHTML = eyeIcon(false);

        // insert immediately after the input as a sibling
        pwInput.insertAdjacentElement('afterend', btn);

        btn.addEventListener('click', function () {
            const shown = pwInput.type === 'text';
            pwInput.type = shown ? 'password' : 'text';
            btn.innerHTML = eyeIcon(shown);
            pwInput.focus();
        });
    }

    function eyeIcon(hidden) {
        // hidden=true  → show the "open eye" (reveal)
        // hidden=false → show the "closed eye" (hide)
        if (hidden) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    }

    /* character counter */

    function attachCounter(input, max) {
        if (!input) return;
        const ctr = document.createElement('span');
        ctr.className = 'ez-counter';
        ctr.textContent = '0 / ' + max;
        input.parentElement.appendChild(ctr);

        input.addEventListener('input', function () {
            const n = input.value.length;
            ctr.textContent = n + ' / ' + max;
            ctr.classList.toggle('ez-counter--warn', n > max * 0.85);
            ctr.classList.toggle('ez-counter--over', n >= max);
        });
    }

    /* validators */

    const USERNAME_MAX = 30;   // sensible UI cap (DB allows 191)
    const USERNAME_RE = /^[A-Za-z0-9_.-]+$/;
    const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function validateUsername(el, required) {
        if (!el) return true;
        const v = el.value.trim();
        if (required && !v) { setError(el, 'Username is required.'); return false; }
        if (!v) { clearState(el); return true; }
        if (v.length < 3) { setError(el, 'Username must be at least 3 characters.'); return false; }
        if (v.length > USERNAME_MAX) { setError(el, 'Username must be ' + USERNAME_MAX + ' characters or fewer.'); return false; }
        if (!USERNAME_RE.test(v)) { setError(el, 'Only letters, numbers, underscores, hyphens and dots allowed.'); return false; }
        setOk(el); return true;
    }

    function validateEmail(el, required) {
        if (!el) return true;
        const v = el.value.trim();
        if (required && !v) { setError(el, 'Email is required.'); return false; }
        if (!v) { clearState(el); return true; }
        if (!EMAIL_RE.test(v)) { setError(el, 'Please enter a valid email address.'); return false; }
        setOk(el); return true;
    }

    function validatePassword(el, required, minLen) {
        if (!el) return true;
        minLen = minLen || 8;
        const v = el.value;
        if (required && !v) { setError(el, 'Password is required.'); return false; }
        if (!v) { clearState(el); return true; }
        if (v.length < minLen) { setError(el, 'Password must be at least ' + minLen + ' characters.'); return false; }
        if (!/[a-zA-Z]/.test(v)) { setError(el, 'Password must contain at least one letter.'); return false; }
        if (!/[0-9]/.test(v)) { setError(el, 'Password must contain at least one number.'); return false; }
        setOk(el); return true;
    }

    function validateMatch(el, refEl, label) {
        if (!el || !refEl) return true;
        const v = el.value;
        if (!v) { clearState(el); return true; }
        if (v !== refEl.value) { setError(el, (label || 'Passwords') + ' do not match.'); return false; }
        setOk(el); return true;
    }

    /* inject shared CSS */

    function injectCSS() {
        if (document.getElementById('ez-val-css')) return;
        const s = document.createElement('style');
        s.id = 'ez-val-css';
        s.textContent = `
      /* inputs */
      input.ez-invalid, textarea.ez-invalid, select.ez-invalid {
        border-color: #dc2626 !important;
        box-shadow: 0 0 0 2px rgba(220,38,38,.15) !important;
      }
      input.ez-valid, textarea.ez-valid {
        border-color: #16a34a !important;
      }

      /* inline error / helper text */
      .ez-tip {
        display: block;
        font-size: .73rem;
        margin-top: 3px;
        font-weight: 500;
      }
      .ez-tip--err  { color: #dc2626; }
      .ez-tip--ok   { color: #16a34a; }

      /* character counter */
      .ez-counter {
        display: block;
        font-size: .7rem;
        text-align: right;
        color: #6b7280;
        margin-top: 2px;
      }
      .ez-counter--warn { color: #d97706; }
      .ez-counter--over { color: #dc2626; font-weight: 700; }

      /* strength bar */
      .ez-str-bar {
        height: 4px;
        border-radius: 2px;
        background: #e5e7eb;
        margin-top: 6px;
        overflow: hidden;
        display: none;
      }
      .ez-str-fill {
        height: 100%;
        border-radius: 2px;
        transition: width .3s, background .3s;
      }
      .ez-str-fill.ez-str--weak    { background: #dc2626; }
      .ez-str-fill.ez-str--fair    { background: #d97706; }
      .ez-str-fill.ez-str--strong  { background: #16a34a; }
      .ez-str-fill.ez-str--vstrong { background: #15803d; }

      .ez-str-lbl {
        display: block;
        font-size: .7rem;
        margin-top: 3px;
        font-weight: 600;
      }
      .ez-str-lbl.ez-str--weak    { color: #dc2626; }
      .ez-str-lbl.ez-str--fair    { color: #d97706; }
      .ez-str-lbl.ez-str--strong  { color: #16a34a; }
      .ez-str-lbl.ez-str--vstrong { color: #15803d; }

      /* show-password button */
      .ez-eye {
        display: inline-block;
        vertical-align: middle;
        margin-left: -36px;
        margin-bottom: 2px;
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        color: #6b7280;
        line-height: 1;
        position: relative;
        z-index: 2;
        }
      .ez-eye:hover { color: #374151; }

      /* push text away from the eye icon */
      input[type=password].ez-has-eye,
      input[type=text].ez-has-eye { padding-right: 38px !important; }
    `;
        document.head.appendChild(s);
    }

    /* REGISTER FORM  (/register) */
    function initRegisterForm() {
        const form = $('form[action$="/register"]') || $('form#register-form');
        if (!form) return;

        const uEl = form.querySelector('[name=username]');
        const eEl = form.querySelector('[name=email]');
        const pEl = form.querySelector('[name=password]');
        const p2El = form.querySelector('[name=confirmPassword]');

        if (uEl) {
            attachCounter(uEl, USERNAME_MAX);
            uEl.addEventListener('input', () => validateUsername(uEl, true));
            uEl.addEventListener('blur', () => validateUsername(uEl, true));
        }
        if (eEl) {
            eEl.addEventListener('input', () => validateEmail(eEl, true));
            eEl.addEventListener('blur', () => validateEmail(eEl, true));
        }
        if (pEl) {
            pEl.classList.add('ez-has-eye');
            attachShowHide(pEl);
            attachStrengthMeter(pEl);
            pEl.addEventListener('input', () => {
                validatePassword(pEl, true);
                if (p2El && p2El.value) validateMatch(p2El, pEl);
            });
            pEl.addEventListener('blur', () => validatePassword(pEl, true));
        }
        if (p2El) {
            p2El.classList.add('ez-has-eye');
            attachShowHide(p2El);
            p2El.addEventListener('input', () => validateMatch(p2El, pEl));
            p2El.addEventListener('blur', () => validateMatch(p2El, pEl));
        }

        form.addEventListener('submit', function (e) {
            const ok = [
                validateUsername(uEl, true),
                validateEmail(eEl, true),
                validatePassword(pEl, true),
                validateMatch(p2El, pEl),
            ].every(Boolean);
            if (!ok) e.preventDefault();
        });
    }

    /* LOGIN FORM  (/login) */
    function initLoginForm() {
        const form = $('form[action$="/login"]') || $('form#login-form');
        if (!form) return;

        const eEl = form.querySelector('[name=email]');
        const pEl = form.querySelector('[name=password]');

        if (pEl) {
            pEl.classList.add('ez-has-eye');
            attachShowHide(pEl);
        }
        if (eEl) {
            eEl.addEventListener('blur', () => validateEmail(eEl, true));
        }

        form.addEventListener('submit', function (e) {
            const ok = [
                validateEmail(eEl, true),
                pEl && pEl.value ? true : (setError(pEl, 'Password is required.'), false),
            ].every(Boolean);
            if (!ok) e.preventDefault();
        });
    }

    /* CHANGE PASSWORD FORM  (/account/change-password) */
    function initChangePasswordForm() {
        const form = $('form[action$="/account/change-password"]');
        if (!form) return;

        const curEl = form.querySelector('[name=currentPassword]');
        const newEl = form.querySelector('[name=newPassword]');
        const cfmEl = form.querySelector('[name=confirmPassword]');

        [curEl, newEl, cfmEl].forEach(function (el) {
            if (el) { el.classList.add('ez-has-eye'); attachShowHide(el); }
        });

        if (newEl) {
            attachStrengthMeter(newEl);
            newEl.addEventListener('input', () => {
                validatePassword(newEl, true);
                if (cfmEl && cfmEl.value) validateMatch(cfmEl, newEl);
            });
            newEl.addEventListener('blur', () => validatePassword(newEl, true));
        }
        if (cfmEl) {
            cfmEl.addEventListener('input', () => validateMatch(cfmEl, newEl));
            cfmEl.addEventListener('blur', () => validateMatch(cfmEl, newEl));
        }
        if (curEl) {
            curEl.addEventListener('blur', () => validatePassword(curEl, true));
        }

        form.addEventListener('submit', function (e) {
            const ok = [
                validatePassword(curEl, true),
                validatePassword(newEl, true),
                validateMatch(cfmEl, newEl),
            ].every(Boolean);
            if (!ok) e.preventDefault();
        });
    }

    /* ADMIN — add-labtech form  (/admin/add-labtech) */
    function initAdminAddLabtech() {
        // old style admin page
        const form = $('form[action$="/admin/add-labtech"]');
        if (!form) return;

        const uEl = form.querySelector('[name=username]');
        const eEl = form.querySelector('[name=email]');
        const pEl = form.querySelector('[name=password]');

        if (uEl) { attachCounter(uEl, USERNAME_MAX); uEl.addEventListener('input', () => validateUsername(uEl, true)); }
        if (eEl) { eEl.addEventListener('blur', () => validateEmail(eEl, true)); }
        if (pEl) {
            pEl.classList.add('ez-has-eye');
            attachShowHide(pEl);
            attachStrengthMeter(pEl);
            pEl.addEventListener('input', () => validatePassword(pEl, true));
        }

        form.addEventListener('submit', function (e) {
            const ok = [
                validateUsername(uEl, true),
                validateEmail(eEl, true),
                validatePassword(pEl, true),
            ].every(Boolean);
            if (!ok) e.preventDefault();
        });
    }

    /* ADMIN DASHBOARD — modal forms  (admin_dashboard.php) */
    function initAdminDashboardForms() {
        // Create user modal
        const createForm = $('form[action*="tab=users"] input[name="_action"][value="create_user"]');
        if (createForm) {
            const f = createForm.closest('form');
            const uEl = f.querySelector('[name=username]');
            const eEl = f.querySelector('[name=email]');
            const pEl = f.querySelector('[name=password]');

            if (uEl) { attachCounter(uEl, USERNAME_MAX); uEl.addEventListener('input', () => validateUsername(uEl, true)); uEl.addEventListener('blur', () => validateUsername(uEl, true)); }
            if (eEl) { eEl.addEventListener('blur', () => validateEmail(eEl, true)); }
            if (pEl) {
                pEl.classList.add('ez-has-eye');
                attachShowHide(pEl);
                attachStrengthMeter(pEl);
                pEl.addEventListener('input', () => validatePassword(pEl, true));
                pEl.addEventListener('blur', () => validatePassword(pEl, true));
            }

            f.addEventListener('submit', function (e) {
                const ok = [
                    validateUsername(uEl, true),
                    validateEmail(eEl, true),
                    validatePassword(pEl, true),
                ].every(Boolean);
                if (!ok) e.preventDefault();
            });
        }

        // Edit user modal 
        const editForm = $('form[action*="tab=users"] input[name="_action"][value="edit_user"]');
        if (editForm) {
            const f = editForm.closest('form');
            const uEl = f.querySelector('[name=username]');
            const eEl = f.querySelector('[name=email]');
            const pEl = f.querySelector('[name=password]');

            if (uEl) { attachCounter(uEl, USERNAME_MAX); uEl.addEventListener('input', () => validateUsername(uEl, true)); uEl.addEventListener('blur', () => validateUsername(uEl, true)); }
            if (eEl) { eEl.addEventListener('blur', () => validateEmail(eEl, true)); }
            if (pEl) {
                pEl.classList.add('ez-has-eye');
                attachShowHide(pEl);
                attachStrengthMeter(pEl);
                pEl.addEventListener('input', () => {
                    // password is optional on edit, only validate if they typed something
                    if (pEl.value) validatePassword(pEl, false);
                    else clearState(pEl);
                });
                pEl.addEventListener('blur', () => {
                    if (pEl.value) validatePassword(pEl, false);
                    else clearState(pEl);
                });
            }

            f.addEventListener('submit', function (e) {
                const pwOk = !pEl || !pEl.value || validatePassword(pEl, false);
                const ok = [
                    validateUsername(uEl, true),
                    validateEmail(eEl, true),
                    pwOk,
                ].every(Boolean);
                if (!ok) e.preventDefault();
            });
        }
    }

    /* bootstrap */

    document.addEventListener('DOMContentLoaded', function () {
        injectCSS();
        initRegisterForm();
        initLoginForm();
        initChangePasswordForm();
        initAdminAddLabtech();
        initAdminDashboardForms();
    });

})();