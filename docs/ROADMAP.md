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

### M9.2-A Hotfix 1 – Normalizzazione baseline e packaging — CANDIDATE CORRENTE

- riallineamento di versione, gate, changelog, roadmap, handoff e checklist;
- rimozione delle configurazioni locali dal pacchetto;
- comando ripetibile per generare lo ZIP di distribuzione;
- verifica automatica dell’inventario del pacchetto;
- nessuna modifica funzionale, migrazione o dipendenza.
- correzione del parser PowerShell di `package-release.ps1`;

### M9.2-B – Autorizzazioni e riservatezza — PROSSIMA

- matrice completa delle rotte per ruolo e proprietà del dato;
- verifica accessi diretti, POST costruiti manualmente e assenza di dati economici per i collaboratori.

### M9.2-C – Robustezza ed error handling

- transazioni, concorrenza SQLite, errori 403/404/500/503 e messaggi uniformi.

### M9.2-D/E – Audit operativo e flussi end-to-end

- logging, audit e scenari completi di commessa, collaboratore, chiusura e ripristino.

### M9.2-F/G/H – Installazione, prestazioni, accessibilità e manuali

- installazione/aggiornamento puliti, benchmark, audit responsive e documentazione utente/amministrativa.

## M9.3 – Release Candidate 1.0

Blocco funzionale, chiusura dei difetti e collaudo completo della RC.

## M9.4 – Release stabile 1.0.0

Pacchetto definitivo, documentazione finale e gate di esercizio.

## M9.2-A Hotfix 1

Corregge esclusivamente la sintassi PowerShell del ciclo ricorsivo in `package-release.ps1`; nessuna funzione applicativa è stata modificata. Gate: `M9.2-A HOTFIX 1 VALIDATION PASSED`.
