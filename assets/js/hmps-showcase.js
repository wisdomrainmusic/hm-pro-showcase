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

    // Preview modal (Astra-like): open preview inside an iframe overlay.
    var modal = qs(root, '.hmps-modal');
    var frame = modal ? qs(modal, '.hmps-modal__frame') : null;
    function openModal(url){
      if(!modal || !frame || !url) return;
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden','false');
      frame.setAttribute('src', url);
      document.documentElement.classList.add('hmps-modal-open');
    }
    function closeModal(){
      if(!modal || !frame) return;
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden','true');
      frame.setAttribute('src','');
      document.documentElement.classList.remove('hmps-modal-open');
    }

    qsa(root, '.hmps-preview-open').forEach(function(a){
      a.addEventListener('click', function(e){
        var url = a.getAttribute('data-preview-url') || a.getAttribute('href');
        if(modal && frame){
          e.preventDefault();
          openModal(url);
        }
      });
    });

    if(modal){
      qsa(modal, '[data-hmps-close]').forEach(function(btn){
        btn.addEventListener('click', function(){ closeModal(); });
      });
      document.addEventListener('keydown', function(ev){
        if(ev.key === 'Escape' && modal.classList.contains('is-open')){
          closeModal();
        }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    var roots = document.querySelectorAll('.hmps-showcase');
    for(var i=0;i<roots.length;i++){
      initShowcase(roots[i]);
    }
  });
})();
