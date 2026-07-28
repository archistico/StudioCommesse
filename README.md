# Studio Commesse

Studio Commesse è un gestionale web interno per studi tecnici. Centralizza clienti, commesse, attività, ore, spese, incassi e documenti, con accessi distinti per Soci e Collaboratori.

L’applicazione usa Symfony, Doctrine ORM, SQLite, Twig e un’interfaccia Tabler responsive. Include report mensili, audit operativo, backup coordinati e strumenti per installazione e aggiornamento controllati.

## Funzioni principali

- gestione di clienti, commesse, responsabili, attività, priorità e scadenze;
- registrazione ore manuale o tramite timer, con riepiloghi per persona e commessa;
- preventivi, tariffe, spese, incassi e controllo della chiusura economica;
- documenti protetti associati a commesse e attività;
- report mensile, permessi per ruolo, registro audit filtrabile riservato ai Soci, blocco temporaneo del login, backup e ripristino.

## Requisiti

- PHP 8.4 o successivo;
- estensioni PHP `ctype`, `fileinfo`, `iconv`, `mbstring`, `PDO` e `pdo_sqlite`;
- Composer;
- Node.js 20 o successivo e npm;
- PowerShell per gli script Windows inclusi nel progetto;
- per Apache: Apache 2.4+, `mod_rewrite`, `DocumentRoot` sulla cartella `public` e `AllowOverride All`.

Per verificare la configurazione PHP usata dal terminale:

```powershell
php --ini
php -m
```

## Installazione

Estrarre il pacchetto in una cartella vuota ed eseguire:

```powershell
.\scripts\setup.ps1
```

Lo script controlla i requisiti, crea `.env.local`, installa le dipendenze, compila gli asset, applica le migrazioni e guida la creazione del primo Socio.

## Aggiornamento

È preferibile estrarre la nuova release in una cartella separata e avviare da lì:

```powershell
.\scripts\update.ps1 -TargetDirectory "C:\Percorso\StudioCommesse" -Confirm UPDATE
```

Sostituire `C:\Percorso\StudioCommesse` con il percorso assoluto reale dell’installazione esistente; non usare letteralmente il segnaposto.

Se viene avviato direttamente dall’installazione da aggiornare, lo script crea automaticamente uno staging temporaneo. Crea e verifica backup di dati e codice, attiva la manutenzione, aggiorna l’applicazione e tenta il rollback automatico in caso di errore. La procedura completa è descritta in `docs/INSTALL_UPDATE.md`.

## Apache

Il pacchetto include `symfony/apache-pack` e `public/.htaccess`. La configurazione del VirtualHost è descritta in `docs/APACHE.md`.

## Sicurezza e manuali

Il login è protetto da CSRF e rate limiting: dopo 5 tentativi falliti dalla stessa utenza e postazione, nuovi tentativi vengono sospesi per un’ora. Per dati personali e produzione usare HTTPS. I manuali utente, amministratore, sicurezza e accessibilità sono disponibili in `docs/`.

## Avvio

```powershell
.\scripts\start-server.ps1
```

L’applicazione è disponibile, salvo diversa configurazione, su `http://localhost:8000`.

## Script principali

| Script | Scopo |
| --- | --- |
| `setup.ps1` | Esegue l’installazione iniziale o riallinea un ambiente già configurato. |
| `update.ps1` | Aggiorna un’installazione esistente con backup, manutenzione e rollback. |
| `release-preflight.ps1` | Verifica sorgente, requisiti e pulizia del pacchetto. |
| `validate.ps1` | Esegue controlli statici, test e smoke test del pacchetto. |
| `benchmark-capacity.ps1` | Misura i profili isolati da 30, 200 e 600 commesse. |
| `start-server.ps1` | Avvia il server PHP locale. |
| `load-fixtures.ps1` | Carica dati dimostrativi; non viene eseguito dal setup ordinario. |
| `backup.ps1` | Crea e verifica un backup di database e allegati. |
| `verify-backup.ps1` | Controlla l’integrità di un backup esistente. |
| `restore-backup.ps1` | Ripristina un backup dopo conferma esplicita. |
| `clear-database-keep-users.ps1` | Svuota i dati operativi conservando utenti e migrazioni. |
| `package-release.ps1` | Genera uno ZIP pulito, senza dati locali o dipendenze installate. |

## Validazione

```powershell
.\scripts\validate.ps1
```

La documentazione tecnica e operativa dettagliata è disponibile nella cartella `docs`.
