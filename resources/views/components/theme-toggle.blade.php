@props(['variant' => 'default'])

<div {{ $attributes->class([
    'theme-switch',
    'theme-switch--on-dark' => $variant === 'on-dark',
]) }} role="group" aria-label="Thème d’affichage">
    <button type="button" class="theme-switch-button" data-theme-choice="system"
            title="Suivre le thème de l’ordinateur" aria-pressed="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <rect x="3" y="4" width="18" height="12" rx="2" />
            <path d="M8 20h8M12 16v4" stroke-linecap="round" />
        </svg>
        <span class="sr-only">Thème automatique</span>
    </button>
    <button type="button" class="theme-switch-button" data-theme-choice="light"
            title="Thème clair" aria-pressed="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke-linecap="round" />
        </svg>
        <span class="sr-only">Thème clair</span>
    </button>
    <button type="button" class="theme-switch-button" data-theme-choice="dark"
            title="Thème sombre" aria-pressed="false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="sr-only">Thème sombre</span>
    </button>
</div>
