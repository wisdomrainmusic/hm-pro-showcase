(function(){
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
      sort: 'order'
    };

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
    }

    function ensureOverlay(){
      var existing = document.querySelector('.hmps-preview-overlay');
      if(existing) return existing;

      var overlay = document.createElement('div');
      overlay.className = 'hmps-preview-overlay';
      overlay.style.cssText = 'position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.55);color:#fff;font-size:16px;z-index:999999;';
      overlay.innerHTML = '<div style="padding:16px 20px;background:rgba(0,0,0,.65);border-radius:12px;font-family:Arial,sans-serif">Demo yükleniyor...</div>';
      document.body.appendChild(overlay);
      return overlay;
    }

    function pickRuntimeKey(){
      if(window.HMPS_SHOWCASE && window.HMPS_SHOWCASE.runtimeKey){
        return window.HMPS_SHOWCASE.runtimeKey;
      }
      return 'rt1';
    }

    function requestPreview(slug, popupWin){
      var endpoint = (window.HMPS_SHOWCASE && window.HMPS_SHOWCASE.previewEndpoint) ? window.HMPS_SHOWCASE.previewEndpoint : '';
      if(!endpoint){
        alert('Preview endpoint not configured.');
        return;
      }

      var overlay = ensureOverlay();
      overlay.style.display = 'flex';

      // If popup was blocked, we will fall back to same-tab redirect.
      // popupWin may be null.

      var payload = {
        slug: slug,
        runtime: pickRuntimeKey()
      };

      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(function(res){
        return res.json().then(function(json){
          return { ok: res.ok, json: json };
        });
      })
      .then(function(res){
        overlay.style.display = 'none';
        if(!res.ok || !res.json || !res.json.ok){
          var msg = (res.json && res.json.message) ? res.json.message : 'Preview failed.';
          alert(msg);
          // close the blank popup if we created it
          try { if(popupWin && !popupWin.closed){ popupWin.close(); } } catch(e){}
          return;
        }
        var url = res.json.redirect_to || res.json.runtime;
        if(url){
          // Prefer redirecting the pre-opened popup (avoids popup blocker).
          if(popupWin && !popupWin.closed){
            try { popupWin.location = url; } catch(e){ window.location.href = url; }
          } else {
            window.location.href = url;
          }
        }
      })
      .catch(function(err){
        overlay.style.display = 'none';
        alert(err && err.message ? err.message : 'Preview request error.');
        try { if(popupWin && !popupWin.closed){ popupWin.close(); } } catch(e){}
      });
    }

    tabs.forEach(function(btn){
      btn.addEventListener('click', function(){
        tabs.forEach(function(b){ b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        state.cat = btn.getAttribute('data-cat') || 'all';
        apply();
      });
    });

    if(search){
      search.addEventListener('input', function(){
        state.q = search.value || '';
        apply();
      });
    }

    if(sort){
      sort.addEventListener('change', function(){
        state.sort = sort.value || 'order';
        apply();
      });
    }

    // initial
    apply();

    qsa(root, '.hmps-preview').forEach(function(btn){
      btn.addEventListener('click', function(){
        var slug = btn.getAttribute('data-slug') || '';
        if(!slug) return;

        // Open popup synchronously in direct click handler to avoid browser blocking.
        var popupWin = null;
        try {
          popupWin = window.open('about:blank', '_blank');
          if(popupWin){
            popupWin.document.title = 'Loading demo...';
            popupWin.document.body.innerHTML = '<p style="font-family:Arial,sans-serif;padding:16px">Demo is loading, please wait...</p>';
          }
        } catch(e){ popupWin = null; }

        requestPreview(slug, popupWin);
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
