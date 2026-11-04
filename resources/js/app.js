import 'leaflet/dist/leaflet.css';
import { initOffline } from './offline';

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
            if (index < 10) {
                row.classList.add('row-in');
                row.style.setProperty('--row-index', index);
            }
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

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const units = Array.isArray(config.units) ? config.units : [];
    const unitsById = new Map(units.map((unit) => [Number(unit.id), unit]));
    const polygons = new Map();
    const missingMarkers = new Map();
    const bounds = L.latLngBounds([]);

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

            polygon.bindTooltip(`${unit.code} · ${unit.name}`, {
                sticky: true,
                direction: 'top',
                opacity: 0.92,
            });
            polygon.on('click', () => onSelect(id));
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

            marker.bindTooltip(`${unit.code} · ${unit.name} — contour à dessiner`, {
                direction: 'top',
                opacity: 0.92,
            });
            marker.on('click', () => onSelect(id));
            missingMarkers.set(id, marker);
        }
    });

    if (bounds.isValid()) {
        map.fitBounds(bounds.pad(0.18), { padding: [24, 24], maxZoom: 18 });
    } else if (config.farm && Number.isFinite(config.farm.latitude) && Number.isFinite(config.farm.longitude)) {
        map.setView([config.farm.latitude, config.farm.longitude], 16);
    } else {
        map.setView([20, 0], 2);
    }

    const setSelected = (id) => {
        const selectedId = id === null || id === undefined ? null : Number(id);

        polygons.forEach((polygon, unitId) => {
            const unit = unitsById.get(unitId);
            const selected = unitId === selectedId;

            polygon.setStyle({
                color: selected ? '#f59e0b' : (unit.color_dark || unit.color),
                weight: selected ? 4 : 2,
                fillOpacity: selected ? 0.7 : 0.45,
            });

            if (selected) {
                polygon.bringToFront();
            }
        });

        missingMarkers.forEach((marker, unitId) => {
            const selected = unitId === selectedId;

            marker.setStyle({
                radius: selected ? 11 : 8,
                color: selected ? '#f59e0b' : '#b45309',
                weight: selected ? 4 : 2,
                fillColor: selected ? '#fbbf24' : '#f59e0b',
            });
        });
    };

    return {
        setSelected,
        clear: () => setSelected(null),
        invalidate: () => map.invalidateSize({ pan: false }),
        destroy: () => map.remove(),
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
    renderer.domElement.setAttribute('aria-hidden', 'true');
    container.appendChild(renderer.domElement);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;
    controls.screenSpacePanning = true;
    controls.maxPolarAngle = Math.PI / 2.05;
    controls.minDistance = Math.max(5, span * 0.08);
    controls.maxDistance = span * 6 + 200;
    controls.target.set(0, extrusionHeight * 0.4, 0);

    camera.position.set(span * 0.72, span * 0.78, span * 1.05);
    controls.update();

    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
    const ground = new THREE.Mesh(
        new THREE.PlaneGeometry(span * 2.4 + 40, span * 2.4 + 40),
        new THREE.MeshStandardMaterial({
            color: isDark() ? 0x10211a : 0xdceee2,
            roughness: 1,
            metalness: 0,
        }),
    );
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.05;
    scene.add(ground);

    const grid = new THREE.GridHelper(span * 2.4 + 40, 24, 0x64748b, 0x94a3b8);
    grid.position.y = 0;
    grid.material.transparent = true;
    grid.material.opacity = isDark() ? 0.25 : 0.3;
    scene.add(grid);

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

    const setSelected = (id) => {
        const selectedId = id === null || id === undefined ? null : Number(id);
        const edgeColor = isDark() ? 0xd1fae5 : 0x0b1220;

        meshes.forEach((mesh) => {
            const selected = mesh.userData.id === selectedId;

            [mesh.userData.topMaterial, mesh.userData.sideMaterial].forEach((material) => {
                material.emissive.setHex(selected ? 0xf59e0b : 0x000000);
                material.emissiveIntensity = selected ? 0.42 : 0;
            });

            mesh.userData.edges.material.color.setHex(selected ? 0xfbbf24 : edgeColor);
            mesh.userData.edges.material.opacity = selected ? 0.95 : 0.35;
        });

        if (selectedId !== null && meshById.has(selectedId)) {
            const mesh = meshById.get(selectedId);
            mesh.userData.selected = true;
        }
    };

    const applyTheme = () => {
        const dark = isDark();

        ground.material.color.setHex(dark ? 0x10211a : 0xdceee2);
        grid.material.opacity = dark ? 0.25 : 0.3;

        meshes.forEach((mesh) => {
            mesh.userData.edges.material.color.setHex(
                mesh.userData.edges.material.opacity > 0.5
                    ? 0xfbbf24
                    : (dark ? 0xd1fae5 : 0x0b1220),
            );
        });
    };

    const themeObserver = new MutationObserver(applyTheme);
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
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

        const hits = raycaster.intersectObjects(meshes, false);

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
        renderer.domElement.style.cursor = raycaster.intersectObjects(meshes, false).length > 0
            ? 'pointer'
            : 'grab';
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
        resize,
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
    const hasBoundaries = units.some(
        (unit) => unit.has_boundary && Array.isArray(unit.points) && unit.points.length >= 3,
    );

    let map2d = null;
    let scene3d = null;
    let selectedId = null;
    let currentMode = null;
    let sceneLoading = false;

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

    const close = () => {
        selectedId = null;
        details.forEach((detail) => {
            detail.hidden = true;
        });

        if (overview) {
            overview.hidden = false;
        }

        map2d?.clear();
        scene3d?.setSelected(null);
        updateChips();
        updateSchematic();
    };

    const open = (id) => {
        const numericId = Number(id);
        const target = details.find((detail) => detail.dataset.mapDetail === `plot-detail-${numericId}`);

        if (!target || !unitsById.has(numericId)) {
            return;
        }

        selectedId = numericId;
        details.forEach((detail) => {
            detail.hidden = detail !== target;
            detail.classList.remove('map-detail-enter');
        });

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
            panel.hidden = panel.dataset.mapPanel !== mode;
        });
        modeButtons.forEach((button) => {
            const active = button.dataset.mapMode === mode;

            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (mode === '2d') {
            if (!map2d && map2dElement) {
                try {
                    map2d = await createParcelMap(map2dElement, config, open);
                    map2d.setSelected(selectedId);
                } catch (error) {
                    map2dElement.textContent = 'Impossible de charger la carte 2D.';
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

    root.querySelectorAll('[data-map-close]').forEach((button) => {
        button.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && selectedId !== null) {
            close();
        }
    });

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
    const L = (await import('leaflet')).default;

    const map = L.map(container, {
        zoomControl: true,
        scrollWheelZoom: true,
        worldCopyJump: true,
    });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

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

    if (points.length >= 1) {
        const bounds = L.latLngBounds(points.map(([lng, lat]) => [lat, lng]));
        map.fitBounds(bounds.pad(0.25), { padding: [24, 24], maxZoom: 18 });
    } else if (Number.isFinite(initialCenterLat) && Number.isFinite(initialCenterLng)) {
        map.setView([initialCenterLat, initialCenterLng], initialZoom);
    } else {
        map.setView([20, 0], 2);
    }

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
    initTableRows();
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
