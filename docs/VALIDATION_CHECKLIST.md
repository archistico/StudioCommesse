# Checklist M9.2-A

## Gate automatico

1. `setup.ps1 -SkipPartnerBootstrap` termina senza errori.
2. `validate.ps1` termina con `M9.2-A HOTFIX 1 VALIDATION PASSED`.
3. PHPStan livello 8 non riporta errori.
4. PHPUnit è completamente verde.
5. Migrazioni e mapping Doctrine sono sincronizzati.
6. `scripts/m92a-packaging-contract.php` passa.
7. Il gate crea e verifica un pacchetto smoke, poi lo rimuove.
8. Non risultano nuove migrazioni o dipendenze.

## Verifica baseline

1. Versione applicativa: `0.9.2-M9.2-A-HF1`.
2. Baseline dichiarata: `StudioCommesse_M9.1_Hotfix3_Corretto.zip`.
3. La documentazione registra 171 test e 1.631 asserzioni sulla baseline.
4. Le correzioni manuali su `$safetyBackupDirectory`, nota Attività e dovuti Clienti sono documentate.

## Pacchetto di distribuzione

1. Eseguire `.\scripts\package-release.ps1`.
2. Verificare la creazione di `dist/StudioCommesse_M9.2-A_Hotfix1_PowerShell_Parser.zip`.
3. Confermare che lo script dichiari il pacchetto verificato.
4. Aprire lo ZIP e verificare che contenga almeno README, lock file, `src`, `templates`, `migrations`, `scripts`, `tests` e `docs`.
5. Verificare l’assenza di `.env.local`, database, allegati, backup, log, cache, `vendor`, `node_modules` e `public/vendor`.
6. Estrarre lo ZIP in una cartella nuova ed eseguire il setup.

## Aggiornamento di un’installazione esistente

Prima di sostituire i file applicativi preservare:

- `.env.local`;
- database indicato da `DATABASE_URL`;
- `var/storage/attachments`;
- cartella `backups`;
- eventuali log utili alla diagnosi.

Dopo la copia eseguire setup e validazione senza caricare fixtures.

## Regressioni M1–M9.1

- accessi e riservatezza invariati;
- ore, economia, report e dovuti invariati;
- allegati e backup invariati;
- nessuna nuova migrazione;
- nessuna nuova dipendenza;
- fixtures standard ancora idempotenti.

## Hotfix 1 PowerShell

1. `validate.ps1` analizza tutti gli script `.ps1` senza errori.
2. PowerShell analizza `package-release.ps1` senza errori.
3. `package-release.ps1` crea e verifica lo ZIP smoke.
4. Il gate finale è `M9.2-A HOTFIX 1 VALIDATION PASSED`.
