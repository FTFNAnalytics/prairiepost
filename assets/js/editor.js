/* The Prairie Post — story editor. Small on purpose: a toolbar, a sync, an uploader. */
(function () {
  var editor = document.getElementById('editor');
  var field = document.getElementById('bodyfield');
  var form = document.getElementById('storyform');
  if (!editor || !field || !form) return;

  function sync() { field.value = editor.innerHTML; }
  editor.addEventListener('input', sync);
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
      }
      editor.focus();
      sync();
    });
  });

  // Paste as clean text — wire copy arrives full of foreign markup.
  editor.addEventListener('paste', function (ev) {
    ev.preventDefault();
    var text = (ev.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, text);
  });

  var upload = document.getElementById('imgupload');
  var imageField = document.getElementById('image');
  if (upload && imageField) {
    upload.addEventListener('change', function () {
      if (!upload.files.length) return;
      var data = new FormData();
      data.append('file', upload.files[0]);
      data.append('csrf', form.querySelector('input[name=csrf]').value);
      fetch('upload.php', { method: 'POST', body: data })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json.url) {
            imageField.value = json.url;
          } else {
            alert(json.error || "The upload didn't go through. Check the file is an image under 8 MB and try again.");
          }
        })
        .catch(function () {
          alert("The upload didn't go through — the connection dropped. Try again.");
        });
    });
  }
})();
