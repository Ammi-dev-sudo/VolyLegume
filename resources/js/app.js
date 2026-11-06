import 'leaflet/dist/leaflet.css';
import { initOffline } from './offline';

const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const SATELLITE_TILE_URL = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';
const SATELLITE_ATTRIBUTION = 'Source: &copy; <a href="https://www.esri.com/" target="_blank" rel="noopener">Esri</a>, Vantor, Earthstar Geographics, GIS User Community';

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

function initScrollProgress() {
    if (REDUCED_MOTION) {
        return;
    }

    const progress = document.createElement('div');
    progress.className = 'scroll-progress';
    progress.setAttribute('aria-hidden', 'true');
    document.body.appendChild(progress);

    let scheduled = false;

    const update = () => {
        scheduled = false;

        const scrollableHeight = document.documentElement.scrollHeight - window.innerHeight;
        const percentage = scrollableHeight > 0 ? window.scrollY / scrollableHeight : 0;

        progress.style.transform = `scaleX(${Math.min(1, Math.max(0, percentage))})`;
        progress.classList.toggle('is-visible', window.scrollY > 24);
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

function initSidebarToggle() {
    const frame = document.querySelector('.workspace-frame:not(.workspace-frame--solo)');
    const sidebar = document.getElementById('workspace-sidebar');
    const toggles = [...document.querySelectorAll('[data-sidebar-toggle]')];
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    if (!frame || !sidebar || toggles.length === 0 || !backdrop) {
        return;
    }

    const mobile = window.matchMedia('(max-width: 1023px)');
    const preferenceKey = 'volylegume.sidebar.collapsed';
    let mobileOpen = false;

    const updateControls = () => {
        const collapsed = frame.classList.contains('workspace-frame--collapsed');
        const expanded = mobile.matches ? mobileOpen : !collapsed;

        toggles.forEach((toggle) => {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.setAttribute(
                'aria-label',
                mobile.matches
                    ? (mobileOpen ? 'Fermer le menu' : 'Ouvrir le menu')
                    : (collapsed ? 'Afficher le menu' : 'Masquer le menu'),
            );
        });

        document.body.classList.toggle('workspace-sidebar-open', mobile.matches && mobileOpen);
        backdrop.hidden = !mobile.matches || !mobileOpen;
    };

    frame.classList.toggle(
        'workspace-frame--collapsed',
        !mobile.matches && window.localStorage.getItem(preferenceKey) === 'true',
    );
    updateControls();

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
            if (mobile.matches) {
                mobileOpen = !mobileOpen;
            } else {
                const collapsed = frame.classList.toggle('workspace-frame--collapsed');
                window.localStorage.setItem(preferenceKey, collapsed ? 'true' : 'false');
            }

            updateControls();
        });
    });

    const closeMobileMenu = () => {
        if (!mobile.matches || !mobileOpen) {
            return;
        }

        mobileOpen = false;
        updateControls();
    };

    backdrop.addEventListener('click', closeMobileMenu);
    sidebar.addEventListener('click', (event) => {
        if (event.target.closest('a[href]')) {
            closeMobileMenu();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobileMenu();
        }
    });
    mobile.addEventListener('change', () => {
        mobileOpen = false;
        updateControls();
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

                card.style.setProperty('--tilt-x', `${(0.5 - y) * 3}deg`);
                card.style.setProperty('--tilt-y', `${(x - 0.5) * 4}deg`);
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

        if (REDUCED_MOTION) {
            return;
        }

        button.classList.add('has-ripple');

        const bounds = button.getBoundingClientRect();
        const ripple = document.createElement('span');
        const diameter = Math.max(bounds.width, bounds.height) * 1.8;

        ripple.className = 'click-ripple';
        ripple.setAttribute('aria-hidden', 'true');
        ripple.style.width = `${diameter}px`;
        ripple.style.height = `${diameter}px`;
        ripple.style.left = `${event.clientX - bounds.left - diameter / 2}px`;
        ripple.style.top = `${event.clientY - bounds.top - diameter / 2}px`;
        button.append(ripple);

        ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
    });
}

/**
 * Géométrie côté navigateur : mêmes formules que App\Support\ParcelBoundary.
 */
function boundaryPointsFromValue(value) {
    if (!value) {
        return [];
    }

    let data = value;

    if (typeof value === 'string') {
        try {
            data = JSON.parse(value);
        } catch (error) {
            return [];
        }
    }

    if (Array.isArray(data) && data.length > 0 && Array.isArray(data[0]) && typeof data[0][0] === 'number') {
        data = { type: 'Polygon', coordinates: [data] };
    }

    const ring = data?.coordinates?.[0];

    if (!Array.isArray(ring)) {
        return [];
    }

    const points = ring
        .filter((point) => Array.isArray(point) && point.length >= 2)
        .map((point) => [Number(point[0]), Number(point[1])])
        .filter(([lng, lat]) => Number.isFinite(lng) && Number.isFinite(lat));

    if (points.length > 1) {
        const first = points[0];
        const last = points[points.length - 1];

        if (Math.abs(first[0] - last[0]) < 1e-10 && Math.abs(first[1] - last[1]) < 1e-10) {
            points.pop();
        }
    }

    return points;
}

function boundaryToGeoJson(points) {
    const ring = points.map(([lng, lat]) => [lng, lat]);

    if (ring.length > 2) {
        ring.push([ring[0][0], ring[0][1]]);
    }

    return { type: 'Polygon', coordinates: [ring] };
}

function boundaryAreaM2(points) {
    if (points.length < 3) {
        return 0;
    }

    const radius = 6378137;
    const radians = Math.PI / 180;
    const meanLatitude = points.reduce((sum, point) => sum + point[1], 0) / points.length;
    const cosLatitude = Math.cos(meanLatitude * radians);
    const projected = points.map(([lng, lat]) => [
        lng * radians * cosLatitude * radius,
        lat * radians * radius,
    ]);

    let sum = 0;

    projected.forEach((point, index) => {
        const next = projected[(index + 1) % projected.length];
        sum += point[0] * next[1] - next[0] * point[1];
    });

    return Math.abs(sum) / 2;
}

function boundaryPerimeterM(points) {
    if (points.length < 2) {
        return 0;
    }

    const radius = 6378137;
    const radians = Math.PI / 180;
    let perimeter = 0;

    points.forEach((point, index) => {
        const next = points[(index + 1) % points.length];
        const lat1 = point[1] * radians;
        const lat2 = next[1] * radians;
        const deltaLat = lat2 - lat1;
        const deltaLng = (next[0] - point[0]) * radians;
        const a = Math.sin(deltaLat / 2) ** 2
            + Math.cos(lat1) * Math.cos(lat2) * Math.sin(deltaLng / 2) ** 2;

        perimeter += 2 * radius * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
    });

    return perimeter;
}

/**
 * Carte 2D Leaflet : contours réels, zoom, déplacement et sélection.
 */
