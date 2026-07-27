# Diagnosi e prestazioni

## Perché `php -S` può sembrare lento

Il server integrato di PHP è destinato allo sviluppo e, su Windows, gestisce le richieste con un solo processo. In ambiente Symfony `dev`, debug, logging e profilazione possono inoltre aggiungere lavoro a ogni query e a ogni richiesta, soprattutto su Windows quando antivirus e filesystem controllano molti file PHP e log.

La base M5 Hotfix 4 riduce il costo applicativo in quattro punti:

1. disattiva in `dev` logging e profiling DBAL non utilizzati;
2. non raccoglie più il backtrace di ogni query Doctrine;
3. aggrega le durate di tutte le attività con una sola query;
4. aggrega l'economia di tutte le commesse con tre query totali, invece di tre query per commessa.

## Avvio consigliato

Sviluppo, con ricostruzione automatica della cache:

```powershell
.\scripts\start-server.ps1
```

Confronto rapido senza debug:

```powershell
.\scripts\start-server.ps1 -Mode fast
```

La modalità `fast` usa `APP_ENV=prod` e `APP_DEBUG=0`; dopo modifiche a codice o template va riavviata, perché la cache di produzione non viene ricostruita automaticamente.

## Diagnosi ripetibile

Con il server già avviato:

```powershell
.\scripts\diagnose-performance.ps1 -Username demo.socio
```

Lo script:

- mostra PHP, `php.ini`, OPcache e Xdebug;
- riporta dimensione e conteggi del database SQLite;
- confronta il bootstrap Symfony `dev` e `prod`;
- effettua richieste autenticate ripetute a dashboard, commesse, attività, ore, controllo, economia e dettaglio commessa;
- stampa mediana e media per ogni percorso.

Per eseguire soltanto la diagnosi locale senza HTTP:

```powershell
.\scripts\diagnose-performance.ps1 -SkipHttp
```

## Interpretazione

- Tutte le rotte lente in modo simile: controllare Xdebug, OPcache CLI, antivirus e ambiente `dev`.
- Solo `/economia` lenta: controllare volume di commesse, ore, spese e incassi.
- Solo `/controllo` lenta: controllare intervallo selezionato e volume delle aggregazioni trasversali.
- Solo `/ore` lenta: controllare volume delle registrazioni, filtri e paginazione.
- Solo il dettaglio commessa lento: controllare numero di attività e registrazioni della commessa.
- Prima richiesta lenta e successive rapide: riscaldamento della cache Symfony e dell'autoloader.
- Modalità `fast` molto più rapida di `dev`: il collo di bottiglia è soprattutto strumentazione di sviluppo, non SQLite o Twig.
