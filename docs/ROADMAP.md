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
- archivio trasversale e archivio della singola commessa;
- file fuori da `public`, impronta SHA-256 e download autorizzato;
- allowlist, controllo MIME/firma e permessi tramite voter;
- audit documentale.

## M8 – Report mensile e visibilità economica — VALIDATA

- visibilità economica differenziata per ruolo;
- collaboratori limitati alle proprie spese;
- report mensile soci con ore, commesse, spese, incassi, documenti e audit;
- esportazione CSV;
- gate `M8 VALIDATION PASSED`.

## M9.1 – Backup e ripristino coordinato — CANDIDATE CON HOTFIX 2

- snapshot SQLite coerente tramite `VACUUM INTO`;
- database e allegati salvati nello stesso backup versionato;
- manifest con hash SHA-256, dimensioni e migrazioni;
- verifica SQLite, chiavi esterne e inventario documentale;
- ZIP PowerShell con estrazione protetta da path traversal, symlink e nomi speciali Windows;
- modalità manutenzione durante il ripristino;
- sostituzione coordinata e rollback in caso di errore;
- backup automatico dello stato precedente al ripristino;
- nessuna nuova migrazione o dipendenza.
- Hotfix 1: PHPStan, filtri Ore vuoti, verifica backup più usabile e importi dovuti per cliente.
- Hotfix 2: corretto il contratto PHPUnit sulle versioni di migrazione, che interpolava accidentalmente `$databasePath`.

## M9.2 – Hardening e collaudo di rilascio — PROSSIMA

- revisione finale autorizzazioni, error handling e logging;
- test end-to-end dei percorsi principali;
- verifica installazione pulita, aggiornamento e ripristino;
- manuale utente completo.

## M9.3 – Release 1.0 — SUCCESSIVA

- chiusura difetti emersi nel collaudo;
- documentazione e checklist di esercizio;
- pacchetto stabile 1.0.
