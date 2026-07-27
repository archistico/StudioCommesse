# Studio Commesse

Gestionale interno per uno studio tecnico, sviluppato con PHP 8.4, Symfony 8.1, Doctrine ORM 3, SQLite, Twig e Tabler 1.4.0.

**Candidate corrente:** `0.7.0-M7`  
**Ultima baseline validata:** M6.3 Hotfix 1  
**Gate atteso:** `M7 VALIDATION PASSED`

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
- riepilogo economico con costi, incassato, residuo e margine;
- area soci `Controllo` con chiusura operativa, economica e complessiva;
- indicatori su commesse ferme, scostamenti e sovraccarico;
- riepiloghi per persona, cliente e mese con filtri persistenti;
- valutazione giornaliera di ogni collaboratore con attività svolte, descrizioni e ore totali;
- form CRUD responsive con campi compatti affiancati sui soli schermi grandi, testi estesi a riga intera e salvataggi a piena larghezza;
- pagine multi-colonna a una sola colonna sotto il breakpoint `lg`;
- DataTables 2.3.8 e Responsive 3.0.8 locali su tutte le tabelle, con ordinamento e ricerca rapida;
- navigazione coerente dalle colonne identificative e operazioni distruttive concentrate nelle schermate di modifica;
- area `Documenti` con allegati protetti di commessa e attività, classificazione, impronta SHA-256 e download autorizzato;
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
M7 VALIDATION PASSED
```

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

La documentazione tecnica è nella cartella `docs`; per gli allegati vedere `docs/ATTACHMENTS.md`.
