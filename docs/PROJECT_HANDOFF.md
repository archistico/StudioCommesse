# Project Handoff

## Stato autoritativo

- ultima baseline validata: **M9.2-G `0.9.2-M9.2-G`**;
- risultato baseline: **235 test**, PHPStan senza errori, benchmark 30/200/600 entro budget e gate `M9.2-G VALIDATION PASSED`;
- archivio baseline: `StudioCommesse_M9.2-G_Performance_Capacity_Fix3.zip`;
- candidate corrente: **M9.2-H PHPStan Fix 1 `0.9.2-M9.2-H`**;
- archivio candidate: `StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip`;
- gate richiesto: `M9.2-H VALIDATION PASSED`;
- suite attesa: **244 test**, PHPStan senza errori e benchmark 30/200/600 entro budget.

## Contenuto M9.2-H

- login throttling Symfony: 5 fallimenti per combinazione utenza/IP in un’ora, più limite globale IP;
- nuovo evento audit `security.login_throttled`;
- tentativi falliti pseudonimizzati con impronta HMAC, senza nome utente in chiaro né ristampa nel form;
- log JSON minimizzati: impronte attore/IP e soli nomi dei campi di dettaglio;
- intestazioni CSP, HSTS su HTTPS, no-referrer, anti-frame, no-sniff, permissions policy e cache no-store;
- cookie di sessione HttpOnly, Secure automatico e SameSite Strict;
- skip link, landmark, `aria-current`, intestazioni tabella con scope, focus visibile e riduzione animazioni;
- comportamento responsive consolidato per azioni, tabelle e contenuti stretti;
- manuali utente, amministratore, sicurezza e accessibilità;
- nessuna migrazione, nuova rotta o dipendenza.

## Comandi

```powershell
.\scripts\setup.ps1 -SkipPartnerBootstrap
.\scripts\validate.ps1
.\scripts\package-release.ps1
```

## Passo successivo dopo la validazione

Procedere con M9.3: Release Candidate 1.0, blocco funzionale e collaudo completo.
