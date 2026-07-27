# Checklist M9.1

## Gate automatico

1. `setup.ps1 -SkipPartnerBootstrap` termina senza errori.
2. `validate.ps1` termina con `M9.1 HOTFIX 2 VALIDATION PASSED`.
3. PHPStan livello 8 non riporta errori.
4. PHPUnit è completamente verde.
5. Migrazioni e mapping Doctrine sono sincronizzati.
6. `scripts/backup-contract.php` passa.
7. Lo smoke test crea e verifica un backup del database di test.
8. Non risultano nuove migrazioni o dipendenze.

## Creazione backup

1. Eseguire `.\scripts\backup.ps1` con applicazione avviata.
2. Verificare la presenza di `StudioCommesse_Backup_*.zip` nella cartella scelta.
3. Controllare che lo script dichiari il backup creato e verificato.
4. Aprire lo ZIP e verificare la presenza di `manifest.json`, `database.sqlite` e `attachments`.
5. Eseguire `.\scripts\verify-backup.ps1 -Archive <file.zip>`.
6. Caricare o eliminare un allegato mentre viene avviato un backup e verificare che entrambe le operazioni terminino senza stato incoerente.

## Verifica manomissioni

Su una copia non operativa dello ZIP:

1. modificare un allegato ed eseguire la verifica: deve fallire;
2. modificare `database.sqlite`: deve fallire;
3. aggiungere un file estraneo sotto `attachments`: deve fallire;
4. modificare hash o dimensioni nel manifest: deve fallire;
5. modificare l’inventario delle migrazioni nel manifest: deve fallire;
6. provare su una copia dello ZIP un percorso `..`, un nome `file:stream` o un nome riservato come `CON`: l’estrazione deve fallire.

## Ripristino controllato

Eseguire soltanto su una copia di prova o dopo aver creato un backup valido:

1. modificare alcuni dati e aggiungere un allegato riconoscibile;
2. eseguire `.\scripts\restore-backup.ps1 -Archive <backup.zip> -Confirm RESTORE`;
3. verificare che durante l’operazione l’applicazione mostri la pagina di manutenzione 503;
4. verificare che dati e allegati tornino allo stato del backup;
5. verificare la creazione di `StudioCommesse_PreRestore_*.zip`;
6. verificare il backup pre-ripristino con `verify-backup.ps1`;
7. ripristinare, in ambiente di prova, il backup pre-ripristino e confermare il ritorno allo stato successivo originale;
8. simulare un errore controllato e verificare che la manutenzione resti attiva fino a `.\scripts\clear-maintenance.ps1 -Confirm CLEAR`.

## Regressioni M1–M8

- login, ruoli e audit restano operativi;
- commesse, attività, ore ed economia restano modificabili secondo i permessi;
- allegati restano fuori da `public` e scaricabili con autorizzazione;
- report mensile e CSV restano riservati ai soci;
- DataTables, layout responsive e form espliciti restano invariati;
- fixtures 30/200/600 restano idempotenti.

- verificare i filtri vuoti del report Ore, il riepilogo importi dovuti per cliente e la selezione automatica dell’ultimo backup;
