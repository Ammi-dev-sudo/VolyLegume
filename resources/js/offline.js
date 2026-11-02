const QUEUE_KEY = 'volylegume.offline.queue.v1';
const REJECTED_KEY = 'volylegume.offline.rejected.v1';
const MAX_ATTEMPTS = 5;
const SYNC_INTERVAL_MS = 60_000;

let flushing = false;
let hideTimer = null;

function currentUserId() {
    return document.querySelector('meta[name="user-id"]')?.content ?? null;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function readList(key) {
    try {
        const value = JSON.parse(window.localStorage.getItem(key) ?? '[]');

        return Array.isArray(value) ? value : [];
    } catch {
        return [];
    }
}

function writeList(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Stockage plein ou désactivé : la saisie reste dans le formulaire.
    }
}

function createReference() {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return `offline-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function mine(entry) {
    return entry.user_id !== null && entry.user_id === currentUserId();
}

function banner() {
    return document.getElementById('offline-banner');
}

function setBanner(message, state) {
    const element = banner();

    if (!element) {
        return;
    }

    window.clearTimeout(hideTimer);

    element.hidden = false;
    element.dataset.state = state;

    const text = element.querySelector('[data-offline-message]');
    const sync = element.querySelector('[data-offline-sync]');
    const clear = element.querySelector('[data-offline-clear]');

    if (text) {
        text.textContent = message;
    }

    if (sync) {
        sync.hidden = state !== 'pending' && state !== 'error';
    }

    if (clear) {
        clear.hidden = state !== 'error' || queueSummary().rejected === 0;
    }
}

function hideBanner() {
    const element = banner();

    if (element) {
        element.hidden = true;
    }
}

function queueSummary() {
    return {
        pending: readList(QUEUE_KEY).filter(mine).length,
        rejected: readList(REJECTED_KEY).filter(mine).length,
    };
}

function updateBanner() {
    const { pending, rejected } = queueSummary();

    if (!navigator.onLine) {
        setBanner(
            pending > 0
                ? `Hors connexion — ${pending} saisie(s) conservée(s) sur cet appareil.`
                : 'Hors connexion — les activités et récoltes seront enregistrées sur cet appareil.',
            'offline',
        );

        return;
    }

    if (pending > 0) {
        setBanner(`${pending} saisie(s) en attente de synchronisation.`, 'pending');

        return;
    }

    if (rejected > 0) {
        setBanner(`${rejected} saisie(s) rejetée(s) — vérifiez les données puis effacez l’alerte.`, 'error');

        return;
    }

    hideBanner();
}

function rejectEntry(entry, reason) {
    const rejected = readList(REJECTED_KEY);

    rejected.push({
        ...entry,
        reason,
        rejected_at: new Date().toISOString(),
    });

    writeList(REJECTED_KEY, rejected.slice(-20));
}

async function flushQueue() {
    if (flushing || !navigator.onLine || currentUserId() === null) {
        updateBanner();

        return;
    }

    const pending = readList(QUEUE_KEY).filter(mine);

    if (pending.length === 0) {
        updateBanner();

        return;
    }

    flushing = true;
    setBanner('Synchronisation des saisies en cours…', 'syncing');

    const token = csrfToken();
    let synced = 0;
    let rejected = 0;
    let stopped = false;

    for (const entry of pending) {
        entry.fields._token = token;

        try {
            const response = await fetch(entry.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new URLSearchParams(entry.fields),
                credentials: 'same-origin',
            });

            if (response.ok) {
                writeList(QUEUE_KEY, readList(QUEUE_KEY).filter((item) => item.id !== entry.id));
                synced++;
                continue;
            }

            if (response.status === 422) {
                const payload = await response.json().catch(() => null);

                writeList(QUEUE_KEY, readList(QUEUE_KEY).filter((item) => item.id !== entry.id));
                rejectEntry(entry, payload?.message ?? 'Données incomplètes ou invalides.');
                rejected++;
                continue;
            }

            if (response.status === 401 || response.status === 419) {
                stopped = true;
                setBanner('Session expirée — reconnectez-vous puis relancez la synchronisation.', 'error');
                break;
            }

            entry.attempts = (entry.attempts ?? 0) + 1;

            if (entry.attempts >= MAX_ATTEMPTS) {
                writeList(QUEUE_KEY, readList(QUEUE_KEY).filter((item) => item.id !== entry.id));
                rejectEntry(entry, `Échec après ${MAX_ATTEMPTS} tentatives (code ${response.status}).`);
                rejected++;
            } else {
                writeList(QUEUE_KEY, readList(QUEUE_KEY).map((item) => (
                    item.id === entry.id ? { ...item, attempts: entry.attempts } : item
                )));
            }
        } catch {
            entry.attempts = (entry.attempts ?? 0) + 1;

            writeList(QUEUE_KEY, readList(QUEUE_KEY).map((item) => (
                item.id === entry.id ? { ...item, attempts: entry.attempts } : item
            )));
        }
    }

    flushing = false;

    if (stopped) {
        return;
    }

    if (synced > 0 || rejected > 0) {
        const parts = [];

        if (synced > 0) {
            parts.push(`${synced} saisie(s) synchronisée(s)`);
        }

        if (rejected > 0) {
            parts.push(`${rejected} rejetée(s)`);
        }

        setBanner(parts.join(' · '), rejected > 0 ? 'error' : 'success');
        hideTimer = window.setTimeout(updateBanner, 5000);
    } else {
        updateBanner();
    }
}

function queueForm(form) {
    const data = new FormData(form);
    const fields = {};

    data.forEach((value, key) => {
        if (typeof value === 'string') {
            fields[key] = value;
        }
    });

    const queue = readList(QUEUE_KEY);

    queue.push({
        id: createReference(),
        user_id: currentUserId(),
        action: form.dataset.offlineAction,
        label: form.dataset.offlineLabel ?? 'Saisie',
        fields,
        attempts: 0,
        created_at: new Date().toISOString(),
    });

    writeList(QUEUE_KEY, queue);
}

function initForms() {
    document.querySelectorAll('form[data-offline-form]').forEach((form) => {
        let reference = form.querySelector('input[name="client_reference"]');

        if (!reference) {
            reference = document.createElement('input');
            reference.type = 'hidden';
            reference.name = 'client_reference';
            form.prepend(reference);
        }

        if (!reference.value) {
            reference.value = createReference();
        }

        form.addEventListener('submit', (event) => {
            if (navigator.onLine || currentUserId() === null) {
                return;
            }

            event.preventDefault();

            queueForm(form);
            form.reset();

            const nextReference = form.querySelector('input[name="client_reference"]');

            if (nextReference) {
                nextReference.value = createReference();
            }

            updateBanner();
        });
    });
}

function clearRejected() {
    writeList(REJECTED_KEY, readList(REJECTED_KEY).filter((entry) => !mine(entry)));
    updateBanner();
}

export function initOffline() {
    if (currentUserId() === null) {
        return;
    }

    initForms();

    const element = banner();

    element?.querySelector('[data-offline-sync]')?.addEventListener('click', () => {
        flushQueue();
    });

    element?.querySelector('[data-offline-dismiss]')?.addEventListener('click', () => {
        hideBanner();
    });

    element?.querySelector('[data-offline-clear]')?.addEventListener('click', () => {
        clearRejected();
    });

    window.addEventListener('online', () => {
        updateBanner();
        flushQueue();
    });

    window.addEventListener('offline', updateBanner);

    updateBanner();

    if (navigator.onLine) {
        window.setTimeout(flushQueue, 800);
    }

    window.setInterval(flushQueue, SYNC_INTERVAL_MS);
}
