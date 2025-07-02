document.addEventListener("DOMContentLoaded", () => {
  const dateEl = document.getElementById("date");
  const today = new Date();
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  dateEl.textContent = today.toLocaleDateString('en-US', options);
});
