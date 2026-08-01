(function () {
  'use strict';

  var tabs = Array.from(document.querySelectorAll('[data-report-tab]'));
  var panels = Array.from(document.querySelectorAll('[data-report-panel]'));
  var activeView = document.getElementById('report-active-view');
  if (tabs.length < 2 || panels.length < 2) return;

  function selectView(view, updateUrl) {
    var selected = tabs.find(function (tab) { return tab.dataset.reportTab === view; });
    if (!selected) return;

    tabs.forEach(function (tab) {
      var isActive = tab === selected;
      tab.classList.toggle('active', isActive);
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      tab.setAttribute('tabindex', isActive ? '0' : '-1');
    });
    panels.forEach(function (panel) {
      panel.hidden = panel.dataset.reportPanel !== view;
    });
    if (activeView) activeView.value = view;
    if (updateUrl) {
      var url = new URL(window.location.href);
      url.searchParams.set('visao', view);
      window.history.replaceState({}, '', url);
    }
  }

  tabs.forEach(function (tab, index) {
    tab.addEventListener('click', function (event) {
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      selectView(tab.dataset.reportTab || '', true);
    });
    tab.addEventListener('keydown', function (event) {
      var targetIndex = null;
      if (event.key === 'ArrowRight') targetIndex = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') targetIndex = (index - 1 + tabs.length) % tabs.length;
      if (event.key === 'Home') targetIndex = 0;
      if (event.key === 'End') targetIndex = tabs.length - 1;
      if (targetIndex === null) return;
      event.preventDefault();
      var target = tabs[targetIndex];
      selectView(target.dataset.reportTab || '', true);
      target.focus();
    });
  });

  selectView((activeView && activeView.value) || tabs[0].dataset.reportTab || '', false);
}());