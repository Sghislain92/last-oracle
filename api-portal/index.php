<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
?><!doctype html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="description" content="Portail partenaire autonome de l'API Oracle." />
    <title>Oracle API — Espace partenaire</title>
    <link rel="icon" href="../images/favicon/favicon-32x32.png" type="image/png" />

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans: ['Manrope', 'ui-sans-serif', 'system-ui'],
              serif: ['"DM Serif Display"', 'Georgia', 'serif'],
            },
            colors: {
              ink: '#17232d',
              navy: '#0f1922',
              orange: { DEFAULT: '#ee7b3d', deep: '#d1602a' },
              paper: '#f6f3ec',
              card: '#fffdf9',
              line: '#e6e0d2',
              muted: '#6d7982',
            },
          },
        },
      };
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700;800&display=swap"
      rel="stylesheet"
    />
  </head>
  <body class="bg-paper font-sans text-ink antialiased">
    <div id="toast" class="fixed bottom-4 right-4 left-4 z-[100] ml-auto max-w-sm translate-y-5 opacity-0 pointer-events-none transition duration-200"></div>
    <div id="modalRoot"></div>

    <!-- Écran neutre pendant la vérification de session — jamais de
         flash de l'écran de connexion pour un agent déjà connecté qui
         recharge la page ; ce chargeur reste affiché jusqu'à ce que
         boot() sache réellement quel écran montrer. -->
    <div id="bootLoader" class="grid min-h-screen place-items-center bg-paper">
      <div class="flex flex-col items-center gap-4">
        <img src="../images/logo/launchericon-192x192.png" alt="" class="h-12 w-12 rounded-2xl" />
        <span class="h-6 w-6 animate-spin rounded-full border-2 border-orange/25 border-t-orange"></span>
      </div>
    </div>

    <!-- ============================================================
         AUTHENTIFICATION
         ============================================================ -->
    <section id="authView" class="hidden grid min-h-screen grid-cols-1 bg-card lg:grid-cols-[1.05fr_.95fr]">
      <div class="relative flex flex-col gap-10 bg-[radial-gradient(circle_at_75%_20%,rgba(244,123,61,0.33),transparent_28%),linear-gradient(155deg,#1d3445,#0f1922)] px-7 py-10 text-white lg:min-h-screen lg:justify-between lg:px-16 lg:py-14">
        <div class="flex items-center gap-3">
          <img src="../images/logo/launchericon-192x192.png" alt="" class="h-10 w-10 rounded-xl" />
          <span>
            <b class="block font-serif text-2xl leading-none">Oracle</b>
            <small class="mt-1 block text-[8px] font-extrabold tracking-[0.16em] text-slate-300">API · CONFIANCE · CONTRÔLE</small>
          </span>
        </div>

        <div class="max-w-lg">
          <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange/90">
            <i data-lucide="waypoints" class="h-3.5 w-3.5"></i> Portail partenaire
          </div>
          <h1 class="mt-5 font-serif text-4xl leading-[1.05] tracking-tight lg:text-6xl">Connectez vos produits à une donnée fiable.</h1>
          <p class="mt-5 max-w-md text-sm leading-loose text-slate-300">
            Un espace indépendant pour vos équipes et vos intégrations. Créez une clé, utilisez-la sur votre site et
            gardez le contrôle de chaque appel.
          </p>
          <div class="mt-8 grid gap-3 text-[11px] font-extrabold text-slate-200">
            <span class="flex items-center gap-2.5"><i data-lucide="key-round" class="h-4 w-4 text-orange"></i> Clés isolées par intégration</span>
            <span class="flex items-center gap-2.5"><i data-lucide="activity" class="h-4 w-4 text-orange"></i> Historique et erreurs visibles</span>
            <span class="flex items-center gap-2.5"><i data-lucide="shield-check" class="h-4 w-4 text-orange"></i> Accès lecture seule</span>
          </div>
        </div>

        <div class="hidden text-[9px] font-extrabold uppercase tracking-[0.16em] text-slate-400 lg:block">Oracle API · Partenaires vérifiés</div>
      </div>

      <div class="mx-auto w-full max-w-md px-6 py-10 lg:py-14">
        <div class="flex items-center justify-between">
          <span class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep">
            <i data-lucide="lock-keyhole" class="h-3.5 w-3.5"></i> Espace sécurisé
          </span>
          <span class="text-[10px] text-muted">API partners</span>
        </div>

        <div class="mt-8 flex gap-6 border-b border-line">
          <button class="auth-tab border-b-2 border-orange pb-3 text-xs font-black text-ink" data-auth-tab="login">Se connecter</button>
          <button class="auth-tab border-b-2 border-transparent pb-3 text-xs font-black text-slate-400" data-auth-tab="register">Créer un compte</button>
        </div>

        <div id="loginPanel" class="mt-7">
          <h2 class="font-serif text-4xl">Bienvenue.</h2>
          <p class="mt-3 text-xs leading-relaxed text-muted">Connectez-vous à votre espace partenaire pour gérer vos clés et vos intégrations.</p>
          <form id="loginForm" class="mt-7 grid gap-4" autocomplete="off">
            <label class="grid gap-1.5 text-[11px] font-extrabold text-slate-600">
              E-mail
              <input id="loginEmail" type="email" autocomplete="email" required
                class="h-11 rounded-lg border border-line bg-white px-3 text-[13px] font-medium text-ink outline-none focus:border-orange focus:ring-4 focus:ring-orange/10" />
            </label>
            <label class="grid gap-1.5 text-[11px] font-extrabold text-slate-600">
              Mot de passe
              <input id="loginPassword" type="password" autocomplete="current-password" required
                class="h-11 rounded-lg border border-line bg-white px-3 text-[13px] font-medium text-ink outline-none focus:border-orange focus:ring-4 focus:ring-orange/10" />
            </label>
            <p id="loginError" class="hidden rounded-lg bg-red-50 px-3 py-2.5 text-[11px] font-bold text-red-700"></p>
            <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-ink px-4 py-3 text-[11px] font-black text-white shadow-md shadow-ink/25 transition hover:bg-[#26394a]">
              <i data-lucide="log-in" class="h-4 w-4"></i> Se connecter
            </button>
          </form>
        </div>

        <div id="registerPanel" class="mt-7 hidden">
          <h2 class="font-serif text-4xl">Votre espace API.</h2>
          <p class="mt-3 text-xs leading-relaxed text-muted">Créez un compte partenaire séparé des comptes des agents de police.</p>
          <form id="registerForm" class="mt-7 grid gap-4" autocomplete="off" novalidate>
            <label class="grid gap-1.5 text-[11px] font-extrabold text-slate-600">
              Entreprise
              <input id="registerCompany" required maxlength="150" autocomplete="organization"
                class="h-11 rounded-lg border border-line bg-white px-3 text-[13px] font-medium text-ink outline-none focus:border-orange focus:ring-4 focus:ring-orange/10" />
            </label>
            <label class="grid gap-1.5 text-[11px] font-extrabold text-slate-600">
              Nom du contact
              <input id="registerName" required maxlength="160" autocomplete="name"
                class="h-11 rounded-lg border border-line bg-white px-3 text-[13px] font-medium text-ink outline-none focus:border-orange focus:ring-4 focus:ring-orange/10" />
            </label>
            <label class="grid gap-1.5 text-[11px] font-extrabold text-slate-600">
              E-mail professionnel
              <input id="registerEmail" type="email" required autocomplete="email"
                class="h-11 rounded-lg border border-line bg-white px-3 text-[13px] font-medium text-ink outline-none focus:border-orange focus:ring-4 focus:ring-orange/10" />
            </label>
            <label class="grid gap-1.5 text-[11px] font-extrabold text-slate-600">
              Mot de passe <span class="font-medium text-slate-400">(10 caractères minimum)</span>
              <input id="registerPassword" type="password" minlength="10" required autocomplete="new-password"
                class="h-11 rounded-lg border border-line bg-white px-3 text-[13px] font-medium text-ink outline-none focus:border-orange focus:ring-4 focus:ring-orange/10" />
            </label>
            <p id="registerError" class="hidden rounded-lg bg-red-50 px-3 py-2.5 text-[11px] font-bold text-red-700"></p>
            <button type="submit" class="flex items-center justify-center gap-2 rounded-lg bg-gradient-to-br from-orange to-orange-deep px-4 py-3 text-[11px] font-black text-white shadow-md shadow-orange/30 transition hover:-translate-y-0.5">
              <i data-lucide="user-plus" class="h-4 w-4"></i> Créer mon espace
            </button>
          </form>
        </div>
        <p class="mt-7 text-[10px] leading-relaxed text-slate-400">
          En continuant, vous acceptez que les appels API soient journalisés à des fins de sécurité et de traçabilité.
        </p>
      </div>
    </section>

    <!-- ============================================================
         APPLICATION (connecté)
         ============================================================ -->
    <section id="appView" class="hidden min-h-screen bg-paper">

      <!-- Barre supérieure mobile/tablette (< 1024px) : titre + hamburger à droite -->
      <header class="fixed inset-x-0 top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-line bg-paper/90 px-4 backdrop-blur lg:hidden">
        <div class="flex min-w-0 items-center gap-2.5">
          <img src="../images/logo/launchericon-192x192.png" alt="" class="h-7 w-7 shrink-0 rounded-lg" />
          <b id="pageTitleMobile" class="truncate text-[13px]">Vue d'ensemble</b>
        </div>
        <button id="hamburgerBtn" aria-label="Ouvrir le menu"
          class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-line bg-card text-ink">
          <i data-lucide="menu" class="h-5 w-5"></i>
        </button>
      </header>

      <!-- Barre du bas mobile/tablette : 4 onglets principaux -->
      <nav class="fixed inset-x-2 bottom-2 z-30 grid grid-cols-4 gap-1 rounded-2xl border border-line/70 bg-card/95 p-1.5 shadow-xl backdrop-blur lg:hidden">
        <button class="nav-item flex flex-col items-center justify-center gap-1 rounded-xl py-2 text-[8px] font-extrabold text-slate-400" data-page="overview" data-active="text-orange-deep bg-orange/10">
          <i data-lucide="layout-dashboard" class="h-[17px] w-[17px]"></i><span>Accueil</span>
        </button>
        <button class="nav-item flex flex-col items-center justify-center gap-1 rounded-xl py-2 text-[8px] font-extrabold text-slate-400" data-page="keys" data-active="text-orange-deep bg-orange/10">
          <i data-lucide="key-round" class="h-[17px] w-[17px]"></i><span>Clés API</span>
        </button>
        <button class="nav-item flex flex-col items-center justify-center gap-1 rounded-xl py-2 text-[8px] font-extrabold text-slate-400" data-page="usage" data-active="text-orange-deep bg-orange/10">
          <i data-lucide="bar-chart-3" class="h-[17px] w-[17px]"></i><span>Usage</span>
        </button>
        <button class="nav-item flex flex-col items-center justify-center gap-1 rounded-xl py-2 text-[8px] font-extrabold text-slate-400" data-page="settings" data-active="text-orange-deep bg-orange/10">
          <i data-lucide="settings" class="h-[17px] w-[17px]"></i><span>Réglages</span>
        </button>
      </nav>

      <!-- Tiroir mobile/tablette : navigation complète, ouvert depuis la droite -->
      <div id="drawerBackdrop" class="fixed inset-0 z-[60] bg-navy/80 opacity-0 invisible transition-opacity duration-300 lg:hidden"></div>
      <aside id="mobileDrawer"
        class="fixed inset-y-0 right-0 z-[70] flex w-[95vw] max-w-sm translate-x-full transform flex-col overflow-y-auto bg-gradient-to-b from-[#172a39] to-[#0f1922] p-5 text-slate-100 shadow-2xl transition-transform duration-300 ease-out lg:hidden">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <img src="../images/logo/launchericon-192x192.png" alt="" class="h-8 w-8 rounded-lg" />
            <b class="text-white">Oracle API</b>
          </div>
          <button id="drawerClose" aria-label="Fermer le menu" class="grid h-9 w-9 place-items-center rounded-lg bg-white/10 text-slate-200">
            <i data-lucide="x" class="h-[18px] w-[18px]"></i>
          </button>
        </div>

        <p class="mb-2 mt-7 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Mon espace</p>
        <nav class="grid gap-1">
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[13px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="overview" data-active="bg-white/10 text-white">
            <i data-lucide="layout-dashboard" class="h-[17px] w-[17px]"></i><span>Vue d'ensemble</span>
          </button>
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[13px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="keys" data-active="bg-white/10 text-white">
            <i data-lucide="key-round" class="h-[17px] w-[17px]"></i><span>Mes clés API</span>
          </button>
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[13px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="usage" data-active="bg-white/10 text-white">
            <i data-lucide="bar-chart-3" class="h-[17px] w-[17px]"></i><span>Utilisation API</span>
          </button>
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[13px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="activity" data-active="bg-white/10 text-white">
            <i data-lucide="history" class="h-[17px] w-[17px]"></i><span>Activité</span>
          </button>
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[13px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="errors" data-active="bg-white/10 text-white">
            <i data-lucide="triangle-alert" class="h-[17px] w-[17px]"></i><span>Erreurs</span>
          </button>
        </nav>

        <p class="mb-2 mt-6 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Compte</p>
        <nav class="grid gap-1">
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[13px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="settings" data-active="bg-white/10 text-white">
            <i data-lucide="settings" class="h-[17px] w-[17px]"></i><span>Paramètres</span>
          </button>
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[13px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="docs" data-active="bg-white/10 text-white">
            <i data-lucide="book-open" class="h-[17px] w-[17px]"></i><span>Documentation</span>
          </button>
        </nav>

        <div class="mt-auto border-t border-white/10 pt-4">
          <div class="mb-2 flex items-center gap-2.5 rounded-xl border border-white/10 bg-white/5 p-2.5">
            <span class="js-avatar grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-[#f6c99a] to-[#f0af7c] text-[10px] font-black text-ink">P</span>
            <div class="min-w-0">
              <b class="js-company block truncate text-[11px]">Partenaire</b>
              <small class="js-email block truncate text-[9px] text-slate-400">—</small>
            </div>
          </div>
          <button class="js-logout flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[12px] font-extrabold text-red-300 transition hover:bg-white/10">
            <i data-lucide="log-out" class="h-[17px] w-[17px]"></i> Déconnexion
          </button>
        </div>
      </aside>

      <!-- Barre latérale desktop (≥1024px uniquement) -->
      <aside class="fixed inset-y-0 left-0 z-20 hidden w-64 flex-col bg-gradient-to-b from-[#172a39] to-[#0f1922] p-6 text-slate-100 lg:flex">
        <div class="flex items-center gap-2.5">
          <img src="../images/logo/launchericon-192x192.png" alt="" class="h-9 w-9 rounded-lg" />
          <span><b class="block font-serif text-xl leading-none">Oracle</b><small class="mt-1 block text-[8px] font-extrabold tracking-[0.14em] text-slate-400">API PARTNERS</small></span>
        </div>
        <div class="my-6 h-px bg-white/10"></div>
        <p class="mb-2.5 px-1 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Mon espace</p>
        <nav class="grid gap-1">
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[11px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="overview" data-active="bg-white/10 text-white">
            <i data-lucide="layout-dashboard" class="h-[17px] w-[17px]"></i><span>Vue d'ensemble</span>
          </button>
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[11px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="keys" data-active="bg-white/10 text-white">
            <i data-lucide="key-round" class="h-[17px] w-[17px]"></i><span>Mes clés API</span>
          </button>
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[11px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="usage" data-active="bg-white/10 text-white">
            <i data-lucide="bar-chart-3" class="h-[17px] w-[17px]"></i><span>Utilisation API</span>
          </button>
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[11px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="activity" data-active="bg-white/10 text-white">
            <i data-lucide="history" class="h-[17px] w-[17px]"></i><span>Activité</span>
          </button>
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[11px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="errors" data-active="bg-white/10 text-white">
            <i data-lucide="triangle-alert" class="h-[17px] w-[17px]"></i><span>Erreurs</span>
          </button>
        </nav>
        <p class="mb-2.5 mt-6 px-1 text-[9px] font-black uppercase tracking-[0.15em] text-slate-400">Compte</p>
        <nav class="grid gap-1">
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[11px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="settings" data-active="bg-white/10 text-white">
            <i data-lucide="settings" class="h-[17px] w-[17px]"></i><span>Paramètres</span>
          </button>
          <button class="nav-item flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[11px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white" data-page="docs" data-active="bg-white/10 text-white">
            <i data-lucide="book-open" class="h-[17px] w-[17px]"></i><span>Documentation</span>
          </button>
        </nav>
        <div class="mt-auto">
          <div class="mb-2 flex items-center gap-2.5 rounded-xl border border-white/10 bg-white/5 p-2.5">
            <span class="js-avatar grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-[#f6c99a] to-[#f0af7c] text-[10px] font-black text-ink">P</span>
            <div class="min-w-0">
              <b class="js-company block truncate text-[11px]">Partenaire</b>
              <small class="js-email block truncate text-[9px] text-slate-400">—</small>
            </div>
          </div>
          <button class="js-logout flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-[11px] font-extrabold text-slate-300 transition hover:bg-white/10 hover:text-white">
            <i data-lucide="log-out" class="h-[17px] w-[17px]"></i> Déconnexion
          </button>
        </div>
      </aside>

      <div class="pt-14 pb-24 lg:pl-64 lg:pb-0 lg:pt-16">
        <!-- Barre supérieure desktop uniquement -->
        <header class="fixed inset-x-0 top-0 z-10 hidden h-16 items-center justify-between border-b border-line bg-paper/90 px-8 backdrop-blur lg:left-64 lg:flex">
          <span class="text-[11px] text-slate-400">Portail partenaire <b class="mx-2 text-slate-300">/</b> <strong id="pageTitle" class="text-ink">Vue d'ensemble</strong></span>
          <div class="flex items-center gap-2.5 text-[10px] font-extrabold text-slate-500">
            <span class="h-[7px] w-[7px] rounded-full bg-emerald-400 shadow-[0_0_0_4px_rgba(52,211,153,0.2)]"></span>
            <span class="js-company">—</span>
            <span class="js-avatar grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-[#f6c99a] to-[#f0af7c] text-[10px] font-black text-ink">P</span>
          </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6 lg:px-8 lg:py-8">

          <!-- ============ VUE D'ENSEMBLE ============ -->
          <div id="page-overview" class="app-page">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-5">
              <div>
                <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep"><i data-lucide="sparkles" class="h-3.5 w-3.5"></i> Espace API</p>
                <h1 class="mt-2.5 font-serif text-4xl">Bonjour, <span id="helloName" class="text-orange">partenaire</span>.</h1>
                <p class="mt-2 max-w-md text-xs text-muted">Voici la santé de vos accès et de vos intégrations Oracle.</p>
              </div>
              <button class="nav-item flex items-center gap-2 rounded-lg bg-gradient-to-br from-orange to-orange-deep px-4 py-3 text-[11px] font-black text-white shadow-md shadow-orange/25 transition hover:-translate-y-0.5" data-page="keys">
                <i data-lucide="plus" class="h-4 w-4"></i> Nouvelle clé
              </button>
            </div>

            <div id="overviewStats" class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
              <div class="col-span-full p-8 text-center text-muted">Chargement…</div>
            </div>

            <div class="rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <span class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep"><i data-lucide="bar-chart-3" class="h-3.5 w-3.5"></i> Activité récente</span>
                  <h2 class="mt-2 font-serif text-2xl">Appels des 7 derniers jours</h2>
                </div>
              </div>
              <div id="overviewChart" class="mt-3"></div>
              <div class="mt-3 flex flex-wrap gap-4 text-[9px] text-muted">
                <span class="flex items-center gap-1.5"><i class="inline-block h-2 w-2 rounded-sm bg-emerald-600"></i> Trouvé</span>
                <span class="flex items-center gap-1.5"><i class="inline-block h-2 w-2 rounded-sm bg-amber-400"></i> Introuvable</span>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.5fr_1fr]">
              <div class="rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <span class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep"><i data-lucide="terminal" class="h-3.5 w-3.5"></i> Démarrage rapide</span>
                    <h2 class="mt-2 font-serif text-2xl">Votre premier appel</h2>
                  </div>
                  <span class="flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-[9px] font-black text-emerald-700">
                    <i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i> API disponible
                  </span>
                </div>
                <p class="mt-4 text-[11px] leading-relaxed text-muted">
                  Générez une clé dans « Mes clés API », puis placez-la dans l'en-tête <code class="text-orange-deep">X-API-Key</code> de votre autre site.
                </p>
                <pre class="mt-3 overflow-auto rounded-lg bg-ink p-4 text-[11px] leading-relaxed text-slate-200">curl "https://oracle.motoscanbj.com/api/v1/vehicles/lookup?type=plate&amp;value=2CR0770RB" \
  -H "X-API-Key: oracle_xxxxxxxxx..."</pre>
              </div>
              <div class="rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
                <div class="grid h-11 w-11 place-items-center rounded-xl bg-orange/10 text-orange-deep"><i data-lucide="shield-check"></i></div>
                <h2 class="mt-3.5 font-serif text-2xl">Bon réflexe sécurité</h2>
                <p class="mt-2 text-[11px] leading-relaxed text-muted">
                  Ne mettez jamais votre clé dans le code JavaScript public d'un site. Utilisez-la côté serveur, dans une variable d'environnement.
                </p>
                <code class="mt-3 block rounded-lg bg-paper p-2.5 text-[10px] text-orange-deep">ORACLE_API_KEY=oracle_...</code>
              </div>
            </div>
          </div>

          <!-- ============ MES CLÉS API ============ -->
          <div id="page-keys" class="app-page hidden">
            <div class="mb-6">
              <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep"><i data-lucide="key-round" class="h-3.5 w-3.5"></i> Accès API</p>
              <h1 class="mt-2.5 font-serif text-4xl">Mes clés API.</h1>
              <p class="mt-2 max-w-md text-xs text-muted">Une clé par application ou environnement. La clé complète est affichée une seule fois.</p>
            </div>

            <div class="rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
              <h2 class="font-serif text-2xl">Générer une nouvelle clé</h2>
              <p class="mt-1 text-[11px] text-muted">Commencez avec une limite basse, puis augmentez-la après validation.</p>
              <form id="keyForm" class="mt-4">
                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                  <div class="grid gap-1.5">
                    <label class="text-[11px] font-extrabold text-slate-600">Libellé</label>
                    <input id="keyLabel" required maxlength="150" placeholder="Ex. Mon site — production"
                      class="h-[42px] rounded-lg border border-line bg-white px-3 text-xs outline-none focus:border-orange focus:ring-2 focus:ring-orange/15" />
                  </div>
                  <div class="grid gap-1.5">
                    <label class="text-[11px] font-extrabold text-slate-600">Requêtes / minute</label>
                    <input id="rateLimit" type="number" min="1" max="1000" value="30" required
                      class="h-[42px] rounded-lg border border-line bg-white px-3 text-xs outline-none focus:border-orange focus:ring-2 focus:ring-orange/15" />
                  </div>
                  <div class="grid gap-1.5">
                    <label class="text-[11px] font-extrabold text-slate-600">Durée de vie <span class="font-medium text-slate-400">(optionnel)</span></label>
                    <select id="keyTtl" class="h-[42px] rounded-lg border border-line bg-white px-3 text-xs outline-none focus:border-orange focus:ring-2 focus:ring-orange/15">
                      <option value="0" selected>Jamais</option>
                      <option value="7">7 jours</option>
                      <option value="30">30 jours</option>
                      <option value="90">90 jours</option>
                      <option value="365">1 an</option>
                    </select>
                  </div>
                </div>
                <div class="mt-3.5 grid grid-cols-1 gap-3.5 sm:grid-cols-3">
                  <div class="grid gap-1.5">
                    <label class="text-[11px] font-extrabold text-slate-600">IP autorisée <span class="font-medium text-slate-400">(optionnel)</span></label>
                    <input id="allowedIp" placeholder="197.155.32.10"
                      class="h-[42px] rounded-lg border border-line bg-white px-3 text-xs outline-none focus:border-orange focus:ring-2 focus:ring-orange/15" />
                  </div>
                </div>
                <div class="mt-4">
                  <button type="submit" class="flex items-center gap-2 rounded-lg bg-gradient-to-br from-orange to-orange-deep px-4 py-3 text-[11px] font-black text-white shadow-md shadow-orange/25 transition hover:-translate-y-0.5">
                    <i data-lucide="plus" class="h-4 w-4"></i> Générer
                  </button>
                </div>
              </form>

              <div id="secretBox" class="mt-4 hidden flex-wrap items-center justify-between gap-4 rounded-xl border border-orange/40 bg-orange/10 p-4">
                <div>
                  <b class="flex items-center gap-1.5 text-[11px] text-orange-deep"><i data-lucide="triangle-alert" class="h-[15px] w-[15px]"></i> Copiez votre clé maintenant</b>
                  <span class="mt-1 block text-[9px] text-amber-800/80">Pour votre sécurité, elle ne sera plus jamais affichée.</span>
                </div>
                <div class="mt-2 flex w-full items-center gap-2">
                  <code id="newKey" class="min-w-0 flex-1 overflow-auto rounded-lg bg-white p-2.5 text-[11px] text-orange-deep"></code>
                  <button id="copyNewKey" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-line bg-white text-muted hover:text-orange">
                    <i data-lucide="copy" class="h-[15px] w-[15px]"></i>
                  </button>
                </div>
              </div>
            </div>

            <div class="mt-4 rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <span class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep"><i data-lucide="list-key" class="h-3.5 w-3.5"></i> Registre privé</span>
                  <h2 class="mt-2 font-serif text-2xl">Vos intégrations</h2>
                </div>
                <button class="js-refresh grid h-9 w-9 place-items-center rounded-lg border border-line bg-card text-muted hover:text-orange" data-page="keys"><i data-lucide="refresh-cw" class="h-[15px] w-[15px]"></i></button>
              </div>
              <div class="mt-4 overflow-x-auto rounded-xl border border-line">
                <table class="w-full border-collapse text-left lg:min-w-[640px]">
                  <thead>
                    <tr class="bg-paper text-[8px] uppercase tracking-wide text-slate-400">
                      <th class="px-3 py-3">Intégration</th>
                      <th class="hidden px-3 py-3 lg:table-cell">Préfixe</th>
                      <th class="hidden px-3 py-3 lg:table-cell">Limite</th>
                      <th class="hidden px-3 py-3 lg:table-cell">Expiration</th>
                      <th class="hidden px-3 py-3 lg:table-cell">Dernier appel</th>
                      <th class="px-3 py-3">État</th>
                      <th class="px-3 py-3"></th>
                    </tr>
                  </thead>
                  <tbody id="keysBody"></tbody>
                </table>
                <p class="border-t border-line bg-paper px-3 py-2 text-[9px] text-muted lg:hidden">Touchez une ligne pour voir tous les détails.</p>
              </div>
            </div>
          </div>

          <!-- ============ UTILISATION API ============ -->
          <div id="page-usage" class="app-page hidden">
            <div class="mb-6">
              <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep"><i data-lucide="bar-chart-3" class="h-3.5 w-3.5"></i> Traçabilité complète</p>
              <h1 class="mt-2.5 font-serif text-4xl">Utilisation de l'API.</h1>
              <p class="mt-2 max-w-lg text-xs text-muted">Chaque appel externe effectué avec l'une de vos clés — type de recherche, valeur, résultat, IP d'origine.</p>
            </div>

            <div id="usageSummary" class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
              <div class="col-span-full p-8 text-center text-muted">Chargement…</div>
            </div>

            <div class="rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
              <div class="mb-3.5 flex flex-wrap items-center gap-2">
                <select id="usageKeyFilter" class="h-9 rounded-lg border border-line bg-white px-2.5 text-[10px]"><option value="">Toutes les clés</option></select>
              </div>
              <div class="overflow-x-auto rounded-xl border border-line">
                <table class="w-full border-collapse text-left lg:min-w-[640px]">
                  <thead>
                    <tr class="bg-paper text-[8px] uppercase tracking-wide text-slate-400">
                      <th class="px-3 py-3">Date</th>
                      <th class="hidden px-3 py-3 lg:table-cell">Clé</th>
                      <th class="hidden px-3 py-3 lg:table-cell">Type</th>
                      <th class="hidden px-3 py-3 lg:table-cell">Valeur recherchée</th>
                      <th class="px-3 py-3">Résultat</th>
                      <th class="hidden px-3 py-3 lg:table-cell">IP</th>
                      <th class="px-3 py-3"></th>
                    </tr>
                  </thead>
                  <tbody id="usageBody"></tbody>
                </table>
                <p class="border-t border-line bg-paper px-3 py-2 text-[9px] text-muted lg:hidden">Touchez une ligne pour voir tous les détails.</p>
              </div>
            </div>
          </div>

          <!-- ============ ACTIVITÉ ============ -->
          <div id="page-activity" class="app-page hidden">
            <div class="mb-6">
              <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep"><i data-lucide="history" class="h-3.5 w-3.5"></i> Traçabilité du compte</p>
              <h1 class="mt-2.5 font-serif text-4xl">Historique d'activité.</h1>
              <p class="mt-2 max-w-lg text-xs text-muted">Connexions, création et révocation de clés, modifications de profil — avec horodatage et adresse IP.</p>
            </div>
            <div class="rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
              <div class="flex items-center justify-between">
                <h2 class="font-serif text-xl">Derniers événements</h2>
                <button class="js-refresh grid h-9 w-9 place-items-center rounded-lg border border-line bg-card text-muted hover:text-orange" data-page="activity"><i data-lucide="refresh-cw" class="h-[15px] w-[15px]"></i></button>
              </div>
              <div id="activityList" class="mt-3"></div>
            </div>
          </div>

          <!-- ============ ERREURS ============ -->
          <div id="page-errors" class="app-page hidden">
            <div class="mb-6">
              <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep"><i data-lucide="triangle-alert" class="h-3.5 w-3.5"></i> Surveillance</p>
              <h1 class="mt-2.5 font-serif text-4xl">Erreurs et incidents.</h1>
              <p class="mt-2 max-w-lg text-xs text-muted">Les erreurs d'authentification et les événements bloquants de votre espace.</p>
            </div>
            <div class="rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
              <div class="flex items-center justify-between">
                <h2 class="font-serif text-xl">Dernières erreurs</h2>
                <button class="js-refresh grid h-9 w-9 place-items-center rounded-lg border border-line bg-card text-muted hover:text-orange" data-page="errors"><i data-lucide="refresh-cw" class="h-[15px] w-[15px]"></i></button>
              </div>
              <div id="errorsList" class="mt-3"></div>
            </div>
          </div>

          <!-- ============ PARAMÈTRES ============ -->
          <div id="page-settings" class="app-page hidden">
            <div class="mb-6">
              <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep"><i data-lucide="settings" class="h-3.5 w-3.5"></i> Compte</p>
              <h1 class="mt-2.5 font-serif text-4xl">Paramètres.</h1>
              <p class="mt-2 max-w-lg text-xs text-muted">Informations de votre entreprise et sécurité de votre compte partenaire.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
              <div class="rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
                <h2 class="font-serif text-2xl">Profil</h2>
                <p class="mt-1 text-[11px] text-muted">Ces informations identifient votre entreprise auprès d'Oracle.</p>
                <form id="profileForm" class="mt-4">
                  <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                      <label class="text-[11px] font-extrabold text-slate-600">Entreprise</label>
                      <input id="settingsCompany" required maxlength="150" class="h-[42px] rounded-lg border border-line bg-white px-3 text-xs outline-none focus:border-orange focus:ring-2 focus:ring-orange/15" />
                    </div>
                    <div class="grid gap-1.5">
                      <label class="text-[11px] font-extrabold text-slate-600">Nom du contact</label>
                      <input id="settingsName" required maxlength="160" class="h-[42px] rounded-lg border border-line bg-white px-3 text-xs outline-none focus:border-orange focus:ring-2 focus:ring-orange/15" />
                    </div>
                  </div>
                  <div class="mt-3.5 grid gap-1.5">
                    <label class="text-[11px] font-extrabold text-slate-600">E-mail</label>
                    <input id="settingsEmail" type="email" required class="h-[42px] rounded-lg border border-line bg-white px-3 text-xs outline-none focus:border-orange focus:ring-2 focus:ring-orange/15" />
                  </div>
                  <p id="profileError" class="mt-3.5 hidden rounded-lg bg-red-50 px-3 py-2.5 text-[11px] font-bold text-red-700"></p>
                  <div class="mt-4">
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-ink px-4 py-3 text-[11px] font-black text-white shadow-md shadow-ink/20 transition hover:bg-[#26394a]">
                      <i data-lucide="save" class="h-4 w-4"></i> Enregistrer
                    </button>
                  </div>
                </form>
              </div>

              <div class="rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
                <h2 class="font-serif text-2xl">Sécurité</h2>
                <p class="mt-1 text-[11px] text-muted">Choisissez un mot de passe d'au moins 10 caractères.</p>
                <form id="passwordForm" class="mt-4">
                  <div class="grid gap-1.5">
                    <label class="text-[11px] font-extrabold text-slate-600">Mot de passe actuel</label>
                    <input id="currentPassword" type="password" required autocomplete="current-password" class="h-[42px] rounded-lg border border-line bg-white px-3 text-xs outline-none focus:border-orange focus:ring-2 focus:ring-orange/15" />
                  </div>
                  <div class="mt-3.5 grid grid-cols-1 gap-3.5 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                      <label class="text-[11px] font-extrabold text-slate-600">Nouveau mot de passe</label>
                      <input id="newPassword" type="password" minlength="10" required autocomplete="new-password" class="h-[42px] rounded-lg border border-line bg-white px-3 text-xs outline-none focus:border-orange focus:ring-2 focus:ring-orange/15" />
                    </div>
                    <div class="grid gap-1.5">
                      <label class="text-[11px] font-extrabold text-slate-600">Confirmer</label>
                      <input id="confirmNewPassword" type="password" minlength="10" required autocomplete="new-password" class="h-[42px] rounded-lg border border-line bg-white px-3 text-xs outline-none focus:border-orange focus:ring-2 focus:ring-orange/15" />
                    </div>
                  </div>
                  <p id="passwordError" class="mt-3.5 hidden rounded-lg bg-red-50 px-3 py-2.5 text-[11px] font-bold text-red-700"></p>
                  <div class="mt-4">
                    <button type="submit" class="flex items-center gap-2 rounded-lg bg-ink px-4 py-3 text-[11px] font-black text-white shadow-md shadow-ink/20 transition hover:bg-[#26394a]">
                      <i data-lucide="lock" class="h-4 w-4"></i> Modifier le mot de passe
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- ============ DOCUMENTATION ============ -->
          <div id="page-docs" class="app-page hidden">
            <div class="mb-6">
              <p class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.16em] text-orange-deep"><i data-lucide="book-open" class="h-3.5 w-3.5"></i> Référence technique</p>
              <h1 class="mt-2.5 font-serif text-4xl">Documentation API.</h1>
              <p class="mt-2 max-w-lg text-xs text-muted">Tout ce qu'il faut pour intégrer la vérification de véhicules Oracle dans votre produit.</p>
            </div>

            <div class="mb-3.5 rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
              <label class="block text-[9px] font-black uppercase tracking-[0.1em] text-muted">Authentification</label>
              <p class="mt-2 text-[11px] leading-relaxed text-muted">
                Toutes les requêtes doivent inclure l'en-tête <code class="text-orange-deep">X-API-Key</code> avec une clé générée
                depuis l'onglet « Mes clés API ». Une clé révoquée, supprimée, expirée, ou dépassant sa limite de débit est
                rejetée avant toute exécution.
              </p>
            </div>

            <div class="mb-3.5 rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
              <div class="flex flex-wrap items-center gap-2.5">
                <span class="rounded-md bg-emerald-50 px-2 py-1 text-[9px] font-black tracking-wide text-emerald-700">GET</span>
                <code class="text-[13px] font-extrabold">/api/v1/vehicles/lookup</code>
              </div>
              <p class="mt-2 text-[11px] text-muted">Recherche un véhicule par numéro de châssis (VIN) ou par plaque d'immatriculation.</p>

              <table class="mt-3 w-full border-collapse text-left">
                <thead><tr class="text-[8px] uppercase tracking-wide text-slate-400"><th class="border-b border-line py-2 pr-3">Paramètre</th><th class="border-b border-line py-2 pr-3">Type</th><th class="border-b border-line py-2">Description</th></tr></thead>
                <tbody class="text-[10px]">
                  <tr><td class="border-b border-line py-2 pr-3"><code>type</code></td><td class="border-b border-line py-2 pr-3">string</td><td class="border-b border-line py-2"><code>vin</code> ou <code>plate</code></td></tr>
                  <tr><td class="py-2 pr-3"><code>value</code></td><td class="py-2 pr-3">string</td><td class="py-2">Le numéro de châssis ou la plaque recherchée</td></tr>
                </tbody>
              </table>

              <label class="mt-4 block text-[9px] font-black uppercase tracking-[0.1em] text-muted">Exemple de requête</label>
              <pre id="curlExample" class="mt-2 overflow-auto rounded-lg bg-ink p-4 text-[11px] leading-relaxed text-slate-200">curl "https://oracle.motoscanbj.com/api/v1/vehicles/lookup?type=plate&amp;value=2CR0770RB" \
  -H "X-API-Key: oracle_xxxxxxxxx..."</pre>
              <button data-copy-target="#curlExample" class="mt-2 flex items-center gap-2 rounded-lg border border-line bg-card px-3 py-2 text-[10px] font-extrabold text-ink hover:bg-orange/10">
                <i data-lucide="copy" class="h-[15px] w-[15px]"></i> Copier
              </button>

              <label class="mt-4 block text-[9px] font-black uppercase tracking-[0.1em] text-muted">Réponse — véhicule trouvé</label>
              <pre class="mt-2 overflow-auto rounded-lg bg-ink p-4 text-[11px] leading-relaxed text-slate-200">{
  "ok": true,
  "found": true,
  "vehicle": {
    "owner": "…",
    "plate": "2CR0770RB",
    "department": "…",
    "vin": "…",
    "status": "OK",
    "updatedAt": "2026-09-01 10:00:00.000"
  },
  "previousOwners": []
}</pre>

              <label class="mt-4 block text-[9px] font-black uppercase tracking-[0.1em] text-muted">Réponse — aucun résultat</label>
              <pre class="mt-2 overflow-auto rounded-lg bg-ink p-4 text-[11px] leading-relaxed text-slate-200">{ "ok": true, "found": false }</pre>
            </div>

            <div class="mb-3.5 rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
              <label class="block text-[9px] font-black uppercase tracking-[0.1em] text-muted">Codes d'erreur</label>
              <table class="mt-2 w-full border-collapse text-left">
                <thead><tr class="text-[8px] uppercase tracking-wide text-slate-400"><th class="border-b border-line py-2 pr-3">Code</th><th class="border-b border-line py-2">Cause</th></tr></thead>
                <tbody class="text-[10px]">
                  <tr><td class="border-b border-line py-2 pr-3"><span class="rounded-md bg-red-50 px-2 py-1 font-black text-red-700">400</span></td><td class="border-b border-line py-2">Paramètres manquants ou invalides</td></tr>
                  <tr><td class="border-b border-line py-2 pr-3"><span class="rounded-md bg-red-50 px-2 py-1 font-black text-red-700">401</span></td><td class="border-b border-line py-2">Clé absente, invalide, révoquée, supprimée ou expirée</td></tr>
                  <tr><td class="border-b border-line py-2 pr-3"><span class="rounded-md bg-red-50 px-2 py-1 font-black text-red-700">403</span></td><td class="border-b border-line py-2">Adresse IP non autorisée pour cette clé</td></tr>
                  <tr><td class="py-2 pr-3"><span class="rounded-md bg-red-50 px-2 py-1 font-black text-red-700">429</span></td><td class="py-2">Limite de requêtes par minute dépassée (en-tête <code>Retry-After</code> fourni)</td></tr>
                </tbody>
              </table>
            </div>

            <div class="rounded-2xl border border-line bg-card p-5 shadow-sm shadow-ink/5">
              <label class="block text-[9px] font-black uppercase tracking-[0.1em] text-muted">Bonnes pratiques</label>
              <p class="mt-2 text-[11px] leading-relaxed text-muted">
                N'exposez jamais votre clé dans du code exécuté côté navigateur — utilisez-la depuis votre serveur, stockée
                dans une variable d'environnement. Restreignez-la à une IP fixe si votre intégration le permet. Donnez-lui
                une durée de vie plutôt que « Jamais » si elle sert à un test ponctuel : elle cessera de fonctionner
                d'elle-même sans action de votre part.
              </p>
            </div>
          </div>

        </main>
      </div>
    </section>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="assets/app.js" defer></script>
  </body>
</html>
