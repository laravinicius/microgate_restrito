
(() => {
	const STORAGE_KEY = 'microgate-theme';
	const root = document.documentElement;

	function systemPrefersDark() {
		return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
	}

	function getStoredTheme() {
		try {
			const value = localStorage.getItem(STORAGE_KEY);
			return value === 'light' || value === 'dark' ? value : null;
		} catch (error) {
			return null;
		}
	}

	function resolveInitialTheme() {
		const stored = getStoredTheme();
		if (stored) return stored;
		return systemPrefersDark() ? 'dark' : 'light';
	}

	function saveTheme(theme) {
		try {
			localStorage.setItem(STORAGE_KEY, theme);
		} catch (error) {
			// Ignora erros de armazenamento (modo privado/permissoes).
		}
	}

	function applyTheme(theme) {
		const next = theme === 'light' ? 'light' : 'dark';
		root.classList.remove('light', 'dark');
		root.classList.add(next);
		root.setAttribute('data-theme', next);
		updateToggleButton(next);
	}

	function setTheme(theme) {
		applyTheme(theme);
		saveTheme(theme);
	}

	function toggleTheme() {
		const current = root.classList.contains('light') ? 'light' : 'dark';
		setTheme(current === 'dark' ? 'light' : 'dark');
	}

	function updateToggleButton(theme) {
		const button = document.getElementById('theme-toggle-button');
		if (!button) return;

		const isLight = theme === 'light';
		button.setAttribute('aria-pressed', isLight ? 'true' : 'false');
		button.setAttribute('aria-label', isLight ? 'Ativar modo escuro' : 'Ativar modo claro');
		button.textContent = isLight ? '☾' : '☀';
	}

	function mountToggleButton() {
		if (document.getElementById('theme-toggle-button')) return;

		const button = document.createElement('button');
		button.id = 'theme-toggle-button';
		button.type = 'button';
		button.className = 'theme-toggle-button';
		button.addEventListener('click', toggleTheme);

		document.body.appendChild(button);
		updateToggleButton(root.classList.contains('light') ? 'light' : 'dark');
	}

	// Aplica o tema o mais cedo possivel para evitar flicker.
	applyTheme(resolveInitialTheme());

	document.addEventListener('DOMContentLoaded', () => {
		mountToggleButton();
	});

	window.MicrogateTheme = {
		setTheme,
		toggleTheme,
		getTheme: () => (root.classList.contains('light') ? 'light' : 'dark'),
	};
})();