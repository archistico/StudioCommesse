# Installazione e aggiornamento

## Installazione pulita

Estrarre lo ZIP in una cartella vuota. Il pacchetto non contiene `.env.local`, database, allegati, backup, cache, log, `vendor`, `node_modules` o asset generati.

Eseguire:

```powershell
.\scripts\release-preflight.ps1 -Mode Package -SkipToolChecks
.\scripts\setup.ps1
.\scripts\validate.ps1
```

`setup.ps1` verifica PHP, Composer, Node.js e npm, genera `.env.local` con un `APP_SECRET` casuale, installa le dipendenze, compila gli asset, applica le migrazioni e guida la creazione del primo Socio.

Lo script è ripetibile. Su un’installazione già configurata conserva `.env.local`, database, allegati e backup.

## Aggiornamento consigliato

La modalità consigliata resta estrarre la nuova release in una cartella separata e, dalla cartella della nuova release, eseguire:

```powershell
.\scripts\update.ps1 `
    -TargetDirectory "C:\Percorso\StudioCommesse" `
    -Confirm UPDATE
```

Sostituire `C:\Percorso\StudioCommesse` con il percorso assoluto reale dell’installazione esistente; non usare letteralmente il segnaposto. Se sorgente e destinazione coincidono, `update.ps1` copia automaticamente la release in una cartella temporanea e prosegue da quello staging.

Prima di modificare l’installazione, lo script:

1. verifica release e installazione di destinazione;
2. crea e verifica un backup di database e allegati;
3. crea uno ZIP del codice precedente;
4. attiva la modalità manutenzione;
5. elimina soltanto i vecchi file distribuibili non più presenti nella release;
6. copia il nuovo codice conservando `.env.local`, `var`, `backups`, `vendor`, `node_modules` e gli allegati;
7. reinstalla dipendenze e asset, applica le migrazioni e valida lo schema;
8. disattiva la manutenzione solo dopo la verifica finale.

I materiali di sicurezza vengono conservati in `backups/update-YYYYMMDD-HHMMSS/`.

## Rollback

Se l’aggiornamento fallisce, lo script tenta automaticamente di:

- ripristinare il precedente ZIP del codice;
- reinstallare le dipendenze coerenti con i lock file precedenti;
- ripristinare database e allegati dal backup pre-aggiornamento;
- rieseguire migrazioni e validazione dello schema;
- disattivare la manutenzione soltanto a rollback concluso.

Il risultato è scritto in `ROLLBACK.txt` nella cartella di sicurezza. Se il rollback automatico non termina, la manutenzione resta attiva: non eseguire `clear-maintenance.ps1` finché codice, database e allegati non sono stati verificati.

## Controllo del pacchetto

```powershell
.\scripts\verify-release-package.ps1 -Archive .\dist\StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip
.\scripts\install-smoke.ps1 -Archive .\dist\StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip
```

Il primo comando confronta inventario e contenuti con il sorgente distribuibile. Il secondo estrae lo ZIP in una cartella temporanea e verifica che sia adatto a un’installazione pulita.

## Apache

La dipendenza `symfony/apache-pack` e `public/.htaccess` sono incluse nel pacchetto. Per la configurazione di Apache consultare `docs/APACHE.md`.

### Release con inventario invariato

Se la nuova release contiene lo stesso insieme di percorsi distribuibili dell'installazione corrente, la lista dei file obsoleti è vuota. `update.ps1` tratta questo caso come normale: non esegue rimozioni e prosegue con copia, setup e verifiche.
