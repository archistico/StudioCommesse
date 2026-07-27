# Sicurezza

## Fondazioni M1

- password hash gestito da Symfony;
- CSRF su login, logout e form;
- throttling accessi;
- account disattivati bloccati;
- gestione utenti riservata ai soci;
- audit degli accessi e delle modifiche;
- database fuori da `public/`;
- asset Tabler locali;
- gate con audit dipendenze.
- nessuna credenziale predefinita: il primo socio viene creato in modo interattivo con password nascosta;
- il bootstrap non crea account aggiuntivi quando esiste già un socio attivo.

## M2

- clienti e commesse sono leggibili dagli utenti autenticati;
- creazione e archiviazione sono riservate ai soci;
- `ProjectVoter` protegge modifica e nota riservata;
- il responsabile non riceve nel form i campi cliente e responsabile;
- archiviazione e ripristino richiedono POST e token CSRF;
- nessuna cancellazione fisica;
- un responsabile con commesse non archiviate non può essere disattivato;
- le query non espongono endpoint economici, non ancora presenti.

## Distribuzione interna

- document root esclusivamente su `public/`;
- HTTPS anche in LAN quando possibile;
- permessi filesystem limitati su `.env.local`, database e log;
- backup consistente del file SQLite;
- aggiornamenti periodici di PHP, Symfony e dipendenze;
- nessuna pubblicazione di `.env*`, `var/`, sorgenti o database.


## M7 documenti

- i file sono conservati in `var/storage/attachments`, fuori da `public`;
- i nomi fisici sono casuali e i nomi originali sono soltanto metadati;
- ogni file registra MIME rilevato, dimensione e SHA-256;
- limite massimo 10 MiB e allowlist di formati;
- estensione, MIME e firma iniziale devono essere coerenti;
- sono rifiutati file eseguibili e firma EICAR di test;
- il download richiede autenticazione e voter, usa `Content-Disposition: attachment`, `nosniff` e `no-store`;
- modifica ed eliminazione richiedono voter e CSRF;
- caricamento, modifica, download ed eliminazione sono sottoposti ad audit;
- `fileinfo` e scrivibilità dello storage sono controllati da setup e validazione;
- database e spazio documentale devono essere salvati e ripristinati insieme.

Il controllo integrato non sostituisce un antivirus di sistema. In ambienti esposti a file esterni è raccomandata una scansione antivirus della directory documentale.


## M9.1 backup e ripristino

- database e allegati vengono salvati come un’unica unità verificabile;
- il database viene fotografato con `VACUUM INTO`, senza copia a caldo del solo file SQLite;
- caricamenti ed eliminazioni di allegati sono coordinati tramite lock filesystem;
- il manifest registra hash SHA-256, dimensione e inventario delle migrazioni, confrontato con il database;
- la verifica esegue `PRAGMA integrity_check` e `PRAGMA foreign_key_check`;
- l’estrazione ZIP rifiuta path traversal, collegamenti simbolici, flussi NTFS alternativi e nomi speciali Windows;
- durante il ripristino le richieste ricevono HTTP 503 e non accedono ai file in sostituzione;
- prima di sostituire lo stato corrente viene creato un backup automatico;
- il ripristino richiede la conferma letterale `RESTORE`;
- un errore di ripristino mantiene la modalità manutenzione fino allo sblocco esplicito `CLEAR`;
- gli archivi non sono cifrati: protezione, copia esterna e retention restano responsabilità operativa dello studio.
