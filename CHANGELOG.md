# Changelog

## 0.9.2-M9.2-H Fix 1 - PHPStan completeness

- Rimossa la chiamata ridondante a `array_values()` su una lista già ordinata in `AuditPrivacyGuard`.
- Aggiunta `AuditAction::LoginThrottled` alla classificazione esaustiva del report mensile.
- Rafforzati i contratti M9.2-H per coprire entrambe le regressioni PHPStan.
- Nessuna modifica funzionale, migrazione, rotta o dipendenza.
- Candidate: `StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip`.


## 0.9.2-M9.2-H — Accessibilità, sicurezza login e manuali

- Login protetto da throttling Symfony: 5 fallimenti per combinazione utenza/IP in un’ora, con limite globale IP.
- Audit distinto dei blocchi temporanei e pseudonimizzazione dei nomi utente tentati.
- Dopo un errore il form non ristampa l’identificativo inserito.
- Log JSON minimizzati con impronte HMAC per attore e IP e senza valori descrittivi.
- Intestazioni HTTP difensive, session cookie SameSite Strict e cache no-store.
- Skip link, landmark, pagina corrente, focus visibile, tabelle accessibili e miglioramenti responsive.
- Manuali utente, amministratore, sicurezza e accessibilità.
- Nessuna migrazione, nuova rotta o dipendenza.
- Candidate: `StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip`.
- Attesi 244 test e gate `M9.2-H VALIDATION PASSED`.

## 0.9.2-M9.2-G Fix 3 — Allegati benchmark compatibili con backup

- Corrette le chiavi `storage_key` degli allegati sintetici: ora rispettano il formato documentale reale `YYYY/MM/<32 caratteri esadecimali>.estensione` validato dal backup.
- Il seeder continua a creare i file fisici nella directory allegati isolata e mantiene coerenti dimensione e SHA-256 con i metadati del database.
- Rafforzati i contratti PHPUnit e CLI per vietare il precedente prefisso `benchmark/...` e richiedere file fisici e chiavi compatibili.
- La candidate corrente è `StudioCommesse_M9.2-G_Performance_Capacity_Fix3.zip`.
- Restano attesi 235 test e il gate `M9.2-G VALIDATION PASSED`; nessuna modifica funzionale, migrazione, rotta o dipendenza.

## 0.9.2-M9.2-G Fix 2 — Bootstrap benchmark SQLite

- Corretto `benchmark-capacity.ps1`: SQLite non usa più `doctrine:database:create`, operazione non supportata dalla piattaforma DBAL.
- Dopo la pulizia del file temporaneo, `doctrine:migrations:migrate` apre e crea direttamente il database isolato del profilo.
- Rafforzati i contratti PHPUnit e CLI per vietare il comando non supportato e richiedere il bootstrap tramite migrazioni.
- La candidate corrente è `StudioCommesse_M9.2-G_Performance_Capacity_Fix2.zip`.
- Restano attesi 235 test e il gate `M9.2-G VALIDATION PASSED`; nessuna modifica funzionale, migrazione, rotta o dipendenza.

## 0.9.2-M9.2-G Fix 1 — Compatibilità PHPStan e DBAL 4

- Corretti i tipi PHPStan del comando benchmark: collezioni attività, liste di identificativi e shape delle metriche.
- Il seeder dei dataset usa ora prepared statement DBAL 4 con `bindValue()` e `executeStatement()` senza parametri.
- Rafforzati i contratti statici per vietare `Statement::executeStatement([...])` e verificare i tipi del rapporto benchmark.
- La candidate corrente è `StudioCommesse_M9.2-G_Performance_Capacity_Fix1.zip`.
- Restano attesi 235 test e il gate `M9.2-G VALIDATION PASSED`; nessuna modifica funzionale, migrazione, rotta o dipendenza.

## 0.9.2-M9.2-G — Prestazioni e capacità

- M9.2-F.1 è stata validata con 230 test, PHPStan senza errori e gate `M9.2-F.1 VALIDATION PASSED`.
- Consolidati i dieci indicatori numerici della dashboard in una sola query DBAL.
- Aggiunta la migrazione `Version20260728160000` con indici mirati per dashboard, liste recenti, intervalli temporali, economia e audit.
- Aggiunti dataset deterministici e isolati da 30, 200 e 600 commesse.
- Aggiunto il benchmark di dashboard, commesse, attività, ore, controllo, economia, report mensile, audit, dettaglio commessa e backup-ripristino.
- I rapporti JSON includono mediana, P95, picco memoria e violazioni dei budget.
- Attesi 235 test; nessuna nuova rotta, dipendenza o modifica ai permessi.
- Gate richiesto: `M9.2-G VALIDATION PASSED`.

## 0.9.2-M9.2-F.1 Fix 2 — Update senza file obsoleti

- Corretto `update.ps1` quando release e installazione hanno lo stesso inventario: una lista vuota di file obsoleti non viene più passata come argomento non valido.
- `Remove-DeployableFiles` accetta esplicitamente collezioni vuote e termina senza effetti.
- Rafforzati i contratti PowerShell/PHPUnit per coprire aggiornamenti senza file da eliminare.
- Riallineati handoff, packaging, smoke e documentazione alla candidate `StudioCommesse_M9.2-F.1_Apache_Self_Staging_Fix2.zip`.
- Nessuna modifica funzionale, migrazione, rotta o dipendenza.

## 0.9.2-M9.2-F.1 — Candidate corretta (Fix 1)

- Corretto il contratto documentale di packaging: la baseline autoritativa è M9.2-F e non M9.2-E.2.
- Riallineati nome della candidate, handoff, packaging, checklist e contratti a `StudioCommesse_M9.2-F.1_Apache_Self_Staging_Fix1.zip`.
- Nessuna modifica funzionale, migrazione, rotta o dipendenza.
- Versione e gate restano `0.9.2-M9.2-F.1` e `M9.2-F.1 VALIDATION PASSED`.

## 0.9.2-M9.2-F.1 — Apache e aggiornamento con staging automatico

