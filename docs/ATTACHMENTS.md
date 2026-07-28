# Allegati e documenti

## Modello

Ogni documento appartiene obbligatoriamente a una commessa e può essere collegato facoltativamente a una singola attività della stessa commessa.

I metadati registrati sono:

- nome originale;
- classificazione: tecnico, contrattuale, amministrativo, comunicazione o altro;
- commessa e attività opzionale;
- descrizione;
- autore e data del caricamento;
- MIME rilevato dal contenuto;
- dimensione;
- impronta SHA-256;
- chiave casuale usata nello spazio documentale.

## Archiviazione protetta

I file non vengono salvati sotto `public` e non sono raggiungibili tramite URL statici. La directory predefinita è:

```text
var/storage/attachments
```

Il download passa sempre dal controller Symfony, che richiede autenticazione, applica il voter e invia:

- `Content-Disposition: attachment`;
- `X-Content-Type-Options: nosniff`;
- cache privata disabilitata.

Il nome fisico è casuale e non contiene il nome fornito dall’utente.

## Limiti e formati

Limite massimo: **10 MiB per file**.

Formati ammessi:

- PDF;
- PNG, JPG/JPEG e WEBP;
- TXT e CSV;
- DOCX e XLSX senza macro.

La validazione combina estensione, MIME rilevato con `fileinfo` e firma iniziale del contenuto. Sono rifiutati formati eseguibili, estensioni non autorizzate, file vuoti, incongruenze tra estensione e contenuto e la firma di test EICAR.

Questi controlli riducono il rischio ma non sostituiscono un motore antivirus centralizzato. Per installazioni esposte a file provenienti dall’esterno, un antivirus di sistema può sorvegliare `var/storage/attachments` senza rendere pubblici i file.

## Permessi

- tutti gli utenti autenticati possono consultare e scaricare i documenti, coerentemente con la visibilità generale delle commesse;
- tutti i collaboratori possono caricare documenti su commesse non archiviate;
- possono modificare classificazione, collegamento e descrizione, oppure eliminare il documento:
  - i soci;
  - chi ha caricato il file;
  - il responsabile della commessa;
  - l’assegnatario o il creatore dell’attività collegata;
- le commesse archiviate restano consultabili ma non accettano nuovi caricamenti.

## Interfaccia

- `Documenti` nel menu apre la vista trasversale `/documenti`;
- il dettaglio commessa apre `/commesse/{id}/documenti`;
- dalla modifica attività il collegamento apre lo stesso archivio con l’attività preselezionata;
- le tabelle non contengono colonne generiche di azione;
- il nome del file apre la pagina documento;
- download, modifica metadati ed eliminazione sono concentrati nella pagina documento.

## Installazione e backup

`setup.ps1`, `setup.sh` e `validate.ps1` eseguono prima `scripts/php-runtime-contract.php`, che controlla PHP 8.4+, `fileinfo` e le altre estensioni richieste. Se `fileinfo` manca su Windows, il diagnostico mostra il `php.ini` caricato dalla CLI, `extension_dir` e la presenza di `php_fileinfo.dll`. Nel file indicato va abilitata `extension=fileinfo`; dopo aver riaperto il terminale verificare con `php --ini` e `php -m | Select-String -Pattern '^fileinfo$'`. La scrivibilità dello spazio documentale viene controllata soltanto dopo il superamento del preflight. Il server PHP integrato avviato da `start-server.ps1` imposta `upload_max_filesize=10M` e `post_max_size=12M`; su IIS, Apache o altri server gli stessi limiti devono essere configurati nel relativo `php.ini`.

Il backup applicativo deve includere insieme:

- il database SQLite;
- l’intera directory `var/storage/attachments`.

Ripristinare soltanto uno dei due elementi produce riferimenti incompleti.
