# Manuale amministratore

## Responsabilità

L’amministratore gestisce installazione, account, aggiornamenti, backup, configurazione Apache e risposta agli incidenti. Le attività applicative dei Soci restano tracciate nell’Audit.

## Installazione e aggiornamento

Per una nuova installazione usare `scripts/setup.ps1`. Per un aggiornamento estrarre la release in una cartella separata e usare `scripts/update.ps1` indicando l’installazione esistente. La procedura completa è in `docs/INSTALL_UPDATE.md`.

Prima di intervenire su produzione:

- eseguire `scripts/validate.ps1` sulla release;
- creare e verificare un backup;
- verificare spazio libero e permessi;
- informare gli utenti della finestra di manutenzione.

## Cancellazione di una commessa

Solo un Socio può usare **Modifica commessa → Cancella commessa**. La pagina di conferma mostra attività, ore, timer attivi, spese, incassi e documenti coinvolti. La conferma cancella definitivamente la commessa e questi dati, inclusi i file dei documenti, conservando clienti, utenti e cronologia audit. Per una commessa archiviata occorre prima il ripristino. Se la transazione non riesce, i dati e i file vengono conservati; l’operazione riuscita viene registrata nell’audit.

## Account

I Soci accedono agli account da **Impostazioni → Gestisci utenti**. Nella modifica di un utente, **Elimina utente** apre una pagina di conferma con i conteggi dei dati collegati. L’eliminazione è consentita soltanto senza commesse (anche archiviate), attività assegnate o create, ore, timer, spese, incassi e documenti caricati. Il proprio account e l’ultimo socio attivo sono protetti. La cronologia audit viene conservata e l’eliminazione viene registrata.

Ogni persona deve avere un account individuale. Disattivare gli account non più necessari; non riutilizzarli per nuove persone. Assegnare il ruolo Socio soltanto a chi deve vedere dati economici, utenti e audit.

Le password devono essere lunghe e uniche. Il sistema usa l’algoritmo di hashing selezionato automaticamente da Symfony e non memorizza password in chiaro.

## Blocco temporaneo del login

La configurazione corrente consente 5 fallimenti in un’ora per combinazione utenza e IP. Il sesto tentativo viene rifiutato anche se la password è corretta. Il blocco scade automaticamente.

Un Socio può verificare gli eventi **Accesso non riuscito** e **Accesso temporaneamente bloccato** nella pagina Audit. Non cancellare manualmente cache o log per aggirare un blocco durante un possibile incidente; prima verificare l’origine dei tentativi.

## HTTPS e Apache

Usare sempre HTTPS quando l’applicazione contiene dati personali. Il `DocumentRoot` deve essere la cartella `public`. Vedere `docs/APACHE.md` e `docs/SECURITY.md`.

## Backup

Da **Impostazioni → Scarica database** un Socio può scaricare una copia SQLite coerente del database, con utenti e dati applicativi. Il file temporaneo viene eliminato dopo l’invio. Il download non comprende i file dei documenti: per un ripristino completo resta necessaria la procedura di backup coordinato con gli allegati.

**Impostazioni → Svuota database** cancella tutti i dati applicativi, inclusi audit, registrazioni dei documenti e commesse archiviate, conservando integralmente utenti, credenziali, ruoli, tariffe personali e migrazioni. Richiede un token CSRF valido e la conferma scritta `CANCELLA DATI`. In presenza di altre richieste attive l’operazione viene rifiutata e può essere ritentata. I file degli allegati restano sul server e non sono più consultabili nel gestionale. Scaricare un backup prima di procedere.

Eseguire backup regolari di database e allegati. Ogni backup deve essere verificato con `verify-backup.ps1`. Conservare almeno una copia fuori dal server applicativo, cifrata e con accesso limitato.

Provare periodicamente il ripristino in un ambiente non produttivo. Non considerare valido un backup mai ripristinato.

## Log e audit

- `security-audit.log`: eventi applicativi in formato JSON con dati minimizzati;
- `operations.log`: anomalie operative ed errori gestiti;
- database Audit: registro consultabile dai Soci.

Proteggere `var/log` e `backups` tramite permessi filesystem. Non pubblicare questi file tramite Apache e non allegarli integralmente a e-mail o ticket esterni.

## Controlli periodici

Almeno mensilmente:

- verificare account attivi e ruoli;
- controllare accessi falliti e blocchi;
- verificare l’ultimo backup;
- applicare gli aggiornamenti con la procedura validata;
- controllare certificato HTTPS e spazio disco;
- eseguire il benchmark di capacità dopo modifiche infrastrutturali rilevanti.
