const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Formate un nombre à la française (séparateur de milliers : espace,
 * séparateur décimal : virgule) comme le fait number_format() en PHP.
 */
function formatNumber(value, decimals) {
    const fixed = value.toFixed(decimals);
    const [integer, fraction] = fixed.split('.');

    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

    return fraction ? `${grouped},${fraction}` : grouped;
}

/**
 * Anime les valeurs chiffrées (indicateurs du tableau de bord).
 */
function initCounters() {
    const counters = document.querySelectorAll('[data-count]');

    if (counters.length === 0) {
        return;
    }

    const run = (element) => {
        const target = parseFloat(element.dataset.count);

        if (Number.isNaN(target)) {
            return;
        }

        const decimals = parseInt(element.dataset.countDecimals ?? '0', 10);
        const suffix = element.dataset.countSuffix ?? '';
        const duration = parseInt(element.dataset.countDuration ?? '900', 10);

        if (REDUCED_MOTION) {
            element.textContent = `${formatNumber(target, decimals)}${suffix}`;

            return;
        }

        const startedAt = performance.now();

        const tick = (now) => {
            const elapsed = Math.min((now - startedAt) / duration, 1);
            const eased = 1 - Math.pow(1 - elapsed, 3);
            const value = target * eased;

            element.textContent = `${formatNumber(value, decimals)}${suffix}`;

            if (elapsed < 1) {
                requestAnimationFrame(tick);
            } else {
                element.textContent = `${formatNumber(target, decimals)}${suffix}`;
            }
        };

        requestAnimationFrame(tick);
    };

    if (!('IntersectionObserver' in window)) {
        counters.forEach(run);

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                run(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.35 });

    counters.forEach((counter) => observer.observe(counter));
}

/**
 * Révèle les éléments marqués [data-reveal] lorsqu'ils entrent dans l'écran.
 * Les enfants d'un [data-reveal-group] sont décalés pour un effet d'escalier.
 */
function initReveal() {
    const elements = [...document.querySelectorAll('[data-reveal]')];

    if (elements.length === 0) {
        return;
    }

    document.querySelectorAll('[data-reveal-group]').forEach((group) => {
        const step = parseInt(group.dataset.revealGroup, 10) || 70;

        group.querySelectorAll('[data-reveal]').forEach((child, index) => {
            child.style.transitionDelay = `${index * step}ms`;
        });
    });

    if (REDUCED_MOTION || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-visible'));

        return;
    }

    const pending = new Set(elements);

    const reveal = (element) => {
        element.classList.add('is-visible');
        pending.delete(element);
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                reveal(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px 40px 0px' });

    elements.forEach((element) => {
        element.classList.add('reveal');
        observer.observe(element);
    });

    // Un défilement rapide peut franchir un élément sans déclencher
    // l'observateur : on révèle alors tout ce qui est déjà au-dessus de la
    // fenêtre, sinon la carte resterait invisible jusqu'au retour en arrière.
    let scheduled = false;

    const sweep = () => {
        scheduled = false;

        pending.forEach((element) => {
            if (element.getBoundingClientRect().top < window.innerHeight) {
                reveal(element);
                observer.unobserve(element);
            }
        });
    };

    window.addEventListener('scroll', () => {
        if (scheduled || pending.size === 0) {
            return;
        }

        scheduled = true;
        window.requestAnimationFrame(sweep);
    }, { passive: true });
}

/**
 * Anime l'entrée des lignes de tableau, ligne par ligne.
 */
function initTableRows() {
    document.querySelectorAll('tbody').forEach((body) => {
        body.querySelectorAll('tr').forEach((row, index) => {
            row.classList.add('row-in');
            row.style.setProperty('--row-index', index);
        });
    });
}

/**
 * Barre de progression et fondu de la page lors de la navigation interne.
 */
function initNavigation() {
    const bar = document.createElement('div');
    bar.className = 'nav-progress';
    bar.setAttribute('aria-hidden', 'true');
    document.body.appendChild(bar);

    const isInternal = (link) => {
        if (!link || link.target === '_blank' || link.hasAttribute('download')) {
            return false;
        }

        if (link.origin !== window.location.origin) {
            return false;
        }

        return !link.hasAttribute('data-no-transition');
    };

    /**
     * Affiche la barre de progression et lance le fondu de la page.
     * Le fondu est annulé si la navigation n'a pas lieu dans les délais.
     */
    const leave = () => {
        bar.classList.add('is-active');
        document.body.classList.add('is-leaving');

        window.setTimeout(() => {
            document.body.classList.remove('is-leaving');
        }, 1200);
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!isInternal(link) || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
            return;
        }

        leave();
    });

    // Filtres GET : on anime sans quitter la page.
    document.addEventListener('submit', (event) => {
        if (event.target.method.toLowerCase() === 'get') {
            leave();
        }
    });
}