async function createParcelMap(container, config, onSelect) {
    const L = (await import('leaflet')).default;
    const map = L.map(container, {
        zoomControl: true,
        scrollWheelZoom: true,
        worldCopyJump: true,
    });

    const panel = container.closest('[data-map-panel]');
    const tileStatus = panel?.querySelector('[data-map-tile-status]') ?? null;
    const basemapButtons = panel ? [...panel.querySelectorAll('[data-map-basemap]')] : [];
    const satelliteTiles = L.tileLayer(SATELLITE_TILE_URL, {
        maxZoom: 19,
        maxNativeZoom: 19,
        attribution: SATELLITE_ATTRIBUTION,
    });
    const planTiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    });
    const basemapLayers = {
        satellite: satelliteTiles,
        plan: planTiles,
    };
    let activeBasemap = 'satellite';

    const currentTiles = () => basemapLayers[activeBasemap];

    const syncBasemapUi = () => {
        basemapButtons.forEach((button) => {
            const active = button.dataset.mapBasemap === activeBasemap;

            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        container.classList.toggle('is-satellite', activeBasemap === 'satellite');
    };

    const showTileStatus = (message) => {
        if (tileStatus) {
            tileStatus.textContent = message;
            tileStatus.hidden = false;
        }
    };

    const hideTileStatus = () => {
        if (tileStatus) {
            tileStatus.hidden = true;
        }
    };

    const setBasemap = (key) => {
        if (!basemapLayers[key] || key === activeBasemap) {
            return;
        }

        const previous = currentTiles();
        activeBasemap = key;

        if (map.hasLayer(previous)) {
            map.removeLayer(previous);
        }

        currentTiles().addTo(map);
        syncBasemapUi();
        hideTileStatus();
        currentTiles().redraw();
    };

    basemapButtons.forEach((button) => {
        button.addEventListener('click', () => setBasemap(button.dataset.mapBasemap));
    });

    Object.entries(basemapLayers).forEach(([key, layer]) => {
        layer.on('tileerror', () => {
            if (key !== activeBasemap) {
                return;
            }

            showTileStatus(
                key === 'satellite'
                    ? 'Imagerie satellite indisponible (réseau faible). Passez au fond Plan ou réessayez.'
                    : 'Fond de plan indisponible (réseau faible). Les contours enregistrés restent affichés.',
            );
        });
        layer.on('tileload', () => {
            if (key === activeBasemap) {
                hideTileStatus();
            }
        });
    });

    satelliteTiles.addTo(map);
    syncBasemapUi();

    const handleOnline = () => {
        hideTileStatus();
        currentTiles().redraw();
    };
    const handleOffline = () => {
        showTileStatus('Hors connexion : le fond de carte ne peut pas être chargé. Les contours restent affichés.');
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }

    const units = Array.isArray(config.units) ? config.units : [];
    const unitsById = new Map(units.map((unit) => [Number(unit.id), unit]));
    const polygons = new Map();
    const missingMarkers = new Map();
    const bounds = L.latLngBounds([]);
    let selectedId = null;
    let hoveredId = null;
    let harvestFilterStates = null;
    const tooltipText = (text) => {
        const element = document.createElement('span');
        element.textContent = text;

        return element;
    };

    const unitMatchesHarvestFilter = (unit) => (
        harvestFilterStates === null
        || (unit?.harvest_state?.key && harvestFilterStates.has(unit.harvest_state.key))
    );

    const polygonStyle = (id) => {
        const unit = unitsById.get(id);
        const selected = id === selectedId;
        const hovered = id === hoveredId;
        const stateColor = activeLayerKeys.has('harvest_state') ? unit?.harvest_state?.color : null;

        if (!unitMatchesHarvestFilter(unit)) {
            return {
                color: '#94a3b8',
                weight: 1,
                opacity: 0.25,
                fillColor: '#cbd5e1',
                fillOpacity: 0.05,
            };
        }

        return {
            color: selected ? '#f59e0b' : (hovered ? '#fbbf24' : (stateColor || unit?.color_dark || unit?.color || '#0f766e')),
            weight: selected ? 4 : (hovered ? 3 : 2),
            fillColor: stateColor || unit?.color || '#0f766e',
            fillOpacity: selected ? 0.7 : (hovered ? 0.6 : 0.45),
        };
    };

    const circleStyle = (id) => {
        const unit = unitsById.get(id);
        const selected = id === selectedId;
        const hovered = id === hoveredId;
        const stateColor = activeLayerKeys.has('harvest_state') ? unit?.harvest_state?.color : null;

        if (!unitMatchesHarvestFilter(unit)) {
            return {
                radius: 6,
                color: '#94a3b8',
                weight: 1,
                opacity: 0.25,
                fillColor: '#cbd5e1',
                fillOpacity: 0.2,
            };
        }

        return {
            radius: selected ? 11 : (hovered ? 10 : 8),
            color: selected ? '#f59e0b' : (hovered ? '#fbbf24' : (stateColor || '#b45309')),
            weight: selected ? 4 : (hovered ? 3 : 2),
            fillColor: selected ? '#fbbf24' : (stateColor || '#f59e0b'),
        };
    };

    const applyUnitStyles = () => {
        polygons.forEach((polygon, id) => polygon.setStyle(polygonStyle(id)));
        missingMarkers.forEach((marker, id) => {
            marker.setStyle(circleStyle(id));
            marker.setRadius(circleStyle(id).radius);
        });
    };

    const makeFocusable = (layer, id, label) => {
        const attach = () => {
            const element = layer.getElement();

            if (!element || element.dataset.mapFocusable === 'true') {
                return;
            }

            element.dataset.mapFocusable = 'true';
            element.setAttribute('tabindex', '0');
            element.setAttribute('role', 'button');
            element.setAttribute('aria-label', label);
            element.addEventListener('focus', () => {
                hoveredId = id;
                applyUnitStyles();
            });
            element.addEventListener('blur', () => {
                if (hoveredId === id) {
                    hoveredId = null;
                    applyUnitStyles();
                }
            });
            element.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    onSelect(id);
                }
            });
        };

        layer.on('add', attach);
        attach();
    };

    const layerKeys = [
        'crops',
        'upcoming_activities',
        'late_activities',
        'irrigation',
        'alerts',
        'campaigns',
        'harvests',
        'status',
        'harvest_state',
    ];
    const layerGroups = new Map(layerKeys.map((key) => [key, L.layerGroup()]));
    const layerMarkers = new Map(layerKeys.map((key) => [key, new Map()]));
    const activeLayerKeys = new Set();
    const numberFr = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 });
    const layerOffsets = {
        crops: -140,
        upcoming_activities: -112,
        late_activities: -84,
        irrigation: -56,
        alerts: -28,
        campaigns: 0,
        harvests: 28,
        status: 56,
        harvest_state: 84,
    };

    const layerBadge = (key, unit) => {
        const data = unit.layers?.[key];

        if (!data || data.available !== true) {
            return null;
        }

        const badge = document.createElement('div');
        badge.className = `map-layer-marker map-layer-marker--${key}`;
        badge.style.setProperty('--layer-offset', `${layerOffsets[key] ?? 0}px`);

        const icon = document.createElement('span');
        icon.className = 'map-layer-marker__icon';
        icon.setAttribute('aria-hidden', 'true');

        const label = document.createElement('span');
        label.className = 'map-layer-marker__label';
        const irrigationAccents = {
            ok: '#0ea5e9',
            approaching: '#f59e0b',
            exceeded: '#dc2626',
            unquoted: '#64748b',
            unavailable: '#94a3b8',
        };

        let title = `${unit.code} · ${unit.name}`;
        let text = '';
        let accent = unit.color;

        if (key === 'crops') {
            text = data.primary_label ?? `${data.count} culture(s)`;
            title += ` · Cultures : ${(data.labels ?? []).join(', ')}`;
        } else if (key === 'campaigns') {
            text = `${data.count} campagne${data.count > 1 ? 's' : ''}`;
            title += ` · ${(data.labels ?? []).join(' · ')}`;
            accent = data.color ?? accent;
        } else if (key === 'upcoming_activities') {
            text = `${data.count} à venir`;
            title += ` · ${(data.labels ?? []).join(' · ')}`;
            accent = '#0284c7';
        } else if (key === 'late_activities') {
            text = `${data.count} en retard`;
            title += ` · ${(data.labels ?? []).join(' · ')}`;
            icon.textContent = '!';
            accent = '#dc2626';
        } else if (key === 'harvests') {
            text = `${data.count} récolte${data.count > 1 ? 's' : ''}`;
            title += ` · ${(data.labels ?? []).join(' · ')}`;
            accent = '#65a30d';
        } else if (key === 'alerts') {
            text = `${data.count} alerte${data.count > 1 ? 's' : ''}`;
            title += ` · ${(data.labels ?? []).join(' · ')}`;
            icon.textContent = '!';
            accent = '#dc2626';
        } else if (key === 'irrigation') {
            text = data.usage_percent !== null
                ? `Eau ${numberFr.format(data.usage_percent)} %`
                : (data.events_count > 0 ? `Eau ${numberFr.format(data.used_m3)} m³` : 'Eau 0 %');
            title += ` · ${data.status_label} · ${numberFr.format(data.used_m3)} m³ relevés`;
            if (data.quota_m3 !== null) {
                title += ` sur ${numberFr.format(data.quota_m3)} m³`;
            }
            if (data.last_irrigated_at) {
                title += ` · dernier arrosage ${data.last_irrigated_at}`;
            }
            accent = irrigationAccents[data.status] ?? irrigationAccents.unquoted;
            badge.dataset.status = data.status;
        } else if (key === 'status') {
            text = data.label;
            title += ` · État : ${data.label}`;
            accent = data.color;
            badge.dataset.status = data.value;
        } else if (key === 'harvest_state') {
            text = data.label;
            title += ` · Récolte : ${data.label}`;
            if (data.reason) {
                title += ` · ${data.reason}`;
            }
            if (data.last_harvested_at) {
                title += ` · dernière récolte ${data.last_harvested_at}`;
            }
            if (data.next_planned_date) {
                title += ` · prochaine récolte ${data.next_planned_date}`;
            }
            accent = data.color;
            badge.dataset.status = data.key;
        }

        label.textContent = text;
        badge.style.setProperty('--layer-accent', accent);
        badge.append(icon, label);
        badge.title = title;
        badge.dataset.unit = String(unit.id);

        return badge;
    };

    units.forEach((unit) => {
        const id = Number(unit.id);
        const points = Array.isArray(unit.points) ? unit.points : [];

        if (unit.has_boundary && points.length >= 3) {
            const latlngs = points.map(([lng, lat]) => [lat, lng]);
            latlngs.forEach((latlng) => bounds.extend(latlng));

            const polygon = L.polygon(latlngs, {
                color: unit.color_dark || unit.color,
                weight: 2,
                fillColor: unit.color,
                fillOpacity: 0.45,
                lineJoin: 'round',
            }).addTo(map);

            polygon.bindTooltip(tooltipText(`${unit.code} · ${unit.name}`), {
                sticky: true,
                direction: 'top',
                opacity: 0.92,
            });
            polygon.on('click', () => onSelect(id));
            polygon.on('mouseover', () => {
                hoveredId = id;
                applyUnitStyles();
                polygon.bringToFront();
            });
            polygon.on('mouseout', () => {
                if (hoveredId === id && document.activeElement !== polygon.getElement()) {
                    hoveredId = null;
                    applyUnitStyles();
                }
            });
            makeFocusable(polygon, id, `Parcelle ${unit.code} · ${unit.name}. Ouvrir la fiche.`);
            polygon.setStyle(polygonStyle(id));
            polygons.set(id, polygon);

            return;
        }

        if (unit.point && Number.isFinite(unit.point.lat) && Number.isFinite(unit.point.lng)) {
            const latlng = [unit.point.lat, unit.point.lng];
            bounds.extend(latlng);

            const marker = L.circleMarker(latlng, {
                radius: 8,
                color: '#b45309',
                weight: 2,
                dashArray: '4 4',
                fillColor: '#f59e0b',
                fillOpacity: 0.85,
            }).addTo(map);

            marker.bindTooltip(tooltipText(`${unit.code} · ${unit.name} — position connue, contour à dessiner`), {
                direction: 'top',
                opacity: 0.92,
            });
            marker.on('click', () => onSelect(id));
            marker.on('mouseover', () => {
                hoveredId = id;
                applyUnitStyles();
            });
            marker.on('mouseout', () => {
                if (hoveredId === id && document.activeElement !== marker.getElement()) {
                    hoveredId = null;
                    applyUnitStyles();
                }
            });
            makeFocusable(marker, id, `Parcelle ${unit.code} · ${unit.name}, position connue sans contour. Ouvrir la fiche.`);
            marker.setStyle(circleStyle(id));
            marker.setRadius(circleStyle(id).radius);
            missingMarkers.set(id, marker);
        }
    });

    units.forEach((unit) => {
        const point = unit.point;

        if (!point || !Number.isFinite(point.lat) || !Number.isFinite(point.lng)) {
            return;
        }

        const id = Number(unit.id);
        const latlng = [point.lat, point.lng];

        layerKeys.forEach((key) => {
            const badge = layerBadge(key, unit);

            if (!badge) {
                return;
            }

            const marker = L.marker(latlng, {
                icon: L.divIcon({
                    className: 'map-layer-icon',
                    html: badge,
                    iconSize: [0, 0],
                    iconAnchor: [0, 0],
                }),
                title: badge.title,
                alt: badge.title,
                keyboard: true,
                riseOnHover: true,
            });

            marker.on('click', () => onSelect(id));
            layerMarkers.get(key).set(id, marker);
        });
    });

    const syncLayer = (key) => {
        const group = layerGroups.get(key);

        if (!group) {
            return;
        }

        group.clearLayers();

        if (!activeLayerKeys.has(key)) {
            if (map.hasLayer(group)) {
                map.removeLayer(group);
            }

            return;
        }

        if (!map.hasLayer(group)) {
            group.addTo(map);
        }

        layerMarkers.get(key).forEach((marker, unitId) => {
            if (unitMatchesHarvestFilter(unitsById.get(unitId))) {
                group.addLayer(marker);
            }
        });
    };

    const setLayers = (keys) => {
        activeLayerKeys.clear();

        (Array.isArray(keys) ? keys : []).forEach((key) => {
            if (layerGroups.has(key)) {
                activeLayerKeys.add(key);
            }
        });

        layerKeys.forEach(syncLayer);
        applyUnitStyles();
    };

    const setHarvestFilter = (states) => {
        harvestFilterStates = Array.isArray(states) && states.length > 0
            ? new Set(states.map(String))
            : null;

        layerKeys.forEach(syncLayer);
        applyUnitStyles();
    };

    const fit = () => {
        if (bounds.isValid()) {
            map.fitBounds(bounds.pad(0.18), { padding: [24, 24], maxZoom: 18 });

            return;
        }

        if (config.farm && Number.isFinite(config.farm.latitude) && Number.isFinite(config.farm.longitude)) {
            map.setView([config.farm.latitude, config.farm.longitude], 16);

            return;
        }

        map.setView([20, 0], 2);
    };

    fit();

    const setSelected = (id) => {
        selectedId = id === null || id === undefined ? null : Number(id);

        applyUnitStyles();

        if (selectedId !== null && polygons.has(selectedId)) {
            polygons.get(selectedId).bringToFront();
        }

        layerMarkers.forEach((markers) => {
            markers.forEach((marker, unitId) => {
                const badge = marker.getElement()?.querySelector('.map-layer-marker');

                badge?.classList.toggle('is-selected', unitId === selectedId);
            });
        });
    };

    return {
        setSelected,
        setLayers,
        setHarvestFilter,
        fit,
        clear: () => setSelected(null),
        invalidate: () => map.invalidateSize({ pan: false }),
        destroy: () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
            map.remove();
        },
    };
}

