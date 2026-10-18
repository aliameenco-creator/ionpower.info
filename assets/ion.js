/* ION Power theme interactions. Every effect enhances editable block content; without JavaScript the content stays visible. */
(() => {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const hover = matchMedia('(hover: hover)').matches;
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => [...c.querySelectorAll(s)];

  /* Header: solid on scroll, progress bar, phone menu */
  const header = $('[data-header]');
  const progress = $('.ionp-progress');
  const onScroll = () => {
    const h = document.documentElement;
    if (progress) progress.style.transform = `scaleX(${h.scrollTop / ((h.scrollHeight - h.clientHeight) || 1)})`;
    if (header) header.classList.toggle('scrolled', h.scrollTop > 40);
  };
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  const burger = $('.ionp-burger');
  const nav = $('#ionp-nav');
  const setMenu = (open) => {
    if (!burger || !nav) return;
    nav.classList.toggle('open', open);
    burger.setAttribute('aria-expanded', String(open));
    burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  };
  if (burger) burger.addEventListener('click', () => setMenu(!nav.classList.contains('open')));
  if (nav) $$('a', nav).forEach((a) => a.addEventListener('click', () => setMenu(false)));
  addEventListener('keydown', (e) => { if (e.key === 'Escape') setMenu(false); });

  /* Active section link on the homepage */
  const homeLinks = $$('.ionp-menu a').filter((a) => { try { const u = new URL(a.href); return u.hash && u.pathname === location.pathname; } catch (_) { return false; } });
  if (homeLinks.length) {
    const io = new IntersectionObserver((entries) => entries.forEach((e) => {
      if (e.isIntersecting) homeLinks.forEach((a) => a.classList.toggle('is-active', new URL(a.href).hash === '#' + e.target.id));
    }), { rootMargin: '-45% 0px -50% 0px' });
    homeLinks.forEach((a) => { const t = document.getElementById(decodeURIComponent(new URL(a.href).hash.slice(1))); if (t) io.observe(t); });
  }

  /* Hero: headline lines, intro */
  const hero = $('.ionp-hero');
  if (hero) {
    const title = $('.ionp-hero-title', hero);
    if (title) {
      const parts = title.innerHTML.split(/<br\s*\/?>/i);
      title.innerHTML = parts.map((p, i) => `<span class="ionp-line"><span style="--d:${0.15 + i * 0.15}s">${p}</span></span>`).join('');
    }
    $$('.ionp-checks li', hero).forEach((li, i) => li.style.setProperty('--d', `${0.55 + i * 0.2}s`));
    requestAnimationFrame(() => setTimeout(() => hero.classList.add('loaded'), 60));
  }

  /* Ticker band: "a / b / c" becomes a moving strip */
  $$('.ionp-ticker').forEach((p) => {
    const items = p.textContent.split('/').map((s) => s.trim()).filter(Boolean);
    if (items.length < 2) return;
    p.setAttribute('aria-label', items.join(', '));
    const spans = items.map((t) => `<span>${t.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]))}</span>`).join('');
    p.innerHTML = `<span class="ionp-track" aria-hidden="true">${spans}${spans}</span>`;
    p.classList.add('on');
  });

  /* Reveal on scroll (staggered within each parent) */
  const revealSel = [
    '.ionp-sec > .wp-block-heading', '.ionp-sec > p', '.ionp-sec > .wp-block-image', '.ionp-sec > .wp-block-gallery',
    '.ionp-center-head > *', '.ionp-split-head > .wp-block-column', '.ionp-sticky', '.ionp-about-grid > .wp-block-column:last-child > p:not(.ionp-words)',
    '.ionp-canpower', '.ionp-stat', '.ionp-cav-grid > .wp-block-column > *', '.ionp-compare', '.ionp-zcard', '.ionp-prod', '.ionp-bcard',
    '.ionp-bento .wp-block-image', '.ionp-paradigm', '.ionp-bood-grid > .wp-block-column:first-child > *', '.ionp-sim', '.ionp-answer',
    '.ionp-cbox', '.ionp-quotes', '.ionp-contact-info > *', '.ionp-form-card', '.ionp-prose > *', '.ionp-page-hero > *',
  ].join(',');
  const revealIO = new IntersectionObserver((entries) => entries.forEach((e) => {
    if (e.isIntersecting) { e.target.classList.add('in'); revealIO.unobserve(e.target); }
  }), { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  const seen = new Map();
  $$(revealSel).forEach((el) => {
    if (el.closest('.ionp-hero')) return;
    const parent = el.parentElement;
    const i = seen.get(parent) || 0;
    seen.set(parent, i + 1);
    el.style.setProperty('--d', `${Math.min(i, 6) * 0.08}s`);
    el.classList.add('ionp-rv');
    revealIO.observe(el);
  });
  $$('.ionp-about-photo, .ionp-stat, .ionp-bood-words').forEach((el) => revealIO.observe(el));

  /* Quotes: card deck with progress bars, arrows and swipe */
  $$('.ionp-quotes').forEach((deck) => {
    const cards = $$(':scope > .wp-block-quote', deck);
    if (cards.length < 2) return;
    deck.classList.add('deck');
    const fit = () => { deck.style.setProperty('--deck-h', '0px'); const h = Math.max(...cards.map((c) => c.scrollHeight)); deck.style.setProperty('--deck-h', `${h}px`); };
    const ctl = document.createElement('div');
    ctl.className = 'ionp-qctl';
    ctl.innerHTML = '<div class="ionp-qbar"></div><div class="ionp-qnav"><button type="button" aria-label="Previous quote"><svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></button><button type="button" aria-label="Next quote"><svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></button></div>';
    deck.after(ctl);
    const bar = $('.ionp-qbar', ctl);
    cards.forEach((_, i) => { const b = document.createElement('button'); b.type = 'button'; b.setAttribute('aria-label', `Quote ${i + 1}`); b.innerHTML = '<i></i>'; b.addEventListener('click', () => show(i)); bar.appendChild(b); });
    const bars = $$('button', bar);
    let qi = 0, timer;
    function show(i) {
      cards[qi].classList.remove('on');
      qi = (i + cards.length) % cards.length;
      cards[qi].classList.add('on');
      bars.forEach((b, k) => { b.classList.remove('run'); b.classList.toggle('done', k < qi); void b.offsetWidth; });
      clearTimeout(timer);
      if (!reduce) { bars[qi].classList.add('run'); timer = setTimeout(() => show(qi + 1), 7000); }
    }
    const [prev, next] = $$('.ionp-qnav button', ctl);
    prev.addEventListener('click', () => show(qi - 1));
    next.addEventListener('click', () => show(qi + 1));
    let sx = 0;
    deck.addEventListener('touchstart', (e) => { sx = e.touches[0].clientX; }, { passive: true });
    deck.addEventListener('touchend', (e) => { const dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 40) show(qi + (dx < 0 ? 1 : -1)); });
    fit();
    addEventListener('resize', fit);
    if (document.fonts) document.fonts.ready.then(fit);
    show(0);
  });

  /* About quote: words light up while scrolling */
  const wordsEls = $$('.ionp-words');
  const wordSpans = [];
  wordsEls.forEach((p) => {
    const walk = (node, hl) => {
      [...node.childNodes].forEach((n) => {
        if (n.nodeType === 3) {
          const frag = document.createDocumentFragment();
          n.textContent.split(/(\s+)/).forEach((w) => {
            if (!w) return;
            if (/^\s+$/.test(w)) { frag.appendChild(document.createTextNode(w)); return; }
            const s = document.createElement('span'); s.className = 'w' + (hl ? ' hl' : ''); s.textContent = w; frag.appendChild(s);
          });
          n.replaceWith(frag);
        } else if (n.nodeType === 1) { walk(n, hl || n.tagName === 'STRONG'); }
      });
    };
    walk(p, false);
    p.classList.add('split');
    wordSpans.push([p, $$('.w', p)]);
  });

  /* "Everywhere. Everyone. Everything." rotator */
  $$('.ionp-rotator').forEach((p) => {
    const words = (p.textContent.match(/[^.]+\./g) || []).map((w) => w.trim());
    if (words.length < 2 || reduce) return;
    p.setAttribute('aria-label', words.join(' '));
    p.innerHTML = `<span class="ionp-rot" aria-hidden="true">${[...words, words[0]].map((w) => `<b>${w}</b>`).join('')}</span>`;
    p.classList.add('on');
    const rot = $('.ionp-rot', p);
    let i = 0;
    setInterval(() => {
      i += 1;
      rot.style.transition = '';
      rot.style.transform = `translateY(${-i * 1.28}em)`;
      if (i === words.length) {
        setTimeout(() => { rot.style.transition = 'none'; rot.style.transform = 'translateY(0)'; i = 0; }, 950);
      }
    }, 2600);
  });

  /* Count-up numbers */
  const countIO = new IntersectionObserver((entries) => entries.forEach((e) => {
    if (!e.isIntersecting) return;
    countIO.unobserve(e.target);
    const el = e.target, to = parseInt(el.dataset.to, 10), t0 = performance.now(), dur = 1600;
    const tick = (t) => { const p = Math.min(1, (t - t0) / dur); el.textContent = String(Math.round(to * (1 - Math.pow(1 - p, 3)))); if (p < 1) requestAnimationFrame(tick); };
    requestAnimationFrame(tick);
  }), { threshold: 0.6 });
  if (!reduce) $$('.ionp-num').forEach((n) => { if (/^\d+$/.test(n.textContent.trim())) { n.dataset.to = n.textContent.trim(); countIO.observe(n); } });

  /* B.O.O.D words light up one by one */
  $$('.ionp-bood-words').forEach((h) => {
    const words = h.textContent.trim().split(/\s+/);
    h.setAttribute('aria-label', h.textContent.trim());
    h.innerHTML = words.map((w, i) => `<span aria-hidden="true" style="--d:${i * 0.18}s">${w}</span>`).join(' ');
    h.classList.add('split');
  });

  /* Scroll-driven: lit words, photo parallax, production timeline */
  const photo = $('.ionp-about-photo img');
  const timeline = $('.ionp-timeline');
  const milestones = timeline ? $$('li', timeline) : [];
  const scrollFx = () => {
    const vh = innerHeight;
    wordSpans.forEach(([p, spans]) => {
      const r = p.getBoundingClientRect();
      const prog = Math.min(1, Math.max(0, (vh * 0.85 - r.top) / (r.height + vh * 0.35)));
      const lit = Math.round(prog * spans.length);
      spans.forEach((s, i) => s.classList.toggle('lit', i < lit));
    });
    if (photo) { const r = photo.parentElement.getBoundingClientRect(); photo.style.transform = `translateY(${(r.top - vh / 2) * -0.08 - 30}px)`; }
    if (timeline) {
      const r = timeline.getBoundingClientRect();
      const tp = Math.min(1, Math.max(0, (vh * 0.9 - r.top) / (vh * 0.55)));
      timeline.style.setProperty('--p', tp.toFixed(3));
      milestones.forEach((m, i) => m.classList.toggle('on', tp >= i / Math.max(1, milestones.length - 1) - 0.01));
    }
  };
  if (reduce) {
    wordSpans.forEach(([, spans]) => spans.forEach((s) => s.classList.add('lit')));
    milestones.forEach((m) => m.classList.add('on'));
  } else {
    addEventListener('scroll', scrollFx, { passive: true });
    addEventListener('resize', scrollFx);
    scrollFx();
  }

  /* Card tilt on hover */
  if (hover && !reduce) {
    $$('.ionp-zcard, .ionp-bcard').forEach((c) => {
      c.addEventListener('mousemove', (e) => {
        const r = c.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5;
        c.style.transform = `perspective(900px) rotateY(${x * 5}deg) rotateX(${-y * 5}deg) translateY(-6px)`;
      });
      c.addEventListener('mouseleave', () => { c.style.transform = ''; });
    });
  }

  /* Video: poster with play button; the YouTube player loads only on click (privacy-enhanced domain) */
  $$('.ionp-video a').forEach((a) => {
    const id = (a.href.match(/(?:v=|youtu\.be\/|embed\/)([\w-]{11})/) || [])[1];
    if (!id) return;
    const play = document.createElement('span');
    play.className = 'ionp-play';
    play.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v16l13-8z"/></svg>';
    a.appendChild(play);
    a.addEventListener('click', (e) => {
      e.preventDefault();
      const f = document.createElement('iframe');
      f.src = `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0`;
      f.title = (a.querySelector('img') || {}).alt || 'Video';
      f.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture';
      f.allowFullscreen = true;
      a.closest('figure').appendChild(f);
      f.focus();
    });
  });

  /* Gallery lightbox */
  const lb = $('.ionp-lb');
  const shots = $$('.ionp-bento a');
  if (lb && shots.length) {
    const img = $('img', lb);
    let li = 0, opener = null;
    const open = (i) => { li = (i + shots.length) % shots.length; img.src = shots[li].href; img.alt = (shots[li].querySelector('img') || {}).alt || ''; lb.hidden = false; document.body.style.overflow = 'hidden'; $('.ionp-lb-x', lb).focus(); };
    const close = () => { lb.hidden = true; document.body.style.overflow = ''; if (opener) opener.focus(); };
    shots.forEach((a, i) => a.addEventListener('click', (e) => { e.preventDefault(); opener = a; open(i); }));
    $('.ionp-lb-x', lb).addEventListener('click', close);
    $('.ionp-lb-p', lb).addEventListener('click', () => open(li - 1));
    $('.ionp-lb-n', lb).addEventListener('click', () => open(li + 1));
    lb.addEventListener('click', (e) => { if (e.target === lb) close(); });
    addEventListener('keydown', (e) => {
      if (lb.hidden) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') open(li - 1);
      if (e.key === 'ArrowRight') open(li + 1);
    });
  }

  /* Transmission-loss slider (original 1000-4000 mile figures from the page) */
  $$('[data-ionp-sim]').forEach((sim) => {
    const rows = $$('.ionp-sim-data li', sim).map((li) => ({ loss: li.dataset.loss, cost: li.dataset.cost, text: li.textContent }));
    const range = $('input[type=range]', sim);
    if (!rows.length || !range) return;
    sim.classList.add('on');
    const lossEl = $('[data-loss]', sim), costEl = $('[data-cost]', sim), sentence = $('.ionp-sentence', sim), wire = $('.ionp-simwire', sim);
    range.max = String(rows.length - 1);
    const anim = (el, to, dec) => {
      const from = parseFloat(el.textContent) || 0, t0 = performance.now();
      const step = (t) => { const p = Math.min(1, (t - t0) / 600); el.textContent = (from + (to - from) * (1 - Math.pow(1 - p, 3))).toFixed(dec) + '%'; if (p < 1) requestAnimationFrame(step); };
      if (reduce) el.textContent = to.toFixed(dec) + '%'; else requestAnimationFrame(step);
    };
    const set = () => {
      const row = rows[+range.value];
      range.style.setProperty('--v', `${(range.value / Math.max(1, rows.length - 1)) * 100}%`);
      if (wire) wire.style.setProperty('--fade', (1 - parseFloat(row.loss) / 40).toFixed(2));
      anim(lossEl, parseFloat(row.loss), (row.loss.split('.')[1] || '').length);
      anim(costEl, parseFloat(row.cost), 0);
      sentence.textContent = row.text;
    };
    range.addEventListener('input', set);
    set();
  });

  /* Contact form: in-page submit (the form's action attribute, never form.action: a field is named "action") */
  $$('[data-ionp-form]').forEach((form) => {
    const started = Date.now();
    const status = $('.ionp-form-status', form);
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const elapsed = $('[data-elapsed]', form);
      if (elapsed) elapsed.value = String(Date.now() - started);
      $$('.ionp-field-error', form).forEach((s) => { s.textContent = ''; });
      $$('[aria-invalid]', form).forEach((f) => f.removeAttribute('aria-invalid'));
      form.classList.add('sending');
      try {
        const res = await fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const data = await res.json();
        status.innerHTML = '';
        const p = document.createElement('p');
        p.className = data.ok ? 'ionp-form-success' : 'ionp-form-error';
        p.textContent = data.message || (data.ok ? 'Thanks for your submission!' : 'Something went wrong. Please try again.');
        status.appendChild(p);
        Object.entries(data.errors || {}).forEach(([key, msg]) => {
          const field = form.querySelector(`[name="ionp_${key}"]`);
          if (!field) return;
          field.setAttribute('aria-invalid', 'true');
          const err = field.parentElement.querySelector('.ionp-field-error');
          if (err) err.textContent = msg;
        });
        if (data.ok) form.reset();
      } catch (err) {
        status.innerHTML = '<p class="ionp-form-error">Your message could not be sent. Please try again.</p>';
      }
      form.classList.remove('sending');
    });
  });
})();