- M9.2-F è stata validata con gate `M9.2-F VALIDATION PASSED` e diventa la baseline autoritativa.
- Aggiunta la dipendenza Composer ufficiale `symfony/apache-pack` e il file `public/.htaccess` della recipe Symfony.
- Documentata la configurazione Apache con `DocumentRoot` su `public`, `mod_rewrite` e `AllowOverride All`.
- `update.ps1` crea automaticamente una copia temporanea della release quando sorgente e destinazione coincidono.
- Rafforzati preflight, packaging, contratti e test; nessuna migrazione o modifica funzionale.
- Gate richiesto: `M9.2-G VALIDATION PASSED`.

## 0.9.2-M9.2-F — Candidate corretta (Fix 1)

- Corretta la sintassi di `scripts/update.ps1`: due messaggi racchiusi tra apici singoli contenevano un apostrofo tipografico, interpretato dal parser PowerShell come terminazione anomala della stringa.
- I messaggi interessati usano ora stringhe con doppi apici e apostrofo ASCII.
- Aggiunto un contratto che vieta virgolette e apostrofi tipografici in tutti gli script PowerShell distribuibili.
- Documentato che `C:\Percorso\StudioCommesse` è un segnaposto da sostituire con il percorso reale dell'installazione.
- Nessuna modifica funzionale, migrazione, rotta o dipendenza.
- Versione e gate restano `0.9.2-M9.2-F` e `M9.2-F VALIDATION PASSED`, perché la prima candidate M9.2-F non è stata validata.

## 0.9.2-M9.2-F — Candidate

- Basata sulla M9.2-E.2 validata con 223 test e PHPStan senza errori.
- Aggiunto preflight condiviso per installazione, aggiornamento e pacchetto pulito.
- Aggiunto aggiornamento da release separata con backup dati verificato, snapshot del codice, manutenzione, rimozione dei file obsoleti e verifica finale.
- Aggiunto rollback automatico di codice, database e allegati; la manutenzione resta attiva se il rollback non termina.
- Aggiunto smoke test dello ZIP estratto e documentazione operativa `docs/INSTALL_UPDATE.md`.
- Allineate le versioni applicative e degli asset.
- Nessuna migrazione, nuova rotta o dipendenza.
- Gate atteso: `M9.2-F VALIDATION PASSED`.

## 0.9.2-M9.2-E.2 — Validata

- Basata sulla M9.2-E.1 validata con 222 test e PHPStan senza errori.
- Le card dashboard sono rinominate in “Commesse in attesa” e “Commesse in ritardo”.
- Il totale “Ore effettuate” considera soltanto le registrazioni concluse iniziate nel mese corrente.
- Valore e titolo “Ore effettuate” sono disposti sulla stessa riga, con “Registrazioni concluse” sulla seconda.
- Rimossa la card “Ore pianificate” e il relativo aggregato repository non più utilizzato.
- Aggiunta regressione sul confine mensile e contratto di packaging M9.2-E.2.
- Nessuna migrazione, nuova rotta o dipendenza.
- Validata dall’utente con `M9.2-E.2 VALIDATION PASSED`; diventa la baseline autoritativa per M9.2-F.

## 0.9.2-M9.2-E.1 — Validata

- Aggiunto al report mensile il riepilogo per utente con ore concluse, tariffa standard attuale, costo standard teorico, costo storico effettivo e scostamento.
- Timer aperti esclusi; utenti disattivati inclusi quando hanno lavorato; tariffa standard zero mostrata come non impostata.
- Aggiunta esportazione CSV separata e coerente con i filtri mese/commessa.
- Matrice autorizzazioni estesa a 48 rotte; report ed export restano riservati ai Soci.
- Nessuna migrazione o nuova dipendenza.
- Validata dall’utente con `M9.2-E.1 VALIDATION PASSED`; diventa la baseline autoritativa per M9.2-E.2.

## 0.9.2-M9.2-E — Validata (Fix 1)

- Corretti i due errori Doctrine nei test end-to-end di archiviazione e ripristino.
- Dopo ogni richiesta HTTP le entità vengono ricaricate tramite identificativo, senza richiamare `refresh()` su istanze detached dal reset dei servizi Doctrine.
- Rafforzati il contratto PHPUnit e il contratto CLI M9.2-E per impedire la regressione.
- Nessuna modifica funzionale, migrazione, rotta o dipendenza.
- Validata dall’utente con `M9.2-E VALIDATION PASSED`; diventa la baseline autoritativa per M9.2-E.1.

## 0.9.2-M9.2-E — Candidate

- Aggiunti flussi end-to-end per chiusura completa e archiviazione della commessa.
- Verificati creazione attività e registrazione ore da parte del Collaboratore senza esposizione dei dati economici riservati.
- Contrattualizzata la rilevazione delle chiusure incoerenti e la sequenza di ripristino cliente/commessa.
- Esteso il test di backup-ripristino a utenti, cliente, commessa, attività, ore, spesa, incasso, audit e allegato.
- Aggiunti `scripts/m92e-end-to-end-contract.php`, `tests/Controller/EndToEndWorkflowTest.php`, `tests/Project/EndToEndWorkflowContractTest.php` e `docs/END_TO_END_FLOWS.md`.
- Nessuna migrazione, nuova rotta o dipendenza.
- Gate atteso: `M9.2-E VALIDATION PASSED`.

## 0.9.2-M9.2-D — Candidate corretta (Fix 1)

- Corretto il test del filtro audit: l'assenza di eventi esclusi viene verificata solo nelle righe risultato, senza confondere le opzioni del selettore azioni.
- Riallineato il contratto di packaging alla baseline autoritativa M9.2-C Hotfix 4 e all'archivio corrente M9.2-D Fix 1.
- Nessuna modifica funzionale, migrazione o nuova dipendenza.
- Versione e gate restano `0.9.2-M9.2-D` e `M9.2-D VALIDATION PASSED`, perché la prima candidate M9.2-D non è stata validata.

## 0.9.2-M9.2-D — Candidate

