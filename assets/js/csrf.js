/**
 * CSRF auto-attach. Reads <meta name="csrf-token"> and adds an X-CSRF-Token
 * header to every same-origin state-changing fetch()/XMLHttpRequest, so existing
 * AJAX code needs no changes. Load this NON-deferred in <head>, after the meta
 * tag, so it patches the network APIs before any request fires.
 */
(function () {
  function token() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') || '' : '';
  }
  function sameOrigin(url) {
    try { return new URL(url, location.href).origin === location.origin; }
    catch (e) { return true; } // relative URL → same origin
  }
  var MUT = /^(POST|PUT|PATCH|DELETE)$/i;

  // --- fetch ---
  if (window.fetch) {
    var _fetch = window.fetch;
    window.fetch = function (input, init) {
      init = init || {};
      var method = init.method
        || (input && typeof input !== 'string' && input.method)
        || 'GET';
      var url = (typeof input === 'string') ? input : (input && input.url) || '';
      if (MUT.test(method) && sameOrigin(url)) {
        var headers = new Headers(
          init.headers || (input && typeof input !== 'string' && input.headers) || {}
        );
        if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token());
        init.headers = headers;
      }
      return _fetch.call(this, input, init);
    };
  }

  // --- XMLHttpRequest ---
  var _open = XMLHttpRequest.prototype.open;
  var _send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (method, url) {
    this.__csrfNeeded = MUT.test(method || '') && sameOrigin(url || '');
    return _open.apply(this, arguments);
  };
  XMLHttpRequest.prototype.send = function () {
    if (this.__csrfNeeded) {
      try { this.setRequestHeader('X-CSRF-Token', token()); } catch (e) {}
    }
    return _send.apply(this, arguments);
  };
})();
