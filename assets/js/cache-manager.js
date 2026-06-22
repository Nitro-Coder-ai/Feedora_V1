/**
 * Feedora browser image cache.
 *
 * Caches remote/static image assets (avatars, post images, banners, recipe
 * photos) in IndexedDB so repeat views and page transitions render instantly
 * without re-downloading. localStorage is intentionally NOT used for the binary
 * payload (≈5MB quota is too small for images); only a tiny index could live
 * there. Each entry stores the image Blob + a timestamp and expires after 1h.
 *
 * Strategy:
 *   - On DOMContentLoaded, scan <img> elements that look cacheable
 *     (Cloudinary URLs, local uploads/, or images marked data-cache).
 *   - If a fresh (<1h) cached Blob exists, swap the src to an object URL.
 *   - Otherwise fetch the image, store the Blob, and keep using the network src.
 *   - Cross-origin fetches that fail (CORS, offline) are swallowed; the original
 *     src is always left working. The browser HTTP cache remains the fallback.
 *
 * Public API: window.FeedoraCache.{ init, cacheImage, get, put, purgeExpired }.
 */
(function (global) {
  'use strict';

  var DB_NAME = 'feedora-cache';
  var STORE = 'images';
  var TTL_MS = 60 * 60 * 1000; // 1 hour
  var dbPromise = null;

  function idbSupported() {
    return typeof indexedDB !== 'undefined';
  }

  function openDB() {
    if (dbPromise) return dbPromise;
    dbPromise = new Promise(function (resolve, reject) {
      var req = indexedDB.open(DB_NAME, 1);
      req.onupgradeneeded = function () {
        var db = req.result;
        if (!db.objectStoreNames.contains(STORE)) {
          db.createObjectStore(STORE, { keyPath: 'url' });
        }
      };
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
    return dbPromise;
  }

  function tx(mode) {
    return openDB().then(function (db) {
      return db.transaction(STORE, mode).objectStore(STORE);
    });
  }

  /** Read a cached entry; resolves null if missing or expired (expired is deleted). */
  function get(url) {
    if (!idbSupported()) return Promise.resolve(null);
    return tx('readonly').then(function (store) {
      return new Promise(function (resolve) {
        var req = store.get(url);
        req.onsuccess = function () {
          var rec = req.result;
          if (!rec) { resolve(null); return; }
          if (Date.now() - rec.ts > TTL_MS) {
            del(url); // stale -> evict
            resolve(null);
            return;
          }
          resolve(rec);
        };
        req.onerror = function () { resolve(null); };
      });
    }).catch(function () { return null; });
  }

  function put(url, blob, contentType) {
    if (!idbSupported()) return Promise.resolve(false);
    return tx('readwrite').then(function (store) {
      return new Promise(function (resolve) {
        var req = store.put({ url: url, blob: blob, contentType: contentType || blob.type, ts: Date.now() });
        req.onsuccess = function () { resolve(true); };
        req.onerror = function () { resolve(false); };
      });
    }).catch(function () { return false; });
  }

  function del(url) {
    if (!idbSupported()) return Promise.resolve();
    return tx('readwrite').then(function (store) { store.delete(url); }).catch(function () {});
  }

  /** Remove all expired entries (housekeeping). */
  function purgeExpired() {
    if (!idbSupported()) return Promise.resolve(0);
    return tx('readwrite').then(function (store) {
      return new Promise(function (resolve) {
        var removed = 0;
        var req = store.openCursor();
        req.onsuccess = function () {
          var cursor = req.result;
          if (!cursor) { resolve(removed); return; }
          if (Date.now() - cursor.value.ts > TTL_MS) { cursor.delete(); removed++; }
          cursor.continue();
        };
        req.onerror = function () { resolve(removed); };
      });
    }).catch(function () { return 0; });
  }

  function isCacheableSrc(src) {
    if (!src) return false;
    if (src.indexOf('data:') === 0 || src.indexOf('blob:') === 0) return false;
    if (/\.svg(\?|$)/i.test(src)) return false; // tiny, no benefit
    return /res\.cloudinary\.com/.test(src)
        || /\/?uploads\//.test(src)
        || /\/?images\//.test(src);
  }

  /** Cache a single <img>: swap to cached Blob if fresh, else fetch+store. */
  function cacheImage(img) {
    if (!img || img.__feedoraCached) return;
    var src = img.currentSrc || img.src;
    var cacheable = img.hasAttribute('data-cache') || isCacheableSrc(src);
    if (!cacheable) return;
    img.__feedoraCached = true;

    get(src).then(function (rec) {
      if (rec && rec.blob) {
        try {
          img.src = URL.createObjectURL(rec.blob);
          img.setAttribute('data-cache-hit', '1');
          return;
        } catch (e) { /* fall through to network */ }
      }
      // Miss: fetch and store without disturbing the visible (network) image.
      fetch(src, { mode: 'cors', credentials: 'omit' })
        .then(function (resp) { return resp.ok ? resp.blob() : null; })
        .then(function (blob) { if (blob) put(src, blob, blob.type); })
        .catch(function () { /* CORS/offline -> keep network src */ });
    });
  }

  function init() {
    if (!idbSupported()) {
      if (global.console) console.log('[FeedoraCache] IndexedDB unavailable; relying on HTTP cache.');
      return;
    }
    purgeExpired();
    var imgs = document.querySelectorAll('img');
    var hits = 0;
    Array.prototype.forEach.call(imgs, function (img) {
      if (img.hasAttribute('data-no-cache')) return;
      cacheImage(img);
    });
    // Observe images added later (infinite scroll, modals, AJAX feeds).
    if ('MutationObserver' in global) {
      new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
          Array.prototype.forEach.call(m.addedNodes, function (node) {
            if (node.nodeType !== 1) return;
            if (node.tagName === 'IMG') cacheImage(node);
            else if (node.querySelectorAll) {
              Array.prototype.forEach.call(node.querySelectorAll('img'), cacheImage);
            }
          });
        });
      }).observe(document.body, { childList: true, subtree: true });
    }
    if (global.console) console.log('[FeedoraCache] Initialized image cache (1h TTL).');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  global.FeedoraCache = { init: init, cacheImage: cacheImage, get: get, put: put, purgeExpired: purgeExpired };
})(window);
