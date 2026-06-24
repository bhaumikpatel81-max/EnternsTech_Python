/**
 * Psychometric candidate flow JS
 * Handles: token validation → details → section runner → submit
 */

const SECTION_NAMES = {
  1: 'Professional Strengths',
  2: 'Work Preferences',
  3: 'Learning Orientation',
  4: 'What Motivates You',
  5: 'Workplace Engagement',
  6: 'Personal Style',
  7: 'Reasoning',
  8: 'Reflection',
};

let _token = '';
let _paper = {};   // {sectionNum: [item, ...]}
let _answers = {}; // {sectionNum: {item_id: answer}}
let _sections = [];
let _currentSec = 0;

function show(stepId) {
  document.querySelectorAll('.psy-step').forEach(el => el.style.display = 'none');
  document.getElementById(stepId).style.display = 'flex';
}

async function initAssessment(token) {
  _token = token;
  if (!token) {
    show('step-invalid');
    document.getElementById('invalid-msg').textContent = 'No assessment token found in the URL.';
    return;
  }

  show('step-loading');
  try {
    const r = await fetch('/api/psy/validate-token', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token }),
    });
    const d = await r.json();
    if (!d.ok) {
      if (d.error === 'assessment_already_submitted') {
        show('step-submitted'); return;
      }
      show('step-invalid');
      document.getElementById('invalid-msg').textContent = d.error || 'Invalid link.';
      return;
    }
    // Pre-fill details form
    document.getElementById('det-name').value  = d.candidate_name  || '';
    document.getElementById('det-email').value = d.candidate_email || '';
    document.getElementById('det-phone').value = d.candidate_phone || '';
    const eduSel = document.getElementById('det-edu');
    if (d.education_level) eduSel.value = String(d.education_level);
    const fieldSel = document.getElementById('det-field');
    if (d.field) fieldSel.value = d.field;

    show('step-welcome');
    document.getElementById('consent-check').addEventListener('change', e => {
      document.getElementById('begin-btn').disabled = !e.target.checked;
    });
    document.getElementById('begin-btn').addEventListener('click', () => show('step-details'));
  } catch (e) {
    show('step-invalid');
    document.getElementById('invalid-msg').textContent = 'Network error. Please try again.';
  }
}

async function submitDetails() {
  const name  = document.getElementById('det-name').value.trim();
  const email = document.getElementById('det-email').value.trim();
  const errEl = document.getElementById('det-err');
  if (!name || !email) {
    errEl.textContent = 'Name and email are required.'; errEl.style.display = 'block'; return;
  }
  if (!email.includes('@')) {
    errEl.textContent = 'Please enter a valid email.'; errEl.style.display = 'block'; return;
  }
  errEl.style.display = 'none';

  show('step-loading');
  try {
    const r = await fetch('/api/psy/save-details', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        token: _token,
        name,
        email,
        phone:           document.getElementById('det-phone').value.trim(),
        education_level: document.getElementById('det-edu').value,
        field:           document.getElementById('det-field').value,
      }),
    });
    const d = await r.json();
    if (!d.ok) {
      show('step-details');
      document.getElementById('det-err').textContent = d.error || 'Error saving details.';
      document.getElementById('det-err').style.display = 'block';
      return;
    }
    _paper = d.paper;
    _sections = Object.keys(_paper).map(Number).sort((a, b) => a - b);
    _sections.forEach(s => { _answers[s] = {}; });
    _currentSec = 0;
    renderSection(_currentSec);
    show('step-runner');
  } catch (e) {
    show('step-details');
  }
}

function renderSection(idx) {
  const sec = _sections[idx];
  if (sec === undefined) { renderReview(); return; }

  const items = _paper[sec] || [];
  const totalSecs = _sections.length;

  // Progress bar
  document.getElementById('progress-bar').style.width = `${Math.round((idx / totalSecs) * 100)}%`;

  // Header
  document.getElementById('section-badge').textContent = `Section ${sec} of ${totalSecs}`;
  document.getElementById('section-title').textContent = SECTION_NAMES[sec] || `Section ${sec}`;
  document.getElementById('section-progress').textContent = `${items.length} item${items.length !== 1 ? 's' : ''}`;

  // Nav buttons
  document.getElementById('prev-btn').style.display = idx > 0 ? 'inline-flex' : 'none';
  document.getElementById('next-btn').textContent = idx < totalSecs - 1 ? 'Next →' : 'Review →';

  // Render items
  const container = document.getElementById('items-container');
  container.innerHTML = '';
  items.forEach((item, i) => {
    container.appendChild(renderItem(item, i + 1, sec));
  });

  // Restore saved answers
  restoreAnswers(sec);
}

