# Studio Commesse

Gestionale interno per uno studio tecnico, sviluppato con PHP 8.4, Symfony 8.1, Doctrine ORM 3, SQLite, Twig e Tabler 1.4.0.

**Candidate corrente:** `0.9.2-M9.2-A-HF1`  
**Correzione:** parser PowerShell del comando di packaging.  
**Ultima baseline validata:** M9.1 Hotfix 3 corretta  
**Archivio baseline:** `StudioCommesse_M9.1_Hotfix3_Corretto.zip`  
**Gate atteso:** `M9.2-A HOTFIX 1 VALIDATION PASSED`

## Funzioni disponibili

- utenti con ruoli Socio e Collaboratore;
- clienti e commesse con responsabile unico;
- attività, assegnazioni, priorità, avanzamento e scadenze;
- registrazione manuale delle ore e timer personale;
- autore delle ore indipendente dall’assegnatario dell’attività;
- dettaglio consuntivato per persona nelle attività di una commessa;
- area globale `Ore` con filtri per periodo, commessa, attività, persona e fatturabilità;
- paginazione delle registrazioni a 50 righe;
- durate uniformate nel formato `ore:minuti`;
- preventivo e regole tariffarie per collaboratore, commessa e attività;
- costo storico congelato su ogni registrazione ore;
- spese e incassi semplici, senza contabilità fiscale;
- visibilità economica per ruolo: i collaboratori vedono e gestiscono soltanto le proprie spese;
- riepilogo economico con costi, incassato, residuo e margine;
- importi dovuti per cliente, visibili esclusivamente ai soci;
- area soci `Controllo` con chiusura operativa, economica e complessiva;
- indicatori su commesse ferme, scostamenti e sovraccarico;
- riepiloghi per persona, cliente e mese con filtri persistenti;
- valutazione giornaliera di ogni collaboratore con attività svolte, descrizioni e ore totali;
- form CRUD responsive con campi compatti affiancati sui soli schermi grandi, testi estesi a riga intera e salvataggi a piena larghezza;
- pagine multi-colonna a una sola colonna sotto il breakpoint `lg`;
- DataTables 2.3.8 e Responsive 3.0.8 locali su tutte le tabelle;
- navigazione coerente dalle colonne identificative e operazioni distruttive concentrate nelle schermate di modifica;
- area `Documenti` con allegati protetti di commessa e attività, classificazione, impronta SHA-256 e download autorizzato;
- report mensile soci con ore, movimenti per commessa, spese, incassi, documenti, azioni di audit e CSV;
- backup e ripristino coordinati di SQLite e allegati, con manifest, hash, verifica e backup automatico pre-ripristino;
- fixtures dimostrative ricche, deterministiche e idempotenti.

## Aggiornamento e validazione

Durante un aggiornamento preservare sempre `.env.local`, il database locale, `var/storage/attachments` e gli archivi di backup, quindi eseguire:

```powershell
.\scripts\setup.ps1 -SkipPartnerBootstrap
.\scripts\validate.ps1
```

Esito atteso:

```text
M9.2-A HOTFIX 1 VALIDATION PASSED
```

La baseline corretta M9.1 Hotfix 3 è stata validata con 171 test e 1.631 asserzioni.

## Creazione del pacchetto di distribuzione

Per produrre uno ZIP pulito e già verificato:

```powershell
.\scripts\package-release.ps1
```

Output predefinito:

```text
dist/StudioCommesse_M9.2-A_Hotfix1_PowerShell_Parser.zip
```

Il comando non modifica i dati locali. Dal pacchetto esclude automaticamente `.env.local`, database, allegati operativi, backup, log, cache, `vendor`, `node_modules`, asset generati e altri file locali. Il gate crea inoltre un pacchetto temporaneo e ne verifica l’inventario. Vedere `docs/PACKAGING.md`.

## Backup e ripristino

Creazione e verifica automatica dello ZIP operativo:

```powershell
.\scripts\backup.ps1
```

Verifica dell’ultimo archivio creato:

```powershell
.\scripts\verify-backup.ps1
```

Ripristino esplicito, con backup automatico dello stato corrente:

```powershell
.\scripts\restore-backup.ps1 -Archive "<backup.zip>" -Confirm RESTORE
```

Gli archivi operativi non sono cifrati automaticamente e devono essere conservati in una posizione protetta. Vedere `docs/BACKUP_RESTORE.md`.

## Fixtures

Le fixtures non vengono mai caricate dal setup ordinario. Per generare o aggiornare il dataset dimostrativo:

```powershell
.\scripts\load-fixtures.ps1
```

Account principale del dataset dimostrativo:

```text
Username: demo.socio
Password: Demo-accesso-2026!
```

Il profilo standard genera 8 utenti, 10 clienti, 30 commesse, 200 attività, 600 registrazioni ore, 240 spese e 120 incassi.

## Avvio locale

```powershell
.\scripts\start-server.ps1
```

In alternativa:

```powershell
php -d opcache.enable_cli=1 -S 127.0.0.1:8000 -t public public/router.php
```

## Prestazioni e diagnosi

```powershell
.\scripts\diagnose-performance.ps1
```

La documentazione tecnica è nella cartella `docs`.
