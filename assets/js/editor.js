/* The Prairie Dispatch — story editor. Small on purpose: a toolbar, a sync,
   an uploader, a word count, and an autosave that respects the workflow. */
(function () {
  var editor = document.getElementById('editor');
  var field = document.getElementById('bodyfield');
  var form = document.getElementById('storyform');
  if (!editor || !field || !form) return;

  var dirty = false;
  function sync() { field.value = editor.innerHTML; }
  editor.addEventListener('input', function () { dirty = true; sync(); countWords(); });
  form.addEventListener('submit', sync);

  document.querySelectorAll('.edtoolbar button').forEach(function (btn) {
    btn.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
    btn.addEventListener('click', function () {
      if (btn.dataset.cmd) {
        document.execCommand(btn.dataset.cmd, false, null);
      } else if (btn.dataset.block) {
        var tag = btn.dataset.block;
        var current = document.queryCommandValue('formatBlock').toLowerCase();
        document.execCommand('formatBlock', false, current === tag ? 'p' : tag);
      } else if (btn.dataset.link) {
        var url = window.prompt('Link address (https://…):');
        if (url) document.execCommand('createLink', false, url);
      } else if (btn.dataset.bodyimg) {
        var picker = document.getElementById('bodyimgupload');
        if (picker) picker.click();
        return; // focus returns after the upload lands
      }
      editor.focus();
      dirty = true;
      sync();
    });
  });

  // Paste as clean text — wire copy arrives full of foreign markup.
  editor.addEventListener('paste', function (ev) {
    ev.preventDefault();
    var text = (ev.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
  });

  function uploadImage(file, done, fail) {
    var data = new FormData();
    data.append('file', file);
    data.append('csrf', form.querySelector('input[name=csrf]').value);
    fetch('upload.php', { method: 'POST', body: data })
      .then(function (r) { return r.json(); })
      .then(function (json) { json.url ? done(json.url) : fail(json.error); })
      .catch(function () { fail(null); });
  }
  function uploadFailed(message) {
    alert(message || "The upload didn't go through. Check the file is an image under 8 MB and try again.");
  }

  // Featured image field.
  var upload = document.getElementById('imgupload');
  var imageField = document.getElementById('image');
  if (upload && imageField) {
    upload.addEventListener('change', function () {
      if (!upload.files.length) return;
      uploadImage(upload.files[0], function (url) { imageField.value = url; }, uploadFailed);
    });
  }

  // Images inside the story text.
  var bodyUpload = document.getElementById('bodyimgupload');
  if (bodyUpload) {
    bodyUpload.addEventListener('change', function () {
      if (!bodyUpload.files.length) return;
      uploadImage(bodyUpload.files[0], function (url) {
        editor.focus();
        document.execCommand('insertHTML', false,
          '<figure><img src="' + url + '" alt=""><figcaption>Caption — credit</figcaption></figure><p></p>');
        bodyUpload.value = '';
        dirty = true;
        sync();
      }, uploadFailed);
    });
  }

  // Word count, in the dateline register.
  var counter = document.getElementById('wordcount');
  function countWords() {
    if (!counter) return;
    var words = (editor.innerText || '').trim().split(/\s+/).filter(Boolean).length;
    counter.textContent = words + (words === 1 ? ' word' : ' words');
  }
  countWords();

  // Autosave: existing drafts and stories in review, every 30 seconds.
  var note = document.getElementById('autosavenote');
  var postId = parseInt(form.dataset.postId || '0', 10);
  var statusSel = document.getElementById('status');
  function autosavable() {
    return postId > 0 && statusSel && (statusSel.value === 'draft' || statusSel.value === 'in_review');
  }
  setInterval(function () {
    if (!dirty || !autosavable()) return;
    var data = new FormData();
    data.append('csrf', form.querySelector('input[name=csrf]').value);
    data.append('id', String(postId));
    data.append('title', (document.getElementById('title') || {}).value || '');
    data.append('lede', (document.getElementById('lede') || {}).value || '');
    data.append('body', editor.innerHTML);
    fetch('autosave.php', { method: 'POST', body: data })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json.saved && note) { note.textContent = 'Draft autosaved ' + json.saved; dirty = false; }
      })
      .catch(function () { /* the next tick tries again */ });
  }, 30000);
})();
