const sections = document.querySelectorAll('[data-reveal]');

if (sections.length && 'IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entry.target.classList.remove('reveal-pending');
            entry.target.classList.add('reveal-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.08 });

    sections.forEach(section => {
        section.addEventListener('focusin', () => {
            section.classList.remove('reveal-pending', 'reveal-visible');
            observer.unobserve(section);
        }, { once: true });
        section.classList.add('reveal-pending');
        observer.observe(section);
    });
}