- Basata esclusivamente sulla baseline validata M9.2-C Hotfix 4.
- Aggiunta area Soci `Audit operativo` con filtri per gruppo, azione, attore, request ID e date.
- Aggiunta paginazione server-side ed esportazione CSV UTF-8 con filtri coerenti.
- Gli eventi audit acquisiscono automaticamente request ID, rotta, metodo HTTP e IP disponibile.
- Aggiunti log JSON separati `security-audit.log` e `operations.log`.
- Gli errori HTTP gestiti e le anomalie database registrano contesto di correlazione.
- Matrice autorizzazioni estesa a 47 rotte; Collaboratori esclusi da consultazione ed export.
- README mantenuto breve e senza cronologia milestone.
- Nessuna migrazione e nessuna nuova dipendenza.
- Gate atteso: `M9.2-D VALIDATION PASSED`.

## 0.9.2-M9.2-C-HF4 — Validata (Fix 2)

- Riallineato il contratto M6.3 alla semantica corrente del filtro attività: il controllo verifica controller, template e comportamento `assignee=me`, senza dipendere dalla forma interna di una vecchia asserzione PHPUnit.
- Riscritto il README come introduzione breve e stabile al progetto, senza milestone, baseline o gate storici.
- Conservate le informazioni essenziali su funzioni, requisiti, installazione, aggiornamento, avvio, validazione e script operativi.
- Nessuna modifica a dominio, schema, migrazioni o dipendenze.
- Validata dall’utente con `M9.2-C HOTFIX 4 VALIDATION PASSED`; diventa la baseline autoritativa per M9.2-D.

## 0.9.2-M9.2-C-HF4 — Candidate corretta (Fix 1)

- Corretti i quattro esiti negativi della prima candidate Hotfix 4 senza modificare schema, dipendenze o logica di dominio.
- Il test del filtro `assignee=me` verifica ora il selettore auto-inviato e non cerca più il pulsante “Mostra”, intenzionalmente rimosso.
- La spiegazione del filtro chiarisce che l’assegnatario è la persona responsabile dell’attività, non l’autore delle ore.
- La tabella delle ore recenti mostra anche la descrizione della registrazione.
- La seconda riga della dashboard usa colonne soltanto dal breakpoint `lg`, rispettando il contratto responsive.
- Versione e gate restano quelli della Hotfix 4, che non era stata validata.
- Gate atteso: `M9.2-C HOTFIX 4 VALIDATION PASSED`.

## 0.9.2-M9.2-C-HF4 — Candidate iniziale

- Basata esclusivamente sulla baseline M9.2-C Hotfix 3 validata con 203 test, 2.125 asserzioni e PHPStan senza errori.
- Il quadro operativo della dashboard è stato spostato in una seconda riga di card.
- Aggiunti i totali consolidati di ore effettuate e ore pianificate sulle attività ancora aperte.
- Le tabelle di commesse, attività e registrazioni ore aggiornate di recente occupano ora l’intera larghezza.
- `/attivita` mostra subito le attività dell’utente corrente e il cambio assegnatario invia automaticamente il filtro.
- La priorità in `/commesse` è rappresentata da icone SVG con etichette accessibili.
- Aggiunti test funzionali e contratto di packaging dedicati.
- Nessuna migrazione e nessuna nuova dipendenza.
- Gate atteso: `M9.2-C HOTFIX 4 VALIDATION PASSED`.

## 0.9.2-M9.2-C-HF3 — Candidate

- Corretto il contratto CLI della Hotfix 2, che dipendeva dalle terminazioni di riga della piattaforma e falliva su Windows pur in presenza del corretto `else { return; }`.
- I contenuti letti dal contratto vengono normalizzati da CRLF a LF e il ritorno esplicito viene verificato strutturalmente con un’espressione regolare indipendente da indentazione e newline.
- Aggiunti contratto CLI e test PHPUnit dedicati alla regressione CRLF/LF; entrambi sono obbligatori nel pacchetto di distribuzione.
- Nessuna modifica al subscriber, alla logica applicativa, allo schema dati o alle dipendenze.
- Gate atteso: `M9.2-C HOTFIX 3 VALIDATION PASSED`.


## 0.9.2-M9.2-C-HF2 — Candidate

- Corretti i tre errori PHPStan rilevati nella candidate M9.2-C Hotfix 1 senza ridurre il livello di analisi o aggiungere esclusioni.
- `DatabaseExceptionSubscriber` usa rami completi con ritorno esplicito, eliminando il confronto impossibile fra `null` e template Twig già determinato.
- `MaintenanceMode::isEnabled()` è dichiarato `@phpstan-impure`, perché legge un marker filesystem che può cambiare fra due chiamate consecutive.
- `MonthlyReportService` gestisce esplicitamente `AuditAction::TimeEntryUpdated` nel riepilogo delle azioni del mese.
- Aggiunti contratto CLI e test PHPUnit dedicati; i relativi file sono obbligatori nel pacchetto di distribuzione.
- Nessuna migrazione, nuova dipendenza o modifica del modello funzionale.
- Gate atteso: `M9.2-C HOTFIX 2 VALIDATION PASSED`.

## 0.9.2-M9.2-C-HF1 — Candidate

- Corretto il gate ambientale: `fileinfo` resta obbligatoria, ma viene verificata prima di Composer, npm e dei contratti applicativi.
- Aggiunto `scripts/php-runtime-contract.php`, condiviso da setup, validazione e controllo storage.
- In caso di estensione mancante vengono mostrati PHP CLI, `php.ini` caricato, `extension_dir`, presenza di `php_fileinfo.dll` e comandi di verifica Windows.
- Aggiunti contratto e test di regressione; i nuovi file sono obbligatori nel pacchetto di distribuzione.
- Candidate cumulativa costruita su M9.2-A Hotfix 2 e M9.2-B, entrambe ancora da validare; la baseline ufficiale resta M9.2-A Hotfix 1 corretta localmente.
- Aggiunto `AuditedTransaction`: mutazione e record audit condividono la stessa transazione Doctrine; il mirror Monolog viene scritto soltanto dopo il commit.
- I controller di commesse, attività, clienti, utenti, economia e ore non eseguono più flush autonomi prima dell’audit.
- SQLite viene configurato nelle transazioni applicative con `busy_timeout = 5000` e chiavi esterne attive.
- Inserimento/modifica ore e avvio/arresto timer condividono un lock esclusivo applicativo per evitare gare fra controlli e salvataggi.
- L’eliminazione degli allegati usa quarantena, ripristino compensativo su rollback e purga post-commit fuori dallo storage attivo.
- Le richieste durante manutenzione o lock esclusivo ricevono subito HTTP 503 senza attese indefinite.
- Aggiunti `RequestIdSubscriber`, header `X-Request-ID`, gestione sicura dei conflitti 409 e dei lock database 503.
- Aggiunte pagine dedicate 405, 409, 422, 500 e 503 senza dettagli tecnici.
- Aggiunti test su transazione/audit, lock non bloccanti, quarantena allegati, request ID e pagina 405.
- Aggiunti `docs/ROBUSTNESS.md` e `scripts/m92c-robustness-contract.php`.
- Nessuna nuova dipendenza e nessuna modifica allo schema dati.
- Gate atteso: `M9.2-C HOTFIX 1 VALIDATION PASSED`.

