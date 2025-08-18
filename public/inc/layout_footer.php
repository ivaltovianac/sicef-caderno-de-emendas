      </div> <!-- .content-area -->
    </main>
  </div> <!-- .user-container -->

  <script>
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
    }
    document.addEventListener('click', function (event) {
      const sidebar = document.getElementById('sidebar');
      const menuToggle = document.querySelector('.menu-toggle');
      if (window.innerWidth <= 768 && sidebar && menuToggle && !sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('active')) {
        toggleSidebar();
      }
    });
    window.addEventListener('resize', function () {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      if (window.innerWidth > 768) {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
      }
    });

    // Quick search (quando existir #quickSearch e .emendas-table)
    document.addEventListener('DOMContentLoaded', function() {
      const quickSearch = document.getElementById('quickSearch');
      if (quickSearch) {
        quickSearch.addEventListener('input', function() {
          const searchTerm = this.value.toLowerCase();
          const tableRows = document.querySelectorAll('.emendas-table tbody tr');

          tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
          });
        });
      }
    });
  </script>
</body>
</html>