/**
 * Vue 3D Three.js : extrusion des contours, caméra orbitale et sélection.
 */
async function createParcelScene(container, config, onSelect) {
    const THREE = await import('three');
    const { OrbitControls } = await import('three/addons/controls/OrbitControls.js');

    const units = (Array.isArray(config.units) ? config.units : []).filter(
        (unit) => unit.has_boundary && Array.isArray(unit.points) && unit.points.length >= 3,
    );

    if (units.length === 0) {
        return null;
    }

    const radians = Math.PI / 180;
    const radius = 6378137;
    let originLat = 0;
    let originLng = 0;
    let coordinateCount = 0;

    units.forEach((unit) => {
        unit.points.forEach(([lng, lat]) => {
            originLat += lat;
            originLng += lng;
            coordinateCount += 1;
        });
    });

    originLat /= Math.max(1, coordinateCount);
    originLng /= Math.max(1, coordinateCount);

    const cosLatitude = Math.cos(originLat * radians);
    const unitsById3d = new Map(units.map((unit) => [Number(unit.id), unit]));
    let harvestFilterStates3d = null;
    let activeLayerKeys3d = new Set();
    const unitMatchesHarvestFilter3d = (unit) => (
        harvestFilterStates3d === null
        || (unit?.harvest_state?.key && harvestFilterStates3d.has(unit.harvest_state.key))
    );
    const projected = units.map((unit) => ({
        unit,
        points: unit.points.map(([lng, lat]) => ({
            x: (lng - originLng) * radians * cosLatitude * radius,
            z: (lat - originLat) * radians * radius,
        })),
    }));

    let minX = Infinity;
    let maxX = -Infinity;
    let minZ = Infinity;
    let maxZ = -Infinity;

    projected.forEach(({ points }) => {
        points.forEach((point) => {
            minX = Math.min(minX, point.x);
            maxX = Math.max(maxX, point.x);
            minZ = Math.min(minZ, point.z);
            maxZ = Math.max(maxZ, point.z);
        });
    });

    const centerX = (minX + maxX) / 2;
    const centerZ = (minZ + maxZ) / 2;
    const span = Math.max(maxX - minX, maxZ - minZ, 10);
    const extrusionHeight = Math.max(2, span * 0.05);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(45, 1, 0.1, span * 50 + 2000);
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });

    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.domElement.style.display = 'block';
    renderer.domElement.style.width = '100%';
    renderer.domElement.style.height = '100%';
    renderer.domElement.setAttribute('role', 'application');
    container.appendChild(renderer.domElement);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.screenSpacePanning = true;
    controls.maxPolarAngle = Math.PI / 2.05;
    controls.minDistance = Math.max(5, span * 0.08);
    controls.maxDistance = span * 6 + 200;
    controls.target.set(0, extrusionHeight * 0.4, 0);

    const homePosition = new THREE.Vector3(span * 0.72, span * 0.78, span * 1.05);
    const homeTarget = new THREE.Vector3(0, extrusionHeight * 0.4, 0);

    camera.position.copy(homePosition);
    controls.target.copy(homeTarget);
    controls.update();

    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
    const groundSize = span * 2.4 + 40;
    const groundHalfSize = groundSize / 2;
    const groundMaterial = new THREE.MeshStandardMaterial({
        color: isDark() ? 0x10211a : 0xdceee2,
        roughness: 1,
        metalness: 0,
    });
    const ground = new THREE.Mesh(
        new THREE.PlaneGeometry(groundSize, groundSize),
        groundMaterial,
    );
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.05;
    ground.userData.hasImagery = false;
    scene.add(ground);

    const grid = new THREE.GridHelper(groundSize, 24, 0x64748b, 0x94a3b8);
    grid.position.y = 0;
    grid.material.transparent = true;
    grid.material.opacity = isDark() ? 0.25 : 0.3;
    scene.add(grid);

    const imageryStatus = container.closest('[data-map-panel]')?.querySelector('[data-map-3d-imagery-status]') ?? null;
    const showImageryStatus = () => {
        if (imageryStatus) {
            imageryStatus.hidden = false;
        }
    };

    const loadGroundImagery = async () => {
        try {
            const latHalf = groundHalfSize / (radians * radius);
            const lngHalf = Math.min(
                180,
                groundHalfSize / (radians * radius * Math.max(cosLatitude, 0.01)),
            );
            const west = Math.max(-180, originLng - lngHalf);
            const south = Math.max(-85, originLat - latHalf);
            const east = Math.min(180, originLng + lngHalf);
            const north = Math.min(85, originLat + latHalf);

            const lonToTile = (lng, zoom) => ((lng + 180) / 360) * 2 ** zoom;
            const latToTile = (lat, zoom) => {
                const sin = Math.sin(lat * radians);

                return (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * 2 ** zoom;
            };
            const tileRange = (zoom) => ({
                xMin: Math.floor(lonToTile(west, zoom)),
                xMax: Math.floor(lonToTile(east, zoom)),
                yMin: Math.floor(latToTile(north, zoom)),
                yMax: Math.floor(latToTile(south, zoom)),
            });

            const targetPixels = 2048;
            const desiredZoom = Math.log2(
                (156543.03392 * Math.cos(originLat * radians) * targetPixels) / groundSize,
            );
            let zoom = Math.min(19, Math.max(2, Math.floor(desiredZoom)));
            let range = tileRange(zoom);

            while ((range.xMax - range.xMin + 1) * (range.yMax - range.yMin + 1) > 144 && zoom > 2) {
                zoom -= 1;
                range = tileRange(zoom);
            }

            const maxIndex = 2 ** zoom - 1;
            const jobs = [];

            for (let y = range.yMin; y <= range.yMax; y += 1) {
                for (let x = range.xMin; x <= range.xMax; x += 1) {
                    const url = SATELLITE_TILE_URL
                        .replace('{z}', String(zoom))
                        .replace('{y}', String(Math.max(0, Math.min(maxIndex, y))))
                        .replace('{x}', String(Math.max(0, Math.min(maxIndex, x))));

                    jobs.push(new Promise((resolve, reject) => {
                        const image = new Image();
                        image.crossOrigin = 'anonymous';
                        image.decoding = 'async';
                        image.onload = () => resolve({ image, x, y });
                        image.onerror = () => reject(new Error('Satellite tile unavailable'));
                        image.src = url;
                    }));
                }
            }

            const images = await Promise.all(jobs);
            const stitched = document.createElement('canvas');
            const stitchWidth = (range.xMax - range.xMin + 1) * 256;
            const stitchHeight = (range.yMax - range.yMin + 1) * 256;

            stitched.width = stitchWidth;
            stitched.height = stitchHeight;

            const stitchContext = stitched.getContext('2d');

            images.forEach(({ image, x, y }) => {
                stitchContext.drawImage(
                    image,
                    (x - range.xMin) * 256,
                    (y - range.yMin) * 256,
                    256,
                    256,
                );
            });

            const left = lonToTile(west, zoom) * 256 - range.xMin * 256;
            const top = latToTile(north, zoom) * 256 - range.yMin * 256;
            const width = (lonToTile(east, zoom) - lonToTile(west, zoom)) * 256;
            const height = (latToTile(south, zoom) - latToTile(north, zoom)) * 256;
            const textureCanvas = document.createElement('canvas');

            textureCanvas.width = Math.max(1, Math.round(width));
            textureCanvas.height = Math.max(1, Math.round(height));

            const textureContext = textureCanvas.getContext('2d');

            textureContext.drawImage(
                stitched,
                left,
                top,
                width,
                height,
                0,
                0,
                textureCanvas.width,
                textureCanvas.height,
            );

            const texture = new THREE.CanvasTexture(textureCanvas);
            texture.colorSpace = THREE.SRGBColorSpace;
            texture.flipY = false;
            texture.anisotropy = renderer.capabilities.getMaxAnisotropy();

            groundMaterial.map = texture;
            groundMaterial.color.setHex(0xffffff);
            groundMaterial.needsUpdate = true;
            ground.userData.hasImagery = true;
            grid.material.opacity = isDark() ? 0.18 : 0.14;

            if (imageryStatus) {
                imageryStatus.hidden = true;
            }
        } catch (error) {
            showImageryStatus();
        }
    };

    loadGroundImagery();

    scene.add(new THREE.HemisphereLight(0xf8fafc, 0x1f2937, 1.45));

    const sun = new THREE.DirectionalLight(0xffffff, 2.1);
    sun.position.set(span, span * 1.8, span * 0.65);
    scene.add(sun);

    const meshes = [];
    const meshById = new Map();

    projected.forEach(({ unit, points }) => {
        const shape = new THREE.Shape();

        points.forEach((point, index) => {
            const x = point.x - centerX;
            const y = -(point.z - centerZ);

            if (index === 0) {
                shape.moveTo(x, y);
            } else {
                shape.lineTo(x, y);
            }
        });

        shape.closePath();

        const geometry = new THREE.ExtrudeGeometry(shape, {
            depth: extrusionHeight,
            bevelEnabled: false,
            steps: 1,
        });
        geometry.rotateX(-Math.PI / 2);
        geometry.computeVertexNormals();

        const topMaterial = new THREE.MeshStandardMaterial({
            color: new THREE.Color(unit.color),
            roughness: 0.65,
            metalness: 0.04,
            side: THREE.DoubleSide,
            emissive: 0x000000,
        });
        const sideMaterial = new THREE.MeshStandardMaterial({
            color: new THREE.Color(unit.color_dark || unit.color),
            roughness: 0.82,
            metalness: 0.02,
            side: THREE.DoubleSide,
            emissive: 0x000000,
        });

        const mesh = new THREE.Mesh(geometry, [topMaterial, sideMaterial]);
        const edges = new THREE.LineSegments(
            new THREE.EdgesGeometry(geometry, 25),
            new THREE.LineBasicMaterial({
                color: isDark() ? 0xd1fae5 : 0x0b1220,
                transparent: true,
                opacity: 0.35,
            }),
        );

        mesh.userData = {
            id: Number(unit.id),
            topMaterial,
            sideMaterial,
            edges,
        };
        mesh.add(edges);
        scene.add(mesh);
        meshes.push(mesh);
        meshById.set(Number(unit.id), mesh);
    });

    const layerGroups3d = new Map(
        [
            'crops',
            'upcoming_activities',
            'late_activities',
            'irrigation',
            'alerts',
            'campaigns',
            'harvests',
            'status',
            'harvest_state',
        ].map((key) => [key, new THREE.Group()]),
    );
    const numberFr3d = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 });
    const irrigationColors = {
        ok: '#0ea5e9',
        approaching: '#f59e0b',
        exceeded: '#dc2626',
        unquoted: '#64748b',
        unavailable: '#94a3b8',
    };

    layerGroups3d.forEach((group) => {
        group.visible = false;
        scene.add(group);
    });

    const roundedRect = (context, x, y, width, height, radius) => {
        const r = Math.min(radius, width / 2, height / 2);

        context.beginPath();
        context.moveTo(x + r, y);
        context.lineTo(x + width - r, y);
        context.quadraticCurveTo(x + width, y, x + width, y + r);
        context.lineTo(x + width, y + height - r);
        context.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
        context.lineTo(x + r, y + height);
        context.quadraticCurveTo(x, y + height, x, y + height - r);
        context.lineTo(x, y + r);
        context.quadraticCurveTo(x, y, x + r, y);
        context.closePath();
    };

    const labelSprite = (text, accent) => {
        const ratio = Math.min(window.devicePixelRatio || 1, 2);
        const fontSize = 28;
        const paddingX = 16;
        const paddingY = 10;
        const display = text.length > 38 ? `${text.slice(0, 35)}…` : text;
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        const font = `600 ${fontSize}px "Inter", "Segoe UI", sans-serif`;

        context.font = font;
        const width = Math.max(80, Math.ceil(context.measureText(display).width + paddingX * 2));
        const height = fontSize + paddingY * 2;

        canvas.width = width * ratio;
        canvas.height = height * ratio;

        context.scale(ratio, ratio);
        context.font = font;
        context.textBaseline = 'middle';

        roundedRect(context, 2, 2, width - 4, height - 4, 16);
        context.fillStyle = 'rgba(6, 48, 38, 0.9)';
        context.fill();
        context.lineWidth = 2.5;
        context.strokeStyle = accent;
        context.stroke();

        context.fillStyle = '#ffffff';
        context.fillText(display, paddingX, height / 2);

        const texture = new THREE.CanvasTexture(canvas);
        texture.colorSpace = THREE.SRGBColorSpace;

        const sprite = new THREE.Sprite(new THREE.SpriteMaterial({
            map: texture,
            transparent: true,
            depthWrite: false,
        }));
        const worldHeight = Math.max(1.4, span * 0.03);

        sprite.scale.set(worldHeight * width / height, worldHeight, 1);

        return sprite;
    };

    const addLabel = (key, unitId, center, text, accent, heightFactor, phase) => {
        const group = layerGroups3d.get(key);
        const sprite = labelSprite(text, accent);
        const baseY = extrusionHeight + span * heightFactor;

        sprite.position.set(center.x, baseY, center.z);
        sprite.userData.unitId = Number(unitId);
        group.add(sprite);
    };

    const addRing = (key, unitId, center, color, radius, y, phase) => {
        const group = layerGroups3d.get(key);
        const ring = new THREE.Mesh(
            new THREE.TorusGeometry(radius, Math.max(0.04, span * 0.003), 8, 36),
            new THREE.MeshBasicMaterial({
                color: new THREE.Color(color),
                transparent: true,
                opacity: 0.65,
                depthWrite: false,
            }),
        );

        ring.rotation.x = -Math.PI / 2;
        ring.position.set(center.x, y, center.z);
        ring.userData.unitId = Number(unitId);
        group.add(ring);
    };

    const addColumn = (key, unitId, center, color, height, phase) => {
        const group = layerGroups3d.get(key);
        const column = new THREE.Mesh(
            new THREE.CylinderGeometry(span * 0.004, span * 0.013, height, 12, 1, true),
            new THREE.MeshBasicMaterial({
                color: new THREE.Color(color),
                transparent: true,
                opacity: 0.3,
                depthWrite: false,
                side: THREE.DoubleSide,
            }),
        );

        column.position.set(center.x, extrusionHeight + height / 2, center.z);
        column.userData.unitId = Number(unitId);
        group.add(column);
    };

    projected.forEach(({ unit, points }, index) => {
        const layers = unit.layers ?? {};
        const center = {
            x: points.reduce((sum, point) => sum + point.x, 0) / points.length - centerX,
            z: points.reduce((sum, point) => sum + point.z, 0) / points.length - centerZ,
        };
        const phase = index * 0.9;
        const unitId = Number(unit.id);

        if (layers.crops?.available) {
            const suffix = layers.crops.count > 1 ? ` +${layers.crops.count - 1}` : '';
            addLabel('crops', unitId, center, `${layers.crops.primary_label ?? 'Culture'}${suffix}`, unit.color, 0.18, phase);
        }

        if (layers.campaigns?.available) {
            const suffix = layers.campaigns.count > 1 ? 's' : '';
            addLabel('campaigns', unitId, center, `${layers.campaigns.count} campagne${suffix}`, layers.campaigns.color ?? '#0284c7', 0.15, phase + 0.2);
        }

        if (layers.upcoming_activities?.available) {
            addLabel('upcoming_activities', unitId, center, `${layers.upcoming_activities.count} à venir`, '#0284c7', 0.12, phase + 0.4);
        }

        if (layers.late_activities?.available) {
            addLabel('late_activities', unitId, center, `! ${layers.late_activities.count} en retard`, '#dc2626', 0.09, phase + 0.6);
            addRing('late_activities', unitId, center, '#dc2626', span * 0.03, extrusionHeight + 0.32, phase + 0.4);
            addColumn('late_activities', unitId, center, '#dc2626', span * 0.07, phase + 0.5);
        }

        if (layers.harvests?.available) {
            const suffix = layers.harvests.count > 1 ? 's' : '';
            addLabel('harvests', unitId, center, `${layers.harvests.count} récolte${suffix}`, '#65a30d', 0.06, phase + 0.7);
        }

        if (layers.alerts?.available) {
            addLabel('alerts', unitId, center, `! ${layers.alerts.count} alerte${layers.alerts.count > 1 ? 's' : ''}`, '#dc2626', 0.24, phase + 0.8);
            addRing('alerts', unitId, center, '#dc2626', span * 0.026, extrusionHeight + 0.35, phase);
            addColumn('alerts', unitId, center, '#dc2626', span * 0.075, phase + 0.2);
        }

        if (layers.irrigation?.available) {
            const color = irrigationColors[layers.irrigation.status] ?? irrigationColors.unquoted;
            const progress = Math.max(0, Math.min(100, layers.irrigation.usage_percent ?? 0));
            const text = layers.irrigation.usage_percent !== null
                ? `Eau ${numberFr3d.format(layers.irrigation.usage_percent)} %`
                : `Eau ${numberFr3d.format(layers.irrigation.used_m3)} m³`;

            addLabel('irrigation', unitId, center, text, color, 0.03, phase + 1.0);
            addRing('irrigation', unitId, center, color, span * 0.034, extrusionHeight + 0.2, phase + 1.2);
            addColumn('irrigation', unitId, center, color, span * (0.02 + 0.1 * progress / 100), phase + 1.4);
        }

        if (layers.status?.available) {
            addLabel('status', unitId, center, layers.status.label, layers.status.color, 0.0, phase + 1.6);
            addRing('status', unitId, center, layers.status.color, span * 0.042, extrusionHeight + 0.1, phase + 1.8);
        }

        if (layers.harvest_state?.available) {
            addLabel('harvest_state', unitId, center, layers.harvest_state.label, layers.harvest_state.color, -0.03, phase + 2.0);
        }
    });

    const applyHarvestVisibility3d = () => {
        layerGroups3d.forEach((group) => {
            group.children.forEach((child) => {
                const unitId = Number(child.userData.unitId);
                child.visible = !Number.isFinite(unitId)
                    || unitMatchesHarvestFilter3d(unitsById3d.get(unitId));
            });
        });
    };

    const setLayers = (keys) => {
        activeLayerKeys3d = new Set(Array.isArray(keys) ? keys : []);

        layerGroups3d.forEach((group, key) => {
            group.visible = activeLayerKeys3d.has(key);
        });

        applyHarvestVisibility3d();
        applyMeshStyles();
    };

    const setHarvestFilter = (states) => {
        harvestFilterStates3d = Array.isArray(states) && states.length > 0
            ? new Set(states.map(String))
            : null;

        applyHarvestVisibility3d();
        applyMeshStyles();
    };

    const resize = () => {
        const width = container.clientWidth || 1;
        const height = container.clientHeight || 1;

        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
    };
    resize();

    const resizeObserver = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(resize);
    resizeObserver?.observe(container);

    let selectedId = null;
    let hoveredId = null;

    const applyMeshStyles = () => {
        const edgeColor = isDark() ? 0xd1fae5 : 0x0b1220;
        const stateLayerActive = activeLayerKeys3d.has('harvest_state');

        meshes.forEach((mesh) => {
            const id = mesh.userData.id;
            const unit = unitsById3d.get(id);
            const selected = id === selectedId;
            const hovered = id === hoveredId;
            const matchesFilter = unitMatchesHarvestFilter3d(unit);
            const stateColor = stateLayerActive && unit?.harvest_state?.color
                ? new THREE.Color(unit.harvest_state.color)
                : null;
            const topColor = stateColor ?? new THREE.Color(unit?.color ?? '#0f766e');
            const sideColor = stateColor
                ? stateColor.clone().multiplyScalar(0.72)
                : new THREE.Color(unit?.color_dark || unit?.color || '#0f766e');

            mesh.userData.topMaterial.color.copy(topColor);
            mesh.userData.sideMaterial.color.copy(sideColor);

            [mesh.userData.topMaterial, mesh.userData.sideMaterial].forEach((material) => {
                material.transparent = !matchesFilter;
                material.opacity = matchesFilter ? 1 : 0.1;
                material.needsUpdate = true;
                material.emissive.setHex(selected ? 0xf59e0b : (hovered ? 0xffffff : 0x000000));
                material.emissiveIntensity = matchesFilter ? (selected ? 0.42 : (hovered ? 0.12 : 0)) : 0;
            });

            mesh.userData.edges.material.color.setHex(
                !matchesFilter ? 0x94a3b8 : (selected ? 0xfbbf24 : (hovered ? 0xfde68a : edgeColor)),
            );
            mesh.userData.edges.material.opacity = !matchesFilter
                ? 0.05
                : (selected ? 0.95 : (hovered ? 0.65 : 0.35));
        });
    };

    const setSelected = (id) => {
        selectedId = id === null || id === undefined ? null : Number(id);
        applyMeshStyles();

        if (selectedId !== null && meshById.has(selectedId)) {
            meshById.get(selectedId).userData.selected = true;
        }
    };

    const applyTheme = () => {
        const dark = isDark();

        if (!ground.userData.hasImagery) {
            groundMaterial.color.setHex(dark ? 0x10211a : 0xdceee2);
            grid.material.opacity = dark ? 0.25 : 0.3;
        } else {
            grid.material.opacity = dark ? 0.18 : 0.14;
        }

        applyMeshStyles();
    };

    const themeObserver = new MutationObserver(applyTheme);
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    const visibleHits = (hits) => hits.filter(
        (hit) => unitMatchesHarvestFilter3d(unitsById3d.get(Number(hit.object.userData.id))),
    );
    let pointerDown = null;

    renderer.domElement.addEventListener('pointerdown', (event) => {
        pointerDown = { x: event.clientX, y: event.clientY };
    });

    renderer.domElement.addEventListener('pointerup', (event) => {
        if (!pointerDown) {
            return;
        }

        const moved = Math.hypot(event.clientX - pointerDown.x, event.clientY - pointerDown.y);
        pointerDown = null;

        if (moved > 6) {
            return;
        }

        const rect = renderer.domElement.getBoundingClientRect();

        pointer.set(
            ((event.clientX - rect.left) / rect.width) * 2 - 1,
            -((event.clientY - rect.top) / rect.height) * 2 + 1,
        );
        raycaster.setFromCamera(pointer, camera);

        const hits = visibleHits(raycaster.intersectObjects(meshes, false));

        if (hits.length > 0) {
            onSelect(Number(hits[0].object.userData.id));
        }
    });

    renderer.domElement.addEventListener('pointermove', (event) => {
        const rect = renderer.domElement.getBoundingClientRect();

        pointer.set(
            ((event.clientX - rect.left) / rect.width) * 2 - 1,
            -((event.clientY - rect.top) / rect.height) * 2 + 1,
        );
        raycaster.setFromCamera(pointer, camera);

        const hits = visibleHits(raycaster.intersectObjects(meshes, false));
        const hitId = hits.length > 0 ? Number(hits[0].object.userData.id) : null;

        if (hitId !== hoveredId) {
            hoveredId = hitId;
            applyMeshStyles();
        }

        renderer.domElement.style.cursor = hitId !== null ? 'pointer' : 'grab';
    });

    renderer.domElement.addEventListener('pointerleave', () => {
        if (hoveredId !== null) {
            hoveredId = null;
            applyMeshStyles();
        }
    });

    renderer.domElement.setAttribute('tabindex', '0');
    renderer.domElement.setAttribute(
        'aria-label',
        'Vue 3D extrudée. Utilisez les flèches gauche et droite pour parcourir les parcelles, puis Entrée pour ouvrir leur fiche.',
    );

    let keyboardIndex = -1;

    renderer.domElement.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Enter', ' '].includes(event.key)) {
            return;
        }

        event.preventDefault();

        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            keyboardIndex = (keyboardIndex + (event.key === 'ArrowRight' ? 1 : -1) + units.length) % units.length;
            hoveredId = Number(units[keyboardIndex].id);
            applyMeshStyles();
            onSelect(hoveredId);

            return;
        }

        if (hoveredId !== null) {
            onSelect(hoveredId);
        }
    });

    let active = true;

    const loop = () => {
        if (!active) {
            return;
        }

        window.requestAnimationFrame(loop);
        controls.update();
        renderer.render(scene, camera);
    };

    if (REDUCED_MOTION) {
        controls.enableDamping = false;
    }

    loop();

    return {
        setSelected,
        setLayers,
        setHarvestFilter,
        resize,
        reset: () => {
            camera.position.copy(homePosition);
            controls.target.copy(homeTarget);
            controls.update();
        },
        setActive: (value) => {
            const next = Boolean(value);

            if (next && !active) {
                active = true;
                loop();
            } else if (!next) {
                active = false;
            }
        },
        destroy: () => {
            active = false;
            resizeObserver?.disconnect();
            themeObserver.disconnect();

            layerGroups3d.forEach((group) => {
                group.traverse((object) => {
                    object.geometry?.dispose();

                    if (!object.material) {
                        return;
                    }

                    const materials = Array.isArray(object.material) ? object.material : [object.material];

                    materials.forEach((material) => {
                        material.map?.dispose();
                        material.dispose();
                    });
                });
                scene.remove(group);
            });

            controls.dispose();
            renderer.dispose();
            renderer.domElement.remove();
        },
    };
}