## 0.9.2-M9.2-A-HF1 — Validata localmente dopo correzione

- Corretto il parser PowerShell di `scripts/package-release.ps1`: nei due blocchi `if` gli operatori `-or` sono collocati alla fine della condizione precedente e non all’inizio della riga successiva.
- Tutti gli script PowerShell hanno superato il parsing; PHPStan e PHPUnit sono risultati verdi con 174 test e 1.651 asserzioni; packaging smoke superato.
- Le parentesi intorno a `Get-ChildItem` sono mantenute per leggibilità, ma non costituiscono la causa della correzione.
- Nessuna modifica alle funzionalità applicative, allo schema Doctrine o alle dipendenze.

## 0.9.2-M9.2-A — Candidate iniziale non validata

- Basata esclusivamente sulla baseline validata `StudioCommesse_M9.1_Hotfix3_Corretto.zip`.
- Riallineati versione, gate, README, roadmap, handoff, avvio nuova chat, testing e checklist.
- Aggiunto `package-release.ps1` per creare e verificare uno ZIP di distribuzione pulito.
- Esclusi dal pacchetto `.env.local`, database, allegati, backup, log, cache, dipendenze installate, asset generati e file temporanei.
- Aggiunti contratto statico, test e smoke test del packaging nel gate.
- Nessuna modifica funzionale, nessuna migrazione e nessuna nuova dipendenza.
- Gate atteso: `M9.2-A VALIDATION PASSED`.

## 0.9.1-M9.1-HF3 — Baseline validata corretta

- Archivio autoritativo: `StudioCommesse_M9.1_Hotfix3_Corretto.zip`.
- Validazione confermata con `M9.1 HOTFIX 2 VALIDATION PASSED`: 171 test e 1.631 asserzioni.
- Corretto il test PHP che interpolava erroneamente `$safetyBackupDirectory`.
- Nota del filtro in `/attivita` resa testo normale a larghezza piena.
- Aggiunta in `/clienti` la colonna `Dovuto`, visibile esclusivamente ai soci.
- Aggiunto il collegamento `Riepilogo dovuti` verso Economia.
- Dovuto calcolato come `max(0, preventivo - incassi)` e sommato sulle sole commesse attive del cliente.
- Dati economici confermati non visibili ai collaboratori.

## 0.9.1-M9.1-HF2 — Candidate

- Basata esclusivamente sulla candidate M9.1 Hotfix 1 derivata dalla baseline validata M8.
- Corretto `BackupContractTest`: la stringa attesa `readMigrationVersions($databasePath)` usa ora apici singoli e non interpola accidentalmente la variabile PHP non definita.
- Nessuna modifica alla logica di backup, ripristino, filtri Ore o report economici.
- Nessuna migrazione e nessuna nuova dipendenza.
- Gate atteso: `M9.1 HOTFIX 2 VALIDATION PASSED`.

## 0.9.1-M9.1-HF1 — Candidate

- Basata esclusivamente sulla candidate M9.1 derivata dalla baseline validata M8.
- Corretti i tre errori PHPStan in `BackupManager` e `FileLockManager` senza ridurre il livello di analisi.
- I filtri opzionali `project`, `activity` e `user` del report Ore accettano correttamente valori vuoti.
- `verify-backup.ps1` può verificare automaticamente l’ultimo backup, supporta pattern e mostra gli archivi disponibili quando il percorso richiesto non esiste.
- Aggiunto in Economia il report soci degli importi dovuti per cliente, con numero commesse, preventivato, incassato, residuo e preventivi mancanti.
- Nessuna migrazione e nessuna nuova dipendenza.
- Gate atteso: `M9.1 HOTFIX 1 VALIDATION PASSED`.

## 0.9.1-M9.1 — Candidate

- Basata esclusivamente sulla baseline validata M8.
- Aggiunto backup coordinato del database SQLite e degli allegati protetti.
- Snapshot SQLite coerente tramite `VACUUM INTO`.
- Aggiunto manifest `studio-commesse-backup-v1` con versione applicativa, migrazioni, dimensioni e SHA-256.
- Verifica di integrità SQLite, chiavi esterne, migrazioni, record allegati e inventario filesystem.
- Le mutazioni documentali partecipano a un lock condiviso; il backup acquisisce il lock esclusivo.
- Aggiunta modalità manutenzione HTTP 503 e lock delle richieste durante il ripristino.
- Il ripristino crea automaticamente un backup dello stato sostituito e tenta il rollback in caso di errore.
- Aggiunti gli script PowerShell `backup.ps1`, `verify-backup.ps1` e `restore-backup.ps1`, con estrazione ZIP protetta anche da nomi speciali Windows e flussi NTFS alternativi.
- Aggiunti smoke test del backup nel gate, test di backup/ripristino reale e contratto M9.1.
- Nessuna nuova migrazione e nessuna nuova dipendenza.
- Gate atteso: `M9.1 VALIDATION PASSED`.

## 0.8.0-M8 — Validata

