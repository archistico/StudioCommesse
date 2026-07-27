# Studio Commesse

Gestionale interno per uno studio tecnico, sviluppato con PHP 8.4, Symfony 8.1, Doctrine ORM 3, SQLite, Twig e Tabler 1.4.0.

**Candidate corrente:** `0.9.1-M9.1-HF2`  
**Ultima baseline validata:** M8  
**Gate atteso:** `M9.1 HOTFIX 2 VALIDATION PASSED`

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
- report soci degli importi dovuti per cliente, con preventivato, incassato e residuo;
- area soci `Controllo` con chiusura operativa, economica e complessiva;
- indicatori su commesse ferme, scostamenti e sovraccarico;
- riepiloghi per persona, cliente e mese con filtri persistenti;
- valutazione giornaliera di ogni collaboratore con attività svolte, descrizioni e ore totali;
- form CRUD responsive con campi compatti affiancati sui soli schermi grandi, testi estesi a riga intera e salvataggi a piena larghezza;
- pagine multi-colonna a una sola colonna sotto il breakpoint `lg`;
- DataTables 2.3.8 e Responsive 3.0.8 locali su tutte le tabelle, con ordinamento e ricerca rapida;
- navigazione coerente dalle colonne identificative e operazioni distruttive concentrate nelle schermate di modifica;
- area `Documenti` con allegati protetti di commessa e attività, classificazione, impronta SHA-256 e download autorizzato;
- report mensile soci con ore, movimenti per commessa, spese, incassi, documenti, azioni di audit e CSV;
- backup e ripristino coordinati di SQLite e allegati, con manifest, hash, verifica e backup automatico pre-ripristino;
- file salvati fuori da `public`, con limite 10 MiB e controlli su estensione, MIME e firma del contenuto;
- fixtures dimostrative ricche, deterministiche e idempotenti.

## Aggiornamento e validazione

Preservare `.env.local`, il database locale e `var/storage/attachments`, quindi eseguire:

```powershell
.\scripts\setup.ps1 -SkipPartnerBootstrap
.\scripts\validate.ps1
```

Esito atteso:

```text
M9.1 HOTFIX 2 VALIDATION PASSED
```

## Backup e ripristino

Creazione e verifica automatica dello ZIP:

```powershell
.\scripts\backup.ps1
```

Verifica dell’ultimo archivio creato:

```powershell
.\scripts\verify-backup.ps1
```

Verifica di un archivio specifico o del più recente che corrisponde a un pattern:

```powershell
.\scripts\verify-backup.ps1 -Archive ".\backups\StudioCommesse_Backup_20260727-230000.zip"
.\scripts\verify-backup.ps1 -Archive ".\backups\StudioCommesse_Backup_*.zip"
```

Il nome con data e ora mostrato negli esempi è illustrativo: usare il percorso stampato da `backup.ps1`. Se il file indicato non esiste, lo script elenca gli archivi disponibili.

Ripristino esplicito, con backup automatico dello stato corrente:

```powershell
.\scripts\restore-backup.ps1 -Archive "<backup.zip>" -Confirm RESTORE
```

Gli archivi non sono cifrati automaticamente e devono essere conservati in una posizione protetta. Se un ripristino fallisce, la manutenzione resta attiva e si rimuove soltanto dopo verifica con `.\scripts\clear-maintenance.ps1 -Confirm CLEAR`.

## Fixtures

Le fixtures non vengono mai caricate dal setup ordinario. Per generare o aggiornare il dataset dimostrativo:

```powershell
.\scripts\load-fixtures.ps1
```

Lo script seleziona esplicitamente l’ambiente `dev`, non usa `--force` e ripristina le variabili ambientali iniziali al termine.

Account principale:

```text
Username: demo.socio
Password: Demo-accesso-2026!
```

Il profilo standard genera 8 utenti, 10 clienti, 30 commesse, 200 attività, 600 registrazioni ore, 240 spese e 120 incassi. Le ore sono distribuite tra assegnatari, altri collaboratori e soci.

## Avvio locale

```powershell
.\scripts\start-server.ps1
```

In alternativa:

```powershell
php -d opcache.enable_cli=1 -S 127.0.0.1:8000 -t public public/router.php
```

## Prestazioni e diagnosi

Per un controllo ripetibile delle pagine principali, incluse le aree `/ore` e `/controllo`:

```powershell
.\scripts\diagnose-performance.ps1
```

La documentazione tecnica è nella cartella `docs`; vedere `docs/ATTACHMENTS.md`, `docs/ECONOMICS_VISIBILITY.md`, `docs/MONTHLY_REPORT.md` e `docs/BACKUP_RESTORE.md`.
