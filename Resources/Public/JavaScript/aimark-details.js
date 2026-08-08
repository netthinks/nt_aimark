/**
 * Toggle for the AI disclosure detail panel.
 *
 * Focus deliberately stays on the button: moving it into the panel would make
 * the disclosure harder to operate, not easier.
 */
const toggle = (button) => {
  const panel = document.getElementById(button.getAttribute('aria-controls'));

  if (!panel) {
    return;
  }

  const expanded = button.getAttribute('aria-expanded') === 'true';

  button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
  panel.toggleAttribute('hidden', expanded);
};

document.addEventListener('click', (event) => {
  const button = event.target.closest('.nt-aimark__toggle');

  if (button) {
    toggle(button);
  }
});