/**
 * Plan interactif : sélection synchronisée entre 2D, 3D et plan indicatif.
 */
async function initFarmMap() {
    const root = document.querySelector('[data-farm-map]');

    if (!root) {
        return;
    }

    const configElement = root.querySelector('script[data-farm-map-config]')
        ?? document.querySelector('script[data-farm-map-config]');

    if (!configElement) {
        return;
    }

    let config;

    try {
        config = JSON.parse(configElement.textContent);
    } catch (error) {
        return;
    }

    const units = Array.isArray(config.units) ? config.units : [];
    const unitsById = new Map(units.map((unit) => [Number(unit.id), unit]));
    const panels = [...root.querySelectorAll('[data-map-panel]')];
    const modeButtons = [...root.querySelectorAll('[data-map-mode]')];
    const overview = root.querySelector('[data-map-overview]');
    const details = [...root.querySelectorAll('[data-map-detail]')];
    const plots = [...root.querySelectorAll('[data-plot-target]')];
    const quickSelects = [...root.querySelectorAll('[data-map-select]')];
    const map2dElement = root.querySelector('[data-map-2d]');
    const map3dElement = root.querySelector('[data-map-3d]');
    const empty3d = root.querySelector('[data-map-empty="3d"]');
    const loadError2d = root.querySelector('[data-map-load-error]');
    const resetButtons = [...root.querySelectorAll('[data-map-reset]')];
    const printButtons = [...root.querySelectorAll('[data-map-print]')];
    const hasBoundaries = units.some(
        (unit) => unit.has_boundary && Array.isArray(unit.points) && unit.points.length >= 3,
    );

    let map2d = null;
    let scene3d = null;
    let selectedId = null;
    let currentMode = null;
    let sceneLoading = false;

    const layerInputs = [...root.querySelectorAll('[data-map-layer]')];
    const layerLegends = new Map(
        [...root.querySelectorAll('[data-layer-legend]')].map((element) => [element.dataset.layerLegend, element]),
    );
    const layerStatus = root.querySelector('[data-map-layer-status]');
    const modeStatusText = root.querySelector('[data-map-mode-status-text]');
    const printLayerRows = [...root.querySelectorAll('[data-print-layer]')];
    const harvestFilterButtons = [...root.querySelectorAll('[data-harvest-filter]')];
    const harvestEvents = [...root.querySelectorAll('[data-harvest-event]')];
    const harvestMonths = [...root.querySelectorAll('[data-harvest-month]')];
    const harvestEmpty = root.querySelector('[data-harvest-filter-empty]');
    const harvestFilterStatus = root.querySelector('[data-harvest-filter-status]');
    const layerLabels = new Map(
        (Array.isArray(config.layers) ? config.layers : []).map((layer) => [layer.key, layer.label]),
    );
    const activeLayers = new Set(
        layerInputs
            .filter((input) => input.checked && !input.disabled)
            .map((input) => input.dataset.mapLayer),
    );
    let activeHarvestStates = null;
    let activeHarvestLabel = 'Toutes';
    let activeHarvestKey = 'all';

    const matchesHarvestFilter = (unit) => (
        activeHarvestStates === null
        || (unit?.harvest_state?.key && activeHarvestStates.has(unit.harvest_state.key))
    );

    const syncLayers = () => {
        const keys = [...activeLayers];

        map2d?.setLayers(keys);
        scene3d?.setLayers(keys);
        map2d?.setHarvestFilter(activeHarvestStates === null ? null : [...activeHarvestStates]);
        scene3d?.setHarvestFilter(activeHarvestStates === null ? null : [...activeHarvestStates]);
    };

    const applyHarvestFilter = (states, label, key) => {
        activeHarvestStates = Array.isArray(states) && states.length > 0 ? new Set(states) : null;
        activeHarvestLabel = label;
        activeHarvestKey = key;

        if (activeHarvestStates !== null && activeLayers.has('harvest_state') === false) {
            activeLayers.add('harvest_state');
            updateLayerUi(true);
        }

        let visibleUnits = 0;

        details.forEach((detail) => {
            const matches = activeHarvestStates === null
                || activeHarvestStates.has(detail.dataset.harvestState);

            if (matches) {
                visibleUnits += 1;
            } else {
                hideDetail(detail);
            }
        });

        plots.forEach((plot) => {
            const matches = activeHarvestStates === null
                || activeHarvestStates.has(plot.dataset.harvestState);

            plot.classList.toggle('is-filtered-out', !matches);
            plot.setAttribute('aria-hidden', matches ? 'false' : 'true');
        });

        quickSelects.forEach((button) => {
            const matches = activeHarvestStates === null
                || activeHarvestStates.has(button.dataset.harvestState);

            button.classList.toggle('is-filtered-out', !matches);
            button.disabled = !matches;
        });

        harvestEvents.forEach((event) => {
            const matches = activeHarvestStates === null
                || activeHarvestStates.has(event.dataset.harvestState);

            event.hidden = !matches;
        });

        harvestMonths.forEach((month) => {
            month.hidden = !month.querySelector('[data-harvest-event]:not([hidden])');
        });

        if (harvestEmpty) {
            harvestEmpty.hidden = harvestEvents.some((event) => !event.hidden);
        }

        harvestFilterButtons.forEach((button) => {
            const active = button.dataset.harvestFilter === activeHarvestKey;

            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (harvestFilterStatus) {
            harvestFilterStatus.textContent = activeHarvestStates === null
                ? 'Toutes les parcelles sont affichées.'
                : `${label} : ${visibleUnits} parcelle${visibleUnits > 1 ? 's' : ''} affichée${visibleUnits > 1 ? 's' : ''}.`;
        }

        syncLayers();
    };

    const updateLayerUi = (animate = false) => {
        layerInputs.forEach((input) => {
            const key = input.dataset.mapLayer;
            const active = activeLayers.has(key) && !input.disabled;
            const legend = layerLegends.get(key);

            input.checked = active;

            if (!legend) {
                return;
            }

            if (!active) {
                legend.hidden = true;
                legend.classList.remove('map-layer-legend-enter');

                return;
            }

            const wasHidden = legend.hidden;
            legend.hidden = false;

            if (animate && wasHidden && !REDUCED_MOTION) {
                legend.classList.remove('map-layer-legend-enter');
                window.requestAnimationFrame(() => legend.classList.add('map-layer-legend-enter'));
            }
        });

        printLayerRows.forEach((row) => {
            row.hidden = !activeLayers.has(row.dataset.printLayer);
        });

        syncLayers();
    };

    const setLayer = (key, enabled) => {
        if (enabled) {
            activeLayers.add(key);
        } else {
            activeLayers.delete(key);
        }

        updateLayerUi(true);

        if (layerStatus) {
            layerStatus.textContent = `${layerLabels.get(key) ?? key} ${enabled ? 'activée' : 'désactivée'}.`;
        }
    };

    layerInputs.forEach((input) => {
        input.addEventListener('change', () => setLayer(input.dataset.mapLayer, input.checked));
    });

    updateLayerUi();

    const updateChips = () => {
        quickSelects.forEach((button) => {
            const active = Number(button.dataset.mapSelect) === selectedId;

            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    const updateSchematic = () => {
        plots.forEach((plot) => {
            const active = selectedId !== null
                && plot.dataset.plotTarget === `plot-detail-${selectedId}`;

            plot.classList.toggle('is-selected', active);
            plot.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    const hideDetail = (detail) => {
        if (detail.hidden) {
            return;
        }

        window.clearTimeout(detail.hideTimer);
        detail.classList.remove('map-detail-enter');

        if (REDUCED_MOTION) {
            detail.hidden = true;

            return;
        }

        detail.classList.add('map-detail-leave');
        detail.hideTimer = window.setTimeout(() => {
            detail.hidden = true;
            detail.classList.remove('map-detail-leave');
        }, 180);
    };

    const showOverview = () => {
        if (!overview) {
            return;
        }

        overview.hidden = false;
        overview.classList.remove('map-overview-enter');

        if (!REDUCED_MOTION) {
            window.requestAnimationFrame(() => overview.classList.add('map-overview-enter'));
        }
    };

    const close = () => {
        selectedId = null;
        details.forEach(hideDetail);
        showOverview();

        map2d?.clear();
        scene3d?.setSelected(null);
        updateChips();
        updateSchematic();
    };

    const open = (id) => {
        const numericId = Number(id);
        const unit = unitsById.get(numericId);
        const target = details.find((detail) => detail.dataset.mapDetail === `plot-detail-${numericId}`);

        if (!target || !unit || !matchesHarvestFilter(unit)) {
            return;
        }

        selectedId = numericId;
        details.forEach((detail) => {
            if (detail === target) {
                return;
            }

            hideDetail(detail);
        });

        window.clearTimeout(target.hideTimer);
        target.classList.remove('map-detail-leave', 'map-detail-enter');
        target.hidden = false;

        if (!REDUCED_MOTION) {
            window.requestAnimationFrame(() => target.classList.add('map-detail-enter'));
        }

        if (overview) {
            overview.hidden = true;
        }

        map2d?.setSelected(numericId);
        scene3d?.setSelected(numericId);
        updateChips();
        updateSchematic();

        if (window.matchMedia('(max-width: 1279px)').matches) {
            target.scrollIntoView({ behavior: REDUCED_MOTION ? 'auto' : 'smooth', block: 'start' });
        }
    };

    const setMode = async (mode) => {
        if (!panels.some((panel) => panel.dataset.mapPanel === mode)) {
            return;
        }

        currentMode = mode;
        panels.forEach((panel) => {
            const active = panel.dataset.mapPanel === mode;

            panel.hidden = !active;
            panel.classList.remove('map-panel-enter');

            if (active && !REDUCED_MOTION) {
                window.requestAnimationFrame(() => panel.classList.add('map-panel-enter'));
            }
        });
        modeButtons.forEach((button) => {
            const active = button.dataset.mapMode === mode;

            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        const modeLabels = {
            '2d': 'Vue 2D · contours enregistrés sur imagerie satellite ou plan',
            '3d': 'Vue 3D · contours extrudés sur imagerie plaquée à plat, sans relief mesuré',
            'plan': 'Plan indicatif · surfaces déclarées, sans géolocalisation',
        };

        if (modeStatusText) {
            modeStatusText.textContent = modeLabels[mode] ?? modeLabels.plan;
        }

        resetButtons.forEach((button) => {
            button.hidden = mode === 'plan';
        });

        if (mode === '2d') {
            if (!map2d && map2dElement) {
                try {
                    map2d = await createParcelMap(map2dElement, config, open);
                    map2d.setLayers([...activeLayers]);
                    map2d.setHarvestFilter(activeHarvestStates === null ? null : [...activeHarvestStates]);
                    map2d.setSelected(selectedId);

                    if (loadError2d) {
                        loadError2d.hidden = true;
                    }
                } catch (error) {
                    if (loadError2d) {
                        loadError2d.hidden = false;
                    }
                }
            }

            map2d?.invalidate();
            scene3d?.setActive(false);

            return;
        }

        if (mode === '3d') {
            if (empty3d) {
                empty3d.hidden = hasBoundaries;
            }

            if (!scene3d && hasBoundaries && !sceneLoading && map3dElement) {
                sceneLoading = true;

                try {
                    const scene = await createParcelScene(map3dElement, config, open);

                    if (scene) {
                        scene3d = scene;
                        scene3d.setLayers([...activeLayers]);
                        scene3d.setHarvestFilter(activeHarvestStates === null ? null : [...activeHarvestStates]);
                        scene3d.setSelected(selectedId);
                        scene3d.setActive(currentMode === '3d');
                    }
                } catch (error) {
                    if (empty3d) {
                        empty3d.hidden = false;
                        empty3d.querySelector('p')?.replaceChildren('Impossible de charger la vue 3D.');
                    }
                } finally {
                    sceneLoading = false;
                }
            }

            scene3d?.resize();
            scene3d?.setActive(true);
            map2d?.invalidate();

            return;
        }

        scene3d?.setActive(false);
    };

    modeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            setMode(button.dataset.mapMode);
        });
    });

    resetButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (currentMode === '3d') {
                scene3d?.reset();
            } else {
                map2d?.fit();
            }
        });
    });

    const preparePrint = async () => {
        if (hasBoundaries && currentMode !== '2d') {
            await setMode('2d');
        }

        map2d?.invalidate();

        await new Promise((resolve) => {
            window.requestAnimationFrame(() => window.requestAnimationFrame(resolve));
        });
    };

    printButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            await preparePrint();
            window.print();
        });
    });

    window.addEventListener('beforeprint', () => {
        if (hasBoundaries && currentMode === '3d') {
            setMode('2d');
        }

        map2d?.invalidate();
    });

    plots.forEach((plot, index) => {
        plot.setAttribute('aria-pressed', 'false');
        plot.style.setProperty('--plot-index', index);
        plot.addEventListener('click', () => open(plot.dataset.plotTarget.replace('plot-detail-', '')));
    });

    quickSelects.forEach((button) => {
        button.addEventListener('click', () => {
            const id = Number(button.dataset.mapSelect);

            if (selectedId === id) {
                close();
            } else {
                open(id);
            }
        });
    });

    harvestFilterButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const states = (button.dataset.harvestStates ?? '').split(',').filter(Boolean);
            const filterStates = button.dataset.harvestFilter === 'all' || states.length === 0
                ? null
                : new Set(states);

            if (selectedId !== null && filterStates !== null) {
                const selectedUnit = unitsById.get(selectedId);

                if (!selectedUnit?.harvest_state?.key || !filterStates.has(selectedUnit.harvest_state.key)) {
                    close();
                }
            }

            applyHarvestFilter(
                filterStates === null ? null : [...filterStates],
                button.dataset.harvestLabel ?? 'Toutes',
                button.dataset.harvestFilter ?? 'all',
            );
        });
    });

    root.querySelectorAll('[data-map-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && selectedId !== null) {
            close();
        }
    });

    applyHarvestFilter(null, 'Toutes', 'all');

    await setMode(config.default_mode === 'plan' ? 'plan' : '2d');
}

