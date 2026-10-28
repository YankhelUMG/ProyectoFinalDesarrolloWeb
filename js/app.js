// Mantiene los formularios de contraseña accesibles sin JavaScript.
document.addEventListener('click', (event) => {
 const toggle = event.target.closest('[data-password-toggle]');
 if (!toggle) return;
 // Cambia la visibilidad del campo y actualiza su estado accesible.
 const field = toggle.closest('.password-control')?.querySelector('input');
 if (!field) return;
 const showing = field.type === 'password';
 field.type = showing ? 'text': 'password';
 toggle.setAttribute('aria-pressed', String(showing));
 toggle.setAttribute('aria-label', showing ? 'Ocultar contraseña': 'Mostrar contraseña');
});
// Muestra errores de validación junto a los campos.
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
 let message = form.querySelector(`[data-field-error="${CSS.escape(field.name)}"]`);
 // Agrega un aviso accesible si el formulario no tiene un espacio para errores.
 if (!message) {
 message = document.createElement('small');
 message.className = 'field-error';
 message.dataset.fieldError = field.name;
 message.id = `validation-${field.id || field.name}-${Math.random().toString(36).slice(2, 8)}`;
 field.insertAdjacentElement('afterend', message);
  }
 field.setAttribute('aria-describedby', message.id);
 message.textContent = validationMessage(field);
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
// Exige un motivo para rechazar empresas o postulaciones.
document.addEventListener('change', (event) => {
 const field = event.target;
 if (!(field instanceof HTMLSelectElement) || field.name !== 'estado' || !field.form?.matches('[data-application-review]')) return;
 const comment = field.form.querySelector('[data-application-comment]');
 if (comment) {
 comment.required = field.value === 'rechazada';
 if (!comment.required) comment.setCustomValidity('');
 comment.placeholder = comment.required ? 'Motivo obligatorio del rechazo, visible para el estudiante': 'Comentario visible para el estudiante (opcional)';
  }
});
document.addEventListener('click', (event) => {
 const button = event.target.closest('button[name="decision"]');
 if (button?.value === 'aprobar') button.form?.querySelector('[data-review-comment]')?.setCustomValidity('');
});
// Anticipa errores antes del envío; el servidor valida nuevamente.
document.addEventListener('submit', (event) => {
 const form = event.target;
 if (!(form instanceof HTMLFormElement)) return;
 if (form.matches('[data-review-form]')) {
 const decision = event.submitter?.value;
 const comment = form.querySelector('[data-review-comment]');
 const needsComment = decision === 'rechazar' || decision === 'solicitar_cambios';
 if (comment) {
 comment.required = needsComment;
 comment.setCustomValidity(needsComment && !comment.value.trim() ? 'Escribe un comentario para continuar.': '');
 if (needsComment && !comment.reportValidity()) event.preventDefault();
    }
  }
 if (form.matches('[data-application-review]')) {
 const state = form.querySelector('select[name="estado"]');
 const comment = form.querySelector('[data-application-comment]');
 if (state?.value === 'rechazada' && comment) {
 comment.required = true;
 comment.setCustomValidity(comment.value.trim() ? '': 'Escribe el motivo del rechazo para el estudiante.');
 if (!comment.reportValidity()) event.preventDefault();
    }
  }
});
document.addEventListener('input', (event) => {
 const field = event.target;
 if (field instanceof HTMLTextAreaElement && field.hasAttribute('data-review-comment')) field.setCustomValidity('');
 if (field instanceof HTMLTextAreaElement && field.hasAttribute('data-application-comment')) field.setCustomValidity('');
});
