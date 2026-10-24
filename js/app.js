// Keep progressive enhancement small: password fields remain usable without JavaScript.
document.addEventListener('click', (event) => {
  const toggle = event.target.closest('[data-password-toggle]');
  if (!toggle) return;

  // Toggle only the adjacent input and update accessible state for screen readers.
  const field = toggle.closest('.password-control')?.querySelector('input');
  if (!field) return;

  const showing = field.type === 'password';
  field.type = showing ? 'text' : 'password';
  toggle.setAttribute('aria-pressed', String(showing));
  toggle.setAttribute('aria-label', showing ? 'Ocultar contraseña' : 'Mostrar contraseña');
});

// Show native HTML validation messages beside fields and highlight the invalid controls.
const validationMessage = (field) => {
  const validity = field.validity;
  if (validity.valueMissing) return 'Completa este campo.';
  if (validity.typeMismatch) return 'Ingresa un valor con el formato correcto.';
  if (validity.patternMismatch) return field.title || 'El formato ingresado no es válido.';
  if (validity.rangeUnderflow) return `El valor mínimo permitido es ${field.min}.`;
  if (validity.rangeOverflow) return `El valor máximo permitido es ${field.max}.`;
  if (validity.stepMismatch) return 'Usa un valor válido para este campo.';
  if (validity.tooLong) return `Este campo admite hasta ${field.maxLength} caracteres.`;
  return 'Revisa el valor ingresado.';
};

document.addEventListener('invalid', (event) => {
  const field = event.target;
  if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
  const form = field.closest('[data-validation-form]');
  if (!form) return;
  field.classList.add('is-invalid');
  field.setAttribute('aria-invalid', 'true');
  const message = form.querySelector(`[data-field-error="${CSS.escape(field.name)}"]`);
  if (message) message.textContent = validationMessage(field);
}, true);

document.addEventListener('input', (event) => {
  const field = event.target;
  if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
  if (!field.closest('[data-validation-form]') || !field.classList.contains('is-invalid') || !field.validity.valid) return;
  field.classList.remove('is-invalid');
  field.removeAttribute('aria-invalid');
  const message = field.form?.querySelector(`[data-field-error="${CSS.escape(field.name)}"]`);
  if (message) message.textContent = '';
});

document.addEventListener('change', (event) => {
  const field = event.target;
  if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
  if (!field.closest('[data-validation-form]') || !field.classList.contains('is-invalid') || !field.validity.valid) return;
  field.classList.remove('is-invalid');
  field.removeAttribute('aria-invalid');
  const message = field.form?.querySelector(`[data-field-error="${CSS.escape(field.name)}"]`);
  if (message) message.textContent = '';
});