/**
 * Éditeur de contour : ajout de sommets, déplacement, surface et GeoJSON.
 */
async function initBoundaryEditor() {
    const container = document.querySelector('[data-boundary-map]');

    if (!container) {
        return;
    }

    const form = document.querySelector('[data-boundary-form]');
    const input = form?.querySelector('[data-boundary-input]');
    const status = document.querySelector('[data-boundary-status]');
    const measure = document.querySelector('[data-boundary-measure]');
    const removeInput = form?.querySelector('input[name="remove_boundary"]');
    const loadError = document.querySelector('[data-boundary-load-error]');
    const tileStatus = document.querySelector('[data-boundary-tile-status]');

    let L;

    try {
        L = (await import('leaflet')).default;
    } catch (error) {
        if (loadError) {
            loadError.hidden = false;
        }

        return;
    }

    const map = L.map(container, {
        zoomControl: true,
        scrollWheelZoom: true,
        worldCopyJump: true,
    });

    const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const showTileStatus = (message) => {
        if (tileStatus) {
            tileStatus.textContent = message;
            tileStatus.hidden = false;
        }
    };

    const hideTileStatus = () => {
        if (tileStatus) {
            tileStatus.hidden = true;
        }
    };

    tiles.on('tileerror', () => {
        showTileStatus('Fond de carte indisponible (réseau faible). Le dessin reste possible, mais sans repère visuel du terrain.');
    });
    tiles.on('tileload', hideTileStatus);

    window.addEventListener('online', () => {
        hideTileStatus();
        tiles.redraw();
    });
    window.addEventListener('offline', () => {
        showTileStatus('Hors connexion : le fond de carte ne peut pas être chargé.');
    });

    if (!navigator.onLine) {
        showTileStatus('Hors connexion : le fond de carte ne peut pas être chargé.');
    }

    let points = boundaryPointsFromValue(input?.value ?? '');
    let shapeLayer = null;
    let markers = [];

    const refreshShape = () => {
        if (shapeLayer) {
            map.removeLayer(shapeLayer);
            shapeLayer = null;
        }

        if (points.length >= 3) {
            shapeLayer = L.polygon(points.map(([lng, lat]) => [lat, lng]), {
                color: '#047857',
                weight: 3,
                fillColor: '#10b981',
                fillOpacity: 0.35,
                lineJoin: 'round',
            }).addTo(map);
        } else if (points.length === 2) {
            shapeLayer = L.polyline(points.map(([lng, lat]) => [lat, lng]), {
                color: '#047857',
                weight: 3,
            }).addTo(map);
        }
    };

    const updateInput = () => {
        if (!input) {
            return;
        }

        input.value = points.length >= 3 ? JSON.stringify(boundaryToGeoJson(points)) : '';
    };

    const updateMeasure = () => {
        const area = boundaryAreaM2(points);
        const perimeter = boundaryPerimeterM(points);

        if (status) {
            status.textContent = `${points.length} sommet(s) · ${points.length >= 3 ? 'contour valide' : 'contour incomplet'}`;
        }

        if (measure) {
            measure.textContent = points.length >= 3
                ? `Surface dessinée : ${formatNumber(area, 0)} m² · périmètre ${formatNumber(perimeter, 0)} m`
                : 'Surface dessinée : —';
        }
    };

    const rebuildMarkers = () => {
        markers.forEach((marker) => map.removeLayer(marker));
        markers = [];

        points.forEach(([lng, lat], index) => {
            const marker = L.marker([lat, lng], {
                draggable: true,
                keyboard: false,
                icon: L.divIcon({
                    className: 'boundary-vertex-wrap',
                    html: '<span class="boundary-vertex"></span>',
                    iconSize: [18, 18],
                    iconAnchor: [9, 9],
                }),
            });

            marker.on('click', (event) => L.DomEvent.stopPropagation(event));
            marker.on('contextmenu', (event) => {
                L.DomEvent.stopPropagation(event);
                points.splice(index, 1);
                render();
            });
            marker.on('drag', (event) => {
                const latlng = event.target.getLatLng();
                points[index] = [latlng.lng, latlng.lat];
                refreshShape();
                updateInput();
                updateMeasure();
            });

            marker.addTo(map);
            markers.push(marker);
        });
    };

    const render = () => {
        refreshShape();
        rebuildMarkers();
        updateInput();
        updateMeasure();
    };

    const initialCenterLat = Number(container.dataset.centerLat);
    const initialCenterLng = Number(container.dataset.centerLng);
    const initialZoom = Number(container.dataset.zoom) || 16;

    const fitView = () => {
        if (points.length >= 1) {
            const bounds = L.latLngBounds(points.map(([lng, lat]) => [lat, lng]));
            map.fitBounds(bounds.pad(0.25), { padding: [24, 24], maxZoom: 18 });

            return;
        }

        if (Number.isFinite(initialCenterLat) && Number.isFinite(initialCenterLng)) {
            map.setView([initialCenterLat, initialCenterLng], initialZoom);

            return;
        }

        map.setView([20, 0], 2);
    };

    fitView();

    document.querySelector('[data-boundary-fit]')?.addEventListener('click', fitView);

    map.on('click', (event) => {
        points.push([event.latlng.lng, event.latlng.lat]);
        render();
    });

    document.querySelector('[data-boundary-undo]')?.addEventListener('click', () => {
        points.pop();
        render();
    });

    document.querySelector('[data-boundary-clear]')?.addEventListener('click', () => {
        if (points.length === 0 || window.confirm('Effacer tout le contour dessiné ?')) {
            points = [];
            render();
        }
    });

    form?.addEventListener('submit', (event) => {
        if (removeInput?.checked) {
            return;
        }

        if (points.length < 3 || boundaryAreaM2(points) <= 0.0001) {
            event.preventDefault();

            if (status) {
                status.textContent = 'Ajoutez au moins 3 sommets valides pour fermer le contour.';
            }
        }
    });

    render();
}

