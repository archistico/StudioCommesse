# Robustezza, transazioni ed error handling

## Obiettivo M9.2-C

M9.2-C rende coerenti mutazioni, audit e risorse su filesystem senza cambiare il modello funzionale. La milestone, comprensiva di M9.2-A Hotfix 2 e M9.2-B, è stata validata cumulativamente con M9.2-C Hotfix 3: 203 test, 2.125 asserzioni e PHPStan senza errori.

## Transazioni applicative

`AuditedTransaction` applica il seguente ordine:

1. configura SQLite con `busy_timeout = 5000` e chiavi esterne attive;
2. esegue la mutazione dentro `EntityManagerInterface::wrapInTransaction()`;
3. effettua un primo flush per assegnare gli identificativi e verificare i vincoli;
4. registra l’evento di audit nello stesso database;
5. effettua il secondo flush;
6. esegue il commit;
7. scrive il mirror Monolog soltanto dopo il commit riuscito.

Se la mutazione o l’audit falliscono, la transazione database viene annullata. I controller CRUD principali non eseguono più un flush autonomo prima dell’audit.

## Ore e timer concorrenti

Inserimento manuale, modifica, avvio e arresto timer condividono `TimerMutationLock`.

Il lock serializza la sequenza:

- controllo timer già attivo o sovrapposizione;
- calcolo tariffa e costo;
- persistenza;
- audit.

Questo evita che due richieste simultanee della stessa installazione superino entrambe il controllo preliminare. Se il lock è già occupato, la seconda richiesta riceve un errore 503 controllato invece di attendere indefinitamente. Il supporto ufficiale resta un singolo server applicativo Windows con filesystem locale condiviso con SQLite.

## Allegati e compensazione filesystem

Le mutazioni documentali falliscono rapidamente con 503 quando backup o ripristino detengono il lock esclusivo. Il caricamento conserva inoltre il comportamento compensativo: se il database non accetta il record, il file appena scritto viene eliminato.

L’eliminazione usa invece una quarantena:

1. il file viene spostato fuori dallo storage attivo;
2. record e audit vengono eliminati nella stessa transazione;
3. se la transazione fallisce, il file viene ripristinato;
4. dopo il commit il file in quarantena viene eliminato definitivamente;
5. se la cancellazione finale fallisce, il file resta isolato e viene scritto un warning tecnico, senza ricomparire nello storage attivo o nei backup.

## Manutenzione e lock richieste

Le nuove richieste non attendono più indefinitamente il lock esclusivo del ripristino:

- se il marker di manutenzione è già presente, ricevono subito HTTP 503;
- se il lock condiviso non è ottenibile immediatamente, ricevono HTTP 503;
- dopo l’acquisizione viene ricontrollato il marker per chiudere la finestra di gara.

Le richieste già avviate mantengono il lock condiviso fino a terminazione o eccezione.

## Errori pubblici

Sono disponibili pagine dedicate per:

- 403 accesso negato;
- 404 risorsa non trovata;
- 405 metodo non consentito;
- 409 conflitto o vincolo univoco;
- 422 dati non applicabili;
- 500 errore interno;
- 503 manutenzione o database temporaneamente occupato.

Nelle risposte non vengono mostrati stack trace, query SQL, percorsi locali o nomi fisici degli allegati.

## Identificativo richiesta

Ogni risposta principale contiene l’header:

```text
X-Request-ID
```

Un identificativo in ingresso viene conservato soltanto se rispetta un formato sicuro; altrimenti ne viene generato uno casuale. Le pagine 500 e 503 mostrano il riferimento, utile per correlare il problema con i log senza esporre dettagli tecnici.

## Errori database intercettati

`DatabaseExceptionSubscriber` converte:

- database SQLite occupato o lock timeout in HTTP 503 con `Retry-After: 2`;
- violazioni di unicità non gestite dal form in HTTP 409.

Gli altri errori continuano attraverso il normale gestore Symfony e la pagina sicura 500.

## Gate

Il gate M9.2-C verifica:

- transazione e audit nello stesso commit;
- assenza di flush autonomi nei controller auditati;
- lock su tutte le mutazioni delle ore;
- quarantena e ripristino degli allegati;
- risposta non bloccante durante manutenzione;
- pagine errore e request ID;
- test funzionali e unitari dedicati;
- PHPStan livello 8 e suite PHPUnit completa.
