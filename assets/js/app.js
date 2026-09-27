document.addEventListener('DOMContentLoaded', function () {

  var toggleBtn = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('sidebar');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });

    document.addEventListener('click', function (e) {
      if (window.innerWidth < 992 && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
          sidebar.classList.remove('open');
        }
      }
    });
  }

});