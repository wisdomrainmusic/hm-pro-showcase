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
  }

  document.addEventListener('DOMContentLoaded', function(){
    var roots = document.querySelectorAll('.hmps-showcase');
    for(var i=0;i<roots.length;i++){
      initShowcase(roots[i]);
    }
  });
})();
