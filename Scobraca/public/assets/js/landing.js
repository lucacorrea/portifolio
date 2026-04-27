function toggleMenu() {
    const nav = document.getElementById('mainNav');

    if (!nav) {
        return;
    }

    nav.classList.toggle('active');
}

function toggleFaq(button) {
    const item = button.closest('.faq-item');

    if (!item) {
        return;
    }

    const allItems = document.querySelectorAll('.faq-item');

    allItems.forEach((faq) => {
        if (faq !== item) {
            faq.classList.remove('active');
        }
    });

    item.classList.toggle('active');
}

document.addEventListener('click', function (event) {
    const nav = document.getElementById('mainNav');
    const toggle = document.querySelector('.menu-toggle');

    if (!nav || !toggle) {
        return;
    }

    const clickedInsideMenu = nav.contains(event.target);
    const clickedToggle = toggle.contains(event.target);

    if (!clickedInsideMenu && !clickedToggle) {
        nav.classList.remove('active');
    }
});

document.querySelectorAll('.main-nav a').forEach((link) => {
    link.addEventListener('click', () => {
        const nav = document.getElementById('mainNav');

        if (nav) {
            nav.classList.remove('active');
        }
    });
});
function toggleMenu() {
    const nav = document.getElementById('mainNav');

    if (!nav) {
        return;
    }

    nav.classList.toggle('active');
}

function toggleFaq(button) {
    const item = button.closest('.faq-item');

    if (!item) {
        return;
    }

    const allItems = document.querySelectorAll('.faq-item');

    allItems.forEach((faq) => {
        if (faq !== item) {
            faq.classList.remove('active');
        }
    });

    item.classList.toggle('active');
}

document.addEventListener('click', function (event) {
    const nav = document.getElementById('mainNav');
    const toggle = document.querySelector('.menu-toggle');

    if (!nav || !toggle) {
        return;
    }

    const clickedInsideMenu = nav.contains(event.target);
    const clickedToggle = toggle.contains(event.target);

    if (!clickedInsideMenu && !clickedToggle) {
        nav.classList.remove('active');
    }
});

document.querySelectorAll('.main-nav a').forEach((link) => {
    link.addEventListener('click', () => {
        const nav = document.getElementById('mainNav');

        if (nav) {
            nav.classList.remove('active');
        }
    });
});

const animatedElements = document.querySelectorAll('[data-animate]');

const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
        }
    });
}, {
    threshold: 0.12
});

animatedElements.forEach((element) => {
    observer.observe(element);
});