/**
 * Feedora client-side image compressor.
 *
 * Converts user-selected images to WebP (quality 0.8) and downscales
 * high-resolution images using the HTML5 Canvas API, before the form is
 * submitted. Videos and non-image files are left untouched.
 *
 * Usage:
 *   1) Automatic: any <input type="file"> that accepts images and is NOT
 *      marked data-no-compress is auto-attached on DOMContentLoaded.
 *   2) Manual:  FeedoraCompressor.attach(inputEl, { quality: 0.8, maxDimension: 1600 });
 *
 * The compressor swaps the input's FileList with the compressed File using a
 * DataTransfer object, so existing form-submit / FormData logic keeps working.
 */
(function (global) {
  'use strict';

  var DEFAULTS = {
    quality: 0.8,        // WebP quality factor
    maxDimension: 1600,  // longest edge in px; larger images are scaled down
    maxBytes: 1.5 * 1024 * 1024, // only bother compressing files above this size? no — always compress images
    mimeType: 'image/webp'
  };

  function isImage(file) {
    return file && typeof file.type === 'string' && file.type.indexOf('image/') === 0
      && file.type !== 'image/gif'; // keep GIFs (animation) intact
  }

  function loadImage(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = function (e) { URL.revokeObjectURL(url); reject(e); };
      img.src = url;
    });
  }

  /**
   * Compress a single File. Resolves with a new File (WebP) or the original
   * file if compression is not applicable or fails.
   */
  function compressFile(file, opts) {
    opts = Object.assign({}, DEFAULTS, opts || {});
    if (!isImage(file)) return Promise.resolve(file);

    return loadImage(file).then(function (img) {
      var w = img.naturalWidth || img.width;
      var h = img.naturalHeight || img.height;
      var scale = Math.min(1, opts.maxDimension / Math.max(w, h));
      var targetW = Math.round(w * scale);
      var targetH = Math.round(h * scale);

      var canvas = document.createElement('canvas');
      canvas.width = targetW;
      canvas.height = targetH;
      var ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0, targetW, targetH);

      return new Promise(function (resolve) {
        canvas.toBlob(function (blob) {
          if (!blob) { resolve(file); return; } // toBlob unsupported -> keep original
          // If WebP somehow grew the file, keep the smaller original.
          if (blob.size >= file.size && scale === 1) { resolve(file); return; }
          var newName = (file.name || 'image').replace(/\.[^.]+$/, '') + '.webp';
          var out = new File([blob], newName, { type: opts.mimeType, lastModified: Date.now() });
          resolve(out);
        }, opts.mimeType, opts.quality);
      });
    }).catch(function () {
      return file; // decoding failed -> keep original, never block the upload
    });
  }

  function replaceInputFile(input, file) {
    try {
      var dt = new DataTransfer();
      dt.items.add(file);
      input.files = dt.files;
      return true;
    } catch (e) {
      return false; // DataTransfer unsupported -> leave original FileList
    }
  }

  /**
   * Attach the compressor to a file input. On change, the selected image is
   * compressed and written back into the input.
   */
  function attach(input, opts) {
    if (!input || input.__feedoraCompress) return;
    input.__feedoraCompress = true;
    input.addEventListener('change', function () {
      if (!input.files || !input.files.length) return;
      var file = input.files[0];
      if (!isImage(file)) return;
      compressFile(file, opts).then(function (out) {
        if (out !== file) replaceInputFile(input, out);
        input.dispatchEvent(new CustomEvent('feedora:compressed', { detail: { file: out }, bubbles: true }));
      });
    });
  }

  function autoInit() {
    var inputs = document.querySelectorAll('input[type="file"]');
    Array.prototype.forEach.call(inputs, function (input) {
      if (input.hasAttribute('data-no-compress')) return;
      var accept = (input.getAttribute('accept') || '').toLowerCase();
      // Attach when it accepts images or has no restriction at all.
      if (accept === '' || accept.indexOf('image') !== -1) attach(input);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoInit);
  } else {
    autoInit();
  }

  global.FeedoraCompressor = { attach: attach, compressFile: compressFile, autoInit: autoInit };
})(window);