function renderItem(item, num, sec) {
  const div = document.createElement('div');
  div.className = 'psy-item';
  div.dataset.itemId = item.item_id;

  const qNum = document.createElement('div');
  qNum.className = 'psy-item__num';
  qNum.textContent = `Q${num}`;
  div.appendChild(qNum);

  const qText = document.createElement('div');
  qText.className = 'psy-item__question';
  qText.textContent = item.question_text;
  div.appendChild(qText);

  if (item.type === 'likert') {
    div.appendChild(renderLikert(item, sec));
  } else if (item.type === 'forced_choice') {
    div.appendChild(renderForcedChoice(item, sec));
  } else if (item.type === 'mcq') {
    div.appendChild(renderMCQ(item, sec));
  } else if (item.type === 'rank') {
    div.appendChild(renderRank(item, sec));
  } else if (item.type === 'open') {
    div.appendChild(renderOpen(item, sec));
  }
  return div;
}

function renderLikert(item, sec) {
  const wrap = document.createElement('div');
  wrap.className = 'psy-likert';
  const labels = ['Strongly\nDisagree', 'Disagree', 'Neutral', 'Agree', 'Strongly\nAgree'];
  [1,2,3,4,5].forEach((val, i) => {
    const label = document.createElement('label');
    const inp = document.createElement('input');
    inp.type = 'radio'; inp.name = `item_${item.item_id}`; inp.value = val;
    inp.addEventListener('change', () => saveAnswer(sec, item.item_id, val));
    const dot = document.createElement('div'); dot.className = 'psy-likert-opt'; dot.textContent = val;
    const lbl = document.createElement('span');
    lbl.textContent = labels[i];
    lbl.style.whiteSpace = 'pre-line';
    label.appendChild(inp); label.appendChild(dot); label.appendChild(lbl);
    wrap.appendChild(label);
  });
  return wrap;
}

function renderForcedChoice(item, sec) {
  const wrap = document.createElement('div'); wrap.className = 'psy-options';
  [['A', item.option_a], ['B', item.option_b]].forEach(([key, text]) => {
    if (!text) return;
    const label = document.createElement('label'); label.className = 'psy-option';
    const inp = document.createElement('input');
    inp.type = 'radio'; inp.name = `item_${item.item_id}`; inp.value = key;
    inp.addEventListener('change', () => saveAnswer(sec, item.item_id, key));
    const dot = document.createElement('div'); dot.className = 'psy-option-dot';
    const lbl = document.createElement('span'); lbl.className = 'psy-option-label'; lbl.textContent = text;
    label.appendChild(inp); label.appendChild(dot); label.appendChild(lbl);
    wrap.appendChild(label);
  });
  return wrap;
}

function renderMCQ(item, sec) {
  const wrap = document.createElement('div'); wrap.className = 'psy-options';
  ['A','B','C','D'].forEach(key => {
    const text = item[`option_${key.toLowerCase()}`];
    if (!text) return;
    const label = document.createElement('label'); label.className = 'psy-option';
    const inp = document.createElement('input');
    inp.type = 'radio'; inp.name = `item_${item.item_id}`; inp.value = key;
    inp.addEventListener('change', () => saveAnswer(sec, item.item_id, key));
    const dot = document.createElement('div'); dot.className = 'psy-option-dot';
    const lbl = document.createElement('span'); lbl.className = 'psy-option-label'; lbl.textContent = text;
    label.appendChild(inp); label.appendChild(dot); label.appendChild(lbl);
    wrap.appendChild(label);
  });
  return wrap;
}

