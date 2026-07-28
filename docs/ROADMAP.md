# Roadmap

## M1 – Fondazioni e sicurezza — VALIDATA

Symfony 8.1, PHP 8.4, SQLite, Tabler locale, autenticazione, ruoli, utenti, audit e gate PowerShell.

## M2 – Clienti e commesse — VALIDATA

Clienti, commesse, codice annuale, responsabile unico, stati, priorità, archiviazione, ricerca e permessi.

## M3 – Attività e fixtures — VALIDATA

Attività, assegnatari, avanzamento, stime, scadenze, viste operative e fixtures idempotenti.

## M4 – Consuntivazione ore e timer — VALIDATA

Registrazione manuale, timer personale, sovrapposizioni vietate, durata calcolata e permessi sulle correzioni.

## M5 – Economia della commessa — VALIDATA CON HOTFIX 4

Preventivi, tariffe, costi storici, spese, incassi, margini, dashboard economica e fixtures 30/200/600 multi-persona.

## M6 – Rendicontazione, controllo e coerenza UI — VALIDATA CON M6.3 HOTFIX 1

- rendicontazione ore trasversale e dettaglio per persona;
- controllo chiusura, criticità, carichi e valutazione giornaliera;
- form responsive e rendering esplicito;
- una colonna sotto `lg`;
- DataTables locali e convenzioni uniformi di navigazione e azioni.

## M7 – Allegati e documenti — VALIDATA CON HOTFIX 1

- allegati protetti su commessa e attività;
- file fuori da `public`, impronta SHA-256 e download autorizzato;
- allowlist, controllo MIME/firma e permessi tramite voter;
- audit documentale.

## M8 – Report mensile e visibilità economica — VALIDATA

- visibilità economica differenziata per ruolo;
- report mensile soci e CSV;
- importi dovuti per cliente riservati ai soci.

## M9.1 – Backup e ripristino coordinato — VALIDATA CON HOTFIX 3 CORRETTA

- snapshot SQLite coerente tramite `VACUUM INTO`;
- database e allegati nello stesso backup versionato;
- manifest con hash SHA-256, dimensioni e migrazioni;
- verifica SQLite, chiavi esterne e inventario documentale;
- estrazione ZIP protetta;
- modalità manutenzione e backup automatico pre-ripristino;
- filtri Ore vuoti corretti e riepilogo dovuti per cliente;
- nota Attività a larghezza piena e colonna `Dovuto` riservata ai soci;
- baseline autoritativa: `StudioCommesse_M9.1_Hotfix3_Corretto.zip`;
- gate confermato: `M9.1 HOTFIX 2 VALIDATION PASSED`, 171 test e 1.631 asserzioni.

## M9.2 – Hardening e collaudo di rilascio

### M9.2-A Hotfix 2 – Completezza packaging e filtri — VALIDATA CUMULATIVAMENTE CON M9.2-C HF3

- riallineamento di versione, gate, changelog, roadmap, handoff e checklist;
- rimozione delle configurazioni locali dal pacchetto;
- comando ripetibile per generare lo ZIP di distribuzione;
- verifica automatica dell’inventario del pacchetto;
- nessuna modifica funzionale, migrazione o dipendenza.
- correzione del parser PowerShell di `package-release.ps1`;

### M9.2-B – Autorizzazioni e riservatezza — VALIDATA CUMULATIVAMENTE CON M9.2-C HF3

- matrice macchina e documentale completa delle 48 rotte;
- verifica accessi diretti, proprietà del dato e aree riservate ai Soci;
- blocco delle scritture su commesse archiviate per attività, ore, incassi e documenti;
- test contro POST costruiti manualmente e assegnazione di campi finanziari/amministrativi;
- verifica che note private, tariffe, costi, incassi e margini non raggiungano il markup dei Collaboratori.

### M9.2-C Hotfix 3 – PHPStan e completezza enum — VALIDATA

- correzione dei tre errori PHPStan emersi dopo l’abilitazione di `fileinfo`;
- narrowing esplicito nel subscriber degli errori database;
- lettura del marker manutenzione dichiarata impura per l’analisi statica;
- gestione esaustiva di `TimeEntryUpdated` nel report mensile;
- include il preflight PHP/fileinfo della Hotfix 1;
- validata con 203 test, 2.125 asserzioni e PHPStan senza errori.

### M9.2-C Hotfix 4 Fix 2 – Dashboard e viste operative — VALIDATA

- contratto M6.3 corretto per verificare la semantica reale del filtro e non il codice letterale del test;
- README sintetico, senza cronologia milestone, con requisiti, installazione e script;
- seconda riga di card operative con clienti, attività e totali ore;
- tabelle recenti di commesse, attività e ore a larghezza piena;
- attività personali caricate subito e filtro assegnatario automatico;
- priorità commesse rappresentata con icone accessibili;
- descrizione inclusa nelle ore recenti e breakpoint responsive solo da `lg`;
- test del filtro riallineati all’assenza del pulsante “Mostra”;
- nessuna migrazione o nuova dipendenza.

