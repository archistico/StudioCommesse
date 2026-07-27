# Packaging della distribuzione

## Obiettivo

Il pacchetto di distribuzione contiene soltanto codice, configurazioni distribuibili, lock file, migrazioni, test, script e documentazione. Non deve contenere dati o segreti dell’installazione usata per costruirlo.

## Creazione

Dalla radice del progetto:

```powershell
.\scripts\package-release.ps1
```

Output predefinito:

```text
dist/StudioCommesse_M9.2-A_Hotfix1_PowerShell_Parser.zip
```

Per scegliere un percorso diverso:

```powershell
.\scripts\package-release.ps1 -OutputPath "E:\rilasci\StudioCommesse_M9.2-A.zip"
```

Usare `-Force` soltanto per sostituire consapevolmente un archivio già esistente.

## Elementi esclusi

Il comando esclude automaticamente:

- `.env.local` e `.env.*.local`;
- `vendor`, `node_modules` e `public/vendor`;
- `var`, inclusi database, allegati, lock, cache e log;
- `backups` e `dist`;
- metadati `.git`, `.idea` e `.vscode`;
- database SQLite e relativi sidecar;
- log, cache, file temporanei, copie di sicurezza e ZIP annidati;
- file tipici del sistema operativo come `Thumbs.db` e `.DS_Store`.

`.env.local.dist` resta incluso perché è il modello usato dal setup per generare una configurazione locale nuova e un `APP_SECRET` casuale.

## Verifica automatica

Prima di confermare il pacchetto, lo script riapre lo ZIP e controlla:

- inventario minimo obbligatorio;
- assenza dei percorsi vietati;
- assenza di traversal, percorsi assoluti, backslash e flussi NTFS alternativi;
- assenza di nomi duplicati senza distinzione tra maiuscole e minuscole;
- presenza di almeno un file applicativo.

Il gate `validate.ps1` esegue lo stesso flusso su uno ZIP temporaneo e lo elimina al termine.

## Aggiornamento

Un pacchetto applicativo non contiene dati dell’installazione. Prima dell’aggiornamento preservare e non sovrascrivere:

- `.env.local`;
- il database configurato;
- `var/storage/attachments`;
- `backups`.

Dopo l’estrazione eseguire:

```powershell
.\scripts\setup.ps1 -SkipPartnerBootstrap
.\scripts\validate.ps1
```

## Confine con i backup

Il pacchetto di distribuzione e il backup operativo hanno scopi diversi:

- il pacchetto distribuisce il software senza dati;
- il backup salva database e allegati di un’installazione specifica.

Non usare il pacchetto di distribuzione come sostituto del backup.

## M9.2-A Hotfix 1

Corregge esclusivamente la sintassi PowerShell del ciclo ricorsivo in `package-release.ps1`; nessuna funzione applicativa è stata modificata. Gate: `M9.2-A HOTFIX 1 VALIDATION PASSED`.