/**
 * Met en évidence la navigation active et prépare les micro-interactions.
 */
function initNavigationHighlight() {
    document.querySelectorAll('nav a[href]').forEach((link) => {
        link.classList.add('nav-link');
    });
}

/**
 * Inclinaison 3D douce des cartes marquées [data-tilt].
 */
function initTiltCards() {
    const cards = [...document.querySelectorAll('[data-tilt]')];

    if (REDUCED_MOTION || cards.length === 0 || window.matchMedia('(pointer: coarse)').matches) {
        return;
    }

    cards.forEach((card) => {
        card.classList.add('tilt-card');

        let frame = null;

        const update = (event) => {
            if (frame !== null) {
                return;
            }

            frame = window.requestAnimationFrame(() => {
                frame = null;

                const rect = card.getBoundingClientRect();
                const x = (event.clientX - rect.left) / rect.width;
                const y = (event.clientY - rect.top) / rect.height;

                card.style.setProperty('--tilt-x', `${(0.5 - y) * 6}deg`);
                card.style.setProperty('--tilt-y', `${(x - 0.5) * 8}deg`);
            });
        };

        const reset = () => {
            card.style.setProperty('--tilt-x', '0deg');
            card.style.setProperty('--tilt-y', '0deg');
        };

        card.addEventListener('pointermove', update);
        card.addEventListener('pointerleave', reset);
        card.addEventListener('blur', reset, true);
    });
}

/**
 * Halo lumineux qui suit le curseur sur les cartes marquées [data-spotlight].
 */
function initSpotlightCards() {
    const cards = [...document.querySelectorAll('[data-spotlight]')];

    if (REDUCED_MOTION || cards.length === 0) {
        return;
    }

    cards.forEach((card) => {
        card.classList.add('spotlight-card');

        card.addEventListener('pointermove', (event) => {
            const rect = card.getBoundingClientRect();

            card.style.setProperty('--spot-x', `${event.clientX - rect.left}px`);
            card.style.setProperty('--spot-y', `${event.clientY - rect.top}px`);
        });
    });
}

/**
 * Parallaxe légère des éléments décoratifs marqués [data-parallax].
 * La valeur de l'attribut est la vitesse (ex. 0.15).
 */
function initParallax() {
    const elements = [...document.querySelectorAll('[data-parallax]')];

    if (REDUCED_MOTION || elements.length === 0) {
        return;
    }

    let scheduled = false;

    const update = () => {
        scheduled = false;
        const offset = window.scrollY;

        elements.forEach((element) => {
            const speed = parseFloat(element.dataset.parallax) || 0.1;
            element.style.transform = `translate3d(0, ${offset * speed}px, 0)`;
        });
    };

    window.addEventListener('scroll', () => {
        if (scheduled) {
            return;
        }

        scheduled = true;
        window.requestAnimationFrame(update);
    }, { passive: true });

    update();
}

/**
 * Boutons « afficher / masquer » des champs mot de passe.
 */
function initPasswordToggles() {
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        const input = document.getElementById(button.dataset.togglePassword);

        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const isHidden = input.type === 'password';

            input.type = isHidden ? 'text' : 'password';
            button.classList.toggle('text-emerald-700', isHidden);
        });
    });
}

/**
 * Petite onde au clic sur les boutons.
 */
function initButtonFeedback() {
    document.addEventListener('pointerdown', (event) => {
        const button = event.target.closest('button, a[role="button"]');

        if (!button) {
            return;
        }

        button.classList.add('scale-95');
        window.setTimeout(() => button.classList.remove('scale-95'), 140);
    });
}

function init() {
    initReveal();
    initCounters();
    initTableRows();
    initNavigation();
    initNavigationHighlight();
    initTiltCards();
    initSpotlightCards();
    initParallax();
    initPasswordToggles();
    initButtonFeedback();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
