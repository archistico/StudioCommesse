# Backup e ripristino coordinato

## Obiettivo

M9.1 salva e ripristina come un’unica unità:

- database SQLite;
- allegati protetti in `var/storage/attachments`;
- manifest versionato con dimensioni, hash SHA-256 e inventario delle migrazioni, verificato anche contro il database salvato.

Il formato corrente è `studio-commesse-backup-v1`.

## Creazione del backup

```powershell
.\scripts\backup.ps1
```

Per scegliere una destinazione diversa:

```powershell
.\scripts\backup.ps1 -DestinationDirectory "D:\Backup\StudioCommesse"
```

Lo script:

1. crea una copia SQLite coerente tramite `VACUUM INTO`;
2. blocca per il tempo necessario caricamenti ed eliminazioni documentali già in corso o nuovi;
3. copia soltanto gli allegati referenziati dal database fotografato;
4. verifica dimensione e SHA-256 di database e file;
5. esegue `PRAGMA integrity_check` e `PRAGMA foreign_key_check`;
6. crea lo ZIP;
7. riestrae e verifica lo ZIP prodotto.

Il server può rimanere avviato durante la creazione del backup. Le normali letture e le modifiche non documentali possono proseguire; lo snapshot SQLite resta coerente nel proprio istante di acquisizione.

## Verifica di un archivio

```powershell
.\scripts\verify-backup.ps1 -Archive ".\backups\StudioCommesse_Backup_20260727-230000.zip"
```

La verifica rifiuta:

- manifest incompleti o di formato sconosciuto;
- database corrotti o con violazioni delle chiavi esterne;
- hash o dimensioni differenti;
- allegati mancanti o non referenziati;
- chiavi storage non valide;
- percorsi ZIP che tentano di uscire dalla directory temporanea;
- collegamenti simbolici nell’archivio;
- percorsi assoluti, flussi NTFS alternativi, nomi riservati Windows e segmenti ambigui.

## Ripristino

```powershell
.\scripts\restore-backup.ps1 `
    -Archive ".\backups\StudioCommesse_Backup_20260727-230000.zip" `
    -Confirm RESTORE
```

Durante il ripristino:

1. l’archivio sorgente viene verificato;
2. viene attivata la modalità manutenzione;
3. le richieste web già attive vengono lasciate terminare;
4. le nuove richieste ricevono HTTP 503;
5. viene creato un backup automatico dello stato che sta per essere sostituito;
6. database e allegati vengono sostituiti come coppia;
7. il risultato viene nuovamente verificato;
8. vengono applicate eventuali migrazioni e verificato lo schema;
9. il backup precedente al ripristino viene verificato e compresso come `StudioCommesse_PreRestore_*.zip`;
10. la modalità manutenzione viene disattivata soltanto al termine dell’intero ciclo.

Il valore `RESTORE` è intenzionalmente obbligatorio e sensibile alle maiuscole.

Dopo un ripristino proveniente da una versione applicativa precedente, eseguire:

```powershell
.\scripts\setup.ps1 -SkipPartnerBootstrap
.\scripts\validate.ps1
```

## Ripristino fallito

Se la sostituzione, la migrazione o la verifica non viene completata:

- il gestore tenta il rollback immediato quando la sostituzione non è ancora conclusa;
- il backup automatico pre-ripristino non viene eliminato;
- lo script mostra la directory non compressa da conservare, se non è riuscito a generare lo ZIP;
- la modalità manutenzione resta attiva per impedire l’uso di uno stato non verificato.

Dopo aver controllato o riparato database e allegati, la manutenzione può essere rimossa esplicitamente con:

```powershell
.\scripts\clear-maintenance.ps1 -Confirm CLEAR
```

Non cancellare il backup di sicurezza fino alla risoluzione del problema.

## Conservazione

Gli archivi contengono dati gestionali e documenti potenzialmente riservati. Non sono cifrati automaticamente.

Prassi consigliata:

- almeno un backup giornaliero nei giorni lavorativi;
- almeno una copia su supporto o host differente;
- rotazione separata giornaliera, settimanale e mensile;
- verifica periodica con `verify-backup.ps1`;
- prova di ripristino su una copia non produttiva;
- accesso filesystem limitato ai soli amministratori autorizzati.

La cartella `backups` è esclusa dal pacchetto sorgente e dal controllo versione.

## Comandi Symfony sottostanti

Gli script PowerShell usano questi comandi:

```powershell
php bin/console app:backup:create <directory>
php bin/console app:backup:verify <directory>
php bin/console app:backup:restore <directory> --safety-backup-dir=<directory> --confirm=RESTORE
php bin/console app:maintenance:enable --confirm=MAINTENANCE
php bin/console app:maintenance:disable --confirm=CLEAR
```

Sono disponibili per diagnosi e automazioni controllate, ma per l’uso ordinario sono preferibili gli script PowerShell, che gestiscono ZIP, estrazione sicura e backup pre-ripristino.

## Selezione dell’archivio da verificare

Se `-Archive` viene omesso, viene selezionato il più recente `StudioCommesse_Backup_*.zip` nella cartella `backups`. Sono supportati anche pattern espliciti. Un percorso inesistente non viene sostituito silenziosamente: lo script elenca fino a cinque backup disponibili.