/**
 * Thème clair / sombre / système, mémorisé dans le navigateur.
 */
function initTheme() {
    const storageKey = 'volylegume.theme';
    const root = document.documentElement;
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const buttons = [...document.querySelectorAll('button[data-theme-choice]')];

    const storedChoice = () => {
        try {
            const value = window.localStorage.getItem(storageKey);

            return value === 'light' || value === 'dark' ? value : 'system';
        } catch (error) {
            return 'system';
        }
    };

    const apply = (choice) => {
        const theme = choice === 'system' ? (media.matches ? 'dark' : 'light') : choice;

        root.setAttribute('data-theme', theme);
        root.setAttribute('data-theme-choice', choice);
        root.style.colorScheme = theme;

        const meta = document.querySelector('meta[name="theme-color"]');

        if (meta) {
            const color = theme === 'dark'
                ? meta.getAttribute('data-dark-color')
                : meta.getAttribute('data-light-color');

            if (color) {
                meta.setAttribute('content', color);
            }
        }

        buttons.forEach((button) => {
            const active = button.dataset.themeChoice === choice;

            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const choice = button.dataset.themeChoice;

            try {
                window.localStorage.setItem(storageKey, choice);
            } catch (error) {
                // Le choix reste actif pour la page courante.
            }

            apply(choice);
        });
    });

    media.addEventListener('change', () => {
        if (storedChoice() === 'system') {
            apply('system');
        }
    });

    window.addEventListener('storage', (event) => {
        if (event.key === storageKey) {
            apply(storedChoice());
        }
    });

    apply(storedChoice());
}

function initServiceWorker() {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // L’application reste utilisable sans service worker.
        });
    });
}

function init() {
    initTheme();
    initServiceWorker();
    initOffline();
    initReveal();
    initCounters();
    initNavigation();
    initScrollProgress();
    initSidebarToggle();
    initNavigationHighlight();
    initTiltCards();
    initSpotlightCards();
    initParallax();
    initFarmMap();
    initBoundaryEditor();
    initPasswordToggles();
    initButtonFeedback();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