- Validata dall’utente con esito `M8 VALIDATION PASSED`; diventa la baseline autoritativa per M9.1.
- Basata esclusivamente sulla baseline validata M7 Hotfix 1.
- In economia commessa i soci vedono il quadro completo; i collaboratori ricevono soltanto le proprie spese e il relativo totale.
- I collaboratori possono creare e gestire esclusivamente le proprie spese; incassi e dati finanziari completi restano riservati ai soci.
- Aggiunta l’area soci `Report mensile` con filtro per mese e commessa.
- Aggiunti indicatori mensili su ore, fatturabilità, persone, commesse movimentate, spese, incassi, documenti e azioni.
- Aggiunto riepilogo per commessa con stato corrente, avanzamento, residuo, scadenze e movimenti del periodo.
- Aggiunti dettaglio completo delle registrazioni ore, totali per azione e cronologia audit.
- Aggiunta esportazione CSV UTF-8 del dettaglio ore.
- Nessuna nuova migrazione e nessuna nuova dipendenza.
- Gate superato: `M8 VALIDATION PASSED`.

## 0.7.0-M7-HF1 — Validata

- Validata dall’utente con esito `M7 HOTFIX 1 VALIDATION PASSED`; diventa la baseline autoritativa per M8.
- Basata esclusivamente sulla candidate M7 derivata dalla baseline validata M6.3 Hotfix 1.
- Corretti i quattro problemi della suite `AttachmentManagementTest`.
- I PDF temporanei dei test mantengono il nome originale richiesto e usano una cartella casuale separata, evitando prefissi indesiderati nel basename.
- Le ricerche degli allegati appena caricati tornano coerenti con `originalName`.
- Il test dell’upload forzato su commessa archiviata ottiene il token CSRF da una sessione browser reale prima dell’archiviazione, senza accedere al token manager fuori da una richiesta.
- Aggiunta pulizia deterministica delle cartelle temporanee create dai test.
- Nessuna modifica funzionale, nessuna migrazione e nessuna variazione ai permessi o allo storage.
- Gate superato: `M7 HOTFIX 1 VALIDATION PASSED`.


## 0.7.0-M7 — Candidate

- Basata esclusivamente sulla baseline validata M6.3 Hotfix 1.
- Aggiunta l’entità `Attachment` e la migrazione M7 per documenti di commessa e attività.
- Aggiunte area globale `Documenti`, archivio della commessa e collegamento dalla modifica attività.
- File memorizzati fuori da `public` con chiave casuale, MIME rilevato, dimensione e SHA-256.
- Limite 10 MiB e allowlist PDF, immagini, TXT, CSV, DOCX e XLSX con verifica della firma.
- Rifiutati file vuoti, incongruenti, eseguibili e firma EICAR di test.
- Download protetto tramite controller con `nosniff` e cache disabilitata.
- Modifica ed eliminazione concentrate nella pagina documento e autorizzate tramite voter.
- Audit di caricamento, modifica, download ed eliminazione.
- Setup e validazione controllano `fileinfo` e scrivibilità dello spazio documentale.
- Aggiunti test di entità, storage, controller, permessi e contratti.
- Gate atteso: `M7 VALIDATION PASSED`.


## 0.6.3-M6.3-HF1 — Validata

- Validata dall’utente con esito `M6.3 HOTFIX 1 VALIDATION PASSED`; diventa la baseline autoritativa per M7.
- Corretto il rendering dei comandi di archiviazione nelle pagine di modifica di clienti e commesse.
- Il comando commessa è mostrato esclusivamente ai soci; gli elementi già archiviati restano non modificabili tramite i controlli esistenti.
- Reso robusto il test del filtro `assignee=me`, verificando il valore effettivamente inviato dal form anziché la serializzazione HTML dell'attributo `selected`.
- Nessuna migrazione e nessuna modifica alla logica di dominio.
- Gate superato: `M6.3 HOTFIX 1 VALIDATION PASSED`.

## 0.6.3-M6.3 — Candidate

- Basata esclusivamente sulla baseline validata M6.2 Hotfix 4.
- Tutte le griglie applicative diventano monocolonna sotto il breakpoint `lg`; le colonne affiancate iniziano soltanto sugli schermi grandi.
- Corretto `/attivita?assignee=me`: il valore simbolico `me` non viene più convertito a intero.
- Spostata su una riga intera la spiegazione del filtro assegnatario nella pagina Attività.
- Il nome del cliente è ora il collegamento principale; rimossa la colonna ridondante `Apri`.
- Integrati localmente DataTables 2.3.8, integrazione Bootstrap 5 e Responsive 3.0.8 in tutte le tabelle.
- Abilitati ordinamento e ricerca rapida; date, durate e importi usano valori macchina per un ordinamento corretto. La paginazione server-side del report Ore resta autoritativa e non viene duplicata nel browser.
- Eliminata la colonna generica `Azioni`: il collegamento principale è su nome, codice, attività o descrizione.
- Spostate archiviazione di clienti/commesse ed eliminazione di spese/incassi nelle rispettive schermate di modifica.
- Aggiunti test funzionali e contrattuali per filtro `me`, link cliente, copertura DataTables, breakpoint e coerenza delle azioni.
- Nessuna nuova migrazione.
- Gate atteso: `M6.3 VALIDATION PASSED`.

## 0.6.2-M6.2-HF4 — Validata

- Candidate cumulativa M6.1/M6.2 validata dall’utente con esito `M6.2 HOTFIX 4 VALIDATION PASSED`.
- Area Ore, controllo avanzato, valutazione giornaliera, form responsive e correzioni PHPStan/test diventano baseline autoritativa.
- Corretti i test della sidebar, il contratto della soglia numerica e la portabilità CRLF/LF.
- Nessuna migrazione aggiuntiva.

## 0.6.2-M6.2-HF3 — Candidate cumulativa

- M6.1, M6.2, Hotfix 1 e Hotfix 2 restano incluse nella candidate e non sono ancora dichiarate validate.
- Corretti i tre errori PHPStan segnalati nei controller dell'area Controllo e della valutazione collaboratori.
- Mantenuto il narrowing esplicito di `Project` dopo l'eventuale azzeramento dovuto al filtro cliente.
- Semplificato il filtro dei parametri del report Ore eliminando il confronto impossibile tra interi e stringa vuota.
- Eliminato il controllo `is_scalar()` ridondante sui valori già tipizzati da `InputBag` come stringhe.
- Nessuna modifica funzionale, nessuna nuova migrazione e nessun indebolimento di `phpstan.neon`.
- Gate atteso: `M6.2 HOTFIX 3 VALIDATION PASSED`.

