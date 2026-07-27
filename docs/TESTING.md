# Testing

Il gate autoritativo è:

```powershell
.\scripts\validate.ps1
```

Esito atteso:

```text
M9.2-A HOTFIX 1 VALIDATION PASSED
```

## Baseline di regressione

La baseline M9.1 Hotfix 3 corretta è stata validata con **171 test e 1.631 asserzioni**. M9.2-A non modifica il comportamento applicativo, lo schema Doctrine o le dipendenze: tutta la suite M1–M9.1 deve restare verde.

## Controlli eseguiti dal gate

- sintassi degli script PowerShell;
- validazione Composer, installazione bloccata e audit;
- installazione npm, audit, build e test asset;
- lint PHP, YAML e Twig;
- contratti Doctrine, Symfony, storage, report mensile e backup;
- contratto M9.2-A di baseline e packaging;
- migrazioni su database di test pulito e schema sincronizzato;
- creazione e verifica di un backup smoke;
- creazione e verifica di un pacchetto di distribuzione smoke;
- PHPStan livello 8;
- PHPUnit completo.

## Contratto M9.2-A

Il packaging deve:

- includere sorgenti, configurazioni distributive, lock file, migrazioni, test, script e documentazione;
- escludere `.env.local` e ogni `.env.*.local`;
- escludere `vendor`, `node_modules`, `public/vendor`, `var`, `backups`, `dist` e metadati degli IDE/VCS;
- escludere database SQLite, sidecar, log, cache, ZIP annidati e file temporanei;
- rifiutare nomi ZIP assoluti, ambigui, duplicati o contenenti traversal;
- verificare la presenza dei file minimi richiesti;
- non cancellare né modificare i dati della cartella di lavoro.

## Copertura funzionale preservata

Restano in regressione:

- login, ruoli, utenti e audit;
- clienti, commesse, attività, ore e timer;
- economia differenziata per ruolo e dovuti per cliente;
- controllo, valutazione collaboratori e report mensile;
- responsive, DataTables e form espliciti;
- allegati protetti;
- backup, verifica e ripristino coordinato;
- fixtures 30/200/600 idempotenti.

## M9.2-A Hotfix 1

Corregge esclusivamente la sintassi PowerShell del ciclo ricorsivo in `package-release.ps1`; nessuna funzione applicativa è stata modificata. Gate: `M9.2-A HOTFIX 1 VALIDATION PASSED`.
