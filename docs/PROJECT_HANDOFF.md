# Project Handoff

## Stato autoritativo

- ultima baseline validata: **M6.3 Hotfix 1**;
- esito: `M6.3 HOTFIX 1 VALIDATION PASSED`;
- archivio baseline: `StudioCommesse_M6.3_Hotfix1_Archive_Filter_Tests.zip`;
- candidate corrente: **M7 `0.7.0-M7`**;
- gate richiesto: `M7 VALIDATION PASSED`.

## M7 candidate

M7 introduce allegati protetti collegati a commessa e, facoltativamente, attività.

- nuova entità `Attachment` e migrazione `Version20260727234500`;
- classificazione tecnico/contrattuale/amministrativo/comunicazione/altro;
- area globale `/documenti` e archivio `/commesse/{id}/documenti`;
- collegamenti dal dettaglio commessa e dalla modifica attività;
- file memorizzati in `var/storage/attachments`, mai sotto `public`;
- nome fisico casuale, MIME rilevato, dimensione e SHA-256;
- massimo 10 MiB, formati autorizzati e controllo della firma;
- download soltanto tramite controller autenticato;
- modifica/eliminazione nella pagina documento, senza colonne azioni nelle tabelle;
- audit di caricamento, modifica, download ed eliminazione;
- test di storage, entità, controller, permessi e contratti;
- documentazione autoritativa in `docs/ATTACHMENTS.md`.

## Comandi

```powershell
.\scripts\setup.ps1 -SkipPartnerBootstrap
.\scripts\validate.ps1
.\scripts\load-fixtures.ps1
```

## Passo successivo dopo la validazione

M8 “Report, backup e rilascio 1.0”, iniziando dal backup atomico di database e spazio documentale.
