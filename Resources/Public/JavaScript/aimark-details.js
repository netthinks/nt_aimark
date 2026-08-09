/**
 * Toggle for the AI disclosure detail panel.
 *
 * The badge itself is the control: a separate button below the picture said
 * the same thing twice and was the loudest part of the label. Focus stays on
 * the badge — moving it into the panel would make the disclosure harder to
 * operate, not easier.
 */
const toggle = (control) => {
  const panel = document.getElementById(control.getAttribute('aria-controls'));

  if (!panel) {
    return;
  }

  const expanded = control.getAttribute('aria-expanded') === 'true';

  control.setAttribute('aria-expanded', expanded ? 'false' : 'true');
  panel.toggleAttribute('hidden', expanded);
};

document.addEventListener('click', (event) => {
  const control = event.target.closest('.nt-aimark__badge[aria-controls]');

  if (control) {
    toggle(control);
  }
});
