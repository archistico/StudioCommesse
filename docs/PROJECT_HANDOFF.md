# Project Handoff

## Stato autoritativo

- ultima baseline validata: **M9.1 Hotfix 3 corretta**;
- archivio baseline: `StudioCommesse_M9.1_Hotfix3_Corretto.zip`;
- validazione confermata: `M9.1 HOTFIX 2 VALIDATION PASSED`;
- risultato baseline: **171 test e 1.631 asserzioni**;
- candidate corrente: **M9.2-A `0.9.2-M9.2-A-HF1`**;
- archivio candidate atteso: `StudioCommesse_M9.2-A_Hotfix1_PowerShell_Parser.zip`;
- gate richiesto: `M9.2-A HOTFIX 1 VALIDATION PASSED`.

## Correzioni autoritative già presenti nella baseline

- contratto PHP delle versioni di migrazione senza interpolazione accidentale di `$databasePath`;
- contratto del backup di sicurezza senza interpolazione accidentale di `$safetyBackupDirectory`;
- nota del filtro Attività come testo normale a larghezza piena;
- colonna `Dovuto` nell’elenco Clienti visibile esclusivamente ai soci;
- pulsante `Riepilogo dovuti` verso Economia;
- dovuto calcolato come `max(0, preventivo - incassi)` sulle commesse attive del cliente;
- dati economici non esposti ai collaboratori.

## M9.2-A candidate

- nessuna nuova funzionalità di dominio;
- versione e documentazione riallineate alla baseline reale;
- `.env.local` escluso dai pacchetti di distribuzione;
- aggiunto `scripts/package-release.ps1` per creare e verificare uno ZIP pulito;
- esclusi dati runtime, backup, database, allegati, log, cache, dipendenze installate e asset generati;
- aggiunti contratto statico, test e smoke test di packaging nel gate;
- nessuna migrazione e nessuna nuova dipendenza.

## Comandi ordinari

```powershell
.\scripts\setup.ps1 -SkipPartnerBootstrap
.\scripts\validate.ps1
.\scripts\load-fixtures.ps1
```

## Creazione pacchetto

```powershell
.\scripts\package-release.ps1
```

## Passo successivo dopo la validazione

M9.2-B: audit completo di autorizzazioni e riservatezza.

## M9.2-A Hotfix 1

Corregge esclusivamente la sintassi PowerShell del ciclo ricorsivo in `package-release.ps1`; nessuna funzione applicativa è stata modificata. Gate: `M9.2-A HOTFIX 1 VALIDATION PASSED`.
