document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.public-menu-toggle');
    const navigation = document.getElementById('public-navigation');
    const backTop = document.querySelector('.public-back-top');
    const navigationLinks = Array.from(document.querySelectorAll('.public-navigation a'));
    const sections = navigationLinks
        .map((link) => document.querySelector(link.getAttribute('href')))
        .filter(Boolean);

    function closeMenu() {
        navigation?.classList.remove('is-open');
        toggle?.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('is-menu-open');
    }

    toggle?.addEventListener('click', () => {
        const open = !navigation.classList.contains('is-open');
        navigation.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('is-menu-open', open);
    });

    document.querySelectorAll('a[href^="#"]').forEach((link) => {
        link.addEventListener('click', closeMenu);
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            navigationLinks.forEach((link) => {
                link.classList.toggle('is-active', link.getAttribute('href') === `#${entry.target.id}`);
            });
        });
    }, { rootMargin: '-25% 0px -60%', threshold: 0 });
    sections.forEach((section) => observer.observe(section));

    function updateBackTop() {
        backTop?.classList.toggle('is-visible', window.scrollY > 700);
    }
    window.addEventListener('scroll', updateBackTop, { passive: true });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) closeMenu();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenu();
    });
    updateBackTop();
});
