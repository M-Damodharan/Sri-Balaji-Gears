const themeStorageKey = 'sbg-theme';
const validThemes = ['dark', 'light'];

try {
  const savedTheme = localStorage.getItem(themeStorageKey);
  document.documentElement.dataset.theme = validThemes.includes(savedTheme) ? savedTheme : 'dark';
} catch (error) {
  console.warn('Theme preference could not be loaded.', error);
  document.documentElement.dataset.theme = 'dark';
}

document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('#themeToggle');
  if (!toggle) return;

  const updateToggle = theme => {
    const isLight = theme === 'light';
    toggle.setAttribute('aria-pressed', String(isLight));
    toggle.setAttribute('aria-label', `Switch to ${isLight ? 'dark' : 'light'} mode`);
    toggle.title = `Switch to ${isLight ? 'dark' : 'light'} mode`;
    toggle.querySelector('.theme-icon').textContent = isLight ? '\u263E' : '\u2600';
    toggle.querySelector('.theme-label').textContent = isLight ? 'Dark' : 'Light';
  };

  updateToggle(document.documentElement.dataset.theme);
  toggle.addEventListener('click', () => {
    const theme = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
    document.documentElement.dataset.theme = theme;
    updateToggle(theme);

    try {
      localStorage.setItem(themeStorageKey, theme);
    } catch (error) {
      console.warn('Theme preference could not be saved.', error);
    }
  });
});
