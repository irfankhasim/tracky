// Tracky Theme Manager — dark is the default, light-mode class = bright mode
(function () {
  function applyTheme(mode) {
    var isLight = mode === 'light';
    document.body.classList.toggle('light-mode', isLight);
    document.body.classList.remove('dark-mode'); // no longer needed

    var icon = document.getElementById('themeIcon');
    var btn  = document.getElementById('themeToggle');
    if (icon) icon.className = isLight ? 'ti ti-moon' : 'ti ti-sun';
    if (btn)  btn.title = isLight ? 'Tukar ke Night Mode' : 'Tukar ke Bright Mode';
  }

  window.toggleTheme = function () {
    var next = (localStorage.getItem('tracky-theme') || 'dark') === 'dark' ? 'light' : 'dark';
    localStorage.setItem('tracky-theme', next);
    applyTheme(next);
  };

  applyTheme(localStorage.getItem('tracky-theme') || 'dark');
})();
