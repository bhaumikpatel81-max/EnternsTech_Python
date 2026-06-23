/* EnternsTech – theme interactivity (no Design Canvas / no React dependency) */
(function () {
  'use strict';

  var D          = window.ET_DATA    || {};
  var ENP        = window.ENP_DATA   || {};
  var PLACEMENTS = D.placements      || [];
  var TRACKS     = D.tracks          || [];
  var REDUCED    = window.matchMedia && window.matchMedia('(prefers-reduced-motion:reduce)').matches;
  var COARSE     = window.matchMedia && window.matchMedia('(pointer:coarse)').matches;

  /* ── tiny helpers ──────────────────────────────────────────────────────── */
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function on(el, ev, fn, opts) { el && el.addEventListener(ev, fn, opts || false); }

  /* ── nav solid-on-scroll ─────────────────────────────────────────────── */
  function initNav() {
    var nav = $('#et-nav');
    if (!nav) return;
    on(window, 'scroll', function () {
      nav.classList.toggle('et-nav--solid', window.scrollY > 24);
    }, { passive: true });
  }

  /* ── smooth scroll for [data-scroll-to] links ────────────────────────── */
  function initScrollLinks() {
    $$('[data-scroll-to]').forEach(function (el) {
      on(el, 'click', function (e) {
        e.preventDefault();
        var id     = el.getAttribute('data-scroll-to');
        var target = document.getElementById(id);
        if (!target) return;
        window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 68, behavior: 'smooth' });
      });
    });
  }

  /* ── pointer glow (desktop only) ─────────────────────────────────────── */
  function initPointer() {
    var el = $('#et-pointer');
    if (!el || COARSE) return;
    var tx = innerWidth / 2, ty = innerHeight / 2, cx = tx, cy = ty;
    on(window, 'mousemove', function (e) { tx = e.clientX; ty = e.clientY; }, { passive: true });
    (function loop() {
      cx += (tx - cx) * 0.12; cy += (ty - cy) * 0.12;
      el.style.transform = 'translate(' + cx + 'px,' + cy + 'px) translate(-50%,-50%)';
      requestAnimationFrame(loop);
    })();
  }

  /* ── custom dot cursor (desktop only) ────────────────────────────────── */
  function initCursor() {
    var dot = $('#et-cursor');
    if (!dot) return;
    if (COARSE) { dot.style.display = 'none'; return; }
    var x = innerWidth / 2, y = innerHeight / 2, cx = x, cy = y, sc = 1;
    on(window, 'mousemove', function (e) { x = e.clientX; y = e.clientY; }, { passive: true });
    (function loop() {
      cx += (x - cx) * 0.32; cy += (y - cy) * 0.32;
      dot.style.transform = 'translate(' + cx + 'px,' + cy + 'px) translate(-50%,-50%) scale(' + sc + ')';
      requestAnimationFrame(loop);
    })();
    $$('a,button,input,select,textarea,[data-magnetic]').forEach(function (el) {
      on(el, 'mouseenter', function () { sc = 2.6; dot.style.background = 'rgba(34,211,238,.2)'; });
      on(el, 'mouseleave', function () { sc = 1;   dot.style.background = '#22D3EE'; });
    });
  }

  /* ── magnetic buttons ─────────────────────────────────────────────────── */
  function initMagnetic() {
    $$('[data-magnetic]').forEach(function (el) {
      el.style.transition = 'transform .25s cubic-bezier(.2,1,.3,1)';
      on(el, 'mousemove', function (e) {
        var r = el.getBoundingClientRect();
        var px = (e.clientX - (r.left + r.width  / 2)) * 0.32;
        var py = (e.clientY - (r.top  + r.height / 2)) * 0.5;
        el.style.transform = 'translate(' + px + 'px,' + py + 'px)';
      });
      on(el, 'mouseleave', function () { el.style.transform = ''; });
    });
  }

  /* ── hero scene 3-D tilt on mouse ────────────────────────────────────── */
  function initHeroTilt() {
    var scene = $('#et-hero-scene');
    if (!scene) return;
    var layers = $$('[data-depth]', scene);
    on(scene, 'mousemove', function (e) {
      var r  = scene.getBoundingClientRect();
      var px = (e.clientX - (r.left + r.width  / 2)) / (r.width  / 2);
      var py = (e.clientY - (r.top  + r.height / 2)) / (r.height / 2);
      scene.style.transform = 'perspective(1300px) rotateY(' + (px * 4) + 'deg) rotateX(' + (-py * 4) + 'deg)';
      layers.forEach(function (l) {
        var d = parseFloat(l.getAttribute('data-depth')) || 0;
        l.style.transform = 'translate3d(' + (px * d * 24) + 'px,' + (py * d * 24) + 'px,0)';
      });
    });
    on(scene, 'mouseleave', function () {
      scene.style.transform = '';
      layers.forEach(function (l) { l.style.transform = ''; });
    });
  }

  /* ── hero particle canvas ────────────────────────────────────────────── */
  function initHeroCanvas() {
    var cv = $('#et-hero-canvas');
    if (!cv) return;
    var ctx = cv.getContext('2d');
    var dpr = Math.min(2, window.devicePixelRatio || 1);
    var W, H, nodes;
    function resize() {
      var r = cv.parentElement.getBoundingClientRect();
      W = cv.width  = r.width  * dpr;
      H = cv.height = r.height * dpr;
      cv.style.width  = r.width  + 'px';
      cv.style.height = r.height + 'px';
    }
    resize();
    var N = 48;
    nodes = Array.from({ length: N }, function () {
      return {
        x: Math.random() * W, y: Math.random() * H,
        vx: (Math.random() - .5) * .28 * dpr,
        vy: (Math.random() - .5) * .28 * dpr,
        r: (Math.random() * 1.6 + .8) * dpr
      };
    });
    function draw() {
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < N; i++) {
        var a = nodes[i];
        a.x += a.vx; a.y += a.vy;
        if (a.x < 0 || a.x > W) a.vx *= -1;
        if (a.y < 0 || a.y > H) a.vy *= -1;
        for (var j = i + 1; j < N; j++) {
          var b = nodes[j], dx = a.x - b.x, dy = a.y - b.y, dd = Math.hypot(dx, dy), lim = 140 * dpr;
          if (dd < lim) {
            ctx.strokeStyle = 'rgba(34,211,238,' + (.16 * (1 - dd / lim)) + ')';
            ctx.lineWidth = dpr * .7;
            ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
          }
        }
      }
      nodes.forEach(function (n) {
        ctx.fillStyle = 'rgba(91,233,255,.6)';
        ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, 7); ctx.fill();
      });
      requestAnimationFrame(draw);
    }
    draw();
    on(window, 'resize', resize, { passive: true });
  }

  /* ── placement badge cycling ─────────────────────────────────────────── */
  function initPlacementCycle() {
    var el = $('#et-placed-role');
    if (!el || PLACEMENTS.length < 2) return;
    var idx = 0;
    setInterval(function () {
      el.style.opacity = '0';
      setTimeout(function () {
        idx = (idx + 1) % PLACEMENTS.length;
        var p = PLACEMENTS[idx];
        el.textContent = p.role + ' · ' + p.weeks + ' weeks';
        el.style.opacity = '1';
      }, 420);
    }, 3200);
  }

  /* ── scroll reveal ───────────────────────────────────────────────────── */
  function initReveal() {
    var els = $$('[data-reveal]');
    if (!('IntersectionObserver' in window)) {
      els.forEach(function (e) { e.style.opacity = '1'; e.style.transform = 'none'; e.style.filter = 'none'; });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        var d  = parseInt(el.getAttribute('data-delay') || '0', 10);
        setTimeout(function () {
          el.style.opacity    = '1';
          el.style.transform  = 'none';
          el.style.filter     = 'none';
        }, d);
        io.unobserve(el);
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -6% 0px' });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ── animated counters ───────────────────────────────────────────────── */
  function initCounters() {
    var els = $$('[data-count]');
    function run(el) {
      var target = parseFloat(el.getAttribute('data-target')) || 0;
      var suffix = el.getAttribute('data-suffix') || '';
      var dur = 1500, t0 = performance.now();
      (function step(t) {
        var p = Math.min(1, (t - t0) / dur);
        var e = 1 - Math.pow(1 - p, 3);
        el.textContent = Math.round(target * e).toLocaleString('en-US') + suffix;
        if (p < 1) requestAnimationFrame(step);
      })(t0);
    }
    if (!('IntersectionObserver' in window)) { els.forEach(run); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        run(entry.target);
        io.unobserve(entry.target);
      });
    }, { threshold: 0.5 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ── technology tracks tab switching ─────────────────────────────────── */
  function initTracks() {
    var tabs   = $$('[data-track-tab]');
    var panels = $$('[data-track-panel]');
    if (!tabs.length) return;
    function activate(idx) {
      tabs.forEach(function (t, i) {
        var on = i === idx;
        t.style.borderColor  = on ? 'rgba(34,211,238,.45)' : 'rgba(255,255,255,.08)';
        t.style.background   = on ? 'rgba(34,211,238,.09)' : 'rgba(255,255,255,.025)';
        t.style.color        = on ? '#22D3EE' : '#9FB1CE';
      });
      panels.forEach(function (p, i) { p.style.display = i === idx ? '' : 'none'; });
    }
    tabs.forEach(function (t, i) { on(t, 'click', function () { activate(i); }); });
    activate(0);
  }

  /* ── FAQ accordion ───────────────────────────────────────────────────── */
  function initFaq() {
    $$('[data-faq-btn]').forEach(function (btn) {
      on(btn, 'click', function () {
        var body = btn.parentElement.querySelector('[data-faq-body]');
        var sign = btn.querySelector('[data-faq-sign]');
        var isOpen = body && body.style.display !== 'none';
        $$('[data-faq-body]').forEach(function (b) { b.style.display = 'none'; });
        $$('[data-faq-sign]').forEach(function (s) { s.textContent = '+'; });
        if (!isOpen && body) {
          body.style.display = '';
          if (sign) sign.textContent = '–';
        }
      });
    });
  }

  /* ── pricing audience toggle ─────────────────────────────────────────── */
  function initPricing() {
    var intlBtn   = $('#et-pick-intl');
    var domBtn    = $('#et-pick-dom');
    var noAud     = $('#et-no-audience');
    var plansGrid = $('#et-plans-grid');
    var combosEl  = $('#et-combos-grid');

    var BTN_ON  = 'flex:1;min-width:220px;max-width:320px;cursor:pointer;text-align:left;padding:22px 26px;border-radius:18px;background:linear-gradient(140deg,rgba(34,211,238,.14),rgba(34,211,238,.03));border:1.5px solid rgba(34,211,238,.5);box-shadow:0 12px 36px rgba(34,211,238,.16);transition:all .3s;';
    var BTN_OFF = 'flex:1;min-width:220px;max-width:320px;cursor:pointer;text-align:left;padding:22px 26px;border-radius:18px;background:rgba(255,255,255,.025);border:1.5px solid rgba(255,255,255,.1);transition:all .3s;';

    function show(aud) {
      if (intlBtn) intlBtn.style.cssText = aud === 'intl' ? BTN_ON : BTN_OFF;
      if (domBtn)  domBtn.style.cssText  = aud === 'dom'  ? BTN_ON : BTN_OFF;
      if (noAud)     noAud.style.display     = 'none';
      if (plansGrid) plansGrid.style.display = '';
      if (combosEl)  combosEl.style.display  = '';

      $$('[data-price-intl]').forEach(function (el) { el.style.display = aud === 'intl' ? '' : 'none'; });
      $$('[data-price-dom]').forEach(function  (el) { el.style.display = aud === 'dom'  ? '' : 'none'; });

      $$('[data-plan-btn]').forEach(function (btn) {
        var price = aud === 'intl' ? btn.getAttribute('data-price-intl') : btn.getAttribute('data-price-dom');
        btn.setAttribute('data-price', price || '');
      });
      $$('[data-combo-btn]').forEach(function (btn) {
        var price = aud === 'intl' ? btn.getAttribute('data-price-intl') : btn.getAttribute('data-price-dom');
        btn.setAttribute('data-price', price || '');
      });
    }
    if (intlBtn) on(intlBtn, 'click', function () { show('intl'); });
    if (domBtn)  on(domBtn,  'click', function () { show('dom'); });
  }

  /* ── enrol modal (Razorpay or contact fallback) ─────────────────────── */
  function initEnrolModal() {
    var modal      = $('#et-enrol-modal');
    var closeBtn   = $('#et-enrol-close');
    var planName   = $('#et-enrol-plan');
    var planPrice  = $('#et-enrol-price');
    var enrolForm  = $('#et-enrol-form');
    var fallback   = $('#et-enrol-fallback');
    var successDiv = $('#et-enrol-success');
    var emailInput = $('#et-enrol-email');
    var payBtn     = $('#et-rzp-pay-btn');
    var payErr     = $('#et-enrol-pay-err');
    var portalLink = $('#et-enrol-portal-link');

    if (!modal) return;

    var currentPlanId = '';

    function resetModal() {
      if (payErr)     { payErr.textContent = ''; payErr.style.display = 'none'; }
      if (emailInput)   emailInput.value = '';
      if (enrolForm)    enrolForm.style.display  = ENP.rzp_configured ? '' : 'none';
      if (fallback)     fallback.style.display   = ENP.rzp_configured ? 'none' : '';
      if (successDiv)   successDiv.style.display = 'none';
      if (payBtn)     { payBtn.disabled = false; payBtn.textContent = 'Pay with Razorpay →'; }
    }

    function close() {
      modal.style.display = 'none';
      document.body.style.overflow = '';
      resetModal();
    }

    function openModal(name, price, planId) {
      currentPlanId = planId || '';
      if (planName)  planName.textContent  = name;
      if (planPrice) planPrice.textContent = price;
      resetModal();
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }

    if (closeBtn) on(closeBtn, 'click', close);
    on(modal, 'click', function (e) { if (e.target === modal) close(); });
    on(document, 'keydown', function (e) { if (e.key === 'Escape') close(); });

    // ── Razorpay payment flow ─────────────────────────────────────────────
    if (payBtn) on(payBtn, 'click', function () {
      var email = emailInput ? emailInput.value.trim() : '';
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        if (payErr) { payErr.textContent = 'Please enter a valid email address.'; payErr.style.display = ''; }
        return;
      }
      if (payErr) payErr.style.display = 'none';
      payBtn.disabled    = true;
      payBtn.textContent = 'Creating order…';

      var body = new FormData();
      body.append('action',   'enp_create_razorpay_order');
      body.append('nonce',    ENP.nonce || '');
      body.append('plan_id',  currentPlanId);
      body.append('email',    email);

      fetch(ENP.ajax_url || '/wp-admin/admin-ajax.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            payBtn.disabled = false;
            payBtn.textContent = 'Pay with Razorpay →';
            if (payErr) { payErr.textContent = data.data || 'Payment error. Please try again.'; payErr.style.display = ''; }
            return;
          }
          var d = data.data;
          var rzp = new Razorpay({
            key:         d.key_id,
            amount:      d.amount,
            currency:    d.currency,
            order_id:    d.order_id,
            name:        'Enterns Tech',
            description: planName ? planName.textContent : 'Enrolment',
            prefill:     { email: email },
            theme:       { color: '#22D3EE' },
            handler: function (resp) {
              // Verify server-side before trusting the result.
              var vBody = new FormData();
              vBody.append('action',              'enp_verify_razorpay_payment');
              vBody.append('nonce',               ENP.nonce || '');
              vBody.append('razorpay_order_id',   resp.razorpay_order_id);
              vBody.append('razorpay_payment_id', resp.razorpay_payment_id);
              vBody.append('razorpay_signature',  resp.razorpay_signature);
              vBody.append('payment_id',          d.payment_id);
              vBody.append('email',               email);
              fetch(ENP.ajax_url || '/wp-admin/admin-ajax.php', { method: 'POST', body: vBody })
                .then(function (r) { return r.json(); })
                .then(function (vdata) {
                  if (vdata.success) {
                    if (enrolForm)  enrolForm.style.display  = 'none';
                    if (successDiv) successDiv.style.display = '';
                    if (portalLink && vdata.data && vdata.data.student_url) {
                      portalLink.href = vdata.data.student_url;
                    }
                  } else {
                    payBtn.disabled = false;
                    payBtn.textContent = 'Pay with Razorpay →';
                    if (payErr) {
                      payErr.textContent = vdata.data || 'Verification failed. Please contact support.';
                      payErr.style.display = '';
                    }
                  }
                })
                .catch(function () {
                  if (payErr) {
                    payErr.textContent = 'Network error during verification. Please contact support.';
                    payErr.style.display = '';
                  }
                });
            },
            modal: {
              ondismiss: function () {
                payBtn.disabled = false;
                payBtn.textContent = 'Pay with Razorpay →';
                if (payErr) { payErr.textContent = 'Payment cancelled.'; payErr.style.display = ''; }
              }
            }
          });
          rzp.open();
        })
        .catch(function () {
          payBtn.disabled = false;
          payBtn.textContent = 'Pay with Razorpay →';
          if (payErr) { payErr.textContent = 'Network error. Please try again.'; payErr.style.display = ''; }
        });
    });

    $$('[data-plan-btn],[data-combo-btn]').forEach(function (btn) {
      on(btn, 'click', function () {
        var name   = btn.getAttribute('data-plan-name') || 'Selected Plan';
        var price  = btn.getAttribute('data-price') || btn.getAttribute('data-price-dom') || 'Contact us';
        var planId = btn.getAttribute('data-plan-id') || '';
        openModal(name, price, planId);
      });
    });
  }

  /* ── contact form (FormSubmit AJAX) ──────────────────────────────────── */
  function initContactForm() {
    var form    = $('#et-contact-form');
    var success = $('#et-contact-success');
    var errEl   = $('#et-contact-err');
    if (!form) return;
    on(form, 'submit', function (e) {
      e.preventDefault();
      var d = new FormData(form);
      if (!d.get('name') || !d.get('email')) {
        if (errEl) { errEl.textContent = 'Please enter your name and email.'; errEl.style.display = ''; }
        return;
      }
      if (errEl) errEl.style.display = 'none';
      fetch('https://formsubmit.co/ajax/info@enternstech.com', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          _subject: 'New enquiry — ' + d.get('name'),
          name: d.get('name'), email: d.get('email'),
          phone: d.get('phone') || '—', plan: d.get('plan') || '—',
          message: d.get('message') || '—'
        })
      }).catch(function () {});
      form.style.display = 'none';
      if (success) success.style.display = '';
    });
  }

  /* ── partner modal ───────────────────────────────────────────────────── */
  function initPartnerModal() {
    var modal   = $('#et-partner-modal');
    var form    = $('#et-partner-form');
    var success = $('#et-partner-success');
    var errEl   = $('#et-partner-err');
    if (!modal) return;
    function open()  { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    function close() { modal.style.display = 'none'; document.body.style.overflow = ''; }
    $$('[data-open-partner]').forEach(function (b) { on(b, 'click', open); });
    $$('[data-close-partner]').forEach(function (b) { on(b, 'click', close); });
    on(modal, 'click', function (e) { if (e.target === modal) close(); });
    on(document, 'keydown', function (e) { if (e.key === 'Escape') close(); });
    if (form) on(form, 'submit', function (e) {
      e.preventDefault();
      var d = new FormData(form);
      if (!d.get('contact') || !d.get('email')) {
        if (errEl) { errEl.textContent = 'Please enter your name and email.'; errEl.style.display = ''; }
        return;
      }
      if (errEl) errEl.style.display = 'none';
      fetch('https://formsubmit.co/ajax/info@enternstech.com', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          _subject: 'Partner request — ' + (d.get('company') || d.get('contact')),
          name: d.get('contact'), company: d.get('company') || '—',
          email: d.get('email'), phone: d.get('phone') || '—',
          partner_type: d.get('ptype') || '—', website: d.get('website') || '—',
          country: d.get('country') || '—', message: d.get('message') || '—'
        })
      }).catch(function () {});
      if (form) form.style.display = 'none';
      if (success) success.style.display = '';
    });
  }

  /* ── admin login modal ───────────────────────────────────────────────── */
  function initAdminModal() {
    var modal = $('#et-admin-modal');
    if (!modal) return;
    function open()  { modal.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    function close() { modal.style.display = 'none';  document.body.style.overflow = ''; }
    $$('[data-open-admin]').forEach(function (el)  { on(el, 'click', open); });
    $$('[data-close-admin]').forEach(function (el) { on(el, 'click', close); });
    on(modal, 'click', function (e) { if (e.target === modal) close(); });
    on(document, 'keydown', function (e) { if (e.key === 'Escape') close(); });
  }

  /* ── recruiter network canvas ────────────────────────────────────────── */
  function initNetCanvas() {
    var cv = $('#et-net-canvas');
    if (!cv) return;
    var ctx = cv.getContext('2d');
    var dpr = Math.min(2, window.devicePixelRatio || 1);
    var labels = ['Google','Deloitte','Cognizant','TCS','Capgemini','Infosys','Accenture','Wipro','Amazon','IBM','Cisco','PwC'];
    function resize() {
      var r = cv.parentElement.getBoundingClientRect();
      cv.width  = r.width  * dpr; cv.height = r.height * dpr;
      cv.style.width  = r.width  + 'px'; cv.style.height = r.height + 'px';
    }
    resize(); on(window, 'resize', resize, { passive: true });
    var t = 0;
    function draw() {
      var W = cv.width, H = cv.height;
      ctx.clearRect(0, 0, W, H);
      var cx = W / 2, cy = H / 2, R = Math.min(W, H) * 0.36, n = labels.length;
      var pts = labels.map(function (l, i) {
        var ang = (i / n) * Math.PI * 2 - Math.PI / 2 + t * 0.0006;
        return { x: cx + Math.cos(ang) * R, y: cy + Math.sin(ang) * R, l: l };
      });
      pts.forEach(function (p, i) {
        var prog = ((t * 0.0004) + i / n) % 1;
        ctx.strokeStyle = 'rgba(34,211,238,.15)'; ctx.lineWidth = dpr;
        ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(p.x, p.y); ctx.stroke();
        var dotX = cx + (p.x - cx) * prog, dotY = cy + (p.y - cy) * prog;
        ctx.fillStyle = 'rgba(91,233,255,.9)';
        ctx.beginPath(); ctx.arc(dotX, dotY, 2.6 * dpr, 0, 7); ctx.fill();
      });
      pts.forEach(function (p) {
        ctx.fillStyle = 'rgba(255,255,255,.04)'; ctx.strokeStyle = 'rgba(34,211,238,.28)'; ctx.lineWidth = dpr;
        ctx.beginPath(); ctx.arc(p.x, p.y, 5.5 * dpr, 0, 7); ctx.fill(); ctx.stroke();
        ctx.fillStyle = 'rgba(159,177,206,.82)';
        ctx.font = (11 * dpr) + 'px Inter,sans-serif'; ctx.textAlign = 'center';
        ctx.fillText(p.l, p.x, p.y - 12 * dpr);
      });
      var pr = (10 + Math.sin(t * 0.004) * 1.8) * dpr;
      ctx.fillStyle = 'rgba(34,211,238,.2)'; ctx.beginPath(); ctx.arc(cx, cy, pr + 9 * dpr, 0, 7); ctx.fill();
      ctx.fillStyle = '#22D3EE'; ctx.beginPath(); ctx.arc(cx, cy, pr, 0, 7); ctx.fill();
      ctx.fillStyle = '#05101F'; ctx.font = '700 ' + (11 * dpr) + 'px Space Grotesk,sans-serif';
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText('YOU', cx, cy); ctx.textBaseline = 'alphabetic';
      t += 16; requestAnimationFrame(draw);
    }
    draw();
  }

  /* ── card 3-D hover tilt ─────────────────────────────────────────────── */
  function initCardTilt() {
    $$('[data-tilt3d]').forEach(function (el) {
      el.style.transition = 'transform .28s ease-out';
      on(el, 'mousemove', function (e) {
        var r = el.getBoundingClientRect();
        var px = (e.clientX - (r.left + r.width  / 2)) / (r.width  / 2);
        var py = (e.clientY - (r.top  + r.height / 2)) / (r.height / 2);
        el.style.transform = 'perspective(820px) rotateY(' + (px * 6) + 'deg) rotateX(' + (-py * 6) + 'deg) translateY(-4px)';
      });
      on(el, 'mouseleave', function () { el.style.transform = ''; });
    });
  }

  /* ── mobile nav toggle ───────────────────────────────────────────────── */
  function initMobileNav() {
    var toggle = $('#et-nav-toggle');
    var menu   = $('#et-nav-menu');
    if (!toggle || !menu) return;
    on(toggle, 'click', function () {
      var open = menu.style.display !== 'flex';
      menu.style.display = open ? 'flex' : 'none';
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    $$('[data-scroll-to]', menu).forEach(function (el) {
      on(el, 'click', function () { menu.style.display = 'none'; toggle.setAttribute('aria-expanded', 'false'); });
    });
  }

  /* ── boot ────────────────────────────────────────────────────────────── */
  function init() {
    initNav();
    initScrollLinks();
    initMobileNav();
    initReveal();
    initCounters();
    initTracks();
    initFaq();
    initPricing();
    initEnrolModal();
    initContactForm();
    initPartnerModal();
    initAdminModal();
    if (!REDUCED) {
      initPointer();
      initCursor();
      initMagnetic();
      initHeroTilt();
      initHeroCanvas();
      initNetCanvas();
      initCardTilt();
    }
    initPlacementCycle();
  }

  if (document.readyState === 'loading') on(document, 'DOMContentLoaded', init);
  else init();
})();
