document.addEventListener('DOMContentLoaded', function () {
    const siteHeader = document.querySelector('#site-header');
    const siteNavToggle = document.querySelector('#siteNavToggle');
    const siteMainNav = document.querySelector('#site-main-nav');
    if (siteNavToggle && siteHeader && siteMainNav) {
        siteNavToggle.addEventListener('click', function () {
            const open = siteHeader.classList.toggle('nav-open');
            siteNavToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        siteMainNav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                siteHeader.classList.remove('nav-open');
                siteNavToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    document.querySelectorAll('.dashboard-container').forEach(function (container) {
        const toggle = container.querySelector('.dashboard-drawer-toggle');
        if (!toggle) {
            return;
        }
        let backdrop = container.querySelector('.dashboard-drawer-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'dashboard-drawer-backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            container.insertBefore(backdrop, container.firstChild);
        }
        function setOpen(open) {
            container.classList.toggle('drawer-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('dashboard-drawer-active', open);
        }
        toggle.addEventListener('click', function () {
            setOpen(!container.classList.contains('drawer-open'));
        });
        backdrop.addEventListener('click', function () {
            setOpen(false);
        });
        container.querySelectorAll('.dashboard-sidebar a').forEach(function (a) {
            a.addEventListener('click', function () {
                if (window.matchMedia('(max-width: 940px)').matches) {
                    setOpen(false);
                }
            });
        });
    });

    if (window.location.pathname.indexOf('user-dashboard.php') !== -1) {
        var pollMs = 45000;
        var pollUrl = 'notifications-poll.php';
        function pollNotes() {
            fetch(pollUrl, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) return;
                    var el = document.querySelector('[data-notification-badge]');
                    if (el) {
                        el.textContent = data.count > 0 ? '(' + data.count + ')' : '';
                        el.setAttribute('data-count', String(data.count));
                    }
                })
                .catch(function () {});
        }
        setInterval(pollNotes, pollMs);
    }

    const eventFilters = document.querySelector('#eventFilters');
    const searchInput = document.querySelector('#searchQuery');

    if (eventFilters) {
        eventFilters.addEventListener('change', updateEventCards);
        if (searchInput) {
            searchInput.addEventListener('input', debounce(updateEventCards, 350));
        }
    }

    const loginForm = document.querySelector('#loginForm');
    const registerForm = document.querySelector('#registerForm');
    const eventForm = document.querySelector('#eventForm');

    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            const email = loginForm.email.value.trim();
            const password = loginForm.password.value.trim();
            if (!email || !password) {
                e.preventDefault();
                alert('Please enter both email and password.');
            }
        });
    }
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            const name = registerForm.name.value.trim();
            const email = registerForm.email.value.trim();
            const password = registerForm.password.value;
            const confirm = registerForm.confirm_password.value;
            if (!name || !email || !password || !confirm) {
                e.preventDefault();
                alert('All fields are required.');
                return;
            }
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters.');
                return;
            }
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match.');
            }
        });
    }
    if (eventForm) {
        eventForm.addEventListener('submit', function (e) {
            const title = eventForm.title.value.trim();
            const date = eventForm.date.value;
            const location = eventForm.location.value.trim();
            const description = eventForm.description.value.trim();
            if (!title || !date || !location || !description) {
                e.preventDefault();
                alert('Please fill in all required event fields.');
            }
        });
    }

    function updateEventCards() {
        const categoryEl = document.querySelector('#filterCategory');
        const locationEl = document.querySelector('#filterLocation');
        const dateEl = document.querySelector('#filterDate');
        const priceTypeEl = document.querySelector('#filterPriceType');
        const sortEl = document.querySelector('#sortBy');
        const queryEl = document.querySelector('#searchQuery');
        const category = categoryEl ? categoryEl.value : '';
        const location = locationEl ? locationEl.value : '';
        const date = dateEl ? dateEl.value : '';
        const price_type = priceTypeEl ? priceTypeEl.value : '';
        const sort = sortEl ? sortEl.value : 'date';
        const query = queryEl ? queryEl.value.trim() : '';
        const cardsContainer = document.querySelector('#eventsCards');
        if (!cardsContainer) return;

        const params = new URLSearchParams({
            category, location, date, query, price_type, sort
        });
        fetch('events_ajax.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (!Array.isArray(data)) return;
                cardsContainer.innerHTML = data.map(event => {
                    const priceHtml = Number(event.price) > 0
                        ? `<div class="event-price">ETB ${Number(event.price).toLocaleString()}</div>`
                        : '<div class="event-price">Free</div>';
                    const safeTitle = String(event.title).replace(/"/g, '&quot;');
                    return `
                    <article class="card card--clickable">
                        <a class="card-stretch-link" href="event-details.php?id=${event.id}" aria-label="Open event: ${safeTitle}"><span class="visually-hidden">Open event details</span></a>
                        <div class="event-card-media">
                            <img src="${event.image_url}" alt="${event.title}">
                            <span class="category-badge">${event.category_name}</span>
                        </div>
                        <div class="card-body">
                            <h3>${event.title}</h3>
                            <div class="meta-row">
                                <span>📅 ${event.date}</span>
                                <span>📍 ${event.location}</span>
                            </div>
                            ${priceHtml}
                        </div>
                    </article>`;
                }).join('');
            })
            .catch(() => {
                cardsContainer.innerHTML = '<p class="text-center">Unable to retrieve events. Please refresh.</p>';
            });
    }

    function debounce(fn, delay) {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(this, args), delay);
        };
    }
});
