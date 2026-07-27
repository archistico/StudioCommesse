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
- DataTables locali e convenzioni uniformi di navigazione e azioni;
- gate `M6.3 HOTFIX 1 VALIDATION PASSED`.

## M7 – Allegati e documenti — CANDIDATE

- allegati protetti su commessa e attività;
- archivio trasversale e archivio della singola commessa;
- classificazione documentale e descrizione;
- file fuori da `public`, chiave casuale e impronta SHA-256;
- limite 10 MiB, allowlist, controllo MIME e firma del contenuto;
- download autenticato e autorizzato;
- gestione riservata a uploader, responsabili, assegnatari/creatori e soci;
- commesse archiviate in sola lettura documentale;
- audit di caricamento, modifica, download ed eliminazione;
- nessuna dipendenza esterna e una nuova migrazione.

## M8 – Report, backup e rilascio 1.0 — PROSSIMA

- esportazioni e report;
- backup e ripristino coordinato di SQLite e documenti;
- hardening, test end-to-end e manuale utente;
- release 1.0.
