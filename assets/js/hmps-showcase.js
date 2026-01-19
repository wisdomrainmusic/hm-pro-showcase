(function(){
  var RR_KEY = 'hmps_runtime_rr_index';
  function qs(root, sel){ return root.querySelector(sel); }
  function qsa(root, sel){ return Array.prototype.slice.call(root.querySelectorAll(sel)); }

  function initShowcase(root){
    var tabs = qsa(root, '.hmps-tab');
    var grid = qs(root, '.hmps-grid');
    var search = qs(root, '.hmps-search');
    var sort = qs(root, '.hmps-sort');
    if(!grid) return;

    var cards = qsa(root, '.hmps-card');
    var state = {
      cat: 'all',
      q: '',
      sort: 'order',
      page: 1
    };

    var perPage = parseInt(root.getAttribute('data-per-page') || '12', 10);
    if(isNaN(perPage) || perPage < 1) perPage = 12;
    var paging = (root.getAttribute('data-paging') || 'loadmore').toLowerCase();
    if(['loadmore','pagination','none'].indexOf(paging) === -1) paging = 'loadmore';

    var pager = qs(root, '.hmps-pager');
    if(!pager){
      pager = document.createElement('div');
      pager.className = 'hmps-pager';
      root.appendChild(pager);
    }

    function setPage(n){
      state.page = n;
    }

    function renderPager(totalVisible){
      if(!pager) return;
      pager.innerHTML = '';
      if(paging === 'none') return;
      if(totalVisible <= perPage) return;

      var totalPages = Math.ceil(totalVisible / perPage);

      if(paging === 'loadmore'){
        var shown = Math.min(state.page * perPage, totalVisible);
        if(shown >= totalVisible) return;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'hmps-loadmore';
        btn.textContent = 'Daha fazla yükle';
        btn.addEventListener('click', function(){
          setPage(state.page + 1);
          apply();
        });
        pager.appendChild(btn);
        return;
      }

      // pagination
      var wrap = document.createElement('div');
      wrap.className = 'hmps-pagination';

      function addPageButton(label, page, isActive){
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'hmps-page' + (isActive ? ' is-active' : '');
        b.textContent = String(label);
        b.addEventListener('click', function(){
          setPage(page);
          apply();
        });
        wrap.appendChild(b);
      }

      // prev
      addPageButton('‹', Math.max(1, state.page - 1), false);

      // compact window
      var start = Math.max(1, state.page - 2);
      var end = Math.min(totalPages, state.page + 2);

      if(start > 1){
        addPageButton(1, 1, state.page === 1);
        if(start > 2){
          var dots = document.createElement('span');
          dots.className = 'hmps-dots';
          dots.textContent = '…';
          wrap.appendChild(dots);
        }
      }

      for(var i=start;i<=end;i++){
        addPageButton(i, i, i === state.page);
      }

      if(end < totalPages){
        if(end < totalPages - 1){
          var dots2 = document.createElement('span');
          dots2.className = 'hmps-dots';
          dots2.textContent = '…';
          wrap.appendChild(dots2);
        }
        addPageButton(totalPages, totalPages, state.page === totalPages);
      }

      // next
      addPageButton('›', Math.min(totalPages, state.page + 1), false);

      pager.appendChild(wrap);
    }

    function apply(){
      var q = (state.q || '').toLowerCase().trim();
      var cat = state.cat;

      cards.forEach(function(card){
        var title = (card.getAttribute('data-title') || '').toLowerCase();
        var cats = (card.getAttribute('data-cats') || '').toLowerCase();

        var okCat = (cat === 'all') ? true : ((' ' + cats + ' ').indexOf(' ' + cat + ' ') !== -1);
        var okQ = !q ? true : (title.indexOf(q) !== -1);

        card.style.display = (okCat && okQ) ? '' : 'none';
      });

      // Sort visible cards by title/order.
      var visible = cards.filter(function(c){ return c.style.display !== 'none'; });
      visible.sort(function(a,b){
        if(state.sort === 'title'){
          var at = (a.getAttribute('data-title') || '');
          var bt = (b.getAttribute('data-title') || '');
          return at.localeCompare(bt);
        }
        // order (fallback 0) then title
        var ao = parseInt(a.getAttribute('data-order') || '0', 10);
        var bo = parseInt(b.getAttribute('data-order') || '0', 10);
        if(ao === bo){
          var at2 = (a.getAttribute('data-title') || '');
          var bt2 = (b.getAttribute('data-title') || '');
          return at2.localeCompare(bt2);
        }
        return ao - bo;
      });

      visible.forEach(function(card){
        grid.appendChild(card);
      });

      // paging visibility (after sort)
      var totalVisible = visible.length;
      if(paging !== 'none' && perPage > 0 && totalVisible > 0){
        var startIdx = 0;
        var endIdx = totalVisible;

        if(paging === 'loadmore'){
          endIdx = Math.min(state.page * perPage, totalVisible);
        } else if(paging === 'pagination'){
          startIdx = (state.page - 1) * perPage;
          endIdx = Math.min(startIdx + perPage, totalVisible);
        }

        visible.forEach(function(card, idx){
          card.style.display = (idx >= startIdx && idx < endIdx) ? '' : 'none';
        });
      }

      renderPager(totalVisible);
    }

    tabs.forEach(function(btn){
      btn.addEventListener('click', function(){
        tabs.forEach(function(b){ b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        state.cat = btn.getAttribute('data-cat') || 'all';
        setPage(1);
        apply();
      });
    });

    if(search){
      search.addEventListener('input', function(){
        state.q = search.value || '';
        setPage(1);
        apply();
      });
    }

    if(sort){
      sort.addEventListener('change', function(){
        state.sort = sort.value || 'order';
        setPage(1);
        apply();
      });
    }

    // initial
    setPage(1);
    apply();

    // Preview: apply demo on runtime then open runtime URL.
    function ensureOverlay(){
      var ov = qs(root, '.hmps-overlay');
      if(ov) return ov;
      ov = document.createElement('div');
      ov.className = 'hmps-overlay';
      ov.innerHTML = '<div class="hmps-overlay__card"><div class="hmps-overlay__title">Demo yükleniyor...</div><div class="hmps-overlay__sub">Lütfen bekleyin</div></div>';
      ov.style.display = 'none';
      root.appendChild(ov);
      return ov;
    }

    function pickRuntimeKeyRoundRobin(){
      try {
        if(window.HMPS_SHOWCASE && Array.isArray(window.HMPS_SHOWCASE.runtimeKeys) && window.HMPS_SHOWCASE.runtimeKeys.length){
          var keys = window.HMPS_SHOWCASE.runtimeKeys.slice(0);
          var idx = 0;
          try {
            idx = parseInt(window.localStorage.getItem(RR_KEY) || '0', 10);
            if(isNaN(idx) || idx < 0) idx = 0;
          } catch(e) { idx = 0; }

          var pick = keys[idx % keys.length];

          // advance
          try {
            window.localStorage.setItem(RR_KEY, String((idx + 1) % keys.length));
          } catch(e) {}

          return pick;
        }
      } catch(e){}
      return 'rt1';
    }

    function writeWindowLoading(w, slug, coverUrl){
      try {
        var cover = '';
        try {
          if (coverUrl) cover = String(coverUrl);
        } catch(e) {}

        w.document.open();
        w.document.write(
          '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+
          '<title>Demo Hazırlanıyor</title></head>'+
          '<body style="margin:0;font-family:Arial,sans-serif;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;position:relative;overflow:hidden;">'+
            (cover ? (
              '<div style="position:absolute;inset:0;background-image:url(' + cover.replace(/"/g,'&quot;') + ');background-size:cover;background-position:center;filter:blur(2px);transform:scale(1.03);opacity:.35;"></div>'
            ) : (
              '<div style="position:absolute;inset:0;background:#0b0d12;"></div>'
            )) +
            '<div style="position:absolute;inset:0;background:rgba(0,0,0,.55);"></div>'+
            '<div style="position:relative;z-index:1;text-align:center;max-width:560px;padding:28px 24px;">'+
              '<div style="width:54px;height:54px;border:4px solid rgba(255,255,255,.25);border-top-color:#fff;border-radius:999px;margin:0 auto 16px;animation:spin 1s linear infinite"></div>'+
              '<h3 style="margin:0 0 8px;font-size:22px;line-height:1.2">Demo hazırlanıyor…</h3>'+
              '<p style="margin:0;opacity:.88;font-size:14px;line-height:1.55">Lütfen bu sekmeyi kapatmayın. İşlem tamamlanınca otomatik olarak yönlendirileceksiniz.</p>'+
              (slug ? '<p style="margin:14px 0 0;opacity:.75;font-size:12px">Demo: '+ String(slug).replace(/</g,'&lt;') +'</p>' : '') +
              '<style>@keyframes spin{to{transform:rotate(360deg)}}</style>'+
            '</div>'+
          '</body></html>'
        );
        w.document.close();
      } catch(e){}
    }

    function writeWindowError(w, msg){
      try {
        w.document.open();
        w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Preview Failed</title></head><body style="font-family:Arial,sans-serif;padding:24px;"><h3>Önizleme başarısız</h3><p>' + String(msg || 'Unknown error') + '</p></body></html>');
        w.document.close();
      } catch(e){
        try { w.alert(msg || 'Preview failed.'); } catch(_e){}
      }
    }

    function requestPreview(slug, popupWin){
      var endpoint = (window.HMPS_SHOWCASE && window.HMPS_SHOWCASE.previewEndpoint) ? window.HMPS_SHOWCASE.previewEndpoint : '';
      if(!endpoint){
        if(popupWin) writeWindowError(popupWin, 'Preview endpoint not configured.');
        else alert('Preview endpoint not configured.');
        return;
      }

      var overlay = ensureOverlay();
      overlay.style.display = 'flex';

      var payload = {
        slug: slug,
        runtime: pickRuntimeKeyRoundRobin()
      };

      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(function(r){ return r.json().then(function(j){ return { ok: r.ok, status: r.status, json: j }; }); })
      .then(function(res){
        overlay.style.display = 'none';
        if(!res.ok || !res.json || !res.json.ok){
          var msg = (res.json && res.json.message) ? res.json.message : 'Preview failed.';
          if(popupWin) writeWindowError(popupWin, msg);
          else alert(msg);
          return;
        }
        var url = res.json.redirect_to || res.json.runtime;
        if(url){
          if(popupWin){
            try { popupWin.location.href = url; } catch(e){ window.open(url, '_blank'); }
          } else {
            window.open(url, '_blank');
          }
        }
      })
      .catch(function(err){
        overlay.style.display = 'none';
        var emsg = (err && err.message) ? err.message : 'Preview request error.';
        if(popupWin) writeWindowError(popupWin, emsg);
        else alert(emsg);
      });
    }

    qsa(root, '.hmps-preview').forEach(function(btn){
      btn.addEventListener('click', function(){
        var slug = btn.getAttribute('data-slug') || '';
        var cover = btn.getAttribute('data-cover') || '';
        if(!slug) return;

        // Popup-proof: open immediately on click.
        var w = null;
        try {
          w = window.open('about:blank', '_blank');
        } catch(e) { w = null; }

        if(w){
          writeWindowLoading(w, slug, cover);
        }

        requestPreview(slug, w);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    var roots = document.querySelectorAll('.hmps-showcase');
    for(var i=0;i<roots.length;i++){
      initShowcase(roots[i]);
    }
  });
})();
