// Simple client-side validation for matching passwords
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form');
  if (!form) return;
  form.addEventListener('submit', e => {
    const pw1 = form.querySelector('input[name="password"]')?.value;
    const pw2 = form.querySelector('input[name="password2"]')?.value;
    if (pw1 !== pw2) {
      alert('Passwords do not match.');
      e.preventDefault();
    }
  });
});