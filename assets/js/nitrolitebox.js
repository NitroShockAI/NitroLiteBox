/**
 * NitroLiteBox -- zero-dependency lightbox
 * Version: 1.1.0
 *
 * New in 1.1:
 *  - Pan & zoom: scroll-wheel, double-click, pinch-to-zoom, drag-to-pan
 *  - Class-based grouping: shared class "nlb-group-*" auto-groups images
 *  - rel="" grouping still works as before
 *
 * Exported as window.NitroLiteBox for theme/builder re-init hooks.
 */
(function () {
  'use strict';

  /* -- Config --------------------------------------------------------------- */
  const cfg = Object.assign({
    autoInit:         true,
    selector:         '',
    captionSource:    'auto',
    loop:             true,
    keyboard:         true,
    swipe:            true,
    zoomIn:           true,          // entrance scale animation
    zoom:             true,          // pan & zoom feature
    zoomMax:          4,             // max zoom level
    zoomStep:         0.4,           // scroll-wheel step
    groupClassPrefix: 'nlb-group-',  // class prefix for auto-grouping
    counterPos:       'top',
  }, window.nlbConfig || {});

  /* -- Regex ---------------------------------------------------------------- */
  const IMG_RE = /\.(jpe?g|png|gif|webp|avif|svg|bmp|tiff?)(\?.*)?$/i;

  /* -- State ---------------------------------------------------------------- */
  let items        = [];
  let idx          = 0;
  let overlay      = null;
  let stage        = null;
  let imgEl        = null;
  let captionEl    = null;
  let counterEl    = null;
  let zoomBtn      = null;
  let preloadCache = {};
  const boundLinks = new WeakSet(); // cache-safe - never written to the DOM

  // Pan & zoom
  let zoomScale  = 1;
  let panX = 0, panY = 0;
  let isZoomed   = false;
  let isDragging = false;
  let dragStartX = 0, dragStartY = 0;
  let panStartX  = 0, panStartY  = 0;
  let clickTimer = null; // single-click vs double-click delay

  // Touch
  let touchCount      = 0;
  let touchStartX     = 0, touchStartY = 0;
  let pinchStartDist  = 0, pinchStartScale = 1;
  let panTouchStartX  = 0, panTouchStartY  = 0;

  /* =========================================================================
   *  BUILD OVERLAY
   * ========================================================================= */
  function buildOverlay() {
    overlay = document.createElement('div');
    overlay.id = 'nlb-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Image lightbox');
    overlay.setAttribute('tabindex', '-1');

    // Add zoom-enabled class for CSS entrance animation
    if (cfg.zoomIn) overlay.classList.add('nlb-zoom-enabled');

    overlay.innerHTML = `
      <div id="nlb-backdrop"></div>
      <button id="nlb-close" aria-label="Close lightbox">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
      ${cfg.zoom ? `<button id="nlb-zoom-btn" aria-label="Zoom in">
        <svg id="nlb-icon-zoomin"  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          <line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>
        </svg>
        <svg id="nlb-icon-zoomout" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
          <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          <line x1="8" y1="11" x2="14" y2="11"/>
        </svg>
      </button>` : ''}
      <button id="nlb-prev" aria-label="Previous image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </button>
      <button id="nlb-next" aria-label="Next image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6"/>
        </svg>
      </button>
      <div id="nlb-stage">
        <div id="nlb-spinner" aria-hidden="true"><div class="nlb-spin-ring"></div></div>
        <img id="nlb-img" alt="" draggable="false">
        <div id="nlb-caption-wrap"><p id="nlb-caption"></p></div>
      </div>
      ${cfg.zoom ? '<div id="nlb-zoom-hint">ESC or 0 to reset zoom</div>' : ''}
      <div id="nlb-counter" aria-live="polite"></div>
    `;

    document.body.appendChild(overlay);

    stage     = overlay.querySelector('#nlb-stage');
    imgEl     = overlay.querySelector('#nlb-img');
    captionEl = overlay.querySelector('#nlb-caption');
    counterEl = overlay.querySelector('#nlb-counter');
    zoomBtn   = overlay.querySelector('#nlb-zoom-btn');

    if (cfg.counterPos === 'bottom') overlay.classList.add('nlb-counter-bottom');
    if (cfg.counterPos === 'none')   overlay.classList.add('nlb-counter-hidden');

    overlay.querySelector('#nlb-close').addEventListener('click', close);
    overlay.querySelector('#nlb-backdrop').addEventListener('click', onBackdropClick);
    overlay.querySelector('#nlb-prev').addEventListener('click', prev);
    overlay.querySelector('#nlb-next').addEventListener('click', next);
    if (zoomBtn) zoomBtn.addEventListener('click', toggleZoom);

    if (cfg.keyboard) document.addEventListener('keydown', onKey);

    // Click-to-navigate zones on image (left third = prev, right third = next)
    imgEl.addEventListener('click', onImgClick);
    imgEl.addEventListener('mousemove', onImgHover);

    if (cfg.zoom) {
      stage.addEventListener('wheel',      onWheel,      { passive: false });
      imgEl.addEventListener('dblclick',   onDblClick);
      imgEl.addEventListener('mousedown',  onMouseDown);
      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup',   onMouseUp);
    }

    overlay.addEventListener('touchstart', onTouchStart, { passive: true  });
    overlay.addEventListener('touchmove',  onTouchMove,  { passive: false });
    overlay.addEventListener('touchend',   onTouchEnd,   { passive: true  });
  }

  /* =========================================================================
   *  LINK COLLECTION & CLASS-BASED GROUPING
   * ========================================================================= */
  function collectLinks() {
    const found = new WeakSet();

    function handle(a) {
      if (found.has(a)) return;
      found.add(a);
      assignGroupFromClass(a);
      tagLink(a);
    }

    /* 1. Auto-detect linked images */
    if (cfg.autoInit) {
      document.querySelectorAll('a[href]').forEach(a => {
        if (IMG_RE.test(a.href) && a.querySelector('img')) handle(a);
      });
    }

    /* 2. Explicit data-nitrolitebox */
    document.querySelectorAll('a[data-nitrolitebox]').forEach(handle);

    /* 3. Extra selectors */
    if (cfg.selector) {
      cfg.selector.split(',').forEach(sel => {
        sel = sel.trim();
        if (!sel) return;
        document.querySelectorAll(sel).forEach(el => {
          const a = el.tagName === 'A' ? el : el.closest('a');
          if (a) handle(a);
        });
      });
    }

    /* 4. Class-based group scan */
    if (cfg.groupClassPrefix) {
      document.querySelectorAll(`a[href][class*="${cfg.groupClassPrefix}"]`).forEach(a => {
        if (IMG_RE.test(a.href)) handle(a);
      });
    }
  }

  /**
   * Promote the first matching nlb-group-* class to data-nlb-group
   * so the grouping query can find it uniformly.
   */
  function assignGroupFromClass(a) {
    if (a.dataset.nlbGroup || !cfg.groupClassPrefix) return;
    const cls = Array.from(a.classList).find(c => c.startsWith(cfg.groupClassPrefix));
    if (cls) a.dataset.nlbGroup = cls;
  }

  function tagLink(a) {
    if (boundLinks.has(a)) return;
    boundLinks.add(a);
    a.addEventListener('click', onLinkClick);
  }

  /* =========================================================================
   *  OPEN / CLOSE
   * ========================================================================= */
  function onLinkClick(e) {
    e.preventDefault();
    const a     = e.currentTarget;
    const group = a.dataset.nlbGroup || a.getAttribute('rel') || null;

    if (group) {
      const esc        = CSS.escape(group);
      const allInGroup = Array.from(
        document.querySelectorAll(
          `a[data-nlb-group="${esc}"],a[rel="${esc}"]`
        )
      ).filter(el => boundLinks.has(el));
      if (allInGroup.length > 1) {
        items = allInGroup.map(el => ({ src: el.href, caption: getCaption(el) }));
        idx   = Math.max(0, allInGroup.indexOf(a));
      } else {
        items = [{ src: a.href, caption: getCaption(a) }];
        idx   = 0;
      }
    } else {
      items = [{ src: a.href, caption: getCaption(a) }];
      idx   = 0;
    }

    if (!overlay) buildOverlay();
    open();
  }

  function open() {
    resetZoom(false);
    document.body.style.overflow = 'hidden';
    overlay.classList.add('nlb-visible');
    overlay.focus();
    updateNav();
    showImage(idx);
  }

  function close() {
    if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; }
    overlay.classList.remove('nlb-visible');
    document.body.style.overflow = '';
    imgEl.src = '';
    imgEl.classList.remove('nlb-loaded');
    resetZoom(false);
  }

  /* =========================================================================
   *  NAVIGATION
   * ========================================================================= */
  function next() {
    if (items.length < 2 || isZoomed) return;
    idx = cfg.loop ? (idx + 1) % items.length : Math.min(idx + 1, items.length - 1);
    showImage(idx);
  }

  function prev() {
    if (items.length < 2 || isZoomed) return;
    idx = cfg.loop ? (idx - 1 + items.length) % items.length : Math.max(idx - 1, 0);
    showImage(idx);
  }

  function updateNav() {
    const show = items.length > 1;
    overlay.querySelector('#nlb-prev').style.display = show ? '' : 'none';
    overlay.querySelector('#nlb-next').style.display = show ? '' : 'none';
  }

  /* =========================================================================
   *  SHOW IMAGE
   * ========================================================================= */
  function showImage(i) {
    const item = items[i];

    // Reset zoom on image change
    resetZoom(false);

    if (cfg.counterPos !== 'none' && items.length > 1) {
      counterEl.textContent   = (i + 1) + ' / ' + items.length;
      counterEl.style.display = '';
    } else {
      counterEl.style.display = 'none';
    }

    captionEl.textContent = item.caption || '';
    overlay.querySelector('#nlb-caption-wrap').style.display = item.caption ? '' : 'none';

    imgEl.classList.remove('nlb-loaded');
    overlay.querySelector('#nlb-spinner').classList.remove('nlb-hidden');

    const image   = new Image();
    image.onload  = () => {
      imgEl.src = item.src;
      imgEl.alt = item.caption || '';
      overlay.querySelector('#nlb-spinner').classList.add('nlb-hidden');
      imgEl.classList.add('nlb-loaded');
      // Set cursor for zoom capability
      if (cfg.zoom) imgEl.style.cursor = 'zoom-in';
    };
    image.onerror = () => {
      overlay.querySelector('#nlb-spinner').classList.add('nlb-hidden');
      imgEl.src = item.src;
      imgEl.classList.add('nlb-loaded');
    };
    image.src = item.src;

    preload(items[(i + 1) % items.length]?.src);
    preload(items[(i - 1 + items.length) % items.length]?.src);
  }

  function preload(src) {
    if (!src || preloadCache[src]) return;
    preloadCache[src] = true;
    (new Image()).src = src;
  }

  /* =========================================================================
   *  PAN & ZOOM
   * ========================================================================= */
  function applyTransform(animate) {
    imgEl.style.transition = animate
      ? 'transform 0.25s cubic-bezier(0.22,1,0.36,1)'
      : 'none';
    imgEl.style.transform =
      `scale(${zoomScale}) translate(${panX / zoomScale}px,${panY / zoomScale}px)`;
  }

  function clampPan() {
    if (zoomScale <= 1) { panX = 0; panY = 0; return; }
    const imgW = imgEl.offsetWidth  * zoomScale;
    const imgH = imgEl.offsetHeight * zoomScale;
    const vpW  = stage.offsetWidth;
    const vpH  = stage.offsetHeight;
    const maxX = Math.max(0, (imgW - vpW)  / 2);
    const maxY = Math.max(0, (imgH - vpH)  / 2);
    panX = Math.min(maxX, Math.max(-maxX, panX));
    panY = Math.min(maxY, Math.max(-maxY, panY));
  }

  function setZoom(newScale, originX, originY, animate) {
    newScale = Math.min(cfg.zoomMax, Math.max(1, newScale));

    if (originX !== undefined) {
      const rect   = imgEl.getBoundingClientRect();
      const relX   = originX - (rect.left + rect.width  / 2);
      const relY   = originY - (rect.top  + rect.height / 2);
      const factor = newScale / zoomScale;
      panX = (panX - relX) * factor + relX;
      panY = (panY - relY) * factor + relY;
    }

    zoomScale = newScale;
    isZoomed  = zoomScale > 1.01;
    clampPan();
    applyTransform(animate);
    syncZoomUI();
  }

  function resetZoom(animate) {
    zoomScale = 1; panX = 0; panY = 0; isZoomed = false;
    if (imgEl) { applyTransform(!!animate); syncZoomUI(); }
  }

  function toggleZoom() {
    isZoomed ? resetZoom(true) : setZoom(2, undefined, undefined, true);
  }

  function syncZoomUI() {
    overlay.classList.toggle('nlb-zoomed', isZoomed);
    // When zoomed: grab cursor for pan. When not zoomed: onImgHover sets zone cursors.
    if (isZoomed) imgEl.style.cursor = 'grab';
    if (!zoomBtn) return;
    zoomBtn.querySelector('#nlb-icon-zoomin').style.display  = isZoomed ? 'none' : '';
    zoomBtn.querySelector('#nlb-icon-zoomout').style.display = isZoomed ? '' : 'none';
    zoomBtn.setAttribute('aria-label', isZoomed ? 'Zoom out' : 'Zoom in');
  }

  /* -- Click-to-navigate zones (left/right thirds of image) ---------------- */
  function getImgZone(e) {
    const rect = imgEl.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    if (x < 0.33) return 'prev';
    if (x > 0.67) return 'next';
    return 'center';
  }

  function onImgClick(e) {
    if (isZoomed || !imgEl.classList.contains('nlb-loaded')) return;
    if (items.length < 2) return; // single image - no navigation zones

    if (cfg.zoom) {
      // Delay to distinguish single-click (navigate) from double-click (zoom)
      if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; return; }
      clickTimer = setTimeout(() => {
        clickTimer = null;
        const zone = getImgZone(e);
        if (zone === 'prev') prev();
        else if (zone === 'next') next();
      }, 250);
    } else {
      // No zoom - navigate immediately, no delay needed
      const zone = getImgZone(e);
      if (zone === 'prev') prev();
      else if (zone === 'next') next();
    }
  }

  function onImgHover(e) {
    if (isZoomed || !imgEl.classList.contains('nlb-loaded')) return;
    if (items.length < 2) {
      imgEl.style.cursor = cfg.zoom ? 'zoom-in' : 'default';
      return;
    }
    const zone = getImgZone(e);
    if (zone === 'center') {
      imgEl.style.cursor = cfg.zoom ? 'zoom-in' : 'default';
    } else {
      imgEl.style.cursor = 'pointer';
    }
  }

  function onWheel(e) {
    if (!imgEl.classList.contains('nlb-loaded')) return;
    e.preventDefault();
    const delta = e.deltaY > 0 ? -cfg.zoomStep : cfg.zoomStep;
    setZoom(zoomScale + delta, e.clientX, e.clientY, false);
  }

  function onDblClick(e) {
    if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; }
    isZoomed ? resetZoom(true) : setZoom(2.5, e.clientX, e.clientY, true);
  }

  function onMouseDown(e) {
    if (!isZoomed || e.button !== 0) return;
    isDragging = true;
    dragStartX = e.clientX; dragStartY = e.clientY;
    panStartX  = panX;      panStartY  = panY;
    imgEl.style.cursor = 'grabbing';
    e.preventDefault();
  }

  function onMouseMove(e) {
    if (!isDragging) return;
    panX = panStartX + (e.clientX - dragStartX);
    panY = panStartY + (e.clientY - dragStartY);
    clampPan();
    applyTransform(false);
  }

  function onMouseUp() {
    if (!isDragging) return;
    isDragging = false;
    if (isZoomed) imgEl.style.cursor = 'grab';
  }

  function onBackdropClick() {
    // Do not close while zoomed (prevents accidental closes mid-pan)
    if (isZoomed || isDragging) return;
    close();
  }

  /* =========================================================================
   *  TOUCH -- swipe + pinch-zoom + drag-pan unified
   * ========================================================================= */
  function getTouchDist(t) {
    const dx = t[0].clientX - t[1].clientX;
    const dy = t[0].clientY - t[1].clientY;
    return Math.sqrt(dx * dx + dy * dy);
  }

  function onTouchStart(e) {
    touchCount = e.touches.length;
    if (touchCount === 1) {
      touchStartX = e.changedTouches[0].screenX;
      touchStartY = e.changedTouches[0].screenY;
      if (isZoomed) { panTouchStartX = panX; panTouchStartY = panY; }
    }
    if (touchCount === 2 && cfg.zoom) {
      pinchStartDist  = getTouchDist(e.touches);
      pinchStartScale = zoomScale;
    }
  }

  function onTouchMove(e) {
    if (e.touches.length === 2 && cfg.zoom) {
      e.preventDefault();
      const dist  = getTouchDist(e.touches);
      const midX  = (e.touches[0].clientX + e.touches[1].clientX) / 2;
      const midY  = (e.touches[0].clientY + e.touches[1].clientY) / 2;
      setZoom(pinchStartScale * (dist / pinchStartDist), midX, midY, false);
      return;
    }
    if (e.touches.length === 1 && isZoomed) {
      e.preventDefault();
      panX = panTouchStartX + (e.touches[0].screenX - touchStartX);
      panY = panTouchStartY + (e.touches[0].screenY - touchStartY);
      clampPan();
      applyTransform(false);
    }
  }

  function onTouchEnd(e) {
    if (touchCount !== 1 || isZoomed) { touchCount = e.touches.length; return; }
    touchCount = e.touches.length;
    const dx = e.changedTouches[0].screenX - touchStartX;
    const dy = e.changedTouches[0].screenY - touchStartY;
    if (cfg.swipe && Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 44) {
      dx < 0 ? next() : prev();
    }
  }

  /* -- Keyboard ------------------------------------------------------------- */
  function onKey(e) {
    if (!overlay || !overlay.classList.contains('nlb-visible')) return;
    switch (e.key) {
      case 'Escape': case 'Esc':    isZoomed ? resetZoom(true) : close(); break;
      case 'ArrowRight': case 'Right': next(); break;
      case 'ArrowLeft':  case 'Left':  prev(); break;
      case '+': case '=': if (cfg.zoom) setZoom(zoomScale + cfg.zoomStep, undefined, undefined, true); break;
      case '-':           if (cfg.zoom) setZoom(zoomScale - cfg.zoomStep, undefined, undefined, true); break;
      case '0':           if (cfg.zoom) resetZoom(true); break;
    }
  }

  /* -- Caption -------------------------------------------------------------- */
  function getCaption(a) {
    if (a.dataset.nlbCaption) return a.dataset.nlbCaption;
    const img = a.querySelector('img');
    const src = cfg.captionSource;
    if (src === 'none') return '';
    if (src === 'title' || src === 'auto') {
      if (a.title)          return a.title;
      if (img && img.title) return img.title;
    }
    if (src === 'alt' || src === 'auto') {
      if (img && img.alt)   return img.alt;
    }
    if (src === 'auto') {
      const fig = a.closest('figure');
      const fc  = fig && fig.querySelector('figcaption');
      if (fc) return fc.textContent.trim();
    }
    return '';
  }

  /* -- CSS.escape polyfill -------------------------------------------------- */
  if (!('escape' in CSS)) CSS.escape = v => v.replace(/([^\w-])/g, '\\$1');

  /* =========================================================================
   *  PUBLIC API
   * ========================================================================= */
  const NitroLiteBox = {
    refresh() { collectLinks(); },
    open(src, startIdx = 0) {
      items = Array.isArray(src)
        ? src.map(s => typeof s === 'string' ? { src: s, caption: '' } : s)
        : [{ src, caption: '' }];
      idx = startIdx;
      if (!overlay) buildOverlay();
      open();
    },
    setZoom(scale) { setZoom(scale, undefined, undefined, true); },
    resetZoom()    { resetZoom(true); },
    close, next, prev,
  };

  window.NitroLiteBox = NitroLiteBox;

  /* -- Init ----------------------------------------------------------------- */
  function init() {
    collectLinks();
    document.dispatchEvent(new CustomEvent('nlb:ready'));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  document.addEventListener('nlb:refresh', () => NitroLiteBox.refresh());
})();