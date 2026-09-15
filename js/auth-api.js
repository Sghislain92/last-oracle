// js/auth-api.js
// ------------------------------------------------------------------
// Façade d'authentification. Toutes les pages (connexion, inscription,
// admin, Oracle lui-même) passent PAR ICI, jamais par fetch('/api/...')
// directement. Le jour où le backend change (vraie base de données,
// autre fournisseur...), seule cette API a besoin d'être réécrite —
// les pages n'ont rien à changer tant que la forme des fonctions reste
// la même.
//
// Session : un jeton signé côté serveur est conservé dans
// localStorage. Il ne contient jamais le mot de passe, et le serveur
// ne fait confiance qu'à sa signature (voir api/_lib/crypto.js).
// ------------------------------------------------------------------

(function (global) {
    'use strict';

    const SESSION_KEY = 'oracle_auth_session_v1';

    function getStoredSession() {
        try {
            const raw = localStorage.getItem(SESSION_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function storeSession(token, user) {
        localStorage.setItem(SESSION_KEY, JSON.stringify({ token, user }));
    }

    function clearSession() {
        localStorage.removeItem(SESSION_KEY);
    }

    async function apiCall(path, { method = 'GET', body, auth = false } = {}) {
        const headers = { 'Content-Type': 'application/json' };
        if (auth) {
            const session = getStoredSession();
            if (session && session.token) headers['Authorization'] = 'Bearer ' + session.token;
        }
        let resp;
        try {
            resp = await fetch(path, {
                method,
                headers,
                body: body ? JSON.stringify(body) : undefined
            });
        } catch (networkErr) {
            const err = new Error('Connexion au serveur impossible. Vérifiez votre connexion.');
            err.cause = networkErr;
            throw err;
        }
        let payload = null;
        try { payload = await resp.json(); } catch (e) { /* réponse vide */ }
        if (!resp.ok) {
            const err = new Error((payload && payload.error) || `Erreur serveur (${resp.status})`);
            // Le code HTTP et l'en-tête Retry-After (si présent) sont
            // propagés — indispensable pour qu'un appelant distingue un
            // vrai 429 (limitation de débit, il faut ralentir beaucoup
            // plus longtemps) d'une erreur ordinaire (503, réseau...).
            err.status = resp.status;
            const retryAfterHeader = resp.headers.get('Retry-After');
            err.retryAfterMs = retryAfterHeader ? (parseInt(retryAfterHeader, 10) * 1000) : null;
            throw err;
        }
        return payload;
    }

    const AuthAPI = {
        /**
         * Crée un compte. Retourne { user } en cas de succès, connecte
         * automatiquement (stocke la session).
         */
        async register({ nom, prenoms, matricule, email, password, confirmPassword, acceptedCgu }) {
            const result = await apiCall('/api/accounts/create', {
                method: 'POST',
                body: { nom, prenoms, matricule, email, password, confirmPassword, acceptedCgu }
            });
            storeSession(result.token, result.user);
            return result.user;
        },

        /** Connexion. Retourne l'utilisateur et stocke la session. */
        async login(email, password) {
            const result = await apiCall('/api/session/start', {
                method: 'POST',
                body: { email, password }
            });
            storeSession(result.token, result.user);
            return result.user;
        },

        /** Déconnexion : supprime uniquement la session locale. */
        logout() {
            clearSession();
        },

        /** Utilisateur actuellement connecté (depuis le cache local), ou null. */
        getCurrentUser() {
            const session = getStoredSession();
            return session ? session.user : null;
        },

        isAuthenticated() {
            return !!getStoredSession();
        },

        isAdmin() {
            const user = AuthAPI.getCurrentUser();
            return !!user && user.role === 'admin';
        },

        /**
         * Revalide la session auprès du serveur (jeton toujours valide ?).
         * Utile au chargement d'une page protégée. Nettoie la session
         * locale et retourne null si le jeton est expiré/invalide.
         */
        async refreshSession() {
            if (!AuthAPI.isAuthenticated()) return null;
            try {
                const result = await apiCall('/api/session/current', { auth: true });
                const session = getStoredSession();
                storeSession(session.token, result.user);
                return result.user;
            } catch (e) {
                clearSession();
                return null;
            }
        },

        /** [Admin uniquement] Liste de tous les utilisateurs + total. */
        async getUsers() {
            return apiCall('/api/accounts/list', { auth: true });
        },

        /** [Admin uniquement] Change le rôle et/ou le statut actif d'un
         *  utilisateur. Retourne { ok, user }. */
        async updateUser(userId, changes) {
            return apiCall('/api/accounts/modify', { method: 'POST', auth: true, body: { userId, ...changes } });
        },

        /** [Admin uniquement] Historique de recherche d'un utilisateur donné,
         *  ou de TOUS les utilisateurs si aucun userId n'est fourni. */
        async getUserHistory(userId) {
            const qs = userId ? '?userId=' + encodeURIComponent(userId) : '';
            return apiCall('/api/accounts/activity' + qs, { auth: true });
        },

        /** [Admin uniquement] Liste des erreurs d'authentification. */
        async getErrors() {
            return apiCall('/api/accounts/incidents', { auth: true });
        },

        /**
         * Synchronise un enregistrement de recherche Oracle vers le
         * serveur (en plus du localStorage local). Échoue silencieusement
         * si hors ligne ou non connecté — la recherche reste de toute
         * façon disponible localement.
         */
        async syncSearch(record) {
            if (!AuthAPI.isAuthenticated()) return null;
            try {
                return await apiCall('/api/telemetry/checkin', { method: 'POST', auth: true, body: { record } });
            } catch (e) {
                console.warn('Synchronisation serveur échouée (conservée localement) :', e.message);
                return null;
            }
        },

        /**
         * Envoie une fiche moto découverte (vérification en ligne réussie) vers le registre
         * central data/Immatriculations.json. Contrairement à syncSearch,
         * celle-ci PROPAGE l'erreur (throw) : l'appelant décide s'il faut
         * mettre l'enregistrement en file d'attente hors ligne ou non.
         * Retourne { ok, added, record }.
         */
        async syncImmatriculation(record) {
            return apiCall('/api/registry/report', { method: 'POST', auth: true, body: { record } });
        },

        /**
         * Synchronise une entrée de log technique vers le serveur. Échoue
         * silencieusement (comme syncSearch) : le log reste de toute façon
         * disponible localement.
         */
        async syncLog(entry) {
            if (!AuthAPI.isAuthenticated()) return null;
            try {
                return await apiCall('/api/telemetry/event', { method: 'POST', auth: true, body: { entry } });
            } catch (e) {
                console.warn('Synchronisation du log échouée (conservé localement) :', e.message);
                return null;
            }
        },

        /** Logs techniques d'un utilisateur donné, ou de TOUS les
         *  utilisateurs si aucun userId n'est fourni (admin uniquement
         *  dans ce dernier cas — un utilisateur normal ne peut demander
         *  que ses propres logs). */
        async getUserLogs(userId) {
            const qs = userId ? '?userId=' + encodeURIComponent(userId) : '';
            return apiCall('/api/accounts/events' + qs, { auth: true });
        },

        /**
         * Synchronisation par delta — renvoie tout ce qui a changé depuis
         * (since, sinceId). Utilisé pour alimenter IndexedDB en continu,
         * sans jamais retélécharger l'intégralité du registre après le
         * premier chargement.
         */
        async syncDelta(since, sinceId, limit = 3000) {
            const qs = `?since=${encodeURIComponent(since)}&sinceId=${sinceId}&limit=${limit}`;
            return apiCall('/api/registry/feed' + qs, { auth: true });
        },

        /** Total de fiches dans le registre — utilisé uniquement pour
         *  afficher une progression de synchronisation lisible. */
        async getRegistryCount() {
            return apiCall('/api/registry/stats', { auth: true });
        },

        /** [Admin uniquement] Résumé IA des logs/erreurs des 7 derniers
         *  jours — uniquement des compteurs agrégés envoyés au modèle,
         *  jamais de données personnelles. */
        async getAiInsights() {
            return apiCall('/api/accounts/insights', { auth: true });
        },

        /** Trace une installation de la PWA (appareil, navigateur, IP côté
         *  serveur). Fonctionne avec ou sans session — un agent peut
         *  installer l'app avant même d'avoir un compte. */
        async trackInstall(details) {
            return apiCall('/api/telemetry/device', { method: 'POST', auth: true, body: details });
        },

        /**
         * Recherche directe dans le registre — MySQL indexe châssis et
         * immatriculation nativement : une recherche est une requête
         * indexée, instantanée quel que soit le volume.
         * Retourne { ok, found, current?, previousOwners? }.
         */
        async searchRegistry(type, value) {
            const qs = `?type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}`;
            return apiCall('/api/registry/lookup' + qs, { auth: true });
        },

        /** [Admin uniquement] Import en masse (upload .txt ou .json) —
         *  fusion + anti-doublon en une seule transaction MySQL. Retourne
         *  { ok, added, enriched, skipped, total }. */
        async importImmatriculations(records) {
            return apiCall('/api/registry/bulk', { method: 'POST', auth: true, body: { records } });
        }
    };

    global.AuthAPI = AuthAPI;
})(window);