## 0.6.2-M6.2-HF2 — Candidate cumulativa

- M6.1, M6.2 e M6.2 Hotfix 1 restano incluse nella candidate e non sono ancora dichiarate validate.
- Ridisegnati tutti i form CRUD con griglia responsive: campi compatti in due colonne da `6/12` soltanto sugli schermi grandi e larghezza piena su schermi piccoli e medi.
- Mantenuti su riga intera descrizioni, note, indirizzi e altri campi estesi.
- Resi espliciti tutti i campi definiti dai sette Symfony Form Type, eliminando il rendering generico `form_widget(form)`.
- Corretto il form utente, che non renderizzava esplicitamente `Tariffa oraria standard` e poteva mostrarla automaticamente dopo il pulsante.
- Spostato `form_rest()` prima del footer e disabilitato il rendering residuo in `form_end()`, così token nascosti e futuri controlli non compaiono dopo il salvataggio.
- Resi a larghezza piena tutti i pulsanti principali di creazione e salvataggio.
- Aggiunto un test contrattuale che confronta campi configurati e campi renderizzati per ogni form, controlla breakpoint, campi estesi e posizione del pulsante.
- Nessuna nuova migrazione.
- Gate atteso: `M6.2 HOTFIX 2 VALIDATION PASSED`.

## 0.6.2-M6.2-HF1 — Candidate cumulativa

- M6.1 e M6.2 restano incluse nella candidate e non sono ancora dichiarate validate.
- Aggiunta la valutazione giornaliera della persona dalla tabella `Carico per persona`.
- Aggiunta la pagina soci `/controllo/collaboratori/{id}` con filtri per periodo, cliente, responsabile, commessa e fatturabilità.
- Mostrati per ogni giornata commessa, attività, descrizione del lavoro, intervallo, durata e totale giornaliero.
- Aggiunti totali complessivi, ore fatturabili, giornate lavorate, media giornaliera, registrazioni e commesse coinvolte.
- Mantenuta la stessa semantica del controllo: attribuzione alla data di inizio e timer aperti esclusi dai totali.
- Aggiunti test funzionali, repository, servizio e contratti di versione.
- Nessuna nuova migrazione.
- Gate atteso: `M6.2 HOTFIX 1 VALIDATION PASSED`.

## 0.6.2-M6.2 — Candidate cumulativa

- M6.1 resta inclusa nella candidate, pur non essendo stata validata separatamente.
- Aggiunta l’area soci `/controllo` con filtri persistenti e ordinamento.
- Introdotte chiusure operative, economiche e complessive derivate senza nuove colonne.
- Segnalate commesse ferme dopo 14 giorni senza avanzamenti.
- Segnalato il sovraccarico oltre 8 attività aperte o 40 ore residue.
- Aggiunti scostamenti ore, superamento preventivo, ritardi, timer incoerenti e preventivo mancante.
- Aggiunti riepiloghi per persona, cliente e mese nel periodo selezionato.
- Aggiunto il pannello `Controllo chiusura` nel dettaglio commessa, visibile soltanto ai soci.
- Implementate query SQLite aggregate senza N+1 e test di regressione su duplicazioni, permessi e persistenza filtri.
- Nessuna nuova migrazione.
- Gate atteso: `M6.2 VALIDATION PASSED`.

## 0.6.1-M6.1 — Candidate

- Aggiunta la nuova area globale `/ore`, visibile a soci e collaboratori.
- Introdotti filtri per commessa, attività, persona che ha lavorato, intervallo di date e fatturabilità.
- Aggiunti riepiloghi sull’intero risultato filtrato e paginazione server-side a 50 righe.
- Aggiunto il dettaglio consuntivato per persona in ogni attività del dettaglio commessa.
- Rinominato il filtro della pagina attività in `Assegnatario`, mantenendo distinta la responsabilità dal lavoro effettivo.
- Aggiunti collegamenti contestuali dal dettaglio commessa e dalla pagina ore dell’attività.
- Mantenuti tariffa e costo fuori dal report globale per rispettare i permessi economici per commessa.
- Aggiunti DTO di query, aggregazioni bulk e test funzionali/repository per filtri, paginazione e contributori multipli.
- Nessuna nuova migrazione.
- Gate atteso: `M6.1 VALIDATION PASSED`.

## 0.5.4-M5-HF4 — Validata

- Corretto `load-fixtures.ps1`: ambiente `dev/test` esplicito, nessun uso di `--force` e ripristino delle variabili PowerShell.
- Corretto `start-server.ps1` affinché ripristini `APP_ENV` e `APP_DEBUG` al termine.
- Portato il profilo standard a 30 commesse, 200 attività e 600 registrazioni ore.
- Distribuite le attività tra soci e collaboratori e le ore tra assegnatario e altri contributori.
- Introdotta la distribuzione deterministica 0/1/2/4/8 registrazioni per attività.
- Aggiunta la riconciliazione delle registrazioni fixture correnti e legacy, preservando i record manuali.
- Aggiunto un test d’integrazione completo su conteggi, idempotenza, contributori, sovrapposizioni e conservazione dati.
- Validazione locale confermata dall’utente con esito `M5 HOTFIX 4 VALIDATION PASSED`.

## 0.5.3-M5-HF3 — Validata

- Resi espliciti nel form commessa i campi `Preventivo` e `Tariffa oraria della commessa`, prima del pulsante di salvataggio.
- Spostato `form_rest()` all'interno del corpo del form per impedire che nuovi campi compaiano dopo il footer.
- Sostituite le somme ore N+1 con aggregazioni SQLite bulk per elenco attività e dettaglio commessa.
- Sostituite le tre query per ogni commessa economica con tre query aggregate complessive.
- Disabilitati in sviluppo logging/profiling DBAL e raccolta backtrace query non utilizzati.
- Estesi `start-server.ps1` con modalità `dev`/`fast` e aggiunto `diagnose-performance.ps1`.
- Aggiunta la guida `docs/PERFORMANCE.md` e test contrattuali di regressione.
- Validazione locale confermata dall’utente con esito `M5 HOTFIX 3 VALIDATION PASSED`.

