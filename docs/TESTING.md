# Testing

Gate autoritativo:

```powershell
.\scripts\validate.ps1
```

Esito atteso:

```text
M9.2-H VALIDATION PASSED
```

## Baseline di regressione

M9.2-G è la baseline validata: **235 test**, PHPStan senza errori e benchmark 30/200/600 superati. M9.2-H aggiunge nove test per login throttling, privacy audit, intestazioni HTTP, landmark, tastiera e contratti documentali, per un totale atteso di **244 test**.

## Controlli del gate

- preflight PHP 8.4+ ed estensioni obbligatorie;
- parsing di tutti gli script PowerShell;
- Composer e npm con audit;
- lint PHP, YAML e Twig;
- contratti Doctrine, Symfony, storage, report, backup, autorizzazioni, robustezza, dashboard, audit, end-to-end, deployment, prestazioni e M9.2-H;
- migrazioni e schema Doctrine di test;
- backup smoke;
- creazione, verifica ed estrazione pulita del pacchetto smoke;
- PHPStan livello 8;
- PHPUnit completo;
- benchmark isolato sui profili 30/200/600.

## Regressioni M9.2-H

La suite verifica:

- blocco del login dopo cinque fallimenti e rifiuto temporaneo anche della password corretta;
- audit distinto tra fallimento e throttling;
- assenza del nome utente e della motivazione tecnica nei fallimenti salvati;
- impronte HMAC stabili e log senza valori personali o descrittivi;
- CSP, HSTS su HTTPS, anti-frame, no-sniff, no-referrer e no-store;
- skip link, landmark, pagina corrente e scope delle intestazioni tabella;
- presenza dei quattro manuali e loro inclusione nel pacchetto.

## Verifica manuale

Eseguire la checklist in `docs/ACCESSIBILITY.md`, provare il login errato da una copia del database di test e controllare gli eventi Sicurezza nella pagina Audit. Non effettuare prove di blocco su account di produzione durante l’orario operativo.
