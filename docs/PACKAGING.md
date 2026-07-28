# Packaging della distribuzione

## Obiettivo

Il pacchetto contiene codice, configurazioni distribuibili, lock file, migrazioni, test, script e documentazione. Non contiene dati, segreti o dipendenze installate della macchina usata per costruirlo.

## Creazione

```powershell
.\scripts\package-release.ps1
```

Output predefinito:

```text
dist/StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip
```

Percorso personalizzato:

```powershell
.\scripts\package-release.ps1 `
    -OutputPath "E:\rilasci\StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip"
```

Usare `-Force` soltanto per sostituire consapevolmente un archivio esistente.

## Verifica ed estrazione pulita

```powershell
.\scripts\verify-release-package.ps1 `
    -Archive ".\dist\StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip"

.\scripts\install-smoke.ps1 `
    -Archive ".\dist\StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip"
```

Il verificatore confronta inventario e contenuti con il sorgente distribuibile. Lo smoke test estrae il pacchetto in una cartella temporanea ed esegue il preflight `Package`.

## Completezza obbligatoria

Lo ZIP deve contenere, non vuoti, gli artefatti applicativi fondamentali e almeno un file per ciascuna famiglia:

- entità e repository;
- sicurezza e servizi;
- template;
- test;
- migrazioni.

Sono obbligatori anche gli script di setup, aggiornamento, preflight, backup, verifica ZIP e smoke installazione, oltre a `docs/INSTALL_UPDATE.md`.

L’inventario deve coincidere esattamente con tutti i file distribuibili del sorgente. File mancanti, inattesi, vuoti o duplicati senza distinzione tra maiuscole e minuscole rendono il pacchetto non valido.

## Elementi esclusi

- `.env.local` e `.env.*.local`;
- `vendor`, `node_modules` e `public/vendor`;
- `var`, inclusi database, allegati, lock, cache e log;
- `backups` e `dist`;
- metadati Git, IDE e sistema operativo;
- database SQLite e sidecar;
- log, file temporanei, copie di sicurezza e ZIP annidati.

`.env.local.dist` resta incluso perché il setup lo usa per generare una configurazione locale con `APP_SECRET` casuale.

## Sicurezza dei nomi

Gli script rifiutano traversal, percorsi assoluti, backslash, flussi NTFS alternativi, segmenti ambigui, nomi riservati Windows e duplicati case-insensitive.

## Gate

`validate.ps1`:

1. analizza la sintassi degli script PowerShell;
2. esegue i contratti di packaging e deployment;
3. crea un pacchetto smoke;
4. verifica inventario e contenuti;
5. estrae il pacchetto e controlla l’assenza di stato locale;
6. elimina il materiale temporaneo.

## Installazione e aggiornamento

Per una nuova installazione usare `setup.ps1`. Per aggiornare, estrarre la nuova release in una cartella separata e usare `update.ps1` con `-TargetDirectory` e `-Confirm UPDATE`.

La procedura completa, inclusi backup, manutenzione e rollback, è descritta in `docs/INSTALL_UPDATE.md`.
