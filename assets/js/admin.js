(() => {
  'use strict';
  const menu = document.querySelector('.admin-menu-toggle');
  const nav = document.querySelector('.admin-nav');
  if (menu && nav) menu.addEventListener('click', () => { const open = menu.getAttribute('aria-expanded') === 'true'; menu.setAttribute('aria-expanded', String(!open)); nav.classList.toggle('is-open', !open); });

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-step]');
    if (!button) return;
    const stepper = button.closest('.stepper');
    const input = stepper?.querySelector('input[type="number"]');
    if (!input) return;
    const delta = Number(button.dataset.step || 0);
    const current = input.value === '' ? 0 : Number(input.value);
    const minimum = input.min === '' ? 0 : Number(input.min);
    input.value = String(Math.max(minimum, current + delta));
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });

  const participantInputs = [...document.querySelectorAll('[data-participant]')];
  const podSelect = document.querySelector('[data-pod-select]');
  const syncParticipants = () => {
    participantInputs.forEach((input) => {
      const score = document.querySelector(`[data-score-player="${input.value}"]`);
      if (score) score.hidden = !input.checked;
      const option = podSelect?.querySelector(`option[value="${input.value}"]`);
      if (option) { option.disabled = !input.checked; if (!input.checked && option.selected) podSelect.value = ''; }
    });
  };
  participantInputs.forEach((input) => input.addEventListener('change', syncParticipants));
  document.querySelector('[data-select-all]')?.addEventListener('click', () => { participantInputs.forEach((input) => { input.checked = true; }); syncParticipants(); });
  document.querySelector('[data-clear-all]')?.addEventListener('click', () => { participantInputs.forEach((input) => { input.checked = false; }); syncParticipants(); });
  syncParticipants();

  const drawForm = document.querySelector('[data-draw-form]');
  const drawCount = drawForm?.querySelector('[data-draw-count]');
  if (drawForm && drawCount) {
    const guests = drawForm.querySelector('[name="guests"]');
    const updateCount = () => {
      const guestNames = new Set((guests?.value ?? '').split(/[\n,]/).map((name) => name.trim().replace(/\s+/g, ' ').toLowerCase()).filter(Boolean));
      const total = drawForm.querySelectorAll('[data-participant]:checked').length + guestNames.size;
      drawCount.textContent = `${total} player${total === 1 ? '' : 's'} selected`;
    };
    drawForm.addEventListener('input', updateCount);
    drawForm.addEventListener('change', updateCount);
    drawForm.querySelectorAll('[data-select-all],[data-clear-all]').forEach((button) => button.addEventListener('click', updateCount));
    updateCount();
  }

  document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
    const field = document.querySelector(button.dataset.copy);
    if (!field) return;
    field.select();
    try { await navigator.clipboard.writeText(field.value); } catch { document.execCommand('copy'); }
    button.textContent = 'Copied';
  }));

  document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); }));
})();