## 0.5.2-M5-HF2 — Validata

- Ripristinati i comandi `Archivia` e `Ripristina` nella testata del dettaglio commessa.
- Mantenuti controllo di ruolo socio, token CSRF e conferma prima dell'archiviazione.
- Rafforzato il test funzionale con una diagnostica esplicita quando il comando non è presente.
- Validazione locale confermata dall'utente con esito `M5 HOTFIX 2 VALIDATION PASSED`.

## 0.5.1-M5-HF1 — Candidate

- Rimossi i fallback ternari ridondanti da `ExpenseType` e `PaymentType`: per costanti non vuote e di uguale lunghezza `array_combine()` è già determinato come array da PHPStan.
- Risolti gli errori PHPStan `ternary.alwaysTrue` senza indebolire `phpstan.neon`.
- Aggiunto un test contrattuale che impedisce di reintrodurre `?: []` nei due cataloghi di scelta.
- Riallineati versione, gate PowerShell, package lock e documentazione a `M5 HOTFIX 1 VALIDATION PASSED`.
- Nessuna modifica a schema, migrazioni o comportamento economico M5.

## 0.5.0-M5 — Candidate

### Economia

- preventivo a corpo e tariffa oraria della commessa;
- tariffa standard del collaboratore e tariffa specifica dell’attività;
- risoluzione tariffaria `attività → commessa → collaboratore → standard applicazione`;
- tariffa congelata sulla registrazione; costo calcolato e storicizzato alla chiusura;
- migrazione conservativa per i record M4 già presenti;
- spese con categoria, attività opzionale e indicazione rimborsabile;
- incassi con data, descrizione, metodo, riferimento e note;
- modifica ed eliminazione con CSRF e audit;
- riepilogo economico per commessa e quadro complessivo per i soci.

### Interfaccia

- dashboard: card `Utenti attivi` da 3/12 al posto della milestone;
- dettaglio commessa in colonne 6/12 + 6/12;
- `Riferimenti` sotto `Stato della commessa`;
- colonna `Attività` a tutta altezza;
- totale consuntivato mostrato su ogni attività;
- tabelle con durate sempre nel formato `ore:minuti`;
- importi formattati in euro secondo il formato italiano.

### Fixtures e qualità

- dataset idempotente: 8 utenti, 10 clienti, 25 commesse, 125 attività, 1.000 registrazioni ore, 200 spese e 100 incassi;
- test per risoluzione tariffe, snapshot dei costi, riepiloghi economici e formatter;
- aggiornati migrazioni, gate PowerShell, roadmap, handoff e checklist.

## 0.4.1-M4-HF1 — Candidate
- Dichiarato `TimeEntryType` come `AbstractType<TimeEntry>` per PHPStan.
- Aggiunta l’icona Tabler alla voce di menu `Attività`.
- Aggiornati versione, gate di validazione, test contrattuali e documentazione.

# M3 Hotfix 1 – Allineamento contratti di versione e gate

- corretti i due test contrattuali rimasti ancorati alla versione M2;
- riallineati `app.version`, `package.json` e `package-lock.json` a `0.3.1-M3-HF1`;
- gate rinominato `M3 HOTFIX 1 VALIDATION PASSED`;
- aggiunta regressione che vieta i precedenti messaggi di gate M2 e M1 nel validatore corrente;
- nessuna modifica al dominio, alle migrazioni o alle fixtures M3.

# M3 – Attività, assegnazioni e fixtures

- attività con assegnatario, stato, priorità, avanzamento, stime e scadenze;
- viste per commessa e collaboratore;
- fixtures demo idempotenti tramite comando dedicato;
- migrazione M3, test e documentazione.

# Changelog

## 0.2.1-M2-HF1 candidate – 2026-07-27

- rimosso `debug:config doctrine` da setup e validazione: su Symfony 8.1 poteva fallire con `Can not change config enabled because it has already been read`, pur con configurazione e schema corretti;
- mantenuti come controlli autoritativi il contratto Doctrine, il lint YAML, il bootstrap del kernel, le migrazioni e `doctrine:schema:validate`;
- resa guidata la creazione del primo socio: su un database senza soci attivi il setup chiede username, nome visualizzato e password nascosta;
- nessuna credenziale predefinita viene inclusa nel progetto;
- gli aggiornamenti successivi saltano automaticamente la procedura quando esiste già un socio attivo;
- aggiunta l’opzione PowerShell `-SkipPartnerBootstrap` per installazioni automatizzate;
- aggiunti test funzionali del bootstrap e contratti di regressione sugli script;
- gate rinominato `M2 HOTFIX 1 VALIDATION PASSED`.

## 0.2.0-M2 candidate – 2026-07-27

### Aggiunto

- entità `Client`, `Project` e `ProjectCodeSequence`;
- migrazione M2 completa;
- codice annuale progressivo transazionale;
- stati e priorità della commessa senza campi duplicati;
- responsabile unico;
- schermate classiche Symfony/Twig per clienti e commesse;
- ricerca e filtri;
- dashboard operativa;
- archiviazione e ripristino controllati, con record archiviati in sola lettura;
- voter per modifica e nota riservata;
- audit di creazione, modifica, archiviazione e ripristino;
- protezione dalla disattivazione di responsabili con commesse non archiviate;
- test unitari, funzionali, di sicurezza e contrattuali M2;
- conservazione della data di completamento quando una commessa già completata viene nuovamente salvata senza riaprirla.

### Baseline

M2 parte esclusivamente da `StudioCommesse_M1_Hotfix8.zip`, validata localmente dall’utente con 33 test, 144 asserzioni, PHPStan pulito e schema sincronizzato.

## 0.1.0-M1-HF7 candidate – 2026-07-27

### Corretto

- lasciata `doctrine_migration_versions` visibile ai comandi delle migrazioni, affidando al filtro dinamico di Doctrine Migrations Bundle l'esclusione dai soli confronti ORM;
- allineato il vincolo univoco `uniq_app_user_username` tra mapping ad attributi e migrazione;
- rimosso `unique: true` dalla colonna `username` in favore di `#[ORM\UniqueConstraint]`, forma raccomandata da ORM 3;
- il setup verifica ora che migrazioni e mapping siano aggiornati prima di dichiarare l’installazione completata;
- la validazione stampa il SQL di drift quando `doctrine:schema:validate` fallisce;
- aggiunti contratti di regressione per filtro schema, vincolo univoco e diagnostica del gate.

