/**
 * Enterns Tech — Psychometric Assessment candidate UI.
 * Drives the multi-step flow: validate → welcome → details → instructions
 * → section runner (autosave) → review → submit → thank-you.
 *
 * SECURITY: correct answers and reverse_scored flags are never in this file
 * or any payload sent to the browser. Submit returns only {status:"ok"}.
 */
/* global jQuery, ENP_PSY */
(function ($) {
  'use strict';

  var AJAX   = ENP_PSY.ajaxUrl;
  var NONCE  = ENP_PSY.nonce;
  var TOKEN  = ENP_PSY.token;

  // State
  var paper       = {};   // section_num → [item, …]
  var sectionMeta = {};   // section_num → {label, count, type}
  var answers     = {};   // item_id → value
  var sectionOrder = [];  // [1,2,3,…,8]
  var currentSectionIdx = 0;
  var autosaveTimer = null;

  var SECTION_DESCS = {
    1: 'Rate each statement on a scale of 1 (Strongly Disagree) to 5 (Strongly Agree).',
    2: 'For each pair, choose the option that is more like you.',
    3: 'Rate each statement on a scale of 1 (Strongly Disagree) to 5 (Strongly Agree).',
    4: 'Rank these motivators in order from most (1) to least important to you.',
    5: 'Rate each statement on a scale of 1 (Strongly Disagree) to 5 (Strongly Agree).',
    6: 'Rate each statement on a scale of 1 (Strongly Disagree) to 5 (Strongly Agree).',
    7: 'Select the single correct answer for each question.',
    8: 'Answer each question briefly in your own words. A few sentences is fine.'
  };

  // ── Show / hide screens ────────────────────────────────────────────────────

  function showScreen(id) {
    $('.enp-psy__screen').hide();
    $('#' + id).show();
    window.scrollTo(0, 0);
  }

  function showError(containerId, msg) {
    $('#' + containerId).text(msg).show();
  }
  function clearError(containerId) {
    $('#' + containerId).hide().text('');
  }

  // ── Autosave indicator ─────────────────────────────────────────────────────

  function setAutosave(state, msg) {
    var $el = $('#enp-psy-autosave-indicator');
    $el.removeClass('enp-psy__autosave--saving enp-psy__autosave--saved');
    if (state === 'saving') { $el.addClass('enp-psy__autosave--saving').text('Saving…'); }
    else if (state === 'saved') { $el.addClass('enp-psy__autosave--saved').text('✓ Saved'); }
    else { $el.text(msg || ''); }
  }

  // ── INIT ──────────────────────────────────────────────────────────────────

  function init() {
    if (!TOKEN) { showScreen('enp-psy-invalid'); return; }
    showScreen('enp-psy-loading');

    $.post(AJAX, {
      action: 'enp_psy_validate_token',
      nonce:  NONCE,
      token:  TOKEN
    }, function (res) {
      if (!res.success) { showScreen('enp-psy-invalid'); return; }
      var d = res.data;

      // Pre-fill details form.
      if (d.candidate_name)  $('#psy-name').val(d.candidate_name);
      if (d.candidate_email) $('#psy-email').val(d.candidate_email);
      if (d.candidate_phone) $('#psy-phone').val(d.candidate_phone);
      if (d.education_level) $('#psy-edu').val(d.education_level);
      if (d.field)           $('#psy-field').val(d.field);

      if (d.status === 'submitted') {
        showScreen('enp-psy-thankyou'); return;
      }
      showScreen('enp-psy-welcome');
    }).fail(function () { showScreen('enp-psy-invalid'); });
  }

  // ── Welcome → Details ─────────────────────────────────────────────────────

  $('#enp-psy-begin-btn').on('click', function () {
    showScreen('enp-psy-details');
  });

  // ── Details form submit ────────────────────────────────────────────────────

  $('#enp-psy-details-form').on('submit', function (e) {
    e.preventDefault();
    clearError('enp-psy-details-error');

    var $btn = $('#enp-psy-details-submit').prop('disabled', true).text('Saving…');

    $.post(AJAX, {
      action:           'enp_psy_save_details',
      nonce:            NONCE,
      token:            TOKEN,
      candidate_name:   $('#psy-name').val(),
      candidate_email:  $('#psy-email').val(),
      candidate_phone:  $('#psy-phone').val(),
      education_level:  $('#psy-edu').val(),
      field:            $('#psy-field').val()
    }, function (res) {
      $btn.prop('disabled', false).text('Continue →');
      if (!res.success) { showError('enp-psy-details-error', res.data || 'An error occurred.'); return; }

      // Store paper + meta.
      paper       = res.data.paper;
      sectionMeta = res.data.meta;
      sectionOrder = Object.keys(paper).map(Number).sort(function (a, b) { return a - b; });

      showScreen('enp-psy-instructions');
    }).fail(function () {
      $btn.prop('disabled', false).text('Continue →');
      showError('enp-psy-details-error', 'Network error. Please try again.');
    });
  });

  // ── Instructions → Runner ─────────────────────────────────────────────────

  $('#enp-psy-start-btn').on('click', function () {
    currentSectionIdx = 0;
    showScreen('enp-psy-runner');
    renderSection(currentSectionIdx);
  });

  // ── Section renderer ───────────────────────────────────────────────────────

  function renderSection(idx) {
    var secNum  = sectionOrder[idx];
    var items   = paper[secNum] || [];
    var meta    = sectionMeta[secNum] || {};
    var total   = sectionOrder.length;
    var pct     = Math.round(((idx) / total) * 100);

    // Progress.
    $('#enp-psy-progress-fill').css('width', pct + '%');
    $('#enp-psy-progress-bar').attr('aria-valuenow', pct);
    $('#enp-psy-section-label').text('Section ' + (idx + 1) + ' of ' + total + ' — ' + (meta.label || ''));
    $('#enp-psy-progress-pct').text(pct + '%');

    // Section header.
    $('#enp-psy-section-num').text('Section ' + (idx + 1) + ' of ' + total);
    $('#enp-psy-section-title').text(meta.label || '');
    $('#enp-psy-section-desc').text(SECTION_DESCS[secNum] || '');

    // Nav buttons.
    $('#enp-psy-back-btn').toggle(idx > 0);
    $('#enp-psy-next-btn').text(idx === total - 1 ? 'Review →' : 'Next →');

    clearError('enp-psy-runner-error');
    setAutosave('', '');

    // Render questions.
    var $qc = $('#enp-psy-questions').empty();
    items.forEach(function (item, i) {
      $qc.append(renderQuestion(item, i + 1, secNum));
    });

    // Restore saved answers.
    restoreAnswers(secNum, items);

    // Drag-and-drop for rank sections.
    if (meta.type === 'rank') {
      initRankDnd(secNum);
    }
  }

  function renderQuestion(item, num, secNum) {
    var $wrap = $('<div>', { 'class': 'enp-psy__q', 'data-item-id': item.item_id });
    $wrap.append($('<div>', { 'class': 'enp-psy__q-num', text: 'Q' + num }));
    $wrap.append($('<div>', { 'class': 'enp-psy__q-text', text: item.question_text }));
    $wrap.append(renderInput(item, secNum));
    return $wrap;
  }

  function renderInput(item, secNum) {
    var type = item.type;
    if (type === 'likert') return renderLikert(item);
    if (type === 'forced_choice') return renderForcedChoice(item);
    if (type === 'mcq')   return renderMCQ(item);
    if (type === 'rank')  return renderRankInput(item);
    if (type === 'open')  return renderOpen(item);
    return $('<div>');
  }

  // ── Likert (1–5) ─────────────────────────────────────────────────────────

  function renderLikert(item) {
    var $wrap = $('<div>', { 'class': 'enp-psy__likert' });
    var labels = ['Strongly Disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly Agree'];
    var $labRow = $('<div>', { 'class': 'enp-psy__likert-labels' });
    $labRow.append($('<span>', { text: labels[0] }));
    $labRow.append($('<span>', { text: labels[4] }));
    $wrap.append($labRow);

    var $opts = $('<div>', { 'class': 'enp-psy__likert-options' });
    for (var v = 1; v <= 5; v++) {
      var id = 'likert-' + item.item_id + '-' + v;
      var $opt = $('<div>', { 'class': 'enp-psy__likert-opt' });
      var $inp = $('<input>', { type: 'radio', name: 'q_' + item.item_id, id: id, value: v });
      var $lbl = $('<label>', { 'for': id, 'class': 'enp-psy__likert-btn', text: v, title: labels[v - 1] });
      $opt.append($inp, $lbl);
      $opts.append($opt);
    }
    $wrap.append($opts);
    return $wrap;
  }

  // ── Forced choice (A/B) ──────────────────────────────────────────────────

  function renderForcedChoice(item) {
    var $wrap = $('<div>', { 'class': 'enp-psy__forced' });
    var opts = [];
    if (item.option_a) opts.push({ key: 'A', text: item.option_a });
    if (item.option_b) opts.push({ key: 'B', text: item.option_b });
    opts.forEach(function (opt) {
      var id = 'fc-' + item.item_id + '-' + opt.key;
      var $row = $('<div>', { 'class': 'enp-psy__choice-opt' });
      $row.append($('<input>', { type: 'radio', name: 'q_' + item.item_id, id: id, value: opt.key }));
      $row.append($('<label>', { 'for': id, 'class': 'enp-psy__choice-label', text: opt.text }));
      $wrap.append($row);
    });
    return $wrap;
  }

  // ── MCQ ──────────────────────────────────────────────────────────────────

  function renderMCQ(item) {
    var $wrap = $('<div>', { 'class': 'enp-psy__mcq' });
    var opts = [];
    if (item.option_a) opts.push({ key: 'A', text: item.option_a });
    if (item.option_b) opts.push({ key: 'B', text: item.option_b });
    if (item.option_c) opts.push({ key: 'C', text: item.option_c });
    if (item.option_d) opts.push({ key: 'D', text: item.option_d });
    opts.forEach(function (opt) {
      var id = 'mcq-' + item.item_id + '-' + opt.key;
      var $row = $('<div>', { 'class': 'enp-psy__choice-opt' });
      $row.append($('<input>', { type: 'radio', name: 'q_' + item.item_id, id: id, value: opt.text }));
      $row.append($('<label>', { 'for': id, 'class': 'enp-psy__choice-label', text: opt.text }));
      $wrap.append($row);
    });
    return $wrap;
  }

  // ── Rank ──────────────────────────────────────────────────────────────────

  function renderRankInput(item) {
    // Numeric fallback input (mobile-friendly; drag-and-drop layered on top for desktop).
    var $wrap = $('<div>', { 'class': 'enp-psy__rank-item', 'data-item-id': item.item_id, draggable: true });
    $wrap.append($('<span>', { 'class': 'enp-psy__rank-handle', html: '&#8597;', 'aria-hidden': 'true' }));
    $wrap.append($('<span>', { 'class': 'enp-psy__rank-num', text: '—' }));
    $wrap.append($('<span>', { 'class': 'enp-psy__rank-text', text: item.question_text }));
    var $numInput = $('<input>', {
      type: 'number', 'class': 'enp-psy__rank-fallback-input',
      min: 1, placeholder: '#',
      'data-item-id': item.item_id,
      'aria-label': 'Rank for: ' + item.question_text
    });
    $wrap.append($numInput);
    return $wrap;
  }

  function initRankDnd(secNum) {
    var $list = $('#enp-psy-questions');
    var dragSrc = null;

    $list.find('.enp-psy__rank-item').each(function () {
      this.addEventListener('dragstart', function (e) {
        dragSrc = this;
        e.dataTransfer.effectAllowed = 'move';
        $(this).addClass('dragging');
      });
      this.addEventListener('dragend', function () {
        $(this).removeClass('dragging');
        $list.find('.enp-psy__rank-item').removeClass('drag-over');
        updateRankNumbers(secNum);
      });
      this.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        $list.find('.enp-psy__rank-item').removeClass('drag-over');
        $(this).addClass('drag-over');
      });
      this.addEventListener('drop', function (e) {
        e.preventDefault();
        if (dragSrc !== this) {
          var $src  = $(dragSrc);
          var $tgt  = $(this);
          var srcIdx = $src.index();
          var tgtIdx = $tgt.index();
          if (srcIdx < tgtIdx) { $tgt.after($src); } else { $tgt.before($src); }
        }
      });
    });

    // Fallback numeric inputs.
    $list.on('change', '.enp-psy__rank-fallback-input', function () {
      syncRankFromInputs(secNum);
    });

    updateRankNumbers(secNum);
  }

  function updateRankNumbers(secNum) {
    $('#enp-psy-questions .enp-psy__rank-item').each(function (i) {
      $(this).find('.enp-psy__rank-num').text(i + 1);
      var itemId = $(this).data('item-id');
      if (answers[itemId] === undefined) {
        answers[itemId] = i + 1;
      }
    });
    serializeRankAnswers(secNum);
  }

  function syncRankFromInputs(secNum) {
    $('#enp-psy-questions .enp-psy__rank-item').each(function () {
      var itemId = $(this).data('item-id');
      var val    = parseInt($(this).find('.enp-psy__rank-fallback-input').val(), 10);
      if (!isNaN(val) && val > 0) {
        answers[itemId] = val;
      }
    });
    serializeRankAnswers(secNum);
  }

  function serializeRankAnswers(secNum) {
    var items = paper[secNum] || [];
    items.forEach(function (item) {
      var $el = $('#enp-psy-questions .enp-psy__rank-item[data-item-id="' + item.item_id + '"]');
      if (!$el.length) return;
      var rank = $el.index() + 1;
      answers[item.item_id] = rank;
    });
  }

  // ── Open text ────────────────────────────────────────────────────────────

  function renderOpen(item) {
    var $wrap = $('<div>', { 'class': 'enp-psy__open' });
    $wrap.append($('<textarea>', {
      'class': 'enp-psy__textarea',
      name: 'q_' + item.item_id,
      rows: 5,
      placeholder: 'Your answer…',
      'data-item-id': item.item_id
    }));
    return $wrap;
  }

  // ── Restore saved answers ─────────────────────────────────────────────────

  function restoreAnswers(secNum, items) {
    items.forEach(function (item) {
      var saved = answers[item.item_id];
      if (saved === undefined) return;
      var type = item.type;
      if (type === 'likert' || type === 'forced_choice' || type === 'mcq') {
        $('input[name="q_' + item.item_id + '"][value="' + saved + '"]').prop('checked', true);
      } else if (type === 'open') {
        $('textarea[data-item-id="' + item.item_id + '"]').val(saved);
      }
    });
  }

  // ── Collect answers for current section ──────────────────────────────────

  function collectSectionAnswers(secNum) {
    var items    = paper[secNum] || [];
    var secAnswers = {};
    items.forEach(function (item) {
      var type = item.type;
      if (type === 'likert' || type === 'forced_choice' || type === 'mcq') {
        var val = $('input[name="q_' + item.item_id + '"]:checked').val();
        if (val !== undefined) {
          answers[item.item_id] = val;
          secAnswers[item.item_id] = val;
        }
      } else if (type === 'rank') {
        var rank = answers[item.item_id];
        if (rank !== undefined) secAnswers[item.item_id] = rank;
      } else if (type === 'open') {
        var txt = $('textarea[data-item-id="' + item.item_id + '"]').val().trim();
        if (txt) {
          answers[item.item_id] = txt;
          secAnswers[item.item_id] = txt;
        }
      }
    });
    return secAnswers;
  }

  // ── Completeness check ────────────────────────────────────────────────────

  function isSectionComplete(secNum) {
    var items = paper[secNum] || [];
    return items.every(function (item) {
      var type = item.type;
      if (type === 'open') return true; // open text is optional
      if (type === 'rank') return answers[item.item_id] !== undefined;
      return answers[item.item_id] !== undefined;
    });
  }

  // ── Autosave ──────────────────────────────────────────────────────────────

  function autosave(secNum, secAnswers, callback) {
    if ($.isEmptyObject(secAnswers)) { if (callback) callback(); return; }
    setAutosave('saving');
    $.post(AJAX, {
      action:  'enp_psy_autosave',
      nonce:   NONCE,
      token:   TOKEN,
      section: secNum,
      answers: secAnswers
    }, function (res) {
      setAutosave(res.success ? 'saved' : '', '');
      if (callback) callback();
    }).fail(function () { setAutosave('', ''); if (callback) callback(); });
  }

  // ── Next / Back ───────────────────────────────────────────────────────────

  $('#enp-psy-next-btn').on('click', function () {
    clearError('enp-psy-runner-error');
    var secNum     = sectionOrder[currentSectionIdx];
    var secAnswers = collectSectionAnswers(secNum);

    // Client-side completeness check (except open text).
    var items     = paper[secNum] || [];
    var mandatory = items.filter(function (i) { return i.type !== 'open'; });
    var missing   = mandatory.filter(function (i) { return secAnswers[i.item_id] === undefined; });

    if (missing.length > 0) {
      showError('enp-psy-runner-error', 'Please answer all questions before continuing.');
      // Highlight first unanswered.
      var $first = $('[data-item-id="' + missing[0].item_id + '"]');
      if ($first.length) {
        $('html,body').animate({ scrollTop: $first.offset().top - 80 }, 250);
      }
      return;
    }

    var $btn = $(this).prop('disabled', true);
    autosave(secNum, secAnswers, function () {
      $btn.prop('disabled', false);
      if (currentSectionIdx < sectionOrder.length - 1) {
        currentSectionIdx++;
        renderSection(currentSectionIdx);
      } else {
        showReview();
      }
    });
  });

  $('#enp-psy-back-btn').on('click', function () {
    if (currentSectionIdx > 0) {
      var secNum     = sectionOrder[currentSectionIdx];
      var secAnswers = collectSectionAnswers(secNum);
      autosave(secNum, secAnswers, function () {
        currentSectionIdx--;
        renderSection(currentSectionIdx);
      });
    }
  });

  // ── Review screen ─────────────────────────────────────────────────────────

  function showReview() {
    var $sum = $('#enp-psy-review-summary').empty();
    sectionOrder.forEach(function (secNum) {
      var meta   = sectionMeta[secNum] || {};
      var items  = paper[secNum] || [];
      var done   = items.filter(function (i) { return answers[i.item_id] !== undefined; }).length;
      var isDone = done === items.length;
      var $row   = $('<div>', { 'class': 'enp-psy__review-row' });
      $row.append($('<span>', { 'class': 'enp-psy__review-row-name', text: meta.label || 'Section ' + secNum }));
      $row.append($('<span>', {
        'class': 'enp-psy__review-badge ' + (isDone ? 'enp-psy__review-badge--done' : 'enp-psy__review-badge--partial'),
        text: isDone ? 'Complete' : done + '/' + items.length
      }));
      $sum.append($row);
    });
    showScreen('enp-psy-review');
  }

  $('#enp-psy-back-to-runner').on('click', function () {
    showScreen('enp-psy-runner');
    renderSection(currentSectionIdx);
  });

  // ── Submit ────────────────────────────────────────────────────────────────

  $('#enp-psy-submit-btn').on('click', function () {
    clearError('enp-psy-submit-error');
    var $btn = $(this).prop('disabled', true).text('Submitting…');

    $.post(AJAX, {
      action: 'enp_psy_submit',
      nonce:  NONCE,
      token:  TOKEN
    }, function (res) {
      $btn.prop('disabled', false).text('Submit Assessment');
      if (!res.success) {
        showError('enp-psy-submit-error', res.data || 'Submission failed. Please try again.');
        return;
      }
      // res.data is only {status:"ok"} — never contains scores.
      showScreen('enp-psy-thankyou');
    }).fail(function () {
      $btn.prop('disabled', false).text('Submit Assessment');
      showError('enp-psy-submit-error', 'Network error. Please check your connection and try again.');
    });
  });

  // ── Kick off ──────────────────────────────────────────────────────────────

  $(document).ready(function () { init(); });

}(jQuery));