### M9.2-C – Robustezza ed error handling — VALIDATA CON HOTFIX 3

- mutazioni e audit persistiti nella stessa transazione;
- lock applicativi per timer e registrazioni ore concorrenti;
- compensazione filesystem/database per documenti;
- gestione uniforme di conflitti, database occupato e manutenzione;
- identificativo richiesta nelle risposte e nelle pagine di errore.

### M9.2-D – Audit operativo e logging — VALIDATA

- area Soci `/audit` con filtri, paginazione server-side e CSV;
- correlazione request ID e logging JSON separato;
- matrice autorizzazioni completa su 48 rotte.

Gate superato: `M9.2-D VALIDATION PASSED`.

### M9.2-E – Flussi end-to-end completi — VALIDATA

- commessa conclusa, pagata e archiviata;
- flusso Collaboratore con attività e ore;
- chiusura incoerente e ripristino ordinato;
- backup-ripristino del grafo aziendale e degli allegati;
- nessuna migrazione o nuova dipendenza.

Gate superato: `M9.2-E VALIDATION PASSED`.

### M9.2-E.1 – Riepilogo mensile costi per utente — VALIDATA

- ore concluse aggregate per utente, inclusi account disattivati;
- tariffa standard attuale distinta dagli snapshot storici;
- costo standard teorico, costo storico effettivo e scostamento;
- CSV riepilogativo separato e filtri mese/commessa coerenti.

Gate superato: `M9.2-E.1 VALIDATION PASSED`.

### M9.2-E.2 – Dashboard ore del mese corrente — VALIDATA

- etichette complete “Commesse in attesa” e “Commesse in ritardo”;
- valore e titolo delle ore effettuate sulla stessa riga;
- totale limitato alle registrazioni concluse iniziate nel mese corrente;
- card e aggregato “Ore pianificate” rimossi;
- nessuna migrazione, nuova rotta o dipendenza.

Gate superato: `M9.2-E.2 VALIDATION PASSED`.

### M9.2-F – Installazione, aggiornamento e distribuzione puliti — VALIDATA

- preflight condiviso per installazione, aggiornamento e pacchetto estratto;
- aggiornamento da cartella separata con backup dati e codice verificati;
- manutenzione coordinata, rimozione dei file obsoleti e verifica post-migrazione;
- rollback automatico di codice, database e allegati;
- smoke test dello ZIP estratto e procedura operativa documentata.

Gate: `M9.2-F VALIDATION PASSED`.

### M9.2-G – Prestazioni e capacità — VALIDATA

- dashboard consolidata in una sola query per i dieci indicatori numerici;
- indici mirati e benchmark deterministici 30/200/600;
- copertura di percorsi applicativi e backup-ripristino;
- gate superato: `M9.2-G VALIDATION PASSED`, 235 test e PHPStan senza errori.

### M9.2-H – Accessibilità, responsive, manuali e sicurezza login — CANDIDATE CORRENTE

- navigazione da tastiera, landmark, focus visibile, scope delle tabelle e responsive;
- manuali utente, amministratore, sicurezza e accessibilità;
- throttling login: 5 fallimenti per utenza/IP in un’ora e limite globale IP;
- audit dei blocchi, pseudonimizzazione dei fallimenti e log minimizzati;
- intestazioni HTTP difensive e session cookie SameSite Strict;
- attesi 244 test e gate `M9.2-H VALIDATION PASSED`.

## M9.3 – Release Candidate 1.0

Blocco funzionale, chiusura dei difetti e collaudo completo della RC.

## M9.4 – Release stabile 1.0.0

Pacchetto definitivo, documentazione finale e gate di esercizio.

## M9.2-A Hotfix 1

Corregge la sintassi PowerShell dei blocchi `if`, spostando gli operatori `-or` a fine riga; il ciclo con `Get-ChildItem` resta parentetizzato solo per chiarezza. Validata localmente con 174 test e 1.651 asserzioni.

## M9.2-A Hotfix 2

Chiude le osservazioni dell’audit esterno: verificatore ZIP indipendente, inventario completo e non vuoto, matrice permessi economica riallineata e filtri Controllo resilienti ai parametri estranei. È inclusa cumulativamente nella candidate M9.2-C e mantiene il proprio gate storico `M9.2-A HOTFIX 2 VALIDATION PASSED`.

### M9.2-F.1 – Apache e self-staging update — VALIDATA

- dipendenza `symfony/apache-pack` e recipe `.htaccess`;
- documentazione Apache;
- staging temporaneo automatico quando `update.ps1` viene avviato dalla destinazione;
- update no-op sicuro quando non esistono file distribuibili obsoleti.

Gate superato: `M9.2-F.1 VALIDATION PASSED`, 230 test e PHPStan senza errori.