## 0.1.0-M1-HF6 candidate – 2026-07-27

### Corretto

- spostato l’import di `PasswordUpgraderInterface` da `Symfony\Bridge\Doctrine\Security\User` al namespace corrente `Symfony\Component\Security\Core\User`;
- eliminato il fatal error durante il caricamento di `UserRepository` con Symfony 8.1;
- tipizzato esplicitamente `upgradePassword()` con `PasswordAuthenticatedUserInterface`;
- aggiunto `scripts/symfony-api-contract.php`, eseguito prima del bootstrap del kernel, che verifica tramite reflection i contratti di `UserRepository` e `ActiveUserChecker`;
- aggiunti test di regressione sul namespace, sulle firme delle interfacce di sicurezza e sull’ordine dei preflight negli script PowerShell;
- aggiornati versione, dashboard, README, roadmap, checklist e handoff.

## 0.1.0-M1-HF5 candidate – 2026-07-27

### Corretto

- rimosse da `doctrine.orm` le opzioni legacy `auto_generate_proxy_classes` e, nell’ambiente di produzione, `proxy_dir`, non più accettate da DoctrineBundle 3.3 con Doctrine ORM 3.6;
- mantenuta una configurazione ORM minimale basata su mapping ad attributi, strategia di naming e cache Symfony in produzione;
- aggiunto `scripts/doctrine-config-contract.php`, eseguito prima del bootstrap Symfony, che vieta le opzioni Doctrine DBAL/ORM obsolete già incontrate;
- aggiunto `debug:config doctrine --quiet` ai flussi di setup e validazione per verificare la configurazione effettiva della versione installata;
- estesi i test contrattuali della configurazione Doctrine e dei comandi di installazione;
- riallineato anche il numero di versione npm, rimasto erroneamente fermo a Hotfix 2.

## 0.1.0-M1-HF4 candidate – 2026-07-27

### Corretto

- rimossa l’opzione Doctrine DBAL `use_savepoints`, non più riconosciuta da DoctrineBundle 3.3 e priva di effetto con DBAL 4;
- eliminato l’errore `Unrecognized option "use_savepoints"` durante il caricamento del container Symfony;
- aggiunto un test contrattuale che analizza `doctrine.yaml` e impedisce la reintroduzione dell’opzione rimossa;
- eseguite le installazioni Composer degli script PowerShell con `--no-scripts`;
- aggiunti al setup lint YAML, lint Twig e `cache:clear` espliciti prima delle migrazioni;
- aggiunto un test di regressione sul nuovo flusso d’installazione diagnostico;
- aggiornati versione, dashboard, documentazione, checklist e messaggio finale del gate.

## 0.1.0-M1-HF3 candidate – 2026-07-27

### Corretto

- adeguata `ActiveUserChecker::checkPostAuth()` alla firma richiesta da Symfony 8.1, aggiungendo il parametro opzionale `?TokenInterface $token = null`;
- eliminato il fatal error durante `composer update` e `cache:clear`;
- aggiunti test contrattuali e comportamentali sul checker degli utenti attivi;
- aggiornati versione, dashboard, documentazione, checklist e messaggio finale del gate.

## 0.1.0-M1-HF2 candidate – 2026-07-27

### Corretto

- corretta l’interpolazione PowerShell ambigua `"$exitCode:"`, che impediva il parsing di `setup.ps1` e `validate.ps1`;
- costruiti i messaggi di errore dei comandi nativi tramite operatore `-f`, compatibile con Windows PowerShell 5.1 e PowerShell 7;
- aggiunto un controllo preventivo che analizza la sintassi di tutti gli script `.ps1` prima di proseguire;
- aggiunti test di regressione sul contratto degli script PowerShell;
- aggiornati versione, dashboard, documentazione, checklist e messaggio finale del gate.

## 0.1.0-M1-HF1 candidate – 2026-07-27

### Corretto

- aggiornato `symfony/monolog-bundle` dalla serie 3.x incompatibile con Symfony 8 alla serie 4.x compatibile;
- eliminata l'assunzione errata che `@tabler/core` contenga un file `LICENSE` nella radice del pacchetto npm;
- aggiunto `THIRD_PARTY_NOTICES.md` con attribuzione e licenza MIT di Tabler;
- aggiunto `NOTICE.txt` agli asset Tabler copiati localmente;
- resi fail-fast tutti i comandi nativi di `setup.ps1` e `validate.ps1`;
- impedita la stampa di un falso messaggio di installazione completata dopo errori Composer, npm o Symfony;
- distinta l'installazione iniziale (`composer update`, `npm install`) da quelle riproducibili successive (`composer install`, `npm ci`);
- aggiunte verifiche di PHP 8.4+, Node.js 20+, estensioni e requisiti Composer;
- resa deterministica la ricostruzione del database di test nel gate PowerShell;
- aggiunti audit Composer e npm al gate di validazione.

## 0.1.0-M1 candidate – 2026-07-27

### Aggiunto

- fondazione Symfony 8.1 / PHP 8.4;
- persistenza SQLite con Doctrine e migrazione iniziale;
- Tabler 1.4.0 installato localmente tramite npm;
- layout Twig classico senza EasyAdmin;
- autenticazione, logout CSRF e login throttling;
- ruoli Socio e Collaboratore;
- gestione utenti per i soci;
- blocco account disattivati;
- protezione dell'ultimo socio attivo e dell'account autenticato;
- audit di accessi e modifiche utenti;
- test unitari e funzionali;
- script PowerShell di setup, avvio e validazione;
- documentazione di progetto e handoff.

## 0.4.1-M4-HF1 — Candidate
- Consuntivazione manuale delle ore per attività.
- Timer personale con un solo intervallo attivo.
- Controllo degli intervalli sovrapposti e modifica limitata al proprietario o ai soci.
- Fixtures demo estese con cinque registrazioni temporali.