function renderRank(item, sec) {
  const wrap = document.createElement('div'); wrap.className = 'psy-rank';
  const row = document.createElement('div'); row.className = 'psy-rank-item';
  const num = document.createElement('div'); num.className = 'psy-rank-num'; num.textContent = '#';
  const lbl = document.createElement('div'); lbl.className = 'psy-rank-label'; lbl.textContent = item.question_text;
  const sel = document.createElement('select'); sel.className = 'psy-rank-select';
  const blank = document.createElement('option'); blank.value = ''; blank.textContent = '—'; sel.appendChild(blank);
  [1,2,3,4,5,6,7,8,9,10].forEach(n => {
    const opt = document.createElement('option'); opt.value = n; opt.textContent = n; sel.appendChild(opt);
  });
  sel.addEventListener('change', () => saveAnswer(sec, item.item_id, sel.value));
  row.appendChild(num); row.appendChild(lbl); row.appendChild(sel);
  wrap.appendChild(row);
  return wrap;
}

function renderOpen(item, sec) {
  const ta = document.createElement('textarea');
  ta.className = 'psy-open-textarea';
  ta.placeholder = 'Your answer…';
  ta.dataset.itemId = item.item_id;
  let debounce;
  ta.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => saveAnswer(sec, item.item_id, ta.value), 600);
  });
  return ta;
}

function saveAnswer(sec, itemId, value) {
  if (!_answers[sec]) _answers[sec] = {};
  _answers[sec][itemId] = value;
}

function restoreAnswers(sec) {
  const ans = _answers[sec] || {};
  Object.entries(ans).forEach(([itemId, val]) => {
    const radio = document.querySelector(`input[name="item_${itemId}"][value="${val}"]`);
    if (radio) { radio.checked = true; return; }
    const ta = document.querySelector(`.psy-item[data-item-id="${itemId}"] textarea`);
    if (ta) ta.value = val;
    const rankSel = document.querySelector(`.psy-item[data-item-id="${itemId}"] select`);
    if (rankSel) rankSel.value = val;
  });
}

async function autosave(sec) {
  const answers = _answers[sec] || {};
  if (!Object.keys(answers).length) return;
  try {
    await fetch('/api/psy/autosave', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: _token, section: sec, answers }),
    });
  } catch (e) { /* silent */ }
}

async function nextSection() {
  const sec = _sections[_currentSec];
  await autosave(sec);
  _currentSec++;
  if (_currentSec >= _sections.length) {
    renderReview();
    show('step-review');
  } else {
    renderSection(_currentSec);
  }
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function prevSection() {
  if (_currentSec > 0) {
    _currentSec--;
    renderSection(_currentSec);
    show('step-runner');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

function goToSection(idx) {
  _currentSec = idx;
  renderSection(_currentSec);
  show('step-runner');
}

function renderReview() {
  const container = document.getElementById('review-summary');
  container.innerHTML = '';
  _sections.forEach((sec, idx) => {
    const items   = _paper[sec] || [];
    const ans     = _answers[sec] || {};
    const answered = Object.keys(ans).filter(k => ans[k] !== '' && ans[k] !== undefined).length;
    const complete = answered >= items.length;

    const row = document.createElement('div');
    row.className = `psy-review-section ${complete ? '' : 'incomplete'}`;
    row.innerHTML = `
      <div>
        <div class="sec-name">Section ${sec}: ${SECTION_NAMES[sec] || ''}</div>
        <div class="sec-answered">${answered}/${items.length} answered</div>
      </div>
      <div class="sec-tick">${complete ? '✓' : '⚠'}</div>
    `;
    row.style.cursor = 'pointer';
    row.addEventListener('click', () => goToSection(idx));
    container.appendChild(row);
  });
}

async function submitAssessment() {
  const errEl = document.getElementById('submit-error');
  errEl.style.display = 'none';
  try {
    const r = await fetch('/api/psy/submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: _token }),
    });
    const d = await r.json();
    if (d.ok) {
      show('step-thankyou');
    } else {
      errEl.textContent = d.error || 'Submission failed. Please try again.';
      errEl.style.display = 'block';
    }
  } catch (e) {
    errEl.textContent = 'Network error. Please check your connection and try again.';
    errEl.style.display = 'block';
  }
}
