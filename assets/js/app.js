(() => {
  'use strict';

  const menuButton = document.querySelector('.menu-toggle');
  const navigation = document.querySelector('.desktop-nav');
  if (menuButton && navigation) {
    menuButton.addEventListener('click', () => {
      const open = menuButton.getAttribute('aria-expanded') === 'true';
      menuButton.setAttribute('aria-expanded', String(!open));
      navigation.classList.toggle('is-open', !open);
    });
  }

  const search = document.querySelector('[data-player-search]');
  if (search) {
    const cards = [...document.querySelectorAll('[data-player-card]')];
    const empty = document.querySelector('[data-search-empty]');
    search.addEventListener('input', () => {
      const query = search.value.trim().toLocaleLowerCase();
      let visible = 0;
      cards.forEach((card) => {
        const matches = card.textContent.toLocaleLowerCase().includes(query);
        card.hidden = !matches;
        visible += matches ? 1 : 0;
      });
      if (empty) empty.hidden = visible > 0;
    });
  }

  const counters = document.querySelectorAll('[data-count]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const animateCounter = (element) => {
    const target = Number(element.dataset.count || 0);
    if (reducedMotion || !Number.isFinite(target)) {
      element.textContent = target.toLocaleString('en-GB');
      return;
    }
    const start = performance.now();
    const duration = 700;
    const tick = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      element.textContent = Math.round(target * eased).toLocaleString('en-GB');
      if (progress < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries, currentObserver) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          currentObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.3 });
    counters.forEach((counter) => observer.observe(counter));
  } else {
    counters.forEach(animateCounter);
  }
})();
