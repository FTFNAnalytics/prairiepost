/* The Prairie Dispatch — the Stories list's bulk bar. The form works
   without this script (the server checks every story); this adds the
   running count, select-all, shift-click ranges, an Apply that waits for
   something to apply, and a confirmation that says what will happen.
   Each row's checkbox value is the status the list showed for it. */
(function () {
  var form = document.getElementById('bulk');
  if (!form) return;
  var all = document.getElementById('bulk-all');
  var count = document.getElementById('bulk-count');
  var select = form.querySelector('select[name="new_status"]');
  var apply = form.querySelector('button[type="submit"]');
  var boxes = Array.prototype.slice.call(
    document.querySelectorAll('input[type="checkbox"][form="bulk"][name^="stories["]'));
  var hint = count ? count.textContent : '';
  var last = null;

  function ticked() {
    return boxes.filter(function (b) { return b.checked; });
  }

  function refresh() {
    var n = ticked().length;
    if (count) count.textContent = n ? n + ' selected' : hint;
    if (all) {
      all.checked = n > 0 && n === boxes.length;
      all.indeterminate = n > 0 && n < boxes.length;
    }
    if (apply) apply.disabled = !n || !select.value;
    boxes.forEach(function (b) {
      var row = b.closest('tr');
      if (row) row.classList.toggle('is-selected', b.checked);
    });
  }

  if (all) {
    all.addEventListener('change', function () {
      boxes.forEach(function (b) { b.checked = all.checked; });
      refresh();
    });
  }

  boxes.forEach(function (b) {
    b.addEventListener('click', function (ev) {
      // Shift-click ticks (or clears) every box between this one and the last.
      if (ev.shiftKey && last && last !== b) {
        var from = boxes.indexOf(last), to = boxes.indexOf(b);
        for (var i = Math.min(from, to); i <= Math.max(from, to); i++) boxes[i].checked = b.checked;
      }
      last = b;
      refresh();
    });
  });
  select.addEventListener('change', refresh);
  // Back/forward restores ticked boxes from the page cache; recount them.
  window.addEventListener('pageshow', refresh);

  function already(target, k) {
    if (target === 'draft') return k === 1 ? 'a draft' : 'drafts';
    return target === 'in_review' ? 'in review' : 'published';
  }

  form.addEventListener('submit', function (ev) {
    var target = select.value;
    var picked = ticked();
    if (!picked.length || !target) { ev.preventDefault(); refresh(); return; }
    var moving = picked.filter(function (b) { return b.value !== target; });
    if (!moving.length) {
      ev.preventDefault();
      window.alert('Nothing to change: the selected ' + (picked.length === 1 ? 'story is' : 'stories are')
        + ' already ' + already(target, picked.length) + '.');
      return;
    }
    var n = moving.length;
    var noun = n === 1 ? 'story' : 'stories';
    var msg = target === 'published'
      ? 'Publish ' + n + ' ' + noun + ' now? ' + (n === 1 ? 'It goes' : 'They go') + ' live on the site immediately.'
      : (target === 'in_review' ? 'Send ' + n + ' ' + noun + ' to review?' : 'Move ' + n + ' ' + noun + ' to draft?');
    if (target !== 'published') {
      var of = function (k) { return k === n ? (n === 1 ? 'It is' : 'They are') : k + ' of them ' + (k === 1 ? 'is' : 'are'); };
      var pub = moving.filter(function (b) { return b.value === 'published'; }).length;
      var sch = moving.filter(function (b) { return b.value === 'scheduled'; }).length;
      if (pub) msg += ' ' + of(pub) + ' live and will come off the site.';
      if (sch) msg += ' ' + of(sch) + ' scheduled and will not go live.';
    }
    var same = picked.length - n;
    if (same) {
      msg += ' (' + same + (same === 1 ? ' is' : ' are') + ' already ' + already(target, same)
        + (same === 1 ? ' and stays as it is.)' : ' and stay as they are.)');
    }
    if (!window.confirm(msg)) ev.preventDefault();
  });

  refresh();
})();
