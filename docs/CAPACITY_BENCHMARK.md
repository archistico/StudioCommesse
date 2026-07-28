# Benchmark di capacità

## Profili autoritativi

| Profilo | Utenti | Clienti | Commesse | Attività | Ore | Spese | Incassi | Audit | Allegati |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 30 | 8 | 10 | 30 | 200 | 600 | 120 | 60 | 300 | 6 |
| 200 | 16 | 40 | 200 | 1.400 | 4.200 | 800 | 400 | 2.000 | 40 |
| 600 | 32 | 100 | 600 | 4.200 | 12.600 | 2.400 | 1.200 | 6.000 | 120 |

I dataset sono deterministici e contengono stati, priorità, date, utenti disattivati, timer aperti, costi storici, movimenti economici, audit e allegati fisici minimi.

## Sicurezza

`benchmark-capacity.ps1` forza `APP_ENV=test`, usa un `DATABASE_URL` temporaneo e pulisce allegati, lock e marker di manutenzione di test. Non deve essere adattato per puntare al database operativo.

Il comando di seed richiede sempre:

```text
--confirm=BENCHMARK
```

## Gate

Il benchmark fallisce se una mediana supera il budget del profilo. I file JSON permettono di confrontare esecuzioni successive sulla stessa workstation.

Il gate ordinario esegue i tre profili con una ripetizione per contenere la durata. Per una misura più stabile usare almeno tre iterazioni manuali.

## Inizializzazione SQLite

Per ogni profilo lo script elimina l’eventuale file precedente e avvia direttamente `doctrine:migrations:migrate`. SQLite crea il nuovo file al primo collegamento; `doctrine:database:create` non viene usato perché l’operazione di enumerazione dei database non è supportata dalla piattaforma SQLite.

## Allegati e backup

Il seeder crea realmente ogni allegato sotto la directory isolata di test. Le chiavi usano lo stesso formato accettato dal sottosistema documentale e dal backup:

```text
YYYY/MM/<digest-esadecimale-di-32-caratteri>.<estensione>
```

Metadati nel database, dimensione, SHA-256 e file fisico devono rimanere coerenti. Il benchmark di backup verifica create, verify e restore anche quando il profilo contiene allegati.

