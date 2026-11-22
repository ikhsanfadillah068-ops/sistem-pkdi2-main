(function () {
  'use strict';

  document.querySelectorAll('[data-date-range]').forEach(function (root) {
    var from = root.querySelector('[data-range-from]');
    var to = root.querySelector('[data-range-to]');
    var status = root.querySelector('[data-range-status]');
    var presets = Array.from(root.querySelectorAll('[data-range-days]'));
    function dateValue(date) {
      return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    }
    function update() {
      var invalid = Boolean(from.value && to.value && from.value > to.value);
      [from, to].forEach(function (input) {
        input.setAttribute('aria-invalid', String(invalid));
        if (input._flatpickr && input._flatpickr.altInput) input._flatpickr.altInput.setAttribute('aria-invalid', String(invalid));
      });
      status.className = invalid ? 'field-error' : 'field-help';
      status.textContent = invalid ? 'Tanggal awal tidak boleh setelah tanggal akhir.' :
        from.value && to.value ? 'Periode dipilih: ' + from.value + ' hingga ' + to.value + '.' :
        'Pilih tanggal atau gunakan tombol cepat di atas.';
    }
    presets.forEach(function (button) {
      button.addEventListener('click', function () {
        var last = new Date(), first = new Date(last);
        first.setDate(last.getDate() - Number(button.dataset.rangeDays) + 1);
        if (from._flatpickr) from._flatpickr.setDate(dateValue(first), false);
        else from.value = dateValue(first);
        if (to._flatpickr) to._flatpickr.setDate(dateValue(last), false);
        else to.value = dateValue(last);
        presets.forEach(function (item) { item.setAttribute('aria-pressed', String(item === button)); });
        update();
      });
    });
    var clearBtn = root.querySelector('[data-range-clear]');
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        if (from._flatpickr) from._flatpickr.clear(false); else from.value = '';
        if (to._flatpickr) to._flatpickr.clear(false); else to.value = '';
        presets.forEach(function (button) { button.setAttribute('aria-pressed', 'false'); });
        update();
        (from._flatpickr && from._flatpickr.altInput ? from._flatpickr.altInput : from).focus();
      });
    }
    [from, to].forEach(function (input) {
      input.addEventListener('change', function () {
        presets.forEach(function (button) { button.setAttribute('aria-pressed', 'false'); });
        update();
      });
    });
  });

  document.querySelectorAll('[data-file-preview]').forEach(function (root) {
    var input = root.querySelector('[data-file-input]');
    var drop = root.querySelector('[data-file-drop]');
    var result = root.querySelector('[data-file-result]');
    var image = root.querySelector('[data-file-image]');
    var icon = root.querySelector('[data-file-icon]');
    var error = root.querySelector('[data-file-error]');
    var objectUrl = null;
    function clear(resetInput) {
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = null;
      if (resetInput !== false) input.value = '';
      image.removeAttribute('src');
      result.hidden = true; error.hidden = true;
    }
    function show(file) {
      clear(false);
      if (!file) return;
      var validType = ['image/png', 'image/jpeg', 'application/pdf'].includes(file.type);
      var maxBytes = Number(root.dataset.maxMb || 5) * 1024 * 1024;
      if (!validType || file.size > maxBytes) {
        error.textContent = !validType ? 'Gunakan berkas PNG, JPG, atau PDF.' : 'Ukuran berkas melebihi batas ' + root.dataset.maxMb + ' MB.';
        error.hidden = false; input.value = ''; return;
      }
      root.querySelector('[data-file-name]').textContent = file.name;
      root.querySelector('[data-file-size]').textContent = (file.size / 1024 / 1024).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' MB (pratinjau lokal)';
      image.hidden = !file.type.startsWith('image/');
      icon.hidden = !image.hidden;
      if (!image.hidden) { objectUrl = URL.createObjectURL(file); image.src = objectUrl; }
      result.hidden = false;
    }
    input.addEventListener('change', function () { show(input.files[0]); });
    root.querySelector('[data-file-remove]').addEventListener('click', function () { clear(); });
    drop.addEventListener('dragover', function (event) { event.preventDefault(); drop.classList.add('is-dragging'); });
    drop.addEventListener('dragleave', function () { drop.classList.remove('is-dragging'); });
    drop.addEventListener('drop', function (event) {
      event.preventDefault(); drop.classList.remove('is-dragging');
      if (!event.dataTransfer.files.length) return;
      input.files = event.dataTransfer.files;
      show(input.files[0]);
    });
  });
})();
