// Dark Mode Toggle Functionality
(function() {
    const themeToggle = document.getElementById('themeToggle');
    const htmlElement = document.documentElement;
    
    // Check if dark mode preference is saved
    const savedTheme = localStorage.getItem('theme') || 'light';
    
    // Apply saved theme on page load
    if (savedTheme === 'dark') {
        htmlElement.classList.add('dark-theme');
        if (themeToggle) {
            themeToggle.textContent = '☀️';
            themeToggle.setAttribute('aria-label', 'Switch to light mode');
        }
    } else {
        htmlElement.classList.remove('dark-theme');
        if (themeToggle) {
            themeToggle.textContent = '🌙';
            themeToggle.setAttribute('aria-label', 'Switch to dark mode');
        }
    }
    
    // Toggle dark mode on button click
    if (themeToggle) {
        themeToggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (htmlElement.classList.contains('dark-theme')) {
                htmlElement.classList.remove('dark-theme');
                localStorage.setItem('theme', 'light');
                themeToggle.textContent = '🌙';
                themeToggle.setAttribute('aria-label', 'Switch to dark mode');
            } else {
                htmlElement.classList.add('dark-theme');
                localStorage.setItem('theme', 'dark');
                themeToggle.textContent = '☀️';
                themeToggle.setAttribute('aria-label', 'Switch to light mode');
            }
        });
    }
})();
