/* MIT. All actions local; no tracking or network requests. */
document.querySelectorAll('[data-copy]').forEach(button => button.addEventListener('click', async () => {
  const target = document.getElementById(button.dataset.copy);
  try { await navigator.clipboard.writeText(target.textContent); button.textContent = 'Скопировано'; }
  catch { const range=document.createRange(); range.selectNodeContents(target); const selection=window.getSelection(); selection.removeAllRanges(); selection.addRange(range); button.textContent='Текст выделен, скопируйте вручную'; }
}));
document.getElementById('generate-key')?.addEventListener('click', () => {
  const bytes = crypto.getRandomValues(new Uint8Array(24));
  document.getElementById('generated-key').textContent = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
  document.getElementById('key-result').hidden = false;
});
