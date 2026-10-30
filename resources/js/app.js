const themeButton = document.getElementById('theme-toggle');
function updateThemeButton() {
    const dark = document.documentElement.classList.contains('dark');
    themeButton?.setAttribute('aria-pressed', String(dark));
    if (themeButton) themeButton.textContent = dark ? 'Tema escuro: ativado' : 'Tema escuro: desativado';
}
themeButton?.addEventListener('click', () => {
    const dark = document.documentElement.classList.toggle('dark');
    try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (error) { /* Tema permanece nesta página. */ }
    updateThemeButton();
});
updateThemeButton();
