# Prestazioni e capacità

Studio Commesse usa SQLite ed è destinato a uno studio tecnico con accesso concorrente contenuto. Il gate prestazionale verifica dataset deterministici da **30, 200 e 600 commesse**, senza usare o modificare il database operativo.

## Ottimizzazioni applicative

La dashboard calcola i dieci indicatori numerici con una sola query DBAL, invece di query separate per commesse, clienti, attività, ore e utenti. Le tabelle recenti restano tre query indipendenti e limitate.

Le aree economia, controllo e report mensile usano aggregazioni SQL complessive: il numero di query non cresce linearmente con il numero di commesse. Gli indici M9.2-G coprono liste attive e recenti, intervalli temporali di ore/spese/incassi e filtri audit.

## Benchmark isolato 30/200/600

Esecuzione completa:

```powershell
.\scripts\benchmark-capacity.ps1
```

Esecuzione di un solo profilo:

```powershell
.\scripts\benchmark-capacity.ps1 -Profiles 600 -Iterations 3
```

Lo script crea un database SQLite temporaneo sotto `var/performance`, applica tutte le migrazioni, carica il dataset e misura:

- dashboard;
- elenco commesse;
- attività personali;
- ore e riepilogo;
- controllo;
- economia;
- report mensile;
- audit;
- dettaglio commessa;
- creazione, verifica e ripristino backup.

Per ogni percorso vengono registrati mediana, percentile 95 e picco di memoria. I rapporti JSON sono scritti in `var/performance/results` e non entrano nel pacchetto di distribuzione.

I budget sono volutamente ampi per assorbire differenze tra workstation Windows. Servono a intercettare regressioni rilevanti, non a confrontare computer diversi.

## Diagnosi HTTP

Con un server già avviato:

```powershell
.\scripts\diagnose-performance.ps1 -Username demo.socio
```

La modalità HTTP confronta richieste autenticate ripetute. Per isolare il solo bootstrap Symfony:

```powershell
.\scripts\diagnose-performance.ps1 -SkipHttp
```

## Interpretazione

- tutte le rotte lente: controllare Xdebug, OPcache, antivirus, filesystem e ambiente `dev`;
- solo report o controllo lenti: verificare periodo selezionato e volume delle aggregazioni;
- solo ore lente: verificare filtri, paginazione e intervallo temporale;
- backup lento: verificare dimensione degli allegati e velocità del disco;
- prima esecuzione lenta e successive rapide: cache Symfony e autoloader non ancora riscaldati.

Le misure devono essere raccolte in ambiente `prod` o `test` senza debug quando si valuta la capacità reale.

Il database SQLite di ciascun profilo viene creato implicitamente dalla prima migrazione. Lo script non usa `doctrine:database:create`, non supportato dalla piattaforma SQLite.
